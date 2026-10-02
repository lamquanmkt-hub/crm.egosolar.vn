<?php

declare(strict_types=1);

namespace App\DTOs\Projects;

use Illuminate\Support\Collection;

/** Một phiếu đề xuất vật tư trên trang dự án: bản ghi thô + nhãn/tông trạng thái, số dòng, tổng số lượng đã định dạng. */
final readonly class MaterialProposalRow
{
    /** @param  Collection<int, MaterialProposalItemRow>  $items */
    public function __construct(
        public object $proposal,
        public Collection $items,
        public string $status,
        public string $statusLabel,
        public string $tone,
        public ?int $linkedMaterialRequestId,
        public int $itemCount,
        public string $totalQuantityText,
    ) {}
}
