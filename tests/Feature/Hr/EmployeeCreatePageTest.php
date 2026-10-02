<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guard trang `hr/employees/create` sau đợt 2026-10-01 (cùng lỗi `@error` với trang sửa). */
final class EmployeeCreatePageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/hr/employees/create.blade.php';

    public function test_view_sach_bootstrap_va_khong_co_directive_trong_thuoc_tinh(): void
    {
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('<script', $view);
        // Blade KHÔNG biên dịch directive trong thuộc tính thẻ component — xem BladeDirectiveTrongThuocTinhTest.
        $this->assertDoesNotMatchRegularExpression('/<x-[\w.\-]+[^>]*@error\s*\(/s', $view);

        preg_match_all('/(?<![-:\w])class="([^"]*)"/', $view, $khop);
        $token = [];
        foreach ($khop[1] as $ds) {
            foreach (preg_split('/\s+/', trim($ds)) ?: [] as $l) {
                if ($l !== '' && ! str_starts_with($l, 'tw:') && $l !== 'bi' && ! str_starts_with($l, 'bi-')) {
                    $token[$l] = true;
                }
            }
        }
        $this->assertSame([], array_keys($token), 'view còn lớp không phải `tw:*`');
    }

    public function test_bieu_mau_trang_va_trang_thai_mac_dinh(): void
    {
        $admin = $this->gieo();

        $html = (string) $this->actingAs($admin)->get('/nhan-su/employees/create')->assertOk()->getContent();

        $this->assertStringContainsString('Thêm nhân viên', $html);
        $this->assertStringContainsString('Phòng Guard', $html);
        $this->assertStringContainsString('Chức vụ Guard', $html);
        // Mặc định "Đang hoạt động" được chọn (bản cũ: `old('is_active', '1') == '1'`).
        $this->assertSame(1, substr_count($html, 'selected'));
        $this->assertStringNotContainsString('@error', $html, 'directive không được lọt ra HTML');
        $this->assertStringNotContainsString('is-invalid', $html);
    }

    public function test_sau_loi_validation_o_nhap_co_vien_do_va_giu_old_input(): void
    {
        $admin = $this->gieo();

        $this->actingAs($admin)->post('/nhan-su/employees', [
            'name' => '', 'email' => 'sai', 'phone_number' => '0901234567', 'password' => '123',
        ]);
        $html = (string) $this->actingAs($admin)->get('/nhan-su/employees/create')->assertOk()->getContent();

        $this->assertStringContainsString('tw:border-[#dc3545]', $html, 'ô lỗi phải có viền đỏ');
        $this->assertStringContainsString('tw:[background-image:var(--ui-invalid-icon)]', $html);
        // Controller khai thông báo tiếng Việt riêng cho `name`; các ô khác dùng bản mặc định.
        $this->assertStringContainsString('Vui lòng nhập họ tên.', $html);
        $this->assertStringContainsString('The email field must be a valid email address.', $html);
        // old() giữ nguyên những gì người dùng đã gõ.
        $this->assertStringContainsString('value="0901234567"', $html);
        $this->assertStringContainsString('value="sai"', $html);
        $this->assertStringNotContainsString('invalid-feedback', $html);
    }

    private function gieo(): object
    {
        DB::table('departments')->insert(['id' => 809100, 'name' => 'Phòng Guard', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('positions')->insert(['id' => 809200, 'name' => 'Chức vụ Guard', 'created_at' => now(), 'updated_at' => now()]);

        return $this->userWithRole('admin', ['id' => 809001, 'name' => 'QT Guard', 'email' => 'qt-new-guard@example.test']);
    }
}
