<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\PricingServiceInterface;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Service tính giá sản phẩm (ưu tiên bảng giá tier, fallback theo loại khách hàng).
 */
class PricingService implements PricingServiceInterface
{
    /**
     * Lấy đơn giá cho sản phẩm.
     *
     * Ưu tiên: Bảng giá (price_tier) → Giá theo loại khách → Giá retail → Giá chung
     *
     * @param  int|null  $priceTierId  Bảng giá (nếu có)
     * @param  string|null  $at  Thời điểm hiệu lực
     * @param  int|null  $customerTypeId  1=Đại lý, 2=Lẻ (MỚI)
     * @return float Giá TRƯỚC VAT
     */
    public function getUnitPrice(
        int $productId,
        ?int $priceTierId,
        ?string $at = null,
        ?int $customerTypeId = null
    ): float {
        // 1. Ưu tiên bảng giá theo tier
        if ($priceTierId) {
            $tierPrice = $this->getTierPrice($productId, $priceTierId, $at);
            if ($tierPrice > 0) {
                return $tierPrice;
            }
        }

        // 2. Fallback theo loại khách hàng
        return $this->fallbackProductPrice($productId, $customerTypeId);
    }

    /**
     * Lấy giá sau VAT.
     */
    public function getUnitPriceWithVat(
        int $productId,
        ?int $priceTierId,
        ?string $at = null,
        ?int $customerTypeId = null
    ): float {
        $basePrice = $this->getUnitPrice($productId, $priceTierId, $at, $customerTypeId);
        $vat = $this->getVatPercent($productId);

        return round($basePrice * (1 + $vat / 100), 0);
    }

