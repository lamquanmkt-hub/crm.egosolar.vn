<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\View\Components\Ui\Input;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Canh giữ component `<x-ui.input>` — bản thay cho `.form-control`.
 *
 * Giá trị trong component lấy từ SỐ ĐO trên chính Bootstrap đang chạy, và đã đối
 * chiếu cạnh nhau: 7 cặp phần tử × 20 thuộc tính + 4 trạng thái tương tác đều khớp
 * tuyệt đối (2026-09-03).
 *
 * ## Ba cái bẫy đã ghi lại để không lặp
 * - Đo focus bằng `el.focus()` trong Chrome headless cho kết quả SAI: cửa sổ không
 *   có focus nên `:focus` không khớp. `CSS.forcePseudoState` cũng sai kiểu khác —
 *   nó bật luật trong `getMatchedStylesForNode` nhưng không vào `getComputedStyle`.
 *   Hai lần liền dẫn tới kết luận sai là "app đã tắt focus của Bootstrap". Chỉ gửi
 *   phím Tab THẬT rồi mới `focus()` mới ra đúng.
 * - `shadow-[...]` của Tailwind sinh thêm 4 lớp bóng trong suốt (biến `--tw-*-shadow`).
 *   Không nhìn thấy nhưng làm chuỗi computed khác Bootstrap, khiến mọi phép so sau
 *   này báo lệch giả. Dùng `[box-shadow:...]` đặt thẳng.
 * - `.is-invalid` KHÔNG chỉ đổi màu viền: nó còn chừa `padding-right: 36px` cho
 *   biểu tượng cảnh báo. Bỏ icon mà giữ padding thì ô trông lệch.
 */
final class UiInputTest extends TestCase
{
    public function test_ra_the_input_mac_dinh(): void
    {
        $html = Blade::render('<x-ui.input name="a" />');

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('name="a"', $html);
    }

    public function test_as_textarea_ra_the_textarea(): void
    {
        $html = Blade::render('<x-ui.input as="textarea" name="a">nội dung</x-ui.input>');

        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('nội dung', $html);
        // Công thức của Bootstrap, KHÔNG quy ra pixel — `1.5em` co giãn theo cỡ chữ.
        $this->assertStringContainsString('calc(1.5em_+_.75rem', $html);
        $this->assertStringNotContainsString('tw:min-h-[38px]', $html);
    }

    /**
     * Ba cỡ chỉ đổi CỠ CHỮ và padding — line-height giữ nguyên 1.5, đúng Bootstrap:
     *     .form-control    { padding:.375rem .75rem; font-size:1rem; line-height:1.5 }
     *     .form-control-sm { padding:.25rem .5rem;   font-size:.875rem }
     *     .form-control-lg { padding:.5rem 1rem;     font-size:1.25rem }
     */
    public function test_ba_co_dung_so_do(): void
    {
        $this->assertSame(['', 'sm', 'lg', 'none'], Input::sizes());

        $default = Blade::render('<x-ui.input />');
        $this->assertStringContainsString('tw:px-3', $default);
        $this->assertStringContainsString('tw:py-[6px]', $default);
        $this->assertStringContainsString('tw:text-[16px]', $default);
        $this->assertStringContainsString('tw:leading-[1.5]', $default);

        $sm = Blade::render('<x-ui.input size="sm" />');
        $this->assertStringContainsString('tw:px-2', $sm);
        $this->assertStringContainsString('tw:text-[14px]', $sm);

        $lg = Blade::render('<x-ui.input size="lg" />');
        $this->assertStringContainsString('tw:px-4', $lg);
        $this->assertStringContainsString('tw:text-[20px]', $lg);
    }

    /**
     * line-height phải TƯƠNG ĐỐI, không ghi cứng theo pixel.
     *
     * Đây là lỗi đã xảy ra thật: bản đầu viết `text-[16px]/[24px]`, `[14px]/[21px]`,
     * `[20px]/[30px]`. Khi CSS của trang đổi cỡ chữ của ô — `sites/edit` đặt 14px
     * trong bảng thiết bị và 18px ở ô tiền — Bootstrap co giãn theo `1.5` còn giá
     * trị cứng thì không. Đo được 32/80 phần tử lệch chiều cao.
     */
    public function test_khong_ghi_cung_line_height_theo_co(): void
    {
        foreach (['', 'sm', 'lg'] as $size) {
            $html = Blade::render('<x-ui.input size="'.$size.'" />');

            $this->assertDoesNotMatchRegularExpression(
                '/tw:text-\[\d+px\]\/\[/',
                $html,
                "cỡ '$size' đang ghi cứng line-height; Bootstrap dùng line-height:1.5 tương đối",
            );
        }
    }

