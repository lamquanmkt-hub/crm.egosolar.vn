<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\View\Component;

/**
 * Ô nhập liệu thay cho `.form-control` của Bootstrap.
 *
 * ## Giá trị lấy từ SỐ ĐO, không chép từ tài liệu
 * Đo trên chính bản Bootstrap đang chạy (2026-09-03) bằng CDP:
 *
 * | trạng thái | padding | cỡ/dòng | viền | bo góc | nền |
 * |---|---|---|---|---|---|
 * | mặc định   | 6px/12px | 16/24 | `#dee2e6` | 6px | `#fff` |
 * | `sm`       | 4px/8px  | 14/21 | `#dee2e6` | 4px | `#fff` |
 * | `lg`       | 8px/16px | 20/30 | `#dee2e6` | 8px | `#fff` |
 * | disabled   | — | — | — | — | `#e9ecef` |
 * | focus      | — | — | `#86b7fe` | — | bóng `0 0 0 4px rgba(13,110,253,.25)` |
 * | invalid    | — | — | `#dc3545` | — | focus bóng `rgba(220,53,69,.25)` |
 *
 * ## ⚠️ Ba lần đo sai trước khi ra được bảng trên
 * - `el.focus()` trong Chrome headless KHÔNG bật `:focus` vì cửa sổ không có focus.
 * - `CSS.forcePseudoState` bật được luật (thấy trong `getMatchedStylesForNode`)
 *   nhưng KHÔNG phản ánh vào `getComputedStyle` — hai lần liền cho ra kết luận sai
 *   rằng "app đã vô hiệu hoá focus của Bootstrap".
 * - Chỉ cách gửi phím Tab THẬT rồi mới `focus()` mới ra giá trị đúng.
 *
 * ## Dùng `:focus` chứ không phải `:focus-visible`
 * Bootstrap tô viền ô nhập ngay cả khi bấm chuột (`.form-control:focus`), khác với
 * nút (`:focus-visible`). Dùng nhầm thì bấm chuột vào ô sẽ không thấy viền.
 */
final class Input extends Component
{
    /** Không có `!important`: dự án cấm, xem bước 3.4 trong tài liệu frontend. */
    private const BASE = 'tw:block tw:w-full tw:appearance-none '
        .'tw:text-[#212529] tw:bg-[#ffffff] tw:bg-clip-padding '
        .'tw:border tw:border-solid tw:border-[#dee2e6] '
        /*
         * line-height phải TƯƠNG ĐỐI đúng như Bootstrap (`line-height: 1.5`).
         * Bản đầu ghi cứng `text-[16px]/[24px]`; khi CSS trang đổi cỡ chữ của ô
         * (bảng thiết bị đặt 14px, ô tiền đặt 18px) thì Bootstrap co giãn theo
         * còn giá trị cứng thì không — đo trên sites/edit lệch 32/80 phần tử.
         * `-sm`/`-lg` của Bootstrap cũng CHỈ đổi cỡ chữ, line-height giữ 1.5.
         */
        .'tw:leading-[1.5] '
        // ⚠️ PHẢI VIẾT LIỀN MỘT DÒNG (Tailwind quét văn bản thô)
        .'tw:[transition:border-color_.15s_ease-in-out,box-shadow_.15s_ease-in-out] '
        .'tw:focus:outline-none tw:focus:border-[#86b7fe] '
        .'tw:focus:[box-shadow:0_0_0_4px_rgba(13,110,253,0.25)] '
        .'tw:disabled:bg-[#e9ecef] tw:disabled:opacity-100';

    /**
     * ⚠️ `min-height` CHỈ có ở `-sm`/`-lg`, KHÔNG có ở cỡ mặc định.
     *
     * Bootstrap 5.3.3 nguyên văn:
     *     .form-control-sm { min-height: calc(1.5em + .5rem + calc(var(--bs-border-width) * 2)) }
     *     .form-control-lg { min-height: calc(1.5em + 1rem + calc(var(--bs-border-width) * 2)) }
     *     .form-control    { (không có min-height) }
     *
     * Thiếu dòng này thì ô `-sm` đo được min-height 31px -> 0px (bắt được khi
     * chuyển hr/attendance/settings). Giữ dạng công thức vì `1.5em` co theo cỡ chữ.
     *
     * @var array<string, string>
     */
    private const SIZES = [
        '' => 'tw:px-3 tw:py-[6px] tw:text-[16px] tw:rounded-[var(--bs-border-radius,.375rem)]',
        'sm' => 'tw:px-2 tw:py-1 tw:text-[14px] tw:rounded-[var(--bs-border-radius-sm,.25rem)] '
            .'tw:[min-height:calc(1.5em_+_.5rem_+_calc(var(--bs-border-width,1px)_*_2))]',
        'lg' => 'tw:px-4 tw:py-2 tw:text-[20px] tw:rounded-[var(--bs-border-radius-lg,.5rem)] '
            .'tw:[min-height:calc(1.5em_+_1rem_+_calc(var(--bs-border-width,1px)_*_2))]',
        'none' => '',
    ];

