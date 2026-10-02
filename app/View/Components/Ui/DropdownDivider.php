<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\View\Component;

/**
 * Đường kẻ ngăn trong `<x-ui.dropdown>` — thay `<li><hr class="dropdown-divider"></li>`.
 *
 * Số đo `.dropdown-divider`: **height 0** (đường kẻ chính là viền TRÊN, không phải chiều cao),
 * lề dọc 8px, `border-top: 1px solid rgba(0,0,0,.176)`, `overflow:hidden`.
 *
 * ⚠️ KHÔNG dùng `tw:border-0 tw:border-t`: utility bề dày của Tailwind v4 phát kèm
 * `border-style: var(--tw-border-style)` cho CẢ BỐN CẠNH
 * (`.tw\:border-0{border-style:var(--tw-border-style);border-width:0}`), mà biến đó mặc định
 * `solid` — ba cạnh còn lại lộ kiểu `solid` dù bề dày 0. Đo trên trang thật đúng như vậy.
 *
 * Cũng KHÔNG cần thêm `[border:0]`: reboot của Bootstrap đã khai `hr { border: 0; border-top: … }`
 * nên bốn cạnh vốn đã bằng 0. Thêm vào còn hại: hai utility thuộc-tính-tuỳ-ý cùng độ đặc hiệu,
 * `[border:0]` đứng sau trong tệp CSS và XOÁ luôn viền trên (đã đo: 1px -> 0px).
 */
final class DropdownDivider extends Component
{
    public function render(): string
    {
        return <<<'BLADE'
        <li><hr class="tw:h-0 tw:my-2 tw:overflow-hidden tw:[border-top:1px_solid_rgba(0,0,0,0.176)] tw:opacity-100"></li>
        BLADE;
    }
}
