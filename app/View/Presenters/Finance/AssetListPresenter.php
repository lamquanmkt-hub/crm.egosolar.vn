<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\AssetEventRow;
use App\DTOs\Finance\AssetFileRow;
use App\DTOs\Finance\AssetFormValues;
use App\DTOs\Finance\AssetRow;
use App\DTOs\Finance\AssetSummaryCards;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị trang `finance/assets` (danh sách tài sản, khấu hao, lịch sử, biểu mẫu sửa).
 *
 * Thay hai khối `@php`:
 *  1. khối đầu view chính khai 3 closure `$money` / `$dateText` / `$numInput` — trong đó `$numInput`
 *     là BIẾN CHẾT (chỉ xuất hiện đúng ở chỗ gán, không nơi nào gọi);
 *  2. khối trong partial `form-fields` khai `$val` / `$num` và gọi `old()` ngay trong Blade — partial
 *     đó được include N+1 lần mỗi trang nên hai closure được dựng lại từng ấy lần.
 *
 * Lớp này thuần: không Facade, không query, không `request()/auth()/session()`. Old input vào bằng
 * tham số `$oldInput` (controller lấy từ `$request->old()`).
 */
final class AssetListPresenter
{
    /**
     * Trạng thái → chuỗi lớp nền/chữ của huy hiệu, quy đổi từ `.ap-badge.<trạng thái>` trong khối
     * `<style>` cũ. Trạng thái không có trong bảng (dữ liệu cũ) cho chuỗi RỖNG — đúng như bản cũ:
     * huy hiệu khi đó chỉ có hình dạng, không có nền và màu riêng.
     *
     * Chuỗi lớp phải TĨNH trong mã nguồn thì Tailwind mới quét thấy; ghép `tw:bg-[…]` lúc chạy là
     * lớp không bao giờ được sinh ra.
     *
     * @var array<string, string>
     */
    private const STATUS_BADGES = [
        'active' => 'tw:bg-[#dcfce7] tw:text-[#047857]',
        'idle' => 'tw:bg-[#f1f5f9] tw:text-[#334155]',
        'repair' => 'tw:bg-[#fef3c7] tw:text-[#b45309]',
        'maintenance' => 'tw:bg-[#fef3c7] tw:text-[#b45309]',
        'liquidated' => 'tw:bg-[#e0f2fe] tw:text-[#0369a1]',
        'lost' => 'tw:bg-[#ffe4e6] tw:text-[#be123c]',
    ];

    /**
     * @param  iterable<object>  $assets  trang hiện tại của paginator, mỗi phần tử đã được controller
     *                                    gắn thêm các chỉ số khấu hao
     * @param  array<string, string>  $statuses
     * @param  array<string, string>  $conditions
     * @param  array<string, string>  $eventTypes
     * @param  array<string, mixed>  $oldInput  toàn bộ old input của session (rỗng khi không có)
     * @param  array<string, mixed>  $summary  chỉ số tổng hợp toàn bộ tài sản (không chỉ trang này)
     * @return array{assetRows: list<AssetRow>, createForm: AssetFormValues, summaryCards: AssetSummaryCards}
     */
    public function viewData(
        iterable $assets,
        array $statuses,
        array $conditions,
        array $eventTypes,
        array $oldInput,
        int $sttOffset,
        array $summary,
    ): array {
        $rows = [];
        $stt = 0;

        foreach ($assets as $asset) {
            $stt++;

            $rows[] = new AssetRow(
                id: (int) ($asset->id ?? 0),
                stt: $stt + $sttOffset,
                code: (string) ($asset->code ?? ''),
                name: (string) ($asset->name ?? ''),
                serialText: ((string) ($asset->serial_no ?? '')) ?: '—',
                locationText: ((string) ($asset->location ?? '')) ?: '—',
                categoryName: ((string) ($asset->category_name ?? '')) ?: 'Chưa phân nhóm',
                categoryColor: ((string) ($asset->category_color ?? '')) ?: '#0ea5e9',
                companyName: ((string) ($asset->company_name ?? '')) ?: '—',
                assignedText: $this->assignedText($asset),
                // Closure `$money` cũ là `number_format($v, 0, ',', '.').' đ'` — trùng TỪNG KÝ TỰ
                // với `DisplayFormat::money()`, nên uỷ quyền thay vì chép lại.
                costText: DisplayFormat::money($asset->original_cost ?? 0),
                bookValueText: DisplayFormat::money($asset->book_value ?? 0),
                accumulatedText: DisplayFormat::money($asset->accumulated_depreciation ?? 0),
                monthlyText: DisplayFormat::money($asset->monthly_depreciation ?? 0),
                progressPercentText: (string) ($asset->progress_percent ?? 0),
                usageText: ($asset->used_months ?? 0).'/'.($asset->useful_life_months ?? 0).' tháng',
                statusBadgeClass: self::STATUS_BADGES[$asset->status ?? ''] ?? '',
                statusLabel: $statuses[$asset->status ?? ''] ?? (string) ($asset->status ?? ''),
                conditionLabel: $conditions[$asset->condition ?? ''] ?? (string) ($asset->condition ?? ''),
                // `$dateText` cũ (`!$v` → `—`, `d/m/Y`, catch trả nguyên chuỗi) trùng `DisplayFormat::date()`.
                nextMaintenanceText: DisplayFormat::date($asset->next_maintenance_date ?? null),
                warrantyText: DisplayFormat::date($asset->warranty_until ?? null),
                events: $this->events($asset->events ?? [], $eventTypes),
                files: $this->files($asset->files ?? []),
                form: $this->formValues($oldInput, $asset),
            );
        }

        return [
            'assetRows' => $rows,
            'createForm' => $this->formValues($oldInput, null),
            'summaryCards' => new AssetSummaryCards(
                countText: DisplayFormat::number($summary['count'] ?? 0),
                activeText: DisplayFormat::number($summary['active'] ?? 0),
                costText: DisplayFormat::money($summary['total_cost'] ?? 0),
                bookValueText: DisplayFormat::money($summary['book_value'] ?? 0),
                accumulatedText: DisplayFormat::money($summary['accumulated'] ?? 0),
                warningText: DisplayFormat::number($summary['maintenance_warning'] ?? 0),
            ),
        ];
    }

