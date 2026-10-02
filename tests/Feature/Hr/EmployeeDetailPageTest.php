<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\DTOs\Hr\EmployeeFileRow;
use App\Models\User;
use App\View\Presenters\Hr\EmployeeDetailPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard cho `hr/employees/show.blade.php` sau khi dời khối `@php` 92 dòng (2026-09-24).
 *
 * Khối cũ tự `use DB` và chạy hai truy vấn ngay trong Blade; guard này chặn việc đó quay lại:
 * view không được có `@php`, mọi biến phải do controller/presenter cấp, `$file->x` phải là
 * thuộc tính DTO thật. Kèm một lượt trang thật assert giá trị in ra, không dừng ở assertOk().
 */
final class EmployeeDetailPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/hr/employees/show.blade.php';

    /** Khoá view() do controller cấp ngoài presenter. */
    private const CONTROLLER_KEYS = ['employee'];

    /** Biến vòng lặp và biến Blade tự có. */
    private const LOOP_AND_BLADE_VARIABLES = ['file', 'label', 'value', 'type', 'gender', 'loop', 'errors', 'slot', 'attributes', 'component'];

    public function test_view_khong_con_php_va_moi_bien_do_presenter_hoac_controller_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);
        $this->assertStringNotContainsString('DB::', $source);
        $this->assertStringNotContainsString('<style', $source, 'CSS của trang đã chuyển sang tw:* — đừng đưa <style> trở lại');
        $this->assertStringNotContainsString('class="emp-', $source, 'class emp-* là hệ CSS riêng đã bỏ');

        $data = (new EmployeeDetailPresenter)->viewData(new User, null, []);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));
        $this->assertSame(
            [],
            array_values(array_diff(array_unique($m[1]), $provided)),
            'biến view dùng mà presenter/controller không cấp'
        );
    }

    public function test_view_chi_doc_thuoc_tinh_that_cua_dto_dong_file(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        preg_match_all('/\$file->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(
            fn (\ReflectionProperty $p) => $p->getName(),
            (new \ReflectionClass(EmployeeFileRow::class))->getProperties()
        );

        $this->assertNotSame([], $m[1], 'không tìm thấy chỗ nào đọc $file-> — guard này mất tác dụng');
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)));
    }

    public function test_trang_that_in_dung_ho_so_mo_rong_va_dong_file(): void
    {
        $admin = $this->userWithRole('admin');
        $employee = User::factory()->create(['name' => 'Nhân viên Đủ', 'email' => 'full-page@example.test']);

        DB::table('hr_employee_profiles')->insert([
            'employee_id' => $employee->id,
            'employee_code' => 'NV-0042',
            'hire_date' => '2024-03-01',
            'contract_type' => 'Xác định thời hạn',
            'bank_name' => 'Vietcombank',
            'address' => 'Số 1, Đường A, Quận B',
            'created_at' => '2024-03-01 00:00:00',
            'updated_at' => '2024-03-01 00:00:00',
        ]);
        DB::table('hr_employee_files')->insert([
            'employee_id' => $employee->id,
            'file_type' => 'Hợp đồng lao động',
            'original_name' => 'hop-dong.pdf',
            'file_path' => 'hr/hop-dong.pdf',
            'size_bytes' => 204800,
            'created_at' => '2024-06-01 08:30:00',
            'updated_at' => '2024-06-01 08:30:00',
        ]);

        $response = $this->actingAs($admin)->get('/nhan-su/employees/'.$employee->id);

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('NV-0042', $html);
        $this->assertStringContainsString('01/03/2024', $html);          // hire_date đã định dạng
        $this->assertStringContainsString('Số 1, Đường A, Quận B', $html);
        $this->assertStringContainsString('value="2024-03-01"', $html);  // input type=date dùng giá trị thô
        $this->assertStringContainsString('hop-dong.pdf', $html);
        $this->assertStringContainsString('200.0 KB', $html);            // dấu chấm thập phân, như bản cũ
        $this->assertStringContainsString('01/06/2024 08:30', $html);
        $this->assertStringContainsString('1 file đã upload.', $html);
    }

    public function test_nhan_vien_chua_co_ho_so_mo_rong_van_mo_duoc_trang(): void
    {
        $admin = $this->userWithRole('admin');
        $employee = User::factory()->create(['name' => 'Nhân viên Trống', 'email' => 'bare-page@example.test']);

        $response = $this->actingAs($admin)->get('/nhan-su/employees/'.$employee->id);

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('Chưa có mã NV', $html);
        $this->assertStringContainsString('Chưa có file hồ sơ nào.', $html);
        $this->assertStringContainsString('0 file đã upload.', $html);
    }
}
