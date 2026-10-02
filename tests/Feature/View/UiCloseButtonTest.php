<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\View\Components\Ui\CloseButton;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Canh giữ <x-ui.close-button> — thay `.btn-close` / `.btn-close-white`.
 *
 * Giá trị chép từ bootstrap@5.3.3 và kiểm bằng bản sao trong trang thật (modal/offcanvas
 * được mở để đo) ở 4 trạng thái. Điều dễ rơi nhất khi sửa tay: `:focus` (Bootstrap dùng
 * `:focus`, KHÔNG phải `:focus-visible` — bấm chuột xong nút vẫn sáng hẳn và có vòng
 * xanh) và đệm theo ngữ cảnh (`.modal-header .btn-close` thay đệm nền chứ không cộng thêm).
 */
final class UiCloseButtonTest extends TestCase
{
    #[Test]
    public function mac_dinh_la_button_type_button_co_moc_va_dam_nen(): void
    {
        $html = Blade::render('<x-ui.close-button data-bs-dismiss="modal" />');

        $this->assertStringStartsWith('<button type="button" data-ego-close', trim($html));
        $this->assertStringContainsString('data-bs-dismiss="modal"', $html);
        $this->assertStringContainsString('tw:p-[0.25em]', $html);
        $this->assertStringContainsString('tw:[background-image:var(--ui-close-icon)]', $html);
        $this->assertStringNotContainsString('btn-close', $html);
    }

    #[Test]
    public function giu_du_trang_thai_hover_focus_disabled(): void
    {
        $html = Blade::render('<x-ui.close-button />');

        foreach ([
            'tw:opacity-50', 'tw:hover:opacity-75',
            'tw:focus:outline-none', 'tw:focus:shadow-[0_0_0_4px_rgba(13,110,253,0.25)]', 'tw:focus:opacity-100',
            'tw:disabled:pointer-events-none', 'tw:disabled:select-none', 'tw:disabled:opacity-25',
        ] as $class) {
            $this->assertStringContainsString($class, $html, "Rơi mất class trạng thái: {$class}");
        }
        $this->assertStringNotContainsString('focus-visible', $html, 'Bootstrap dùng :focus cho nút đóng');
    }

    /** Đệm ngữ cảnh THAY đệm nền: không được có cả `tw:p-2` lẫn `tw:p-[0.25em]`. */
    #[Test]
    public function ngu_canh_thay_dem_nen(): void
    {
        foreach (['modal', 'offcanvas'] as $in) {
            $html = Blade::render('<x-ui.close-button in="'.$in.'" />');
            $this->assertStringContainsString('tw:p-2 tw:-my-2 tw:-mr-2 tw:ml-auto', $html, $in);
            $this->assertStringNotContainsString('tw:p-[0.25em]', $html, $in);
        }

        $alert = Blade::render('<x-ui.close-button in="alert" />');
        $this->assertStringContainsString('tw:absolute tw:top-0 tw:right-0 tw:z-[2] tw:py-5 tw:px-4', $alert);
        $this->assertStringNotContainsString('tw:p-[0.25em]', $alert);
    }

    #[Test]
    public function white_lat_mau_bang_filter(): void
    {
        $html = Blade::render('<x-ui.close-button white />');

        $this->assertStringContainsString('tw:[filter:invert(1)_grayscale(100%)_brightness(200%)]', $html);
        $this->assertStringNotContainsString('filter', Blade::render('<x-ui.close-button />'));
    }

    /** Nơi gọi khai đệm/lề thì component nhường (cơ chế LopTienIch). */
    #[Test]
    public function nhuong_lop_cho_noi_goi(): void
    {
        $html = Blade::render('<x-ui.close-button class="tw:p-0 tw:m-auto tw:mr-2" />');

        $this->assertStringContainsString('tw:p-0', $html);
        $this->assertStringNotContainsString('tw:p-[0.25em]', $html);
        $this->assertStringContainsString('tw:m-auto tw:mr-2', $html);
    }

    #[Test]
    public function ngu_canh_la_bi_tu_choi(): void
    {
        $this->expectException(\Illuminate\View\ViewException::class);
        $this->expectExceptionMessage('in="popover" không được phép');
        Blade::render('<x-ui.close-button in="popover" />');
    }

    #[Test]
    public function danh_sach_ngu_canh_dong(): void
    {
        $this->assertSame(['', 'modal', 'offcanvas', 'alert'], CloseButton::contexts());
    }
}
