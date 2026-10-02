<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

/** Một dòng lương/target của sales trên trang cấu hình hoa hồng; thiếu thiết lập thì dùng mặc định 7 triệu / 500 triệu. */
final readonly class SalesSalaryRow
{
    public function __construct(
        public object $user,
        public ?object $salary,
        public float $baseSalary,
        public float $targetRevenue,
        public float $targetCommission,
        public bool $isActive,
        public string $note,
        public string $baseSalaryText,
        public string $targetRevenueText,
        public string $targetCommissionText,
    ) {}
}
