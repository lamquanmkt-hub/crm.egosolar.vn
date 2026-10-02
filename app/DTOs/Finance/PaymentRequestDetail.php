<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Toàn bộ giá trị hiển thị của trang `payment_requests/show`.
 *
 * Thay khối `@php` 55 dòng ở đầu view: nó tự gọi `auth()->user()`, tự suy vai bằng `hasRole` /
 * `hasAnyRole` / cột `role` / cờ `is_admin`, dựng 2 bản đồ nhãn, định dạng 4 mốc thời gian và
 * tra tên hai người duyệt.
 *
 * 🚨 Bản đồ `$statusToneMap` (8 dòng) và biến `$statusTone` của bản cũ là CODE CHẾT — `$statusTone`
 * chỉ xuất hiện đúng ở chỗ gán, không nơi nào đọc. Đã bỏ, không tái hiện.
 */
final readonly class PaymentRequestDetail
{
    /**
     * @param  string  $statusSlug  mã trạng thái đã đổi `_` thành `-` cho lớp `ego-pr-status--*`
     * @param  string  $amountText  `12.500.000 đ` — bản cũ ép `(int)` TRƯỚC khi định dạng
     * @param  list<PaymentAttachmentCard>  $attachments
     * @param  string  $actionTitle  tiêu đề khối "Xử lý phiếu", đổi theo vai đang xem
     * @param  string  $approveUrl  action của form duyệt — khác nhau giữa QLTC và kế toán
     * @param  string  $step2State  `done` | `active` | `` — lớp phụ của bước "Quản lý tài chính"
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $statusLabel,
        public string $statusSlug,
        public string $createdAtText,
        public string $paymentDueDateText,
        public string $adminApprovedAtText,
        public string $accApprovedAtText,
        public string $adminApproverName,
        public string $accApproverName,
        public string $creatorDisplay,
        public string $companyHeader,
        public string $companyField,
        public string $receiverName,
        public string $department,
        public string $amountText,
        public string $docTypeLabel,
        public string $bankName,
        public string $bankAccount,
        public string $bankAccountName,
        public string $paymentContent,
        public string $reason,
        public string $adminNote,
        public string $accountingNote,
        public array $attachments,
        public bool $canEdit,
        public bool $canSubmit,
        public bool $canDelete,
        public bool $canAdminAction,
        public bool $canAccAction,
        public bool $hasAnyAction,
        public bool $showPdf,
        public string $actionTitle,
        public string $approveUrl,
        public string $rejectUrl,
        public string $approveConfirm,
        public string $approveButtonLabel,
        public string $step2State,
        public string $step2Text,
        public string $step3State,
        public string $step3Text,
    ) {}
}
