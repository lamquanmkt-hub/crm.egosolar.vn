<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một dòng bảng của trang `settlement_requests/index` (đề nghị hoàn ứng).
 *
 * Gom các giá trị mà view cũ tự tính ngay trong Blade: 4 lượt `number_format`, `optional(...)
 * ->format()`, nhánh ba chiều của kết quả đối soát, và 4 điều kiện hiện nút mà mỗi cái đều tự
 * hỏi `auth()->id()`.
 */
final readonly class SettlementRequestRow
{
    /**
     * @param  string  $advanceCode  mã phiếu tạm ứng gốc; chuỗi RỖNG khi phiếu không gắn tạm ứng nào
     * @param  string  $outcomeText  kết quả đối soát đã gộp nhãn + số tiền
     * @param  string  $outcomeClass  CHUỖI LỚP màu chữ của kết quả (phải tĩnh thì Tailwind mới quét thấy)
     * @param  list<array{index: int, path: string}>  $attachments  chứng từ; `index` là số thứ tự
     *                                                              hiển thị (`CT 1`, `CT 2`…), `path` là
     *                                                              đường dẫn trong disk `public`
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $createdAtText,
        public int $advanceRequestId,
        public string $advanceCode,
        public string $recipientName,
        public string $company,
        public string $reason,
        public string $note,
        public string $advanceAmountText,
        public string $actualAmountText,
        public string $outcomeText,
        public string $outcomeClass,
        public array $attachments,
        public string $statusLabel,
        public bool $canSubmit,
        public bool $canManagementApprove,
        public bool $canAccountingApprove,
        public bool $canDelete,
        public string $deleteConfirmText,
    ) {}
}
