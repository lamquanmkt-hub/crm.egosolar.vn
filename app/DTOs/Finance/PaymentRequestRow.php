<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một phiếu đề nghị thanh toán trên trang danh sách (bảng máy tính và thẻ di động dùng chung):
 * bản ghi + trạng thái/nhãn + các quyền thao tác theo người xem + hạn thanh toán/nội dung đã định dạng.
 */
final readonly class PaymentRequestRow
{
    public function __construct(
        public object $request,
        public string $status,
        public string $statusSlug,
        public string $statusLabel,
        public bool $canEdit,
        public bool $canSubmit,
        public bool $canDelete,
        public bool $canAdminAction,
        public bool $canAccountingAction,
        public bool $bulkSelectable,
        public bool $canDownloadPdf,
        public string $dueText,
        public bool $isOverdue,
        public string $contentText,
    ) {}
}
