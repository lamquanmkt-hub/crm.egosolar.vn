<?php

declare(strict_types=1);

namespace App\DTOs\Inventory;

/** Một serial trong bảng "dọn serial" của form sửa sản phẩm: bản ghi + trạng thái đã chuẩn hoá + có khoá sửa/xoá không. */
final readonly class ProductSerialLine
{
    public function __construct(
        public object $serial,
        public string $state,
        public bool $locked,
    ) {}
}
