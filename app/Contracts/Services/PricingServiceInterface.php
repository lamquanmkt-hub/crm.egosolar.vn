<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Hợp đồng service tính giá sản phẩm (ưu tiên bảng giá tier, fallback theo loại khách hàng).
 */
interface PricingServiceInterface
{
    public function getUnitPrice(
        int $productId,
        ?int $priceTierId,
        ?string $at = null,
        ?int $customerTypeId = null
    ): float;

    public function getUnitPriceWithVat(
        int $productId,
        ?int $priceTierId,
        ?string $at = null,
        ?int $customerTypeId = null
    ): float;

    public function getVatPercent(int $productId): float;

    /**
     * Tra % VAT cho dòng hàng đơn: ưu tiên bảng giá theo tier, fallback catalog.
     */
    public function resolveVatPercentForOrderItem(int $productId, ?int $priceTierId = null): float;

    /**
     * Tra % VAT cho NHIỀU dòng hàng cùng lúc (cùng quy tắc, gộp truy vấn).
     *
     * Dùng khi lưu đơn nhiều dòng: gọi lẻ từng dòng sẽ thành N+1.
     *
     * @param  list<array{0: int, 1: int|null}>  $pairs  Cặp [product_id, price_tier_id]
     * @return array<string, float> "productId:tierId" => % VAT (tierId rỗng khi null)
     */
    public function resolveVatPercentsForOrderItems(array $pairs): array;
}
