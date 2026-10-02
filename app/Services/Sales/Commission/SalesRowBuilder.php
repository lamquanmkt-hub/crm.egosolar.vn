<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use Illuminate\Support\Collection;

/**
 * Dựng một dòng báo cáo cho mỗi sales: doanh số, hoa hồng, thưởng, thu nhập.
 *
 * Lớp này chỉ ghép kết quả của bốn lớp bên dưới rồi tính vài tỷ lệ hiển thị.
 * Toàn bộ phần "khó" nằm ở chỗ khác: cộng dồn đơn, đọc hoa hồng đã chốt, tính
 * hoa hồng theo quy tắc, chọn bậc KPI.
 */
final class SalesRowBuilder
{
    /** Áp dụng khi sales chưa có thiết lập lương riêng cho kỳ. */
    private const DEFAULT_BASE_SALARY = 7_000_000.0;

    private const DEFAULT_TARGET_REVENUE = 500_000_000.0;

    /** Chặn trên của % hoàn thành chỉ tiêu, tránh vỡ thanh tiến độ trên giao diện. */
    private const MAX_TARGET_RATE = 999;

    public function __construct(
        private readonly SalesOrderAggregator $aggregator,
        private readonly RecordedCommissionQuery $recorded,
        private readonly CommissionCalculator $calculator,
        private readonly KpiBonusCalculator $kpi,
    ) {}

    /**
     * @param  Collection<int, object>  $salesUsers
     * @param  Collection<int, object>  $salarySettings  khoá theo id sales
     * @return Collection<int, object> xếp theo thu nhập giảm dần
     */
    public function build(Collection $salesUsers, Collection $salarySettings, SalesPeriod $period): Collection
    {
        return $salesUsers
            ->map(fn ($user) => $this->rowFor($user, $salarySettings, $period))
            ->filter()
            ->sortByDesc('total_income')
            ->values();
    }

    /** @param  Collection<int, object>  $salarySettings */
    private function rowFor(object $user, Collection $salarySettings, SalesPeriod $period): ?object
    {
        $salesId = (int) ($user->id ?? 0);

        if ($salesId <= 0) {
            return null;
        }

        $salary = $salarySettings->get($salesId);
        $baseSalary = (float) ($salary->base_salary ?? self::DEFAULT_BASE_SALARY);
        $targetRevenue = (float) ($salary->target_revenue ?? self::DEFAULT_TARGET_REVENUE);

        $orders = $this->aggregator->forSales($salesId, $period);
        $commission = $this->commissionFor($salesId, $orders, $period);
        $kpi = $this->kpi->forSales($salesId, $orders->revenue, $commission, $baseSalary);

        $eligibleBeforeVat = $orders->eligibleRevenueBeforeVat;

        return (object) [
            'id' => $salesId,
            'name' => $user->name ?? ('Sales #'.$salesId),
            'email' => $user->email ?? '',
            'base_salary' => $baseSalary,
            'target_revenue' => $targetRevenue,
            'target_commission' => (float) ($salary->target_commission ?? 0),
            'revenue' => $orders->revenue,
            'paid' => $orders->paid,
            'debt' => $orders->debt(),
            'order_count' => $orders->orderCount,
            'commission' => $commission,
            'commission_rate' => $eligibleBeforeVat > 0 ? ($commission / $eligibleBeforeVat) * 100 : 0,
            'eligible_revenue_before_vat' => $eligibleBeforeVat,
            'kpi_bonus' => $kpi->amount,
            'total_income' => $baseSalary + $commission + $kpi->amount,
            'target_rate' => $targetRevenue > 0 ? min(self::MAX_TARGET_RATE, ($orders->revenue / $targetRevenue) * 100) : 0,
            'kpi_name' => $kpi->tierName,
        ];
    }

    /** Số kế toán đã chốt luôn được ưu tiên; chưa có thì tính theo quy tắc đang cấu hình. */
    private function commissionFor(int $salesId, SalesOrderAggregate $orders, SalesPeriod $period): float
    {
        $recorded = $this->recorded->forSales($salesId, $orders->eligibleOrderIds, $period);

        if ($recorded !== null) {
            return $recorded;
        }

        $commission = 0.0;

        foreach ($orders->eligibleOrders as $order) {
            $commission += $this->calculator->forOrder(
                $order->beforeVat,
                $order->afterVat,
                $orders->eligibleRevenueBeforeVat,
                $order->customerStatus,
                $order->quantity,
                $order->productText,
            );
        }

        return $commission;
    }
}
