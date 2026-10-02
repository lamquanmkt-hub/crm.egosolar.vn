<?php

declare(strict_types=1);

namespace App\DTOs\Marketing;

/**
 * Đầu vào của {@see \App\View\Presenters\Marketing\MarketingDashboardPresenter}: số liệu controller
 * đã truy vấn (Ads) hoặc còn trống (SEO/Content/Email chưa có nguồn). Mọi trường đều tuỳ chọn để
 * bật thêm nguồn sau (phễu, ngân sách, insight, bảng SEO) không phải đổi chữ ký.
 *
 * @param  array<string, mixed>  $kpi  spend, reach, leads, cpl, impressions, clicks, ctr, cpc, frequency, cpm (+ prev)
 * @param  array<string, mixed>|null  $kpiPrev  kỳ trước; thiếu thì lấy `$kpi['prev']`
 * @param  array<string, list<mixed>>  $trend  labels, spend, leads, cpl, clicks, cpc
 */
final readonly class MarketingDashboardInput
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public ?string $platform = null,
        public ?string $seoTab = null,
        public array $kpi = [],
        public ?array $kpiPrev = null,
        public array $trend = [],
        public array $seoKpi = [],
        public ?array $seoKpiPrev = null,
        public array $seoTrend = [],
        public array $funnel = [],
        public array $budget = [],
        public iterable $insights = [],
        public iterable $seoIssues = [],
        public array $seoPlanKpi = [],
        public iterable $seoTopQueries = [],
        public iterable $seoTopPages = [],
        public iterable $seoPlanItems = [],
        public iterable $byMonth = [],
        public iterable $byDevice = [],
        public iterable $byGeo = [],
        public iterable $topAds = [],
        public iterable $topCampaigns = [],
        public iterable $topKeywords = [],
    ) {}
}
