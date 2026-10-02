<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một chứng từ đính kèm trên trang chi tiết hoàn ứng.
 *
 * Thay khối `@php` nằm TRONG `@foreach`: nó gọi `basename()`, `pathinfo()` hai lần và `asset()`
 * cho từng tệp.
 */
final readonly class SettlementAttachmentRow
{
    /**
     * @param  string  $extLabel  nhãn trong ô vuông: `ẢNH` với ảnh, ngược lại là phần mở rộng VIẾT HOA;
     *                            tệp không có phần mở rộng ra `FILE` (đúng `?: 'FILE'` của bản cũ)
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $extLabel,
    ) {}
}
