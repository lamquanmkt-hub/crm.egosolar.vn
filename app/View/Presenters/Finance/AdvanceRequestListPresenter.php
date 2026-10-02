<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\AdvanceKpiCards;
use App\DTOs\Finance\AdvanceRequestRow;
use App\DTOs\Finance\AdvanceStage;
use App\Support\DisplayFormat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Chuẩn bị trang `advance_requests/index` (danh sách đề nghị tạm ứng).
 *
 * Thay hai khối `@php`:
 *  1. closure `$stageFor` 32 dòng suy tiến độ hồ sơ từ trạng thái tạm ứng + trạng thái hoàn ứng,
 *     có cả `Carbon::parse()` + `now()` để đếm ngày quá hạn — tức tính nghiệp vụ trong Blade;
 *  2. khối nằm TRONG `@forelse`, dựng kết quả quyết toán cho TỪNG dòng.
 * Đồng thời gom 5 điều kiện hiển thị nút thao tác, mỗi cái đều tự gọi `auth()->id()` trong view.
 *
 * Lớp này thuần: không Facade, không query, không `auth()`/`request()`. Thời điểm "hôm nay" và id
 * người đang đăng nhập vào bằng tham số.
 */
final class AdvanceRequestListPresenter
{
    /**
     * Tông huy hiệu tiến độ → chuỗi lớp, quy đổi từ `.ego-fin-stage--*` của khối `<style>` cũ.
     *
     * @var array<string, string>
     */
    private const TONE_CLASSES = [
        'muted' => 'tw:bg-[#f3f6f8] tw:text-[#607681] tw:border-[#e5ecef]',
        'wait' => 'tw:bg-[#fff7e9] tw:text-[#a86600] tw:border-[#f3dfb5]',
        'action' => 'tw:bg-[#eaf9fb] tw:text-[#087d91] tw:border-[#ccebf0]',
        'danger' => 'tw:bg-[#fff0f0] tw:text-[#b23a3a] tw:border-[#f3cdcd]',
        'success' => 'tw:bg-[#edf9f4] tw:text-[#087458] tw:border-[#cfeadf]',
        'blue' => 'tw:bg-[#eef4ff] tw:text-[#3866a4] tw:border-[#d8e4fb]',
    ];

    /** Trạng thái tạm ứng mà chủ phiếu được gửi (lại) duyệt. */
    private const TRANG_THAI_GUI_LAI = ['draft', 'admin_rejected', 'accounting_rejected'];

    /** Trạng thái chờ QL tài chính duyệt — `pending` là mã cũ còn trong dữ liệu. */
    private const TRANG_THAI_CHO_QLTC = ['submitted', 'pending'];

