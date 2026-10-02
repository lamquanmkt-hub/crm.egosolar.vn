<?php

declare(strict_types=1);

namespace App\View\Presenters\Marketing;

use App\DTOs\Marketing\MarketingDashboardInput;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị mọi giá trị cho view `marketing.dashboard`.
 *
 * Trước 2026-09-07 view tự tính 190 dòng trong `@php` (cờ nền tảng, KPI suy ra, kỳ trước,
 * VAT, phễu, tiến độ ngân sách) và 11 khối nhỏ chuẩn hoá từng dòng bảng. Gom về đây để
 * view chỉ in giá trị. Tên khoá trả về giữ đúng tên biến cũ của view nên markup không đổi;
 * kiểm bằng so HTML 17 trang trước/sau.
 *
 * Dòng bảng nhận cả mảng lẫn object, thiếu cột nào cũng được — view cũ chết
 * "Cannot use object of type stdClass as array" khi một object thiếu cột.
 */
final class MarketingDashboardPresenter
{
    private const VAT_RATE = 0.10;

    private const PACING_TEXT = [
        'over' => 'Chi tiêu đang vượt tiến độ',
        'under' => 'Chi tiêu đang thấp hơn tiến độ',
    ];

    private const PACING_TEXT_DEFAULT = 'Chi tiêu đang đúng tiến độ';

