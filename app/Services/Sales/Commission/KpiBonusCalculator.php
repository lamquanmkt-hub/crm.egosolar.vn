<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use Illuminate\Support\Collection;

/**
 * Chọn bậc KPI cao nhất mà sales với tay tới, rồi quy ra tiền thưởng.
 *
 * Bậc có thể đặt riêng cho một sales (`sales_id`) hoặc dùng chung cho cả nhóm
 * (`sales_id` rỗng). Khi nhiều bậc cùng thoả, bậc có mốc doanh thu cao hơn
 * thắng — đó mới là bậc sales thực sự đạt được.
 */
final class KpiBonusCalculator
{
    /** @param  Collection<int, object>  $tiers */
    public function __construct(private readonly Collection $tiers) {}

    public function forSales(int $salesId, float $revenue, float $commission, float $baseSalary): KpiBonus
    {
        $tier = $this->bestTier($salesId, $revenue);

        if (! $tier) {
            return new KpiBonus;
        }

        return new KpiBonus(
            $this->bonusAmount($tier, $revenue, $commission, $baseSalary),
            (string) ($tier->tier_name ?? KpiBonus::NOT_REACHED),
        );
    }

    private function bestTier(int $salesId, float $revenue): ?object
    {
        $best = null;

        foreach ($this->tiers->where('is_active', 1) as $tier) {
            if (! $this->appliesTo($tier, $salesId) || ! $this->reaches($tier, $revenue)) {
                continue;
            }

            if (! $best || (float) ($tier->from_revenue ?? 0) >= (float) ($best->from_revenue ?? 0)) {
                $best = $tier;
            }
        }

        return $best;
    }

    /** Bậc không ghi `sales_id` là bậc dùng chung cho mọi sales. */
    private function appliesTo(object $tier, int $salesId): bool
    {
        $tierSalesId = $tier->sales_id ?? null;

        return empty($tierSalesId) || (int) $tierSalesId === $salesId;
    }

    private function reaches(object $tier, float $revenue): bool
    {
        if ($revenue < (float) ($tier->from_revenue ?? 0)) {
            return false;
        }

        $to = $tier->to_revenue !== null && $tier->to_revenue !== '' ? (float) $tier->to_revenue : null;

        return $to === null || $revenue <= $to;
    }

    private function bonusAmount(object $tier, float $revenue, float $commission, float $baseSalary): float
    {
        $amount = (float) ($tier->bonus_amount ?? 0);

        return match ($tier->bonus_type ?? 'fixed') {
            'percent_revenue' => $revenue * $amount / 100,
            'percent_commission' => $commission * $amount / 100,
            'salary_percent' => $baseSalary * $amount / 100,
            default => $amount,
        };
    }
}
