<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

/**
 * Một dòng file hồ sơ nhân viên đã định dạng sẵn cho view.
 *
 * Tồn tại vì trang chi tiết nhân viên trước đây gọi `number_format()` và `date()` ngay trong
 * Blade cho từng dòng; gom vào đây để view chỉ in và để test khẳng định được giá trị thật.
 */
final readonly class EmployeeFileRow
{
    /**
     * @param  int  $id  id bảng hr_employee_files, dùng cho route tải và xoá
     * @param  string  $name  tên gốc người dùng đặt khi upload; có thể rỗng nếu bản ghi cũ thiếu
     * @param  string  $typeLabel  loại file, rỗng thì hiển thị "Hồ sơ khác" như bản cũ
     * @param  string  $sizeText  dung lượng dạng `200.0 KB` — dấu CHẤM thập phân, theo bản cũ
     * @param  string  $uploadedAtText  thời điểm upload dạng `d/m/Y H:i`
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $typeLabel,
        public string $sizeText,
        public string $uploadedAtText,
    ) {}
}
