<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

/**
 * Một dòng của bảng danh sách sản phẩm (trang tổng / đầu vào / đầu ra): bản ghi sản phẩm (hoặc dòng lô)
 * kèm các số đã suy ra. Trang nào không dùng trường nào thì trường đó là 0 / rỗng.
 */
final readonly class ProductListRow
{
    /**
     * @param  object  $product  Product (dòng lô của trang tổng/đầu ra mang thêm stock_lot_*, warehouse_name)
     * @param  list<array{name: string, qty: float}>  $warehouseStocks  tồn theo kho (trang đầu vào), giảm dần theo số lượng
     */
    public function __construct(
        public object $product,
        public ?string $lotTitle,
        public ?string $note,
        public ?string $imageUrl,
        public int $displayQty,
        public float $vatPercent,
        public float $sellBefore,
        public float $sellAfter,
        public float $costBefore,
        public float $costAfter,
        public float $rowAmount,
        public array $warehouseStocks,
    ) {}
}
