<?php

declare(strict_types=1);

namespace App\View\Presenters\Sales;

use App\DTOs\Sales\CommissionRuleRow;
use App\DTOs\Sales\KpiTierRow;
use App\DTOs\Sales\SalesSalaryRow;
use App\Support\DisplayFormat;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị giá trị cho view `sales.commissions.settings` (chính sách hoa hồng theo tháng).
 *
 * Trước 2026-09-08 view tự tính trong 4 khối `@php` (119 dòng): 6 bảng nhãn, tổng lương/target,
 * và diễn giải từng dòng quy tắc (khoảng doanh thu, cách tính, giá trị), lương sales (mặc định khi
 * chưa thiết lập) và bậc KPI (tên sales, khoảng, thưởng). Dữ liệu thô do CommissionEngineService
 * cấp; presenter chỉ diễn giải. Tên khoá trả về giữ tên biến cũ của view; kiểm bằng so HTML.
 */
final class SalesCommissionSettingsPresenter
{
    public const TYPE_LABELS = ['project' => 'Công trình', 'trade_product' => 'Thương mại', 'solar_panel' => 'Tấm pin'];

    public const CUSTOMER_STATUS_LABELS = ['all' => 'Tất cả khách', 'lead' => 'Lead / Khách ADS', 'member' => 'Member / Tự phát triển', 'retail' => 'Khách lẻ'];

    public const TARGET_LABELS = ['all' => 'Tất cả', 'customer_status' => 'Trạng thái khách', 'product' => 'Sản phẩm', 'category' => 'Danh mục', 'brand' => 'Thương hiệu', 'keyword' => 'Từ khóa / model'];

    public const CALC_LABELS = ['percent' => '% doanh thu', 'fixed_per_item' => 'Tiền / sản phẩm', 'fixed_per_kwp' => 'Tiền / kWp', 'fixed_per_order' => 'Cố định / đơn'];

    public const BASE_LABELS = ['revenue_before_vat' => 'Doanh thu trước VAT', 'revenue_after_vat' => 'Doanh thu sau VAT', 'gross_profit' => 'Lợi nhuận gộp', 'quantity' => 'Số lượng', 'kwp' => 'kWp'];

    public const BONUS_TYPE_LABELS = ['fixed' => 'Thưởng cố định', 'percent_revenue' => '% doanh số', 'percent_commission' => '% hoa hồng', 'salary_percent' => '% lương cứng'];

    /**
     * @param  Collection<int, object>  $rules  quy tắc của chính sách (bản ghi thô)
     * @param  Collection<int, object>  $salesUsers  id, name, email
     * @param  Collection<int, object>  $salarySettings  thiết lập lương, khoá theo sales_id
     * @param  Collection<int, object>  $kpiTiers  bậc thưởng KPI của tháng
     * @return array<string, mixed>
     */
    public function viewData(Collection $rules, Collection $salesUsers, Collection $salarySettings, Collection $kpiTiers, object $policy): array
    {
        $rules = $rules->values();
        $salesUsers = $salesUsers->values();
        $salesNameMap = $salesUsers->mapWithKeys(fn (object $user) => [(int) $user->id => $user->name])->toArray();
        $activeSalaries = $salarySettings->where('is_active', 1);

        return [
            'typeLabel' => self::TYPE_LABELS,
            'customerStatusLabel' => self::CUSTOMER_STATUS_LABELS,
            'baseLabel' => self::BASE_LABELS,
            'calcLabel' => self::CALC_LABELS,
            'salesNameMap' => $salesNameMap,
            'activeRules' => $rules->where('is_active', 1)->count(),
            'totalBaseSalaryText' => DisplayFormat::money($activeSalaries->sum('base_salary')),
            'totalTargetRevenueText' => DisplayFormat::money($activeSalaries->sum('target_revenue')),
            'policyProjectRateText' => self::percent($policy->project_rate_percent ?? 4),
            'policyTradeRateText' => self::percent($policy->trade_rate_percent ?? 1),
            'policyPanelFixedText' => DisplayFormat::money($policy->panel_fixed_amount ?? 15000),
            'ruleRows' => $rules->map(fn (object $rule) => $this->ruleRow($rule))->all(),
            'salaryRows' => $salesUsers->map(fn (object $user) => $this->salaryRow($user, $salarySettings->get((int) $user->id)))->all(),
            'tierRows' => $kpiTiers->values()->map(fn (object $tier) => $this->tierRow($tier, $salesNameMap))->all(),
        ];
    }

