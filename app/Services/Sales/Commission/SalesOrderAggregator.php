<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use Illuminate\Support\Facades\DB;

/**
 * Cộng dồn đơn hàng của một sales trong kỳ.
 *
 * Mỗi đơn được hỏi bốn thứ (đã thu bao nhiêu, có đủ điều kiện không, doanh thu
 * trước VAT, hàng hoá gì) — mỗi câu hỏi là một lớp riêng, ở đây chỉ ghép lại.
 */
final class SalesOrderAggregator
{
    public function __construct(
        private readonly OrderColumnMap $cols,
        private readonly OrderPaidAmount $paidAmount,
        private readonly OrderBeforeVatAmount $beforeVatAmount,
        private readonly OrderProductSummary $productSummary,
        private readonly OrderCustomerLookup $customers,
        private readonly CommissionEligibilityPolicy $eligibility,
    ) {}

    public function forSales(int $salesId, SalesPeriod $period): SalesOrderAggregate
    {
        if (! $this->canQueryOrders()) {
            return new SalesOrderAggregate;
        }

        $orders = DB::table((string) $this->cols->orderTable)
            ->whereBetween((string) $this->cols->dateCol, $period->range())
            ->where((string) $this->cols->salesCol, $salesId)
            ->get();

        $revenue = 0.0;
        $paid = 0.0;
        $eligibleRevenue = 0.0;
        $eligibleBeforeVat = 0.0;
        $eligibleIds = [];
        $eligibleOrders = [];

        foreach ($orders as $order) {
            $total = (float) ($order->{$this->cols->totalCol} ?? 0);
            $orderPaid = $this->paidAmount->forOrder($order);

            $revenue += $total;
            $paid += $orderPaid;

            if (! $this->eligibility->isEligible($order, $total, $orderPaid)) {
                continue;
            }

            $beforeVat = $this->beforeVatAmount->forOrder($order);
            $products = $this->productSummary->forOrder($order);

            $eligibleRevenue += $total;
            $eligibleBeforeVat += $beforeVat;

            $eligibleOrders[] = new EligibleOrder(
                afterVat: $total,
                beforeVat: $beforeVat,
                customerStatus: $this->customers->status($order),
                quantity: $products->quantity,
                productText: $products->text,
            );

            if (! empty($order->id)) {
                $eligibleIds[] = (int) $order->id;
            }
        }

        return new SalesOrderAggregate(
            revenue: $revenue,
            paid: $paid,
            orderCount: $orders->count(),
            eligibleRevenue: $eligibleRevenue,
            eligibleRevenueBeforeVat: $eligibleBeforeVat,
            eligibleOrderIds: $eligibleIds,
            eligibleOrders: $eligibleOrders,
        );
    }

    /** Thiếu bất kỳ cột nào trong bốn cột này thì không dựng nổi câu truy vấn. */
    private function canQueryOrders(): bool
    {
        return $this->cols->orderTable !== null
            && $this->cols->dateCol !== null
            && $this->cols->totalCol !== null
            && $this->cols->salesCol !== null;
    }
}
