<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\SettlementAdvanceOption;
use App\DTOs\Finance\SettlementKpiCards;
use App\DTOs\Finance\SettlementRequestRow;
use App\Support\DisplayFormat;
use Illuminate\Support\Carbon;

/**
 * Chuẩn bị trang `settlement_requests/index` (đề nghị hoàn ứng).
 *
 * Thay khối `@php` phòng hờ ở đầu view (controller luôn truyền đủ 4 khoá đó) và gom mọi phép tính
 * mà view cũ làm ngay trong Blade: 6 lượt `number_format`, `optional(...)->format()`,
 * `Carbon::parse()` trong vòng lặp `<option>`, nhánh ba chiều của kết quả đối soát, và 4 điều kiện
 * hiện nút — mỗi cái đều tự gọi `auth()->id()`.
 *
 * Lớp này thuần: không Facade, không query, không `auth()`/`request()`.
 */
final class SettlementRequestListPresenter
{
    /** Trạng thái mà chủ phiếu được gửi (lại) duyệt. */
    private const TRANG_THAI_GUI_LAI = ['draft', 'admin_rejected', 'accounting_rejected'];

    /**
     * @param  iterable<object>  $items  trang hiện tại của paginator, đã eager load `advanceRequest`
     * @param  iterable<object>  $advances  phiếu tạm ứng đã chi và chưa hoàn ứng
     * @param  array<string, string>  $labels
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $rawFilters
     * @param  array<string, mixed>  $oldInput
     * @return array{settlementRows: list<SettlementRequestRow>, advanceOptions: list<SettlementAdvanceOption>,
     *               kpi: SettlementKpiCards, filters: array<string, string>, createForm: array<string, string>}
     */
    public function viewData(
        iterable $items,
        iterable $advances,
        array $labels,
        ?int $currentUserId,
        bool $canViewAll,
        bool $canApproveManagement,
        bool $canApproveAccounting,
        array $stats,
        int $pageCount,
        int $totalCount,
        array $rawFilters,
        array $oldInput,
    ): array {
        $rows = [];

        foreach ($items as $item) {
            $status = (string) ($item->status ?? '');
            $laChuPhieu = $currentUserId !== null && (int) ($item->created_by ?? 0) === $currentUserId;
            $code = (string) ($item->code ?? '');

            $rows[] = new SettlementRequestRow(
                id: (int) ($item->id ?? 0),
                code: $code,
                // Bản cũ: `optional($item->created_at)->format('d/m/Y H:i')` — ngày trống ra chuỗi
                // RỖNG, không phải gạch ngang, nên KHÔNG dùng được `DisplayFormat::date()`.
                createdAtText: $item->created_at ? Carbon::parse($item->created_at)->format('d/m/Y H:i') : '',
                advanceRequestId: (int) (($item->advanceRequest->id ?? 0)),
                advanceCode: (string) ($item->advanceRequest->code ?? ''),
                recipientName: (string) ($item->recipient_name ?? ''),
                company: (string) ($item->company ?? ''),
                reason: (string) ($item->reason ?? ''),
                note: (string) ($item->note ?? ''),
                advanceAmountText: DisplayFormat::money($item->advance_amount ?? 0),
                actualAmountText: DisplayFormat::money($item->actual_amount ?? 0),
                outcomeText: $this->outcomeText($item),
                outcomeClass: $this->outcomeClass($item),
                attachments: $this->attachments($item->attachments ?? null),
                statusLabel: $labels[$status] ?? $status,
                canSubmit: $laChuPhieu && in_array($status, self::TRANG_THAI_GUI_LAI, true),
                canManagementApprove: $canApproveManagement && $status === 'submitted',
                canAccountingApprove: $canApproveAccounting && $status === 'admin_approved',
                // Bản cũ: `$canViewAll || (chủ phiếu && status === 'draft')`.
                canDelete: $canViewAll || ($laChuPhieu && $status === 'draft'),
                deleteConfirmText: 'Xóa phiếu '.$code.'?',
            );
        }

        $options = [];
        $oldAdvanceId = array_key_exists('advance_request_id', $oldInput)
            ? (string) ($oldInput['advance_request_id'] ?? '')
            : '';

        foreach ($advances as $advance) {
            $amount = (float) ($advance->amount ?? 0);
            $code = (string) ($advance->code ?? '');
            $recipient = (string) ($advance->recipient_name ?? '');
            $amountText = DisplayFormat::number($amount);

            $options[] = new SettlementAdvanceOption(
                id: (int) ($advance->id ?? 0),
                code: $code,
                amount: $amount,
                amountText: $amountText.' đ',
                recipient: $recipient,
                // ⚠️ Giữ NGUYÊN giá trị thô (chuỗi rỗng khi trống), đúng `data-company` của bản cũ.
                // JS cũ có đọc `option.dataset.company || '-'` nhưng KHÔNG dùng `company` ở đâu cả —
                // đây là dữ liệu chết, sẽ bỏ khi chuyển sang Alpine.
                company: (string) ($advance->company ?? ''),
                dueText: $advance->settlement_due_date
                    ? Carbon::parse($advance->settlement_due_date)->format('d/m/Y')
                    : '-',
                label: $code.' — '.$recipient.' — '.$amountText.' đ',
                selected: $oldAdvanceId !== '' && $oldAdvanceId === (string) ($advance->id ?? ''),
            );
        }

        return [
            'settlementRows' => $rows,
            'advanceOptions' => $options,
            'kpi' => new SettlementKpiCards(
                total: DisplayFormat::number($stats['total'] ?? 0),
                pending: DisplayFormat::number($stats['pending'] ?? 0),
                done: DisplayFormat::number($stats['done'] ?? 0),
                refundText: DisplayFormat::money($stats['refund'] ?? 0),
                pageCount: DisplayFormat::number($pageCount),
                totalCount: DisplayFormat::number($totalCount),
            ),
            'filters' => [
                'q' => (string) ($rawFilters['q'] ?? ''),
                'status' => (string) ($rawFilters['status'] ?? ''),
                'created_by' => (string) ($rawFilters['created_by'] ?? ''),
                'date_from' => (string) ($rawFilters['date_from'] ?? ''),
                'date_to' => (string) ($rawFilters['date_to'] ?? ''),
            ],
            'createForm' => [
                'actual_amount' => $this->old($oldInput, 'actual_amount'),
                'reason' => $this->old($oldInput, 'reason'),
                'note' => $this->old($oldInput, 'note'),
            ],
        ];
    }

