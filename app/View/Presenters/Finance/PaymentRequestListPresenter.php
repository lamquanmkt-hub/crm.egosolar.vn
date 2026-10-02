<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\PaymentRequestRow;
use Illuminate\Support\Carbon;

/**
 * Chuẩn bị giá trị cho view `payment_requests.index` (danh sách đề nghị thanh toán). Trước 2026-09-08
 * view tự tính trong 4 khối `@php`: tham số xuất Excel/PDF; hai khối giống nhau cho bảng máy tính và
 * thẻ di động (quyền sửa/gửi/duyệt/chi/xoá/tải PDF theo trạng thái + chủ phiếu + vai người xem, hạn
 * thanh toán, quá hạn, nội dung bỏ thẻ HTML); và bản đồ mã phiếu → hạn cho JavaScript.
 * Tên khoá trả về giữ tên biến cũ của view; kiểm bằng so HTML 14 trang (4 vai × bộ lọc).
 */
final class PaymentRequestListPresenter
{
    /** Chủ phiếu được sửa/gửi/xoá khi phiếu còn nháp hoặc đã bị trả. */
    private const OWNER_EDITABLE_STATUSES = ['draft', 'admin_rejected', 'accounting_rejected'];

    /** Phiếu đã chốt (chi hoặc từ chối chi) không còn tính quá hạn. */
    private const CLOSED_STATUSES = ['accounting_approved', 'accounting_rejected'];

    /** Tham số phân trang không đưa vào liên kết xuất. */
    private const NON_EXPORT_PARAMS = ['per_page', 'page'];

    private const DATE_FORMAT = 'd/m/Y';

    private const EMPTY = '-';

    /**
     * @param  mixed  $items  paginator của controller
     * @param  array<string, string>  $statusLabels
     * @param  int|null  $viewerId  id người đang xem — chủ phiếu so với created_by
     * @param  array<string, mixed>  $paginationQuery  tham số bộ lọc đang giữ
     * @return array<string, mixed>
     */
    public function viewData(mixed $items, array $statusLabels, ?int $viewerId, bool $canAdminApprove, bool $canAccountingApprove, bool $canHrEditSubmitted, array $paginationQuery): array
    {
        $rows = [];
        if (is_object($items) && method_exists($items, 'items')) {
            foreach ($items->items() as $request) {
                $rows[] = $this->row($request, $statusLabels, $viewerId, $canAdminApprove, $canAccountingApprove, $canHrEditSubmitted);
            }
        }

        return [
            'rows' => $rows,
            'exportParams' => collect($paginationQuery)->except(self::NON_EXPORT_PARAMS)->all(),
            'egoPaymentDueMap' => collect($rows)->mapWithKeys(fn (PaymentRequestRow $row) => [$row->request->code => $row->dueText])->all(),
        ];
    }

    /** @param  array<string, string>  $statusLabels */
    private function row(object $request, array $statusLabels, ?int $viewerId, bool $canAdminApprove, bool $canAccountingApprove, bool $canHrEditSubmitted): PaymentRequestRow
    {
        $status = $request->status ?? 'draft';
        $isOwner = (int) $request->created_by === (int) $viewerId;
        $ownerCanEdit = $isOwner && in_array($status, self::OWNER_EDITABLE_STATUSES, true);
        $canEditSubmitted = $status === 'submitted' && ($canAccountingApprove || ($canHrEditSubmitted && $isOwner));
        $canAdminAction = $status === 'submitted';
        $canAccountingAction = $status === 'admin_approved';

        $dueText = self::EMPTY;
        $isOverdue = false;
        if (! empty($request->payment_due_date)) {
            try {
                $dueDate = Carbon::parse($request->payment_due_date);
                $dueText = $dueDate->format(self::DATE_FORMAT);
                $isOverdue = $dueDate->isPast() && ! in_array($status, self::CLOSED_STATUSES, true);
            } catch (\Throwable) {
                $dueText = (string) $request->payment_due_date;
            }
        }

        return new PaymentRequestRow(
            request: $request,
            status: (string) $status,
            statusSlug: str_replace('_', '-', (string) $status),
            statusLabel: (string) ($statusLabels[$status] ?? $status),
            canEdit: $ownerCanEdit || $canEditSubmitted,
            canSubmit: $ownerCanEdit,
            canDelete: $ownerCanEdit,
            canAdminAction: $canAdminAction,
            canAccountingAction: $canAccountingAction,
            bulkSelectable: ($canAdminApprove && $canAdminAction) || ($canAccountingApprove && $canAccountingAction),
            canDownloadPdf: $status === 'accounting_approved',
            dueText: $dueText,
            isOverdue: $isOverdue,
            contentText: trim(strip_tags((string) ($request->payment_content ?: $request->reason ?: self::EMPTY))),
        );
    }
}
