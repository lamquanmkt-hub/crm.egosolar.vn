<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\DTOs\Hr\EmployeeFormValues;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guard + hành vi trang `hr/employees/edit` sau đợt 2026-10-01. */
final class EmployeeEditPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/hr/employees/edit.blade.php';

    public function test_view_khong_con_php_va_khong_con_lop_bootstrap(): void
    {
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('number_format', $view);
        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('<script', $view);
        // Directive trong thuộc tính thẻ component: Blade không biên dịch (xem BladeDirectiveTrongThuocTinhTest).
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

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));
        preg_match_all('/\$formValues->([a-zA-Z0-9]+)/', $view, $hit);

        $props = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass(EmployeeFormValues::class))->getProperties()
        );

        $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)));
    }

    public function test_trang_in_dung_gia_tri_dang_co(): void
    {
        [$admin, $nv] = $this->gieo();

        $html = (string) $this->actingAs($admin)->get("/nhan-su/employees/{$nv->id}/edit")->assertOk()->getContent();

        $this->assertStringContainsString('value="Kỹ sư Guard"', $html);
        $this->assertStringContainsString('value="0900000009"', $html);
        // Lương từ DB được định dạng `15.000.000` (bản cũ dùng number_format(…, 0, ',', '.')).
        $this->assertStringContainsString('value="15.000.000"', $html);
        $this->assertStringContainsString('value="12.000.000"', $html);
        $this->assertSame(4, substr_count($html, 'selected'), 'vai trò + phòng ban + chức vụ + trạng thái');
        $this->assertStringNotContainsString('@error', $html, 'directive không được lọt ra HTML');
    }

    public function test_loi_validation_bat_trang_thai_do_cua_o_nhap(): void
    {
        [$admin, $nv] = $this->gieo();

        $this->actingAs($admin)->put("/nhan-su/employees/{$nv->id}", ['name' => '', 'email' => 'sai']);
        $html = (string) $this->actingAs($admin)->get("/nhan-su/employees/{$nv->id}/edit")->assertOk()->getContent();

        // `<x-ui.input invalid>` phát đúng lớp viền đỏ + chỗ cho icon cảnh báo của Bootstrap.
        $this->assertStringContainsString('tw:border-[#dc3545]', $html);
        $this->assertStringContainsString('tw:[background-image:var(--ui-invalid-icon)]', $html);
        // Thông báo lỗi hiện bằng component riêng, không phụ thuộc luật anh em của Bootstrap.
        $this->assertStringContainsString('tw:text-[#dc3545]', $html);
        $this->assertStringContainsString('The name field is required.', $html);
        $this->assertStringNotContainsString('invalid-feedback', $html);
        $this->assertStringNotContainsString('is-invalid', $html);
    }

    public function test_old_input_thang_gia_tri_trong_db(): void
    {
        [$admin, $nv] = $this->gieo();

        $this->actingAs($admin)->put("/nhan-su/employees/{$nv->id}", [
            'name' => '', 'email' => 'sai', 'official_salary' => '99999',
        ]);
        $html = (string) $this->actingAs($admin)->get("/nhan-su/employees/{$nv->id}/edit")->assertOk()->getContent();

        // Người dùng vừa gõ gì thì giữ NGUYÊN VĂN, không định dạng lại.
        $this->assertStringContainsString('value="99999"', $html);
        $this->assertStringNotContainsString('value="15.000.000"', $html);
    }

    /** @return array{0: User, 1: User} */
    private function gieo(): array
    {
        $admin = $this->userWithRole('admin', ['id' => 806001, 'name' => 'QT Guard', 'email' => 'qt-emp-guard@example.test']);
        DB::table('departments')->insert(['id' => 806100, 'name' => 'Phòng Guard', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('positions')->insert(['id' => 806200, 'name' => 'Chức vụ Guard', 'created_at' => now(), 'updated_at' => now()]);

        $nv = $this->userWithRole('technical', ['id' => 806002, 'name' => 'Kỹ sư Guard', 'email' => 'ks-emp-guard@example.test']);
        User::whereKey($nv->id)->update([
            'phone_number' => '0900000009', 'department_id' => 806100, 'position_id' => 806200,
            'is_active' => 1, 'official_salary' => 15000000, 'probation_salary' => 12000000,
        ]);

        return [$admin, $nv->refresh()];
    }
}
