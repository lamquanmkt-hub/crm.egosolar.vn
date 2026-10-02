<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một dòng thành phần trong phiếu lương chi tiết.
 *
 * Tồn tại vì view trước đây có ba khối `@php $def=$row->definition; @endphp` — mỗi vòng lặp một
 * khối, chỉ để lấy ra phần định nghĩa rồi tự gọi closure định dạng.
 */
final readonly class SalaryComponentRow
{
    /**
     * @param  int  $id  id định nghĩa, dùng cho `name="components[<id>]"`
     * @param  bool  $editable  định nghĩa cho phép sửa số tiền hay không
     * @param  float  $amountValue  số thô cho `<input type=number>`
     * @param  string  $valueText  số đã định dạng theo `display_format` của định nghĩa
     */
    public function __construct(
        public int $id,
        public string $label,
        public bool $editable,
        public float $amountValue,
        public string $valueText,
    ) {}
}
