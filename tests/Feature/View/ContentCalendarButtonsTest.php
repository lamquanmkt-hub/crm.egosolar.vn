<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Canh giữ đợt chuyển nút của trang lịch biên tập (29 nút).
 *
 * ## Cái gì CỐ Ý không chuyển
 * - `btn-ego` (3 nút): biến thể riêng của trang, tự khai đủ nền/viền/chữ/đậm/bo/padding
 *   trong <style> của chính view. Không thuộc component dùng chung.
 * - Cụm `btn-group cc-view-switch` (3 nút): đây là WIDGET, không phải nút đơn. Nó phụ
 *   thuộc ba quy tắc khác nhau bám `.btn` — `.btn-group>.btn` (position/flex của
 *   Bootstrap), `.cc-view-switch .btn` (bo góc, viền) và `.cc-view-switch .btn.active`
 *   (màu trạng thái đang chọn). Gỡ `.btn` khỏi chúng làm hỏng cả ba, đã đo và thấy.
 * - `btn-close` (3 nút): thành phần khác của Bootstrap.
 */
final class ContentCalendarButtonsTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/marketing/reports/content_calendar.blade.php';

    /** Chỉ còn đúng các nút cố ý giữ lại mang class Bootstrap. */
    public function test_chi_con_nut_co_y_giu_lai(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        preg_match_all('/class="([^"]*\bbtn btn-[^"]*)"/', $source, $m);
        // Đợt btn 2026-09-06 chuyển nốt cả nút "giữ lại" (btn-ego, cc-view-btn) → phải rỗng.
        // Trước đây foreach trên danh sách rỗng → test không assert gì (PHPUnit báo risky).
        $this->assertSame([], $m[1], 'Còn nút Bootstrap chưa chuyển');
    }

    /** Trang render được và có nút dựng từ component. */
    public function test_trang_render_duoc(): void
    {
        $res = $this->actingAs($this->userWithRole(Role::Admin->value))
            ->get('/marketing/reports/content-calendar');

        $res->assertOk();
        $this->assertStringContainsString('tw:inline-block', (string) $res->getContent());
    }

    /**
     * Nút nằm trong input-group phải giữ position/z-index.
     *
     * Bootstrap đặt `.input-group .btn{position:relative;z-index:2}`. Bỏ class `.btn` là
     * mất cả hai, làm viền nút xoá bị input bên cạnh đè lên — sai lệch 0 pixel về kích
     * thước nên rất khó thấy.
     */
    public function test_nut_trong_input_group_giu_position(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertMatchesRegularExpression('/cc-clear[^"]*"|tw:relative tw:z-\[2\][^"]*cc-clear/', $source);
        $this->assertStringContainsString('tw:relative tw:z-[2]', $source);
    }

    /**
     * Nhóm lọc nhanh phải có trạng thái "đang chọn".
     *
     * Thêm 2026-09-03 theo phản hồi người dùng. Trước đó nhóm này KHÔNG có trạng thái nào:
     * bấm xong không biết đang lọc theo mốc nào. Đây là THÊM MỚI, không phải sửa hồi quy —
     * đã đo và xác nhận 4 trạng thái CSS của nút khớp y hệt bản trước khi chuyển sang
     * component; JS chưa bao giờ gắn class `active` cho nhóm này.
     *
     * Độ đặc hiệu 0,3,0 (`.cc-filters .cc-btn-quick.active`) là cố ý: để thắng cả utility
     * hover (0,2,0), giống cách `.cc-view-switch .btn.active` vốn thắng `.btn:hover`.
     */
    public function test_loc_nhanh_co_trang_thai_dang_chon(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertStringContainsString('.cc-filters .cc-btn-quick.active{', $source);
        $this->assertStringContainsString('markQuickActive', $source);
        $this->assertStringContainsString("b.classList.toggle('active', b === btn)", $source);
    }

    /**
     * Nút có CSS viền riêng dùng `:border-base="false"`, KHÔNG dùng `!important`.
     *
     * Lịch sử của chỗ này đáng đọc trước khi sửa:
     * 1. Bản đầu đè bằng `tw:border-[...]!` -> LỌT RA PRODUCTION một lỗi: `!important` áp
     *    cho MỌI trạng thái nên viền đứng im khi hover/nhấn, nút trông như "bấm không ăn".
     * 2. Bản vá thêm `!` cho từng trạng thái thì chạy đúng nhưng rườm rà, dễ quên một
     *    trạng thái, và vẫn là búa tạ.
     * 3. Bản hiện tại: component KHÔNG phát màu viền mặc định (`borderBase=false`), để CSS
     *    của trang lo trạng thái tĩnh; hover/active/focus vẫn do biến thể lo và tự thắng
     *    nhờ đặc hiệu hơn (0,2,0 so với 0,1,0) — đúng như Bootstrap vốn làm.
     */
    public function test_dung_border_base_thay_vi_important(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertKhongLamDungImportant($source);

        preg_match_all('/<x-ui\\.button\\b[^>]*>/', $source, $m);

        foreach (['cc-btn-quick', 'cc-mini-btn', 'cc-menu-btn', 'cc-clear'] as $cls) {
            $tags = array_values(array_filter($m[0], fn (string $t): bool => str_contains($t, $cls)));

            $this->assertNotEmpty($tags, "Không thấy nút nào mang lớp {$cls}");

            foreach ($tags as $t) {
                $this->assertStringContainsString(':border-base="false"', $t, "Nút {$cls} thiếu :border-base");
            }
        }
    }
}
