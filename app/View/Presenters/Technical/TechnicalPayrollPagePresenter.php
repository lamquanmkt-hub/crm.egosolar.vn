<?php

declare(strict_types=1);

namespace App\View\Presenters\Technical;

use App\DTOs\Technical\TechnicalKpiCriteriaInput;
use App\DTOs\Technical\TechnicalPayrollListRow;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị trang lương KPI kỹ thuật (`kythuat/luong`).
 *
 * Thay ba khối `@php`: một khối đầu đọc 5 hệ số từ `$settings` và khai closure chọn icon; một khối
 * trong `@foreach($kpis)` suy ra cờ kiểu tính; một khối một dòng trong `@forelse($payrolls)`.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 */
final class TechnicalPayrollPagePresenter
{
    /** Icon mặc định xoay theo VỊ TRÍ tiêu chí (không theo khoá mảng) — giữ đúng bản cũ. */
    private const FALLBACK_ICONS = ['bi-stopwatch', 'bi-gem', 'bi-clipboard2-check', 'bi-stars', 'bi-phone', 'bi-tools', 'bi-graph-up-arrow'];

    /** Icon riêng cho ba kiểu tính đặc biệt. */
    private const TYPE_ICONS = [
        'material_waste' => 'bi-box-seam',
        'minus_safety' => 'bi-shield-check',
        'minus_quality' => 'bi-patch-check',
    ];

    /** Hệ số và giá trị mặc định, đọc từ `$settings` của controller. */
    private const RATE_DEFAULTS = [
        'base_salary_rate' => 0.70,
        'kpi_salary_rate' => 0.30,
        'kpi_max_rate' => 1.30,
        'quality_error_penalty' => 0.05,
        'safety_error_penalty' => 0.05,
    ];

    /**
     * @param  array<string, mixed>  $settings
     * @param  iterable<string|int, array<string, mixed>>  $kpis  khoá là `cfg_<id>` chứ KHÔNG phải
     *                                                            số tuần tự — phải giữ nguyên khoá
     *                                                            vì view dùng nó cho `kpis[<khoá>]`
     * @param  iterable<object>  $payrolls
     * @return array{baseRate: float, kpiRate: float, kpiMaxRate: float, qualityPenalty: float, safetyPenalty: float, criteriaCount: int, criteriaInputs: array<string|int, TechnicalKpiCriteriaInput>, payrollRows: list<TechnicalPayrollListRow>, payrollCount: int}
     */
    public function viewData(array $settings, iterable $kpis, iterable $payrolls): array
    {
        $criteriaInputs = [];
        $position = 0;
        foreach ($kpis as $key => $kpi) {
            $criteriaInputs[$key] = $this->criteriaInput($kpi, $position);
            $position++;
        }

        $rows = [];
        foreach ($payrolls as $payroll) {
            $rows[] = $this->payrollRow($payroll);
        }

        return [
            'baseRate' => $this->rate($settings, 'base_salary_rate'),
            'kpiRate' => $this->rate($settings, 'kpi_salary_rate'),
            'kpiMaxRate' => $this->rate($settings, 'kpi_max_rate'),
            'qualityPenalty' => $this->rate($settings, 'quality_error_penalty'),
            'safetyPenalty' => $this->rate($settings, 'safety_error_penalty'),
            'criteriaCount' => count($criteriaInputs),
            'criteriaInputs' => $criteriaInputs,
            'payrollRows' => $rows,
            'payrollCount' => count($rows),
        ];
    }

    /** @param  array<string, mixed>  $settings */
    private function rate(array $settings, string $key): float
    {
        return (float) ($settings[$key] ?? self::RATE_DEFAULTS[$key]);
    }

    /** @param  array<string, mixed>  $kpi */
    private function criteriaInput(array $kpi, int $position): TechnicalKpiCriteriaInput
    {
        $type = (string) ($kpi['type'] ?? '');
        $name = (string) ($kpi['name'] ?? '');

        return new TechnicalKpiCriteriaInput(
            definitionId: (string) ($kpi['definition_id'] ?? ''),
            name: $name,
            unit: (string) ($kpi['unit'] ?? ''),
            weight: $kpi['weight'] ?? null,
            weightPercentText: number_format(((float) ($kpi['weight'] ?? 0)) * 100, 0),
            type: $type,
            // Bản cũ: `$kpi['subject'] ?? $kpi['name']` — subject rỗng thì lấy tên.
            subject: (string) ($kpi['subject'] ?? $name),
            rule: (string) ($kpi['rule'] ?? ''),
            source: (string) ($kpi['source'] ?? ''),
            icon: self::TYPE_ICONS[$type] ?? self::FALLBACK_ICONS[$position % count(self::FALLBACK_ICONS)],
            isMaterial: $type === 'material_waste',
            isErrorBased: in_array($type, ['minus_quality', 'minus_safety'], true),
            defaultPlan: $kpi['default_plan'] ?? 1,
            defaultActual: $kpi['default_actual'] ?? 1,
        );
    }

    private function payrollRow(object $payroll): TechnicalPayrollListRow
    {
        return new TechnicalPayrollListRow(
            id: (int) ($payroll->id ?? 0),
            employeeName: (string) ($payroll->employee_name ?? '—'),
            positionName: (string) ($payroll->position_name ?? 'Kỹ thuật'),
            // Bản cũ xếp hạng dự phòng: month_label -> payroll_month -> gạch dài.
            monthText: (string) ($payroll->month_label ?? $payroll->payroll_month ?? '—'),
            // KPI lưu dạng tỷ lệ (0,925) nên nhân 100 rồi lấy 1 chữ số thập phân.
            kpiPercentText: number_format(((float) ($payroll->total_kpi_percent ?? 0)) * 100, 1, ',', '.').'%',
            grossText: DisplayFormat::money($payroll->gross_salary ?? 0),
            incomeText: DisplayFormat::money($payroll->total_income ?? 0),
            approved: (string) ($payroll->status ?? 'draft') === 'approved',
        );
    }
}
