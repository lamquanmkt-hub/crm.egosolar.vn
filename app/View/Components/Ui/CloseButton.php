<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Nút đóng (dấu ×) — thay `.btn-close` / `.btn-close-white` của Bootstrap.
 *
 * ## Giá trị lấy từ đâu
 * Chép từ `bootstrap@5.3.3` đang chạy, rồi kiểm bằng phép so bản sao trong trang thật
 * (modal/offcanvas được mở ra để đo) ở 4 trạng thái: mặc định / hover / nhấn / focus.
 * `.btn-close` dùng `:focus` (không phải `:focus-visible`): bấm chuột xong nút vẫn sáng
 * hẳn (opacity 1) và có vòng xanh — giữ đúng như vậy.
 *
 * ## Vì sao có prop `in`
 * `.btn-close` còn là MÓC cho luật theo ngữ cảnh của Bootstrap:
 *   `.modal-header .btn-close`     padding 8px; margin -8px -8px -8px auto
 *   `.offcanvas-header .btn-close` padding 8px; margin -8px -8px -8px auto
 *   `.alert-dismissible .btn-close` absolute; top/right 0; z-index 2; padding 20px 16px
 * Bỏ class là mất chúng, nên nơi gọi khai ngữ cảnh: `in="modal|offcanvas|alert"`.
 * Đệm của ngữ cảnh THAY cho đệm nền (không xếp chồng hai utility cùng thuộc tính —
 * ai thắng sẽ do thứ tự trong tệp CSS, không đoán được).
 *
 * ## Ảnh nền
 * SVG data-URI khai ở `:root { --ui-close-icon }` trong resources/css/app.css (cùng cách
 * với `--ui-select-caret`), vì Tailwind v4 cắt biến `@theme` mà nó không thấy ai dùng.
 */
final class CloseButton extends Component
{
    private const BASE = 'tw:box-content tw:w-[1em] tw:h-[1em] tw:text-[#000000] tw:bg-transparent '
        .'tw:[background-image:var(--ui-close-icon)] tw:bg-center tw:bg-no-repeat tw:[background-size:1em_auto] '
        // `border:0` của Bootstrap đặt cả width lẫn style; `tw:border-0` chỉ đặt width -> thêm border-none.
        .'tw:border-0 tw:border-none tw:rounded-[.375rem] tw:opacity-50 '
        .'tw:hover:opacity-75 '
        .'tw:focus:outline-none tw:focus:shadow-[0_0_0_4px_rgba(13,110,253,0.25)] tw:focus:opacity-100 '
        .'tw:disabled:pointer-events-none tw:disabled:select-none tw:disabled:opacity-25';

    /** Đệm nền của `.btn-close`: .25em mỗi cạnh. */
    private const PADDING_DEFAULT = 'tw:p-[0.25em]';

    /** @var array<string, string> ngữ cảnh -> đệm + lề thay cho đệm nền */
    private const CONTEXTS = [
        '' => self::PADDING_DEFAULT,
        'modal' => 'tw:p-2 tw:-my-2 tw:-mr-2 tw:ml-auto',
        'offcanvas' => 'tw:p-2 tw:-my-2 tw:-mr-2 tw:ml-auto',
        'alert' => 'tw:absolute tw:top-0 tw:right-0 tw:z-[2] tw:py-5 tw:px-4',
    ];

    /** `.btn-close-white`: lật màu để dùng trên nền tối. */
    private const WHITE = 'tw:[filter:invert(1)_grayscale(100%)_brightness(200%)]';

    public function __construct(
        public string $in = '',
        public bool $white = false,
    ) {
        if (! array_key_exists($in, self::CONTEXTS)) {
            throw new \InvalidArgumentException(
                "x-ui.close-button: in=\"$in\" không được phép; chỉ nhận ".implode('/', array_filter(array_keys(self::CONTEXTS))).'.'
            );
        }
    }

    /** Chuỗi class hoàn chỉnh, đã nhường lớp mà nơi gọi phủ trọn. */
    public function classes(string $callerClasses = ''): string
    {
        $all = self::BASE.' '.self::CONTEXTS[$this->in].($this->white ? ' '.self::WHITE : '');

        return LopTienIch::nhuong($all, $callerClasses);
    }

    /** @return list<string> */
    public static function contexts(): array
    {
        return array_keys(self::CONTEXTS);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <button type="{{ $attributes->get('type', 'button') }}" data-ego-close {{ $attributes->except('type')->class($classes($attributes->get('class', ''))) }}></button>
        BLADE;
    }
}
