<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Contracts\Services\PricingServiceInterface;
use App\Support\MoneyParser;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Ghi lại dòng hàng và tổng tiền của đơn từ mảng `items[]` gửi lên.
 *
 * ## Nguồn gốc
 * Tách nguyên khối từ `OrderController::egoSyncOrderItemsAndTotalFromRequest`
 * (~285 dòng trong controller). Bản cũ có 3 vấn đề đã xử lý ở đây:
 *
 * 1. **Trùng lặp**: hai khối "EGO FIX" và "EGO FINAL FIX" dài ~45 dòng làm
 *    ĐÚNG một việc (quy đổi giá trước VAT → sau VAT), chỉ khác tên biến. Khối
 *    thứ hai chạy lại y hệt trên kết quả của khối thứ nhất.
 * 2. **N+1**: mỗi dòng hàng bắn thêm truy vấn `crm_product_prices` và gọi
 *    `getColumnListing()` HAI lần. Đơn 20 dòng ⇒ hơn 60 truy vấn thừa.
 *    Nay nạp một lần toàn bộ bảng giá của các cặp (sản phẩm, tầng giá) xuất
 *    hiện trong request rồi tra bằng bảng băm.
 * 3. **Nghiệp vụ nằm trong controller**: công thức tiền/VAT/chiết khấu không
 *    test được nếu không đi qua HTTP.
 *
 * ## Quy ước nghiệp vụ (giữ nguyên, đã chốt bằng test)
 * - `crm_order_items.unit_price` là giá **đã gồm VAT**. Form có thể gửi giá
 *   trước VAT lấy từ bảng giá đại lý; nếu khớp thì quy đổi sang giá sau VAT.
 * - `total_amount` của đơn = tổng tiền dòng sau chiết khấu.
 * - `tax_amount` tách ngược từ giá đã gồm VAT.
 * - Dòng thiếu sản phẩm/kho/đơn giá bị bỏ qua; nếu không còn dòng hợp lệ nào
 *   thì KHÔNG đụng tới tổng tiền của đơn.
 */
final class OrderItemSyncService
{
    /** Sai số cho phép khi đối chiếu đơn giá với bảng giá (đồng). */
    private const PRICE_MATCH_TOLERANCE = 1.0;

    /** Các tên cột chứa % VAT từng gặp trong bảng giá. */
    private const VAT_COLUMN_CANDIDATES = ['vat_percent', 'vat', 'tax_percent'];

    public function __construct(
        private readonly PricingServiceInterface $pricingService,
    ) {}

    /**
     * Đồng bộ dòng hàng + tổng tiền cho một đơn.
     *
     * @param  array<int, mixed>  $items  Mảng items[] từ request
     */
    public function sync(int $orderId, array $items): void
    {
        if ($items === []) {
            return;
        }

        $orderTable = $this->resolveTable(['crm_orders', 'orders']);
        $itemTable = $this->resolveTable(['crm_order_items', 'order_items']);

        if ($orderTable === null || $itemTable === null) {
            return;
        }

        $rows = $this->normalizeRows($items);

        if ($rows === []) {
            return;
        }

        $tierPrices = $this->loadTierPrices($rows);
        $resolvedVat = $this->pricingService->resolveVatPercentsForOrderItems(array_map(
            static fn (array $row): array => [$row['product_id'], $row['price_tier_id']],
            $rows,
        ));
        $fallbackVat = $this->loadFallbackVatPercents($rows);
        $currentItems = DB::table($itemTable)->where('order_id', $orderId)->get()->keyBy('id');

        $usedItemIds = [];
        $totals = ['amount' => 0.0, 'discount' => 0.0, 'tax' => 0.0];

        foreach ($rows as $row) {
            $line = $this->calculateLine($row, $tierPrices, $resolvedVat, $fallbackVat);

            if ($line === null) {
                continue;
            }

            $usedItemIds[] = $this->writeItem($itemTable, $orderId, $row, $line, $currentItems, $usedItemIds);

            $totals['amount'] += $line['total'];
            $totals['discount'] += $line['discount'];
            $totals['tax'] += $line['tax'];
        }

        if ($totals['amount'] <= 0) {
            return;
        }

        $this->writeOrderTotals($orderTable, $orderId, $totals);
    }

