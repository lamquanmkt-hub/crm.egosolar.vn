<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

/** Một bậc thưởng KPI theo doanh số trên trang cấu hình hoa hồng. */
final readonly class KpiTierRow
{
    public function __construct(
        public object $tier,
        public bool $isActive,
        public string $salesName,
        public string $range,
        public string $bonusTypeText,
        public string $bonusValue,
    ) {}
}
