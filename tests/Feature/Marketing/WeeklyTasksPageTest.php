<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\DTOs\Marketing\WeeklyTaskRow;
use App\View\Presenters\Marketing\WeeklyTaskListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard cho `marketing/reports/weekly_tasks.blade.php` sau khi dời 2 khối `@php` (2026-09-25).
 *
 * Khối đầu tự `use DB` và chạy HAI truy vấn chết (`getDatabaseName()` và `count()` trên
 * `weekly_tasks`) — không biến nào trong đó được view dùng. Khối thứ hai nằm trong `@forelse`,
 * chạy lại `match()` cho từng dòng. Guard này chặn cả hai quay lại.
 */
final class WeeklyTasksPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/marketing/reports/weekly_tasks.blade.php';

    /** Khoá view() do controller cấp ngoài presenter. */
    private const CONTROLLER_KEYS = ['tasks', 'total', 'done', 'doing', 'overdue', 'priorityCount', 'categories'];

    private const LOOP_AND_BLADE_VARIABLES = ['row', 'c', 'loop', 'errors', 'slot', 'attributes', 'component'];

    public function test_view_khong_con_php_va_khong_tu_truy_van(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);
        $this->assertStringNotContainsString('DB::', $source, 'truy vấn không được nằm trong Blade');
        $this->assertStringNotContainsString('use Illuminate', $source, 'view không được có câu use');

        $data = (new WeeklyTaskListPresenter)->viewData([]);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));
        $this->assertSame(
            [],
            array_values(array_diff(array_unique($m[1]), $provided)),
            'biến view dùng mà presenter/controller không cấp'
        );
    }

    public function test_view_chi_doc_thuoc_tinh_that_cua_dto(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        preg_match_all('/\$row->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(
            fn (\ReflectionProperty $p) => $p->getName(),
            (new \ReflectionClass(WeeklyTaskRow::class))->getProperties()
        );

        $this->assertNotSame([], $m[1], 'không thấy chỗ nào đọc $row-> — guard mất tác dụng');
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)));
    }

    public function test_trang_that_in_dung_badge_cho_moi_muc_uu_tien_va_trang_thai(): void
    {
        $admin = $this->userWithRole('admin');
        DB::table('weekly_tasks')->delete();
        $now = '2026-01-01 00:00:00';
        DB::table('weekly_tasks')->insert([
            ['title' => 'Việc cao', 'priority' => 'high', 'category' => 'Content', 'assignee' => 'An', 'start_date' => '2026-01-05', 'due_date' => '2026-01-09', 'progress' => 10, 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Việc vừa', 'priority' => 'medium', 'category' => 'Digital', 'assignee' => 'Bình', 'start_date' => null, 'due_date' => null, 'progress' => 50, 'status' => 'doing', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Việc thấp', 'priority' => 'low', 'category' => null, 'assignee' => null, 'start_date' => '2026-01-07', 'due_date' => '2026-01-11', 'progress' => 100, 'status' => 'done', 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Việc quá hạn', 'priority' => 'medium', 'category' => 'Event', 'assignee' => 'Chi', 'start_date' => null, 'due_date' => null, 'progress' => 0, 'status' => 'overdue', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $response = $this->actingAs($admin)->get('/marketing/reports/weekly-tasks');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('badge bg-danger wt-badge">HIGH', $html);
        $this->assertStringContainsString('badge bg-warning text-dark wt-badge">MEDIUM', $html);
        $this->assertStringContainsString('badge bg-info text-dark wt-badge">LOW', $html);
        $this->assertStringContainsString('badge bg-success wt-badge">DONE', $html);
        $this->assertStringContainsString('badge bg-primary wt-badge">DOING', $html);
        // 'overdue' KHÔNG có nhánh riêng trong bản cũ -> rơi vào bg-secondary. Giữ nguyên.
        $this->assertStringContainsString('badge bg-secondary wt-badge">OVERDUE', $html);

        $this->assertStringContainsString('2026-01-05', $html);
        $this->assertStringContainsString('Hiển thị: 4 dòng', $html);
    }

    public function test_o_trong_hien_gach_ngang_chu_khong_phai_gach_dai(): void
    {
        $admin = $this->userWithRole('admin');
        DB::table('weekly_tasks')->delete();
        DB::table('weekly_tasks')->insert([
            'title' => 'Thiếu dữ liệu', 'priority' => 'low', 'category' => null, 'assignee' => null,
            'start_date' => null, 'due_date' => null, 'progress' => 0, 'status' => 'pending',
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/marketing/reports/weekly-tasks');

        $response->assertOk();
        $html = (string) $response->getContent();

        // Trang này dùng gạch NGANG '-'; DisplayFormat::date trả gạch DÀI '—' nên presenter
        // giữ guard riêng. Nếu ai đổi sang DisplayFormat cho cả nhánh trống, test này đỏ.
        $this->assertStringContainsString('wt-nowrap">-</td>', $html);
        $this->assertStringNotContainsString('wt-nowrap">—</td>', $html);
    }

    public function test_danh_sach_rong_thi_hien_thong_bao(): void
    {
        $admin = $this->userWithRole('admin');
        DB::table('weekly_tasks')->delete();

        $response = $this->actingAs($admin)->get('/marketing/reports/weekly-tasks');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('Chưa có dữ liệu.', $html);
        $this->assertStringContainsString('Hiển thị: 0 dòng', $html);
    }
}