    public function __construct(private readonly MarketingNumberFormat $format) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(MarketingDashboardInput $input): array
    {
        $platform = $input->platform ?? '';
        $seoTab = $input->seoTab ?? 'report';
        $isSeo = in_array($platform, ['seo', 'seo_organic'], true);

        $kpi = $input->kpi;
        $kpiPrev = $input->kpiPrev ?? $this->array($kpi['prev'] ?? null);
        $spend = $this->float($kpi['spend'] ?? 0);
        $leads = $this->float($kpi['leads'] ?? 0);
        $impressions = $this->float($kpi['impressions'] ?? 0);
        $clicks = $this->float($kpi['clicks'] ?? 0);
        $conversions = $this->float($kpi['conversions'] ?? $leads);
        $spendPrev = $this->float($kpiPrev['spend'] ?? 0);
        $leadsPrev = $this->float($kpiPrev['leads'] ?? 0);
        $spendVat = $spend * (1 + self::VAT_RATE);

        $seoKpi = $input->seoKpi;
        $seoPrev = $input->seoKpiPrev ?? $this->array($seoKpi['prev'] ?? null);
        $seoClicks = $this->float($seoKpi['clicks'] ?? 0);
        $seoImpressions = $this->float($seoKpi['impressions'] ?? 0);
        $seoLeads = $this->float($seoKpi['leads'] ?? 0);

        $trend = $input->trend;
        $seoTrend = $input->seoTrend;

        return [
            'fmt' => $this->format,
            'from' => $input->from,
            'to' => $input->to,
            'platform' => $platform,
            'seoTab' => $seoTab,
            'isFacebook' => $platform === 'facebook',
            'isGoogle' => in_array($platform, ['google', 'google_search'], true),
            'isSEO' => $isSeo,
            'isSeoPlan' => $isSeo && $seoTab === 'plan',
            'isSeoReport' => $isSeo && $seoTab !== 'plan',

            // KPI Ads/Search kỳ này
            'spend' => $spend,
            'leads' => $leads,
            'reach' => $this->float($kpi['reach'] ?? 0),
            'impr' => $impressions,
            'freq' => $this->float($kpi['frequency'] ?? 0),
            'cpm' => $this->float($kpi['cpm'] ?? 0),
            'clicks' => $clicks,
            'ctr' => $this->float($kpi['ctr'] ?? 0),
            'cpc' => $this->float($kpi['cpc'] ?? 0),
            'cpl' => $this->float($kpi['cpl'] ?? $this->ratio($spend, $leads)),
            'cpa' => $this->float($kpi['cpa'] ?? $this->ratio($spend, $conversions)),
            'spendVat' => $spendVat,
            'vatMoney' => $spendVat - $spend,

            // KPI Ads/Search kỳ trước (backend chưa cấp thì 0 → huy hiệu "—")
            'spendPrev' => $spendPrev,
            'leadsPrev' => $leadsPrev,
            'cplPrev' => $this->float($kpiPrev['cpl'] ?? $this->ratio($spendPrev, $leadsPrev)),
            'clicksPrev' => $this->float($kpiPrev['clicks'] ?? 0),
            'ctrPrev' => $this->float($kpiPrev['ctr'] ?? 0),
            'cpcPrev' => $this->float($kpiPrev['cpc'] ?? 0),
            'reachPrev' => $this->float($kpiPrev['reach'] ?? 0),
            'imprPrev' => $this->float($kpiPrev['impressions'] ?? 0),

            // KPI SEO
            'seoSessions' => $this->float($seoKpi['sessions'] ?? 0),
            'seoClicks' => $seoClicks,
            'seoImpr' => $seoImpressions,
            'seoCtr' => $this->float($seoKpi['ctr'] ?? $this->percent($seoClicks, $seoImpressions)),
            'seoPos' => $this->float($seoKpi['avg_position'] ?? 0),
            'seoLeads' => $seoLeads,
            'seoCvr' => $this->float($seoKpi['cvr'] ?? $this->percent($seoLeads, $seoClicks)),
            'seoTop10' => $this->float($seoKpi['top10'] ?? 0),
            'seoValueEq' => $this->float($seoKpi['value_equivalent'] ?? 0),
            'seoSessionsPrev' => $this->float($seoPrev['sessions'] ?? 0),
            'seoClicksPrev' => $this->float($seoPrev['clicks'] ?? 0),
            'seoImprPrev' => $this->float($seoPrev['impressions'] ?? 0),
            'seoCtrPrev' => $this->float($seoPrev['ctr'] ?? 0),
            'seoPosPrev' => $this->float($seoPrev['avg_position'] ?? 0),
            'seoLeadsPrev' => $this->float($seoPrev['leads'] ?? 0),
            'seoTop10Prev' => $this->float($seoPrev['top10'] ?? 0),

            // Chuỗi theo ngày cho chart
            'trendLabels' => $trend['labels'] ?? [],
            'trendSpend' => $trend['spend'] ?? [],
            'trendLeads' => $trend['leads'] ?? [],
            'trendCpl' => $trend['cpl'] ?? [],
            'trendClicks' => $trend['clicks'] ?? [],
            'trendCpc' => $trend['cpc'] ?? [],
            'seoTrendLabels' => $seoTrend['labels'] ?? [],
            'seoTrendSess' => $seoTrend['sessions'] ?? [],
            'seoTrendClicks' => $seoTrend['clicks'] ?? [],
            'seoTrendLeads' => $seoTrend['leads'] ?? [],
            'seoTrendTop10' => $seoTrend['top10'] ?? [],

            ...$this->funnel($input->funnel, $impressions, $clicks, $leads),
            ...$this->budgetPacing($input->budget, $spend),

            'insights' => $this->insightItems($input->insights)->all(),
            'seoIssues' => $this->insightItems($input->seoIssues),
            'seoPlanKpi' => $input->seoPlanKpi,
            'seoTopQueries' => $this->rows($input->seoTopQueries, function (\Closure $field): array {
                $clicks = $this->float($field('clicks', 0));
                $impressions = $this->float($field('impressions', 0));

                return [
                    'query' => $field('query', '—'),
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $this->float($field('ctr') ?? $this->percent($clicks, $impressions)),
                    'position' => $this->float($field('position', 0)),
                ];
            }),
            'seoTopPages' => $this->rows($input->seoTopPages, function (\Closure $field): array {
                $sessions = $this->float($field('sessions', 0));
                $leads = $this->float($field('leads', 0));

                return [
                    'url' => $field('url', '—'),
                    'sessions' => $sessions,
                    'leads' => $leads,
                    'cvr' => $this->float($field('cvr') ?? $this->percent($leads, $sessions)),
                ];
            }),
            'seoPlanItems' => $this->rows($input->seoPlanItems, fn (\Closure $field): array => [
                'type' => $field('type', 'Content'),
                'title' => $field('title', '—'),
                'cluster' => $field('cluster', '—'),
                'url' => $field('url', '—'),
                'owner' => $field('owner', '—'),
                'due_date' => $field('due_date', '—'),
                'status' => $field('status', 'Chưa làm'),
            ]),
            'byMonth' => $this->rows($input->byMonth, fn (\Closure $field): array => [
                'month' => $field('month'),
                'impressions' => $this->float($field('impressions', 0)),
                'reach' => $this->float($field('reach', 0)),
                ...$this->spendLeadsCpl($field),
            ]),
            'byDevice' => $this->rows($input->byDevice, fn (\Closure $field): array => [
                'device' => $field('device', '—'),
                'clicks' => $this->float($field('clicks', 0)),
                ...$this->spendLeadsCpl($field),
            ]),
            'byGeo' => $this->rows($input->byGeo, fn (\Closure $field): array => [
                'location' => $field('location', '—'),
                ...$this->spendLeadsCpl($field),
            ]),
            'topAds' => $this->rows($input->topAds, fn (\Closure $field): array => [
                'name' => $field('ad_name') ?? $field('creative_name', '—'),
                ...$this->spendLeadsCpl($field),
            ]),
            'topCampaigns' => collect($input->topCampaigns),
            'topKeywords' => collect($input->topKeywords),
        ];
    }

