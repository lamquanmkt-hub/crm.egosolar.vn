<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\View\Presenters\Finance\FinanceDashboardPresenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `FinanceDashboardPresenter` — thay khối `@php` khai closure `$money` (14 chỗ gọi) cộng 4 lượt
 * `number_format` và một lượt `Carbon::parse` viết thẳng trong `finance/index`.
 *
 * Dùng `PHPUnit\TestCase` trơn (không Laravel): presenter phải THUẦN — không Facade, không truy
 * vấn, không đọc request. Test đỏ ở đây nghĩa là ai đó vừa nhét phụ thuộc vào.
 */
final class FinanceDashboardPresenterTest extends TestCase
{
    /** @param array<string, mixed> $so */
    private function data(array $so = [], array $don = []): array
    {
        return (new FinanceDashboardPresenter)->viewData($so, $don);
    }

    public function test_tien_dinh_dang_kieu_viet_co_dau_cach_truoc_dong(): void
    {
        $d = $this->data([
            'totalRevenue' => 1_234_567,
            'totalCost' => 1_000_000,
            'grossProfit' => 234_567,
            'totalReceivableBase' => 9_000_000,
            'collectedAmount' => 4_500_000,
            'receivableAmount' => 4_500_000,
            'overdueReceivables' => 500_000,
            'cashInPeriod' => 7_000_000,
            'cashOutPeriod' => 2_000_000,
            'netCashFlow' => 5_000_000,
            'pendingDisbursement' => 300_000,
        ]);

        $this->assertSame('1.234.567 đ', $d['revenueText']);
        $this->assertSame('1.000.000 đ', $d['costText']);
        $this->assertSame('234.567 đ', $d['grossProfitText']);
        $this->assertSame('9.000.000 đ', $d['receivableBaseText']);
        $this->assertSame('4.500.000 đ', $d['collectedText']);
        $this->assertSame('4.500.000 đ', $d['receivableText']);
        $this->assertSame('500.000 đ', $d['overdueText']);
        $this->assertSame('7.000.000 đ', $d['cashInText']);
        $this->assertSame('2.000.000 đ', $d['cashOutText']);
        $this->assertSame('5.000.000 đ', $d['netCashFlowText']);
        $this->assertSame('300.000 đ', $d['pendingDisbursementText']);
    }

    public function test_thieu_het_so_thi_ve_khong_chu_khong_no(): void
    {
        $d = $this->data();

        $this->assertSame('0 đ', $d['revenueText']);
        $this->assertSame('0,0%', $d['collectionRateText']);
        $this->assertSame('0', $d['totalOrdersText']);
        $this->assertSame('0', $d['pendingRequestsText']);
        $this->assertSame('0,00%', $d['grossMarginText']);
        $this->assertSame([], $d['profitRows']);
    }

    /** Phần trăm dùng dấu VIỆT ở mọi trang — chốt 2026-09-29, xem DisplayFormatTest. */
    public function test_phan_tram_dung_dau_viet(): void
    {
        $d = $this->data(['collectionRate' => 1234.56, 'grossMargin' => -9.09]);

        $this->assertSame('1.234,6%', $d['collectionRateText']);
        $this->assertSame('-9,09%', $d['grossMarginText']);
    }

    /**
     * Lớp màu theo DẤU: mốc 0 tính là DƯƠNG (`>= 0`), giữ y bản cũ.
     */
    #[DataProvider('mocDau')]
    public function test_tone_theo_dau_lay_moc_0_la_duong(float $so, string $lop, string $badge, string $chu): void
    {
        $d = $this->data(['grossProfit' => $so, 'netCashFlow' => $so]);

        $this->assertSame($lop, $d['grossProfitClass']);
        $this->assertSame($lop, $d['netCashFlowClass']);
        $this->assertSame($badge, $d['netCashFlowBadgeClass']);
        $this->assertSame($chu, $d['netCashFlowBadgeText']);
    }