    /**
     * Người giữ + bộ phận, đúng hai lượt in liền nhau của bản cũ:
     * `{{ $asset->assigned_name ?: 'Chưa bàn giao' }}{{ $asset->department ? ' · '.$asset->department : '' }}`.
     */
    private function assignedText(object $asset): string
    {
        $name = ((string) ($asset->assigned_name ?? '')) ?: 'Chưa bàn giao';
        $department = (string) ($asset->department ?? '');

        return $department !== '' ? $name.' · '.$department : $name;
    }

    /**
     * @param  iterable<object>  $events
     * @param  array<string, string>  $eventTypes
     * @return list<AssetEventRow>
     */
    private function events(iterable $events, array $eventTypes): array
    {
        $rows = [];
        foreach ($events as $event) {
            $amount = $event->amount ?? null;

            $rows[] = new AssetEventRow(
                typeLabel: $eventTypes[$event->type ?? ''] ?? (string) ($event->type ?? ''),
                dateText: DisplayFormat::date($event->event_date ?? null),
                noteText: ((string) ($event->note ?? '')) ?: '—',
                // ⚠️ Giữ đúng `@if($event->amount)` của bản cũ: chuỗi `"0.00"` là truthy.
                amountText: $amount ? DisplayFormat::money($amount) : null,
            );
        }

        return $rows;
    }

    /**
     * @param  iterable<object>  $files
     * @return list<AssetFileRow>
     */
    private function files(iterable $files): array
    {
        $rows = [];
        foreach ($files as $file) {
            $rows[] = new AssetFileRow(
                id: (int) ($file->id ?? 0),
                name: ((string) ($file->original_name ?? '')) ?: 'File',
            );
        }

        return $rows;
    }

    /**
     * Dựng giá trị biểu mẫu đúng thứ tự ưu tiên cũ: old input → thuộc tính tài sản → mặc định.
     *
     * @param  array<string, mixed>  $oldInput
     */
    private function formValues(array $oldInput, ?object $asset): AssetFormValues
    {
        return new AssetFormValues(
            code: $this->text($oldInput, $asset, 'code'),
            name: $this->text($oldInput, $asset, 'name'),
            categoryId: $this->text($oldInput, $asset, 'category_id'),
            companyId: $this->text($oldInput, $asset, 'company_id'),
            assignedTo: $this->text($oldInput, $asset, 'assigned_to'),
            department: $this->text($oldInput, $asset, 'department'),
            serialNo: $this->text($oldInput, $asset, 'serial_no'),
            purchaseDate: $this->text($oldInput, $asset, 'purchase_date'),
            startUseDate: $this->text($oldInput, $asset, 'start_use_date'),
            warrantyUntil: $this->text($oldInput, $asset, 'warranty_until'),
            nextMaintenanceDate: $this->text($oldInput, $asset, 'next_maintenance_date'),
            originalCost: $this->numberText($oldInput, $asset, 'original_cost'),
            salvageValue: $this->numberText($oldInput, $asset, 'salvage_value'),
            usefulLifeMonths: $this->text($oldInput, $asset, 'useful_life_months', '36'),
            depreciationMethod: $this->text($oldInput, $asset, 'depreciation_method', 'straight_line'),
            status: $this->text($oldInput, $asset, 'status', 'active'),
            condition: $this->text($oldInput, $asset, 'condition', 'good'),
            vendor: $this->text($oldInput, $asset, 'vendor'),
            invoiceNo: $this->text($oldInput, $asset, 'invoice_no'),
            location: $this->text($oldInput, $asset, 'location'),
            note: $this->text($oldInput, $asset, 'note'),
        );
    }

    /**
     * Tương đương `old($field, $asset->{$field} ?? $default)`.
     *
     * Dùng `array_key_exists` chứ không `??` vì `old()` đi qua `Arr::get`, tức khoá tồn tại với giá
     * trị null vẫn được trả về chứ không rơi về mặc định.
     *
     * @param  array<string, mixed>  $oldInput
     */
    private function text(array $oldInput, ?object $asset, string $field, string $default = ''): string
    {
        if (array_key_exists($field, $oldInput)) {
            return (string) ($oldInput[$field] ?? '');
        }

        return (string) ($asset->{$field} ?? $default);
    }

    /**
     * Tương đương closure `$num` cũ: trống thì trả rỗng, số nguyên thì bỏ phần thập phân, số lẻ thì
     * cắt các số 0 vô nghĩa. Dấu chấm thập phân, KHÔNG phân cách nghìn — đây là giá trị cho
     * `<input inputmode="decimal">`, không phải chuỗi hiển thị.
     *
     * @param  array<string, mixed>  $oldInput
     */
    private function numberText(array $oldInput, ?object $asset, string $field): string
    {
        $value = array_key_exists($field, $oldInput)
            ? $oldInput[$field]
            : ($asset->{$field} ?? 0);

        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;

        return abs($number - round($number)) < 0.00001
            ? (string) (int) round($number)
            : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
