@extends('layouts.app')

@section('content')

<style>
.sales-dashboard-pro{
    --ink:#07111f;
    --muted:#64748b;
    --line:#dbe4ee;
    --soft:#f8fafc;
    --cyan:#06b6d4;
    --teal:#0f766e;
    --green:#16a34a;
    --red:#e11d48;
    --amber:#f59e0b;
    font-size:13px;
}
.sales-dashboard-pro *{letter-spacing:-.01em}
.sdp-hero{
    position:relative;
    overflow:hidden;
    border-radius:28px;
    padding:22px;
    color:#fff;
    background:
        radial-gradient(circle at 9% 10%, rgba(34,211,238,.38), transparent 28%),
        radial-gradient(circle at 92% 0%, rgba(16,185,129,.34), transparent 30%),
        linear-gradient(135deg,#06111f 0%,#0f766e 58%,#05bcd4 100%);
    box-shadow:0 28px 80px rgba(15,23,42,.22);
}
.sdp-hero:before,.sdp-hero:after{
    content:"";
    position:absolute;
    width:260px;
    height:260px;
    border-radius:999px;
    background:rgba(255,255,255,.10);
    animation:sdpFloat 9s ease-in-out infinite;
}
.sdp-hero:before{right:-90px;top:-140px}
.sdp-hero:after{left:46%;bottom:-210px;animation-delay:1.4s}
@keyframes sdpFloat{
    0%,100%{transform:translate3d(0,0,0) scale(1)}
    50%{transform:translate3d(20px,-18px,0) scale(1.08)}
}
.sdp-hero-inner{
    position:relative;
    z-index:2;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    flex-wrap:wrap;
}
.sdp-kicker{
    display:inline-flex;
    align-items:center;
    height:26px;
    padding:0 11px;
    border-radius:999px;
    background:rgba(255,255,255,.16);
    border:1px solid rgba(255,255,255,.22);
    color:#e0fcff;
    font-size:11px;
    font-weight:950;
}
.sdp-hero h1{
    margin:9px 0 0;
    font-size:34px;
    font-weight:950;
    letter-spacing:-.06em;
}
.sdp-hero p{
    margin:6px 0 0;
    color:rgba(255,255,255,.78);
    font-weight:750;
}
.sdp-actions{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end}
.sdp-btn{
    height:38px;
    border:0;
    border-radius:14px;
    padding:0 14px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:7px;
    font-weight:950;
    text-decoration:none;
    white-space:nowrap;
    transition:.2s ease;
}
.sdp-btn:hover{transform:translateY(-2px)}
.sdp-btn-white{background:#fff;color:#0f766e}
.sdp-btn-glass{background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.22)}
.sdp-btn-main{background:linear-gradient(135deg,#06b6d4,#16a34a);color:#fff;box-shadow:0 14px 30px rgba(6,182,212,.25)}
.sdp-btn-dark{background:#07111f;color:#fff}
.sdp-btn-soft{background:#fff;color:#07111f;border:1px solid var(--line)}
.sdp-monthbar{
    margin-top:14px;
    border:1px solid var(--line);
    background:rgba(255,255,255,.92);
    backdrop-filter:blur(16px);
    border-radius:22px;
    padding:12px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    box-shadow:0 16px 42px rgba(15,23,42,.07);
}
.sdp-month-title{font-size:15px;color:#07111f;font-weight:950}
.sdp-month-sub{font-size:12px;color:#64748b;font-weight:750;margin-top:2px}
.sdp-month-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sdp-month-form input{
    height:38px;
    border:1px solid var(--line);
    border-radius:14px;
    padding:0 12px;
    font-weight:900;
    color:#07111f;
    outline:none;
}
.sdp-stat-grid{
    display:grid;
    grid-template-columns:repeat(6,minmax(0,1fr));
    gap:12px;
    margin-top:15px;
}
.sdp-stat{
    position:relative;
    overflow:hidden;
    border-radius:22px;
    background:#fff;
    border:1px solid var(--line);
    padding:14px;
    box-shadow:0 16px 42px rgba(15,23,42,.07);
    animation:sdpUp .45s ease both;
}
.sdp-stat:after{
    content:"";
    position:absolute;
    width:90px;
    height:90px;
    border-radius:999px;
    right:-34px;
    top:-42px;
    background:linear-gradient(135deg,rgba(6,182,212,.19),rgba(16,185,129,.16));
}
.sdp-stat.dark{background:#07111f;border-color:#07111f}
.sdp-stat span{
    display:block;
    color:#64748b;
    font-size:10px;
    text-transform:uppercase;
    font-weight:950;
}
.sdp-stat.dark span{color:rgba(255,255,255,.65)}
.sdp-stat b{
    display:block;
    margin-top:5px;
    color:#07111f;
    font-size:19px;
    font-weight:950;
}
.sdp-stat.dark b{color:#fff}
@keyframes sdpUp{
    from{opacity:0;transform:translateY(14px)}
    to{opacity:1;transform:translateY(0)}
}
.sdp-layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 360px;
    gap:16px;
    margin-top:16px;
}
.sdp-panel{
    border-radius:24px;
    background:#fff;
    border:1px solid var(--line);
    box-shadow:0 16px 46px rgba(15,23,42,.07);
    overflow:hidden;
    margin-bottom:16px;
}
.sdp-head{
    padding:15px 17px;
    border-bottom:1px solid var(--line);
    background:linear-gradient(135deg,#fff,#f8fafc);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}
.sdp-title{margin:0;font-size:19px;color:#07111f;font-weight:950}
.sdp-sub{margin-top:3px;color:#64748b;font-weight:750;font-size:12px}
.sdp-table-wrap{overflow:auto}
.sdp-table{
    width:100%;
    min-width:1180px;
    border-collapse:separate;
    border-spacing:0;
}
.sdp-table th{
    background:#f8fafc;
    color:#475569;
    font-size:10px;
    text-transform:uppercase;
    font-weight:950;
    padding:11px 12px;
    border-bottom:1px solid var(--line);
    white-space:nowrap;
}
.sdp-table td{
    padding:12px;
    border-bottom:1px solid #eef2f7;
    vertical-align:middle;
}
.sdp-table tr:hover td{background:#fbfdff}
.sdp-name{color:#07111f;font-weight:950;line-height:1.25}
.sdp-meta{color:#64748b;font-size:12px;font-weight:750;margin-top:2px}
.sdp-chip{
    display:inline-flex;
    align-items:center;
    min-height:25px;
    padding:0 10px;
    border-radius:999px;
    background:#ecfeff;
    color:#0e7490;
    border:1px solid #a5f3fc;
    font-size:11px;
    font-weight:950;
    white-space:nowrap;
}
.sdp-chip.green{background:#dcfce7;color:#166534;border-color:#bbf7d0}
.sdp-chip.gray{background:#f1f5f9;color:#64748b;border-color:#e2e8f0}
.sdp-chip.red{background:#fff1f2;color:#be123c;border-color:#fecdd3}
.sdp-chip.amber{background:#fff7ed;color:#c2410c;border-color:#fed7aa}
.sdp-progress{
    width:190px;
    height:12px;
    border-radius:999px;
    background:#edf2f7;
    overflow:hidden;
}
.sdp-progress span{
    display:block;
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg,#06b6d4,#16a34a);
}
.sdp-bars{padding:15px;display:grid;gap:12px}
.sdp-bar-row{
    display:grid;
    grid-template-columns:180px minmax(0,1fr) 130px;
    gap:10px;
    align-items:center;
}
.sdp-bar-name{font-weight:950;color:#07111f}
.sdp-bar-track{
    height:18px;
    border-radius:999px;
    background:#eef2f7;
    overflow:hidden;
}
.sdp-bar-fill{
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg,#06b6d4,#16a34a);
}
.sdp-side{display:grid;gap:16px;align-content:start}
.sdp-side-card{
    border-radius:24px;
    overflow:hidden;
    background:#07111f;
    color:#fff;
    box-shadow:0 18px 48px rgba(15,23,42,.16);
}
.sdp-side-card .inner{padding:16px}
.sdp-side-card h3{margin:0;font-size:19px;font-weight:950}
.sdp-side-card p{color:rgba(255,255,255,.68);font-weight:750;font-size:12px;margin:5px 0 0}
.sdp-top-sales{
    margin-top:14px;
    border-radius:18px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.10);
    padding:14px;
}
.sdp-top-sales .big{
    font-size:30px;
    font-weight:950;
    line-height:1;
    margin-top:8px;
}
.sdp-income-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
    margin-top:14px;
}
.sdp-income{
    border-radius:16px;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.10);
    padding:11px;
}
.sdp-income span{display:block;font-size:10px;color:rgba(255,255,255,.65);font-weight:950;text-transform:uppercase}
.sdp-income b{display:block;margin-top:4px;font-size:17px;color:#fff;font-weight:950}
.sdp-empty{padding:28px;text-align:center;color:#64748b;font-weight:800}
.sdp-panel-filter{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}
.sdp-panel-filter select{
    height:38px;
    min-width:210px;
    border:1px solid var(--line);
    border-radius:14px;
    padding:0 12px;
    background:#fff;
    color:#07111f;
    font-size:13px;
    font-weight:900;
    outline:none;
}
.sdp-panel-filter select:focus{
    border-color:#06b6d4;
    box-shadow:0 0 0 4px rgba(6,182,212,.13);
}

@media(max-width:1280px){
    .sdp-layout{grid-template-columns:1fr}
    .sdp-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:900px){
    .sdp-monthbar{flex-direction:column;align-items:flex-start}
    .sdp-month-form{width:100%}
    .sdp-month-form input,.sdp-month-form .sdp-btn{flex:1}
}

/* EGO_UI_COMMISSION_RATE_START */
.sales-dashboard-pro{font-size:13px;line-height:1.35}
.sales-dashboard-pro .sdp-hero h1{font-size:30px;font-weight:800;letter-spacing:-.04em}
.sales-dashboard-pro .sdp-hero p,
.sales-dashboard-pro .sdp-sub,
.sales-dashboard-pro .sdp-meta{font-weight:500}
.sales-dashboard-pro .sdp-title{font-size:17px;font-weight:800}
.sales-dashboard-pro .sdp-stat b{font-size:17px;font-weight:800}
.sales-dashboard-pro .sdp-stat span{font-weight:700}
.sales-dashboard-pro .sdp-table{font-size:12.5px;min-width:1260px}
.sales-dashboard-pro .sdp-table th{font-size:9.5px;font-weight:700;padding:9px 10px;letter-spacing:.01em}
.sales-dashboard-pro .sdp-table td{padding:9px 10px}
.sales-dashboard-pro .sdp-name{font-size:13px;font-weight:700;line-height:1.22}
.sales-dashboard-pro .sdp-meta{font-size:10.5px;line-height:1.2}
.sales-dashboard-pro .sdp-chip{font-size:10.5px;font-weight:700;min-height:22px;padding:0 8px}
.sales-dashboard-pro .sdp-btn,
.sales-dashboard-pro .sdp-panel-filter select,
.sales-dashboard-pro .sdp-month-form input{font-weight:700}
.sales-dashboard-pro .sdp-rate{min-width:58px;justify-content:center}
/* EGO_UI_COMMISSION_RATE_END */

/* EGO_EMPLOYEE_FILTER_COMPACT_UI_START */
.sales-dashboard-pro .sdp-sales-filter-form{
    align-items:center;
    gap:8px;
}
.sales-dashboard-pro .sdp-sales-select{
    min-width:210px;
    height:38px;
    border:1px solid #dbe4ee;
    border-radius:14px;
    padding:0 12px;
    background:#fff;
    color:#0f172a;
    font-size:12.5px;
    font-weight:700;
    outline:none;
}
.sales-dashboard-pro .sdp-order-tools{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
}
.sales-dashboard-pro .sdp-order-table-compact{
    min-width:980px !important;
    font-size:12px;
}
.sales-dashboard-pro .sdp-order-table-compact th{
    padding:8px 10px;
    font-size:9.5px;
}
.sales-dashboard-pro .sdp-order-table-compact td{
    padding:8px 10px;
    vertical-align:middle;
}
.sales-dashboard-pro .sdp-order-table-compact .sdp-name{
    font-size:12.5px;
    font-weight:700;
    line-height:1.2;
}
.sales-dashboard-pro .sdp-order-table-compact .sdp-meta{
    font-size:10.5px;
    line-height:1.2;
}
.sales-dashboard-pro .sdp-order-table-compact .sdp-chip{
    font-size:10px;
    min-height:21px;
    padding:0 7px;
}
@media(max-width: 900px){
    .sales-dashboard-pro .sdp-sales-select{
        width:100%;
    }
}
/* EGO_EMPLOYEE_FILTER_COMPACT_UI_END */
</style>

{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="container-fluid sales-dashboard-pro tw:py-4">
    <div class="sdp-hero">
        <div class="sdp-hero-inner">
            <div>
                <span class="sdp-kicker">Sales Compensation Dashboard</span>
                <h1>Dashboard hoa hồng</h1>
                <p>Theo dõi doanh thu, lương cứng, KPI, hoa hồng và tổng thu nhập Sales theo từng tháng.</p>
            </div>

            <div class="sdp-actions">
                
                <a class="sdp-btn sdp-btn-main" href="{{ route('sales.commissions.export.excel', array_merge(request()->query(), ['month' => $month])) }}">
                    Xuất Excel
                </a>
                <a class="sdp-btn sdp-btn-glass" href="{{ route('sales.commissions.settings', ['month' => $month]) }}">
                    Cài đặt chính sách
                </a>
                <a class="sdp-btn sdp-btn-white" href="{{ route('sales.commissions.index', ['month' => now()->format('Y-m')]) }}">
                    Tháng hiện tại
                </a>
            </div>
        </div>
    </div>

    <div class="sdp-monthbar">
        <div>
            <div class="sdp-month-title">Chọn tháng xem hoa hồng</div>
            <div class="sdp-month-sub">Dashboard tự đọc chính sách, lương cứng và KPI của tháng đang chọn.</div>
        </div>

        <form class="sdp-month-form sdp-sales-filter-form" method="GET" action="{{ route('sales.commissions.index') }}">
            <a class="sdp-btn sdp-btn-soft"
               href="{{ route('sales.commissions.index', array_filter(['month' => $prevMonth, 'sales_id' => $filterSalesId > 0 ? $filterSalesId : null])) }}">
                ← Tháng trước
            </a>

            <input type="month" name="month" value="{{ $month }}">

            <select name="sales_id" class="sdp-sales-select">
                <option value="0">Tất cả nhân viên Sales</option>
                @foreach($salesUsers as $su)
                    <option value="{{ $su->id }}" {{ $filterSalesId === (int)$su->id ? 'selected' : '' }}>
                        {{ $su->name }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="sdp-btn sdp-btn-dark">Lọc</button>

            <a class="sdp-btn sdp-btn-main"
               href="{{ route('sales.commissions.export.excel', array_filter(['month' => $month, 'sales_id' => $filterSalesId > 0 ? $filterSalesId : null])) }}">
                {{ $filterSalesId > 0 ? 'Xuất Excel nhân viên' : 'Xuất Excel tất cả' }}
            </a>

            @if($filterSalesId > 0)
                <a class="sdp-btn sdp-btn-soft" href="{{ route('sales.commissions.index', ['month' => $month]) }}">
                    Bỏ lọc
                </a>
            @endif

            <a class="sdp-btn sdp-btn-soft"
               href="{{ route('sales.commissions.index', array_filter(['month' => $nextMonth, 'sales_id' => $filterSalesId > 0 ? $filterSalesId : null])) }}">
                Tháng sau →
            </a>
        </form>
    </div>

    <div class="sdp-stat-grid">
        <div class="sdp-stat"><span>Doanh thu</span><b>{{ $fmt->money($totalRevenue) }}</b></div>
        <div class="sdp-stat"><span>Đã thu</span><b>{{ $fmt->money($totalPaid) }}</b></div>
        <div class="sdp-stat"><span>Công nợ</span><b>{{ $fmt->money($totalDebt) }}</b></div>
        <div class="sdp-stat"><span>Hoa hồng</span><b>{{ $fmt->money($totalCommission) }}</b></div>
        <div class="sdp-stat"><span>KPI thưởng</span><b>{{ $fmt->money($totalKpiBonus) }}</b></div>
        <div class="sdp-stat dark"><span>Tổng thu nhập</span><b>{{ $fmt->money($totalIncome) }}</b></div>
    </div>

    <div class="sdp-layout">
        <div>
            <div class="sdp-panel">
                <div class="sdp-head">
                    <div>
                        <h2 class="sdp-title">Ranking thu nhập Sales</h2>
                        <div class="sdp-sub">Tổng thu nhập = Lương cứng + Hoa hồng đơn hàng + Thưởng KPI.</div>
                    </div>

                    <div class="tw:flex tw:gap-2 flex-wrap">
                        <span class="sdp-chip">Sales: {{ $salesRows->count() }}</span>
                        <span class="sdp-chip green">Đơn: {{ $fmt->number($totalOrders) }}</span>
                    </div>
                </div>

                <div class="sdp-table-wrap">
                    <table class="sdp-table">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Sales</th>
                            <th>Doanh thu</th>
                            <th>Đạt target</th>
                            <th>Đơn</th>
                            <th>Lương cứng</th>
                            <th>Hoa hồng</th>
                            <th>% HH</th>
                            <th>KPI</th>
                            <th>Tổng thu nhập</th>
                            <th>Trạng thái</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse($salesRows as $i => $r)
                            @php
                                $rate = min(100, (float)$r->target_rate);
                                $statusClass = $r->target_rate >= 100 ? 'green' : ($r->target_rate >= 70 ? 'amber' : 'gray');
                                $statusText = $r->target_rate >= 100 ? 'Đạt KPI' : ($r->target_rate >= 70 ? 'Gần đạt' : 'Đang chạy');
                            @endphp

                            <tr>
                                <td><span class="sdp-chip {{ $i === 0 ? 'green' : '' }}">#{{ $i + 1 }}</span></td>
                                <td>
                                    <div class="sdp-name">{{ $r->name }}</div>
                                    <div class="sdp-meta">{{ $r->email }}</div>
                                </td>
                                <td><div class="sdp-name">{{ $fmt->money($r->revenue) }}</div></td>
                                <td>
                                    <div class="tw:flex tw:items-center tw:gap-2">
                                        <div class="sdp-progress"><span style="width:{{ $rate }}%"></span></div>
                                        <b>{{ \App\Support\DisplayFormat::percent($r->target_rate) }}</b>
                                    </div>
                                    <div class="sdp-meta">Target: {{ $fmt->money($r->target_revenue) }}</div>
                                </td>
                                <td><span class="sdp-chip">{{ $r->order_count }} đơn</span></td>
                                <td><div class="sdp-name">{{ $fmt->money($r->base_salary) }}</div></td>
                                <td><div class="sdp-name tw:text-[#198754]!">{{ $fmt->money($r->commission) }}</div></td>
                                <td><span class="sdp-chip green sdp-rate">{{ $fmt->rate($r->commission_rate ?? 0) }}</span></td>
                                <td>
                                    <div class="sdp-name">{{ $fmt->money($r->kpi_bonus) }}</div>
                                    <div class="sdp-meta">{{ $r->kpi_name }}</div>
                                </td>
                                <td><div class="sdp-name tw:text-[#0d6efd]!">{{ $fmt->money($r->total_income) }}</div></td>
                                <td><span class="sdp-chip {{ $statusClass }}">{{ $statusText }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11">
                                    <div class="sdp-empty">Chưa có Sales hoặc chưa có dữ liệu hoa hồng trong tháng này.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sdp-panel">
                <div class="sdp-head">
                    <div>
                        <h2 class="sdp-title">Biểu đồ doanh thu theo Sales</h2>
                        <div class="sdp-sub">So sánh nhanh doanh thu phát sinh trong tháng.</div>
                    </div>
                </div>

                <div class="sdp-bars">
                    @forelse($salesRows as $r)
                        @php $w = max(2, min(100, ($r->revenue / $maxRevenue) * 100)); @endphp
                        <div class="sdp-bar-row">
                            <div>
                                <div class="sdp-bar-name">{{ $r->name }}</div>
                                <div class="sdp-meta">{{ $r->order_count }} đơn</div>
                            </div>

                            <div class="sdp-bar-track">
                                <div class="sdp-bar-fill" style="width:{{ $w }}%"></div>
                            </div>

                            <div class="tw:text-right tw:font-bold">{{ $fmt->money($r->revenue) }}</div>
                        </div>
                    @empty
                        <div class="sdp-empty">Chưa có dữ liệu biểu đồ.</div>
                    @endforelse
                </div>
            </div>

            <div class="sdp-panel">
                <div class="sdp-head">
                    <div>
                        <h2 class="sdp-title">Đơn hàng phát sinh gần nhất</h2>
                        <div class="sdp-sub">
                            Dùng để đối chiếu nhanh doanh thu, công nợ và Sales phụ trách.
                            @if($filterSalesId > 0)
                                <span class="sdp-chip green tw:ml-2">Đang lọc theo nhân viên</span>
                            @endif
                        </div>
                    </div>

                    <div class="sdp-panel-filter sdp-order-tools">
                        @if($filterSalesId > 0 && $selectedSalesUser)
                            <span class="sdp-chip green">Đang lọc: {{ $selectedSalesUser->name }}</span>
                        @else
                            <span class="sdp-chip gray">Đang xem tất cả Sales</span>
                        @endif

                        <a class="sdp-btn sdp-btn-soft"
                           href="{{ route('sales.commissions.export.excel', array_filter(['month' => $month, 'sales_id' => $filterSalesId > 0 ? $filterSalesId : null])) }}">
                            Xuất đơn hàng
                        </a>
                    </div>
                </div>

                <div class="sdp-table-wrap">
                    <table class="sdp-table sdp-order-table-compact" style="min-width:980px;">
                        <thead>
                        <tr>
                            <th>Đơn hàng</th>
                            <th>Khách / Sales</th>
                            <th>Giá trị</th>
                            <th>Thu / Nợ</th>
                            <th>Hoa hồng</th>
                            <th>Trạng thái</th>
                        </tr>
                        </thead>

                        <tbody>
                        @forelse($recentOrders as $o)
                            <tr>
                                <td>
                                    @if(!empty($o->id))
                                        <a class="sdp-chip tw:no-underline"
                                           href="{{ url('/orders/' . $o->id) }}">
                                            {{ $o->code }}
                                        </a>
                                    @else
                                        <span class="sdp-chip">{{ $o->code }}</span>
                                    @endif

                                    <div class="sdp-meta tw:mt-1">
                                        @if($o->date)
                                            {{ \Carbon\Carbon::parse($o->date)->format('d/m/Y') }}
                                        @else
                                            ---
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <div class="sdp-name">{{ $o->customer }}</div>
                                    <div class="sdp-meta">{{ $o->sales }}</div>
                                </td>

                                <td>
                                    <div class="sdp-name">{{ $fmt->money($o->total) }}</div>
                                    <div class="sdp-meta">Trước VAT: {{ $fmt->money($o->before_vat ?? 0) }}</div>
                                </td>

                                <td>
                                    <div class="sdp-name tw:text-[#198754]!">{{ $fmt->money($o->paid) }}</div>

                                    @if(($o->debt ?? 0) <= 0 && ($o->total ?? 0) > 0)
                                        <span class="sdp-chip green tw:mt-1">Đủ tiền</span>
                                    @else
                                        <span class="sdp-chip red tw:mt-1">Nợ {{ $fmt->money($o->debt ?? 0) }}</span>
                                    @endif
                                </td>

                                <td>
                                    @if(!empty($o->commission_eligible))
                                        <div class="sdp-name tw:text-[#198754]!">{{ $fmt->money($o->commission ?? 0) }}</div>
                                        <div class="sdp-meta">
                                            {{ $fmt->rate($o->commission_rate ?? (((float)($o->before_vat ?? 0) > 0) ? (((float)($o->commission ?? 0) / (float)($o->before_vat ?? 1)) * 100) : 0)) }}
                                        </div>
                                    @else
                                        <span class="sdp-chip gray">Chưa tính</span>
                                    @endif
                                </td>

                                <td><span class="sdp-chip gray">{{ $o->status ?: '---' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="sdp-empty">Chưa có đơn hàng trong tháng này.</div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="sdp-side">
            <div class="sdp-side-card">
                <div class="inner">
                    <h3>Top Sales tháng {{ $month }}</h3>
                    <p>Người có tổng thu nhập cao nhất theo dashboard.</p>

                    <div class="sdp-top-sales">
                        @if($topSales)
                            <span class="sdp-chip green">#1</span>
                            <div class="big">{{ $topSales->name }}</div>
                            <p>{{ $topSales->email }}</p>

                            <div class="sdp-income-grid">
                                <div class="sdp-income"><span>Doanh thu</span><b>{{ $fmt->money($topSales->revenue) }}</b></div>
                                <div class="sdp-income"><span>Thu nhập</span><b>{{ $fmt->money($topSales->total_income) }}</b></div>
                                <div class="sdp-income"><span>Hoa hồng</span><b>{{ $fmt->money($topSales->commission) }}</b></div>
                                <div class="sdp-income"><span>KPI</span><b>{{ $fmt->money($topSales->kpi_bonus) }}</b></div>
                            </div>
                        @else
                            <p>Chưa có dữ liệu Sales.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="sdp-panel">
                <div class="sdp-head">
                    <div>
                        <h2 class="sdp-title">Cấu hình đang áp dụng</h2>
                        <div class="sdp-sub">Đọc từ chính sách tháng {{ $month }}.</div>
                    </div>
                </div>

                <div class="tw:p-4 d-grid tw:gap-2">
                    <div class="sdp-stat m-0"><span>Công trình</span><b>{{ $fmt->percent($policy->project_rate_percent ?? 4) }}</b></div>
                    <div class="sdp-stat m-0"><span>Thương mại</span><b>{{ $fmt->percent($policy->trade_rate_percent ?? 1) }}</b></div>
                    <div class="sdp-stat m-0"><span>Tấm pin</span><b>{{ $fmt->money($policy->panel_fixed_amount ?? 15000) }}</b></div>
                    <div class="sdp-stat m-0 dark"><span>Rule bật</span><b>{{ $activeRules }}</b></div>

                    <a class="sdp-btn sdp-btn-main tw:mt-2" href="{{ route('sales.commissions.settings', ['month' => $month]) }}">
                        Sửa chính sách tháng này
                    </a>
                </div>
            </div>

            <div class="sdp-panel">
                <div class="sdp-head">
                    <div>
                        <h2 class="sdp-title">Điều kiện tính</h2>
                        <div class="sdp-sub">Trạng thái bật/tắt trong policy.</div>
                    </div>
                </div>

                <div class="tw:p-4 d-grid tw:gap-2">
                    <span class="sdp-chip {{ !empty($policy->only_paid) ? 'green' : 'gray' }}">Đã thanh toán</span>
                    <span class="sdp-chip {{ !empty($policy->only_shipped) ? 'green' : 'gray' }}">Đã xuất kho</span>
                    <span class="sdp-chip {{ !empty($policy->only_completed) ? 'green' : 'gray' }}">Hoàn tất đơn</span>
                    <span class="sdp-chip {{ !empty($policy->hold_if_debt) ? 'amber' : 'gray' }}">Giữ nếu còn công nợ</span>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- EGO_SALES_MANAGER_DROPDOWN_START --}}
{{-- $egoSalesManagerOptions do App\Services\Sales\SalesManagerDirectory cung cấp
     qua ViewComposerServiceProvider — KHÔNG truy vấn User trong Blade. --}}

<script>
(function () {
    var managers = @json($egoSalesManagerOptions);

    function cleanText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function selectLooksLikeSalesOwner(select) {
        var name = cleanText(select.getAttribute('name'));
        var id = cleanText(select.getAttribute('id'));
        var text = cleanText(select.closest('form, .modal, .card, section, div') ? select.closest('form, .modal, .card, section, div').textContent : '');

        return name.indexOf('sales') !== -1
            || name.indexOf('assigned') !== -1
            || name.indexOf('owner') !== -1
            || id.indexOf('sales') !== -1
            || text.indexOf('sales phụ trách') !== -1
            || text.indexOf('sales phu trach') !== -1;
    }

    function hasOption(select, value) {
        return Array.prototype.slice.call(select.options).some(function (opt) {
            return String(opt.value) === String(value);
        });
    }

    function addManagersToSelect(select) {
        if (!select || !selectLooksLikeSalesOwner(select)) return;

        managers.forEach(function (manager) {
            if (!manager || !manager.id || hasOption(select, manager.id)) return;

            var option = document.createElement('option');
            option.value = manager.id;
            option.textContent = manager.name + ' - Sales Manager';
            option.setAttribute('data-ego-sales-manager', '1');

            select.appendChild(option);
        });
    }

    function run() {
        if (!Array.isArray(managers) || managers.length === 0) return;

        document.querySelectorAll('select').forEach(addManagersToSelect);
    }

    document.addEventListener('DOMContentLoaded', run);

    setTimeout(run, 300);
    setTimeout(run, 900);
    setTimeout(run, 1800);

    document.addEventListener('click', function () {
        setTimeout(run, 150);
        setTimeout(run, 500);
    }, true);
})();
</script>
{{-- EGO_SALES_MANAGER_DROPDOWN_END --}}
@endsection


{{-- EGO_FIX_CONG_TRINH_CLICKABLE_START --}}
<script>
(function () {
    function normalizeText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function findSidebar() {
        var selectors = [
            'aside',
            '.sidebar',
            '#sidebar',
            '.main-sidebar',
            '.app-sidebar',
            '.side-menu',
            '.navigation',
            'nav'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var nodes = document.querySelectorAll(selectors[i]);

            for (var j = 0; j < nodes.length; j++) {
                var text = normalizeText(nodes[j].textContent);

                if (
                    text.indexOf('trang chủ') !== -1 &&
                    (
                        text.indexOf('đơn hàng') !== -1 ||
                        text.indexOf('đề nghị thanh toán') !== -1 ||
                        text.indexOf('khách hàng') !== -1 ||
                        text.indexOf('công trình') !== -1
                    )
                ) {
                    return nodes[j];
                }
            }
        }

        return null;
    }

    function isCompactMenuElement(el) {
        if (!el) return false;

        var text = normalizeText(el.textContent);

        if (text !== 'công trình') return false;

        var rect = el.getBoundingClientRect();

        return rect.height <= 80 && rect.width <= 320;
    }

    function cleanDropdownBehavior(el) {
        if (!el) return;

        [
            'data-bs-toggle',
            'data-toggle',
            'data-bs-target',
            'data-target',
            'aria-expanded',
            'aria-controls'
        ].forEach(function (attr) {
            el.removeAttribute(attr);
        });

        el.classList.remove('collapsed');
    }

    function makeClickable(el) {
        if (!el) return;

        el.setAttribute('data-ego-cong-trinh-click', '1');
        el.setAttribute('role', 'link');
        el.setAttribute('tabindex', '0');
        el.style.cursor = 'pointer';

        var link = el.matches('a') ? el : el.querySelector('a');

        if (link) {
            link.href = '/cong-trinh';
            cleanDropdownBehavior(link);
        }

        cleanDropdownBehavior(el);

        var parent = el.closest('li, .nav-item, .menu-item, .sidebar-item, [class*="nav"], [class*="menu"]');

        if (parent && parent !== el) {
            parent.setAttribute('data-ego-cong-trinh-click', '1');
            parent.style.cursor = 'pointer';
            cleanDropdownBehavior(parent);
        }
    }

    function fixMenu() {
        var sidebar = findSidebar();

        if (!sidebar) return;

        var directLink = Array.prototype.slice.call(sidebar.querySelectorAll('a')).find(function (a) {
            return normalizeText(a.textContent) === 'công trình';
        });

        if (directLink) {
            makeClickable(directLink);
        }

        var candidates = Array.prototype.slice.call(sidebar.querySelectorAll('a, button, li, div, span')).filter(isCompactMenuElement);

        candidates.forEach(makeClickable);

        var active = location.pathname.indexOf('/cong-trinh') === 0;

        if (active) {
            sidebar.querySelectorAll('[data-ego-cong-trinh-click="1"]').forEach(function (el) {
                el.classList.add('active');
            });
        }
    }

    function goToCongTrinh(event) {
        var target = event.target;
        var clickable = target && target.closest ? target.closest('[data-ego-cong-trinh-click="1"]') : null;

        if (!clickable) return;

        event.preventDefault();
        event.stopPropagation();

        window.location.href = '/cong-trinh';
    }

    function goToCongTrinhKeyboard(event) {
        if (event.key !== 'Enter' && event.key !== ' ') return;

        var target = event.target;
        var clickable = target && target.closest ? target.closest('[data-ego-cong-trinh-click="1"]') : null;

        if (!clickable) return;

        event.preventDefault();
        window.location.href = '/cong-trinh';
    }

    document.addEventListener('DOMContentLoaded', fixMenu);
    document.addEventListener('click', goToCongTrinh, true);
    document.addEventListener('keydown', goToCongTrinhKeyboard, true);

    setTimeout(fixMenu, 300);
    setTimeout(fixMenu, 900);
    setTimeout(fixMenu, 1600);
})();
</script>
{{-- EGO_FIX_CONG_TRINH_CLICKABLE_END --}}
