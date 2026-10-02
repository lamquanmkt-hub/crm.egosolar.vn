<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

/**
 * Kỳ tính hoa hồng: một tháng.
 *
 * Giữ cả hai dạng vì CSDL dùng cả hai: bảng hoa hồng lọc theo chuỗi `'Y-m'`
 * (cột `period_month`), còn bảng đơn lọc theo khoảng thời gian đầy đủ.
 */
final class SalesPeriod
{
    public function __construct(
        public readonly string $month,
        public readonly string $from,
        public readonly string $to,
    ) {}

    /** @return array{0: string, 1: string} khoảng dùng cho `whereBetween` */
    public function range(): array
    {
        return [$this->from, $this->to];
    }
}
