<?php

declare(strict_types=1);

namespace App\DTOs\Projects;

/** Một dòng vật tư trong phiếu đề xuất: bản ghi thô + số lượng đã định dạng (bỏ số 0 thừa sau dấu phẩy). */
final readonly class MaterialProposalItemRow
{
    public function __construct(
        public object $item,
        public string $quantityText,
    ) {}
}
