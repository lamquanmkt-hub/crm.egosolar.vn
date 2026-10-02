<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

/**
 * Một dòng bảng chấm công của chính nhân viên.
 *
 * Tồn tại vì view trước đây có một khối `@php` NGAY TRONG `@forelse` để tìm đơn xin sửa đang chờ
 * và tính cờ "đang làm hôm nay" — chạy lại cho từng dòng.
 *
 * Giữ nguyên model `record`: view đọc 12 thuộc tính của nó, gồm cả cast Carbon (`work_date`) và
 * accessor hiển thị (`status_label`). Tiền lệ cùng cách: `App\DTOs\Projects\WarehouseItemRow`.
 */
final readonly class AttendanceRecordRow
{
    /**
     * @param  object  $record  một dòng `attendance_records`
     * @param  string  $statusClass  hậu tố lớp CSS của viên trạng thái (success/primary/warning/…)
     * @param  object|null  $pendingCorrection  đơn xin sửa đang chờ duyệt; null nếu không có
     * @param  bool  $isActiveToday  bản công của HÔM NAY và chưa check-out
     */
    public function __construct(
        public object $record,
        public string $statusClass,
        public ?object $pendingCorrection,
        public bool $isActiveToday,
    ) {}
}
