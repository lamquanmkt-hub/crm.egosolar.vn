<?php

declare(strict_types=1);

namespace App\View\Presenters\Technical;

use App\DTOs\Technical\KpiCriteriaRow;
use App\DTOs\Technical\KpiSettingRow;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị trang cài đặt hệ số KPI kỹ thuật (`kythuat/luong_settings`).
 *
 * Thay hai khối `@php` của view: khối đầu tự chạy truy vấn `technical_payroll_kpi_items`, tự gọi
 * `auth()->user()->hasRole()` và một service; khối thứ hai nằm TRONG vòng lặp chỉ để phân biệt
 * dòng lấy từ DB (object) với 5 tiêu chí mặc định (mảng). Truy vấn và quyền nay do controller lo,
 * còn việc phân biệt hai dạng biến mất nhờ DTO `KpiCriteriaRow`.
 *
 * Lớp này thuần: không Facade, không query, không `auth()`/`request()`.
 */
final class TechnicalPayrollSettingsPresenter
{
    /** Icon theo thứ tự tiêu chí, giữ đúng bản cũ; ngoài phạm vi thì về `bi-bar-chart`. */
    private const ICONS = [1 => 'bi-stopwatch', 2 => 'bi-gem', 3 => 'bi-box-seam', 4 => 'bi-shield-check', 5 => 'bi-phone'];

    /**
     * Giá trị mặc định của ba hệ số, dạng [tỷ lệ, nhãn, ghi chú].
     *
     * Bản cũ lặp lại mấy con số này ở 4 chỗ trong Blade (ô tóm tắt, ô input, hai input hidden);
     * gom về một nơi để sửa quy ước là sửa một chỗ.
     */
    private const SETTING_DEFAULTS = [
        'base_salary_rate' => [0.70, 'Tỷ lệ lương cố định', 'Phần lương cố định trên Gross.'],
        'kpi_salary_rate' => [0.30, 'Tỷ lệ lương KPI', 'Phần lương biến đổi theo KPI.'],
        'kpi_max_rate' => [1.30, 'Trần KPI tổng', 'Giới hạn KPI tối đa dùng khi tính lương.'],
    ];

    /** 5 tiêu chí dùng khi `technical_payroll_kpi_items` chưa có dòng nào được bật. */
    private const DEFAULT_CRITERIA = [
        ['Tiến độ hoàn thành lắp đặt hệ thống', 'Công trình', 1, 1, 0.30, 'actual_div_plan', 'TH / KH. TH = công trình hoàn thành đúng hạn; KH = tổng công trình đến hạn trong kỳ.', 1],
        ['Chất lượng thi công & thẩm mỹ', 'Công trình', 1, 1, 0.25, 'actual_div_plan', 'TH / KH. TH = công trình nghiệm thu đạt ngay lần đầu; KH = tổng công trình nghiệm thu.', 2],
        ['Khảo sát kỹ thuật & khối lượng', '% hao hụt', 0, 0, 0.15, 'material_waste', '0%=120%; >0–2%=100%; >2–4%=85%; >4–6%=70%; >6%=0%.', 3],
        ['An toàn lao động (HSE) & vệ sinh', 'Công trình', 1, 1, 0.15, 'actual_div_plan', 'TH / KH. Vi phạm HSE nghiêm trọng có thể trừ thêm 10–20 điểm KPI.', 4],
        ['Hỗ trợ thủ tục EVN & cài đặt App', 'Công trình', 1, 1, 0.15, 'actual_div_plan', 'TH / KH. Chỉ tính các công trình có yêu cầu EVN/App trong kỳ.', 5],
    ];

