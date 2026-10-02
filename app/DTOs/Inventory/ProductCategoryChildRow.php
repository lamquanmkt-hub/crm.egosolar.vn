<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

/** Một danh mục con trên trang chi tiết danh mục. */
final readonly class ProductCategoryChildRow
{
    public function __construct(
        public int $id,
        public string $name,
        /** Số sản phẩm — bản cũ gọi `$child->products()->count()` NGAY TRONG vòng lặp (N+1). */
        public int $productCount,
    ) {}
}
