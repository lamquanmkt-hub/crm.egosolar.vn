<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

/**
 * Một dòng đơn tăng ca trên `hr/overtime/index`.
 *
 * Thay khối `@php` nằm TRONG `@forelse` — khối đó gọi `auth()->user()` lại cho TỪNG dòng rồi so
 * `approver_id` để quyết định có hiện hai form duyệt/từ chối hay không.
 */
final readonly class OvertimeRow
{
    public function __construct(
        public int $id,
        public string $userName,
        public string $departmentName,
        public string $dateText,
        public string $timeText,
        public string $hoursText,
        public string $approverName,
        public string $reasonText,
        public string $statusLabel,
        /** Tông huy hiệu: `warning|success|danger|secondary` của model, đổi sang lớp ở view. */
        public string $statusTone,
        public string $approvalNote,
        /** Đang chờ duyệt VÀ người đang đăng nhập được phép duyệt dòng này. */
        public bool $canApprove,
    ) {}
}
