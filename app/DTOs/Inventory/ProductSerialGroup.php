<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

/** Nhóm serial theo sản phẩm trong form sửa: sản phẩm (null nếu không còn trong catalog) + các dòng serial. */
final readonly class ProductSerialGroup
{
    /** @param  list<ProductSerialLine>  $lines */
    public function __construct(
        public int $productId,
        public ?object $product,
        public array $lines,
    ) {}
}
