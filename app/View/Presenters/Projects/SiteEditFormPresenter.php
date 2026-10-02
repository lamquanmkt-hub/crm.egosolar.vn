<?php

declare(strict_types=1);

namespace App\View\Presenters\Projects;

use App\Models\Projects\Site;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Giá trị điền sẵn cho form sửa công trình `sites.edit`.
 *
 * Trước 2026-09-07 view tự tính trong `@php`: ưu tiên old input (lần gửi trước bị lỗi) rồi mới
 * tới dữ liệu đã lưu, dòng mặc định khi chưa có thiết bị / vật tư / đợt thu, ngày về `Y-m-d`,
 * tách chuỗi kỹ thuật viên "A, B; C" thành chip. Tên khoá trả về giữ tên biến cũ của view.
 */
final class SiteEditFormPresenter
{
    private const DEFAULT_DEVICE_ROW = [
        'type' => 'inverter', 'brand' => '', 'model' => '', 'serial' => '', 'power_kw' => '', 'capacity_kwh' => '', 'qty' => 1, 'warranty_to' => '',
    ];

    private const DEFAULT_PLANNED_ROW = ['name' => '', 'unit' => '', 'qty' => 0];

    private const DEFAULT_PAYMENT_TERM_ROWS = [
        ['name' => 'Đợt 1 - Đặt cọc', 'percent' => 30, 'amount' => '', 'due_date' => '', 'note' => ''],
        ['name' => 'Đợt 2 - Giao vật tư / triển khai', 'percent' => 40, 'amount' => '', 'due_date' => '', 'note' => ''],
        ['name' => 'Đợt 3 - Nghiệm thu / bàn giao', 'percent' => 30, 'amount' => '', 'due_date' => '', 'note' => ''],
    ];

    /**
     * @param  array<string, mixed>  $old  old input của phiên (`$request->old()`), rỗng khi mở form lần đầu
     * @param  list<array<string, mixed>>  $devices  thiết bị đã lưu (SiteService::getEditDevices)
     * @param  list<array<string, mixed>>  $planned  vật tư kế hoạch đã lưu
     * @param  list<array<string, mixed>>  $paymentTerms  đợt thu đã lưu
     * @param  array{label: string, index_url: string}  $projectMeta
     * @param  mixed  $defaultCompanyId  công ty đang chọn trong phiên, dùng khi công trình chưa có công ty
     * @return array<string, mixed>
     */
    public function viewData(Site $site, array $old, array $devices, array $planned, array $paymentTerms, string $projectType, array $projectMeta, mixed $defaultCompanyId): array
    {
        $technicianRaw = (string) Arr::get($old, 'technician_name', $site->technician_name ?? '');
        $deviceRows = $this->rows(Arr::get($old, 'devices'), $devices, [self::DEFAULT_DEVICE_ROW]);

        return [
            'site' => $site,
            'projectType' => $projectType,
            'projectLabel' => $projectMeta['label'],
            'projectIndexUrl' => $projectMeta['index_url'],
            'egoOldSiteCompanyId' => Arr::get($old, 'company_id', $site->company_id ?? $defaultCompanyId),

            'devicesRows' => array_map(fn (array $row) => ['type' => $row['type'] ?? 'inverter'] + $row, $deviceRows),
            'plannedRows' => $this->rows(Arr::get($old, 'planned'), $planned, [self::DEFAULT_PLANNED_ROW]),
            'paymentTermRows' => $this->rows(Arr::get($old, 'payment_terms'), $paymentTerms, self::DEFAULT_PAYMENT_TERM_ROWS),

            'installedAt' => Arr::get($old, 'installed_at', $this->dateInput($site->installed_at)),
            'warrantyTo' => Arr::get($old, 'warranty_to', $this->dateInput($site->warranty_to)),
            'contractSignedAt' => Arr::get($old, 'contract_signed_at', $this->dateInput($site->contract_signed_at)),
            'contractAmount' => Arr::get($old, 'contract_amount', $site->contract_amount ?? 0),
            'financeNote' => Arr::get($old, 'finance_note', $site->finance_note ?? ''),
            'techRaw' => $technicianRaw,
            'techList' => array_values(array_filter(array_map('trim', preg_split('/[,;]+/u', $technicianRaw) ?: []), fn (string $name) => $name !== '')),

            'st' => Arr::get($old, 'status', $site->status ?? ''),
            'stype' => Arr::get($old, 'system_type', $site->system_type ?? ''),
            'ph' => Arr::get($old, 'phase', $site->phase ?? ''),
            'stage' => Arr::get($old, 'stage', $site->stage ?? ''),
        ];
    }

    /**
     * Dòng lặp của form: old input → dữ liệu đã lưu → dòng mặc định.
     *
     * @param  list<array<string, mixed>>  $saved
     * @param  list<array<string, mixed>>  $defaults
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $old, array $saved, array $defaults): array
    {
        $rows = $old;
        if (! $rows && $saved !== []) {
            $rows = $saved;
        }
        if (! $rows || ! is_array($rows) || count($rows) === 0) {
            return $defaults;
        }

        // Giữ nguyên khoá: old input có thể là devices[0], devices[2] sau khi người dùng xoá dòng giữa.
        return $rows;
    }

    /** Ngày lưu trong DB → `Y-m-d` cho `<input type="date">`; trống giữ rỗng. */
    private function dateInput(mixed $value): string
    {
        return ! empty($value) ? Carbon::parse($value)->format('Y-m-d') : '';
    }
}
