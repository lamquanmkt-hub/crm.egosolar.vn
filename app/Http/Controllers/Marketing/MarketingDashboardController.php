<?php

namespace App\Http\Controllers\Marketing;

use App\DTOs\Marketing\MarketingDashboardInput;
use App\Http\Controllers\Controller;
use App\View\Presenters\Marketing\MarketingDashboardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Controller dashboard marketing với các tab Ads/SEO/Content/Email.
 *
 * Mọi giá trị suy ra cho view (cờ nền tảng, KPI kỳ trước, phễu, tiến độ ngân sách, dòng bảng
 * chuẩn hoá) do {@see MarketingDashboardPresenter} tính; controller chỉ truy vấn và lọc ngày.
 */
class MarketingDashboardController extends Controller
{
    public function __construct(private readonly MarketingDashboardPresenter $presenter) {}

    /**
     * Điều phối render dashboard theo tab và khoảng ngày lọc.
     */
    public function index(Request $request)
    {
        // TAB cấp 1: ads|seo|content|email
        $tab = strtolower($request->input('tab', 'ads'));
        if (! in_array($tab, ['ads', 'seo', 'content', 'email'], true)) {
            $tab = 'ads';
        }

        // ===== FILTER DATE =====
        [$startDate, $endDate, $from, $to] = $this->resolveDateRange($request->input('from'), $request->input('to'));

        // Tab con của SEO: report|plan (link đổi nền tảng ở mọi tab đều mang theo giá trị này)
        $seoTab = is_string($request->input('seo_tab')) ? $request->input('seo_tab') : 'report';

        // ===== Platform filter (chỉ thực sự dùng trong ADS tab) =====
        $platform = is_string($request->input('platform')) ? $request->input('platform') : null;

        $platformMap = [
            'facebook' => 'Facebook',
            'google' => 'Google',
            'google_search' => 'Google',
            'tiktok' => 'Tiktok',
            'website' => 'Website',
            'youtube' => 'Youtube',
            'seo' => 'SEO Organic',
            'seo_organic' => 'SEO Organic',
        ];

        $platformDb = $platform ? ($platformMap[strtolower($platform)] ?? $platform) : null;

        // Render theo tab
        if ($tab === 'seo') {
            return $this->renderSeo($from, $to, $seoTab);
        }
        if ($tab === 'content' || $tab === 'email') {
            return $this->renderPlaceholder($from, $to, $seoTab);
        }

        // Default: ADS
        return $this->renderAds($startDate, $endDate, $from, $to, $seoTab, $platform, $platformDb);
    }

