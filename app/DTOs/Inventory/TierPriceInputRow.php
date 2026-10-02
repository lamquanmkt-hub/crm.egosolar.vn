<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

/** Ô nhập giá theo bảng giá trên form sửa sản phẩm: bảng giá + giá trị hiện lên ô (old input thắng giá đã lưu). */
final readonly class TierPriceInputRow
{
    public function __construct(
        public object $tier,
        public string $beforeVat,
        public string $vatPercent,
    ) {}
}
