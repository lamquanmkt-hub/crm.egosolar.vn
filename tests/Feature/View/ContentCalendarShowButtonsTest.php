<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canh giữ trang chi tiết lịch biên tập (15 nút đã chuyển, 10 `btn-ego` giữ nguyên).
 *
 * ## Lỗi production tìm ra khi dựng dữ liệu để đo
 * View gọi `route(...feedback.update, $ch->id)` với MỘT tham số trong khi route cần HAI
 * (`{id}` và `{feedbackId}`). Hệ quả: trang trả 500 mỗi khi một góp ý CÓ TRẢ LỜI. Không
 * lộ ra ở dữ liệu rỗng — đúng loại lỗi mà smoke test tự cảnh báo là nó không bắt được.
 *
 * ## Khoảng trống che phủ đã biết
 * 3 nút mang lớp `cc-mini` nằm ở nhánh không render với dữ liệu mẫu, nên phép so ảnh
 * không chạm tới. Chúng được canh bằng test mã nguồn bên dưới.
 */
final class ContentCalendarShowButtonsTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/marketing/reports/content_calendar_show.blade.php';

    /** Chỉ còn `btn-ego` mang class Bootstrap; đó là biến thể riêng của trang. */
    public function test_chi_con_btn_ego(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        preg_match_all('/class="([^"]*\bbtn btn-[^"]*)"/', $source, $m);

        // Đợt btn 2026-09-06 chuyển nốt cả btn-ego → phải rỗng (foreach rỗng làm test risky).
        $this->assertSame([], $m[1], 'Còn nút Bootstrap chưa chuyển');
    }

    /**
     * Nút dùng `size="none"` phải tự khai cỡ chữ.
     *
     * `.cc-btn`/`.cc-btn-sm`/`.cc-mini` khai bo góc + đậm + padding nhưng KHÔNG khai
     * font-size; trước đây 16px đến từ `.btn` của Bootstrap. Quên bù thì chữ tụt xuống
     * 14px trên 10 nút — đã đo và thấy.
     */
    public function test_size_none_phai_khai_co_chu(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        preg_match_all('/<x-ui\.button\b[^>]*>/', $source, $m);
        $none = array_values(array_filter($m[0], fn (string $t): bool => str_contains($t, 'size="none"')));

        $this->assertNotEmpty($none);

        foreach ($none as $tag) {
            $this->assertStringContainsString('tw:text-[16px]/[24px]', $tag, "Nút size=none thiếu cỡ chữ: {$tag}");
        }
    }

    /** Không dùng !important. */
    public function test_khong_dung_important(): void
    {
        $this->assertKhongLamDungImportant((string) file_get_contents(resource_path(self::VIEW)));
    }

    /**
     * Lời gọi route feedback phải đủ HAI tham số.
     *
     * Đây là bản canh cho lỗi 500 đã sửa: route
     * `marketing/reports/content-calendar/{id}/feedback/{feedbackId}` cần cả hai.
     */
    public function test_route_feedback_du_hai_tham_so(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        preg_match_all("/route\('marketing\.reports\.content-calendar\.feedback\.(update|delete)',\s*([^)]*)\)/", $source, $m);

        $this->assertNotEmpty($m[2]);

        foreach ($m[2] as $args) {
            $this->assertStringContainsString('feedbackId', $args, "Lời gọi route thiếu feedbackId: {$args}");
        }
    }

    /** Trang render được khi góp ý CÓ trả lời — nhánh từng làm 500. */
    public function test_render_khi_gop_y_co_tra_loi(): void
    {
        $user = $this->userWithRole(Role::Admin->value);
        $now = now();

        DB::table('content_calendars')->insert([
            'id' => 920002, 'publish_date' => '2026-09-03', 'platform' => 'facebook',
            'content_type' => 'post', 'title' => 'Canh test', 'created_by' => $user->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('content_feedbacks')->insert([
            'id' => 930003, 'content_calendar_id' => 920002, 'user_id' => $user->id,
            'message' => 'Gop y', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('content_feedbacks')->insert([
            'id' => 930004, 'content_calendar_id' => 920002, 'parent_id' => 930003,
            'user_id' => $user->id, 'message' => 'Tra loi', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($user)->get('/marketing/reports/content-calendar/920002')->assertOk();
    }
}