    /**
     * Chuẩn hoá mảng thô từ request thành các dòng đã ép kiểu.
     *
     * @param  array<int, mixed>  $items
     * @return list<array{id: int, product_id: int, warehouse_id: int, price_tier_id: int|null, quantity: int, unit_price: float, discount_percent: float, discount_amount: float, vat_percent: float}>
     */
    private function normalizeRows(array $items): array
    {
        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $rows[] = [
                'id' => (int) ($item['id'] ?? 0),
                'product_id' => (int) ($item['product_id'] ?? 0),
                'warehouse_id' => (int) ($item['warehouse_id'] ?? 0),
                'price_tier_id' => ! empty($item['price_tier_id']) ? (int) $item['price_tier_id'] : null,
                'quantity' => MoneyParser::parseQuantity($item['quantity'] ?? 1, minimum: 1),
                'unit_price' => MoneyParser::parse($item['unit_price'] ?? 0),
                'discount_percent' => MoneyParser::parsePercent($item['discount_percent'] ?? 0),
                'discount_amount' => max(0.0, MoneyParser::parse($item['discount_amount'] ?? 0)),
                'vat_percent' => MoneyParser::parsePercent($item['vat_percent'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Nạp MỘT LẦN bảng giá của mọi cặp (sản phẩm, tầng giá) có trong request.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{price: float, vat: float}> "productId:tierId" => giá & VAT
     */
    private function loadTierPrices(array $rows): array
    {
        if (! SchemaCache::hasColumns('crm_product_prices', ['product_id', 'price_tier_id', 'price'])) {
            return [];
        }

        $pairs = [];

        foreach ($rows as $row) {
            if ($row['product_id'] > 0 && $row['price_tier_id'] !== null) {
                $pairs[$row['product_id']][$row['price_tier_id']] = true;
            }
        }

        if ($pairs === []) {
            return [];
        }

        $vatColumn = $this->resolveVatColumn();

        $records = DB::table('crm_product_prices')
            ->whereIn('product_id', array_keys($pairs))
            ->get();

        $prices = [];

        foreach ($records as $record) {
            $productId = (int) $record->product_id;
            $tierId = (int) $record->price_tier_id;

            if (! isset($pairs[$productId][$tierId])) {
                continue;
            }

            $prices[$productId.':'.$tierId] = [
                'price' => (float) ($record->price ?? 0),
                'vat' => $vatColumn !== null ? (float) ($record->{$vatColumn} ?? 0) : 0.0,
            ];
        }

        return $prices;
    }

    /**
     * % VAT dự phòng cho từng sản phẩm khi PricingService không quyết định được.
     *
     * Giữ đúng hành vi cũ (lấy VAT của tầng giá nhỏ nhất có VAT > 0) nhưng gộp
     * về MỘT truy vấn cho toàn bộ sản phẩm trong request thay vì mỗi dòng một lần.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<int, float> product_id => % VAT
     */
    private function loadFallbackVatPercents(array $rows): array
    {
        if (! SchemaCache::hasColumns('crm_product_prices', ['product_id', 'vat_percent', 'price_tier_id'])) {
            return [];
        }

        $productIds = array_values(array_unique(array_filter(
            array_column($rows, 'product_id'),
            static fn (int $id): bool => $id > 0,
        )));

        if ($productIds === []) {
            return [];
        }

        $records = DB::table('crm_product_prices')
            ->whereIn('product_id', $productIds)
            ->where('vat_percent', '>', 0)
            ->orderBy('price_tier_id')
            ->get(['product_id', 'vat_percent']);

        $fallback = [];

        foreach ($records as $record) {
            $productId = (int) $record->product_id;
            // Bản ghi đầu tiên theo price_tier_id thắng — như `->value()` cũ.
            $fallback[$productId] ??= (float) $record->vat_percent;
        }

        return $fallback;
    }

    /**
     * Tính tiền cho một dòng; trả null nếu dòng không đủ điều kiện lưu.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array{price: float, vat: float}>  $tierPrices
     * @param  array<string, float>  $resolvedVat  % VAT do PricingService quyết định (đã gộp truy vấn)
     * @param  array<int, float>  $fallbackVat
     * @return array{unit_price: float, vat_percent: float, total: float, discount: float, tax: float}|null
     */
    private function calculateLine(array $row, array $tierPrices, array $resolvedVat, array $fallbackVat): ?array
    {
        $productId = (int) $row['product_id'];
        $quantity = (int) $row['quantity'];
        $unitPrice = (float) $row['unit_price'];
        $vatPercent = (float) $row['vat_percent'];

        if ($vatPercent <= 0 && $productId > 0) {
            $key = $productId.':'.($row['price_tier_id'] ?? '');
            $vatPercent = MoneyParser::parsePercent($resolvedVat[$key] ?? 0);

            if ($vatPercent <= 0) {
                $vatPercent = MoneyParser::parsePercent($fallbackVat[$productId] ?? 0);
            }
        }

        [$unitPrice, $vatPercent] = $this->applyTierPrice($row, $tierPrices, $unitPrice, $vatPercent);

        $subtotal = $unitPrice * $quantity;
        $discount = min($subtotal, ($subtotal * (float) $row['discount_percent'] / 100) + (float) $row['discount_amount']);
        $total = max(0.0, $subtotal - $discount);

        $tax = 0.0;
        if ($vatPercent > 0 && $total > 0) {
            $tax = max(0.0, $total - ($total / (1 + ($vatPercent / 100))));
        }

        if ($unitPrice <= 0 && $total > 0 && $quantity > 0) {
            $unitPrice = round($total / $quantity, 2);
        }

        if ($productId <= 0 || (int) $row['warehouse_id'] <= 0 || $unitPrice <= 0) {
            return null;
        }

        return [
            'unit_price' => $unitPrice,
            'vat_percent' => $vatPercent,
            'total' => $total,
            'discount' => $discount,
            'tax' => $tax,
        ];
    }

    /**
     * Quy đổi giá TRƯỚC VAT của bảng giá đại lý sang giá SAU VAT.
     *
     * Bản cũ lặp lại nguyên khối này hai lần (`EGO FIX` và `EGO FINAL FIX`) —
     * cùng một phép tính, chạy hai lượt.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, array{price: float, vat: float}>  $tierPrices
     * @return array{0: float, 1: float} [đơn giá, % VAT]
     */
    private function applyTierPrice(array $row, array $tierPrices, float $unitPrice, float $vatPercent): array
    {
        $tierId = $row['price_tier_id'];

        if ($tierId === null || (int) $row['product_id'] <= 0) {
            return [$unitPrice, $vatPercent];
        }

        $tier = $tierPrices[$row['product_id'].':'.$tierId] ?? null;

        if ($tier === null) {
            return [$unitPrice, $vatPercent];
        }

        if ($vatPercent <= 0 && $tier['vat'] > 0) {
            $vatPercent = MoneyParser::parsePercent($tier['vat']);
        }

        $priceBeforeVat = $tier['price'];

        if ($priceBeforeVat > 0 && $vatPercent > 0
            && abs($unitPrice - $priceBeforeVat) <= self::PRICE_MATCH_TOLERANCE) {
            $unitPrice = round($priceBeforeVat * (1 + $vatPercent / 100), 0);
        }

        return [$unitPrice, $vatPercent];
    }

    /**
     * Ghi một dòng hàng: cập nhật theo id, hoặc theo cặp (sản phẩm, kho), hoặc thêm mới.
     *
     * @param  array<string, mixed>  $row
     * @param  array{unit_price: float, vat_percent: float, total: float, discount: float, tax: float}  $line
     * @param  \Illuminate\Support\Collection<int, object>  $currentItems
     * @param  list<int>  $usedItemIds
     * @return int Id dòng vừa ghi
     */
    private function writeItem(
        string $itemTable,
        int $orderId,
        array $row,
        array $line,
        $currentItems,
        array $usedItemIds,
    ): int {
        $payload = $this->itemPayload($itemTable, $row, $line);

        $itemId = (int) $row['id'];

        if ($itemId > 0 && $currentItems->has($itemId)) {
            DB::table($itemTable)->where('id', $itemId)->where('order_id', $orderId)->update($payload);

            return $itemId;
        }

        $matched = $currentItems->first(
            static fn ($item): bool => ! in_array((int) $item->id, $usedItemIds, true)
                && (int) ($item->product_id ?? 0) === (int) $row['product_id']
                && (int) ($item->warehouse_id ?? 0) === (int) $row['warehouse_id']
        );

        if ($matched !== null) {
            DB::table($itemTable)->where('id', $matched->id)->where('order_id', $orderId)->update($payload);

            return (int) $matched->id;
        }

        $payload['order_id'] = $orderId;

        if (SchemaCache::hasColumn($itemTable, 'created_at')) {
            $payload['created_at'] = now();
        }

        return (int) DB::table($itemTable)->insertGetId($payload);
    }

    /**
     * Payload ghi dòng hàng, chỉ gồm các cột thực sự tồn tại trong schema.
     *
     * @param  array<string, mixed>  $row
     * @param  array{unit_price: float, vat_percent: float, total: float, discount: float, tax: float}  $line
     * @return array<string, mixed>
     */
    private function itemPayload(string $itemTable, array $row, array $line): array
    {
        $candidates = [
            'warehouse_id' => (int) $row['warehouse_id'],
            'product_id' => (int) $row['product_id'],
            'price_tier_id' => $row['price_tier_id'],
            'quantity' => (int) $row['quantity'],
            'unit_price' => $line['unit_price'],
            'vat_percent' => $line['vat_percent'],
            'discount_percent' => (float) $row['discount_percent'],
            'discount_amount' => (float) $row['discount_amount'],
            'updated_at' => now(),
        ];

        return array_filter(
            $candidates,
            static fn (string $column): bool => SchemaCache::hasColumn($itemTable, $column),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Ghi tổng tiền/chiết khấu/thuế lên đơn (chỉ các cột có trong schema).
     *
     * @param  array{amount: float, discount: float, tax: float}  $totals
     */
    private function writeOrderTotals(string $orderTable, int $orderId, array $totals): void
    {
        $payload = array_filter(
            [
                'total_amount' => $totals['amount'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => round($totals['tax'], 2),
                'updated_at' => now(),
            ],
            static fn (string $column): bool => SchemaCache::hasColumn($orderTable, $column),
            ARRAY_FILTER_USE_KEY,
        );

        if ($payload === []) {
            return;
        }

        DB::table($orderTable)->where('id', $orderId)->update($payload);
    }

    /**
     * Tên bảng đầu tiên có thật trong danh sách ứng viên.
     *
     * @param  list<string>  $candidates
     */
    private function resolveTable(array $candidates): ?string
    {
        foreach ($candidates as $table) {
            if (SchemaCache::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    /** Cột chứa % VAT trong bảng giá (schema production đã đổi tên qua các đợt). */
    private function resolveVatColumn(): ?string
    {
        foreach (self::VAT_COLUMN_CANDIDATES as $column) {
            if (SchemaCache::hasColumn('crm_product_prices', $column)) {
                return $column;
            }
        }

        return null;
    }
}