    // =========================================================
    // ADS TAB (Facebook/Google/Tiktok...)
    // =========================================================
    /**
     * Render tab Ads: KPI, trend theo ngày, tổng hợp theo tháng và top chiến dịch.
     *
     * Trước 2026-09-07 còn tổng hợp theo nền tảng và 3 breakdown nhân khẩu học (3 truy vấn)
     * nhưng view chưa bao giờ hiển thị — đã bỏ.
     */
    private function renderAds(Carbon $startDate, Carbon $endDate, string $from, string $to, string $seoTab, ?string $platform, ?string $platformDb)
    {
        // Budgets: dùng created_at như bạn đang làm
        $budgetsQ = DB::table('marketing_budgets')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($platformDb) {
            $budgetsQ->where('platform', $platformDb);
        }

        // Metrics: lọc overlap theo date_from/date_to
        $metricsQ = DB::table('marketing_metrics');
        $this->applyOverlapRange($metricsQ, $startDate, $endDate, 'date_from', 'date_to');

        if ($platformDb) {
            $metricsQ->where('platform', $platformDb);
        }

        // ===== KPI =====
        $budgetSpend = (clone $budgetsQ)->sum(DB::raw('COALESCE(actual_spent, 0)'));
        $metricSpend = (clone $metricsQ)->sum(DB::raw('COALESCE(spend, 0)'));
        $spendTotal = max((float) $budgetSpend, (float) $metricSpend);

        $reachSum = (clone $metricsQ)->sum(DB::raw('COALESCE(reach, 0)'));
        $leadsSum = (clone $metricsQ)->sum(DB::raw('COALESCE(leads, 0)'));
        $cpl = $leadsSum > 0 ? ($spendTotal / $leadsSum) : 0;

        // Dashboard có impressions/clicks nhưng DB chưa có -> để 0
        $imprSum = 0;
        $clickSum = 0;
        $ctr = 0;
        $cpc = 0;
        $frequency = 0;
        $cpm = 0;

        if ($platformDb === 'Facebook') {
            $frequency = ($reachSum > 0 && $imprSum > 0) ? ($imprSum / $reachSum) : 0;
            $cpm = ($imprSum > 0) ? (($spendTotal / $imprSum) * 1000) : 0;
        }
        if ($platformDb === 'Google') {
            $ctr = ($imprSum > 0) ? (($clickSum / $imprSum) * 100) : 0;
            $cpc = ($clickSum > 0) ? ($spendTotal / $clickSum) : 0;
        }

        // ===== Trend theo ngày (quan trọng cho chart) =====
        $trend = $this->buildDailyTrendFromMetrics($startDate, $endDate, $platformDb);

        // ===== BY MONTH =====
        $metricByMonth = DB::table('marketing_metrics as m')
            ->selectRaw("DATE_FORMAT(m.date_from, '%Y-%m') as month")
            ->selectRaw('SUM(COALESCE(m.spend,0)) as metric_spend')
            ->selectRaw('SUM(COALESCE(m.reach,0)) as reach')
            ->selectRaw('SUM(COALESCE(m.leads,0)) as leads')
            ->whereDate('m.date_from', '>=', $startDate->toDateString())
            ->whereDate('m.date_from', '<=', $endDate->toDateString())
            ->when($platformDb, fn ($q) => $q->where('m.platform', $platformDb))
            ->groupByRaw("DATE_FORMAT(m.date_from, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(m.date_from, '%Y-%m') asc")
            ->get()
            ->keyBy('month');

        $budgetByMonth = DB::table('marketing_budgets as b')
            ->selectRaw("DATE_FORMAT(b.created_at, '%Y-%m') as month")
            ->selectRaw('SUM(COALESCE(b.actual_spent,0)) as budget_spend')
            ->whereBetween('b.created_at', [$startDate, $endDate])
            ->when($platformDb, fn ($q) => $q->where('b.platform', $platformDb))
            ->groupByRaw("DATE_FORMAT(b.created_at, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(b.created_at, '%Y-%m') asc")
            ->get()
            ->keyBy('month');

        $months = collect($budgetByMonth->keys())
            ->merge($metricByMonth->keys())
            ->unique()
            ->sort()
            ->values();

        $byMonth = $months->map(function ($month) use ($budgetByMonth, $metricByMonth) {
            $b = $budgetByMonth->get($month);
            $m = $metricByMonth->get($month);

            $budgetSpend = (float) ($b->budget_spend ?? 0);
            $metricSpend = (float) ($m->metric_spend ?? 0);

            return (object) [
                'month' => $month,
                'spend' => max($budgetSpend, $metricSpend),
                'reach' => (float) ($m->reach ?? 0),
                'leads' => (int) ($m->leads ?? 0),
            ];
        });

        // ===== TOP CAMPAIGNS =====
        $budgetAgg = DB::table('marketing_budgets as b')
            ->selectRaw('b.campaign_id, SUM(COALESCE(b.actual_spent,0)) as spend')
            ->whereBetween('b.created_at', [$startDate, $endDate])
            ->when($platformDb, fn ($q) => $q->where('b.platform', $platformDb))
            ->whereNotNull('b.campaign_id')
            ->groupBy('b.campaign_id');

        $metricsAgg = DB::table('marketing_metrics as m')
            ->selectRaw('m.campaign_id, SUM(COALESCE(m.reach,0)) as reach, SUM(COALESCE(m.leads,0)) as leads')
            ->whereDate('m.date_from', '>=', $startDate->toDateString())
            ->whereDate('m.date_from', '<=', $endDate->toDateString())
            ->when($platformDb, fn ($q) => $q->where('m.platform', $platformDb))
            ->whereNotNull('m.campaign_id')
            ->groupBy('m.campaign_id');

        $topCampaigns = DB::query()
            ->fromSub($budgetAgg, 'bx')
            ->leftJoin('marketing_campaigns as c', 'c.id', '=', 'bx.campaign_id')
            ->leftJoinSub($metricsAgg, 'mx', fn ($join) => $join->on('mx.campaign_id', '=', 'bx.campaign_id'))
            ->selectRaw("COALESCE(c.name, 'Không rõ') as campaign_name")
            ->selectRaw('bx.spend as spend')
            ->selectRaw('COALESCE(mx.reach,0) as reach')
            ->selectRaw('COALESCE(mx.leads,0) as leads')
            ->orderByDesc('bx.spend')
            ->limit(8)
            ->get();

        return view('marketing.dashboard', $this->presenter->viewData(new MarketingDashboardInput(
            from: $from,
            to: $to,
            seoTab: $seoTab,
            platform: $platform,
            kpi: [
                'spend' => $spendTotal,
                'reach' => $reachSum,
                'leads' => $leadsSum,
                'cpl' => $cpl,

                'impressions' => $imprSum,
                'clicks' => $clickSum,
                'ctr' => $ctr,
                'cpc' => $cpc,
                'frequency' => $frequency,
                'cpm' => $cpm,
            ],
            trend: $trend,
            byMonth: $byMonth,
            topCampaigns: $topCampaigns,
        )));
    }