    /**
     * Phễu Impressions → Clicks → Leads → Qualified → Appointments: bề rộng thanh (% so với
     * tầng đầu) và tỉ lệ chuyển đổi giữa các tầng. Thiếu số phễu riêng thì dùng KPI chung.
     *
     * @return array<string, mixed>
     */
    private function funnel(array $funnel, float $impressions, float $clicks, float $leads): array
    {
        $fImpressions = $this->float($funnel['impressions'] ?? $impressions);
        $fClicks = $this->float($funnel['clicks'] ?? $clicks);
        $fLeads = $this->float($funnel['leads'] ?? $leads);
        $fQualified = $this->float($funnel['qualified'] ?? 0);
        $fAppointments = $this->float($funnel['appointments'] ?? 0);
        $base = max(1, $fImpressions ?: 1);

        return [
            'funnelImpressions' => $fImpressions,
            'funnelClicks' => $fClicks,
            'funnelLeads' => $fLeads,
            'funnelQualified' => $fQualified,
            'funnelAppointments' => $fAppointments,
            'funnelWidths' => [
                'impressions' => min(100, ($fImpressions / $base) * 100),
                'clicks' => min(100, ($fClicks / $base) * 100),
                'leads' => min(100, ($fLeads / $base) * 100),
                'qualified' => $fQualified > 0 ? min(100, ($fQualified / $base) * 100) : 0,
                'appointments' => $fAppointments > 0 ? min(100, ($fAppointments / $base) * 100) : 0,
            ],
            'funnelRates' => [
                'click' => $this->percent($fClicks, $fImpressions),
                'lead' => $this->percent($fLeads, $fClicks),
                'qualified' => $this->percent($fQualified, $fLeads),
            ],
        ];
    }

    /**
     * Tiến độ ngân sách tháng: % đã chi (trần 100), trạng thái ok|over|under và câu mô tả.
     *
     * @return array<string, mixed>
     */
    private function budgetPacing(array $budget, float $spend): array
    {
        $monthBudget = $this->float($budget['month_budget'] ?? 0);
        $spentToDate = $this->float($budget['spent'] ?? $spend);
        $status = $budget['status'] ?? 'ok';

        return [
            'monthBudget' => $monthBudget,
            'spentToDate' => $spentToDate,
            'forecast' => $this->float($budget['forecast'] ?? 0),
            'pacingPct' => $monthBudget > 0 ? min(100, ($spentToDate / $monthBudget) * 100) : 0,
            'pacingStatus' => $status,
            'pacingStatusText' => self::PACING_TEXT[$status] ?? self::PACING_TEXT_DEFAULT,
            'pacingNote' => $budget['note'] ?? null,
        ];
    }

    /**
     * Mục cảnh báo/insight: luôn có `level` (mặc định info) và `text`.
     *
     * @return Collection<int, array{level: mixed, text: mixed}>
     */
    private function insightItems(mixed $items): Collection
    {
        return collect($items)->map(fn ($item) => [
            'level' => $this->field($item, 'level', 'info'),
            'text' => $this->field($item, 'text', ''),
        ])->values();
    }

    /**
     * Chuẩn hoá từng dòng bảng thành object đủ cột; `$normalise` nhận closure đọc cột.
     *
     * @param  \Closure(\Closure(string, mixed=): mixed): array<string, mixed>  $normalise
     * @return Collection<int, object>
     */
    private function rows(mixed $rows, \Closure $normalise): Collection
    {
        return collect($rows)->map(function ($row) use ($normalise): object {
            $field = fn (string $key, mixed $default = null): mixed => $this->field($row, $key, $default);

            return (object) $normalise($field);
        })->values();
    }

    /** @return array{spend: float, leads: float, cpl: float} */
    private function spendLeadsCpl(\Closure $field): array
    {
        $spend = $this->float($field('spend', 0));
        $leads = $this->float($field('leads', 0));

        return ['spend' => $spend, 'leads' => $leads, 'cpl' => $this->ratio($spend, $leads)];
    }

    private function field(mixed $row, string $key, mixed $default = null): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? $default;
        }
        if (is_object($row)) {
            return $row->{$key} ?? $default;
        }

        return $default;
    }

    private function ratio(float $numerator, float $denominator): float
    {
        return $denominator > 0 ? $numerator / $denominator : 0;
    }

    private function percent(float $numerator, float $denominator): float
    {
        return $denominator > 0 ? ($numerator / $denominator) * 100 : 0;
    }

    /** @return array<string, mixed> */
    private function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function float(mixed $value): float
    {
        return (float) $value;
    }
}
