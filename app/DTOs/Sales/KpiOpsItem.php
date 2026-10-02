<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

/** Một công tắc vận hành trên trang cấu hình KPI (cảnh báo, khấu trừ, duyệt, Callio, khoá sửa). */
final readonly class KpiOpsItem
{
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public string $icon,
        public bool $isOn,
    ) {}
}
