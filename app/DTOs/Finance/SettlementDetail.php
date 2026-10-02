<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Toàn bộ giá trị hiển thị của trang `settlement_requests/show`.
 *
 * Thay khối `@php` 29 dòng ở đầu view: nó tự gọi `auth()->user()`, suy 4 cờ quyền, định dạng 4 mốc
 * thời gian, tra tên hai người duyệt, và tính kết quả đối soát.
 */
final readonly class SettlementDetail
{
    /**
     * @param  string  $statusSlug  mã trạng thái đã đổi `_` thành `-` cho lớp `ego-pr-status--*`
     * @param  string  $companyHeader  `Chưa có công ty` khi trống — KHÁC `companyField` (`-`);
     *                                 hai chỗ trong bản cũ dùng hai chuỗi mặc định khác nhau
     * @param  string  $resultAmountText  số tiền của ô tổng kết ở đầu trang
     * @param  bool  $hasResultAmount  bản cũ: `@if($resultAmount > 0)` — 0 thì KHÔNG in phần `: <tiền>`
     * @param  list<SettlementAttachmentRow>  $attachments
     * @param  string  $step2State  `done` | `active` | `` — lớp phụ của bước "Quản lý tài chính"
     * @param  string  $step3State  tương tự cho bước "Kế toán"
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $statusLabel,
        public string $statusSlug,
        public string $createdAtText,
        public string $submittedAtText,
        public string $adminApprovedAtText,
        public string $accountingApprovedAtText,
        public string $adminApproverName,
        public string $accountingApproverName,
        public string $creatorDisplay,
        public string $companyHeader,
        public string $companyField,
        public string $recipientText,
        public int $advanceRequestId,
        public string $advanceCode,
        public string $advanceAmountText,
        public string $actualAmountText,
        public string $settlementTypeText,
        public string $settlementType,
        public string $resultLabel,
        public string $resultAmountText,
        public bool $hasResultAmount,
        public string $refundAmountText,
        public string $payMoreAmountText,
        public string $reasonText,
        public string $noteText,
        public array $attachments,
        public string $attachmentCountText,
        public bool $canSubmit,
        public bool $canDelete,
        public bool $canAdminAction,
        public bool $canAccAction,
        public bool $hasAnyAction,
        public string $step2State,
        public string $step2Text,
        public string $step3State,
        public string $step3Text,
    ) {}
}
