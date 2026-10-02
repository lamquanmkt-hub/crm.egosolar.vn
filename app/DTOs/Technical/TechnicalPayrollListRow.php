<?php

declare(strict_types=1);

namespace App\DTOs\Technical;

/**
 * Một dòng bảng lương kỹ thuật đã định dạng sẵn.
 *
 * Tồn tại vì view trước đây có khối `@php $approved = ...; @endphp` trong vòng lặp, và tự gọi
 * `number_format()` cho ba cột số ở từng dòng.
 */
final readonly class TechnicalPayrollListRow
{
    /** @param  bool  $approved  đã duyệt thì đổi nhãn/icon và ẩn nút Sửa */
    public function __construct(
        public int $id,
        public string $employeeName,
        public string $positionName,
        public string $monthText,
        public string $kpiPercentText,
        public string $grossText,
        public string $incomeText,
        public bool $approved,
    ) {}
}
