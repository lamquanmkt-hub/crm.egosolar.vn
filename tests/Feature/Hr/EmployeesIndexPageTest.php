<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\DTOs\Hr\EmployeeCard;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trang danh sách nhân viên sau khi dời 11 khối `@php` sang {@see \App\Services\Hr\EmployeeDirectoryService}
 * và gộp ba khối card thành partial (2026-09-07).
 */
final class EmployeesIndexPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/hr/employees/index.blade.php';

    private const CARD_PARTIAL = 'resources/views/hr/employees/partials/person-card.blade.php';

    public function test_trang_in_gia_tri_da_tinh_tu_service(): void
    {
        $actor = $this->seedDirectory();

        $html = $this->actingAs($actor)->get('/nhan-su/employees')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="hr-person-card is-leader">', $html, 'ban giám đốc và trưởng phòng');
        $this->assertStringContainsString('Marketing, Marketing &amp; Sales', $html);
        $this->assertStringContainsString('Kế toán &amp; Kho', $html);
        $this->assertStringContainsString('<span>Lương thực tập</span>', $html);
        $this->assertStringContainsString('3.000.000 đ', $html);
        $this->assertStringContainsString('Chưa gán phòng ban', $html);
        $this->assertStringContainsString('src="'.asset('storage/avatars/ky.jpg').'"', $html);
        $this->assertStringContainsString('<div class="hr-avatar-fallback" style="display:none;">TK</div>', $html, 'có ảnh: chữ tắt ẩn sẵn cho onerror');
        $this->assertStringContainsString('<div class="hr-avatar-fallback">HP</div>', $html, 'không ảnh: chữ tắt hiện');
        $this->assertStringContainsString('1 trưởng nhóm chính', $html);
        $this->assertStringContainsString('<i class="bi bi-pause-circle"></i> Đã ngừng hợp tác', $html);

        // Lọc chỉ còn ban giám đốc: không có nhóm phòng ban nào ngoài "Ban giám đốc" → không hiện thẻ rỗng (hành vi cũ).
        $html = $this->actingAs($actor)->get('/nhan-su/employees?role=admin')->assertOk()->getContent();
        $this->assertStringNotContainsString('Chưa có nhân sự phù hợp bộ lọc', $html);
        $html = $this->actingAs($actor)->get('/nhan-su/employees?keyword=zzz')->assertOk()->getContent();
        $this->assertStringContainsString('Chưa có nhân sự phù hợp bộ lọc', $html);
    }

    /** View và partial chỉ in: không `@php`; partial chỉ đọc thuộc tính có thật của EmployeeCard. */
    public function test_view_khong_tu_tinh(): void
    {
        $view = (string) file_get_contents(base_path(self::VIEW));
        $partial = (string) file_get_contents(base_path(self::CARD_PARTIAL));
        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('@php', $partial);

        preg_match_all('/\$card->([a-zA-Z]+)/', $partial, $m);
        $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(EmployeeCard::class))->getProperties());
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), 'partial đọc thuộc tính không có');
    }

    private function seedDirectory(): User
    {
        $now = '2026-09-01 08:00:00';
        DB::table('departments')->insert([
            ['id' => 998001, 'name' => 'Kỹ thuật', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998002, 'name' => 'Marketing', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998004, 'name' => 'Ban giám đốc', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('positions')->insert([
            ['id' => 998101, 'name' => 'Giám đốc điều hành', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998102, 'name' => 'Trưởng phòng Kỹ thuật', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998103, 'name' => 'Nhân viên Marketing', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998105, 'name' => 'Thực tập sinh Marketing', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998107, 'name' => 'Thủ kho', 'created_at' => $now, 'updated_at' => $now],
        ]);
        $actor = $this->userWithRole(Role::Admin->value, [
            'name' => 'Nguoi Dung Canh', 'email' => 'nguoi.dung@example.test', 'is_active' => 1, 'department_id' => 998004, 'position_id' => 998101,
        ]);
        foreach ([
            ['Trần Văn Kỹ', 998001, 998102, 1, 'avatars/ky.jpg', 25000000, null],
            ['Lê Thị Marketing', 998002, 998103, 1, null, 15000000, null],
            ['Ngô Thực Tập', 998002, 998105, 1, null, 20000000, 3000000],
            ['Đỗ Kho', null, 998107, 1, null, 9000000, null],
            ['Hoàng Không Phòng', null, null, 1, null, null, null],
            ['Mai Nghỉ Việc', 998001, null, 0, null, 12000000, null],
        ] as $i => [$name, $dept, $pos, $active, $avatar, $official, $intern]) {
            User::factory()->create([
                'name' => $name, 'email' => "u{$i}@example.test", 'department_id' => $dept, 'position_id' => $pos, 'is_active' => $active,
                'avatar' => $avatar, 'official_salary' => $official, 'internship_salary' => $intern,
            ]);
        }

        return $actor;
    }
}
