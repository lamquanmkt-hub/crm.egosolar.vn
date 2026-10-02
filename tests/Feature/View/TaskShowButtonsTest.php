<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canh giữ đợt chuyển nút của trang chi tiết công việc (29 nút).
 *
 * ## Không dùng !important
 * Trang này có `<style>` nằm trong @section('content') tức TRONG <body>, nạp SAU bundle
 * Vite — nên CSS của trang THẮNG utility, ngược hẳn trang lịch biên tập (style ở
 *
 * @section('styles'), trong <head>). Hệ quả: cái gì trang cần khác chuẩn thì khai bằng
 * chính CSS của trang (`.wrk-btn-wide`), đừng dùng utility rồi đè bằng `!`.
 *
 * ⚠️ ĐÃ THỬ chuyển khối <style> lên @section('styles') để utility thắng tự nhiên: HỎNG
 * 440 chỗ, vì khi đó nó rơi ra TRƯỚC lớp branding runtime. Đừng thử lại.
 */
final class TaskShowButtonsTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/tasks/show.blade.php';

    /** Không còn class nút Bootstrap, trừ <summary>/<label> vốn không phải nút. */
    public function test_khong_con_class_nut_bootstrap(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        preg_match_all('/<(a|button)\b[^>]*class="([^"]*\bbtn btn-[^"]*)"/', $source, $m);

        $this->assertSame([], $m[2], 'Còn thẻ <a>/<button> mang class Bootstrap chưa chuyển');
    }

    /** Không dùng !important ở bất kỳ đâu trong view. */
    public function test_khong_dung_important(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertKhongLamDungImportant($source);
    }

    /**
     * Nút submit rộng dùng lớp .wrk-btn-wide, và lớp đó phải nằm SAU .wrk-btn.
     *
     * Cùng độ đặc hiệu nên thứ tự quyết định: `.wrk-btn{padding:7px 13px}` là shorthand,
     * đứng sau sẽ ghi đè longhand padding-left/right. Đặt nhầm thứ tự thì nút hẹp lại 22px
     * mà nhìn không ra.
     */
    public function test_wrk_btn_wide_dat_sau_wrk_btn(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        $posBtn = strpos($source, '.wrk-btn{min-height:36px;');
        $posWide = strpos($source, '.wrk-btn-wide{');

        $this->assertNotFalse($posBtn);
        $this->assertNotFalse($posWide);
        $this->assertGreaterThan($posBtn, $posWide, '.wrk-btn-wide phải nằm SAU .wrk-btn');
    }

    /**
     * Nút trong dock di động phải khai line-height.
     *
     * Trước đây nó thừa hưởng `line-height:1.5` từ `.btn` của Bootstrap; bỏ `.btn` thì mất,
     * và nút cao thêm 6px ở màn ≤760px — chỉ lộ ra khi đo ở khổ 390px.
     */
    public function test_dock_khai_line_height(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertMatchesRegularExpression(
            '/\.wrk-mobile-dock \.wrk-dock-btn\{[^}]*line-height:1\.5/',
            $source
        );
    }

    /** Trang render được ở mọi trạng thái công việc. */
    public function test_render_moi_trang_thai(): void
    {
        $user = $this->userWithRole(Role::Admin->value);
        $now = now();

        foreach (['new', 'in_progress', 'submitted', 'revision', 'approved'] as $i => $status) {
            $id = 910000 + $i;
            DB::table('tasks')->insert([
                'id' => $id,
                'title' => 'Canh test',
                'status' => $status,
                'assignee_id' => $user->id,
                'requester_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $res = $this->actingAs($user)->get('/chat/tasks/'.$id);

            $res->assertOk();
            $this->assertStringContainsString('tw:inline-block', (string) $res->getContent());
        }
    }
}
