<?php

declare(strict_types=1);

namespace App\DTOs\Order;

/** Một bước trên dải luồng duyệt của trang chi tiết đơn: nhãn, số thứ tự và lớp CSS (done/active/pending). */
final readonly class OrderFlowStepView
{
    public function __construct(
        public string $key,
        public string $label,
        public int $number,
        public bool $done,
        public bool $active,
        public string $class,
    ) {}
}