    /** Kết quả đối soát — ba nhánh, chép đúng bản cũ kể cả chữ viết tắt `Cty`. */
    private function outcomeText(object $item): string
    {
        return match ((string) ($item->settlement_type ?? '')) {
            'refund' => 'Hoàn lại '.DisplayFormat::number($item->refund_amount ?? 0).' đ',
            // `max(0, …)` của bản cũ: chênh lệch âm thì in 0 chứ không in số âm.
            'pay_more' => 'Cty trả thêm '.DisplayFormat::number(max(0, (float) ($item->difference_amount ?? 0))).' đ',
            default => 'Khớp đủ',
        };
    }

    private function outcomeClass(object $item): string
    {
        return match ((string) ($item->settlement_type ?? '')) {
            'refund' => 'tw:text-[#087f98]',
            'pay_more' => 'tw:text-[#c2410c]',
            default => 'tw:text-[#15803d]',
        };
    }

    /**
     * Chứng từ: bản cũ ép `(array)` rồi `@forelse` với `$i+1` làm số thứ tự hiển thị.
     *
     * @return list<array{index: int, path: string}>
     */
    private function attachments(mixed $attachments): array
    {
        $ds = [];
        foreach ((array) $attachments as $i => $path) {
            $ds[] = ['index' => ((int) $i) + 1, 'path' => (string) $path];
        }

        return $ds;
    }

    /**
     * Tương đương `old($field)`; `array_key_exists` vì `old()` đi qua `Arr::get`.
     *
     * @param  array<string, mixed>  $oldInput
     */
    private function old(array $oldInput, string $field): string
    {
        return array_key_exists($field, $oldInput) ? (string) ($oldInput[$field] ?? '') : '';
    }
}