    private function ruleRow(object $rule): CommissionRuleRow
    {
        $targetText = (string) ($rule->target_text ?: ($rule->target_id ? '#'.$rule->target_id : ''));
        $targetType = $rule->target_type ?? 'all';
        $customerStatus = $targetType === 'customer_status' ? ($targetText ?: 'all') : 'all';
        $applyText = $targetType === 'customer_status'
            ? 'Theo trạng thái khách'
            : (self::TARGET_LABELS[$targetType] ?? 'Tất cả').($targetText ? ': '.$targetText : '');

        $fromAmount = $rule->from_amount ?? '';
        $toAmount = $rule->to_amount ?? '';
        $revenueRange = match (true) {
            $fromAmount !== '' && $toAmount !== '' => DisplayFormat::money($fromAmount).' - '.DisplayFormat::money($toAmount),
            $fromAmount !== '' => 'Từ '.DisplayFormat::money($fromAmount),
            $toAmount !== '' => 'Đến '.DisplayFormat::money($toAmount),
            default => 'Không giới hạn',
        };

        $fixedAmount = (float) ($rule->fixed_amount ?: ($rule->amount_per_unit ?? 0));
        $calc = (string) ($rule->calculation_type ?? 'percent');
        $valueText = match ($calc) {
            'fixed_per_item' => DisplayFormat::money($fixedAmount).'/SP',
            'fixed_per_kwp' => DisplayFormat::money($rule->amount_per_kwp ?? 0).'/kWp',
            'fixed_per_order' => DisplayFormat::money($fixedAmount).'/đơn',
            default => self::percent($rule->rate_percent ?? 0),
        };

        return new CommissionRuleRow(
            rule: $rule,
            targetText: $targetText,
            customerStatus: $customerStatus,
            customerStatusText: self::CUSTOMER_STATUS_LABELS[$customerStatus] ?? $customerStatus,
            applyText: $applyText,
            revenueRange: $revenueRange,
            fixedAmount: $fixedAmount,
            calc: $calc,
            calcText: self::CALC_LABELS[$calc] ?? '%',
            valueText: $valueText,
            isActive: ! empty($rule->is_active),
            typeText: self::TYPE_LABELS[$rule->commission_type ?? 'trade_product'] ?? 'Thương mại',
            baseText: self::BASE_LABELS[$rule->base_type ?? 'revenue_before_vat'] ?? 'Doanh thu trước VAT',
        );
    }

    private function salaryRow(object $user, ?object $salary): SalesSalaryRow
    {
        $baseSalary = (float) ($salary->base_salary ?? 7000000);
        $targetRevenue = (float) ($salary->target_revenue ?? 500000000);
        $targetCommission = (float) ($salary->target_commission ?? 0);

        return new SalesSalaryRow(
            user: $user,
            salary: $salary,
            baseSalary: $baseSalary,
            targetRevenue: $targetRevenue,
            targetCommission: $targetCommission,
            isActive: ! empty($salary->is_active),
            note: (string) ($salary->note ?? ''),
            baseSalaryText: DisplayFormat::money($baseSalary),
            targetRevenueText: DisplayFormat::money($targetRevenue),
            targetCommissionText: DisplayFormat::money($targetCommission),
        );
    }

    /** @param  array<int, string>  $salesNameMap */
    private function tierRow(object $tier, array $salesNameMap): KpiTierRow
    {
        $bonusType = $tier->bonus_type ?? 'fixed';

        return new KpiTierRow(
            tier: $tier,
            isActive: ! empty($tier->is_active),
            salesName: ! empty($tier->sales_id) ? ($salesNameMap[(int) $tier->sales_id] ?? ('Sales #'.$tier->sales_id)) : 'Tất cả Sales',
            range: ! empty($tier->to_revenue)
                ? DisplayFormat::money($tier->from_revenue ?? 0).' - '.DisplayFormat::money($tier->to_revenue)
                : 'Từ '.DisplayFormat::money($tier->from_revenue ?? 0),
            bonusTypeText: self::BONUS_TYPE_LABELS[$bonusType] ?? 'Thưởng cố định',
            bonusValue: $bonusType === 'fixed' ? DisplayFormat::money($tier->bonus_amount ?? 0) : self::percent($tier->bonus_amount ?? 0),
        );
    }

    /** `2,50%` — hai chữ số thập phân, dấu phẩy. */
    private static function percent(mixed $value): string
    {
        return DisplayFormat::number($value, 2).'%';
    }
}
