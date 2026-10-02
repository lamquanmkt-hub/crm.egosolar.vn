<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

/**
 * Kết quả cộng dồn đơn hàng của MỘT sales trong kỳ.
 *
 * Tách "doanh thu" khỏi "doanh thu đủ điều kiện": doanh thu phản ánh sales bán
 * được bao nhiêu, còn phần đủ điều kiện mới là cơ sở tính hoa hồng.
 */
final class SalesOrderAggregate
{
    /**
     * @param  list<int>  $eligibleOrderIds
     * @param  list<EligibleOrder>  $eligibleOrders
     */
    public function __construct(
        public readonly float $revenue = 0.0,
        public readonly float $paid = 0.0,
        public readonly int $orderCount = 0,
        public readonly float $eligibleRevenue = 0.0,
        public readonly float $eligibleRevenueBeforeVat = 0.0,
        public readonly array $eligibleOrderIds = [],
        public readonly array $eligibleOrders = [],
    ) {}

    public function debt(): float
    {
        return max(0, $this->revenue - $this->paid);
    }
}
