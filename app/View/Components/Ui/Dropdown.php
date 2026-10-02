<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Menu thả xuống — thay `.dropdown` + `data-bs-toggle="dropdown"` của Bootstrap, chạy bằng Alpine.
 *
 * ## Vì sao KHÔNG dùng sự kiện `window` như <x-ui.modal> / <x-ui.disclosure>
 * Hai component kia có nút bấm nằm xa khối nội dung nên phải nói chuyện qua sự kiện có tên.
 * Dropdown thì ngược lại: nút và menu LUÔN đi liền nhau và mỗi dòng bảng có một cái riêng —
 * đặt tên cho từng cái là thừa và dễ trùng. Ở đây `x-data` bọc đúng một cặp nút+menu là gọn nhất.
 *
 * ## Đóng khi bấm ra ngoài
 * `x-on:click.outside` của Alpine 3. Không cần lắng nghe `document` thủ công như bản
 * `bootstrap-compat.js`, và cũng không cần logic "đóng mọi dropdown khác": mỗi cái tự đóng khi
 * con trỏ ra khỏi phạm vi của nó.
 *
 * ## Số đo (bootstrap@5.3.3, đo trên trang thật)
 * Menu: `position:absolute`, `z-index:1000`, `min-width:160px`, đệm dọc 8px, nền trắng,
 * viền 1px solid rgba(0,0,0,.176), bo 6px, bỏ dấu đầu dòng.
 * Mục: `display:block`, đệm 4px/16px, `white-space:nowrap`, màu #212529, nền trong suốt.
 * Bootstrap KHÔNG đặt bóng cho `.dropdown-menu` (`box-shadow:none`) — trang gọi tự thêm nếu muốn.
 */
final class Dropdown extends Component
{
    private const WRAP = 'tw:relative';

    // `tw:px-0` KHÔNG thừa: `.dropdown-menu` của Bootstrap khai `padding: .5rem 0`, tức nó cũng
    // xoá `padding-inline-start` mặc định của <ul>. Thiếu nó thì menu thụt vào 32px (đã đo).
    private const MENU = 'tw:absolute tw:z-[1000] tw:min-w-[160px] tw:py-2 tw:px-0 tw:m-0 tw:list-none '
        .'tw:text-[16px] tw:text-left tw:text-[#212529] tw:bg-[#ffffff] '
        .'tw:border tw:border-solid tw:border-[rgba(0,0,0,0.176)] tw:rounded-[6px]';

    /** `end` = mép phải menu thẳng mép phải nút (tương đương `.dropdown-menu-end`). */
    private const ALIGN = [
        'start' => 'tw:left-0',
        'end' => 'tw:right-0',
    ];

    public function __construct(
        /** start | end */
        public string $align = 'start',
        /** Lớp thêm cho chính thẻ <ul> (bóng, bo góc riêng của trang). */
        public string $menuClass = '',
    ) {}

    public function wrapClasses(): string
    {
        return self::WRAP;
    }

    public function menuClasses(): string
    {
        return trim(self::MENU.' '.(self::ALIGN[$this->align] ?? self::ALIGN['start']).' '.$this->menuClass);
    }

    public function render(): View
    {
        return view('components.ui.dropdown');
    }
}
