<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Vỏ cuộn ngang của bảng — thay `.table-responsive`.
 *
 * ## `.table-responsive` KHÔNG chỉ là lớp trang trí
 * Nó là MÓC của hai đoạn JS trong `public/js/main.js`:
 *   1. `autoWrapTables()` bỏ qua bảng nào đã nằm trong `.table-responsive`; đổi tên lớp mà không
 *      báo cho nó biết thì mọi bảng bị bọc THÊM một div nữa (đã đo: 4 bảng -> +4 phần tử DOM);
 *   2. thanh cuộn ngang dính (`getWrappers()`) dò đúng danh sách lớp đó.
 * Nên component phát `data-ego-table-wrap` và `main.js` đã được dạy nhận thêm móc này — cùng cách
 * `<x-ui.alert>` phát `data-ego-alert` cho `bs-compat/alert.js`.
 *
 * Số đo từ `.table-responsive` + luật `!important` trong `layouts/app`: `overflow-x:auto`
 * (kéo theo `overflow-y:auto`) và `-webkit-overflow-scrolling:touch`.
 */
final class TableWrap extends Component
{
    private const BASE = 'tw:overflow-x-auto tw:[-webkit-overflow-scrolling:touch]';

    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div data-ego-table-wrap {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</div>
        BLADE;
    }
}
