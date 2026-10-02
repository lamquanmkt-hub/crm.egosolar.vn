<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\SalaryComponentRow;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị trang phiếu lương chi tiết (`finance/salary-detail`).
 *
 * Thay bốn khối `@php`: một khối đầu lọc thành phần theo section, chuẩn hoá điểm KPI và khai một
 * closure định dạng ba kiểu; ba khối còn lại là `$def=$row->definition;` lặp trong ba vòng lặp.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 */
final class SalaryDetailPresenter
{
    /**
     * @param  iterable<object>  $componentRows  mỗi phần tử có `definition` và `amount`
     * @param  array<string, mixed>  $salaryMeta
     * @param  object|null  $technicalKpi  bản chụp KPI kỹ thuật; null khi nhân viên không có
     * @return array{infoRows: list<SalaryComponentRow>, incomeRows: list<SalaryComponentRow>, deductionRows: list<SalaryComponentRow>, noteText: string, kpiPercentText: string}
     */
    public function viewData(iterable $componentRows, array $salaryMeta, ?object $technicalKpi): array
    {
        $sections = ['info' => [], 'income' => [], 'deduction' => []];

        foreach ($componentRows as $row) {
            $definition = $row->definition ?? null;
            $section = (string) ($definition->section ?? '');
            if (! array_key_exists($section, $sections)) {
                continue;
            }

            $sections[$section][] = new SalaryComponentRow(
                id: (int) ($definition->id ?? 0),
                label: (string) ($definition->label ?? ''),
                editable: (bool) ($definition->editable_amount ?? false),
                amountValue: (float) ($row->amount ?? 0),
                valueText: $this->formatValue($row->amount ?? 0, (string) ($definition->display_format ?? 'money')),
            );
        }

        return [
            'infoRows' => $sections['info'],
            'incomeRows' => $sections['income'],
            'deductionRows' => $sections['deduction'],
            'noteText' => (string) ($salaryMeta['note_text'] ?? ''),
            'kpiPercentText' => $this->kpiPercentText($technicalKpi),
        ];
    }

    /**
     * Định dạng theo `display_format` của định nghĩa.
     *
     * Kiểu `number` CỐ Ý dùng dấu phân cách kiểu Anh (`.` thập phân, `,` nghìn) rồi cắt số 0 vô
     * nghĩa ở cuối — khác `DisplayFormat::number`. Giữ y bản cũ để HTML không đổi.
     */
    private function formatValue(mixed $amount, string $format): string
    {
        if ($format === 'number') {
            return rtrim(rtrim(number_format((float) $amount, 2, '.', ','), '0'), '.');
        }

        if ($format === 'percent') {
            return number_format((float) $amount, 1, ',', '.').'%';
        }

        return DisplayFormat::money($amount);
    }

    /**
     * Điểm KPI đã định dạng; không có bản chụp KPI thì trả dấu `—` như bản cũ.
     *
     * Giữ cả phép chuẩn hoá cũ: giá trị trong khoảng (0; 2] được coi là tỷ lệ (0,85) nên nhân 100
     * thành phần trăm. Đây là suy đoán theo dữ liệu cũ, không phải quy tắc nghiệp vụ — giữ nguyên
     * vì đổi là đổi số hiển thị.
     */
    private function kpiPercentText(?object $technicalKpi): string
    {
        if ($technicalKpi === null) {
            return '—';
        }

        $percent = (float) ($technicalKpi->total_kpi_percent ?? 0);
        if ($percent > 0 && $percent <= 2) {
            $percent *= 100;
        }

        return DisplayFormat::percent($percent, 1);
    }
}
