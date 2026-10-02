<?php

declare(strict_types=1);

namespace App\DTOs\Marketing;

/**
 * Một dòng bảng "Danh sách ngân sách" của trang `marketing/budget`.
 *
 * `$id` giữ lại để view dựng `route('marketing.budget.edit', ...)` — `route()` được phép ở Blade.
 */
final readonly class BudgetRow
{
    /**
     * @param  string  $monthText  tháng `m/Y`; CHUỖI RỖNG khi `month` null, đúng như
     *                             `optional($r->month)->format('m/Y')` của bản cũ (không phải `—`)
     * @param  string  $campaignName  tên chiến dịch, `-` khi không có
     */
    public function __construct(
        public int $id,
        public string $monthText,
        public string $platform,
        public string $campaignName,
        public float $budget,
        public float $actualSpent,
    ) {}
}