    /**
     * `.is-invalid`: viền đỏ, bóng đỏ khi focus, VÀ biểu tượng cảnh báo.
     *
     * Biểu tượng không phải chi tiết bỏ được: Bootstrap chừa `padding-right: 36px`
     * cho nó. Thiếu icon mà vẫn chừa chỗ thì ô trông lệch; bỏ cả hai thì chữ dài
     * chạy sát mép khác hẳn bản cũ. Data-URI để trong biến `--ui-invalid-icon`
     * ở `resources/css/app.css`.
     */
    private const INVALID = 'tw:border-[#dc3545] tw:focus:border-[#dc3545] '
        .'tw:focus:[box-shadow:0_0_0_4px_rgba(220,53,69,0.25)] '
        .'tw:pr-9 tw:bg-no-repeat '
        .'tw:[background-image:var(--ui-invalid-icon)] '
        .'tw:[background-position:calc(100%_-_9px)_50%] '
        .'tw:[background-size:18px_18px]';

    /**
     * `type="file"`: nút "Chọn tệp" do trình duyệt vẽ, Bootstrap tô lại bằng
     * `::file-selector-button`. Bỏ qua phần này là đổi giao diện thấy rõ — đo được
     * **25 thuộc tính lệch** (nút cao 31,5px -> 25,5px, nền #f8f9fa -> #efefef,
     * viền solid -> outset, ô cao 33,5px -> 39,5px).
     *
     * Nguyên văn Bootstrap 5.3.3:
     *     .form-control[type=file] { overflow: hidden }
     *     .form-control[type=file]:not(:disabled):not([readonly]) { cursor: pointer }
     *     .form-control::file-selector-button {
     *         padding: .375rem .75rem; margin: -.375rem -.75rem;
     *         margin-inline-end: .75rem; color: var(--bs-body-color);
     *         background-color: var(--bs-tertiary-bg); pointer-events: none;
     *         border-color: inherit; border-style: solid; border-width: 0;
     *         border-inline-end-width: var(--bs-border-width); border-radius: 0;
     *         transition: color .15s, background-color .15s, border-color .15s, box-shadow .15s;
     *     }
     *
     * ⚠️ `border-width` viết GỘP một khai báo `0 1px 0 0` chứ không tách
     * `border-0` + `border-r`: Tailwind tự sắp thứ tự utility, tách ra thì
     * `border-width:0` có thể đè lên và xoá mất viền phải.
     *
     * ⚠️ `border-color` phải là `inherit` — luật riêng của trang đổi màu viền ô
     * (proposals dùng #dbe3ee) và nút phải đổi theo. Ghi cứng màu là sai ngay khi
     * trang khác đổi màu.
     */
    private const FILE = 'tw:[&[type=file]]:overflow-hidden '
        .'tw:[&[type=file]:not(:disabled):not([readonly])]:cursor-pointer '
        .'tw:file:text-[#212529] tw:file:bg-[#f8f9fa] '
        .'tw:file:pointer-events-none '
        .'tw:file:border-inherit tw:file:border-solid '
        .'tw:file:[border-width:0_var(--bs-border-width,1px)_0_0] '
        .'tw:file:rounded-none '
        // ⚠️ PHẢI VIẾT LIỀN MỘT DÒNG (Tailwind quét văn bản thô)
        .'tw:file:[transition:color_.15s_ease-in-out,background-color_.15s_ease-in-out,border-color_.15s_ease-in-out,box-shadow_.15s_ease-in-out]';

