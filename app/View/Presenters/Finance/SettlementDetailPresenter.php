<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\SettlementAttachmentRow;
use App\DTOs\Finance\SettlementDetail;
use App\Support\DisplayFormat;
use Illuminate\Support\Carbon;

/**
 * Chuẩn bị trang `settlement_requests/show` (chi tiết đề nghị hoàn ứng).
 *
 * Thay hai khối `@php`: khối 29 dòng ở đầu view (tự gọi `auth()->user()`, suy 4 cờ quyền, định
 * dạng 4 mốc thời gian, tra tên hai người duyệt, tính kết quả đối soát) và khối nằm TRONG
 * `@foreach` chứng từ (gọi `basename()` + `pathinfo()` hai lần + `asset()` cho từng tệp).
 *
 * Lớp này thuần: không Facade, không query, không `auth()`/`request()`.
 */
final class SettlementDetailPresenter
{
    /** Trạng thái mà chủ phiếu được gửi (lại) duyệt. */
    private const TRANG_THAI_GUI_LAI = ['draft', 'admin_rejected', 'accounting_rejected'];

    /** Trạng thái cho thấy QL tài chính đã xử lý xong bước của mình. */
    private const QLTC_DA_XU_LY = ['admin_approved', 'admin_rejected', 'accounting_approved', 'accounting_rejected'];

    /** Trạng thái cho thấy kế toán đã xử lý xong. */
    private const KE_TOAN_DA_XU_LY = ['accounting_approved', 'accounting_rejected'];

