<?php

declare(strict_types=1);

namespace App\DTOs\Technical;

/**
 * Phần đầu trang "KPI công trình": mã, tên và 5 ô thống kê.
 *
 * Thay khối `@php` đầu view (ghép mã dự phòng `DA-SITE-00000`, tên dự phòng) và 2 lượt
 * `Carbon::parse(...)->format()` viết thẳng trong Blade.
 */
final readonly class ProjectKpiHeader
{
    public function __construct(
        public string $projectCode,
        public string $projectName,
        public string $progressText,
        public string $targetCompletionText,
        public string $completedText,
        public string $engineerCountText,
    ) {}
}
