<?php

declare(strict_types=1);

namespace App\View\Presenters\Hr;

use App\DTOs\Hr\EmployeeFormValues;
use Illuminate\Support\Arr;

/**
 * Chuẩn bị biểu mẫu `hr/employees/edit`.
 *
 * Thay khối `@php` khai `$currentRole` (lấy vai trò đầu tiên của nhân viên) và ba lượt
 * `number_format()` viết thẳng trong thuộc tính `value` của ô lương.
 *
 * Lớp này thuần: `old()` đi vào bằng tham số `$oldInput` (`(array) request()->old()` từ controller).
 */
final class EmployeeEditPresenter
{
    /**
     * @param  array<string, mixed>  $oldInput
     * @return array{formValues: EmployeeFormValues}
     */
    public function viewData(object $employee, array $oldInput): array
    {
        $cu = fn (string $khoa, mixed $macDinh): string => $this->cu($oldInput, $khoa, $macDinh);

        return [
            'formValues' => new EmployeeFormValues(
                name: $cu('name', $employee->name ?? ''),
                email: $cu('email', $employee->email ?? ''),
                phoneNumber: $cu('phone_number', $employee->phone_number ?? ''),
                // Bản cũ: `old('role', $employee->roles->first()->name ?? '')`.
                currentRole: $cu('role', $employee->roles->first()->name ?? ''),
                // KHÔNG ép chuỗi: xem PHPDoc của `EmployeeFormValues` (null == 0 vs '' == 0).
                // Và dùng `Arr::has` thay `??`: xem PHPDoc của `cu()` về ngữ nghĩa `old()`.
                departmentId: Arr::has($oldInput, 'department_id')
                    ? Arr::get($oldInput, 'department_id')
                    : ($employee->department_id ?? null),
                positionId: Arr::has($oldInput, 'position_id')
                    ? Arr::get($oldInput, 'position_id')
                    : ($employee->position_id ?? null),
                isActive: Arr::has($oldInput, 'is_active')
                    ? Arr::get($oldInput, 'is_active')
                    : ($employee->is_active ?? null),
                officialSalary: $this->tien($oldInput, 'official_salary', $employee->official_salary ?? null),
                probationSalary: $this->tien($oldInput, 'probation_salary', $employee->probation_salary ?? null),
                internshipSalary: $this->tien($oldInput, 'internship_salary', $employee->internship_salary ?? null),
            ),
        ];
    }

    /**
     * Gộp `old()` ĐÚNG ngữ nghĩa Laravel: nếu khoá CÓ trong old input thì lấy giá trị đó, **kể cả
     * khi nó là null**; chỉ khi khoá KHÔNG có mới rơi về giá trị mặc định.
     *
     * ⚠️ Dùng `?? $macDinh` là SAI: middleware `ConvertEmptyStringsToNull` biến ô bỏ trống thành
     * null, nên sau khi validation lỗi, `old('platform')` trả **null** và bản cũ in ra RỖNG — còn
     * `??` lại rơi về giá trị trong DB và tự chọn lại lựa chọn cũ. Đo được: trang lỗi có thêm một
     * `<option selected>` không đúng.
     */
    private function cu(array $oldInput, string $khoa, mixed $macDinh): string
    {
        if (Arr::has($oldInput, $khoa)) {
            return (string) (Arr::get($oldInput, $khoa) ?? '');
        }

        return (string) ($macDinh ?? '');
    }

    /**
     * Ô lương: `old()` thắng nguyên văn (người dùng vừa gõ gì thì giữ nguyên thế), còn giá trị từ DB
     * mới định dạng `15.000.000`.
     *
     * Giữ đúng `number_format($x, 0, ',', '.')` của bản cũ — KHÔNG dùng `DisplayFormat::money()` vì
     * hàm đó thêm ` đ` và ô này là `<input type="text">` người dùng gõ lại được.
     *
     * @param  array<string, mixed>  $oldInput
     */
    private function tien(array $oldInput, string $khoa, mixed $giaTri): string
    {
        // Khoá CÓ trong old input thì giữ nguyên văn, kể cả rỗng (xem PHPDoc của `cu()`).
        if (Arr::has($oldInput, $khoa)) {
            return (string) (Arr::get($oldInput, $khoa) ?? '');
        }

        // Bản cũ: `isset($x) && $x !== null ? number_format((float) $x, 0, ',', '.') : ''`.
        return $giaTri === null ? '' : number_format((float) $giaTri, 0, ',', '.');
    }
}