    /**
     * Đệm và lề của nút chọn tệp ĐỔI THEO CỠ ô, nguyên văn Bootstrap:
     *     .form-control::file-selector-button    { padding:.375rem .75rem; margin:-.375rem -.75rem; margin-inline-end:.75rem }
     *     .form-control-sm::file-selector-button { padding:.25rem .5rem;   margin:-.25rem -.5rem;   margin-inline-end:.5rem }
     *     .form-control-lg::file-selector-button { padding:.5rem 1rem;     margin:-.5rem -1rem;     margin-inline-end:1rem }
     *
     * @var array<string, string>
     */
    private const FILE_SIZES = [
        '' => 'tw:file:px-3 tw:file:py-[6px] tw:file:-mt-[6px] tw:file:-mb-[6px] tw:file:-ml-3 tw:file:mr-3',
        'sm' => 'tw:file:px-2 tw:file:py-1 tw:file:-mt-1 tw:file:-mb-1 tw:file:-ml-2 tw:file:mr-2',
        'lg' => 'tw:file:px-4 tw:file:py-2 tw:file:-mt-2 tw:file:-mb-2 tw:file:-ml-4 tw:file:mr-4',
        'none' => '',
    ];

    /**
     * Chiều cao tối thiểu của `textarea`, công thức nguyên văn của Bootstrap:
     *     textarea.form-control { min-height: calc(1.5em + .75rem + calc(var(--bs-border-width) * 2)) }
     *
     * Phải giữ dạng CÔNG THỨC chứ không quy ra pixel: `1.5em` co giãn theo cỡ chữ,
     * nên ô nằm trong khối có `font-size` khác sẽ cao khác. Ghi cứng 38px là đúng
     * ở cỡ mặc định và sai ở mọi cỡ còn lại.
     */
    private const TEXTAREA_MIN_HEIGHT =
        'tw:[min-height:calc(1.5em_+_.75rem_+_calc(var(--bs-border-width,1px)_*_2))]';

    /**
     * @param  string  $as  `input` | `textarea` | `select`
     *
     * `select` KHÔNG phải nhầm với {@see Select}: một số trang cố ý gắn
     * `.form-control` (chứ không phải `.form-select`) lên thẻ `<select>` — hộp
     * viền phẳng, KHÔNG có mũi tên. Giữ đúng như vậy thay vì "sửa cho đúng chuẩn",
     * vì đổi là đổi giao diện.
     */
    public function __construct(
        public string $size = '',
        public bool $invalid = false,
        public string $as = 'input',
    ) {}

    /** @return list<string> */
    public static function sizes(): array
    {
        return array_keys(self::SIZES);
    }

    public function classes(): string
    {
        $out = self::BASE.' '.(self::SIZES[$this->size] ?? self::SIZES['']);

        if (($this->attributes?->get('type') ?? '') === 'file') {
            $out .= ' '.self::FILE.' '.(self::FILE_SIZES[$this->size] ?? self::FILE_SIZES['']);
        }

        if ($this->invalid) {
            // Bỏ màu viền mặc định để không có hai khai báo cùng thuộc tính.
            $out = implode(' ', array_filter(
                explode(' ', $out),
                static fn (string $c): bool => $c !== 'tw:border-[#dee2e6]'
                    && ! str_starts_with($c, 'tw:focus:border-')
                    && ! str_starts_with($c, 'tw:focus:[box-shadow'),
            ));
            $out .= ' '.self::INVALID;
        }

        return $out;
    }

    /**
     * Lớp cho `textarea` — thêm chiều cao tối thiểu.
     *
     * Phải là method chứ không hằng số dùng trực tiếp trong template: `self::`
     * trong chuỗi Blade của `render()` được biên dịch ra ngoài ngữ cảnh lớp này
     * nên phân giải sai (đã gặp: "Undefined constant Filesystem::…").
     */
    public function textareaClasses(): string
    {
        // Cỡ `-sm`/`-lg` đã tự mang `min-height` riêng (xem SIZES) và Bootstrap
        // cũng khai đúng như vậy cho `textarea.form-control-sm/-lg`. Cộng thêm
        // công thức của cỡ mặc định vào là có hai khai báo chọi nhau.
        if ($this->size !== '') {
            return $this->classes();
        }

        return $this->classes().' '.self::TEXTAREA_MIN_HEIGHT;
    }

    public function render(): string
    {
        return <<<'BLADE'
        @if ($as === 'textarea')
            <textarea {{ $attributes->class($textareaClasses()) }}>{{ $slot }}</textarea>
        @elseif ($as === 'select')
            <select {{ $attributes->class($classes()) }}>{{ $slot }}</select>
        @else
            <input {{ $attributes->class($classes()) }}>
        @endif
        BLADE;
    }
}
