<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use App\View\Presenters\Marketing\MarketingNumberFormat;
use PHPUnit\Framework\TestCase;

final class MarketingNumberFormatTest extends TestCase
{
    private MarketingNumberFormat $format;

    protected function setUp(): void
    {
        $this->format = new MarketingNumberFormat;
    }

    public function test_money_va_number_theo_quy_uoc_viet_nam(): void
    {
        $this->assertSame('1.234.567 đ', $this->format->money(1234567.4));
        $this->assertSame('0 đ', $this->format->money(null));
        $this->assertSame('1.235', $this->format->number('1234.6'));
        $this->assertSame('0', $this->format->number('abc'));
    }

    public function test_change_tra_null_khi_ky_truoc_bang_khong(): void
    {
        $this->assertNull($this->format->change(10, 0));
        $this->assertNull($this->format->change(0, null));
        $this->assertEqualsWithDelta(25.0, $this->format->change(125, 100), 1e-9);
        $this->assertEqualsWithDelta(-50.0, $this->format->change(50, 100), 1e-9);
    }

    public function test_delta_badge_len_xuong_va_trung_tinh(): void
    {
        $this->assertSame('<span class="ego-delta neutral">—</span>', $this->format->deltaBadge(10, 0)->toHtml());
        $this->assertSame('<span class="ego-delta up">▲ 12,5%</span>', $this->format->deltaBadge(112.5, 100)->toHtml());
        $this->assertSame('<span class="ego-delta down">▼ 3,0%</span>', $this->format->deltaBadge(97, 100)->toHtml());
        // Bằng kỳ trước vẫn coi là "up" 0,0% — giữ đúng hành vi closure cũ của view.
        $this->assertSame('<span class="ego-delta up">▲ 0,0%</span>', $this->format->deltaBadge(100, 100)->toHtml());
    }
}
