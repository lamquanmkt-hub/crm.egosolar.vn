<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

/**
 * Một đơn đã đủ điều kiện tính hoa hồng, kèm mọi dữ kiện mà quy tắc cần.
 *
 * Gom sẵn tại thời điểm duyệt đơn để {@see CommissionCalculator} không phải
 * truy vấn lại: hoa hồng của một sales được tính sau khi biết doanh số cả
 * tháng, tức là sau khi vòng lặp đơn đã chạy xong.
 */
final class EligibleOrder
{
    public function __construct(
        public readonly float $afterVat,
        public readonly float $beforeVat,
        public readonly string $customerStatus,
        public readonly float $quantity,
        public readonly string $productText,
    ) {}
}
