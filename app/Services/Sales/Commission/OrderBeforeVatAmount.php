<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use App\Support\MoneyParser;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Doanh thu TRƯỚC VAT của một đơn — cơ sở để tính hoa hồng.
 *
 * ## Vì sao phải tính chứ không đọc thẳng
 * Dữ liệu thật của hệ thống này không có sẵn một con số trước VAT đáng tin:
 * - `crm_orders.total_amount` là tổng SAU VAT.
 * - `crm_order_items.unit_price` và `line_total` cũng là SAU VAT.
 * - `crm_order_items.vat_percent` phần lớn đang bằng 0, nên không chia ngược được.
 * - Giá trước VAT đúng nằm ở `crm_product_prices.price` theo cặp sản phẩm + bậc giá.
 *
 * Nên phải thử lần lượt nhiều nguồn, từ đáng tin nhất trở xuống. Mỗi nguồn là
 * một method riêng ở dưới, trả `null` nghĩa là "nguồn này không kết luận được,
 * thử nguồn sau".
 */
final class OrderBeforeVatAmount
{
    private const PRICE_TABLE = 'crm_product_prices';

    private const CATALOG_TABLE = 'crm_product_catalog';

    /** %VAT của sản phẩm, nhớ lại trong một lượt dựng báo cáo. */
    private array $productVatPercent = [];

    public function __construct(
        private readonly OrderColumnMap $cols,
        private readonly OrderItemReader $items,
    ) {}

    public function forOrder(object $order): float
    {
        $totalAfterVat = $this->totalAfterVat($order);
        $vatAmount = $this->vatAmount($order);

        // 1. Đáng tin nhất với dữ liệu hiện tại: tổng sau VAT trừ tiền thuế đã ghi.
        if ($this->canSubtractVat($totalAfterVat, $vatAmount)) {
            return round($totalAfterVat - $vatAmount);
        }

        $orderId = $order->id ?? null;

        // 2. Bảng giá: nguồn duy nhất ghi thẳng giá trước VAT.
        $fromPrices = $this->fromProductPrices($orderId);

        if ($fromPrices !== null) {
            return $fromPrices;
        }

        // 3. Cột tổng trước VAT trên đơn, nếu có và không vô lý so với tổng sau VAT.
        $fromColumn = $this->fromOrderColumn($order, $totalAfterVat);

        if ($fromColumn !== null) {
            return $fromColumn;
        }

        // 4. Chia ngược từng dòng hàng, chỉ với dòng có %VAT thật.
        $fromItems = $this->fromItemVatPercent($orderId);

        if ($fromItems !== null) {
            return $fromItems;
        }

        /*
         * Ở đây bản cũ lặp lại nguyên chốt chặn của bước 1. Không bao giờ chạy
         * tới: hai biến đó chỉ gán một lần ở đầu hàm, nếu điều kiện đúng thì
         * bước 1 đã trả về rồi. Đã bỏ.
         */

        // 5. Đơn chỉ có %VAT: quy tổng sau VAT về trước VAT.
        $fromPercent = $this->fromOrderVatPercent($order, $totalAfterVat);

        if ($fromPercent !== null) {
            return $fromPercent;
        }

        // Không suy ra được VAT thì coi tổng đơn là trước VAT còn hơn trả 0.
        return round($totalAfterVat);
    }

    private function totalAfterVat(object $order): float
    {
        $column = $this->cols->totalCol;

        return $column && isset($order->{$column}) ? MoneyParser::parse($order->{$column}) : 0.0;
    }

