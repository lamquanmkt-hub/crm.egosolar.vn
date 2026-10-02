<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\View\Presenters\Hr\EmployeeEditPresenter;
use Tests\TestCase;

/** `EmployeeEditPresenter` — assert giá trị thật. */
final class EmployeeEditPresenterTest extends TestCase
{
    private function nhanVien(array $attrs = []): object
    {
        return (object) array_merge([
            'id' => 7, 'name' => 'Kỹ sư A', 'email' => 'a@example.test', 'phone_number' => '0900',
            'department_id' => 3, 'position_id' => 5, 'is_active' => 1,
            'official_salary' => 15000000, 'probation_salary' => null, 'internship_salary' => 4000000.9,
            'roles' => collect([(object) ['name' => 'technical']]),
        ], $attrs);
    }

    public function test_lay_gia_tri_tu_nhan_vien(): void
    {
        $v = (new EmployeeEditPresenter)->viewData($this->nhanVien(), [])['formValues'];

        $this->assertSame('Kỹ sư A', $v->name);
        $this->assertSame('technical', $v->currentRole, 'vai trò đầu tiên của nhân viên');
        $this->assertSame(3, $v->departmentId);
        $this->assertSame('15.000.000', $v->officialSalary);
        $this->assertSame('', $v->probationSalary, 'lương null ra chuỗi rỗng');
        // number_format làm TRÒN: 4000000.9 -> 4.000.001 (bản cũ cũng vậy).
        $this->assertSame('4.000.001', $v->internshipSalary);
    }

    public function test_old_input_thang(): void
    {
        $v = (new EmployeeEditPresenter)->viewData($this->nhanVien(), [
            'name' => 'Tên vừa gõ', 'role' => 'hr', 'official_salary' => '99999', 'department_id' => '9',
        ])['formValues'];

        $this->assertSame('Tên vừa gõ', $v->name);
        $this->assertSame('hr', $v->currentRole);
        $this->assertSame('99999', $v->officialSalary, 'giữ nguyên văn, không định dạng lại');
        $this->assertSame('9', $v->departmentId);
    }

    public function test_nhan_vien_chua_co_vai_tro_va_luong(): void
    {
        $v = (new EmployeeEditPresenter)->viewData($this->nhanVien([
            'roles' => collect(), 'official_salary' => null, 'internship_salary' => null,
            'phone_number' => null,
        ]), [])['formValues'];

        $this->assertSame('', $v->currentRole);
        $this->assertSame('', $v->officialSalary);
        $this->assertSame('', $v->phoneNumber);
    }

    /**
     * ⚠️ Ba trường so sánh LỎNG phải giữ KIỂU, không ép chuỗi.
     *
     * View viết `$formValues->isActive == 0`. Với nhân viên chưa có giá trị thì bản cũ truyền `null`
     * và `null == 0` là TRUE (ô "Ngưng hoạt động" được chọn). Ép sang `''` thì `'' == 0` là FALSE —
     * đã đo thấy mất đúng một `selected` trên dump trước khi sửa.
     */
    public function test_gia_tri_null_giu_nguyen_kieu_de_so_sanh_long_khong_doi(): void
    {
        $v = (new EmployeeEditPresenter)->viewData($this->nhanVien([
            'is_active' => null, 'department_id' => null, 'position_id' => null,
        ]), [])['formValues'];

        $this->assertNull($v->isActive);
        $this->assertTrue($v->isActive == 0, 'null == 0 phải vẫn đúng để ô "Ngưng hoạt động" được chọn');
        $this->assertNull($v->departmentId);
        $this->assertNull($v->positionId);
    }
}
