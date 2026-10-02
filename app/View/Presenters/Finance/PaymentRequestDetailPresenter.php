<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\PaymentAttachmentCard;
use App\DTOs\Finance\PaymentRequestDetail;
use App\Support\DisplayFormat;
use Illuminate\Support\Carbon;

/**
 * Chuẩn bị trang `payment_requests/show` (chi tiết đề nghị thanh toán).
 *
 * Thay hai khối `@php`: khối 55 dòng ở đầu view (tự gọi `auth()->user()`, tự suy vai, dựng 2 bản
 * đồ nhãn, định dạng 4 mốc thời gian, tra tên hai người duyệt) và khối trong `@foreach` chứng từ.
 *
 * Lớp này thuần: không Facade, không query, không `auth()`/`request()`/`url()`. Vai và URL tải/xem
 * trước vào bằng tham số.
 */
final class PaymentRequestDetailPresenter
{
    /** Trạng thái mà chủ phiếu còn sửa / gửi lại / xoá được. */
    private const TRANG_THAI_SUA_DUOC = ['draft', 'admin_rejected', 'accounting_rejected'];

    /** Trạng thái cho thấy QL tài chính đã xử lý xong bước của mình. */
    private const QLTC_DA_XU_LY = ['admin_approved', 'admin_rejected', 'accounting_approved', 'accounting_rejected'];

    /** Trạng thái cho thấy kế toán đã xử lý xong. */
    private const KE_TOAN_DA_XU_LY = ['accounting_approved', 'accounting_rejected'];

    /** @var array<string, string> */
    private const STATUS_LABELS = [
        'draft' => 'Nháp',
        'submitted' => 'Đã gửi duyệt',
        'admin_approved' => 'Quản lý tài chính đã duyệt',
        'admin_rejected' => 'Quản lý tài chính từ chối',
        'accounting_approved' => 'Kế toán đã chi',
        'accounting_rejected' => 'Kế toán từ chối',
    ];

    /** Phần mở rộng được coi là ảnh — nhãn ô vuông in `ẢNH`. */
    private const DUOI_ANH = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    /**
     * @param  iterable<object>  $attachments  đã eager load, giữ nguyên thứ tự của quan hệ
     * @param  array{approveAdmin: string, rejectAdmin: string, approveAcc: string, rejectAcc: string}  $actionUrls
     * @param  callable(int): array{download: string, preview: string}  $fileUrls  dựng URL cho một tệp
     * @return array{detail: PaymentRequestDetail}
     */
    public function viewData(
        object $item,
        iterable $attachments,
        ?int $currentUserId,
        bool $isAdmin,
        bool $isAccounting,
        ?bool $canEditByPolicy,
        array $actionUrls,
        callable $fileUrls,
    ): array {
        $status = (string) ($item->status ?? '');
        $laChuPhieu = $currentUserId !== null && (int) ($item->created_by ?? 0) === $currentUserId;

        $suaDuoc = $laChuPhieu && in_array($status, self::TRANG_THAI_SUA_DUOC, true);
        // Bản cũ: `isset($canEditByPolicy) ? (bool) $canEditByPolicy : $canEditBase` — policy chỉ đè
        // quyền SỬA, ba cờ còn lại vẫn theo `$canEditBase`.
        $canEdit = $canEditByPolicy ?? $suaDuoc;
        $canAdminAction = $isAdmin && $status === 'submitted';
        $canAccAction = $isAccounting && $status === 'admin_approved';

        $detail = new PaymentRequestDetail(
            id: (int) ($item->id ?? 0),
            code: (string) ($item->code ?? ''),
            statusLabel: self::STATUS_LABELS[$status] ?? ($status !== '' ? $status : '-'),
            statusSlug: str_replace('_', '-', $status !== '' ? $status : 'draft'),
            createdAtText: $this->thoiDiem($item->created_at ?? null, 'd/m/Y H:i'),
            paymentDueDateText: $this->thoiDiem($item->payment_due_date ?? null, 'd/m/Y'),
            adminApprovedAtText: $this->thoiDiem($item->admin_approved_at ?? null, 'd/m/Y H:i'),
            accApprovedAtText: $this->thoiDiem($item->accounting_approved_at ?? null, 'd/m/Y H:i'),
            adminApproverName: $this->tenNguoiDuyet($item->director->name ?? null, $item->admin_approved_by ?? null),
            accApproverName: $this->tenNguoiDuyet($item->accountant->name ?? null, $item->accounting_approved_by ?? null),
            // Bản cũ: `$item->creator->name ?? ('#'.$item->created_by)` — `??` chỉ bắt NULL.
            creatorDisplay: ($item->creator->name ?? null) !== null
                ? (string) $item->creator->name
                : '#'.((string) ($item->created_by ?? '')),
            companyHeader: ((string) ($item->company ?? '')) ?: 'Chưa có công ty',
            companyField: ((string) ($item->company ?? '')) ?: '-',
            receiverName: ((string) ($item->receiver_name ?? '')) ?: '-',
            department: ((string) ($item->department ?? '')) ?: '-',
            // ⚠️ Bản cũ ép `(int)` TRƯỚC khi định dạng — số thập phân bị cắt, giữ y.
            amountText: DisplayFormat::money((int) ($item->amount ?? 0)),
            docTypeLabel: match ((string) ($item->doc_type ?? '')) {
                'payment_voucher' => 'Phiếu chi',
                'refund_request' => 'Đề nghị hoàn tiền',
                'advance' => 'Tạm ứng',
                default => 'Phiếu đề nghị thanh toán',
            },
            bankName: ((string) ($item->bank_name ?? '')) ?: '-',
            bankAccount: ((string) ($item->bank_account ?? '')) ?: '-',
            bankAccountName: ((string) ($item->bank_account_name ?? '')) ?: '-',
            paymentContent: ((string) ($item->payment_content ?? '')) ?: '-',
            reason: ((string) ($item->reason ?? '')) ?: '-',
            adminNote: ((string) ($item->admin_note ?? '')) ?: '-',
            accountingNote: ((string) ($item->accounting_note ?? '')) ?: '-',
            attachments: $this->attachments($attachments, $fileUrls),
            canEdit: $canEdit,
            canSubmit: $suaDuoc,
            canDelete: $suaDuoc,
            canAdminAction: $canAdminAction,
            canAccAction: $canAccAction,
            hasAnyAction: $canEdit || $suaDuoc || $canAdminAction || $canAccAction,
            showPdf: $status === 'accounting_approved',
            actionTitle: $canAdminAction
                ? 'Quản lý tài chính xử lý'
                : ($canAccAction ? 'Kế toán xử lý' : 'Thao tác phiếu'),
            approveUrl: $canAdminAction ? $actionUrls['approveAdmin'] : $actionUrls['approveAcc'],
            rejectUrl: $canAdminAction ? $actionUrls['rejectAdmin'] : $actionUrls['rejectAcc'],
            approveConfirm: $canAdminAction ? 'Duyệt phiếu này?' : 'Xác nhận đã chi phiếu này?',
            approveButtonLabel: $canAdminAction ? 'Duyệt phiếu' : 'Xác nhận đã chi',
            step2State: in_array($status, self::QLTC_DA_XU_LY, true) ? 'done' : ($status === 'submitted' ? 'active' : ''),
            step2Text: in_array($status, self::QLTC_DA_XU_LY, true)
                ? $this->tenNguoiDuyet($item->director->name ?? null, $item->admin_approved_by ?? null)
                : ($status === 'submitted' ? 'Đang chờ xử lý' : 'Chưa đến bước duyệt'),
            step3State: in_array($status, self::KE_TOAN_DA_XU_LY, true) ? 'done' : ($status === 'admin_approved' ? 'active' : ''),
            step3Text: in_array($status, self::KE_TOAN_DA_XU_LY, true)
                ? $this->tenNguoiDuyet($item->accountant->name ?? null, $item->accounting_approved_by ?? null)
                : ($status === 'admin_approved' ? 'Đang chờ kế toán' : 'Chưa đến bước kế toán'),
        );

        return ['detail' => $detail];
    }

