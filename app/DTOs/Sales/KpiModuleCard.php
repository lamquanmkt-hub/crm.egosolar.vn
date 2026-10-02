<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

/** Một thẻ chỉ tiêu KPI trên trang cấu hình: định nghĩa cố định + trạng thái bật/target đọc từ cấu hình đã lưu. */
final readonly class KpiModuleCard
{
    public function __construct(
        public string $enabledKey,
        public ?string $targetKey,
        public string $title,
        public string $unit,
        public string $icon,
        public ?string $tone,
        public string $description,
        public bool $isOn,
        public int $targetValue,
    ) {}
}