    /** Tiền thuế ghi trên đơn — lấy cột đầu tiên có giá trị dương. */
    private function vatAmount(object $order): float
    {
        foreach ($this->cols->vatAmountCols as $column) {
            if (! isset($order->{$column})) {
                continue;
            }

            $value = MoneyParser::parse($order->{$column});

            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    /** Thuế lớn hơn cả tổng đơn là dữ liệu hỏng, không trừ. */
    private function canSubtractVat(float $totalAfterVat, float $vatAmount): bool
    {
        return $totalAfterVat > 0 && $vatAmount > 0 && $totalAfterVat >= $vatAmount;
    }

    private function fromProductPrices(mixed $orderId): ?float
    {
        if (! $this->items->isReady() || ! $orderId || ! SchemaCache::hasTable(self::PRICE_TABLE)) {
            return null;
        }

        try {
            $sum = 0.0;
            $found = false;

            foreach ($this->items->rowsFor($orderId) as $item) {
                $price = $this->listedPriceBeforeVat($item);

                if ($price === null) {
                    continue;
                }

                $sum += $price * $this->items->quantity($item);
                $found = true;
            }

            return $found && $sum > 0 ? round($sum) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Giá niêm yết trước VAT của một dòng; cần đủ cả sản phẩm lẫn bậc giá. */
    private function listedPriceBeforeVat(object $item): ?float
    {
        $productId = $this->items->has('product_id') ? (int) ($item->product_id ?? 0) : 0;
        $tierId = $this->items->has('price_tier_id') ? (int) ($item->price_tier_id ?? 0) : 0;

        if ($productId <= 0 || $tierId <= 0) {
            return null;
        }

        $price = DB::table(self::PRICE_TABLE)
            ->where('product_id', $productId)
            ->where('price_tier_id', $tierId)
            ->orderByDesc('id')
            ->first();

        if (! $price || ! isset($price->price)) {
            return null;
        }

        $value = MoneyParser::parse($price->price);

        return $value > 0 ? $value : null;
    }

    private function fromOrderColumn(object $order, float $totalAfterVat): ?float
    {
        $column = $this->cols->beforeVatCol;

        if (! $column || ! isset($order->{$column})) {
            return null;
        }

        $value = MoneyParser::parse($order->{$column});

        // Lớn hơn cả tổng sau VAT thì cột này đang chứa thứ khác, không phải trước VAT.
        if ($value > 0 && ($totalAfterVat <= 0 || $value <= $totalAfterVat + 1)) {
            return round($value);
        }

        return null;
    }

    private function fromItemVatPercent(mixed $orderId): ?float
    {
        if (! $this->items->isReady() || ! $orderId) {
            return null;
        }

        try {
            $sum = 0.0;

            foreach ($this->items->rowsFor($orderId) as $item) {
                $vatPercent = $this->itemVatPercent($item);
                $lineAfterVat = $this->lineAfterVat($item);

                /*
                 * %VAT = 0 nghĩa là dòng này chưa lưu thuế. Tiền dòng vẫn đang là
                 * SAU VAT nhưng không có gì để chia ngược, nên bỏ qua dòng thay vì
                 * cộng nhầm một con số đã gồm thuế.
                 */
                if ($lineAfterVat <= 0 || $vatPercent <= 0) {
                    continue;
                }

                $sum += $lineAfterVat / (1 + $vatPercent / 100);
            }

            return $sum > 0 ? round($sum) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** %VAT của dòng; dòng chưa lưu thì mượn của sản phẩm trong danh mục. */
    private function itemVatPercent(object $item): float
    {
        $vat = $this->items->has('vat_percent') ? MoneyParser::parse($item->vat_percent ?? 0) : 0.0;

        if ($vat <= 0 && $this->items->has('vat')) {
            $vat = MoneyParser::parse($item->vat ?? 0);
        }

        if ($vat > 0 || ! $this->items->has('product_id') || empty($item->product_id)) {
            return $vat;
        }

        return $this->catalogVatPercent((int) $item->product_id);
    }

    private function catalogVatPercent(int $productId): float
    {
        if ($productId <= 0
            || ! SchemaCache::hasTable(self::CATALOG_TABLE)
            || ! SchemaCache::hasColumn(self::CATALOG_TABLE, 'vat_percent')) {
            return 0.0;
        }

        if (! array_key_exists($productId, $this->productVatPercent)) {
            try {
                $this->productVatPercent[$productId] = (float) DB::table(self::CATALOG_TABLE)
                    ->where('id', $productId)
                    ->value('vat_percent');
            } catch (\Throwable $e) {
                // Thiếu dữ liệu sản phẩm thì coi như không có VAT, không chặn trang.
                return 0.0;
            }
        }

        return (float) ($this->productVatPercent[$productId] ?? 0);
    }

    /** Tiền một dòng SAU VAT: ưu tiên tổng dòng đã lưu, không có thì tự nhân. */
    private function lineAfterVat(object $item): float
    {
        foreach (['line_total', 'total_amount', 'total', 'amount'] as $column) {
            if ($this->items->has($column)) {
                return MoneyParser::parse($item->{$column} ?? 0);
            }
        }

        $unit = $this->items->has('unit_price') ? MoneyParser::parse($item->unit_price ?? 0) : 0.0;

        if ($unit <= 0 && $this->items->has('price')) {
            $unit = MoneyParser::parse($item->price ?? 0);
        }

        $percent = $this->items->has('discount_percent') ? MoneyParser::parse($item->discount_percent ?? 0) : 0.0;
        $amount = $this->items->has('discount_amount') ? MoneyParser::parse($item->discount_amount ?? 0) : 0.0;

        $quantity = $this->items->quantity($item);

        return max(0, ($quantity * $unit * (1 - $percent / 100)) - $amount);
    }

    private function fromOrderVatPercent(object $order, float $totalAfterVat): ?float
    {
        $column = $this->cols->vatPercentCol;

        if ($totalAfterVat <= 0 || ! $column || ! isset($order->{$column})) {
            return null;
        }

        $percent = MoneyParser::parse($order->{$column});

        return $percent > 0 && $percent <= 100 ? round($totalAfterVat / (1 + $percent / 100)) : null;
    }
}