    /** Gạch NGẮN `-` khi trống — KHÔNG phải gạch dài của `DisplayFormat::date()`. */
    private function thoiDiem(mixed $value, string $format): string
    {
        return ! empty($value) ? Carbon::parse($value)->format($format) : '-';
    }

    /**
     * Tên người duyệt.
     *
     * 🚨 Bản cũ LUÔN rơi vào nhánh `#<id>` vì view gọi sai tên quan hệ (`adminApprover` thay vì
     * `director`) — Eloquent trả null cho thuộc tính lạ. Nay gọi đúng tên nên tên người duyệt hiện
     * ra; nhánh `#<id>` giữ lại làm dự phòng, nhưng khoá ngoại tới `users` là RESTRICT nên trên
     * production id luôn tồn tại.
     */
    private function tenNguoiDuyet(?string $ten, mixed $id): string
    {
        if ($ten !== null) {
            return $ten;
        }

        return ! empty($id) ? '#'.$id : '-';
    }

    /**
     * @param  iterable<object>  $attachments
     * @param  callable(int): array{download: string, preview: string}  $fileUrls
     * @return list<PaymentAttachmentCard>
     */
    private function attachments(iterable $attachments, callable $fileUrls): array
    {
        $cards = [];
        foreach ($attachments as $att) {
            $id = (int) ($att->id ?? 0);
            $name = (string) ($att->original_name ?? basename((string) ($att->path ?? '')));
            $duoi = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $url = $fileUrls($id);

            $cards[] = new PaymentAttachmentCard(
                id: $id,
                name: $name,
                mimeText: (string) ($att->mime_type ?? 'Tệp đính kèm'),
                // Bản cũ: `number_format($size / 1024, 1).' KB'` — dấu chấm thập phân kiểu Anh.
                sizeText: ! empty($att->size) ? number_format(((float) $att->size) / 1024, 1).' KB' : '',
                // Bản cũ: `in_array($ext, [...]) ? 'ẢNH' : strtolower($ext ?: 'FILE')` rồi `strtoupper()`.
                badge: strtoupper(in_array($duoi, self::DUOI_ANH, true) ? 'ẢNH' : ($duoi !== '' ? $duoi : 'FILE')),
                downloadUrl: $url['download'],
                previewUrl: $url['preview'],
            );
        }

        return $cards;
    }
}
