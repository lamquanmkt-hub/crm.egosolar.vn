<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Models\User;
use App\View\Presenters\Hr\EmployeeDetailPresenter;
use Tests\TestCase;

/**
 * EmployeeDetailPresenter thay khối `@php` 92 dòng của trang chi tiết nhân viên.
 *
 * Khẳng định giá trị thật, gồm cả hai chỗ presenter CỐ Ý khác quy ước chung của repo vì phải giữ
 * hành vi bản cũ: dung lượng file dùng dấu chấm thập phân, và ngày không đọc được ra 01/01/1970.
 */
final class EmployeeDetailPresenterTest extends TestCase
{
    private function employee(array $attributes = []): User
    {
        $user = new User;
        $user->forceFill(array_merge(['id' => 7, 'name' => 'Trần Văn A', 'email' => 'a@example.test'], $attributes));

        return $user;
    }

    public function test_hop_nhat_ho_so_mo_rong_ghi_de_thuoc_tinh_cung_ten(): void
    {
        $data = (new EmployeeDetailPresenter)->viewData(
            $this->employee(['employee_code' => 'CU-01']),
            (object) ['employee_code' => 'NV-0042', 'hire_date' => '2024-03-01'],
            [],
        );

        $this->assertSame(7, $data['employeeId']);
        $this->assertSame('Trần Văn A', $data['name']);
        // Dòng hr_employee_profiles thắng thuộc tính cùng tên của user, đúng thứ tự merge bản cũ.
        $this->assertSame('NV-0042', $data['chipCode']);
        $this->assertSame('NV-0042', $data['formValue']['employee_code']);
        $this->assertSame('2024-03-01', $data['dateValue']['hire_date']);
    }

    public function test_thieu_du_lieu_thi_hien_thi_gach_ngang_nhung_input_de_rong(): void
    {
        $data = (new EmployeeDetailPresenter)->viewData($this->employee(), null, []);

        $this->assertSame('—', $data['text']['phone']);
        $this->assertSame('—', $data['text']['address']);
        $this->assertSame('', $data['formValue']['address']);
        $this->assertSame('', $data['dateValue']['birth_date']);
        $this->assertSame('Chưa có mã NV', $data['chipCode']);
        $this->assertSame('Đang hoạt động', $data['chipStatus']);
        $this->assertSame('Chưa gán vai trò', $data['chipRole']);
        $this->assertSame(0, $data['fileCount']);
        $this->assertSame([], $data['fileRows']);
    }

    public function test_quan_he_eloquent_thanh_mang_van_ra_ten_chu_khong_ra_chu_array(): void
    {
        // toArray() biến quan hệ thành mảng lồng; presenter phải dò khoá tên.
        $employee = $this->employee();
        $employee->setRelation('department', null);
        $data = (new EmployeeDetailPresenter)->viewData(
            $employee,
            (object) ['department' => ['id' => 3, 'name' => 'Kỹ thuật'], 'position' => ['title' => 'Trưởng nhóm']],
            [],
        );

        $this->assertSame('Kỹ thuật', $data['text']['department']);
        $this->assertSame('Trưởng nhóm', $data['text']['position']);
    }

    public function test_tien_va_ngay_dinh_dang_dung_so_that(): void
    {
        $data = (new EmployeeDetailPresenter)->viewData(
            $this->employee(['official_salary' => 12500000, 'probation_salary' => 0, 'intern_salary' => 'chưa chốt']),
            (object) ['hire_date' => '2024-03-01', 'contract_end_date' => '2026-06-01'],
            [],
        );

        $this->assertSame('12.500.000 đ', $data['salaryText']['official_salary']);
        $this->assertSame('0 đ', $data['salaryText']['probation_salary']);
        // Không phải số thì gạch ngang, KHÁC DisplayFormat::money (trả '0 đ').
        $this->assertSame('—', $data['salaryText']['intern_salary']);

        $rows = collect($data['infoRows'])->mapWithKeys(fn (array $r) => [$r[0] => $r[1]])->all();
        $this->assertSame('01/03/2024', $rows['Ngày nhận việc']);
        $this->assertSame('01/06/2026', $rows['Hạn hợp đồng']);
        $this->assertSame('—', $rows['Ngày sinh']);
    }

    public function test_ngay_khong_doc_duoc_ra_1970_dung_nhu_ban_cu(): void
    {
        // Bản cũ dùng strtotime(): chuỗi lạ -> false -> date() ra 01/01/1970. Giữ nguyên có chủ ý;
        // nếu sau này đổi sang DisplayFormat::date thì test này phải đổi theo và ghi rõ trong commit.
        $data = (new EmployeeDetailPresenter)->viewData(
            $this->employee(),
            (object) ['hire_date' => 'không phải ngày'],
            [],
        );

        $rows = collect($data['infoRows'])->mapWithKeys(fn (array $r) => [$r[0] => $r[1]])->all();
        $this->assertSame('01/01/1970', $rows['Ngày nhận việc']);
    }

    public function test_dong_file_dinh_dang_dau_cham_thap_phan_va_thoi_diem_upload(): void
    {
        $data = (new EmployeeDetailPresenter)->viewData($this->employee(), null, [
            (object) ['id' => 11, 'original_name' => 'hop-dong.pdf', 'file_type' => 'Hợp đồng lao động', 'size_bytes' => 204800, 'created_at' => '2024-06-01 08:30:00'],
            (object) ['id' => 12, 'original_name' => null, 'file_type' => null, 'size_bytes' => null, 'created_at' => '2024-06-02 09:00:00'],
        ]);

        $this->assertSame(2, $data['fileCount']);
        [$first, $second] = $data['fileRows'];

        $this->assertSame(11, $first->id);
        $this->assertSame('hop-dong.pdf', $first->name);
        $this->assertSame('Hợp đồng lao động', $first->typeLabel);
        // Dấu CHẤM thập phân: bản cũ gọi number_format($x, 1) với phân cách mặc định tiếng Anh.
        $this->assertSame('200.0 KB', $first->sizeText);
        $this->assertSame('01/06/2024 08:30', $first->uploadedAtText);

        $this->assertSame('', $second->name);
        $this->assertSame('Hồ sơ khác', $second->typeLabel);
        $this->assertSame('0.0 KB', $second->sizeText);
    }
}
