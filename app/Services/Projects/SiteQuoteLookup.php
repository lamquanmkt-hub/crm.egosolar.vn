<?php

declare(strict_types=1);

namespace App\Services\Projects;

/**
 * Biểu thức SQL đọc một cột của bản báo giá MỚI NHẤT (`site_quotes`, version cao nhất) cho
 * truy vấn thô (query builder / whereRaw) — nơi không có model để gọi `latestQuote()`.
 *
 * Là subquery tương quan nên dùng được cả trong SELECT lẫn WHERE, không cần JOIN, không phá
 * các truy vấn đang `select('s.*')` hay phân trang. `site_quotes` có unique (site_id, version)
 * nên đi thẳng index. Trước đợt CONTRACT (2026_09_07_150000) đây là `COALESCE(…, cột cũ)`.
 */
final class SiteQuoteLookup
{
    /**
     * @param  string  $quoteColumn  cột trong `site_quotes` (customer_company, grand_total…)
     * @param  string  $siteAlias  alias của bảng sites trong truy vấn đang ghép vào
     */
    public static function latest(string $quoteColumn, string $siteAlias = 'sites'): string
    {
        foreach ([$quoteColumn, $siteAlias] as $identifier) {
            if (! preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
                throw new \InvalidArgumentException("Định danh SQL không hợp lệ: {$identifier}");
            }
        }

        return sprintf(
            '(SELECT q.%1$s FROM site_quotes q WHERE q.site_id = %2$s.id ORDER BY q.version DESC LIMIT 1)',
            $quoteColumn,
            $siteAlias,
        );
    }
}
