<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

/**
 * Giá trị đã sẵn sàng để in của một card nhân sự trên trang danh sách nhân viên.
 *
 * View cũ tính từng thứ này bằng closure trong `@php` cho ba khối card giống nhau;
 * nay {@see EmployeeDirectoryService::card()} dựng một lần, partial chỉ in.
 */
final readonly class EmployeeCard
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $email,
        public string $phoneText,
        public bool $active,
        public bool $isLeader,
        public ?string $avatarUrl,
        public string $initials,
        public string $departmentText,
        public string $positionText,
        public string $salaryLabel,
        public string $salaryText,
    ) {}
}
