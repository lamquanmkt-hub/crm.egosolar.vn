<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Một mục trong `<x-ui.dropdown>` — thay `.dropdown-item`.
 *
 * Tự bọc trong `<li>` để nơi gọi khỏi phải nhớ: menu là `<ul>` nên con trực tiếp phải là `<li>`.
 * Có `href` thì ra thẻ `<a>`, không thì ra `<button type="submit">` (các mục xoá nằm trong form).
 *
 * Số đo `.dropdown-item` (bootstrap@5.3.3): `display:block`, đệm 4px 16px, `white-space:nowrap`,
 * màu #212529, nền trong suốt, không gạch chân. Hover của Bootstrap là nền #e9ecef.
 */
final class DropdownItem extends Component
{
    private const BASE = 'tw:block tw:w-full tw:py-1 tw:px-4 tw:text-left tw:font-normal '
        .'tw:text-[#212529] tw:bg-transparent tw:whitespace-nowrap tw:no-underline '
        // `[border:0]` chứ không `border-0`: bản Bootstrap khai `border: 0` nên xoá cả KIỂU viền,
        // còn `tw:border-0` chỉ xoá bề dày và kiểu viền lộ ra `solid` (đã đo trên chính trang này).
        .'tw:[border:0] '
        .'tw:cursor-pointer tw:hover:bg-[#e9ecef]';

    public function __construct(
        public ?string $href = null,
    ) {}

    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <li>
            @if ($href !== null)
                <a href="{{ $href }}" {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</a>
            @else
                <button {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</button>
            @endif
        </li>
        BLADE;
    }
}
