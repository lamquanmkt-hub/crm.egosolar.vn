<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Ui\LopTienIch;
use PHPUnit\Framework\TestCase;

/** Bảng tra "lớp utility chi phối thuộc tính nào" — nền của cơ chế nhường lớp. */
final class LopTienIchTest extends TestCase
{
    public function test_doc_dung_canh_cua_lop_spacing(): void
    {
        $this->assertSame(['m-bottom'], LopTienIch::thuocTinh('tw:mb-4'));
        $this->assertSame(['p-top', 'p-bottom'], LopTienIch::thuocTinh('tw:py-2'));
        $this->assertSame(['p-top', 'p-right', 'p-bottom', 'p-left'], LopTienIch::thuocTinh('tw:p-4'));
        $this->assertSame(['m-right'], LopTienIch::thuocTinh('tw:mr-2'));
        $this->assertSame(['m-bottom'], LopTienIch::thuocTinh('tw:-mb-1'));
        $this->assertSame(['p-top', 'p-right', 'p-bottom', 'p-left'], LopTienIch::thuocTinh('p-md-4'));
    }

    /** Lớp mang `!` tự thắng — không ai phải nhường nó, và nó không đè ai. */
    public function test_lop_co_dau_than_khong_tinh(): void
    {
        $this->assertSame([], LopTienIch::thuocTinh('tw:py-6!'));
    }

    /**
     * Lớp chỉ đổi ở một breakpoint KHÔNG thay được lớp nền vô điều kiện.
     *
     * `<x-ui.label class="tw:md:mb-0">` muốn lề mặc định ở mobile và 0 từ md trở
     * lên. Nhường lớp nền ở đây là mất lề mobile.
     */
    public function test_lop_theo_breakpoint_khong_thay_the_lop_nen(): void
    {
        $this->assertSame([], LopTienIch::thuocTinh('tw:md:mb-0'));
        $this->assertSame('tw:mb-2', LopTienIch::nhuong('tw:mb-2', 'tw:md:mb-0'));
    }

    public function test_nhuong_khi_noi_goi_phu_tron_ven(): void
    {
        $this->assertSame('tw:p-4', LopTienIch::nhuong('tw:p-4 tw:mb-4', 'tw:mb-0'));
        $this->assertSame('', LopTienIch::nhuong('tw:mb-4', 'tw:mb-0'));
    }

    /**
     * Phủ MỘT PHẦN thì giữ nguyên lớp nền.
     *
     * `tw:py-2` chỉ đặt trên/dưới; bỏ `tw:p-4` đi là mất đệm trái/phải. Phần
     * chồng nhau để thứ tự CSS lo — đã đo `.tw\:p-4` đứng trước `.tw\:py-2`.
     */
    public function test_khong_nhuong_khi_chi_phu_mot_phan(): void
    {
        $this->assertSame('tw:p-4', LopTienIch::nhuong('tw:p-4', 'tw:py-2'));
    }

    public function test_khong_biet_thi_khong_bo(): void
    {
        $this->assertSame([], LopTienIch::thuocTinh('ego-alert-lạ'));
        $this->assertSame('tw:relative ego-x', LopTienIch::nhuong('tw:relative ego-x', 'ego-y'));
    }

    /**
     * `border-*` gánh ba thuộc tính khác nhau — gộp chung là nuốt nhầm màu viền.
     *
     * `<x-ui.alert class="tw:border-0">` chỉ được bỏ ĐỘ DÀY của lớp nền, không
     * được bỏ `tw:border-[#9eeaf9]` (màu) lẫn `tw:border-solid` (kiểu).
     */
    public function test_tach_dung_ba_vai_cua_border(): void
    {
        $this->assertSame(['border-width'], LopTienIch::thuocTinh('tw:border'));
        $this->assertSame(['border-width'], LopTienIch::thuocTinh('tw:border-0'));
        $this->assertSame(['border-width'], LopTienIch::thuocTinh('tw:border-2'));
        $this->assertSame(['border-style'], LopTienIch::thuocTinh('tw:border-solid'));
        $this->assertSame(['border-color'], LopTienIch::thuocTinh('tw:border-[#9eeaf9]'));

        $this->assertSame(
            'tw:border-solid tw:border-[#9eeaf9]',
            LopTienIch::nhuong('tw:border tw:border-solid tw:border-[#9eeaf9]', 'tw:border-0'),
        );
    }

