<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use Illuminate\Support\Collection;

/**
 * Tính hoa hồng cho MỘT đơn hàng theo bộ quy tắc của kỳ.
 *
 * Trước đây là closure `$commissionAmountByRules` dài 274 dòng nằm giữa một
 * method 1.299 dòng. Nó chỉ phụ thuộc hai thứ — bộ quy tắc và chính sách của kỳ —
 * nên tách ra là tự nhiên: đây là nơi duy nhất biết "quy tắc nào thắng".
 *
 * Việc đọc từng quy tắc (loại gì, có khớp không, ra bao nhiêu tiền) thuộc về
 * {@see CommissionRuleMatcher} và dùng chung với bản xuất Excel. Lớp này chỉ lo
 * phần còn lại: trong nhiều quy tắc cùng áp được thì lấy cái nào.
 *
 * Dựng bằng `new` chứ không qua container: quy tắc và chính sách thay đổi theo
 * từng kỳ báo cáo, không phải phụ thuộc dùng chung của ứng dụng.
 */
final class CommissionCalculator
{
    private readonly CommissionRuleMatcher $matcher;

    /** @param  Collection<int, mixed>  $rules */
    public function __construct(
        private readonly Collection $rules,
        private readonly object $policy,
        ?CommissionRuleMatcher $matcher = null,
    ) {
        $this->matcher = $matcher ?? new CommissionRuleMatcher;
    }

    public function forOrder(
        float $beforeVat,
        float $afterVat,
        float $monthlyRevenueBeforeVat,
        ?string $customerStatus = '',
        float $quantity = 0,
        string $productText = ''
    ): float {
        $beforeVat = max(0, $beforeVat);
        $afterVat = max(0, $afterVat);
        $monthlyRevenueBeforeVat = max(0, $monthlyRevenueBeforeVat);
        $quantity = max(0, $quantity);

        $active = $this->matcher->activeRules(collect($this->rules));

        // Chưa cấu hình quy tắc nào thì rơi về % thương mại mặc định của chính sách.
        if ($active->isEmpty()) {
            return $beforeVat * ((float) ($this->policy->trade_rate_percent ?? 0)) / 100;
        }

        $trade = null;
        $extra = 0.0;
        $fallbacks = [];

        /*
         * Có quy tắc đặt mốc doanh thu (ví dụ 0,5% từ 500 triệu) thì phần dự
         * phòng CHỈ được lấy trong nhóm có mốc — nếu không, đơn không đọc được
         * nhóm khách sẽ ăn nhầm mức cao hơn mức nó đáng được hưởng.
         */
        $hasRevenueFloor = $active->contains(fn ($rule) => $this->matcher->isTrade($rule) && $this->hasRevenueFloor($rule));

        foreach ($active as $rule) {
            $isTrade = $this->matcher->isTrade($rule);

            if (! $isTrade && ! $this->matcher->isSolarPanel($rule)) {
                continue;
            }

            $amount = $this->matcher->amountFor($rule, $beforeVat, $afterVat, $monthlyRevenueBeforeVat, $quantity);

            if ($amount === null) {
                continue;
            }

            $amount = max(0, $amount);
            $matches = $this->matcher->matchesTarget($rule, $customerStatus, $productText);

            // Thưởng tấm pin cộng dồn, không tranh chỗ với hoa hồng thương mại.
            if (! $isTrade) {
                if ($matches) {
                    $extra += $amount;
                }

                continue;
            }

            if ($matches) {
                $trade ??= $amount;

                continue;
            }

            if (! $hasRevenueFloor || $this->hasRevenueFloor($rule)) {
                $fallbacks[] = [
                    'amount' => $amount,
                    'rate' => (float) ($rule->rate_percent ?? 0),
                    'priority' => (int) ($rule->priority ?? 0),
                ];
            }
        }

        return ($trade ?? $this->lowestFallback($fallbacks)) + $extra;
    }

    private function hasRevenueFloor(object $rule): bool
    {
        return ($rule->from_amount ?? null) !== null && (float) ($rule->from_amount ?? 0) > 0;
    }

    /**
     * Không quy tắc nào khớp đích thì lấy mức THẤP NHẤT trong các quy tắc dự
     * phòng — đơn chưa xác định được nhóm khách chỉ nên hưởng mức tối thiểu.
     *
     * @param  list<array{amount: float, rate: float, priority: int}>  $fallbacks
     */
    private function lowestFallback(array $fallbacks): float
    {
        if ($fallbacks === []) {
            return 0.0;
        }

        usort($fallbacks, function (array $a, array $b) {
            return [$a['rate'], $b['priority']] <=> [$b['rate'], $a['priority']];
        });

        return (float) $fallbacks[0]['amount'];
    }
}
