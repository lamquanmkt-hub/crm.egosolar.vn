<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một dòng bảng "Phải thu công trình".
 *
 * Thay khối `@php` nằm TRONG `@foreach` (tra bản đồ trạng thái + lấy đợt kế tiếp) và 9 lượt gọi
 * closure `$money` của view cũ.
 */
final readonly class ProjectReceivableRow
{
    /**
     * @param  string  $codeText  mã công trình; không có thì `CT-<id>` đúng như bản cũ
     * @param  string  $companyText  chuỗi RỖNG khi công trình chưa gắn công ty — view không in dòng đó
     * @param  string|null  $overpaidText  chỉ khác null khi thu vượt > 1.000 đ (ngưỡng của bản cũ)
     * @param  string|null  $overdueText  chỉ khác null khi còn nợ quá hạn
     * @param  string  $statusToneClass  CHUỖI LỚP đầy đủ của huy hiệu trạng thái
     */
    public function __construct(
        public int $id,
        public string $codeText,
        public string $name,
        public string $addressText,
        public string $companyText,
        public string $progressText,
        public string $customerName,
        public string $phoneText,
        public string $contractText,
        public string $receivedText,
        public string $paymentsCountText,
        public ?string $overpaidText,
        public string $receivableText,
        public ?string $overdueText,
        public bool $hasNextTerm,
        public string $nextTermName,
        public string $nextTermAmountText,
        public string $nextTermDueText,
        public string $statusText,
        public string $statusToneClass,
    ) {}
}
