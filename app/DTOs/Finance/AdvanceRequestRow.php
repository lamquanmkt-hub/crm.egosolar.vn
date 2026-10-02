<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một dòng bảng của trang `advance_requests/index`.
 *
 * Gộp cả hai khối `@php` cũ: closure `$stageFor` (tiến độ hồ sơ) và khối nằm TRONG `@forelse`
 * (kết quả quyết toán + 5 điều kiện hiển thị nút thao tác, mỗi điều kiện đều tự hỏi
 * `auth()->id()` ngay trong Blade).
 */
final readonly class AdvanceRequestRow
{
    /**
     * @param  string  $reasonText  đã cắt 82 ký tự bằng `Str::limit` như bản cũ
     * @param  string  $noteText  đã cắt 60 ký tự; chuỗi RỖNG khi phiếu không có ghi chú
     * @param  string  $outcomeSub  dòng nhỏ dưới ô quyết toán
     * @param  int|null  $settlementId  id hồ sơ hoàn ứng, null khi chưa phát sinh
     */
    public function __construct(
        public int $id,
        public string $code,
        public string $createdAtText,
        public string $recipientName,
        public string $company,
        public string $reasonText,
        public string $noteText,
        public string $amountText,
        public string $dueDateText,
        public AdvanceStage $stage,
        public string $outcomeLabel,
        public string $outcomeSub,
        public string $creatorName,
        public ?int $settlementId,
        public bool $canCreateSettlement,
        public bool $canSubmit,
        public bool $canManagementApprove,
        public bool $canAccountingApprove,
        public bool $canApproveSettlement,
        public bool $canReconcileSettlement,
    ) {}
}