    /**
     * @param  iterable<object>  $settings  các dòng `technical_kpi_settings`
     * @param  iterable<object>  $kpiItems  các dòng `technical_payroll_kpi_items`, đã sắp theo
     *                                      sort_order rồi id; rỗng khi bảng chưa tồn tại
     * @param  bool  $canManageKpi  quyền sửa, controller quyết định (presenter không đọc auth)
     * @param  array<string, string>  $projectSourceOptions  từ ProjectKpiLinkService::sourceOptions()
     * @return array{baseSetting: KpiSettingRow, kpiSalarySetting: KpiSettingRow, maxSetting: KpiSettingRow, criteriaRows: list<KpiCriteriaRow>, criteriaCount: int, legacyRows: list<KpiCriteriaRow>, canManageKpi: bool, projectSourceOptions: array<string, string>}
     */
    public function viewData(iterable $settings, iterable $kpiItems, bool $canManageKpi, array $projectSourceOptions): array
    {
        $map = [];
        foreach ($settings as $row) {
            $map[(string) ($row->setting_key ?? '')] = $row;
        }

        $items = $kpiItems instanceof Collection ? $kpiItems->all() : iterator_to_array($kpiItems, false);

        $enabled = [];
        $disabled = [];
        foreach ($items as $item) {
            // Cột is_enabled là tinyint; so lỏng để nhận cả '1' từ query builder.
            if ((int) ($item->is_enabled ?? 0) === 1) {
                $enabled[] = $item;
            } else {
                $disabled[] = $item;
            }
        }

        $criteriaRows = $enabled !== [] ? $this->fromDatabase($enabled) : $this->fromDefaults();

        return [
            'baseSetting' => $this->setting($map, 'base_salary_rate'),
            'kpiSalarySetting' => $this->setting($map, 'kpi_salary_rate'),
            'maxSetting' => $this->setting($map, 'kpi_max_rate'),
            'criteriaRows' => $criteriaRows,
            'criteriaCount' => count($criteriaRows),
            'legacyRows' => $this->legacy($disabled),
            'canManageKpi' => $canManageKpi,
            'projectSourceOptions' => $projectSourceOptions,
        ];
    }

    /** @param  array<string, object>  $map */
    private function setting(array $map, string $key): KpiSettingRow
    {
        [$defaultRate, $defaultLabel, $defaultNote] = self::SETTING_DEFAULTS[$key];
        $row = $map[$key] ?? null;

        $rate = (float) ($row->setting_value ?? $defaultRate);

        return new KpiSettingRow(
            percentSummary: number_format($rate * 100, 0),
            // Dấu chấm thập phân, không phân cách nghìn — `<input type=number>` không nhận dấu phẩy.
            percentInput: number_format($rate * 100, 2, '.', ''),
            label: (string) ($row->setting_label ?? $defaultLabel),
            note: (string) ($row->note ?? $defaultNote),
        );
    }

    /**
     * @param  list<object>  $rows
     * @return list<KpiCriteriaRow>
     */
    private function fromDatabase(array $rows): array
    {
        $ra = [];
        foreach ($rows as $idx => $row) {
            $ra[] = new KpiCriteriaRow(
                id: isset($row->id) ? (int) $row->id : null,
                key: 'solar_'.$idx,
                name: (string) ($row->name ?? ''),
                unit: (string) ($row->unit ?? ''),
                plan: $row->plan_value ?? null,
                actual: $row->actual_value ?? null,
                weightPercent: ((float) ($row->weight ?? 0)) * 100,
                calcType: (string) ($row->calc_type ?? ''),
                sourceCode: (string) ($row->source_code ?? 'manual'),
                note: (string) ($row->note ?? ''),
                order: $row->sort_order ?? ($idx + 1),
                icon: self::ICONS[$idx + 1] ?? 'bi-bar-chart',
            );
        }

        return $ra;
    }

    /** @return list<KpiCriteriaRow> */
    private function fromDefaults(): array
    {
        $ra = [];
        foreach (self::DEFAULT_CRITERIA as $idx => [$name, $unit, $plan, $actual, $weight, $calcType, $note, $order]) {
            $ra[] = new KpiCriteriaRow(
                id: null,
                key: 'solar_'.$idx,
                name: $name,
                unit: $unit,
                plan: $plan,
                actual: $actual,
                weightPercent: $weight * 100,
                calcType: $calcType,
                sourceCode: 'manual',
                note: $note,
                order: $order,
                icon: self::ICONS[$idx + 1] ?? 'bi-bar-chart',
            );
        }

        return $ra;
    }

    /**
     * Dòng đã tắt: view chỉ ghi lại nguyên trạng bằng input hidden để lần lưu sau không mất chúng.
     *
     * @param  list<object>  $rows
     * @return list<KpiCriteriaRow>
     */
    private function legacy(array $rows): array
    {
        $ra = [];
        foreach ($rows as $row) {
            $id = isset($row->id) ? (int) $row->id : null;
            $ra[] = new KpiCriteriaRow(
                id: $id,
                key: 'legacy_'.$id,
                name: (string) ($row->name ?? ''),
                unit: (string) ($row->unit ?? ''),
                plan: $row->plan_value ?? null,
                actual: $row->actual_value ?? null,
                weightPercent: ((float) ($row->weight ?? 0)) * 100,
                calcType: (string) ($row->calc_type ?? ''),
                sourceCode: (string) ($row->source_code ?? 'manual'),
                note: (string) ($row->note ?? ''),
                order: $row->sort_order ?? 99,
            );
        }

        return $ra;
    }
}
