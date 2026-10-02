<?php

declare(strict_types=1);

namespace App\DTOs\Projects;

/**
 * Một dòng vật tư thực tế (đã xuất kho) trên trang chi tiết công trình, đã phân nhóm và tách
 * tên/ĐVT/ghi chú từ cột `note` dạng "[Nhóm] Tên | ĐVT: x | ghi chú".
 */
final readonly class SiteMaterialRow
{
    public function __construct(
        public int $requestId,
        public ?string $requestCreatedAt,
        public ?int $productId,
        public bool $inCatalog,
        public ?string $sku,
        public string $name,
        public string $group,
        public string $groupClass,
        public float $qty,
        public string $unit,
        public float $unitCost,
        public float $vatPercent,
        public float $lineTotal,
        public string $note,
    ) {}
}
