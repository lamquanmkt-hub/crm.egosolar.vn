<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

/**
 * Giá trị đã gộp `old()` cho biểu mẫu sửa nhân viên.
 *
 * Thay khối `@php` khai `$currentRole` và ba lượt `number_format(...)` viết thẳng trong thuộc tính
 * `value` của ô lương.
 */
final readonly class EmployeeFormValues
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phoneNumber,
        /** Tên vai trò đang chọn (`''` nếu chưa có). */
        public string $currentRole,
        /**
         * Ba trường này để `mixed`, KHÔNG ép `string` — view so sánh LỎNG (`==`) và ngữ nghĩa phụ
         * thuộc KIỂU: `null == 0` là **true** còn `'' == 0` là **false** (PHP 8). Ép chuỗi làm mất
         * `selected` của ô "Ngưng hoạt động" khi nhân viên chưa có giá trị — đã đo thấy trên dump.
         */
        public mixed $departmentId,
        public mixed $positionId,
        public mixed $isActive,
        /** Lương đã định dạng `15.000.000` để điền vào ô text; `''` khi null. */
        public string $officialSalary,
        public string $probationSalary,
        public string $internshipSalary,
    ) {}
}
