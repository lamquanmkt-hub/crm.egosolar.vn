<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Bốn ô chỉ số đầu trang `settlement_requests/index`, cùng hai con số của thanh tiêu đề bảng.
 *
 * ⚠️ `total`/`pending`/`done`/`pageCount`/`totalCount` ĐỔI ĐẦU RA có chủ ý: bản cũ dùng
 * `number_format()` TRƠN (dấu Anh) ngay cạnh ô tiền in kiểu Việt. Khác biệt chỉ khi số ≥ 1.000.
 */
final readonly class SettlementKpiCards
{
    public function __construct(
        public string $total,
        public string $pending,
        public string $done,
        public string $refundText,
        public string $pageCount,
        public string $totalCount,
    ) {}
}