    /**
     * Lấy giá từ bảng giá tier.
     */
    private function getTierPrice(int $productId, int $priceTierId, ?string $at): float
    {
        $at = $at ?: now()->toDateTimeString();
        $row = DB::table('crm_product_prices')
            ->select('price')
            ->where('product_id', $productId)
            ->where('price_tier_id', $priceTierId)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at))
            ->orderByRaw('effective_from IS NULL')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return ($row && isset($row->price)) ? (float) $row->price : 0.0;
    }

    /**
     * Fallback giá theo loại khách hàng.
     *
     * ✅ FIX: Trước đây luôn trả price_retail, bỏ qua agent.
     * Giờ: Đại lý (1) → price_agent, Lẻ (2) → price_retail, Default → price_retail
     *
     * @param  int|null  $customerTypeId  1=Đại lý, 2=Lẻ
     * @return float Giá TRƯỚC VAT
     */
    private function fallbackProductPrice(int $productId, ?int $customerTypeId = null): float
    {
        $p = DB::table('crm_product_catalog')
            ->select('price', 'price_retail', 'price_agent')
            ->where('id', $productId)
            ->first();
        if (! $p) {
            return 0.0;
        }
        // ✅ FIX: Đại lý (customer_type_id = 1) → ưu tiên price_agent
        if ((int) $customerTypeId === 1) {
            $agentPrice = (float) ($p->price_agent ?? 0);
            if ($agentPrice > 0) {
                return $agentPrice;
            }
        }

        // Mặc định: price_retail → price → 0
        return (float) ($p->price_retail ?? $p->price ?? 0);
    }

    /**
     * Lấy % VAT của sản phẩm.
     */
    public function getVatPercent(int $productId): float
    {
        $vat = DB::table('crm_product_catalog')
            ->where('id', $productId)
            ->value('vat_percent');

        return max(0, (float) ($vat ?? 0));
    }

    /**
     * Tra % VAT cho dòng hàng đơn: ưu tiên bảng giá crm_product_prices theo tier
     * (thử lần lượt cột vat_percent/vat/tax_percent), sau đó tới catalog
     * (vat_percent/cost_vat_percent); trả 0 nếu không có. Kết quả kẹp [0, 100].
     *
     * Chuyển nguyên trạng từ OrderController::egoResolveVatPercentForOrderItem
     * (P1a refactor) — dùng chung cho API product-price và đồng bộ dòng hàng khi sửa đơn.
     */
    public function resolveVatPercentForOrderItem(int $productId, ?int $priceTierId = null): float
    {
        $key = $productId.':'.($priceTierId ?? '');

        return $this->resolveVatPercentsForOrderItems([[$productId, $priceTierId]])[$key] ?? 0.0;
    }

    /**
     * Bản gộp của resolveVatPercentForOrderItem: cùng thứ tự ưu tiên nhưng chỉ
     * tốn 2 truy vấn cho cả danh sách thay vì 2 truy vấn cho MỖI dòng hàng.
     *
     * @param  list<array{0: int, 1: int|null}>  $pairs
     * @return array<string, float>
     */
    public function resolveVatPercentsForOrderItems(array $pairs): array
    {
        $productIds = [];

        foreach ($pairs as [$productId, $priceTierId]) {
            if ((int) $productId > 0) {
                $productIds[(int) $productId] = true;
            }
        }

        if ($productIds === []) {
            return [];
        }

        $productIds = array_keys($productIds);
        $tierVat = $this->tierVatPercents($productIds);
        $catalogVat = $this->catalogVatPercents($productIds);

        $result = [];

        foreach ($pairs as [$productId, $priceTierId]) {
            $productId = (int) $productId;
            $key = $productId.':'.($priceTierId ?? '');

            if ($productId <= 0) {
                $result[$key] = 0.0;

                continue;
            }

            $vat = $priceTierId !== null
                ? ($tierVat[$productId.':'.(int) $priceTierId] ?? 0.0)
                : 0.0;

            if ($vat <= 0) {
                $vat = $catalogVat[$productId] ?? 0.0;
            }

            $result[$key] = min(100, max(0, $vat));
        }

        return $result;
    }

    /**
     * % VAT theo cặp (sản phẩm, tầng giá) từ bảng giá — MỘT truy vấn.
     *
     * @param  list<int>  $productIds
     * @return array<string, float> "productId:tierId" => % VAT
     */
    private function tierVatPercents(array $productIds): array
    {
        if (! SchemaCache::hasColumns('crm_product_prices', ['product_id', 'price_tier_id'])) {
            return [];
        }

        $vatColumns = array_values(array_filter(
            ['vat_percent', 'vat', 'tax_percent'],
            static fn (string $column): bool => SchemaCache::hasColumn('crm_product_prices', $column),
        ));

        if ($vatColumns === []) {
            return [];
        }

        $rows = DB::table('crm_product_prices')
            ->whereIn('product_id', $productIds)
            ->get(array_merge(['product_id', 'price_tier_id'], $vatColumns));

        $map = [];

        foreach ($rows as $row) {
            foreach ($vatColumns as $column) {
                $vat = (float) ($row->{$column} ?? 0);

                if ($vat > 0) {
                    $map[(int) $row->product_id.':'.(int) $row->price_tier_id] = $vat;
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * % VAT dự phòng lấy từ catalog sản phẩm — MỘT truy vấn.
     *
     * @param  list<int>  $productIds
     * @return array<int, float>
     */
    private function catalogVatPercents(array $productIds): array
    {
        $vatColumns = array_values(array_filter(
            ['vat_percent', 'cost_vat_percent'],
            static fn (string $column): bool => SchemaCache::hasColumn('crm_product_catalog', $column),
        ));

        if ($vatColumns === []) {
            return [];
        }

        $rows = DB::table('crm_product_catalog')
            ->whereIn('id', $productIds)
            ->get(array_merge(['id'], $vatColumns));

        $map = [];

        foreach ($rows as $row) {
            foreach ($vatColumns as $column) {
                $vat = (float) ($row->{$column} ?? 0);

                if ($vat > 0) {
                    $map[(int) $row->id] = $vat;
                    break;
                }
            }
        }

        return $map;
    }
}