    /**
     * @param  iterable<object>  $items  trang hiện tại của paginator, đã eager load
     *                                   `creator` và `settlementRequest`
     * @param  array<string, string>  $labels  bảng nhãn trạng thái tạm ứng của controller
     * @param  array<string, mixed>  $stats  chỉ số tổng hợp của controller
     * @param  array<string, mixed>  $rawFilters  giá trị lọc thô lấy từ query string
     * @param  array<string, mixed>  $oldInput  old input của session (rỗng khi không có)
     * @return array{advanceRows: list<AdvanceRequestRow>, kpi: AdvanceKpiCards,
     *               filters: array<string, string>, createForm: array<string, string>}
     */
    public function viewData(
        iterable $items,
        array $labels,
        ?int $currentUserId,
        bool $canApproveManagement,
        bool $canApproveAccounting,
        Carbon $today,
        array $stats,
        int $pageCount,
        int $totalCount,
        array $rawFilters,
        array $oldInput,
        string $currentUserName,
    ): array {
        $rows = [];

        foreach ($items as $item) {
            $settle = $item->settlementRequest ?? null;
            $status = (string) ($item->status ?? '');
            $laChuPhieu = $currentUserId !== null && (int) ($item->created_by ?? 0) === $currentUserId;
            $settleStatus = $settle ? (string) ($settle->status ?? '') : '';

            $rows[] = new AdvanceRequestRow(
                id: (int) ($item->id ?? 0),
                code: (string) ($item->code ?? ''),
                // Bản cũ: `optional($item->created_at)->format('d/m/Y H:i')` — ngày trống ra chuỗi
                // RỖNG chứ không phải gạch ngang, nên KHÔNG dùng được `DisplayFormat::date()`.
                createdAtText: $item->created_at ? Carbon::parse($item->created_at)->format('d/m/Y H:i') : '',
                recipientName: (string) ($item->recipient_name ?? ''),
                company: (string) ($item->company ?? ''),
                reasonText: Str::limit((string) ($item->reason ?? ''), 82),
                noteText: ($item->note ?? '') !== '' ? Str::limit((string) $item->note, 60) : '',
                // Tiền ở trang này in `12.500.000 đ` — dấu cách trước `đ`, đúng `DisplayFormat::money()`.
                amountText: DisplayFormat::money($item->amount ?? 0),
                dueDateText: $item->settlement_due_date
                    ? Carbon::parse($item->settlement_due_date)->format('d/m/Y')
                    : '—',
                stage: $this->stage($item, $settle, $status, $settleStatus, $labels, $today),
                outcomeLabel: $this->outcomeLabel($item, $settle, $status),
                outcomeSub: $this->outcomeSub($item, $settle, $status),
                creatorName: (string) (($item->creator->name ?? null) ?? '—'),
                settlementId: $settle ? (int) $settle->id : null,
                canCreateSettlement: $laChuPhieu && $status === 'accounting_approved' && ! $settle,
                canSubmit: $laChuPhieu && in_array($status, self::TRANG_THAI_GUI_LAI, true),
                canManagementApprove: $canApproveManagement && in_array($status, self::TRANG_THAI_CHO_QLTC, true),
                canAccountingApprove: $canApproveAccounting && $status === 'admin_approved',
                canApproveSettlement: $settle !== null && $canApproveManagement
                    && in_array($settleStatus, self::TRANG_THAI_CHO_QLTC, true),
                canReconcileSettlement: $settle !== null && $canApproveAccounting
                    && $settleStatus === 'admin_approved',
            );
        }

        $quaHan = (int) ($stats['overdue_settlement'] ?? 0);

        return [
            'advanceRows' => $rows,
            'kpi' => new AdvanceKpiCards(
                total: DisplayFormat::number($stats['total'] ?? 0),
                pending: DisplayFormat::number($stats['pending'] ?? 0),
                needSettlement: DisplayFormat::number($stats['need_settlement'] ?? 0),
                done: DisplayFormat::number($stats['done'] ?? 0),
                hasOverdue: $quaHan > 0,
                // Bản cũ nhánh CÓ quá hạn không có dấu cách quanh chuỗi, nhánh không có thì CÓ —
                // giữ y nguyên để không đổi một ký tự nào của HTML.
                settlementHint: $quaHan > 0
                    ? DisplayFormat::number($quaHan).' phiếu đã quá hạn'
                    : ' đã chi tiền, chờ nhân sự quyết toán ',
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
                // `old('recipient_name', auth()->user()->name ?? '')` của bản cũ.
                'recipient_name' => $this->old($oldInput, 'recipient_name', $currentUserName),
                'amount' => $this->old($oldInput, 'amount'),
                'needed_date' => $this->old($oldInput, 'needed_date'),
                'settlement_due_date' => $this->old($oldInput, 'settlement_due_date'),
                'reason' => $this->old($oldInput, 'reason'),
                'bank_name' => $this->old($oldInput, 'bank_name'),
                'bank_account' => $this->old($oldInput, 'bank_account'),
                'bank_account_name' => $this->old($oldInput, 'bank_account_name'),
                'note' => $this->old($oldInput, 'note'),
            ],
        ];
    }

    /**
     * Tương đương `old($field, $default)`; dùng `array_key_exists` vì `old()` đi qua `Arr::get`,
     * tức khoá tồn tại với giá trị null vẫn được trả về chứ không rơi về mặc định.
     *
     * @param  array<string, mixed>  $oldInput
     */
    private function old(array $oldInput, string $field, string $default = ''): string
    {
        return array_key_exists($field, $oldInput)
            ? (string) ($oldInput[$field] ?? '')
            : $default;
    }

