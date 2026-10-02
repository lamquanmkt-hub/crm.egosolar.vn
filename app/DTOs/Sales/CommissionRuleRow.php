<?php

declare(strict_types=1);

namespace App\DTOs\Sales;

/**
 * Một dòng quy tắc hoa hồng trên trang cấu hình: giá trị đã diễn giải để in cạnh bản ghi thô
 * (`rule` vẫn cần cho các ô ẩn của form sửa).
 */
final readonly class CommissionRuleRow
{
    public function __construct(
        public object $rule,
        public string $targetText,
        public string $customerStatus,
        public string $customerStatusText,
        public string $applyText,
        public string $revenueRange,
        public float $fixedAmount,
        public string $calc,
        public string $calcText,
        public string $valueText,
        public bool $isActive,
        public string $typeText,
        public string $baseText,
    ) {}
}
