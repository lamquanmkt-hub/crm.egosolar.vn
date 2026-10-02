<?php

declare(strict_types=1);

namespace App\DTOs\Order;

/** Một dòng hàng trên form duyệt đơn: giảm % đã kẹp [0,100], giảm tiền tự suy từ % khi DB không lưu. */
final readonly class OrderApprovalItemView
{
    public function __construct(
        public string $productName,
        public int $qty,
        public float $unitPrice,
        public float $discountPercent,
        public float $discountAmount,
        public float $lineTotal,
    ) {}
}
