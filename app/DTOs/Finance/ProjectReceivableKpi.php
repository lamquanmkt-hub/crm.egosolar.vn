<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Sáu ô chỉ số đầu trang "Phải thu công trình".
 *
 * `projectCount` ĐỔI ĐẦU RA có chủ ý: bản cũ dùng `number_format()` TRƠN (dấu Anh) cho số đếm
 * trong khi 5 ô tiền cùng hàng in kiểu Việt. Khác biệt chỉ xuất hiện khi số ≥ 1.000.
 */
final readonly class ProjectReceivableKpi
{
    public function __construct(
        public string $projectCount,
        public string $contractAmount,
        public string $receivedAmount,
        public string $receivableAmount,
        public string $overdueAmount,
        public string $overpaidAmount,
    ) {}
}
