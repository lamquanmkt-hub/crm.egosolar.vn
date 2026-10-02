<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một tệp đính kèm của tài sản; `name` đã áp mặc định `File` khi bản ghi không có tên gốc.
 */
final readonly class AssetFileRow
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
