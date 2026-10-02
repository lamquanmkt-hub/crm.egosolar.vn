<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\View\Component;

/**
 * Ô chọn thay cho `.form-select` của Bootstrap.
 *
 * ## Giá trị lấy từ chính bản Bootstrap đang chạy
 * Trích thẳng từ `bootstrap@5.3.3/dist/css/bootstrap.min.css` — đúng file mà
 * `layouts/app.blade.php` nạp từ CDN (2026-09-04):
 *
 * | | padding | cỡ/dòng | bo góc |
 * |---|---|---|---|
 * | mặc định | `.375rem 2.25rem .375rem .75rem` | 1rem / 1.5 | `--bs-border-radius` |
 * | `sm` | trên–dưới `.25rem`, trái `.5rem` | `.875rem` | `--bs-border-radius-sm` |
 * | `lg` | trên–dưới `.5rem`, trái `1rem` | `1.25rem` | `--bs-border-radius-lg` |
 *
 * focus: viền `#86b7fe` + bóng `0 0 0 .25rem rgba(13,110,253,.25)`;
 * disabled: nền `--bs-secondary-bg`.
 *
 * ## ⚠️ `padding-right` KHÔNG đổi theo cỡ
 * Bootstrap chỉ đặt lại `padding-top/bottom/left` cho `sm` và `lg`; lề phải giữ
 * nguyên `2.25rem` ở cả ba cỡ vì mũi tên vẽ bằng ảnh nền có kích thước cố định
 * (`16px 12px`, đặt ở `right .75rem center`). Nếu "cho gọn" bằng cách thu lề phải
 * theo cỡ thì chữ dài sẽ chạy đè lên mũi tên.
 *
 * Ảnh mũi tên nằm trong biến `--ui-select-caret` ở `resources/css/app.css`, chép
 * nguyên văn data-URI của Bootstrap để không lệch một pixel nào.
 *
 * @see Input cùng cách dựng, cho `.form-control`
 */
final class Select extends Component
{
    /** Không có `!important`: dự án cấm, xem bước 3.4 trong tài liệu frontend. */
    private const BASE = 'tw:block tw:w-full tw:appearance-none '
        .'tw:text-[#212529] tw:bg-[#ffffff] '
        .'tw:border tw:border-solid tw:border-[#dee2e6] '
        // line-height tương đối như Bootstrap — xem ghi chú ở Input.
        .'tw:leading-[1.5] '
        // ⚠️ PHẢI VIẾT LIỀN MỘT DÒNG (Tailwind quét văn bản thô)
        .'tw:[transition:border-color_.15s_ease-in-out,box-shadow_.15s_ease-in-out] '
        // Bootstrap khai HAI lớp nền (`--bs-form-select-bg-img` và
        // `--bs-form-select-bg-icon`, lớp sau mặc định `none`). Khai đủ hai lớp thì
        // computed style trùng khít, thiếu một lớp là lệch 4 thuộc tính.
        .'tw:[background-repeat:no-repeat,no-repeat] '
        .'tw:[background-image:var(--ui-select-caret),none] '
        .'tw:[background-position:calc(100%_-_12px)_50%,12px_50%] '
        .'tw:[background-size:16px_12px,16px_12px] '
        .'tw:pr-9 '
        /*
         * Bootstrap bỏ mũi tên khi ô chọn hiện nhiều dòng:
         *   .form-select[multiple], .form-select[size]:not([size="1"])
         *       { padding-right: .75rem; background-image: none; }
         * Dùng đúng cơ chế attribute selector để không cần thêm tham số cho
         * component — bên gọi vẫn viết `multiple` như HTML thường.
         */
        .'tw:[&[multiple]]:[background-image:none] tw:[&[multiple]]:pr-3 '
        .'tw:[&[size]:not([size=\'1\'])]:[background-image:none] '
        .'tw:[&[size]:not([size=\'1\'])]:pr-3 '
        .'tw:focus:outline-none tw:focus:border-[#86b7fe] '
        .'tw:focus:[box-shadow:0_0_0_4px_rgba(13,110,253,0.25)] '
        .'tw:disabled:bg-[#e9ecef] tw:disabled:opacity-100';

    /** @var array<string, string> */
    private const SIZES = [
        '' => 'tw:pl-3 tw:py-[6px] tw:text-[16px] tw:rounded-[var(--bs-border-radius,.375rem)]',
        'sm' => 'tw:pl-2 tw:py-1 tw:text-[14px] tw:rounded-[var(--bs-border-radius-sm,.25rem)]',
        'lg' => 'tw:pl-4 tw:py-2 tw:text-[20px] tw:rounded-[var(--bs-border-radius-lg,.5rem)]',
        'none' => '',
    ];

    private const INVALID = 'tw:border-[#dc3545] tw:focus:border-[#dc3545] '
        .'tw:focus:[box-shadow:0_0_0_4px_rgba(220,53,69,0.25)]';

    public function __construct(
        public string $size = '',
        public bool $invalid = false,
    ) {}

    /** @return list<string> */
    public static function sizes(): array
    {
        return array_keys(self::SIZES);
    }

    public function classes(): string
    {
        $out = self::BASE.' '.(self::SIZES[$this->size] ?? self::SIZES['']);

        if (! $this->invalid) {
            return $out;
        }

        // Bỏ khai báo mặc định để không có hai lần cùng một thuộc tính.
        $out = implode(' ', array_filter(
            explode(' ', $out),
            static fn (string $c): bool => $c !== 'tw:border-[#dee2e6]'
                && ! str_starts_with($c, 'tw:focus:border-')
                && ! str_starts_with($c, 'tw:focus:[box-shadow'),
        ));

        return $out.' '.self::INVALID;
    }

    public function render(): string
    {
        return <<<'BLADE'
        <select {{ $attributes->class($classes()) }}>{{ $slot }}</select>
        BLADE;
    }
}