    // =========================================================
    // SEO TAB (wireframe ready)
    // =========================================================
    /**
     * Render tab SEO (wireframe, chưa có nguồn số liệu — presenter điền 0 và bảng rỗng).
     */
    private function renderSeo(string $from, string $to, string $seoTab)
    {
        return view('marketing.dashboard', $this->presenter->viewData(new MarketingDashboardInput(from: $from, to: $to, seoTab: $seoTab, platform: 'seo')));
    }

    // =========================================================
    // CONTENT / EMAIL TAB (placeholder)
    // =========================================================
    /**
     * Render tab Content và Email (placeholder: bố cục Ads với số liệu 0).
     */
    private function renderPlaceholder(string $from, string $to, string $seoTab)
    {
        return view('marketing.dashboard', $this->presenter->viewData(new MarketingDashboardInput(from: $from, to: $to, seoTab: $seoTab)));
    }

    // =========================================================
    // Helpers: Overlap filter
    // =========================================================
    /**
     * Thêm điều kiện lọc bản ghi có khoảng ngày giao với range đã chọn.
     */
    private function applyOverlapRange($query, Carbon $start, Carbon $end, string $fromCol, string $toCol)
    {
        $query->whereDate($fromCol, '<=', $end->toDateString())
            ->whereDate($toCol, '>=', $start->toDateString());
    }

