<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Năm ô chỉ số đầu trang `finance/assets`.
 *
 * ⚠️ `countText` / `activeText` / `warningText` ĐỔI ĐẦU RA có chủ ý: bản cũ dùng `number_format()`
 * TRƠN, tức dấu phân cách mặc định kiểu Anh (`1,234`) ngay cạnh các ô tiền in kiểu Việt
 * (`1.234.567 đ`). Nay cả trang dùng một quy ước, theo quyết định đã chốt ở đợt 2026-09-29 (11).
 * Khác biệt chỉ xuất hiện khi số ≥ 1.000.
 */
final readonly class AssetSummaryCards
{
    public function __construct(
        public string $countText,
        public string $activeText,
        public string $costText,
        public string $bookValueText,
        public string $accumulatedText,
        public string $warningText,
    ) {}
}
