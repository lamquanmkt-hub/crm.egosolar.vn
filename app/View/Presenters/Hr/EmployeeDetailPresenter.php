<?php

declare(strict_types=1);

namespace App\View\Presenters\Hr;

use App\DTOs\Hr\EmployeeFileRow;
use App\Models\User;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị mọi giá trị cho trang chi tiết nhân viên.
 *
 * Thay khối `@php` 92 dòng của `hr/employees/show.blade.php` — khối đó tự `use DB` và chạy hai
 * truy vấn ngay trong Blade. Hai truy vấn nay do controller thực hiện và truyền vào đây, nên lớp
 * này thuần: không Facade, không query, không `request()`.
 *
 * Giữ nguyên hành vi bản cũ, kể cả hai chỗ khác quy ước chung của repo (xem chú thích tại chỗ):
 * dung lượng file dùng dấu chấm thập phân, và ngày không đọc được ra `01/01/1970`.
 */
final class EmployeeDetailPresenter
{
    /** Lựa chọn cho dropdown "Loại file" ở popup upload, giữ đúng thứ tự bản cũ. */
    private const FILE_TYPES = ['CV', 'CCCD/CMND', 'Hợp đồng lao động', 'Bằng cấp', 'Quyết định', 'Ảnh hồ sơ', 'Cam kết', 'File khác'];

    /** Khoá đọc ra `<input type="date">`; cần giá trị thô `Y-m-d`, không định dạng. */
    private const DATE_INPUT_KEYS = [
        'hire_date', 'official_date', 'probation_start_date', 'probation_end_date',
        'contract_start_date', 'contract_end_date', 'birth_date', 'id_card_date',
    ];

    /** Khoá đọc ra `<input>`/`<textarea>` text; trống phải là chuỗi rỗng, không phải `—`. */
    private const FORM_TEXT_KEYS = [
        'employee_code', 'contract_type', 'gender', 'id_card', 'id_card_place',
        'bank_name', 'bank_account', 'tax_code', 'insurance_number',
        'emergency_contact_phone', 'emergency_contact_name', 'address', 'hr_note',
    ];

    /**
     * @param  User  $employee  đã eager load department, position, roles, avatar
     * @param  object|null  $profile  một dòng `hr_employee_profiles`, null khi bảng/dòng chưa có
     * @param  iterable<object>  $files  các dòng `hr_employee_files`, controller sắp theo id giảm dần
     * @return array{employeeId: int, name: string, chipCode: string, chipStatus: string, chipRole: string, text: array<string, string>, infoRows: list<array{0: string, 1: string}>, salaryText: array<string, string>, formValue: array<string, string>, dateValue: array<string, string>, fileTypes: list<string>, fileRows: list<EmployeeFileRow>, fileCount: int}
     */
    public function viewData(User $employee, ?object $profile, iterable $files): array
    {
        // Hồ sơ mở rộng ghi đè thuộc tính cùng tên của user, đúng thứ tự merge của bản cũ.
        $emp = (object) array_merge((array) $employee->toArray(), (array) ($profile ?: []));

        $text = [
            'email' => $this->value($emp, 'email'),
            'phone' => $this->value($emp, 'phone'),
            'department' => $this->value($emp, 'department_name', $this->value($emp, 'department')),
            'position' => $this->value($emp, 'position_name', $this->value($emp, 'position')),
            'address' => $this->value($emp, 'address'),
            'hr_note' => $this->value($emp, 'hr_note'),
        ];

        $formValue = [];
        foreach (self::FORM_TEXT_KEYS as $key) {
            $formValue[$key] = $this->value($emp, $key, '');
        }

        $dateValue = [];
        foreach (self::DATE_INPUT_KEYS as $key) {
            $dateValue[$key] = $this->rawDate($emp, $key);
        }

        $salaryText = [];
        foreach (['official_salary', 'probation_salary', 'intern_salary'] as $key) {
            $salaryText[$key] = $this->money($emp, $key);
        }

        $fileRows = [];
        $fileCount = 0;
        foreach ($files as $file) {
            $fileCount++;
            $fileRows[] = new EmployeeFileRow(
                id: (int) ($file->id ?? 0),
                name: (string) ($file->original_name ?? ''),
                typeLabel: (string) ($file->file_type ?: 'Hồ sơ khác'),
                // Bản cũ dùng number_format($x, 1) với dấu phân cách mặc định tiếng Anh
                // (`200.0 KB`), KHÁC DisplayFormat::number. Giữ nguyên để HTML không đổi.
                sizeText: number_format(((float) ($file->size_bytes ?? 0)) / 1024, 1).' KB',
                uploadedAtText: date('d/m/Y H:i', (int) strtotime((string) ($file->created_at ?? ''))),
            );
        }

        return [
            'employeeId' => (int) $employee->id,
            'name' => $this->value($emp, 'full_name', $this->value($emp, 'name', 'Nhân viên')),
            'chipCode' => $this->value($emp, 'employee_code', 'Chưa có mã NV'),
            'chipStatus' => $this->value($emp, 'status', 'Đang hoạt động'),
            'chipRole' => $this->value($emp, 'role', 'Chưa gán vai trò'),
            'text' => $text,
            'infoRows' => [
                ['Email', $text['email']],
                ['Số điện thoại', $text['phone']],
                ['Phòng ban', $text['department']],
                ['Chức vụ', $text['position']],
                ['Ngày nhận việc', $this->date($emp, 'hire_date')],
                ['Ngày chính thức', $this->date($emp, 'official_date')],
                ['Loại hợp đồng', $this->value($emp, 'contract_type')],
                ['Hạn hợp đồng', $this->date($emp, 'contract_end_date')],
                ['Ngày sinh', $this->date($emp, 'birth_date')],
                ['CCCD/CMND', $this->value($emp, 'id_card')],
                ['Ngân hàng', $this->value($emp, 'bank_name')],
                ['Số tài khoản', $this->value($emp, 'bank_account')],
                ['Mã số thuế', $this->value($emp, 'tax_code')],
                ['Số BHXH', $this->value($emp, 'insurance_number')],
                ['Liên hệ khẩn cấp', $this->value($emp, 'emergency_contact_name')],
                ['SĐT khẩn cấp', $this->value($emp, 'emergency_contact_phone')],
            ],
            'salaryText' => $salaryText,
            'formValue' => $formValue,
            'dateValue' => $dateValue,
            'fileTypes' => self::FILE_TYPES,
            'fileRows' => $fileRows,
            'fileCount' => $fileCount,
        ];
    }