    /** Phần mở rộng được coi là ảnh — nhãn ô vuông in `ẢNH` thay cho phần mở rộng. */
    private const DUOI_ANH = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * @param  array<string, string>  $labels
     * @param  array<int|string, string>  $approvers  id người duyệt → tên
     * @return array{detail: SettlementDetail}
     */
    public function viewData(
        object $item,
        array $labels,
        array $approvers,
        ?int $currentUserId,
        bool $canViewAll,
        bool $canApproveManagement,
        bool $canApproveAccounting,
    ): array {
        $status = (string) ($item->status ?? '');
        $laChuPhieu = $currentUserId !== null && (int) ($item->created_by ?? 0) === $currentUserId;

        $canSubmit = $laChuPhieu && in_array($status, self::TRANG_THAI_GUI_LAI, true);
        $canDelete = $canViewAll || ($laChuPhieu && $status === 'draft');
        $canAdminAction = $canApproveManagement && $status === 'submitted';
        $canAccAction = $canApproveAccounting && $status === 'admin_approved';

        $loai = (string) ($item->settlement_type ?? 'balanced');
        $payMore = max(0, (float) ($item->difference_amount ?? 0));
        $resultAmount = $loai === 'pay_more' ? $payMore : (float) ($item->refund_amount ?? 0);

        $creator = (string) ($item->creator->name ?? '');
        $recipient = (string) ($item->recipient_name ?? '');
        // Bản cũ: `$item->creator->name ?? $item->recipient_name ?? ('#'.$item->created_by)` — `??`
        // chỉ bắt NULL nên tên rỗng vẫn được dùng; giữ đúng thứ tự đó bằng `??` trên giá trị thô.
        $creatorDisplay = ($item->creator->name ?? $item->recipient_name ?? null) !== null
            ? (string) ($item->creator->name ?? $item->recipient_name)
            : '#'.((string) ($item->created_by ?? ''));

        $detail = new SettlementDetail(
            id: (int) ($item->id ?? 0),
            code: (string) ($item->code ?? ''),
            statusLabel: $labels[$status] ?? ($status ?: '-'),
            statusSlug: str_replace('_', '-', $status ?: 'draft'),
            createdAtText: $this->thoiDiem($item->created_at ?? null),
            submittedAtText: $this->thoiDiem($item->submitted_at ?? null),
            adminApprovedAtText: $this->thoiDiem($item->admin_approved_at ?? null),
            accountingApprovedAtText: $this->thoiDiem($item->accounting_approved_at ?? null),
            adminApproverName: $this->tenNguoiDuyet($approvers, $item->admin_approved_by ?? null),
            accountingApproverName: $this->tenNguoiDuyet($approvers, $item->accounting_approved_by ?? null),
            creatorDisplay: $creatorDisplay,
            companyHeader: ((string) ($item->company ?? '')) ?: 'Chưa có công ty',
            companyField: ((string) ($item->company ?? '')) ?: '-',
            recipientText: $recipient ?: ($creator ?: '-'),
            advanceRequestId: (int) ($item->advanceRequest->id ?? 0),
            advanceCode: (string) ($item->advanceRequest->code ?? ''),
            advanceAmountText: DisplayFormat::money($item->advance_amount ?? 0),
            actualAmountText: DisplayFormat::money($item->actual_amount ?? 0),
            settlementTypeText: match ($loai) {
                'refund' => 'Nhân sự hoàn lại công ty',
                'pay_more' => 'Công ty thanh toán thêm',
                default => 'Khớp đủ',
            },
            settlementType: $loai,
            resultLabel: match ($loai) {
                'pay_more' => 'Công ty thanh toán thêm',
                'refund' => 'Số tiền hoàn lại',
                default => 'Chênh lệch',
            },
            resultAmountText: DisplayFormat::money($resultAmount),
            hasResultAmount: $resultAmount > 0,
            refundAmountText: DisplayFormat::money($item->refund_amount ?? 0),
            payMoreAmountText: DisplayFormat::money($payMore),
            reasonText: ((string) ($item->reason ?? '')) ?: '-',
            noteText: ((string) ($item->note ?? '')) ?: '-',
            attachments: $this->attachments($item->attachments ?? null),
            attachmentCountText: DisplayFormat::number(count($this->attachments($item->attachments ?? null))),
            canSubmit: $canSubmit,
            canDelete: $canDelete,
            canAdminAction: $canAdminAction,
            canAccAction: $canAccAction,
            hasAnyAction: $canSubmit || $canDelete || $canAdminAction || $canAccAction,
            step2State: in_array($status, self::QLTC_DA_XU_LY, true) ? 'done' : ($status === 'submitted' ? 'active' : ''),
            step2Text: in_array($status, self::QLTC_DA_XU_LY, true)
                ? $this->tenNguoiDuyet($approvers, $item->admin_approved_by ?? null)
                : ($status === 'submitted' ? 'Đang chờ xử lý' : 'Chưa đến bước duyệt'),
            step3State: in_array($status, self::KE_TOAN_DA_XU_LY, true) ? 'done' : ($status === 'admin_approved' ? 'active' : ''),
            step3Text: in_array($status, self::KE_TOAN_DA_XU_LY, true)
                ? $this->tenNguoiDuyet($approvers, $item->accounting_approved_by ?? null)
                : ($status === 'admin_approved' ? 'Đang chờ kế toán' : 'Chưa đến bước kế toán'),
        );

        return ['detail' => $detail];
    }

    /** `d/m/Y H:i`, gạch NGẮN `-` khi trống — KHÔNG phải gạch dài của `DisplayFormat::date()`. */
    private function thoiDiem(mixed $value): string
    {
        return $value ? Carbon::parse($value)->format('d/m/Y H:i') : '-';
    }

    /**
     * Tên người duyệt; `-` khi chưa có ai, `#<id>` khi id không còn trong bảng users.
     *
     * @param  array<int|string, string>  $approvers
     */
    private function tenNguoiDuyet(array $approvers, mixed $id): string
    {
        if (empty($id)) {
            return '-';
        }

        return $approvers[$id] ?? ('#'.$id);
    }

    /** @return list<SettlementAttachmentRow> */
    private function attachments(mixed $attachments): array
    {
        $rows = [];
        // Bản cũ: `array_values(array_filter((array) $attachments))` — bỏ phần tử rỗng rồi đánh lại chỉ số.
        foreach (array_values(array_filter((array) $attachments)) as $path) {
            $path = (string) $path;
            $name = basename($path);
            $duoi = pathinfo($name, PATHINFO_EXTENSION);

            $rows[] = new SettlementAttachmentRow(
                name: $name,
                path: $path,
                extLabel: in_array(strtolower($duoi), self::DUOI_ANH, true)
                    ? 'ẢNH'
                    : (strtoupper($duoi) ?: 'FILE'),
            );
        }

        return $rows;
    }
}
