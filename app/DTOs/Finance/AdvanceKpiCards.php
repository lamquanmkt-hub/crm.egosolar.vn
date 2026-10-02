<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Bốn ô chỉ số đầu trang `advance_requests/index` cùng hai con số của thanh tiêu đề bảng.
 *
 * ⚠️ Các số ĐỔI ĐẦU RA có chủ ý: bản cũ dùng `number_format()` TRƠN (dấu Anh `1,234`) trong khi
 * cột tiền cùng trang in kiểu Việt. Nay thống nhất theo quyết định đã chốt ở đợt 2026-09-29 (11);
 * khác biệt chỉ xuất hiện khi số ≥ 1.000.
 */
final readonly class AdvanceKpiCards
{
    /**
     * @param  bool  $hasOverdue  có phiếu quá hạn hoàn ứng hay không — ô KPI đổi tông chữ phụ
     * @param  string  $settlementHint  dòng nhỏ của ô "Cần hoàn ứng"; đổi hẳn nội dung khi có phiếu quá hạn
     */
    public function __construct(
        public string $total,
        public string $pending,
        public string $needSettlement,
        public string $done,
        public bool $hasOverdue,
        public string $settlementHint,
        public string $pageCount,
        public string $totalCount,
    ) {}
}
