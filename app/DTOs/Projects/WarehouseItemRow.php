<?php

declare(strict_types=1);

namespace App\DTOs\Projects;

/**
 * Cặp "dòng vật tư + bản ghép kho đầu tiên" của trang xuất kho công trình (bản thử).
 *
 * Tồn tại vì view trước đây lặp lại `@php $allocation=$item->allocations->first(); @endphp` ở HAI
 * vòng lặp khác nhau trên cùng danh sách.
 *
 * Giữ nguyên model thay vì trải 18 thuộc tính ra: view còn dùng cả quan hệ (`->product`,
 * `->warehouse`) và `rules/blade-views.md` cho phép đọc accessor của model. Tiền lệ cùng cách:
 * `App\DTOs\Finance\PaymentRequestAttachmentRow`.
 */
final readonly class WarehouseItemRow
{
    /**
     * @param  object  $item  một dòng `project_test_material_items`
     * @param  object|null  $allocation  bản ghép kho đầu tiên; null khi dòng chưa ghép kho
     */
    public function __construct(
        public object $item,
        public ?object $allocation,
    ) {}
}
