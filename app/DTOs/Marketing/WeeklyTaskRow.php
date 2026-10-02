<?php

declare(strict_types=1);

namespace App\DTOs\Marketing;

/**
 * Một dòng công việc tuần đã định dạng sẵn cho bảng báo cáo.
 *
 * Tồn tại vì view trước đây có một khối PHP NGAY TRONG `@forelse` chỉ để hạ chữ thường
 * priority/status rồi `match()` ra lớp badge — lặp cho mỗi dòng.
 */
final readonly class WeeklyTaskRow
{
    /**
     * @param  string  $priorityBadge  lớp nền badge (còn là lớp Bootstrap, xem ghi chú presenter)
     * @param  string  $priorityText  chữ in hoa hiển thị, rỗng thì `N/A`
     * @param  string  $startDateText  ngày `Y-m-d`; trống thì `-` (gạch NGANG, không phải `—`)
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $priorityBadge,
        public string $priorityText,
        public string $category,
        public string $assignee,
        public string $startDateText,
        public string $dueDateText,
        public string $statusBadge,
        public string $statusText,
    ) {}
}
