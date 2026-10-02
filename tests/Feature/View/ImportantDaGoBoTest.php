<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Support\Ui\LopTienIch;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Canh kết quả đợt gỡ `!important` khỏi lớp Tailwind trong view.
 *
 * Đợt quy đổi Bootstrap→Tailwind từng rải 4.231 dấu `!`. Gỡ sạch rồi đo lại
 * 11 trang × 6 khổ màn chỉ thấy 339/2.636.550 giá trị lệch trên 60 phần tử —
 * truy ra đúng 3 nguyên nhân gốc, 2 trong đó là lỗi thật (xem
 * TAILWIND_IMPORTANT_CHO_PHEP trong Tests\TestCase).
 *
 * Test này giữ cho kết quả đó không lặng lẽ trôi ngược.
 */
final class ImportantDaGoBoTest extends TestCase
{
    /** Nơi gọi tự đặt `mb-*` thì component KHÔNG thêm `tw:mb-2` nữa. */
    public function test_label_nhuong_le_cho_noi_goi(): void
    {
        $co = Blade::render('<x-ui.label class="small text-muted tw:mb-1">X</x-ui.label>');

        $this->assertStringContainsString('tw:mb-1', $co);
        $this->assertStringNotContainsString('tw:mb-2', $co, 'Hai lớp mb-* trên cùng phần tử thì '
            .'thứ tự tệp CSS quyết định, không phải thứ tự HTML — trước đây phải chữa bằng tw:mb-1!.');
        $this->assertStringNotContainsString('!', $co);
    }

    /** Không ai đặt `mb-*` thì vẫn giữ lề mặc định của `.form-label`. */
    public function test_label_van_giu_le_mac_dinh(): void
    {
        $this->assertStringContainsString('tw:mb-2', Blade::render('<x-ui.label>X</x-ui.label>'));
    }

    /** `mb-*` chỉ ở breakpoint thì lề gốc vẫn cần, không được nhường. */
    public function test_label_giu_le_khi_noi_goi_chi_doi_o_breakpoint(): void
    {
        $this->assertStringContainsString(
            'tw:mb-2',
            Blade::render('<x-ui.label class="tw:md:mb-0">X</x-ui.label>')
        );
    }

    /**
     * Nút đóng toast: không còn `m-auto` của Bootstrap, và giữ ĐÚNG thứ đang hiển thị.
     *
     * `m-auto` của Bootstrap là `margin:auto!important`, nhưng Tailwind nạp SAU
     * Bootstrap nên `tw:mr-2!` vẫn thắng — đo trên HEAD ra auto/8px/auto/auto.
     * Bỏ được dấu `!` vì trong chính tệp CSS Tailwind, `mr-2` đứng sau `m-auto`.
     * Nên bản quy đổi đúng là `tw:m-auto tw:mr-2`, và `m-auto` của Bootstrap phải
     * biến mất — để lại là nó thắng ngược.
     */
    public function test_nut_dong_toast_giu_dung_le_dang_hien_thi(): void
    {
        $nguon = file_get_contents(resource_path('views/layouts/app.blade.php'));

        // Từ đợt btn-close (2026-09-06) nút là <x-ui.close-button>; lề vẫn do nơi gọi khai.
        $this->assertStringContainsString('<x-ui.close-button white class="tw:m-auto tw:mr-2"', $nguon);
        $this->assertStringNotContainsString(' m-auto"', str_replace('tw:m-auto', '', $nguon));
    }

    /**
     * Không phần tử nào mang utility Bootstrap đè cùng thuộc tính với utility Tailwind.
     *
     * MỌI utility Bootstrap đều `!important` (`.p-md-4{padding:1.5rem!important}`).
     * Chừng nào lớp Tailwind còn `!` thì hai bên hoà, Tailwind nạp sau nên thắng —
     * và lớp Bootstrap kia đã chết mà không ai biết. Bỏ dấu `!` là nó sống dậy,
     * giao diện đổi. Đo 11 trang thấy đúng kiểu này ở `card-body tw:p-4 p-lg-4`.
     * Quét tĩnh ra 25 chỗ nữa; tất cả đã gỡ lớp Bootstrap đã chết.
     */
    public function test_khong_con_utility_bootstrap_doi_dau_voi_tailwind(): void
    {
        $viPham = [];

        foreach ($this->dsBlade() as $duongDan) {
            // `(?<![-:\w])` để KHÔNG vơ luôn `x-bind:class="…"` / `:class="…"` của Alpine —
            // trong đó là biểu thức JS, không phải danh sách lớp.
            preg_match_all('/(?<![-:\\w])class="([^"{}]*)"/', file_get_contents($duongDan), $ms);

            foreach ($ms[1] as $danhSach) {
                $lop = preg_split('/\s+/', trim($danhSach)) ?: [];
                $bs = array_filter($lop, static fn ($c) => $c !== '' && ! str_starts_with($c, 'tw:'));
                $tw = array_filter($lop, static fn ($c) => str_starts_with($c, 'tw:'));

                foreach ($bs as $b) {
                    foreach ($tw as $t) {
                        if (LopTienIch::chungThuocTinh($b, $t) !== []) {
                            $viPham[] = str_replace(resource_path('views').'/', '', $duongDan)." — {$b} đối đầu {$t}";
                        }
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($viPham)), 'Utility Bootstrap là '
            .'`!important`; để cạnh utility Tailwind cùng thuộc tính thì Bootstrap thắng. '
            .'Gỡ lớp Bootstrap, đừng thêm lại dấu `!`.');
    }

    /**
     * Lớp `.alert` của Bootstrap đã biến mất hoàn toàn — và phải ở nguyên như vậy.
     *
     * 161 hộp cảnh báo nay dùng `<x-ui.alert>`. Bốn luật CSS từng nhắm `.alert`
     * (hai trong `<style>` của view, hai trong `public/css`) đã xoá, giá trị chép
     * thẳng vào lớp Tailwind của từng nơi gọi — đo đối chứng khớp tuyệt đối.
     * Thêm lại một `class="alert"` là luật cũ không còn ở đó để đỡ nữa.
     */
    public function test_khong_con_lop_alert_va_card_cua_bootstrap(): void
    {
        $viPham = [];

        foreach ($this->dsBlade() as $duongDan) {
            // `(?<![-:\w])` để KHÔNG vơ luôn `x-bind:class="…"` / `:class="…"` của Alpine —
            // trong đó là biểu thức JS, không phải danh sách lớp.
            preg_match_all('/(?<![-:\\w])class="([^"]*)"/', file_get_contents($duongDan), $ms);

            foreach ($ms[1] as $danhSach) {
                if (array_intersect(['alert', 'card', 'card-body', 'card-header', 'card-footer'],
                    preg_split('/\s+/', trim($danhSach)) ?: []) !== []) {
                    $viPham[] = str_replace(resource_path('views').'/', '', $duongDan);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($viPham)),
            'Dùng <x-ui.alert> / <x-ui.card*> — các luật CSS đỡ cho .alert đã xoá, còn luật của .card chỉ bắt móc data-ego-card.');
    }

    /** Toàn bộ view: dấu `!` chỉ còn ở đúng danh sách đã đo và giải thích được. */
    public function test_view_khong_con_dau_important_ngoai_danh_sach(): void
    {
        foreach ($this->dsBlade() as $duongDan) {
            $this->assertKhongLamDungImportant(
                file_get_contents($duongDan),
                str_replace(resource_path('views').'/', '', $duongDan)
            );
        }
    }
}
