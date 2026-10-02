<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một dòng lịch sử / bảo trì của tài sản trong bảng chi tiết `finance/assets`.
 */
final readonly class AssetEventRow
{
    /**
     * @param  string  $typeLabel  nhãn tiếng Việt; loại lạ thì trả nguyên mã, đúng `?? $event->type` cũ
     * @param  string|null  $amountText  null khi không in dòng chi phí. ⚠️ Bản cũ dùng `@if($event->amount)`
     *                                   trên giá trị THÔ: cột decimal trả chuỗi `"0.00"` — chuỗi này
     *                                   TRUTHY trong PHP nên chi phí 0 VẪN hiện `0 đ`. Giữ y hành vi đó.
     */
    public function __construct(
        public string $typeLabel,
        public string $dateText,
        public string $noteText,
        public ?string $amountText,
    ) {}
}
