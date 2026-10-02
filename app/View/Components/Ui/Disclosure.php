<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Khối thu/mở — thay `.collapse` + `data-bs-toggle="collapse"` của Bootstrap, chạy bằng Alpine.
 *
 * ## Vì sao dùng SỰ KIỆN chứ không bọc cả nút lẫn khối trong một thẻ
 * Cách gọn nhất của Alpine là đặt `x-data` lên thẻ cha bao trọn cả nút bấm lẫn khối nội dung.
 * Nhưng trên các trang thật của repo, nút nằm trong thanh công cụ còn khối nội dung nằm xa
 * bên dưới (`marketing/budget`); bọc chung sẽ phải thêm một thẻ cha ôm gần cả trang và làm
 * đổi bố cục. Nên nút và khối rời nhau, nói chuyện qua sự kiện `window` có tên:
 *
 *     <x-ui.button x-on:click="$dispatch('toggle-disclosure', 'budgetSummary')">…</x-ui.button>
 *     <x-ui.disclosure name="budgetSummary" :open="$hasFilter"> … </x-ui.disclosure>
 *
 * Cùng một cơ chế với `<x-ui.modal>` nên cả trang chỉ có MỘT khái niệm phải nhớ.
 *
 * ## `x-cloak` là bắt buộc
 * Alpine nạp kiểu module (defer), nên nếu thiếu `x-cloak` thì khối đang đóng vẫn HIỆN ĐỦ một
 * nhịp rồi mới biến mất. Quy tắc `[x-cloak]{display:none!important}` khai trong
 * `resources/css/app.css` — Alpine không kèm sẵn phần CSS đó.
 */
final class Disclosure extends Component
{
    /** Bootstrap `.collapse` không có kiểu dáng riêng, chỉ ẩn/hiện — nên nền cũng để trống. */
    private const BASE = '';

    public function __construct(
        /** Tên để nút bấm ở nơi khác gọi tới qua `$dispatch('toggle-disclosure', '<tên>')`. */
        public string $name,
        /** Mở sẵn khi vào trang (tương đương `class="collapse show"` của bản cũ). */
        public bool $open = false,
    ) {}

    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div
            x-data="{ open: @js($open) }"
            x-on:toggle-disclosure.window="$event.detail === @js($name) && (open = ! open)"
            x-show="open"
            x-cloak
            {{ $attributes->class($classes($attributes->get('class', ''))) }}
        >{{ $slot }}</div>
        BLADE;
    }
}
