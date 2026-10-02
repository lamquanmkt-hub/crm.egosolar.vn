<?php

declare(strict_types=1);

namespace App\DTOs\Marketing;

/**
 * Một dòng bảng "Chỉ số marketing (gần đây)" của trang `marketing/budget`.
 *
 * Ba cột breakdown thay closure `$formatBreakdown` khai trong `@php` giữa thân view.
 */
final readonly class MetricRow
{
    /**
     * @param  string  $dateFromText  `d/m`; rỗng khi `date_from` null
     * @param  string  $dateToText  `d/m`; rỗng khi `date_to` null — view chỉ in dấu `-` khi khác rỗng
     * @param  string  $genderText  `nam:18, nu:12`; `-` khi mảng trống/null/mọi giá trị ≤ 0
     */
    public function __construct(
        public int $id,
        public string $dateFromText,
        public string $dateToText,
        public string $platform,
        public string $campaignName,
        public float $spend,
        public float $reach,
        public float $leads,
        public string $genderText,
        public string $ageText,
        public string $regionText,
    ) {}
}