    /**
     * Lớp nay là chuỗi TAILWIND, không còn tên Bootstrap/BEM: trang này từng định nghĩa lại
     * `.text-success`/`.text-danger` bằng `!important` nên giữ tên cũ là màu rơi về bản Bootstrap.
     *
     * @return array<string, array{0: float, 1: string, 2: string, 3: string}>
     */
    public static function mocDau(): array
    {
        $xanh = 'tw:text-[#166534]';
        $do = 'tw:text-[#dc2626]';
        $badgeXanh = 'tw:bg-[rgba(22,163,74,0.10)] tw:text-[#166534] tw:border-[rgba(22,163,74,0.16)]';
        $badgeDo = 'tw:bg-[rgba(220,38,38,0.10)] tw:text-[#dc2626] tw:border-[rgba(220,38,38,0.16)]';

        return [
            'dương' => [1.0, $xanh, $badgeXanh, 'Dương'],
            'đúng 0 -> DƯƠNG' => [0.0, $xanh, $badgeXanh, 'Dương'],
            'âm sát 0' => [-0.01, $do, $badgeDo, 'Âm'],
            'âm' => [-1_000_000.0, $do, $badgeDo, 'Âm'],
        ];
    }

    public function test_dong_don_du_ba_nhanh(): void
    {
        $d = $this->data([], [
            (object) ['order_code' => 'FIN-A', 'order_date' => '2026-09-01', 'sale_amount' => 20_000_000,
                'cost_amount' => 12_000_000, 'gross_profit' => 8_000_000, 'margin_percent' => 40],
            (object) ['order_code' => 'FIN-B', 'order_date' => '2026-08-15', 'sale_amount' => 2_000_000,
                'cost_amount' => 12_000_000, 'gross_profit' => -10_000_000, 'margin_percent' => -500],
            // margin NULL (NULLIF khi doanh thu = 0) và KHÔNG có ngày.
            (object) ['order_code' => 'FIN-C', 'order_date' => null, 'sale_amount' => 0,
                'cost_amount' => 0, 'gross_profit' => 0, 'margin_percent' => null],
        ]);

        [$a, $b, $c] = $d['profitRows'];

        $this->assertSame('FIN-A', $a->codeText);
        $this->assertSame('01/09/2026', $a->dateText);
        $this->assertSame('20.000.000 đ', $a->saleText);
        $this->assertSame('12.000.000 đ', $a->costText);
        $this->assertSame('8.000.000 đ', $a->profitText);
        $this->assertSame('tw:text-[#166534]', $a->profitClass);
        $this->assertSame('40,00%', $a->marginText);
        $this->assertStringContainsString('tw:text-[#166534]', $a->marginClass);

        $this->assertSame('-10.000.000 đ', $b->profitText);
        $this->assertSame('tw:text-[#dc2626]', $b->profitClass);
        $this->assertSame('-500,00%', $b->marginText);
        $this->assertStringContainsString('tw:text-[#dc2626]', $b->marginClass);

        // Ngày trống in `--` (HAI gạch), KHÔNG phải `—` của DisplayFormat::date().
        $this->assertSame('--', $c->dateText);
        $this->assertSame('0,00%', $c->marginText, 'margin NULL phải về 0');
        $this->assertStringContainsString('tw:text-[#166534]', $c->marginClass);
    }

    /**
     * Bản cũ: `$row->order_code ?? ('#' . ($row->order_id ?? ''))`.
     *
     * `??` chỉ bắt NULL — mã đơn là chuỗi RỖNG thì in ra RỖNG, không rơi về `#<id>`.
     * Đừng "sửa" thành `?:` cho gọn: đó là đổi hành vi.
     */
    public function test_ma_don_rong_khong_roi_ve_id(): void
    {
        $d = $this->data([], [
            (object) ['order_code' => null, 'order_id' => 77],
            (object) ['order_code' => '', 'order_id' => 88],
            (object) ['order_code' => null],
        ]);

        $this->assertSame('#77', $d['profitRows'][0]->codeText);
        $this->assertSame('', $d['profitRows'][1]->codeText, 'chuỗi rỗng KHÔNG rơi về #id');
        $this->assertSame('#', $d['profitRows'][2]->codeText, 'thiếu cả id thì chỉ còn dấu #');
    }
}
