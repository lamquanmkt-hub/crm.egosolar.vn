<?php

declare(strict_types=1);

namespace App\DTOs\Marketing;

/**
 * Một dòng "Tổng hợp theo tháng & kênh" của trang `marketing/budget`.
 *
 * Tồn tại vì view có khối `@php` NGAY TRONG `@forelse` chỉ để tính `% tiêu` — lặp cho mỗi dòng.
 */
final readonly class BudgetSummaryRow
{
    /**
     * @param  string  $monthText  tháng `m/Y`; cột `month` NOT NULL nên không có nhánh trống
     * @param  float  $totalBudget  số thô — view tự `number_format()` như cũ (xem ghi chú presenter)
     * @param  float  $percentSpent  `% tiêu` đã làm tròn; bằng 0 khi ngân sách bằng 0 (tránh chia cho 0)
     */
    public function __construct(
        public string $monthText,
        public string $platform,
        public float $totalBudget,
        public float $totalSpent,
        public float $percentSpent,
    ) {}
}
