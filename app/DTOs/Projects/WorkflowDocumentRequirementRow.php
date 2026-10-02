<?php

declare(strict_types=1);

namespace App\DTOs\Projects;

use Illuminate\Support\Collection;

/** Một dòng hồ sơ bắt buộc/tuỳ chọn của bước workflow: định nghĩa + file đã tải, số lượng, còn thiếu, được tải thêm không. */
final readonly class WorkflowDocumentRequirementRow
{
    /**
     * @param  array<string, mixed>  $requirement  mục trong `document_state.items` (code, label, required, extensions…)
     * @param  Collection<int, object>  $documents  file đã tải của mã hồ sơ này
     */
    public function __construct(
        public array $requirement,
        public Collection $documents,
        public int $count,
        public int $minimum,
        public ?int $maximum,
        public int $missing,
        public bool $canUpload,
    ) {}
}