    /** Đọc một khoá và ép về chuỗi hiển thị được; mảng/đối tượng thì dò khoá tên thường gặp. */
    private function value(object $emp, string $key, string $default = '—'): string
    {
        return $this->safeText(data_get($emp, $key), $default);
    }

    /**
     * Ép giá trị bất kỳ về chuỗi hiển thị.
     *
     * Quan hệ Eloquent sau `toArray()` là mảng lồng (department, position…), nên phải dò khoá tên
     * thay vì in `Array`. Nhánh loại bỏ chuỗi dạng ngày tránh ghép `created_at` vào nhãn.
     */
    private function safeText(mixed $value, string $default): string
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $nameKeys = ['full_name', 'name', 'title', 'department_name', 'position_name', 'label'];

        if (is_array($value)) {
            foreach ($nameKeys as $key) {
                if (! empty($value[$key]) && is_scalar($value[$key])) {
                    return (string) $value[$key];
                }
            }

            $parts = [];
            foreach ($value as $item) {
                if (is_scalar($item) && $item !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $item)) {
                    $parts[] = (string) $item;
                }
            }

            return count($parts) ? implode(', ', array_slice($parts, 0, 2)) : $default;
        }

        if (is_object($value)) {
            foreach ($nameKeys as $key) {
                if (! empty($value->{$key}) && is_scalar($value->{$key})) {
                    return (string) $value->{$key};
                }
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return $default;
        }

        return (string) $value;
    }

    /**
     * Ngày hiển thị `d/m/Y`.
     *
     * Giữ `strtotime` của bản cũ thay vì DisplayFormat::date: `strtotime` trả false với chuỗi lạ
     * và `date()` biến false thành `01/01/1970`, còn DisplayFormat::date trả lại nguyên chuỗi.
     * Đổi sang cách kia là đổi hành vi, nên để nguyên và ghi lại ở đây.
     */
    private function date(object $emp, string $key): string
    {
        $value = data_get($emp, $key);
        if ($value === null || $value === '' || is_array($value) || is_object($value)) {
            return '—';
        }

        return date('d/m/Y', (int) strtotime((string) $value));
    }

    /** Giá trị thô cho `<input type="date">`; trống hoặc không phải vô hướng thì để rỗng. */
    private function rawDate(object $emp, string $key): string
    {
        $value = data_get($emp, $key);

        return $value !== null && $value !== '' && ! is_array($value) && ! is_object($value)
            ? (string) $value
            : '';
    }

    /** Tiền `1.234.567 đ`; không phải số thì `—` như bản cũ (khác DisplayFormat::money trả `0 đ`). */
    private function money(object $emp, string $key): string
    {
        $value = data_get($emp, $key);
        if ($value === null || $value === '' || is_array($value) || is_object($value) || ! is_numeric($value)) {
            return '—';
        }

        return DisplayFormat::money($value);
    }
}