    // =========================================================
    // Build daily trend (SAFE VERSION - không dùng clicks)
    // =========================================================
    /**
     * Dựng trend theo ngày từ marketing_metrics (chia đều spend/leads theo số ngày của bản ghi).
     *
     * @return array labels/spend/leads/cpl/clicks/cpc cho chart
     */
    private function buildDailyTrendFromMetrics(Carbon $startDate, Carbon $endDate, ?string $platformDb = null): array
    {
        $rows = DB::table('marketing_metrics')
            ->select('date_from', 'date_to', 'spend', 'leads')
            ->whereDate('date_from', '<=', $endDate->toDateString())
            ->whereDate('date_to', '>=', $startDate->toDateString())
            ->when($platformDb, fn ($q) => $q->where('platform', $platformDb))
            ->get();

        $labels = [];
        $map = [];
        $cursor = $startDate->copy()->startOfDay();

        while ($cursor->lte($endDate)) {
            $d = $cursor->toDateString();
            $labels[] = $d;
            $map[$d] = ['spend' => 0.0, 'leads' => 0.0];
            $cursor->addDay();
        }

        foreach ($rows as $r) {
            $df = Carbon::parse($r->date_from)->startOfDay();
            $dt = Carbon::parse($r->date_to)->startOfDay();

            if ($df->lt($startDate)) {
                $df = $startDate->copy();
            }
            if ($dt->gt($endDate)) {
                $dt = $endDate->copy();
            }
            if ($df->gt($dt)) {
                continue;
            }

            $days = max(1, $df->diffInDays($dt) + 1);

            $sPer = ((float) ($r->spend ?? 0)) / $days;
            $lPer = ((float) ($r->leads ?? 0)) / $days;

            $c = $df->copy();
            while ($c->lte($dt)) {
                $k = $c->toDateString();
                if (isset($map[$k])) {
                    $map[$k]['spend'] += $sPer;
                    $map[$k]['leads'] += $lPer;
                }
                $c->addDay();
            }
        }

        $spend = [];
        $leads = [];
        $cpl = [];

        foreach ($labels as $d) {
            $s = (float) round($map[$d]['spend']);
            $l = (float) round($map[$d]['leads']);

            $spend[] = $s;
            $leads[] = (int) $l;
            $cpl[] = $l > 0 ? ($s / $l) : 0;
        }

        // clicks/cpc trả về array 0 để blade + chart không lỗi
        $zeros = array_fill(0, count($labels), 0);

        return [
            'labels' => $labels,
            'spend' => $spend,
            'leads' => $leads,
            'cpl' => $cpl,
            'clicks' => $zeros,
            'cpc' => $zeros,
        ];
    }

    // =========================================================
    // Date parsing
    // =========================================================
    /**
     * Parse ngày từ UI, hỗ trợ Y-m, Y-m-d và d/m/Y.
     */
    private function parseUiDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m', $value)->startOfMonth();
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            $v = substr($value, 0, 10);
            try {
                return Carbon::createFromFormat('Y-m-d', $v);
            } catch (\Throwable $e) {
                try {
                    return Carbon::parse($value);
                } catch (\Throwable $e2) {
                    return null;
                }
            }
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $value)) {
            $v = substr($value, 0, 10);
            try {
                return Carbon::createFromFormat('d/m/Y', $v);
            } catch (\Throwable $e) {
                try {
                    return Carbon::parse($value);
                } catch (\Throwable $e2) {
                    return null;
                }
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Xác định khoảng ngày lọc, mặc định là cả tháng hiện tại.
     *
     * @return array [$start, $end, $fromStr, $toStr]
     */
    private function resolveDateRange(?string $fromRaw, ?string $toRaw): array
    {
        $from = $this->parseUiDate($fromRaw);
        $to = $this->parseUiDate($toRaw);

        if (! $from && ! $to) {
            $start = now()->startOfMonth()->startOfDay();
            $end = now()->endOfMonth()->endOfDay();

            return [$start, $end, $start->toDateString(), $end->toDateString()];
        }

        $start = ($from ?: $to)->copy()->startOfDay();
        $end = ($to ?: $from)->copy()->endOfDay();

        if ($fromRaw && preg_match('/^\d{4}-\d{2}$/', trim((string) $fromRaw))) {
            $start = $this->parseUiDate($fromRaw)->startOfMonth()->startOfDay();
        }
        if ($toRaw && preg_match('/^\d{4}-\d{2}$/', trim((string) $toRaw))) {
            $end = $this->parseUiDate($toRaw)->endOfMonth()->endOfDay();
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end, $start->toDateString(), $end->toDateString()];
    }
}
