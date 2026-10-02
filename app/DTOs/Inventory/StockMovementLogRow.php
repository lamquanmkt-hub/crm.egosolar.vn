<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

/** Một dòng lịch sử xuất nhập trên form sửa sản phẩm: bản ghi + nguồn đã phân loại + các ô đã định dạng. */
final readonly class StockMovementLogRow
{
    public function __construct(
        public object $log,
        public int $changeQty,
        public string $changeText,
        public string $sourceLabel,
        public string $sourceClass,
        public string $sourceIcon,
        public string $beforeText,
        public string $afterText,
        public string $createdAtText,
        public string $noteText,
    ) {}
}