    /**
     * Tiến độ hồ sơ — chép đúng chuỗi `if/elseif` của closure cũ, kể cả thứ tự xét.
     *
     * @param  array<string, string>  $labels
     */
    private function stage(
        object $item,
        ?object $settle,
        string $status,
        string $settleStatus,
        array $labels,
        Carbon $today,
    ): AdvanceStage {
        if ($status === 'draft') {
            return new AdvanceStage('Nháp', self::TONE_CLASSES['muted'], 'bi-pencil', 'Chưa gửi phê duyệt');
        }
        if ($status === 'submitted') {
            return new AdvanceStage('Chờ QL tài chính duyệt', self::TONE_CLASSES['wait'], 'bi-hourglass-split', 'Giai đoạn tạm ứng');
        }
        if ($status === 'admin_approved') {
            return new AdvanceStage('Chờ kế toán chi', self::TONE_CLASSES['wait'], 'bi-cash-stack', 'QL tài chính đã duyệt');
        }
        if ($status === 'admin_rejected') {
            return new AdvanceStage('QL tài chính từ chối', self::TONE_CLASSES['danger'], 'bi-x-circle', 'Cần chỉnh sửa / gửi lại');
        }
        if ($status === 'accounting_rejected') {
            return new AdvanceStage('Kế toán từ chối chi', self::TONE_CLASSES['danger'], 'bi-x-circle', 'Cần chỉnh sửa / gửi lại');
        }

        if ($status !== 'accounting_approved') {
            // Trạng thái lạ: nhãn tra bảng, không có thì in nguyên mã — đúng giá trị khởi tạo cũ.
            return new AdvanceStage($labels[$status] ?? $status, self::TONE_CLASSES['muted'], 'bi-circle', '');
        }

        if (! $settle) {
            $days = 0;
            if ($item->settlement_due_date) {
                $due = Carbon::parse($item->settlement_due_date)->startOfDay();
                if ($due->lt($today)) {
                    $days = (int) $due->diffInDays($today);
                }
            }

            return $days > 0
                ? new AdvanceStage('Quá hạn hoàn ứng '.$days.' ngày', self::TONE_CLASSES['danger'], 'bi-exclamation-triangle', 'Đã chi • Cần hoàn ứng ngay', true)
                : new AdvanceStage('Cần hoàn ứng', self::TONE_CLASSES['action'], 'bi-receipt-cutoff', 'Kế toán đã chi tạm ứng');
        }

        return match ($settleStatus) {
            'draft' => new AdvanceStage('Hoàn ứng nháp', self::TONE_CLASSES['blue'], 'bi-pencil-square', 'Nhân sự chưa gửi duyệt'),
            'submitted' => new AdvanceStage('Chờ QL tài chính duyệt hoàn ứng', self::TONE_CLASSES['wait'], 'bi-person-check', 'Đã gửi hồ sơ quyết toán'),
            'admin_rejected' => new AdvanceStage('QL tài chính từ chối hoàn ứng', self::TONE_CLASSES['danger'], 'bi-x-circle', 'Cần bổ sung và gửi lại'),
            'admin_approved' => new AdvanceStage('Chờ kế toán đối soát', self::TONE_CLASSES['wait'], 'bi-calculator', 'QL tài chính đã duyệt hoàn ứng'),
            'accounting_rejected' => new AdvanceStage('Kế toán từ chối đối soát', self::TONE_CLASSES['danger'], 'bi-x-circle', 'Cần bổ sung và gửi lại'),
            'accounting_approved' => new AdvanceStage('Đã quyết toán', self::TONE_CLASSES['success'], 'bi-check2-circle', 'Hồ sơ đã khép kín'),
            // Hồ sơ hoàn ứng ở trạng thái lạ: bản cũ KHÔNG gán lại `$stage` nên giữ giá trị khởi tạo
            // — nhãn tra theo trạng thái TẠM ỨNG (`accounting_approved`), không phải trạng thái hoàn ứng.
            default => new AdvanceStage($labels[$status] ?? $status, self::TONE_CLASSES['muted'], 'bi-circle', ''),
        };
    }

    private function outcomeLabel(object $item, ?object $settle, string $status): string
    {
        if ($settle) {
            return match ((string) ($settle->settlement_type ?? '')) {
                'refund' => 'Hoàn lại '.DisplayFormat::number($settle->refund_amount ?? 0).' đ',
                'pay_more' => 'Công ty trả thêm '.DisplayFormat::number(abs((float) ($settle->difference_amount ?? 0))).' đ',
                default => 'Khớp đủ',
            };
        }

        return $status === 'accounting_approved' ? 'Chưa lập hoàn ứng' : '—';
    }

    private function outcomeSub(object $item, ?object $settle, string $status): string
    {
        if ($settle) {
            return 'Đã chi '.DisplayFormat::number($settle->actual_amount ?? 0)
                .' / '.DisplayFormat::number($item->amount ?? 0).' đ';
        }

        return $status === 'accounting_approved'
            ? 'Tạm ứng '.DisplayFormat::number($item->amount ?? 0).' đ'
            : 'Chưa phát sinh hoàn ứng';
    }
}
