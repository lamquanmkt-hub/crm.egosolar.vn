<?php

declare(strict_types=1);

namespace App\DTOs\Marketing;

/**
 * Một dòng bảng "Campaign tổng hợp (Ngân sách + Chỉ số)" của trang `marketing/budget`.
 *
 * Giữ HAI dạng tháng vì view in khác nhau theo `$month`: khi lọc gọn trong một tháng thì
 * controller đặt `month` = `Y-m-d` và view parse ra `m/Y`; khi lọc bắc cầu nhiều tháng thì
 * `month` đã là nhãn khoảng (`01/09/2026 - 30/09/2026`) và view in thẳng.
 */
final readonly class CampaignCombinedRow
{
    public function __construct(
        public string $monthText,
        public string $monthRaw,
        public string $platform,
        public string $campaignName,
        public float $budget,
        public float $budgetSpent,
        public float $spend,
        public float $reach,
        public float $leads,
    ) {}
}
