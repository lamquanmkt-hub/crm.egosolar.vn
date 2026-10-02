<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Models\User;
use App\Services\Sales\SalesManagerDirectory;
use App\Support\SchemaCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sidebar render ở MỌI trang, nên chi phí truy vấn của nó nhân với toàn bộ
 * lưu lượng. Nhóm test này khoá hai điều:
 *
 * 1. Số truy vấn KHÔNG tăng theo số user trong hệ thống (trước đây sidebar
 *    chạy `User::query()->get()` rồi gọi `getRoleNames()` cho từng user).
 * 2. Danh sách trưởng phòng sales vẫn đúng nội dung.
 */
final class SidebarDataTest extends TestCase
{
    use DatabaseTransactions;

    /** Thêm bao nhiêu user cũng không được làm tăng số truy vấn của trang. */
    public function test_page_query_count_does_not_scale_with_user_count(): void
    {
        $viewer = $this->userWithRole('admin');

        /*
         * Đo trên /workspace chứ không phải '/'.
         *
         * Từ khi có Workspace phòng ban, '/' chỉ còn là chuyển hướng
         * (WorkspaceController@root -> ego.workspace.index) nên trả 302 và KHÔNG
         * render sidebar — đo ở đó là đo rỗng. /workspace là trang chủ thật sau
         * đăng nhập, có sidebar, đúng thứ test này muốn khoá.
         */
        $home = '/workspace';

        // Làm nóng cache schema + view compile để phép đo chỉ còn truy vấn nghiệp vụ.
        $this->actingAs($viewer)->get($home);

        $baseline = $this->captureQueryCount(function () use ($viewer, $home): void {
            $this->actingAs($viewer)->get($home)->assertSuccessful();
        });

        User::factory()->count(30)->create();

        $afterManyUsers = $this->captureQueryCount(function () use ($viewer, $home): void {
            $this->actingAs($viewer)->get($home)->assertSuccessful();
        });

        $this->assertSame(
            $baseline,
            $afterManyUsers,
            sprintf(
                'Số truy vấn tăng khi thêm user (trước: %d, sau: %d) — N+1 theo số user đã quay lại.',
                $baseline,
                $afterManyUsers,
            ),
        );
    }

    /** Chỉ user mang role/chức danh/phòng ban trưởng phòng sales mới vào danh sách. */
    public function test_sales_manager_directory_matches_role_position_and_department(): void
    {
        Role::findOrCreate('sales_manager', 'web');

        $byRole = User::factory()->create(['name' => 'Quản lý theo role']);
        $byRole->assignRole('sales_manager');

        $positionId = DB::table('positions')->insertGetId([
            'name' => 'Trưởng phòng Sales',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $byPosition = User::factory()->create([
            'name' => 'Quản lý theo chức danh',
            'position_id' => $positionId,
        ]);

        $outsider = User::factory()->create(['name' => 'Nhân viên thường']);

        $names = app(SalesManagerDirectory::class)->options()->pluck('name')->all();

        $this->assertContains($byRole->name, $names);
        $this->assertContains($byPosition->name, $names);
        $this->assertNotContains($outsider->name, $names);
    }

    /**
     * Regression: user CÓ phòng ban không được làm hỏng danh sách.
     *
     * Bản cũ đọc `(string) $u->department` trong khi `department` là quan hệ
     * BelongsTo → ném lỗi, bị `try/catch` nuốt và trả danh sách RỖNG.
     */
    public function test_directory_still_works_when_user_has_department(): void
    {
        Role::findOrCreate('sales_manager', 'web');

        $departmentId = DB::table('departments')->insertGetId([
            'name' => 'Kinh doanh',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = User::factory()->create([
            'name' => 'Quản lý có phòng ban',
            'department_id' => $departmentId,
        ]);
        $manager->assignRole('sales_manager');

        $names = app(SalesManagerDirectory::class)->options()->pluck('name')->all();

        $this->assertContains($manager->name, $names);
    }

    /** Số truy vấn để dựng danh sách là hằng số, không phụ thuộc số user. */
    public function test_directory_uses_constant_number_of_queries(): void
    {
        Role::findOrCreate('sales_manager', 'web');
        SchemaCache::hasTable('users');

        User::factory()->count(20)->create();

        $queries = $this->captureQueryCount(static function (): void {
            (new SalesManagerDirectory)->options();
        });

        $this->assertLessThanOrEqual(
            4,
            $queries,
            'Dựng danh sách trưởng phòng sales phải là số truy vấn cố định (users + 3 quan hệ eager load).',
        );
    }

    /** Đếm số truy vấn chạy trong lúc thực thi callback. */
    private function captureQueryCount(callable $callback): int
    {
        $count = 0;

        DB::listen(static function () use (&$count): void {
            $count++;
        });

        $callback();

        return $count;
    }
}
