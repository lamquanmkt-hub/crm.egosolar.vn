<?php

declare(strict_types=1);

namespace App\View\Presenters\Marketing;

use App\Support\DisplayFormat;
use Illuminate\Support\HtmlString;

/**
 * Định dạng số cho dashboard marketing, quy ước Việt Nam: chấm ngăn nghìn, phẩy ngăn thập phân.
 *
 * Trước 2026-09-07 là bốn closure khai ngay đầu view `marketing.dashboard`
 * (`$money`, `$num`, `$pct`, `$deltaBadge`). Gom về một lớp vì cùng một mối quan tâm —
 * cách trang này hiển thị số — nên sửa quy ước là sửa đúng một chỗ, và test được.
 */
final class MarketingNumberFormat
{
    /** Tiền, không thập phân: `1.234.567 đ`. */
    public function money(mixed $value): string
    {
        return $this->group($value, 0).' đ';
    }

    /** Số đếm, không thập phân: `1.234`. */
    public function number(mixed $value): string
    {
        return $this->group($value, 0);
    }

    /** Biến động % so với kỳ trước; null khi kỳ trước bằng 0 (không so được). */
    public function change(mixed $current, mixed $previous): ?float
    {
        $current = (float) $current;
        $previous = (float) $previous;
        if ($previous == 0) {
            return null;
        }

        return (($current - $previous) / $previous) * 100;
    }

    /** Huy hiệu `▲ 12,5%` / `▼ 3,0%`; `—` khi không so được với kỳ trước. */
    public function deltaBadge(mixed $current, mixed $previous): HtmlString
    {
        $delta = $this->change($current, $previous);
        if ($delta === null) {
            return new HtmlString('<span class="ego-delta neutral">—</span>');
        }
        $direction = $delta >= 0 ? 'up' : 'down';
        $arrow = $delta >= 0 ? '▲' : '▼';

        return new HtmlString('<span class="ego-delta '.$direction.'">'.$arrow.' '.$this->group(abs($delta), 1).'%</span>');
    }

    private function group(mixed $value, int $decimals): string
    {
        return DisplayFormat::number($value, $decimals);
    }
}
