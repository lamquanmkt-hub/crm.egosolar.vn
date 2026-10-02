<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

/** Dữ liệu trang `product-categories/show`. */
final readonly class ProductCategoryDetail
{
    /**
     * @param  list<ProductCategoryChildRow>  $children
     * @param  list<array{id: int, name: string}>  $products  tối đa 10 sản phẩm đầu
     */
    public function __construct(
        public int $id,
        public string $name,
        /** Mô tả; `—` khi null — giữ đúng `?? '—'` của bản cũ (chuỗi RỖNG vẫn in rỗng). */
        public string $descriptionText,
        public ?string $parentName,
        public string $createdText,
        public string $updatedText,
        public int $productCount,
        public int $childCount,
        public array $children,
        public array $products,
        /** Số sản phẩm còn lại ngoài 10 cái đầu; 0 nếu không có. */
        public int $extraProductCount,
    ) {}
}
