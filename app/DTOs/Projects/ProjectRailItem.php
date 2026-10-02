<?php

declare(strict_types=1);

namespace App\DTOs\Projects;

/** Một mục trên thanh quy trình (rail) của trang dự án hợp nhất: nhãn, biểu tượng, tông màu và dòng trạng thái. */
final readonly class ProjectRailItem
{
    public function __construct(
        public string $code,
        public string $label,
        public bool $done,
        public string $icon,
        public string $tone,
        public string $status,
    ) {}
}
