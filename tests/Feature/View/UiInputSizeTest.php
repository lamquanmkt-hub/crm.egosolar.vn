<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\View\Components\Ui\Input;
use Tests\TestCase;

/**
 * Cỡ `sm` / `lg` phải mang `min-height` đúng như Bootstrap, cỡ mặc định thì KHÔNG.
 *
 * ## Vì sao dễ sai
 * Đọc lướt sẽ tưởng `.form-control` cũng có `min-height`. Tra bản
 * bootstrap@5.3.3 đã nạp trên trang (2026-09-04):
 *
 *     .form-control        (không có min-height)
 *     .form-control-sm     min-height: calc(1.5em + .5rem  + calc(var(--bs-border-width) * 2))
 *     .form-control-lg     min-height: calc(1.5em + 1rem   + calc(var(--bs-border-width) * 2))
 *     textarea.form-control    min-height: calc(1.5em + .75rem + …)
 *     textarea.form-control-sm min-height: calc(1.5em + .5rem  + …)
 *
 * Bỏ sót: đo trên hr/attendance/settings thấy 3 ô `-sm` tụt min-height 31px -> 0px.
 * Thừa (cộng cả công thức cỡ mặc định vào textarea `-sm`) thì hai khai báo chọi nhau.
 *
 * ## Lớp đổi cỡ KHÔNG được giữ nguyên khi chuyển
 * `class="form-control form-control-sm"` mà chỉ bỏ `form-control` là hỏng:
 * Bootstrap nạp ở `<head>` còn app.css nạp SAU, nên utility cỡ mặc định của
 * component đè lên `.form-control-sm` và ô nhỏ phình về cỡ thường. Phải đổi thành
 * `size="sm"`.
 */
final class UiInputSizeTest extends TestCase
{
    public function test_co_mac_dinh_khong_co_min_height(): void
    {
        $this->assertStringNotContainsString('min-height', $this->classes(''),
            'Bootstrap không đặt min-height cho `.form-control`');
    }

    public function test_co_sm_va_lg_co_min_height_dang_cong_thuc(): void
    {
        $this->assertStringContainsString(
            'tw:[min-height:calc(1.5em_+_.5rem_+_calc(var(--bs-border-width,1px)_*_2))]',
            $this->classes('sm'));

        $this->assertStringContainsString(
            'tw:[min-height:calc(1.5em_+_1rem_+_calc(var(--bs-border-width,1px)_*_2))]',
            $this->classes('lg'));
    }

    /** `1.5em` co theo cỡ chữ — quy ra pixel là sai ở mọi cỡ chữ khác. */
    public function test_min_height_giu_dang_cong_thuc_khong_quy_ra_pixel(): void
    {
        foreach (['sm', 'lg'] as $size) {
            $this->assertStringNotContainsString('min-height:3', $this->classes($size),
                "cỡ $size: min-height bị ghi cứng ra pixel");
        }
    }

    public function test_textarea_co_mac_dinh_dung_cong_thuc_rieng(): void
    {
        $input = new Input(as: 'textarea');
        $input->withAttributes([]);

        $this->assertStringContainsString('.75rem', $input->textareaClasses());
    }

    /** textarea cỡ sm chỉ được có MỘT khai báo min-height. */
    public function test_textarea_co_sm_khong_cong_don_hai_min_height(): void
    {
        $input = new Input(size: 'sm', as: 'textarea');
        $input->withAttributes([]);
        $classes = $input->textareaClasses();

        $this->assertSame(1, substr_count($classes, 'min-height'),
            "textarea `-sm` phải có đúng 1 khai báo min-height:\n".$classes);
        $this->assertStringNotContainsString('.75rem', $classes);
    }

    /** Đệm/lề nút chọn tệp cũng đổi theo cỡ. */
    public function test_nut_chon_tep_doi_dem_theo_co(): void
    {
        $this->assertStringContainsString('tw:file:px-3', $this->classes('', 'file'));
        $this->assertStringContainsString('tw:file:px-2', $this->classes('sm', 'file'));
        $this->assertStringContainsString('tw:file:px-4', $this->classes('lg', 'file'));
    }

    private function classes(string $size, ?string $type = null): string
    {
        $input = new Input(size: $size);
        $input->withAttributes($type === null ? [] : ['type' => $type]);

        return $input->classes();
    }
}
