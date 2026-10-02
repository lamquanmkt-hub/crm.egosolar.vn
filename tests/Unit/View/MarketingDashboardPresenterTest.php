<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use App\DTOs\Marketing\MarketingDashboardInput;
use App\View\Presenters\Marketing\MarketingDashboardPresenter;
use App\View\Presenters\Marketing\MarketingNumberFormat;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Presenter thay 12 khối `@php` của view marketing/dashboard (2026-09-07). Mỗi test khoá một
 * nhóm phép tính mà view cũ tự làm, để sửa presenter không lặng lẽ đổi số trên dashboard.
 */
final class MarketingDashboardPresenterTest extends TestCase
{
    private MarketingDashboardPresenter $presenter;

    protected function setUp(): void
    {
        $this->presenter = new MarketingDashboardPresenter(new MarketingNumberFormat);
    }

    public function test_du_lieu_rong_ra_gia_tri_mac_dinh(): void
    {
        $data = $this->present([]);

        $this->assertInstanceOf(MarketingNumberFormat::class, $data['fmt']);
        $this->assertSame('', $data['platform']);
        $this->assertSame('report', $data['seoTab']);
        $this->assertFalse($data['isSEO']);
        $this->assertFalse($data['isSeoReport']);
        $this->assertSame(0.0, $data['spend']);
        $this->assertSame(0.0, $data['cpl']);
        $this->assertSame([], $data['trendLabels']);
        $this->assertSame([], $data['insights']);
        $this->assertSame([], $data['seoPlanKpi']);
        $this->assertInstanceOf(Collection::class, $data['seoIssues']);
        $this->assertCount(0, $data['byMonth']);
        $this->assertSame(0, $data['pacingPct']);
        $this->assertSame('ok', $data['pacingStatus']);
        $this->assertSame('Chi tiêu đang đúng tiến độ', $data['pacingStatusText']);
    }

    public function test_kpi_suy_ra_cpl_cpa_vat_va_ky_truoc(): void
    {
        $data = $this->present([
            'kpi' => ['spend' => 2000000, 'leads' => 40, 'conversions' => 20, 'clicks' => 500,
                'prev' => ['spend' => 1000000, 'leads' => 10]],
        ]);

        $this->assertSame(50000.0, $data['cpl']);
        $this->assertSame(100000.0, $data['cpa']);
        $this->assertSame(2200000.0, $data['spendVat']);
        $this->assertEqualsWithDelta(200000.0, $data['vatMoney'], 1e-6);
        $this->assertSame(1000000.0, $data['spendPrev']);
        $this->assertSame(100000.0, $data['cplPrev']);

        // cpl/cpa backend cấp sẵn thì giữ; `kpi_prev` riêng thắng `kpi.prev`.
        $data = $this->present([
            'kpi' => ['spend' => 100, 'leads' => 4, 'cpl' => 7, 'cpa' => 9, 'prev' => ['spend' => 1]],
            'kpiPrev' => ['spend' => 55, 'leads' => 5, 'cpl' => 3],
        ]);
        $this->assertSame(7.0, $data['cpl']);
        $this->assertSame(9.0, $data['cpa']);
        $this->assertSame(55.0, $data['spendPrev']);
        $this->assertSame(3.0, $data['cplPrev']);
    }

    public function test_co_nen_tang_va_tab_seo(): void
    {
        $data = $this->present(['platform' => 'google_search']);
        $this->assertTrue($data['isGoogle']);
        $this->assertFalse($data['isFacebook']);
        $this->assertFalse($data['isSEO']);

        $data = $this->present(['platform' => 'seo_organic', 'seoTab' => 'plan']);
        $this->assertTrue($data['isSEO']);
        $this->assertTrue($data['isSeoPlan']);
        $this->assertFalse($data['isSeoReport']);

        $data = $this->present(['platform' => 'seo']);
        $this->assertSame('report', $data['seoTab'], 'không có seo_tab → report');
        $this->assertTrue($data['isSeoReport']);
    }

    public function test_kpi_seo_tinh_ctr_cvr_khi_backend_khong_cap(): void
    {
        $data = $this->present([
            'seoKpi' => ['sessions' => 12000, 'clicks' => 4000, 'impressions' => 150000, 'leads' => 60,
                'prev' => ['sessions' => 10000, 'top10' => 30]],
            'seoTrend' => ['labels' => ['2026-09-01'], 'sessions' => [400]],
        ]);

        $this->assertEqualsWithDelta(2.6666667, $data['seoCtr'], 1e-6);
        $this->assertSame(1.5, $data['seoCvr']);
        $this->assertSame(10000.0, $data['seoSessionsPrev']);
        $this->assertSame(30.0, $data['seoTop10Prev']);
        $this->assertSame(['2026-09-01'], $data['seoTrendLabels']);
        $this->assertSame([400], $data['seoTrendSess']);
        $this->assertSame([], $data['seoTrendTop10']);
    }

