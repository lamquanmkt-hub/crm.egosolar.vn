@extends('layouts.app')

@section('title', 'Marketing Dashboard')

@section('content')

{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="container-fluid tw:px-6 tw:py-4">

    {{-- HERO --}}
    <div class="ego-hero tw:mt-4 tw:mb-4">
        <div class="tw:flex tw:items-start tw:justify-between flex-wrap tw:gap-2">
            <div class="tw:flex tw:items-center tw:gap-2">
                <div class="ego-hero-ic"><i class="bi bi-megaphone"></i></div>
                <div>
                    <div class="ego-hero-title">Marketing Dashboard</div>
                    <div class="ego-hero-sub">
                        @if($isSEO)
                            SEO Organic · Báo cáo & lập kế hoạch SEO ngay trên CRM
                        @else
                            FB Lead Form + Google Search · Tổng hợp ngân sách, hiệu quả & tối ưu
                        @endif
                    </div>
                </div>
            </div>

            <div class="tw:flex tw:gap-2 tw:items-center flex-wrap">
                <div class="ego-seg" role="group" aria-label="Platform">
                    <a class="ego-seg-btn {{ $isFacebook ? 'active' : '' }}"
                       href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'facebook'])) }}">
                        <i class="bi bi-facebook me-1"></i> Facebook Ads
                    </a>
                    <a class="ego-seg-btn {{ $isGoogle ? 'active' : '' }}"
                       href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'google_search'])) }}">
                        <i class="bi bi-google me-1"></i> Google Search
                    </a>
                    <a class="ego-seg-btn {{ $isSEO ? 'active' : '' }}"
                       href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'seo','seo_tab'=>($seoTab ?? 'report')])) }}">
                        <i class="bi bi-search me-1"></i> SEO Organic
                    </a>
                </div>

                @if($isSEO)
                    <div class="ego-seg" role="group" aria-label="SEO Tabs">
                        <a class="ego-seg-btn {{ $isSeoReport ? 'active' : '' }}"
                           href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'seo','seo_tab'=>'report'])) }}">
                            <i class="bi bi-graph-up me-1"></i> Báo cáo
                        </a>
                        <a class="ego-seg-btn {{ $isSeoPlan ? 'active' : '' }}"
                           href="{{ route('marketing.dashboard', array_merge(request()->query(), ['platform'=>'seo','seo_tab'=>'plan'])) }}">
                            <i class="bi bi-list-check me-1"></i> Lập kế hoạch
                        </a>
                    </div>
                @endif

                <x-ui.button variant="outline-secondary" href="{{ route('marketing.budget') }}" size="none" class="ego-btn tw:text-[16px]/[24px]">
                    <i class="bi bi-wallet2 me-1"></i> Ngân sách + chỉ số
                </x-ui.button>
                <x-ui.button variant="outline-secondary" href="{{ route('marketing.reports.content-calendar') }}" size="none" class="ego-btn tw:text-[16px]/[24px]">
                    <i class="bi bi-calendar2-week me-1"></i> Lịch biên tập
                </x-ui.button>
            </div>
        </div>
    </div>

    {{-- FILTER --}}
    <x-ui.card class="ego-card tw:mb-4">
        <x-ui.card-body>
            <div class="tw:flex tw:items-center tw:gap-2 tw:mb-2">
                <i class="bi bi-funnel"></i>
                <div class="tw:font-semibold">Bộ lọc</div>
                <div class="tw:text-[rgba(33,37,41,0.75)] small">Chọn khoảng thời gian & nền tảng</div>
            </div>

            <form method="GET" action="{{ route('marketing.dashboard') }}">
                <div class="tw:row tw:g-2 tw:items-end">
                    <div class="tw:md:col12-3">
                        <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Từ ngày</x-ui.label>
                        <x-ui.input type="date" class="ego-input" name="from" value="{{ $from ?? '' }}" />
                    </div>
                    <div class="tw:md:col12-3">
                        <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Đến ngày</x-ui.label>
                        <x-ui.input type="date" class="ego-input" name="to" value="{{ $to ?? '' }}" />
                    </div>

                    <div class="tw:md:col12-3">
                        <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Nền tảng</x-ui.label>
                        <x-ui.select class="ego-input" name="platform">
                            <option value="">-- Tất cả --</option>
                            <option value="facebook" {{ ($platform ?? '') === 'facebook' ? 'selected' : '' }}>Facebook</option>
                            <option value="google_search" {{ ($platform ?? '') === 'google_search' ? 'selected' : '' }}>Google Search</option>
                            <option value="seo" {{ ($platform ?? '') === 'seo' ? 'selected' : '' }}>SEO Organic</option>
                            <option value="tiktok" {{ ($platform ?? '') === 'tiktok' ? 'selected' : '' }}>Tiktok</option>
                            <option value="website" {{ ($platform ?? '') === 'website' ? 'selected' : '' }}>Website</option>
                            <option value="youtube" {{ ($platform ?? '') === 'youtube' ? 'selected' : '' }}>YouTube</option>
                        </x-ui.select>
                        @if($isSEO)
                            <input type="hidden" name="seo_tab" value="{{ $seoTab }}">
                        @endif
                    </div>

                    <div class="tw:md:col12-3">
                        <x-ui.button variant="success" type="submit" size="none" class="tw:w-full ego-btn-primary tw:text-[16px]/[24px]">
                            <i class="bi bi-funnel me-1"></i> Lọc dữ liệu
                        </x-ui.button>
                    </div>
                </div>
            </form>

            <div class="tw:flex tw:gap-2 flex-wrap tw:mt-4">
                <span class="ego-mini-pill"><i class="bi bi-calendar3 me-1"></i> Kỳ: {{ $from ?? '—' }} → {{ $to ?? '—' }}</span>
                @if($isSEO)
                    <span class="ego-mini-pill"><i class="bi bi-search me-1"></i> Kênh: SEO Organic</span>
                    <span class="ego-mini-pill"><i class="bi bi-ui-checks me-1"></i> Chế độ: {{ $isSeoPlan ? 'Lập kế hoạch' : 'Báo cáo' }}</span>
                @elseif($isFacebook)
                    <span class="ego-mini-pill"><i class="bi bi-ui-checks me-1"></i> Mục tiêu: Lead Form</span>
                @elseif($isGoogle)
                    <span class="ego-mini-pill"><i class="bi bi-search me-1"></i> Kênh: Search</span>
                @else
                    <span class="ego-mini-pill"><i class="bi bi-stack me-1"></i> Tổng hợp đa kênh</span>
                @endif
            </div>
        </x-ui.card-body>
    </x-ui.card>

    {{-- =========================================================
         SEO MODULE (Wireframe UI)
         ========================================================= --}}
    @if($isSEO)

        @if($isSeoReport)
            {{-- SEO KPI --}}
            <div class="tw:row tw:g-3 tw:mb-4">
                <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                    <x-ui.card class="ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Organic Sessions</div>
                                <div class="ego-kpi-value">{{ $fmt->number($seoSessions) }}</div>
                                <div class="ego-kpi-sub">
                                    Lượt truy cập tự nhiên
                                    <span class="tw:ml-2">{{ $fmt->deltaBadge($seoSessions, $seoSessionsPrev) }}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-bar-chart"></i></div>
                        </div>
                    </x-ui.card>
                </div>

                <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                    <x-ui.card class="ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Clicks (GSC)</div>
                                <div class="ego-kpi-value">{{ $fmt->number($seoClicks) }}</div>
                                <div class="ego-kpi-sub">
                                    Nhấp tự nhiên
                                    <span class="tw:ml-2">{{ $fmt->deltaBadge($seoClicks, $seoClicksPrev) }}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-cursor"></i></div>
                        </div>
                    </x-ui.card>
                </div>

                <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                    <x-ui.card class="ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Impressions (GSC)</div>
                                <div class="ego-kpi-value">{{ $fmt->number($seoImpr) }}</div>
                                <div class="ego-kpi-sub">
                                    Hiển thị tự nhiên
                                    <span class="tw:ml-2">{{ $fmt->deltaBadge($seoImpr, $seoImprPrev) }}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-eye"></i></div>
                        </div>
                    </x-ui.card>
                </div>

                <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                    <x-ui.card class="ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">CTR (GSC)</div>
                                <div class="ego-kpi-value">{{ \App\Support\DisplayFormat::percent($seoCtr, 2) }}</div>
                                <div class="ego-kpi-sub">
                                    Tỉ lệ nhấp
                                    <span class="tw:ml-2">{{ $fmt->deltaBadge($seoCtr, $seoCtrPrev) }}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-percent"></i></div>
                        </div>
                    </x-ui.card>
                </div>

                <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                    <x-ui.card class="ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Avg Position</div>
                                <div class="ego-kpi-value">{{ number_format($seoPos, 2, ',', '.') }}</div>
                                <div class="ego-kpi-sub">
                                    Vị trí trung bình (thấp hơn là tốt)
                                    <span class="tw:ml-2">{{ $fmt->deltaBadge($seoPosPrev, $seoPos) }}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-geo-alt"></i></div>
                        </div>
                    </x-ui.card>
                </div>

                <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                    <x-ui.card class="ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Leads (Organic)</div>
                                <div class="ego-kpi-value">{{ $fmt->number($seoLeads) }}</div>
                                <div class="ego-kpi-sub">
                                    Lead từ SEO
                                    <span class="tw:ml-2">{{ $fmt->deltaBadge($seoLeads, $seoLeadsPrev) }}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-person-plus"></i></div>
                        </div>
                    </x-ui.card>
                </div>

                <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                    <x-ui.card class="ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">CVR (Lead/Click)</div>
                                <div class="ego-kpi-value">{{ \App\Support\DisplayFormat::percent($seoCvr, 2) }}</div>
                                <div class="ego-kpi-sub">Tỉ lệ chuyển đổi tự nhiên</div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-lightning-charge"></i></div>
                        </div>
                    </x-ui.card>
                </div>

                <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                    <x-ui.card class="ego-kpi">
                        <div class="ego-kpi-head">
                            <div>
                                <div class="ego-kpi-label">Keywords TOP 10</div>
                                <div class="ego-kpi-value">{{ $fmt->number($seoTop10) }}</div>
                                <div class="ego-kpi-sub">
                                    Độ phủ từ khóa
                                    <span class="tw:ml-2">{{ $fmt->deltaBadge($seoTop10, $seoTop10Prev) }}</span>
                                </div>
                            </div>
                            <div class="ego-kpi-ic"><i class="bi bi-award"></i></div>
                        </div>
                    </x-ui.card>
                </div>
            </div>

            {{-- SEO MAIN: Trend + Issues --}}
            <div class="tw:row tw:g-3 tw:mb-4">
                <div class="tw:min-[62rem]:col12-8">
                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header tw:flex tw:items-center tw:justify-between flex-wrap tw:gap-2">
                            <div class="tw:font-semibold">Xu hướng theo ngày (SEO)</div>
                            <div class="tw:flex tw:gap-2 flex-wrap">
                                <span class="ego-mini-pill">Sessions · Clicks · Leads · TOP10</span>
                                <span class="ego-mini-pill tw:text-[rgba(33,37,41,0.75)]!">So sánh kỳ trước (nếu có)</span>
                            </div>
                        </x-ui.card-header>
                        <x-ui.card-body>
                            @if(is_array($seoTrendLabels) && count($seoTrendLabels))
                                <div class="ego-chart-wrap">
                                    <canvas id="egoSeoTrendChart" height="110"></canvas>
                                </div>
                                <div class="ego-chart-legend tw:mt-2">
                                    <span class="ego-dot"></span> Sessions
                                    <span class="ms-3"><span class="ego-dot alt"></span> Clicks</span>
                                    <span class="ms-3"><span class="ego-dot alt2"></span> Leads</span>
                                    <span class="ms-3"><span class="ego-dot alt3"></span> TOP10</span>
                                </div>
                            @else
                                <div class="ego-empty">
                                    <div class="ego-empty-ic"><i class="bi bi-graph-up"></i></div>
                                    <div class="tw:font-semibold">Chưa có dữ liệu xu hướng SEO</div>
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Gợi ý: đồng bộ GA4/GSC hoặc import CSV để hiển thị biểu đồ.</div>
                                </div>
                            @endif
                        </x-ui.card-body>
                    </x-ui.card>
                </div>

                <div class="tw:min-[62rem]:col12-4">
                    <x-ui.card class="ego-card tw:mb-4">
                        <x-ui.card-header class="ego-card-header tw:flex tw:items-center tw:justify-between">
                            <div class="tw:font-semibold">Technical / Content Issues</div>
                            <span class="ego-mini-pill">{{ ($seoIssues ?? collect())->count() }}</span>
                        </x-ui.card-header>
                        <x-ui.card-body>
                            @if(($seoIssues ?? collect())->count())
                                <div class="ego-insights">
                                    @foreach($seoIssues as $it)
                                        <div class="ego-insight {{ $it['level'] }}">
                                            <div class="ego-insight-ic">
                                                @if($it['level']==='danger')
                                                    <i class="bi bi-exclamation-octagon"></i>
                                                @elseif($it['level']==='warn')
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                @elseif($it['level']==='ok')
                                                    <i class="bi bi-check2-circle"></i>
                                                @else
                                                    <i class="bi bi-info-circle"></i>
                                                @endif
                                            </div>
                                            <div class="ego-insight-txt">{{ $it['text'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                    Wireframe gợi ý các rule:
                                    <ul class="tw:mb-0">
                                        <li>CTR giảm &gt; 15%</li>
                                        <li>Avg position xấu đi &gt; 2 bậc</li>
                                        <li>Landing có traffic tăng nhưng lead giảm</li>
                                        <li>Nhiều trang 404 / redirect chain</li>
                                    </ul>
                                </div>
                            @endif
                        </x-ui.card-body>
                    </x-ui.card>

                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header">
                            <div class="tw:font-semibold">Giá trị traffic (ước tính)</div>
                        </x-ui.card-header>
                        <x-ui.card-body>
                            <div class="tw:flex tw:items-center tw:justify-between">
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Value equivalent</div>
                                <div class="tw:font-semibold">{{ $seoValueEq > 0 ? $fmt->money($seoValueEq) : '—' }}</div>
                            </div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mt-2">
                                Wireframe: có thể tính bằng CPC trung bình Google Ads * Clicks SEO.
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>
            </div>

            {{-- SEO TABLES: Queries + Pages --}}
            <div class="tw:row tw:g-3">
                <div class="tw:min-[62rem]:col12-7">
                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header tw:flex tw:items-center tw:justify-between flex-wrap tw:gap-2">
                            <div class="tw:font-semibold">Top Queries (GSC)</div>
                            <span class="ego-mini-pill">Clicks · Impr · CTR · Position</span>
                        </x-ui.card-header>
                        <x-ui.card-body class="tw:p-0">
                            <div class="table-responsive">
                                <table class="table table-hover tw:mb-0 align-middle ego-table">
                                    <thead>
                                    <tr>
                                        <th>Query</th>
                                        <th class="tw:text-right" style="width:90px;">Clicks</th>
                                        <th class="tw:text-right" style="width:110px;">Impr</th>
                                        <th class="tw:text-right" style="width:90px;">CTR</th>
                                        <th class="tw:text-right" style="width:110px;">Pos</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(($seoTopQueries ?? collect())->count())
                                        @foreach($seoTopQueries as $r)
                                            <tr>
                                                <td class="tw:font-semibold">{{ $r->query }}</td>
                                                <td class="tw:text-right">{{ $fmt->number($r->clicks) }}</td>
                                                <td class="tw:text-right">{{ $fmt->number($r->impressions) }}</td>
                                                <td class="tw:text-right">{{ \App\Support\DisplayFormat::percent($r->ctr, 2) }}</td>
                                                <td class="tw:text-right">{{ number_format($r->position, 2, ',', '.') }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="5" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">Chưa có dữ liệu query (wireframe)</td></tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>

                <div class="tw:min-[62rem]:col12-5">
                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header tw:flex tw:items-center tw:justify-between flex-wrap tw:gap-2">
                            <div class="tw:font-semibold">Top Landing Pages</div>
                            <span class="ego-mini-pill">Sessions · Clicks · Leads</span>
                        </x-ui.card-header>
                        <x-ui.card-body class="tw:p-0">
                            <div class="table-responsive">
                                <table class="table table-hover tw:mb-0 align-middle ego-table">
                                    <thead>
                                    <tr>
                                        <th>URL</th>
                                        <th class="tw:text-right" style="width:90px;">Sessions</th>
                                        <th class="tw:text-right" style="width:90px;">Leads</th>
                                        <th class="tw:text-right" style="width:90px;">CVR</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(($seoTopPages ?? collect())->count())
                                        @foreach($seoTopPages as $r)
                                            <tr>
                                                <td class="tw:font-semibold tw:truncate" style="max-width:240px;" title="{{ $r->url }}">{{ $r->url }}</td>
                                                <td class="tw:text-right">{{ $fmt->number($r->sessions) }}</td>
                                                <td class="tw:text-right">{{ $fmt->number($r->leads) }}</td>
                                                <td class="tw:text-right">{{ \App\Support\DisplayFormat::percent($r->cvr, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="4" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">Chưa có dữ liệu landing (wireframe)</td></tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>
            </div>
        @endif

        @if($isSeoPlan)
            {{-- SEO PLAN HEADER KPI --}}
            <div class="tw:row tw:g-3 tw:mb-4">
                <div class="tw:min-[62rem]:col12-8">
                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header tw:flex tw:items-center tw:justify-between flex-wrap tw:gap-2">
                            <div class="tw:font-semibold">Mục tiêu SEO (Kỳ đã chọn)</div>
                            <span class="ego-mini-pill">Wireframe: editable form</span>
                        </x-ui.card-header>
                        <x-ui.card-body>
                            <div class="tw:row tw:g-2">
                                <div class="tw:md:col12-3">
                                    <div class="ego-plan-field">
                                        <div class="ego-plan-label">Traffic target</div>
                                        <div class="ego-plan-val">{{ $fmt->number($seoPlanKpi['traffic_target'] ?? 0) }}</div>
                                        <div class="ego-plan-sub tw:text-[rgba(33,37,41,0.75)] small">sessions</div>
                                    </div>
                                </div>
                                <div class="tw:md:col12-3">
                                    <div class="ego-plan-field">
                                        <div class="ego-plan-label">Leads target</div>
                                        <div class="ego-plan-val">{{ $fmt->number($seoPlanKpi['lead_target'] ?? 0) }}</div>
                                        <div class="ego-plan-sub tw:text-[rgba(33,37,41,0.75)] small">leads</div>
                                    </div>
                                </div>
                                <div class="tw:md:col12-3">
                                    <div class="ego-plan-field">
                                        <div class="ego-plan-label">TOP10 target</div>
                                        <div class="ego-plan-val">{{ $fmt->number($seoPlanKpi['top10_target'] ?? 0) }}</div>
                                        <div class="ego-plan-sub tw:text-[rgba(33,37,41,0.75)] small">keywords</div>
                                    </div>
                                </div>
                                <div class="tw:md:col12-3">
                                    <div class="ego-plan-field">
                                        <div class="ego-plan-label">Revenue target</div>
                                        <div class="ego-plan-val">{{ $fmt->money($seoPlanKpi['revenue_target'] ?? 0) }}</div>
                                        <div class="ego-plan-sub tw:text-[rgba(33,37,41,0.75)] small">ước tính</div>
                                    </div>
                                </div>
                            </div>

                            @if(!empty($seoPlanKpi['note']))
                                <div class="tw:mt-4 ego-note">
                                    <i class="bi bi-lightbulb me-1"></i>{{ $seoPlanKpi['note'] }}
                                </div>
                            @else
                                <div class="tw:mt-4 tw:text-[rgba(33,37,41,0.75)] small">
                                    Wireframe: nút <b>Chỉnh mục tiêu</b> (modal) để nhập target theo tháng/quý + phân bổ theo cụm keyword.
                                </div>
                            @endif
                        </x-ui.card-body>
                    </x-ui.card>
                </div>

                <div class="tw:min-[62rem]:col12-4">
                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header">
                            <div class="tw:font-semibold">Trạng thái triển khai</div>
                        </x-ui.card-header>
                        <x-ui.card-body>
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Wireframe checklist:</div>
                            <div class="tw:mt-2 ego-checklist">
                                <div class="ego-check"><i class="bi bi-circle"></i> Audit kỹ thuật</div>
                                <div class="ego-check"><i class="bi bi-circle"></i> Mapping keyword → landing</div>
                                <div class="ego-check"><i class="bi bi-circle"></i> Content plan</div>
                                <div class="ego-check"><i class="bi bi-circle"></i> Internal link</div>
                                <div class="ego-check"><i class="bi bi-circle"></i> Backlink / Entity</div>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>
            </div>

            {{-- SEO PLAN ITEMS --}}
            <x-ui.card class="ego-card tw:mb-4">
                <x-ui.card-header class="ego-card-header tw:flex tw:items-center tw:justify-between flex-wrap tw:gap-2">
                    <div class="tw:font-semibold">Backlog công việc SEO</div>
                    <div class="tw:flex tw:gap-2 flex-wrap">
                        <span class="ego-mini-pill">Content · Landing · Technical · Backlink</span>
                        <span class="ego-mini-pill tw:text-[rgba(33,37,41,0.75)]!">Wireframe: filter theo status/owner</span>
                    </div>
                </x-ui.card-header>
                <x-ui.card-body class="tw:p-0">
                    <div class="table-responsive">
                        <table class="table table-hover tw:mb-0 align-middle ego-table">
                            <thead>
                            <tr>
                                <th style="width:110px;">Loại</th>
                                <th>Hạng mục</th>
                                <th style="width:160px;">Cluster</th>
                                <th style="width:160px;">URL</th>
                                <th style="width:120px;">Owner</th>
                                <th class="tw:text-right" style="width:120px;">Deadline</th>
                                <th class="tw:text-right" style="width:130px;">Trạng thái</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if(($seoPlanItems ?? collect())->count())
                                @foreach($seoPlanItems as $r)
                                    <tr>
                                        <td class="tw:font-semibold">{{ $r->type }}</td>
                                        <td class="tw:font-semibold">{{ $r->title }}</td>
                                        <td>{{ $r->cluster }}</td>
                                        <td class="tw:truncate" style="max-width:160px;" title="{{ $r->url }}">{{ $r->url }}</td>
                                        <td>{{ $r->owner }}</td>
                                        <td class="tw:text-right">{{ $r->due_date }}</td>
                                        <td class="tw:text-right">
                                            <span class="ego-status-chip {{ \Illuminate\Support\Str::slug($r->status) }}">{{ $r->status }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="7" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">
                                        Wireframe: danh sách task SEO (content/landing/tech/backlink). Chưa có dữ liệu.
                                    </td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </x-ui.card-body>
            </x-ui.card>

            {{-- SEO PLAN: quick templates --}}
            <div class="tw:row tw:g-3">
                <div class="tw:min-[62rem]:col12-6">
                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header">
                            <div class="tw:font-semibold">Mẫu kế hoạch Content</div>
                        </x-ui.card-header>
                        <x-ui.card-body>
                            <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mb-2">Wireframe gợi ý cấu trúc:</div>
                            <div class="ego-note">
                                <ul class="tw:mb-0">
                                    <li>2 Landing “Báo giá / Chi phí” + “Lắp đặt” (đẩy lead)</li>
                                    <li>4 Blog cluster cho “áp mái / gia đình / nhà xưởng / hòa lưới”</li>
                                    <li>1 Case study dự án / tuần</li>
                                    <li>FAQ schema cho 5 trang money-keyword</li>
                                </ul>
                            </div>
                            <div class="tw:mt-4 tw:text-[rgba(33,37,41,0.75)] small">Wireframe: nút “Tạo kế hoạch từ template”.</div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>
                <div class="tw:min-[62rem]:col12-6">
                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header">
                            <div class="tw:font-semibold">Mẫu Technical SEO</div>
                        </x-ui.card-header>
                        <x-ui.card-body>
                            <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mb-2">Wireframe checklist:</div>
                            <div class="ego-note">
                                <ul class="tw:mb-0">
                                    <li>Core Web Vitals ≥ 80</li>
                                    <li>Index coverage sạch lỗi</li>
                                    <li>Sitemap/robots đúng</li>
                                    <li>Redirect chain & broken links = 0</li>
                                    <li>Structured data: Organization + FAQ + Breadcrumb</li>
                                </ul>
                            </div>
                            <div class="tw:mt-4 tw:text-[rgba(33,37,41,0.75)] small">Wireframe: nút “Tạo task kỹ thuật”.</div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>
            </div>
        @endif

    @else
    {{-- =========================================================
         EXISTING ADS/SEARCH DASHBOARD (your original)
         ========================================================= --}}

    {{-- KPI --}}
    <div class="tw:row tw:g-3 tw:mb-4">
        {{-- Spend (VAT) --}}
        <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
            <x-ui.card class="ego-kpi">
                <div class="ego-kpi-head">
                    <div>
                        <div class="ego-kpi-label">
                            Tổng chi tiêu <span class="ego-badge-vat tw:ml-2">+VAT 10%</span>
                        </div>
                        <div class="ego-kpi-value ego-kpi-value-strong">{{ $fmt->money($spend) }}</div>
                        <div class="ego-kpi-sub">
                            Sau VAT: <span class="ego-kpi-sub-strong">{{ $fmt->money($spendVat) }}</span>
                            · VAT: <span class="ego-kpi-sub-strong">{{ $fmt->money($vatMoney) }}</span>
                            <span class="tw:ml-2">{{ $fmt->deltaBadge($spend, $spendPrev) }}</span>
                        </div>
                    </div>
                    <div class="ego-kpi-ic"><i class="bi bi-cash-coin"></i></div>
                </div>
            </x-ui.card>
        </div>

        {{-- Common: Leads --}}
        <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
            <x-ui.card class="ego-kpi">
                <div class="ego-kpi-head">
                    <div>
                        <div class="ego-kpi-label">Leads</div>
                        <div class="ego-kpi-value">{{ $fmt->number($leads) }}</div>
                        <div class="ego-kpi-sub">
                            Tổng lead form
                            <span class="tw:ml-2">{{ $fmt->deltaBadge($leads, $leadsPrev) }}</span>
                        </div>
                    </div>
                    <div class="ego-kpi-ic"><i class="bi bi-person-plus"></i></div>
                </div>
            </x-ui.card>
        </div>

        {{-- Common: CPL/CPA --}}
        <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
            <x-ui.card class="ego-kpi">
                <div class="ego-kpi-head">
                    <div>
                        <div class="ego-kpi-label">{{ $isGoogle ? 'CPA' : 'CPL' }}</div>
                        <div class="ego-kpi-value">{{ $fmt->money($isGoogle ? $cpa : $cpl) }}</div>
                        <div class="ego-kpi-sub">
                            {{ $isGoogle ? 'Chi phí / chuyển đổi' : 'Chi phí / lead' }}
                            <span class="tw:ml-2">{{ $fmt->deltaBadge(($isGoogle ? $cpa : $cpl), $cplPrev) }}</span>
                        </div>
                    </div>
                    <div class="ego-kpi-ic"><i class="bi bi-graph-up"></i></div>
                </div>
            </x-ui.card>
        </div>

        {{-- Dynamic per platform --}}
        @if($isFacebook)
            <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                <x-ui.card class="ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Reach</div>
                            <div class="ego-kpi-value">{{ $fmt->number($reach) }}</div>
                            <div class="ego-kpi-sub">Tiếp cận <span class="tw:ml-2">{{ $fmt->deltaBadge($reach, $reachPrev) }}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-broadcast"></i></div>
                    </div>
                </x-ui.card>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                <x-ui.card class="ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Impressions</div>
                            <div class="ego-kpi-value">{{ $fmt->number($impr) }}</div>
                            <div class="ego-kpi-sub">Lượt hiển thị <span class="tw:ml-2">{{ $fmt->deltaBadge($impr, $imprPrev) }}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-eye"></i></div>
                    </div>
                </x-ui.card>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                <x-ui.card class="ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Frequency</div>
                            <div class="ego-kpi-value">{{ number_format($freq, 2, ',', '.') }}</div>
                            <div class="ego-kpi-sub">Tần suất trung bình</div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-repeat"></i></div>
                    </div>
                </x-ui.card>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                <x-ui.card class="ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">CPM</div>
                            <div class="ego-kpi-value">{{ $fmt->money($cpm) }}</div>
                            <div class="ego-kpi-sub">Chi phí / 1.000 hiển thị</div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-speedometer2"></i></div>
                    </div>
                </x-ui.card>
            </div>
        @elseif($isGoogle)
            <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                <x-ui.card class="ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Impressions</div>
                            <div class="ego-kpi-value">{{ $fmt->number($impr) }}</div>
                            <div class="ego-kpi-sub">Lượt hiển thị</div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-eye"></i></div>
                    </div>
                </x-ui.card>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                <x-ui.card class="ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Clicks</div>
                            <div class="ego-kpi-value">{{ $fmt->number($clicks) }}</div>
                            <div class="ego-kpi-sub">Lượt nhấp <span class="tw:ml-2">{{ $fmt->deltaBadge($clicks, $clicksPrev) }}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-cursor"></i></div>
                    </div>
                </x-ui.card>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                <x-ui.card class="ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">CTR</div>
                            <div class="ego-kpi-value">{{ \App\Support\DisplayFormat::percent($ctr, 2) }}</div>
                            <div class="ego-kpi-sub">Tỉ lệ nhấp <span class="tw:ml-2">{{ $fmt->deltaBadge($ctr, $ctrPrev) }}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-percent"></i></div>
                    </div>
                </x-ui.card>
            </div>

            <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                <x-ui.card class="ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Avg CPC</div>
                            <div class="ego-kpi-value">{{ $fmt->money($cpc) }}</div>
                            <div class="ego-kpi-sub">Chi phí / click <span class="tw:ml-2">{{ $fmt->deltaBadge($cpc, $cpcPrev) }}</span></div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-lightning-charge"></i></div>
                    </div>
                </x-ui.card>
            </div>
        @else
            <div class="tw:min-[75rem]:col12-3 tw:min-[62rem]:col12-4 tw:md:col12-6">
                <x-ui.card class="ego-kpi">
                    <div class="ego-kpi-head">
                        <div>
                            <div class="ego-kpi-label">Impressions</div>
                            <div class="ego-kpi-value">{{ $fmt->number($impr) }}</div>
                            <div class="ego-kpi-sub">Tổng hiển thị (nếu có)</div>
                        </div>
                        <div class="ego-kpi-ic"><i class="bi bi-eye"></i></div>
                    </div>
                </x-ui.card>
            </div>
        @endif
    </div>

    {{-- MAIN: Trend + Funnel + Pacing + Insights --}}
    <div class="tw:row tw:g-3 tw:mb-4">
        <div class="tw:min-[62rem]:col12-8">
            <x-ui.card class="ego-card">
                <x-ui.card-header class="ego-card-header tw:flex tw:items-center tw:justify-between flex-wrap tw:gap-2">
                    <div class="tw:font-semibold">Xu hướng theo ngày</div>
                    <div class="tw:flex tw:gap-2 flex-wrap">
                        <span class="ego-mini-pill">Spent · Leads{{ $isGoogle ? ' · CPC' : ' · CPL' }}</span>
                        <span class="ego-mini-pill tw:text-[rgba(33,37,41,0.75)]!">So sánh kỳ trước (nếu có)</span>
                    </div>
                </x-ui.card-header>
                <x-ui.card-body>
                    @if(is_array($trendLabels) && count($trendLabels))
                        <div class="ego-chart-wrap">
                            <canvas id="egoTrendChart" height="110"></canvas>
                        </div>
                        <div class="ego-chart-legend tw:mt-2">
                            <span class="ego-dot"></span> Spend
                            <span class="ms-3"><span class="ego-dot alt"></span> Leads</span>
                            @if($isGoogle)
                                <span class="ms-3"><span class="ego-dot alt2"></span> CPC</span>
                            @else
                                <span class="ms-3"><span class="ego-dot alt2"></span> CPL</span>
                            @endif
                        </div>
                    @else
                        <div class="ego-empty">
                            <div class="ego-empty-ic"><i class="bi bi-graph-up"></i></div>
                            <div class="tw:font-semibold">Chưa có dữ liệu xu hướng</div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Bạn hãy thử chọn khoảng ngày khác hoặc kiểm tra đồng bộ dữ liệu.</div>
                        </div>
                    @endif
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:min-[62rem]:col12-4">
            {{-- Funnel --}}
            <x-ui.card class="ego-card tw:mb-4">
                <x-ui.card-header class="ego-card-header">
                    <div class="tw:font-semibold">Phễu chuyển đổi (Form)</div>
                </x-ui.card-header>
                <x-ui.card-body>

                    <div class="ego-funnel">
                        <div class="ego-funnel-row">
                            <div class="ego-funnel-label">Impressions</div>
                            <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $funnelWidths['impressions'] }}%"></div></div>
                            <div class="ego-funnel-val">{{ $fmt->number($funnelImpressions) }}</div>
                        </div>
                        <div class="ego-funnel-row">
                            <div class="ego-funnel-label">Clicks</div>
                            <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $funnelWidths['clicks'] }}%"></div></div>
                            <div class="ego-funnel-val">{{ $fmt->number($funnelClicks) }}</div>
                        </div>
                        <div class="ego-funnel-row">
                            <div class="ego-funnel-label">Leads</div>
                            <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $funnelWidths['leads'] }}%"></div></div>
                            <div class="ego-funnel-val">{{ $fmt->number($funnelLeads) }}</div>
                        </div>

                        @if($funnelQualified > 0)
                            <div class="ego-funnel-row">
                                <div class="ego-funnel-label">Qualified</div>
                                <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $funnelWidths['qualified'] }}%"></div></div>
                                <div class="ego-funnel-val">{{ $fmt->number($funnelQualified) }}</div>
                            </div>
                        @endif
                        @if($funnelAppointments > 0)
                            <div class="ego-funnel-row">
                                <div class="ego-funnel-label">Appointment</div>
                                <div class="ego-funnel-bar"><div class="ego-funnel-fill" style="width: {{ $funnelWidths['appointments'] }}%"></div></div>
                                <div class="ego-funnel-val">{{ $fmt->number($funnelAppointments) }}</div>
                            </div>
                        @endif
                    </div>

                    <div class="tw:mt-4 tw:flex tw:gap-2 flex-wrap">
                        <span class="ego-mini-pill">Click rate: {{ \App\Support\DisplayFormat::percent($funnelRates['click'], 2) }}</span>
                        <span class="ego-mini-pill">Lead/Click: {{ \App\Support\DisplayFormat::percent($funnelRates['lead'], 2) }}</span>
                        @if($funnelQualified > 0)
                            <span class="ego-mini-pill">Qual/Lead: {{ \App\Support\DisplayFormat::percent($funnelRates['qualified'], 2) }}</span>
                        @endif
                    </div>
                </x-ui.card-body>
            </x-ui.card>

            {{-- Pacing --}}
            <x-ui.card class="ego-card tw:mb-4">
                <x-ui.card-header class="ego-card-header">
                    <div class="tw:font-semibold">Pacing ngân sách</div>
                </x-ui.card-header>
                <x-ui.card-body>
                    <div class="tw:flex tw:items-center tw:justify-between">
                        <div class="tw:text-[rgba(33,37,41,0.75)] small">Ngân sách tháng</div>
                        <div class="tw:font-semibold">{{ $monthBudget > 0 ? $fmt->money($monthBudget) : '—' }}</div>
                    </div>
                    <div class="tw:flex tw:items-center tw:justify-between tw:mt-1">
                        <div class="tw:text-[rgba(33,37,41,0.75)] small">Đã chi</div>
                        <div class="tw:font-semibold">{{ $fmt->money($spentToDate) }}</div>
                    </div>

                    <div class="ego-progress tw:mt-2">
                        <div class="ego-progress-bar {{ $pacingStatus }}" style="width: {{ $pacingPct }}%"></div>
                    </div>

                    <div class="tw:mt-2">
                        <span class="ego-status {{ $pacingStatus }}"><i class="bi bi-check2-circle me-1"></i>{{ $pacingStatusText }}</span>
                        @if($forecast > 0)
                            <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mt-1">Dự báo cuối kỳ: <span class="tw:font-semibold">{{ $fmt->money($forecast) }}</span></div>
                        @endif
                        @if($pacingNote)
                            <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mt-1">{{ $pacingNote }}</div>
                        @endif
                    </div>
                </x-ui.card-body>
            </x-ui.card>

            {{-- Insights --}}
            <x-ui.card class="ego-card">
                <x-ui.card-header class="ego-card-header tw:flex tw:items-center tw:justify-between">
                    <div class="tw:font-semibold">Insights & Cảnh báo</div>
                    <span class="ego-mini-pill">{{ count($insights) ? count($insights) : 0 }} mục</span>
                </x-ui.card-header>
                <x-ui.card-body>
                    @if(count($insights))
                        <div class="ego-insights">
                            @foreach($insights as $it)
                                <div class="ego-insight {{ $it['level'] }}">
                                    <div class="ego-insight-ic">
                                        @if($it['level']==='danger')
                                            <i class="bi bi-exclamation-octagon"></i>
                                        @elseif($it['level']==='warn')
                                            <i class="bi bi-exclamation-triangle"></i>
                                        @elseif($it['level']==='ok')
                                            <i class="bi bi-check2-circle"></i>
                                        @else
                                            <i class="bi bi-info-circle"></i>
                                        @endif
                                    </div>
                                    <div class="ego-insight-txt">{{ $it['text'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="tw:text-[rgba(33,37,41,0.75)] small">Chưa có cảnh báo. (Bạn có thể tạo rule: CPA tăng, Leads giảm, IS thấp...)</div>
                    @endif
                </x-ui.card-body>
            </x-ui.card>
        </div>
    </div>

    {{-- BOTTOM: Tables --}}
    <div class="tw:row tw:g-3">
        <div class="tw:min-[62rem]:col12-8">
            <x-ui.card class="ego-card tw:mb-4">
                <x-ui.card-header class="ego-card-header tw:flex tw:items-center tw:justify-between flex-wrap tw:gap-2">
                    <div class="tw:font-semibold">Tổng quan theo tháng</div>
                    <span class="ego-mini-pill">Gợi ý: thêm so sánh kỳ trước để “đỡ trống”</span>
                </x-ui.card-header>
                <x-ui.card-body class="tw:p-0">
                    <div class="table-responsive">
                        <table class="table table-hover tw:mb-0 align-middle ego-table">
                            <thead>
                            <tr>
                                <th style="width: 120px;">Tháng</th>
                                <th class="tw:text-right" style="width: 160px;">Chi tiêu</th>
                                <th class="tw:text-right" style="width: 140px;">{{ $isGoogle ? 'Impr' : 'Reach' }}</th>
                                <th class="tw:text-right" style="width: 120px;">Leads</th>
                                <th class="tw:text-right" style="width: 120px;">{{ $isGoogle ? 'CPA' : 'CPL' }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if(($byMonth ?? collect())->count())
                                @foreach($byMonth as $r)
                                    <tr>
                                        <td class="tw:font-semibold">{{ $r->month }}</td>
                                        <td class="tw:text-right">{{ $fmt->money($r->spend) }}</td>
                                        <td class="tw:text-right">{{ $fmt->number($isGoogle ? $r->impressions : $r->reach) }}</td>
                                        <td class="tw:text-right">{{ $fmt->number($r->leads) }}</td>
                                        <td class="tw:text-right">{{ $fmt->money($r->cpl) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr><td colspan="5" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">Chưa có dữ liệu</td></tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </x-ui.card-body>
            </x-ui.card>

            <div class="tw:row tw:g-3">
                <div class="tw:md:col12-6">
                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header">
                            <div class="tw:font-semibold">Theo thiết bị</div>
                        </x-ui.card-header>
                        <x-ui.card-body class="tw:p-0">
                            <div class="table-responsive">
                                <table class="table table-hover tw:mb-0 align-middle ego-table">
                                    <thead>
                                    <tr>
                                        <th>Thiết bị</th>
                                        <th class="tw:text-right">Chi tiêu</th>
                                        @if($isGoogle)<th class="tw:text-right">Clicks</th>@endif
                                        <th class="tw:text-right">Leads</th>
                                        <th class="tw:text-right">{{ $isGoogle ? 'CPA' : 'CPL' }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(($byDevice ?? collect())->count())
                                        @foreach($byDevice as $r)
                                            <tr>
                                                <td class="tw:font-semibold">{{ $r->device }}</td>
                                                <td class="tw:text-right">{{ $fmt->money($r->spend) }}</td>
                                                @if($isGoogle)<td class="tw:text-right">{{ $fmt->number($r->clicks) }}</td>@endif
                                                <td class="tw:text-right">{{ $fmt->number($r->leads) }}</td>
                                                <td class="tw:text-right">{{ $fmt->money($r->cpl) }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="{{ $isGoogle ? 5 : 4 }}" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">Chưa có dữ liệu</td></tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>

                <div class="tw:md:col12-6">
                    <x-ui.card class="ego-card">
                        <x-ui.card-header class="ego-card-header">
                            <div class="tw:font-semibold">Theo khu vực</div>
                        </x-ui.card-header>
                        <x-ui.card-body class="tw:p-0">
                            <div class="table-responsive">
                                <table class="table table-hover tw:mb-0 align-middle ego-table">
                                    <thead>
                                    <tr>
                                        <th>Khu vực</th>
                                        <th class="tw:text-right">Chi tiêu</th>
                                        <th class="tw:text-right">Leads</th>
                                        <th class="tw:text-right">{{ $isGoogle ? 'CPA' : 'CPL' }}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(($byGeo ?? collect())->count())
                                        @foreach($byGeo as $r)
                                            <tr>
                                                <td class="tw:font-semibold">{{ $r->location }}</td>
                                                <td class="tw:text-right">{{ $fmt->money($r->spend) }}</td>
                                                <td class="tw:text-right">{{ $fmt->number($r->leads) }}</td>
                                                <td class="tw:text-right">{{ $fmt->money($r->cpl) }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td colspan="4" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">Chưa có dữ liệu</td></tr>
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>
            </div>
        </div>

        {{-- Right column: Top Campaign / Keywords / Ads --}}
        <div class="tw:min-[62rem]:col12-4">
            <x-ui.card class="ego-card tw:mb-4">
                <x-ui.card-header class="ego-card-header">
                    <div class="tw:font-semibold">Top chiến dịch (theo chi tiêu)</div>
                </x-ui.card-header>
                <x-ui.card-body class="tw:p-0">
                    <div class="table-responsive">
                        <table class="table table-hover tw:mb-0 align-middle ego-table">
                            <thead>
                            <tr>
                                <th>Chiến dịch</th>
                                @if($isGoogle)
                                    <th class="tw:text-right" style="width:100px;">Clicks</th>
                                @else
                                    <th class="tw:text-right" style="width:100px;">Reach</th>
                                @endif
                                <th class="tw:text-right" style="width:90px;">Leads</th>
                                <th class="tw:text-right" style="width:130px;">Chi tiêu</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if(($topCampaigns ?? collect())->count())
                                @foreach($topCampaigns as $r)
                                    <tr>
                                        <td class="tw:font-semibold">{{ $r->campaign_name ?? 'Không rõ' }}</td>
                                        @if($isGoogle)
                                            <td class="tw:text-right">{{ $fmt->number($r->clicks ?? 0) }}</td>
                                        @else
                                            <td class="tw:text-right">{{ $fmt->number($r->reach ?? 0) }}</td>
                                        @endif
                                        <td class="tw:text-right">{{ $fmt->number($r->leads ?? 0) }}</td>
                                        <td class="tw:text-right">{{ $fmt->money($r->spend ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr><td colspan="4" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">Chưa có dữ liệu campaign</td></tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </x-ui.card-body>
            </x-ui.card>

            @if($isGoogle)
                <x-ui.card class="ego-card">
                    <x-ui.card-header class="ego-card-header">
                        <div class="tw:font-semibold">Top từ khóa</div>
                    </x-ui.card-header>
                    <x-ui.card-body class="tw:p-0">
                        <div class="table-responsive">
                            <table class="table table-hover tw:mb-0 align-middle ego-table">
                                <thead>
                                <tr>
                                    <th>Từ khóa</th>
                                    <th class="tw:text-right" style="width:90px;">Clicks</th>
                                    <th class="tw:text-right" style="width:110px;">CPC</th>
                                    <th class="tw:text-right" style="width:90px;">Leads</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if(($topKeywords ?? collect())->count())
                                    @foreach($topKeywords as $r)
                                        <tr>
                                            <td class="tw:font-semibold">{{ $r->keyword ?? '—' }}</td>
                                            <td class="tw:text-right">{{ $fmt->number($r->clicks ?? 0) }}</td>
                                            <td class="tw:text-right">{{ $fmt->money($r->cpc ?? 0) }}</td>
                                            <td class="tw:text-right">{{ $fmt->number($r->conversions ?? $r->leads ?? 0) }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="4" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">Chưa có dữ liệu từ khóa</td></tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>
            @elseif($isFacebook)
                <x-ui.card class="ego-card">
                    <x-ui.card-header class="ego-card-header">
                        <div class="tw:font-semibold">Top mẫu quảng cáo</div>
                    </x-ui.card-header>
                    <x-ui.card-body class="tw:p-0">
                        <div class="table-responsive">
                            <table class="table table-hover tw:mb-0 align-middle ego-table">
                                <thead>
                                <tr>
                                    <th>Mẫu / QC</th>
                                    <th class="tw:text-right" style="width:90px;">Leads</th>
                                    <th class="tw:text-right" style="width:120px;">CPL</th>
                                    <th class="tw:text-right" style="width:130px;">Chi tiêu</th>
                                </tr>
                                </thead>
                                <tbody>
                                @if(($topAds ?? collect())->count())
                                    @foreach($topAds as $r)
                                        <tr>
                                            <td class="tw:font-semibold">{{ $r->name }}</td>
                                            <td class="tw:text-right">{{ $fmt->number($r->leads) }}</td>
                                            <td class="tw:text-right">{{ $fmt->money($r->cpl) }}</td>
                                            <td class="tw:text-right">{{ $fmt->money($r->spend) }}</td>
                                        </tr>
                                    @endforeach
                                @else
                                    <tr><td colspan="4" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">Chưa có dữ liệu mẫu</td></tr>
                                @endif
                                </tbody>
                            </table>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>
            @endif
        </div>
    </div>
    @endif {{-- end non-SEO --}}

</div>

{{-- CHART.JS (nếu layout chưa có) --}}
<script>
(function(){
  if (typeof Chart !== 'undefined') return;
  var s = document.createElement('script');
  s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
  s.onload = function(){
    initEgoTrendChart();
    initEgoSeoTrendChart();
  };
  document.head.appendChild(s);
})();

document.addEventListener('DOMContentLoaded', function(){
  if (typeof Chart !== 'undefined') {
    initEgoTrendChart();
    initEgoSeoTrendChart();
  }
});

function initEgoTrendChart(){
  var el = document.getElementById('egoTrendChart');
  if (!el) return;

  var labels = @json($trendLabels);
  var spend  = @json($trendSpend);
  var leads  = @json($trendLeads);
  var cpl    = @json($trendCpl);
  var clicks = @json($trendClicks);
  var cpc    = @json($trendCpc);

  if ((!cpl || !cpl.length) && spend && spend.length && leads && leads.length) {
    cpl = spend.map(function(v, i){
      var l = Number(leads[i] || 0);
      return l > 0 ? (Number(v || 0) / l) : 0;
    });
  }
  if ((!cpc || !cpc.length) && spend && spend.length && clicks && clicks.length) {
    cpc = spend.map(function(v, i){
      var c = Number(clicks[i] || 0);
      return c > 0 ? (Number(v || 0) / c) : 0;
    });
  }

  var showGoogle = {{ $isGoogle ? 'true' : 'false' }};

  new Chart(el, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        { label: 'Spend', data: spend, tension: 0.35, yAxisID: 'y',  pointRadius: 0, borderWidth: 2 },
        { label: 'Leads', data: leads, tension: 0.35, yAxisID: 'y1', pointRadius: 0, borderWidth: 2 },
        { label: showGoogle ? 'CPC' : 'CPL', data: showGoogle ? cpc : cpl, tension: 0.35, yAxisID: 'y2', pointRadius: 0, borderWidth: 2 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(ctx){
              var v = ctx.raw ?? 0;
              if (ctx.dataset.label === 'Spend' || ctx.dataset.label === 'CPC' || ctx.dataset.label === 'CPL') {
                return ctx.dataset.label + ': ' + new Intl.NumberFormat('vi-VN').format(Math.round(v)) + ' đ';
              }
              return ctx.dataset.label + ': ' + new Intl.NumberFormat('vi-VN').format(Math.round(v));
            }
          }
        }
      },
      scales: {
        y: { position: 'left',  ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y1:{ position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y2:{ position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } }
      }
    }
  });
}

function initEgoSeoTrendChart(){
  var el = document.getElementById('egoSeoTrendChart');
  if (!el) return;

  var labels = @json($seoTrendLabels);
  var sessions = @json($seoTrendSess);
  var clicks   = @json($seoTrendClicks);
  var leads    = @json($seoTrendLeads);
  var top10    = @json($seoTrendTop10);

  new Chart(el, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        { label: 'Sessions', data: sessions, tension: 0.35, yAxisID: 'y',  pointRadius: 0, borderWidth: 2 },
        { label: 'Clicks',   data: clicks,   tension: 0.35, yAxisID: 'y1', pointRadius: 0, borderWidth: 2 },
        { label: 'Leads',    data: leads,    tension: 0.35, yAxisID: 'y2', pointRadius: 0, borderWidth: 2 },
        { label: 'TOP10',    data: top10,    tension: 0.35, yAxisID: 'y3', pointRadius: 0, borderWidth: 2 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false } },
      scales: {
        y:  { position: 'left',  ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y2: { position: 'right', grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } },
        y3: { position: 'right', display: false, grid: { drawOnChartArea: false }, ticks: { callback: v => new Intl.NumberFormat('vi-VN').format(v) } }
      }
    }
  });
}
</script>

{{-- STYLE --}}
<style>
/* HERO */
.ego-hero{
    border-radius: 18px;
    padding: 18px;
    background: linear-gradient(90deg, rgba(224,242,254,.75), rgba(255,255,255,1));
    border: 1px solid rgba(15,23,42,.08);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.ego-hero-ic{
    width: 44px; height: 44px;
    border-radius: 14px;
    display:flex; align-items:center; justify-content:center;
    background: rgba(56,189,248,.18);
    color: #0ea5e9;
    font-size: 20px;
}
.ego-hero-title{
    font-size: 28px;
    font-weight: 900;
    letter-spacing: .2px;
    color: #0f172a;
    line-height: 1.1;
}
.ego-hero-sub{
    margin-top: 3px;
    color: rgba(15,23,42,.65);
    font-size: 13px;
}

/* Segmented */
.ego-seg{
    display:inline-flex;
    border-radius: 14px;
    padding: 4px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.7);
    box-shadow: 0 10px 20px rgba(15,23,42,.05);
}
.ego-seg-btn{
    text-decoration:none;
    color: rgba(15,23,42,.70);
    font-weight: 800;
    font-size: 13px;
    padding: 8px 12px;
    border-radius: 12px;
    display:inline-flex;
    align-items:center;
}
.ego-seg-btn.active{
    background: #16a34a;
    color: #fff;
    box-shadow: 0 10px 20px rgba(22,163,74,.25);
}

/* CARD */
.ego-card{
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,.10);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
}
.ego-card-header{
    background: transparent;
    border-bottom: 1px solid rgba(15,23,42,.08);
    padding: 12px 14px;
}

/* INPUT/BUTTON */
.ego-input{ border-radius: 12px; }
.ego-btn{
    border-radius: 12px;
    padding: 10px 12px;
    font-weight: 600;
}
.ego-btn-primary{
    border-radius: 12px;
    padding: 10px 12px;
    font-weight: 800;
}

/* Pills */
.ego-mini-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.7);
    font-size: 12px;
    font-weight: 700;
    color: rgba(15,23,42,.75);
}

/* KPI */
.ego-kpi{
    border-radius: 16px;
    border: 1px solid rgba(15,23,42,.10);
    box-shadow: 0 10px 24px rgba(15,23,42,.06);
    padding: 14px;
    background: #fff;
}
.ego-kpi-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 12px;
}
.ego-kpi-label{
    color: rgba(15,23,42,.70);
    font-size: 13px;
    font-weight: 800;
}
.ego-kpi-value{
    margin-top: 6px;
    font-size: 22px;
    font-weight: 900;
    color: #0f172a;
}
.ego-kpi-sub{
    margin-top: 2px;
    font-size: 12px;
    color: rgba(15,23,42,.55);
}
.ego-kpi-ic{
    width: 44px; height: 44px;
    border-radius: 16px;
    display:flex; align-items:center; justify-content:center;
    background: rgba(34,197,94,.12);
    color: #16a34a;
    font-size: 20px;
}

/* Delta badge */
.ego-delta{
    display:inline-flex;
    align-items:center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    border: 1px solid rgba(15,23,42,.08);
}
.ego-delta.up{ background: rgba(34,197,94,.12); color:#16a34a; border-color: rgba(34,197,94,.25); }
.ego-delta.down{ background: rgba(239,68,68,.12); color:#dc2626; border-color: rgba(239,68,68,.25); }
.ego-delta.neutral{ background: rgba(148,163,184,.15); color: rgba(15,23,42,.60); }

/* VAT UI */
.ego-kpi .ego-kpi-value-strong{
    font-size: 24px !important;
    font-weight: 900 !important;
    color: #0f172a !important;
}
.ego-badge-vat{
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 900;
    line-height: 1;
    border: 1px solid rgba(34,197,94,.25);
    background: rgba(34,197,94,.12);
    color: #16a34a;
}
.ego-kpi-sub-strong{
    font-weight: 900;
    color: rgba(15,23,42,.78);
}

/* Chart */
.ego-chart-wrap{
    position: relative;
    height: 280px;
}
.ego-chart-legend{
    font-size: 12px;
    font-weight: 800;
    color: rgba(15,23,42,.60);
}
.ego-dot{
    display:inline-block;
    width:10px; height:10px;
    border-radius:999px;
    background: rgba(2,132,199,.8);
    margin-right:6px;
    vertical-align:middle;
}
.ego-dot.alt{ background: rgba(22,163,74,.8); }
.ego-dot.alt2{ background: rgba(245,158,11,.85); }
.ego-dot.alt3{ background: rgba(99,102,241,.85); }

/* Empty */
.ego-empty{
    border: 1px dashed rgba(15,23,42,.18);
    border-radius: 16px;
    padding: 22px;
    text-align:center;
    background: rgba(248,250,252,.8);
}
.ego-empty-ic{
    width: 52px; height: 52px;
    margin: 0 auto 8px;
    border-radius: 18px;
    display:flex; align-items:center; justify-content:center;
    background: rgba(56,189,248,.16);
    color:#0284c7;
    font-size: 22px;
}

/* Funnel */
.ego-funnel-row{
    display:grid;
    grid-template-columns: 92px 1fr 72px;
    gap: 10px;
    align-items:center;
    margin-bottom: 10px;
}
.ego-funnel-label{
    font-size: 12px;
    font-weight: 900;
    color: rgba(15,23,42,.70);
}
.ego-funnel-bar{
    height: 10px;
    border-radius: 999px;
    background: rgba(148,163,184,.18);
    overflow:hidden;
}
.ego-funnel-fill{
    height: 100%;
    border-radius: 999px;
    background: rgba(22,163,74,.85);
}
.ego-funnel-val{
    text-align:right;
    font-size: 12px;
    font-weight: 900;
    color: rgba(15,23,42,.80);
}

/* Pacing */
.ego-progress{
    height: 10px;
    border-radius: 999px;
    background: rgba(148,163,184,.18);
    overflow:hidden;
}
.ego-progress-bar{
    height:100%;
    border-radius:999px;
    background: rgba(22,163,74,.9);
}
.ego-progress-bar.over{ background: rgba(239,68,68,.85); }
.ego-progress-bar.under{ background: rgba(245,158,11,.90); }

.ego-status{
    display:inline-flex;
    align-items:center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    border: 1px solid rgba(15,23,42,.08);
}
.ego-status.ok{ background: rgba(34,197,94,.12); color:#16a34a; border-color: rgba(34,197,94,.25); }
.ego-status.over{ background: rgba(239,68,68,.12); color:#dc2626; border-color: rgba(239,68,68,.25); }
.ego-status.under{ background: rgba(245,158,11,.12); color:#b45309; border-color: rgba(245,158,11,.25); }

/* Insights */
.ego-insights{ display:flex; flex-direction:column; gap:10px; }
.ego-insight{
    display:flex; gap:10px; align-items:flex-start;
    padding: 10px 10px;
    border-radius: 14px;
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(248,250,252,.85);
}
.ego-insight-ic{
    width: 34px; height: 34px;
    border-radius: 12px;
    display:flex; align-items:center; justify-content:center;
    font-size: 16px;
}
.ego-insight-txt{ font-size: 12.5px; font-weight: 800; color: rgba(15,23,42,.75); line-height: 1.35; }

.ego-insight.info .ego-insight-ic{ background: rgba(56,189,248,.18); color:#0284c7; }
.ego-insight.ok .ego-insight-ic{ background: rgba(34,197,94,.14); color:#16a34a; }
.ego-insight.warn .ego-insight-ic{ background: rgba(245,158,11,.14); color:#b45309; }
.ego-insight.danger .ego-insight-ic{ background: rgba(239,68,68,.14); color:#dc2626; }

/* TABLE */
.ego-table thead th{
    font-size: 12px;
    color: rgba(15,23,42,.65);
    font-weight: 900;
    border-top: 0;
}
.ego-table tbody td{ font-size: 13px; }
.ego-table tbody tr:hover{ background: rgba(2,132,199,.05); }

/* SEO Plan small UI */
.ego-plan-field{
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(248,250,252,.85);
    border-radius: 14px;
    padding: 10px 12px;
}
.ego-plan-label{ font-size: 12px; font-weight: 900; color: rgba(15,23,42,.70); }
.ego-plan-val{ margin-top: 4px; font-size: 18px; font-weight: 900; color: #0f172a; }
.ego-plan-sub{ margin-top: 2px; }

.ego-note{
    border: 1px dashed rgba(15,23,42,.18);
    background: rgba(248,250,252,.9);
    border-radius: 14px;
    padding: 10px 12px;
    font-size: 13px;
    color: rgba(15,23,42,.75);
}
.ego-checklist{ display:flex; flex-direction:column; gap:8px; }
.ego-check{ font-weight: 800; color: rgba(15,23,42,.70); display:flex; align-items:center; gap:8px; }
.ego-check i{ color: rgba(15,23,42,.35); }

.ego-status-chip{
    display:inline-flex;
    align-items:center;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(148,163,184,.12);
    color: rgba(15,23,42,.75);
}

/* MOBILE */
@media (max-width: 991px){
    .ego-hero-title{ font-size: 22px; }
    .ego-hero{ padding: 14px; }
    .ego-seg-btn{ padding: 8px 10px; }
    .ego-chart-wrap{ height: 240px; }
}
</style>
@endsection