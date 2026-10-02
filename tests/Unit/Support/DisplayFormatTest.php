<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\DisplayFormat;
use PHPUnit\Framework\TestCase;

final class DisplayFormatTest extends TestCase
{
    public function test_money_va_number(): void
    {
        $this->assertSame('1.234.567 đ', DisplayFormat::money('1234567.00'));
        $this->assertSame('0 đ', DisplayFormat::money(null));
        $this->assertSame('1.235', DisplayFormat::number(1234.6));
        $this->assertSame('1.234,50', DisplayFormat::number(1234.5, 2));
    }

    /**
     * Phần trăm dùng dấu VIỆT NAM ở mọi trang (chốt 2026-09-29).
     *
     * Trước đó các trang gọi `number_format($v, 1)` trơn nên ra `0.0%` kiểu Anh, trong khi TIỀN
     * cùng trang lại ra `1.000.000 đ` kiểu Việt. Test này là chốt chặn cho quy ước mới.
     */
    public function test_percent_dung_dau_tieng_viet(): void
    {
        $this->assertSame('0%', DisplayFormat::percent(null));
        $this->assertSame('0%', DisplayFormat::percent(0));
        $this->assertSame('0,0%', DisplayFormat::percent(0, 1));
        $this->assertSame('100,00%', DisplayFormat::percent(100, 2));
        $this->assertSame('12,5%', DisplayFormat::percent(12.5, 1));
        $this->assertSame('-9,09%', DisplayFormat::percent(-9.09, 2));
        // Dấu ngăn NGHÌN cũng phải là dấu chấm — đây là chỗ bản cũ sai ngược lại (`1,234.6%`).
        $this->assertSame('1.234,6%', DisplayFormat::percent(1234.56, 1));
        $this->assertSame('1.235%', DisplayFormat::percent(1234.56));
    }

    public function test_quantity_bo_thap_phan_khi_so_nguyen(): void
    {
        $this->assertSame('—', DisplayFormat::quantity(null));
        $this->assertSame('—', DisplayFormat::quantity(''));
        $this->assertSame('0', DisplayFormat::quantity(0));
        $this->assertSame('2', DisplayFormat::quantity('2.00'));
        $this->assertSame('20,50', DisplayFormat::quantity(20.5));
        $this->assertSame('1.234,25', DisplayFormat::quantity(1234.25));
    }

    public function test_date(): void
    {
        $this->assertSame('—', DisplayFormat::date(null));
        $this->assertSame('—', DisplayFormat::date(''));
        $this->assertSame('15/08/2026', DisplayFormat::date('2026-08-15'));
        $this->assertSame('15/08/2026 09:30', DisplayFormat::date('2026-08-15 09:30:00', 'd/m/Y H:i'));
        $this->assertSame('không phải ngày', DisplayFormat::date('không phải ngày'));
    }
}