    /**
     * Focus dùng `:focus`, KHÔNG phải `:focus-visible`.
     *
     * Bootstrap tô viền ô nhập cả khi bấm chuột; dùng nhầm `:focus-visible` thì
     * bấm chuột vào ô sẽ không thấy viền — ảnh tĩnh không bắt được lỗi này.
     */
    public function test_focus_dung_pseudo_class_dung(): void
    {
        $html = Blade::render('<x-ui.input />');

        $this->assertStringContainsString('tw:focus:border-[#86b7fe]', $html);
        $this->assertStringContainsString('tw:focus:[box-shadow:0_0_0_4px_rgba(13,110,253,0.25)]', $html);
        $this->assertStringNotContainsString('focus-visible', $html);
    }

    /** Bóng phải đặt thẳng, không qua `shadow-*` (tránh 4 lớp bóng thừa). */
    public function test_khong_dung_utility_shadow(): void
    {
        $html = Blade::render('<x-ui.input />').Blade::render('<x-ui.input :invalid="true" />');

        $this->assertDoesNotMatchRegularExpression('/tw:(focus:)?shadow-\[/', $html);
    }

    /** `.is-invalid` phải có đủ viền đỏ, bóng đỏ, icon VÀ chỗ chừa cho icon. */
    public function test_invalid_du_bon_thanh_phan(): void
    {
        $html = Blade::render('<x-ui.input :invalid="true" />');

        $this->assertStringContainsString('tw:border-[#dc3545]', $html);
        $this->assertStringContainsString('tw:focus:[box-shadow:0_0_0_4px_rgba(220,53,69,0.25)]', $html);
        $this->assertStringContainsString('var(--ui-invalid-icon)', $html);
        $this->assertStringContainsString('tw:pr-9', $html);

        // Không được còn màu viền/bóng của trạng thái thường: hai khai báo cùng
        // thuộc tính thì thứ tự trong CSS sinh ra quyết định, không đoán được.
        $this->assertStringNotContainsString('tw:border-[#dee2e6]', $html);
        $this->assertStringNotContainsString('rgba(13,110,253,0.25)', $html);
    }

    /** Biến icon phải tồn tại trong CSS nguồn, nếu không `var()` rỗng. */
    public function test_bien_icon_ton_tai(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--ui-invalid-icon:', $css);
        $this->assertStringContainsString('data:image/svg+xml', $css);
    }

    /** Không dùng !important. */
    public function test_khong_dung_important(): void
    {
        $html = Blade::render('<x-ui.input :invalid="true" size="lg" />');

        $this->assertKhongLamDungImportant($html);
    }

    /**
     * Nhãn chỉ có `margin-bottom` — và KHÔNG được có gì khác.
     *
     * `.form-label` của Bootstrap 5.3.3 đúng một khai báo:
     *     .form-label { margin-bottom: .5rem; }
     * Không cỡ chữ, không font-weight, không display.
     *
     * Bản đầu của component thêm `text-[16px]/[24px]`, `font-normal` và
     * `inline-block`. Trên nhãn được cấp cỡ chữ khác (kèm `.small`, hoặc nằm trong
     * bảng đặt `font-size:14px`), những giá trị đó ĐÈ mất cỡ chữ thừa kế.
     */
    public function test_nhan_chi_dat_khoang_cach_day(): void
    {
        $html = Blade::render('<x-ui.label for="a">L</x-ui.label>');

        $this->assertStringContainsString('<label', $html);
        $this->assertStringContainsString('for="a"', $html);
        $this->assertStringContainsString('tw:mb-2', $html);

        foreach (['tw:text-[', 'tw:font-', 'tw:inline-block'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html,
                '`.form-label` của Bootstrap không đặt thuộc tính này, component cũng không được đặt');
        }
    }
}