    public function test_pheu_tinh_be_rong_thanh_va_ti_le_chuyen_doi(): void
    {
        $data = $this->present([
            'funnel' => ['impressions' => 1000, 'clicks' => 100, 'leads' => 10, 'qualified' => 5],
        ]);

        $this->assertSame(1000.0, $data['funnelImpressions']);
        $this->assertSame(['impressions' => 100, 'clicks' => 10.0, 'leads' => 1.0, 'qualified' => 0.5, 'appointments' => 0], $data['funnelWidths']);
        $this->assertSame(['click' => 10.0, 'lead' => 10.0, 'qualified' => 50.0], $data['funnelRates']);

        // Không có số phễu riêng → lấy KPI chung; mọi thứ 0 thì bề rộng 0 chứ không chia cho 0.
        $data = $this->present(['kpi' => ['impressions' => 50, 'clicks' => 25, 'leads' => 5]]);
        $this->assertSame(50.0, $data['funnelImpressions']);
        $this->assertSame(50.0, $data['funnelWidths']['clicks']);
        $data = $this->present([]);
        $this->assertSame(0.0, $data['funnelWidths']['impressions']);
        $this->assertSame(0.0, $data['funnelRates']['click']);
    }

    public function test_tien_do_ngan_sach(): void
    {
        $data = $this->present([
            'budget' => ['month_budget' => 5000000, 'spent' => 5500000, 'forecast' => 6100000, 'status' => 'over', 'note' => 'Tăng bid'],
        ]);
        $this->assertSame(100, $data['pacingPct']);
        $this->assertSame('Chi tiêu đang vượt tiến độ', $data['pacingStatusText']);
        $this->assertSame(6100000.0, $data['forecast']);
        $this->assertSame('Tăng bid', $data['pacingNote']);

        $data = $this->present(['kpi' => ['spend' => 1000000], 'budget' => ['month_budget' => 4000000, 'status' => 'under']]);
        $this->assertSame(1000000.0, $data['spentToDate'], 'thiếu spent → lấy spend KPI');
        $this->assertSame(25.0, $data['pacingPct']);
        $this->assertSame('Chi tiêu đang thấp hơn tiến độ', $data['pacingStatusText']);

        $data = $this->present(['budget' => ['status' => 'la']]);
        $this->assertSame('Chi tiêu đang đúng tiến độ', $data['pacingStatusText'], 'trạng thái lạ → câu mặc định');
    }

    public function test_dong_bang_nhan_mang_lan_object_thieu_cot(): void
    {
        $data = $this->present([
            'seoTopQueries' => [(object) ['query' => 'pin', 'clicks' => 5], ['clicks' => 300, 'impressions' => 9000, 'ctr' => 3.5]],
            'seoTopPages' => collect([(object) ['url' => '/pin', 'sessions' => 200, 'leads' => 5]]),
            'seoPlanItems' => [['title' => 'Bài blog']],
            'byMonth' => [(object) ['month' => '2026-09', 'spend' => 1000, 'reach' => 50, 'leads' => 4]],
            'byDevice' => [['spend' => 10]],
            'byGeo' => [(object) ['location' => 'HCM', 'spend' => 0, 'leads' => 0]],
            'topAds' => [(object) ['ad_name' => 'Video'], ['creative_name' => 'Carousel'], []],
            'insights' => [(object) ['level' => 'warn'], ['text' => 'Thiếu level']],
            'seoIssues' => [['level' => 'danger', 'text' => '404']],
        ]);

        $query = $data['seoTopQueries'][0];
        $this->assertSame(['pin', 5.0, 0.0, 0.0, 0.0], [$query->query, $query->clicks, $query->impressions, $query->ctr, $query->position]);
        $this->assertSame(['—', 3.5], [$data['seoTopQueries'][1]->query, $data['seoTopQueries'][1]->ctr]);
        $this->assertSame(2.5, $data['seoTopPages'][0]->cvr);
        $plan = $data['seoPlanItems'][0];
        $this->assertSame(['Content', 'Bài blog', '—', 'Chưa làm'], [$plan->type, $plan->title, $plan->url, $plan->status]);
        $this->assertSame(250.0, $data['byMonth'][0]->cpl);
        $this->assertSame(0.0, $data['byMonth'][0]->impressions);
        $this->assertSame(['—', 10.0, 0.0], [$data['byDevice'][0]->device, $data['byDevice'][0]->spend, $data['byDevice'][0]->cpl]);
        $this->assertSame(0.0, $data['byGeo'][0]->cpl, 'leads 0 → cpl 0, không chia cho 0');
        $this->assertSame(['Video', 'Carousel', '—'], $data['topAds']->pluck('name')->all());
        $this->assertSame([['level' => 'warn', 'text' => ''], ['level' => 'info', 'text' => 'Thiếu level']], $data['insights']);
        $this->assertSame('danger', $data['seoIssues'][0]['level']);
    }

    /** @param  array<string, mixed>  $input  khoá = tên tham số của MarketingDashboardInput */
    private function present(array $input): array
    {
        return $this->presenter->viewData(new MarketingDashboardInput(...$input));
    }
}