    /** `tw:text-[...]` là cỡ chữ hay màu chữ — phân biệt bằng chính giá trị. */
    public function test_tach_co_chu_va_mau_chu(): void
    {
        $this->assertSame(['color'], LopTienIch::thuocTinh('tw:text-[#58151c]'));
        $this->assertSame(['color'], LopTienIch::thuocTinh('tw:text-[rgba(0,0,0,.5)]'));
        $this->assertSame(['font-size'], LopTienIch::thuocTinh('tw:text-[0.875em]'));
        $this->assertSame(['font-size'], LopTienIch::thuocTinh('tw:text-sm'));

        // Nơi gọi đặt CỠ chữ thì không được nuốt MÀU chữ của biến thể.
        $this->assertSame('tw:text-[#58151c]',
            LopTienIch::nhuong('tw:text-[#58151c]', 'tw:text-[0.875em]'));
    }

    public function test_biet_bong_do(): void
    {
        $this->assertSame(['box-shadow'], LopTienIch::thuocTinh('tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]'));
        $this->assertSame(['box-shadow'], LopTienIch::thuocTinh('tw:shadow-none'));
    }

    public function test_phat_hien_bootstrap_doi_dau_tailwind(): void
    {
        $this->assertSame(['p-top', 'p-right', 'p-bottom', 'p-left'],
            LopTienIch::chungThuocTinh('p-md-4', 'tw:p-4'));
        $this->assertSame(['p-left', 'p-right'], LopTienIch::chungThuocTinh('px-lg-4', 'tw:px-4'));
        $this->assertSame([], LopTienIch::chungThuocTinh('pt-4', 'tw:px-6'));
        $this->assertSame([], LopTienIch::chungThuocTinh('mb-0', 'tw:mt-4'));
    }

    public function test_tw_bg_khai_la_nen_de_component_nhuong_dung(): void
    {
        $this->assertSame(['background-color'], LopTienIch::thuocTinh('tw:bg-white'));
        $this->assertSame(['background-color'], LopTienIch::thuocTinh('tw:bg-[rgba(0,0,0,0.03)]'));

        // Nơi gọi đặt nền -> lớp nền của component phải bị bỏ, không để hai lớp
        // cùng thuộc tính đứng cạnh nhau rồi phụ thuộc thứ tự tệp CSS.
        $this->assertSame(
            'tw:py-2 tw:px-4',
            LopTienIch::nhuong('tw:py-2 tw:px-4 tw:bg-[rgba(33,37,41,0.03)]', 'tw:bg-white')
        );

        // Bản Bootstrap vẫn trả rỗng (nó `!important` nên tự thắng, không cần nhường).
        $this->assertSame([], LopTienIch::thuocTinh('bg-white'));
    }

    public function test_tw_align_khai_la_vertical_align(): void
    {
        $this->assertSame(['vertical-align'], LopTienIch::thuocTinh('tw:align-middle'));
        $this->assertSame(['vertical-align'], LopTienIch::thuocTinh('tw:align-top'));

        // Nơi gọi đặt căn dọc -> lớp căn dọc của component phải bị bỏ.
        $this->assertSame(
            'tw:w-full tw:mb-4',
            LopTienIch::nhuong('tw:w-full tw:mb-4 tw:align-top', 'tw:align-middle')
        );

        // Biến thể KHÔNG được tính: nó chỉ đổi ở một ngữ cảnh con nên không thay được lớp nền.
        $this->assertSame([], LopTienIch::thuocTinh('tw:[&>thead]:align-bottom'));
    }
}
