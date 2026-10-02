<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

/**
 * Thưởng KPI của một sales trong kỳ, kèm tên bậc để hiện lên báo cáo.
 */
final class KpiBonus
{
    public const NOT_REACHED = 'Chưa đạt KPI';

    public function __construct(
        public readonly float $amount = 0.0,
        public readonly string $tierName = self::NOT_REACHED,
    ) {}
}
