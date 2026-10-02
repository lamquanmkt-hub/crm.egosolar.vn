
{{-- Hai con số badge (đơn chờ duyệt, phiếu vật tư chờ duyệt) do
     App\Services\System\SidebarStatusService cấp cho partials.sidebar qua view
     composer. Trước đây đúng chỗ này có một khối 17 dòng CHÉP QUA 9 VIEW tự chạy
     lại hai câu COUNT rồi nuốt lỗi bằng catch(Throwable). Giá trị nó tính ra bị
     composer ghi đè nên không hiển thị ở đâu — chỉ tốn 2 câu truy vấn mỗi lần
     dựng trang. --}}

@extends('layouts.app')

@section('content')
{{-- EGO_CREATE_MATERIAL_REQUEST_FROM_SITE_START --}}

@if($canCreateMaterialRequest)
    <div class="tw:flex tw:justify-end tw:mb-4">
        <x-ui.button href="{{ route('material-requests.create', ['site_id' => $site->id]) }}"
           variant="primary"
           style="border-radius:12px;font-weight:800;">
            <i class="bi bi-plus-circle"></i> Tạo đơn vật tư
        </x-ui.button>
    </div>
@endif
{{-- EGO_CREATE_MATERIAL_REQUEST_FROM_SITE_END --}}


<style> :root{
        --ego:#0BC9AA;
        --ego-dark:#08a88f;
        --ego-soft:#ddfbf5;
        --ego-bg:#f3fffc;
        --ink:#0f172a;
        --muted:#64748b;
        --line:#e5f4f0;
    }.ego-page{
        background:
            radial-gradient(circle at top left, rgba(11,201,170,.16), transparent 30%),
            linear-gradient(180deg, var(--ego-bg), #fff 55%);
        min-height: calc(100vh - 64px);
    }.ego-hero{
        border:1px solid rgba(11,201,170,.24);
        background:
            radial-gradient(900px 420px at 5% 0%, rgba(11,201,170,.22), transparent 55%),
            radial-gradient(700px 360px at 95% 0%, rgba(59,130,246,.10), transparent 58%),
            linear-gradient(180deg, #fff, rgba(243,255,252,.82));
        border-radius:24px;
        box-shadow:0 18px 42px rgba(2,44,34,.08);
        overflow:hidden;
    }.ego-title{
        color:var(--ink);
        letter-spacing:-.5px;
        font-weight:900;
    }.ego-sub{
        color:var(--muted);
    }.ego-chip{
        display:inline-flex;
        gap:7px;
        align-items:center;
        background:rgba(11,201,170,.12);
        border:1px solid rgba(11,201,170,.24);
        padding:7px 11px;
        border-radius:999px;
        color:#0f766e;
        font-weight:800;
        font-size:12px;
    }.status-pill{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:7px 11px;
        border-radius:999px;
        font-size:12px;
        font-weight:800;
        border:1px solid rgba(15,23,42,.08);
        background:#fff;
    }.status-muted{ color:#475569; background:#f8fafc; border-color:#e2e8f0; }.status-warning{ color:#b45309; background:#fffbeb; border-color:#fde68a; }.status-success{ color:#047857; background:#ecfdf5; border-color:#bbf7d0; }.status-info{ color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }.debt-danger{ color:#dc2626; background:#fff1f2; border-color:#fecdd3; }.debt-ok{ color:#047857; background:#ecfdf5; border-color:#bbf7d0; }.btn-ego{
        background:linear-gradient(135deg, var(--ego), var(--ego-dark)) !important;
        border:0 !important;
        color:#fff !important;
        border-radius:14px !important;
        box-shadow:0 12px 24px rgba(11,201,170,.24);
        font-weight:800;
    }.btn-ego-outline{
        border-color:rgba(11,201,170,.40) !important;
        color:#0f766e !important;
        border-radius:14px !important;
        font-weight:800;
        background:rgba(255,255,255,.78);
    }.btn-ego-outline:hover{ background:var(--ego-soft) !important; }.kpi{
        border:1px solid var(--line);
        border-radius:18px;
        background:rgba(255,255,255,.95);
        height:100%;
        box-shadow:0 8px 20px rgba(2,44,34,.045);
    }.kpi .label{ color:var(--muted); font-size:12px; }.kpi .value{ font-weight:900; letter-spacing:-.5px; }.kpi .sub{ color:var(--muted); font-size:12px; }.ego-card{
        border:1px solid var(--line) !important;
        border-radius:20px !important;
        box-shadow:0 12px 30px rgba(2,44,34,.07) !important;
        overflow:hidden;
        background:rgba(255,255,255,.96);
    }.ego-card .card-body,
    .ego-card [data-ego-card-body]{ padding:20px !important; }.nav-pills .nav-link{
        border-radius:14px;
        color:#0b3b36;
        font-weight:900;
        background:rgba(255,255,255,.72);
        border:1px solid rgba(15,23,42,.06);
    }.nav-pills .nav-link.active{
        background:rgba(11,201,170,.15) !important;
        color:#0f766e !important;
        border:1px solid rgba(11,201,170,.30);
    }.info-grid{
        display:grid;
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:12px;
    }.info-item{
        border:1px solid rgba(15,23,42,.06);
        background:#fff;
        border-radius:16px;
        padding:13px;
    }.info-item .label{ color:var(--muted); font-size:12px; margin-bottom:4px; }.info-item .value{ font-weight:850; color:var(--ink); }.system-card{
        border:1px solid rgba(11,201,170,.20);
        background:linear-gradient(135deg, rgba(11,201,170,.10), rgba(255,255,255,.95));
        border-radius:18px;
        padding:15px;
        height:100%;
    }.system-card .icon{
        width:38px;
        height:38px;
        border-radius:14px;
        display:flex;
        align-items:center;
        justify-content:center;
        background:rgba(11,201,170,.14);
        color:#0f766e;
        margin-bottom:8px;
    }.system-card .label{ color:var(--muted); font-size:12px; }.system-card .value{ font-weight:950; font-size:22px; color:var(--ink); letter-spacing:-.4px; }.finance-main{
        border-radius:20px;
        padding:18px;
        background:
            radial-gradient(circle at top right, rgba(11,201,170,.24), transparent 42%),
            linear-gradient(135deg, rgba(11,201,170,.12), rgba(255,255,255,.96));
        border:1px solid rgba(11,201,170,.22);
    }.money-big{
        font-size:32px;
        font-weight:950;
        color:#0f766e;
        letter-spacing:-.9px;
    }.finance-box,
    .summary-box{
        border:1px solid rgba(15,23,42,.07);
        background:rgba(248,250,252,.90);
        border-radius:17px;
        padding:14px;
        height:100%;
    }.finance-box .amount,
    .summary-box .amount{
        font-weight:950;
        font-size:18px;
        letter-spacing:-.3px;
    }.progress-soft{
        height:11px;
        background:rgba(15,23,42,.08);
        border-radius:999px;
        overflow:hidden;
    }.progress-soft .bar{
        height:100%;
        border-radius:999px;
        background:linear-gradient(90deg, var(--ego), #10b981);
    }.progress-soft.cost .bar{
        background:linear-gradient(90deg, #f59e0b, #ef4444);
    }.material-summary{
        border:1px solid rgba(11,201,170,.20);
        background:rgba(11,201,170,.07);
        border-radius:18px;
        padding:14px;
        height:100%;
    }.material-summary .label{ color:var(--muted); font-size:12px; }.material-summary .value{ font-weight:950; font-size:22px; color:var(--ink); }.payment-form{
        border:1px solid rgba(11,201,170,.24);
        background:linear-gradient(135deg, rgba(11,201,170,.10), rgba(255,255,255,.96));
        border-radius:18px;
        padding:16px;
    }.ss-input{
        border-radius:13px;
        border-color:rgba(15,23,42,.12);
    }.ss-input:focus{
        border-color:rgba(11,201,170,.7);
        box-shadow:0 0 0 .2rem rgba(11,201,170,.12);
    }.table thead th{
        background:var(--ego-soft) !important;
        border-bottom:1px solid var(--line) !important;
        color:#0b3b36 !important;
        font-weight:900 !important;
        white-space:nowrap;
    }.table td{
        border-color:var(--line) !important;
        vertical-align:middle;
    }.table-hover tbody tr:hover{ background:rgba(11,201,170,.055) !important; }.group-badge{
        display:inline-flex;
        align-items:center;
        gap:6px;
        border-radius:999px;
        padding:6px 10px;
        font-size:12px;
        font-weight:900;
        border:1px solid rgba(15,23,42,.08);
        white-space:nowrap;
    }.group-main-stock{ color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }.group-main-external{ color:#7c3aed; background:#f5f3ff; border-color:#ddd6fe; }.group-sub-stock{ color:#047857; background:#ecfdf5; border-color:#bbf7d0; }.group-sub-external{ color:#b45309; background:#fffbeb; border-color:#fde68a; }.group-muted{ color:#475569; background:#f8fafc; border-color:#e2e8f0; }.actual-name{ font-weight:900; color:var(--ink); }.actual-sub{ color:var(--muted); font-size:12px; }@media (max-width: 991.98px){.sticky-top{ position:static !important; }
    }@media (max-width: 575.98px){.info-grid{ grid-template-columns:1fr; }.money-big{ font-size:25px; }
    }
</style>

<div class="container-fluid ego-page tw:p-4">

    @if(session('success'))
        <x-ui.alert variant="success" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]" style="border-radius:16px;">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]" style="border-radius:16px;">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
        </x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]" style="border-radius:16px;">
            <div class="tw:font-bold tw:mb-1">
                <i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại:
            </div>
            <ul class="tw:mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <div class="ego-hero tw:p-4 tw:mb-4">
        <div class="tw:flex flex-wrap tw:justify-between tw:items-start tw:gap-2">
            <div>
                <div class="tw:flex flex-wrap tw:items-center tw:gap-2 tw:mb-1">
                    <h3 class="ego-title tw:mb-0">Chi tiết công trình</h3>
                    <span class="ego-chip">#{{ $site->id }}</span>

                    @if($status)
                        <span class="status-pill {{ $statusInfo['class'] }}">
                            <i class="bi {{ $statusInfo['icon'] }}"></i> {{ $statusInfo['label'] }}
                        </span>
                    @endif

                    @if($canSeeCost)
                        <span class="status-pill {{ $debtClass }}">
                            <i class="bi {{ $remainingReceivable > 0 ? 'bi-exclamation-circle' : 'bi-check-circle' }}"></i>
                            {{ $debtLabel }}
                        </span>
                    @endif
                </div>

                <div class="ego-sub tw:font-semibold">{{ $site->name ?? '—' }}</div>

                <div class="small ego-sub tw:mt-1">
                    <i class="bi bi-geo-alt"></i> {{ $site->address ?? '—' }}
                </div>
            </div>

            <div class="tw:flex flex-wrap tw:gap-2">
                <x-ui.button variant="none" size="none" class="btn-ego tw:py-[6px] tw:px-3" href="{{ url('/cong-trinh/'.$site->id.'/edit') }}">
                    <i class="bi bi-pencil-square"></i> Sửa
                </x-ui.button>

                <x-ui.button variant="none" size="none" class="btn-ego-outline tw:py-[6px] tw:px-3" href="{{ url('/cong-trinh') }}">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </x-ui.button>
            </div>
        </div>

        <div class="tw:row tw:g-3 tw:mt-2">
            <div class="tw:col12-12 tw:md:col12-3">
                <div class="kpi tw:p-4">
                    <div class="label">Hệ DC</div>
                    <div class="value h4 tw:mb-0 tw:text-[#198754]!">{{ $fmt->qty($systemKwp) }} kWp</div>
                    <div class="sub">Công suất tấm pin</div>
                </div>
            </div>

            <div class="tw:col12-12 tw:md:col12-3">
                <div class="kpi tw:p-4">
                    <div class="label">Hệ AC / Inverter</div>
                    <div class="value h4 tw:mb-0 tw:text-[#0d6efd]!">{{ $fmt->qty($inverterKw) }} kW</div>
                    <div class="sub">Công suất inverter</div>
                </div>
            </div>

            <div class="tw:col12-12 tw:md:col12-3">
                <div class="kpi tw:p-4">
                    <div class="label">Lưu trữ</div>
                    <div class="value h4 tw:mb-0 tw:text-[#ffc107]!">{{ $fmt->qty($batteryKwh) }} kWh</div>
                    <div class="sub">Dung lượng pin lưu trữ</div>
                </div>
            </div>

            <div class="tw:col12-12 tw:md:col12-3">
                <div class="kpi tw:p-4">
                    <div class="label">Bảo hành đến</div>
                    <div class="value h4 tw:mb-0 tw:text-[#0dcaf0]!">{{ $fmt->date($systemSummary['warranty_to'] ?? ($site->warranty_to ?? null)) }}</div>
                    <div class="sub">Kỹ thuật: {{ $systemSummary['technician_name'] ?: ($site->technician_name ?? '—') }}</div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills tw:gap-2 tw:mb-4" id="siteTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-overview" data-bs-toggle="pill" data-bs-target="#pane-overview" type="button" role="tab">
                <i class="bi bi-grid-1x2"></i> Tổng quan
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-finance" data-bs-toggle="pill" data-bs-target="#pane-finance" type="button" role="tab">
                <i class="bi bi-cash-coin"></i> Tài chính
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-main-materials" data-bs-toggle="pill" data-bs-target="#pane-main-materials" type="button" role="tab">
                <i class="bi bi-cpu"></i> Thiết bị chính
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-sub-materials" data-bs-toggle="pill" data-bs-target="#pane-sub-materials" type="button" role="tab">
                <i class="bi bi-tools"></i> Vật tư phụ
            </button>
        </li>
    </ul>

    <div class="tab-content" id="siteTabsContent">

        <div class="tab-pane fade show active" id="pane-overview" role="tabpanel">
            <div class="tw:row tw:g-3">
                <div class="tw:col12-12 tw:min-[62rem]:col12-8">
                    <x-ui.card class="ego-card tw:mb-4">
                        <x-ui.card-body>
                            <h5 class="tw:mb-4">
                                <i class="bi bi-lightning-charge"></i> Tổng quan hệ thống
                            </h5>

                            <div class="tw:row tw:g-3">
                                <div class="tw:col12-6 tw:md:col12-3">
                                    <div class="system-card">
                                        <div class="icon"><i class="bi bi-sun"></i></div>
                                        <div class="label">PV / DC</div>
                                        <div class="value">{{ $fmt->qty($pvKwp) }}</div>
                                        <div class="small tw:text-[rgba(33,37,41,0.75)]">kWp</div>
                                    </div>
                                </div>

                                <div class="tw:col12-6 tw:md:col12-3">
                                    <div class="system-card">
                                        <div class="icon"><i class="bi bi-cpu"></i></div>
                                        <div class="label">Inverter / AC</div>
                                        <div class="value">{{ $fmt->qty($inverterKw) }}</div>
                                        <div class="small tw:text-[rgba(33,37,41,0.75)]">kW</div>
                                    </div>
                                </div>

                                <div class="tw:col12-6 tw:md:col12-3">
                                    <div class="system-card">
                                        <div class="icon"><i class="bi bi-battery-charging"></i></div>
                                        <div class="label">Pin lưu trữ</div>
                                        <div class="value">{{ $fmt->qty($batteryKwh) }}</div>
                                        <div class="small tw:text-[rgba(33,37,41,0.75)]">kWh</div>
                                    </div>
                                </div>

                                <div class="tw:col12-6 tw:md:col12-3">
                                    <div class="system-card">
                                        <div class="icon"><i class="bi bi-hdd-stack"></i></div>
                                        <div class="label">Thiết bị cấu hình</div>
                                        <div class="value">{{ $deviceCount }}</div>
                                        <div class="small tw:text-[rgba(33,37,41,0.75)]">dòng</div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="label">Loại hệ</div>
                                    <div class="value">{{ ['on_grid'=>'On-grid','hybrid'=>'Hybrid','off_grid'=>'Off-grid','other'=>'Khác'][$systemSummary['system_type'] ?: ($site->system_type ?? '')] ?? ($systemSummary['system_type'] ?: ($site->system_type ?? '—')) }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Pha / Điện áp</div>
                                    <div class="value">{{ $systemSummary['phase'] ?: ($site->phase ?? '—') }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Ngày lắp đặt</div>
                                    <div class="value">{{ $fmt->date($systemSummary['installed_at'] ?? ($site->installed_at ?? null)) }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Bảo hành đến</div>
                                    <div class="value">{{ $fmt->date($systemSummary['warranty_to'] ?? ($site->warranty_to ?? null)) }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Người phụ trách kỹ thuật</div>
                                    <div class="value">{{ $systemSummary['technician_name'] ?: ($site->technician_name ?? '—') }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Giai đoạn</div>
                                    <div class="value">{{ $systemSummary['stage'] ?: ($site->stage ?? '—') }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">Người liên hệ</div>
                                    <div class="value">{{ $site->contact_name ?? '—' }}</div>
                                </div>

                                <div class="info-item">
                                    <div class="label">SĐT liên hệ</div>
                                    <div class="value">{{ $site->contact_phone ?? '—' }}</div>
                                </div>

                                <div class="info-item" style="grid-column:1/-1;">
                                    <div class="label">Monitoring</div>

                                    <div class="tw:flex flex-wrap tw:gap-2 tw:items-center">
                                        @if(!empty($systemSummary['monitoring_link']) || !empty($site->monitoring_link))
                                            <x-ui.button variant="none" size="sm" class="btn-ego-outline" href="{{ $systemSummary['monitoring_link'] ?: $site->monitoring_link }}" target="_blank">
                                                <i class="bi bi-box-arrow-up-right"></i> Mở monitoring
                                            </x-ui.button>
                                        @endif

                                        <span class="small tw:text-[rgba(33,37,41,0.75)]">Tài khoản:</span>
                                        <span class="tw:font-bold">{{ $systemSummary['monitoring_account'] ?: ($site->monitoring_account ?? '—') }}</span>
                                    </div>
                                </div>

                                <div class="info-item" style="grid-column:1/-1;">
                                    <div class="label">Ghi chú</div>
                                    <div class="value">{{ $site->note ?? '—' }}</div>
                                </div>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>

                    <x-ui.card class="ego-card">
                        <x-ui.card-body>
                            <h5 class="tw:mb-4">
                                <i class="bi bi-diagram-3"></i> Vật tư theo đơn đã xuất kho
                            </h5>

                            <div class="tw:row tw:g-2">
                                <div class="tw:md:col12-6">
                                    <div class="summary-box">
                                        <div class="tw:text-[rgba(33,37,41,0.75)] small">Thiết bị chính</div>
                                        <div class="amount">{{ $mainSummary['rows'] }} dòng</div>
                                        <div class="small tw:text-[rgba(33,37,41,0.75)]">
                                            {{ $mainSummary['stock'] }} trong kho / {{ $mainSummary['external'] }} ngoài kho
                                        </div>

                                        @if($canSeeCost)
                                            <div class="tw:font-bold tw:text-[#198754] tw:mt-1">{{ $fmt->money($mainSummary['cost']) }}</div>
                                        @endif

                                        <x-ui.button variant="none" size="sm" class="btn-ego-outline tw:mt-2" type="button"
                                                onclick="bootstrap.Tab.getOrCreateInstance(document.querySelector('#tab-main-materials')).show()">
                                            Xem thiết bị chính
                                        </x-ui.button>
                                    </div>
                                </div>

                                <div class="tw:md:col12-6">
                                    <div class="summary-box">
                                        <div class="tw:text-[rgba(33,37,41,0.75)] small">Vật tư phụ</div>
                                        <div class="amount">{{ $subSummary['rows'] }} dòng</div>
                                        <div class="small tw:text-[rgba(33,37,41,0.75)]">
                                            {{ $subSummary['stock'] }} trong kho / {{ $subSummary['external'] }} ngoài kho
                                        </div>

                                        @if($canSeeCost)
                                            <div class="tw:font-bold tw:text-[#198754] tw:mt-1">{{ $fmt->money($subSummary['cost']) }}</div>
                                        @endif

                                        <x-ui.button variant="none" size="sm" class="btn-ego-outline tw:mt-2" type="button"
                                                onclick="bootstrap.Tab.getOrCreateInstance(document.querySelector('#tab-sub-materials')).show()">
                                            Xem vật tư phụ
                                        </x-ui.button>
                                    </div>
                                </div>
                            </div>

                            <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-4">
                                Dữ liệu 2 tab này lấy từ các <strong>Đơn vật tư đã kho duyệt / xuất kho</strong> của công trình.
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>

                <div class="tw:col12-12 tw:min-[62rem]:col12-4">
                    <x-ui.card class="ego-card tw:mb-4">
                        <x-ui.card-body>
                            <h5 class="tw:mb-4">
                                <i class="bi bi-cash-coin"></i> Doanh thu công trình
                            </h5>

                            <div class="finance-main tw:mb-4">
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng doanh thu dự án</div>
                                <div class="money-big">{{ $fmt->money($contractAmount) }}</div>

                                <div class="tw:flex tw:justify-between small tw:mt-2">
                                    <span class="tw:text-[rgba(33,37,41,0.75)]">Đã thu</span>
                                    <strong>{{ $fmt->money($receivedAmount) }}</strong>
                                </div>

                                <div class="progress-soft tw:mt-2">
                                    <div class="bar" style="width:{{ $paidPercent }}%"></div>
                                </div>
                            </div>

                            @if($canSeeCost)
                                <div class="tw:row tw:g-2">
                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Công nợ</div>
                                            <div class="amount {{ $remainingReceivable > 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $fmt->money($remainingReceivable) }}
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Chi phí vật tư</div>
                                            <div class="amount">{{ $fmt->money($materialCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Nhân công</div>
                                            <div class="amount">{{ $fmt->money($laborCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Vận chuyển</div>
                                            <div class="amount">{{ $fmt->money($transportCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Chi phí khác</div>
                                            <div class="amount">{{ $fmt->money($otherCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng chi phí</div>
                                            <div class="amount">{{ $fmt->money($totalCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-12">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Lợi nhuận</div>
                                            <div class="amount {{ $grossProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $fmt->money($grossProfit) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <x-ui.button variant="none" size="sm" class="btn-ego tw:mt-4 tw:w-full" type="button"
                                    onclick="bootstrap.Tab.getOrCreateInstance(document.querySelector('#tab-finance')).show()">
                                <i class="bi bi-plus-circle"></i> Ghi nhận thanh toán
                            </x-ui.button>
                        </x-ui.card-body>
                    </x-ui.card>

                    <x-ui.card class="ego-card">
                        <x-ui.card-body>
                            <h5 class="tw:mb-4">
                                <i class="bi bi-person-badge"></i> Phụ trách & vận hành
                            </h5>

                            <div class="info-item tw:mb-2">
                                <div class="label">Kỹ thuật phụ trách</div>
                                <div class="value">{{ $systemSummary['technician_name'] ?: ($site->technician_name ?? '—') }}</div>
                            </div>

                            <div class="info-item tw:mb-2">
                                <div class="label">Ngày lắp / bảo hành</div>
                                <div class="value">
                                    {{ $fmt->date($systemSummary['installed_at'] ?? ($site->installed_at ?? null)) }}
                                    →
                                    {{ $fmt->date($systemSummary['warranty_to'] ?? ($site->warranty_to ?? null)) }}
                                </div>
                            </div>

                            <div class="info-item">
                                <div class="label">Liên hệ khách hàng</div>
                                <div class="value">
                                    {{ $site->contact_name ?? '—' }}
                                    <div class="small tw:text-[rgba(33,37,41,0.75)]">{{ $site->contact_phone ?? '—' }}</div>
                                </div>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-finance" role="tabpanel">
            <div class="tw:row tw:g-3">
                <div class="tw:col12-12 tw:min-[62rem]:col12-5">
                    <x-ui.card class="ego-card tw:mb-4">
                        <x-ui.card-body>
                            <h5 class="tw:mb-4">
                                <i class="bi bi-cash-coin"></i> Tổng quan tài chính
                            </h5>

                            <div class="finance-main tw:mb-4">
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng doanh thu dự án / Giá trị hợp đồng</div>
                                <div class="money-big">{{ $fmt->money($contractAmount) }}</div>

                                <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-2">
                                    Tổng các đợt thanh toán: <b>{{ $fmt->money($totalTermAmount) }}</b>
                                </div>

                                <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-1">
                                    Đã thu: <b class="tw:text-[#0d6efd]">{{ $fmt->money($receivedAmount) }}</b>
                                    · Còn lại:
                                    <b class="{{ $remainingReceivable > 0 ? 'text-danger' : 'text-success' }}">
                                        {{ $fmt->money($remainingReceivable) }}
                                    </b>
                                </div>

                                <div class="progress-soft tw:mt-2">
                                    <div class="bar" style="width:{{ $paidPercent }}%"></div>
                                </div>
                            </div>

                            <div class="tw:row tw:g-2">
                                <div class="tw:col12-6">
                                    <div class="finance-box">
                                        <div class="tw:text-[rgba(33,37,41,0.75)] small">Đã thu</div>
                                        <div class="amount tw:text-[#0d6efd]">{{ $fmt->money($receivedAmount) }}</div>
                                    </div>
                                </div>

                                <div class="tw:col12-6">
                                    <div class="finance-box">
                                        <div class="tw:text-[rgba(33,37,41,0.75)] small">Công nợ</div>
                                        <div class="amount {{ $remainingReceivable > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $fmt->money($remainingReceivable) }}
                                        </div>
                                    </div>
                                </div>

                                @if($canSeeCost)
                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Chi phí vật tư</div>
                                            <div class="amount">{{ $fmt->money($materialCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Chi phí nhân công</div>
                                            <div class="amount">{{ $fmt->money($laborCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Chi phí vận chuyển</div>
                                            <div class="amount">{{ $fmt->money($transportCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Chi phí khác</div>
                                            <div class="amount">{{ $fmt->money($otherCost) }}</div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Tổng chi phí</div>
                                            <div class="amount">{{ $fmt->money($totalCost) }}</div>
                                            <div class="progress-soft cost tw:mt-2">
                                                <div class="bar" style="width:{{ $costPercent }}%"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tw:col12-6">
                                        <div class="finance-box">
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Lợi nhuận tạm tính</div>
                                            <div class="amount {{ $grossProfit >= 0 ? 'text-success' : 'text-danger' }}">
                                                {{ $fmt->money($grossProfit) }}
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if($canSeeCost)
                                <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-4">
                                    <b>Công thức:</b> Lợi nhuận = Tổng doanh thu dự án - Tổng chi phí.
                                </div>
                            @endif
                        </x-ui.card-body>
                    </x-ui.card>

                    <x-ui.card class="ego-card" id="ghi-nhan-thanh-toan">
                        <x-ui.card-body>
                            <h5 class="tw:mb-4">
                                <i class="bi bi-receipt"></i> Ghi nhận thanh toán
                            </h5>

                            @if(session('success'))
                                <x-ui.alert variant="success" class="tw:py-2 tw:mb-4">
                                    <i class="bi bi-check-circle"></i> {{ session('success') }}
                                </x-ui.alert>
                            @endif

                            @if(session('error'))
                                <x-ui.alert variant="danger" class="tw:py-2 tw:mb-4">
                                    <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
                                </x-ui.alert>
                            @endif

                            @if($errors->any())
                                <x-ui.alert variant="danger" class="tw:py-2 tw:mb-4">
                                    <div class="tw:font-bold tw:mb-1">Chưa ghi nhận được thanh toán:</div>
                                    @foreach($errors->all() as $error)
                                        <div>• {{ $error }}</div>
                                    @endforeach
                                </x-ui.alert>
                            @endif

                            <form method="POST"
                                  action="{{ route('sites.record-payment', ['id' => $site->id]) }}"
                                  class="payment-form">
                                @csrf

                                <div class="tw:mb-4">
                                    <x-ui.label>Thanh toán cho đợt</x-ui.label>
                                    <x-ui.select name="site_payment_term_id" id="paymentTermSelect" class="ss-input" required>
                                        @foreach($paymentTerms as $term)
                                            <option value="{{ $term['id'] ?? '' }}"
                                                    data-amount="{{ $term['remaining_amount'] > 0 ? $term['remaining_amount'] : $term['amount'] }}"
                                                    {{ (string)old('site_payment_term_id') === (string)($term['id'] ?? '') ? 'selected' : '' }}>
                                                {{ $term['name'] ?? 'Đợt thanh toán' }}
                                                - Còn: {{ $fmt->money($term['remaining_amount']) }}
                                            </option>
                                        @endforeach
                                    </x-ui.select>
                                </div>

                                <div class="tw:mb-4">
                                    <x-ui.label>Số tiền thanh toán <span class="tw:text-[#dc3545]">*</span></x-ui.label>
                                    <x-ui.input type="text"
                                           name="amount"
                                           id="paymentAmountInput"
                                           class="ss-input tw:text-right"
                                           value="{{ old('amount') }}"
                                           placeholder="VD: 50000000 hoặc 50.000.000"
                                           required />
                                </div>

                                <div class="tw:row tw:g-2">
                                    <div class="tw:md:col12-6">
                                        <x-ui.label>Hình thức <span class="tw:text-[#dc3545]">*</span></x-ui.label>
                                        <x-ui.select name="payment_method" class="ss-input" required>
                                            <option value="">-- Chọn --</option>
                                            <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Tiền mặt</option>
                                            <option value="bank_transfer" {{ old('payment_method') === 'bank_transfer' ? 'selected' : '' }}>Chuyển khoản</option>
                                            <option value="card" {{ old('payment_method') === 'card' ? 'selected' : '' }}>Thẻ</option>
                                            <option value="momo" {{ old('payment_method') === 'momo' ? 'selected' : '' }}>MoMo</option>
                                            <option value="vnpay" {{ old('payment_method') === 'vnpay' ? 'selected' : '' }}>VNPay</option>
                                            <option value="other" {{ old('payment_method') === 'other' ? 'selected' : '' }}>Khác</option>
                                        </x-ui.select>
                                    </div>

                                    <div class="tw:md:col12-6">
                                        <x-ui.label>Ngày thanh toán</x-ui.label>
                                        <x-ui.input type="date"
                                               name="payment_date"
                                               class="ss-input"
                                               value="{{ old('payment_date', now()->toDateString()) }}"
                                               required />
                                    </div>
                                </div>

                                <div class="tw:mt-4">
                                    <x-ui.label>Ghi chú</x-ui.label>
                                    <x-ui.input as="textarea" name="note"
                                              class="ss-input"
                                              rows="2"
                                              placeholder="VD: Khách chuyển khoản đợt 1, mã giao dịch...">{{ old('note') }}</x-ui.input>
                                </div>

                                <x-ui.button variant="none" size="none" class="btn-ego tw:w-full tw:mt-4 tw:py-[6px] tw:px-3" type="submit" onclick="return confirm('Xác nhận ghi nhận thanh toán cho công trình này?')">
                                    <i class="bi bi-check2-circle"></i> Ghi nhận thanh toán
                                </x-ui.button>
                            </form>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>

                <div class="tw:col12-12 tw:min-[62rem]:col12-7">
                    <x-ui.card class="ego-card tw:mb-4">
                        <x-ui.card-body>
                            <h5 class="tw:mb-4">
                                <i class="bi bi-calendar-check"></i> Các đợt thanh toán
                            </h5>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle tw:mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width:52px">#</th>
                                        <th>Đợt thanh toán</th>
                                        <th class="tw:text-right" style="width:90px">%</th>
                                        <th class="tw:text-right" style="width:150px">Kế hoạch</th>
                                        <th class="tw:text-right" style="width:150px">Đã thu</th>
                                        <th class="tw:text-right" style="width:150px">Còn lại</th>
                                        <th style="width:130px">Ngày dự kiến</th>
                                        <th style="width:110px">Trạng thái</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($paymentTerms as $i => $term)

                                        <tr>
                                            <td class="tw:font-bold">{{ $i + 1 }}</td>

                                            <td>
                                                <div class="tw:font-semibold">{{ $term['name'] ?? 'Đợt thanh toán' }}</div>
                                                @if(!empty($term['note']))
                                                    <div class="small tw:text-[rgba(33,37,41,0.75)]">{{ $term['note'] }}</div>
                                                @endif
                                            </td>

                                            <td class="tw:text-right">
                                                {{ isset($term['percent']) && $term['percent'] !== '' ? number_format((float)$term['percent'], 2) . '%' : '—' }}
                                            </td>

                                            <td class="tw:text-right tw:font-bold">
                                                {{ $fmt->money($term['amount']) }}
                                            </td>

                                            <td class="tw:text-right tw:text-[#0d6efd]! tw:font-bold">
                                                {{ $fmt->money($term['paid_amount']) }}
                                            </td>

                                            <td class="tw:text-right tw:font-bold {{ $term['remaining_amount'] > 0 ? 'text-danger' : 'text-success' }}">
                                                {{ $fmt->money($term['remaining_amount']) }}
                                            </td>

                                            <td>{{ $fmt->date($term['due_date'] ?? null) }}</td>

                                            <td>
                                                <span class="badge {{ $term['status_class'] }}">
                                                    {{ $term['status_label'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">
                                                Chưa có đợt thanh toán.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>

                                    <tfoot>
                                    <tr>
                                        <th colspan="3">Tổng</th>
                                        <th class="tw:text-right">{{ $fmt->money($totalTermAmount) }}</th>
                                        <th class="tw:text-right tw:text-[#0d6efd]!">{{ $fmt->money($receivedAmount) }}</th>
                                        <th class="tw:text-right {{ $remainingReceivable > 0 ? 'text-danger' : 'text-success' }}">
                                            {{ $fmt->money($remainingReceivable) }}
                                        </th>
                                        <th colspan="2"></th>
                                    </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>

                    <x-ui.card class="ego-card">
                        <x-ui.card-body>
                            <h5 class="tw:mb-4">
                                <i class="bi bi-clock-history"></i> Lịch sử thanh toán
                            </h5>

                            <div class="table-responsive">
                                <table class="table table-hover align-middle tw:mb-0">
                                    <thead>
                                    <tr>
                                        <th style="width:52px">#</th>
                                        <th>Đợt</th>
                                        <th class="tw:text-right" style="width:160px">Số tiền</th>
                                        <th style="width:150px">Hình thức</th>
                                        <th style="width:140px">Ngày thu</th>
                                        <th>Ghi chú</th>
                                    </tr>
                                    </thead>

                                    <tbody>
                                    @forelse($paymentReceipts as $i => $receipt)
                                        <tr>
                                            <td class="tw:font-bold">{{ $i + 1 }}</td>

                                            <td>{{ $receipt['term_name'] ?: 'Thanh toán chung' }}</td>

                                            <td class="tw:text-right tw:font-bold tw:text-[#0d6efd]!">
                                                {{ $fmt->money($receipt['amount'] ?? 0) }}
                                            </td>

                                            <td>
                                                <span class="badge bg-light tw:text-[#212529] border">
                                                    {{ $fmt->methodLabel($receipt['payment_method'] ?? '') }}
                                                </span>
                                            </td>

                                            <td>{{ $fmt->date($receipt['paid_at'] ?? ($receipt['created_at'] ?? null)) }}</td>

                                            <td class="tw:text-[rgba(33,37,41,0.75)]!">{{ $receipt['note'] ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">
                                                Chưa có lịch sử thanh toán.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                        </x-ui.card-body>
                    </x-ui.card>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-main-materials" role="tabpanel">
            <x-ui.card class="ego-card">
                <x-ui.card-body>
                    <div class="tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2 tw:mb-4">
                        <div>
                            <h5 class="tw:mb-1">
                                <i class="bi bi-cpu"></i> Thiết bị chính
                            </h5>
                            <div class="small tw:text-[rgba(33,37,41,0.75)]">
                                Lấy từ đơn vật tư: inverter, pin, tấm pin, smart meter... trong kho hoặc ngoài kho.
                            </div>
                        </div>

                        <div class="tw:flex flex-wrap tw:gap-2">
                            <x-ui.input size="sm" class="ss-input material-search"
                                   style="width:260px; border-radius:12px;"
                                   data-target="mainMaterialTable"
                                   placeholder="Tìm thiết bị chính..." />
                            <span class="badge bg-light tw:text-[#212529] border">{{ $mainSummary['rows'] }} dòng</span>
                        </div>
                    </div>

                    <div class="tw:row tw:g-2 tw:mb-4">
                        <div class="tw:col12-6 tw:md:col12-3">
                            <div class="material-summary">
                                <div class="label">Tổng dòng</div>
                                <div class="value">{{ $mainSummary['rows'] }}</div>
                            </div>
                        </div>

                        <div class="tw:col12-6 tw:md:col12-3">
                            <div class="material-summary">
                                <div class="label">Tổng số lượng</div>
                                <div class="value">{{ $fmt->qty($mainSummary['qty']) }}</div>
                            </div>
                        </div>

                        <div class="tw:col12-6 tw:md:col12-3">
                            <div class="material-summary">
                                <div class="label">Trong kho / Ngoài kho</div>
                                <div class="value">{{ $mainSummary['stock'] }} / {{ $mainSummary['external'] }}</div>
                            </div>
                        </div>

                        @if($canSeeCost)
                            <div class="tw:col12-6 tw:md:col12-3">
                                <div class="material-summary">
                                    <div class="label">Tổng giá vốn</div>
                                    <div class="value tw:text-[#198754]!">{{ $fmt->money($mainSummary['cost']) }}</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="mainMaterialTable">
                            <thead>
                            <tr>
                                <th style="width:52px">#</th>
                                <th style="width:110px">Đơn VT</th>
                                <th style="min-width:260px">Thiết bị</th>
                                <th style="width:150px">SKU / Mã SP</th>
                                <th style="width:170px">Nhóm</th>
                                <th class="tw:text-right" style="width:100px">SL</th>
                                <th style="width:90px">ĐVT</th>

                                @if($canSeeCost)
                                    <th class="tw:text-right" style="width:140px">Giá vốn</th>
                                    <th class="tw:text-right" style="width:90px">VAT</th>
                                    <th class="tw:text-right" style="width:150px">Thành tiền</th>
                                @endif

                                <th style="min-width:220px">Ghi chú</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($mainMaterials as $i => $item)

                                <tr>
                                    <td class="tw:font-bold">{{ $i + 1 }}</td>

                                    <td>
                                        <x-ui.button variant="none" size="sm" class="btn-ego-outline" href="{{ url('/don-vat-tu/'.$item->requestId) }}">
                                            #{{ $item->requestId }}
                                        </x-ui.button>
                                        <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-1">{{ $fmt->date($item->requestCreatedAt) }}</div>
                                    </td>

                                    <td>
                                        <div class="actual-name">{{ $item->name }}</div>
                                        <div class="actual-sub">{{ $item->inCatalog ? 'Trong catalog' : 'Ngoài kho / phát sinh' }}</div>
                                    </td>

                                    <td>
                                        @if($item->sku)
                                            <span class="badge bg-light tw:text-[#212529] border">{{ $item->sku }}</span>
                                        @elseif($item->inCatalog)
                                            <span class="badge bg-light tw:text-[#212529] border">ID: {{ $item->productId }}</span>
                                        @else
                                            <span class="tw:text-[rgba(33,37,41,0.75)]">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="group-badge {{ $item->groupClass }}">{{ $item->group }}</span>
                                    </td>

                                    <td class="tw:text-right tw:font-bold">{{ $fmt->qty($item->qty) }}</td>
                                    <td>{{ $item->unit }}</td>

                                    @if($canSeeCost)
                                        <td class="tw:text-right tw:font-bold tw:text-[#198754]!">{{ $fmt->money($item->unitCost) }}</td>
                                        <td class="tw:text-right">{{ $fmt->qty($item->vatPercent) }}%</td>
                                        <td class="tw:text-right tw:font-bold">{{ $fmt->money($item->lineTotal) }}</td>
                                    @endif

                                    <td class="tw:text-[rgba(33,37,41,0.75)]!">{{ $item->note }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canSeeCost ? 11 : 8 }}" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">
                                        Chưa có thiết bị chính đã xuất kho.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            @if($mainSummary['rows'] > 0)
                                <tfoot>
                                <tr>
                                    <th colspan="5">Tổng cộng</th>
                                    <th class="tw:text-right">{{ $fmt->qty($mainSummary['qty']) }}</th>

                                    @if($canSeeCost)
                                        <th colspan="3"></th>
                                        <th class="tw:text-right tw:text-[#198754]!">{{ $fmt->money($mainSummary['cost']) }}</th>
                                        <th></th>
                                    @else
                                        <th colspan="2"></th>
                                    @endif
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tab-pane fade" id="pane-sub-materials" role="tabpanel">
            <x-ui.card class="ego-card">
                <x-ui.card-body>
                    <div class="tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2 tw:mb-4">
                        <div>
                            <h5 class="tw:mb-1">
                                <i class="bi bi-tools"></i> Vật tư phụ
                            </h5>
                            <div class="small tw:text-[rgba(33,37,41,0.75)]">
                                Lấy từ đơn vật tư: dây, CB, rail, ống gen, phụ kiện... trong kho hoặc ngoài kho.
                            </div>
                        </div>

                        <div class="tw:flex flex-wrap tw:gap-2">
                            <x-ui.input size="sm" class="ss-input material-search"
                                   style="width:260px; border-radius:12px;"
                                   data-target="subMaterialTable"
                                   placeholder="Tìm vật tư phụ..." />
                            <span class="badge bg-light tw:text-[#212529] border">{{ $subSummary['rows'] }} dòng</span>
                        </div>
                    </div>

                    <div class="tw:row tw:g-2 tw:mb-4">
                        <div class="tw:col12-6 tw:md:col12-3">
                            <div class="material-summary">
                                <div class="label">Tổng dòng</div>
                                <div class="value">{{ $subSummary['rows'] }}</div>
                            </div>
                        </div>

                        <div class="tw:col12-6 tw:md:col12-3">
                            <div class="material-summary">
                                <div class="label">Tổng số lượng</div>
                                <div class="value">{{ $fmt->qty($subSummary['qty']) }}</div>
                            </div>
                        </div>

                        <div class="tw:col12-6 tw:md:col12-3">
                            <div class="material-summary">
                                <div class="label">Trong kho / Ngoài kho</div>
                                <div class="value">{{ $subSummary['stock'] }} / {{ $subSummary['external'] }}</div>
                            </div>
                        </div>

                        @if($canSeeCost)
                            <div class="tw:col12-6 tw:md:col12-3">
                                <div class="material-summary">
                                    <div class="label">Tổng giá vốn</div>
                                    <div class="value tw:text-[#198754]!">{{ $fmt->money($subSummary['cost']) }}</div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="subMaterialTable">
                            <thead>
                            <tr>
                                <th style="width:52px">#</th>
                                <th style="width:110px">Đơn VT</th>
                                <th style="min-width:260px">Vật tư phụ</th>
                                <th style="width:150px">SKU / Mã SP</th>
                                <th style="width:170px">Nhóm</th>
                                <th class="tw:text-right" style="width:100px">SL</th>
                                <th style="width:90px">ĐVT</th>

                                @if($canSeeCost)
                                    <th class="tw:text-right" style="width:140px">Giá vốn</th>
                                    <th class="tw:text-right" style="width:90px">VAT</th>
                                    <th class="tw:text-right" style="width:150px">Thành tiền</th>
                                @endif

                                <th style="min-width:220px">Ghi chú</th>
                            </tr>
                            </thead>

                            <tbody>
                            @forelse($subMaterials as $i => $item)

                                <tr>
                                    <td class="tw:font-bold">{{ $i + 1 }}</td>

                                    <td>
                                        <x-ui.button variant="none" size="sm" class="btn-ego-outline" href="{{ url('/don-vat-tu/'.$item->requestId) }}">
                                            #{{ $item->requestId }}
                                        </x-ui.button>
                                        <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-1">{{ $fmt->date($item->requestCreatedAt) }}</div>
                                    </td>

                                    <td>
                                        <div class="actual-name">{{ $item->name }}</div>
                                        <div class="actual-sub">{{ $item->inCatalog ? 'Trong catalog' : 'Ngoài kho / phát sinh' }}</div>
                                    </td>

                                    <td>
                                        @if($item->sku)
                                            <span class="badge bg-light tw:text-[#212529] border">{{ $item->sku }}</span>
                                        @elseif($item->inCatalog)
                                            <span class="badge bg-light tw:text-[#212529] border">ID: {{ $item->productId }}</span>
                                        @else
                                            <span class="tw:text-[rgba(33,37,41,0.75)]">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="group-badge {{ $item->groupClass }}">{{ $item->group }}</span>
                                    </td>

                                    <td class="tw:text-right tw:font-bold">{{ $fmt->qty($item->qty) }}</td>
                                    <td>{{ $item->unit }}</td>

                                    @if($canSeeCost)
                                        <td class="tw:text-right tw:font-bold tw:text-[#198754]!">{{ $fmt->money($item->unitCost) }}</td>
                                        <td class="tw:text-right">{{ $fmt->qty($item->vatPercent) }}%</td>
                                        <td class="tw:text-right tw:font-bold">{{ $fmt->money($item->lineTotal) }}</td>
                                    @endif

                                    <td class="tw:text-[rgba(33,37,41,0.75)]!">{{ $item->note }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $canSeeCost ? 11 : 8 }}" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">
                                        Chưa có vật tư phụ đã xuất kho.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>

                            @if($subSummary['rows'] > 0)
                                <tfoot>
                                <tr>
                                    <th colspan="5">Tổng cộng</th>
                                    <th class="tw:text-right">{{ $fmt->qty($subSummary['qty']) }}</th>

                                    @if($canSeeCost)
                                        <th colspan="3"></th>
                                        <th class="tw:text-right tw:text-[#198754]!">{{ $fmt->money($subSummary['cost']) }}</th>
                                        <th></th>
                                    @else
                                        <th colspan="2"></th>
                                    @endif
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

    </div>
</div>

<script>
(function(){
    document.querySelectorAll('.material-search').forEach(function(input){
        input.addEventListener('input', function(){
            const tableId = input.dataset.target;
            const table = document.getElementById(tableId);

            if (!table) return;

            const q = (input.value || '').toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(function(row){
                const text = (row.innerText || '').toLowerCase();
                row.style.display = (!q || text.includes(q)) ? '' : 'none';
            });
        });
    });

    const termSelect = document.getElementById('paymentTermSelect');
    const amountInput = document.getElementById('paymentAmountInput');

    if (termSelect && amountInput) {
        termSelect.addEventListener('change', function(){
            const option = termSelect.options[termSelect.selectedIndex];

            if (!option) return;

            const amount = option.dataset.amount || '';

            if (amount && Number(amount) > 0) {
                amountInput.value = amount;
            }
        });
    }
})();
</script>
@endsection

{{-- EGO_SITE_PAYMENT_TERM_ONLY_VIEW_START --}}

<style> .ego-pay-actions{display:flex;gap:6px;align-items:center;justify-content:flex-start}.ego-pay-btn{border:0;border-radius:8px;padding:6px 9px;font-weight:800;font-size:12px;cursor:pointer}.ego-pay-btn-edit{background:#e0f2fe;color:#075985}.ego-pay-btn-delete{background:#fee2e2;color:#991b1b}.ego-pay-modal-mask{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9998;display:none;align-items:center;justify-content:center;padding:16px}.ego-pay-modal{width:min(540px,100%);background:#fff;border-radius:16px;box-shadow:0 24px 80px rgba(0,0,0,.22);padding:18px}.ego-pay-modal h3{margin:0 0 14px;font-size:18px;font-weight:900}.ego-pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ego-pay-field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}.ego-pay-field label{font-size:13px;font-weight:800;color:#334155}.ego-pay-field input,
    .ego-pay-field select,
    .ego-pay-field textarea{border:1px solid #cbd5e1;border-radius:10px;padding:9px 10px;width:100%}.ego-pay-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}.ego-pay-modal-actions button{border:0;border-radius:10px;padding:9px 14px;font-weight:900;cursor:pointer}.ego-pay-cancel{background:#e5e7eb;color:#111827}.ego-pay-save{background:#10b981;color:#fff}@media(max-width:640px){.ego-pay-grid{grid-template-columns:1fr}}
</style>

<div class="ego-pay-modal-mask" id="egoPaymentEditModal">
    <div class="ego-pay-modal">
        <h3>Sửa thanh toán</h3>

        <form method="POST" id="egoPaymentEditForm">
            @csrf
            @method('PUT')

            <div class="ego-pay-field">
                <label>Thanh toán cho đợt *</label>
                <select name="site_payment_term_id" id="egoPaymentTermId" required>
                    @foreach($paymentEditorTerms as $term)
                        <option value="{{ $term['id'] }}">
                            {{ $term['name'] }} - {{ $fmt->money($term['amount']) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="ego-pay-grid">
                <div class="ego-pay-field">
                    <label>Số tiền *</label>
                    <input name="amount" id="egoPaymentAmount" inputmode="decimal" required>
                </div>

                <div class="ego-pay-field">
                    <label>Ngày thanh toán *</label>
                    <input type="date" name="payment_date" id="egoPaymentDate" required>
                </div>
            </div>

            <div class="ego-pay-field">
                <label>Hình thức *</label>
                <select name="payment_method" id="egoPaymentMethod" required>
                    <option value="cash">Tiền mặt</option>
                    <option value="bank_transfer">Chuyển khoản</option>
                    <option value="card">Thẻ</option>
                    <option value="other">Khác</option>
                </select>
            </div>

            <div class="ego-pay-field">
                <label>Ghi chú</label>
                <textarea name="note" id="egoPaymentNote" rows="3"></textarea>
            </div>

            <div class="ego-pay-modal-actions">
                <button type="button" class="ego-pay-cancel" id="egoPaymentCloseBtn">Đóng</button>
                <button type="submit" class="ego-pay-save">Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var receipts = @json($paymentEditorRows);
    var baseUrl = @json(url('/cong-trinh/' . $site->id . '/thanh-toan'));
    var csrf = @json(csrf_token());

    function findPaymentTable() {
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));

        return tables.find(function (table) {
            var text = (table.innerText || '').toLowerCase();
            return text.indexOf('số tiền') !== -1
                && text.indexOf('hình thức') !== -1
                && (text.indexOf('ngày thu') !== -1 || text.indexOf('ngày thanh toán') !== -1);
        });
    }

    function money(v) {
        v = Number(v || 0);
        return v.toLocaleString('vi-VN');
    }

    function openEdit(row) {
        var modal = document.getElementById('egoPaymentEditModal');
        var form = document.getElementById('egoPaymentEditForm');

        form.action = baseUrl + '/' + row.id + '/cap-nhat';
        document.getElementById('egoPaymentTermId').value = row.term_id || '';
        document.getElementById('egoPaymentAmount').value = money(row.amount);
        document.getElementById('egoPaymentDate').value = (row.receipt_date || '').substring(0, 10);
        document.getElementById('egoPaymentMethod').value = row.payment_method || 'cash';
        document.getElementById('egoPaymentNote').value = row.note || '';

        modal.style.display = 'flex';
    }

    function deletePayment(row) {
        if (!confirm('Xóa thanh toán này?')) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = baseUrl + '/' + row.id + '/xoa';
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<input type="hidden" name="_method" value="DELETE">';

        document.body.appendChild(form);
        form.submit();
    }

    function enhanceTable() {
        if (!receipts.length) {
            return;
        }

        var table = findPaymentTable();

        if (!table) {
            return;
        }

        var headerRow = table.querySelector('thead tr') || table.querySelector('tr');

        if (headerRow && !headerRow.querySelector('[data-ego-pay-actions-head]')) {
            var th = document.createElement('th');
            th.textContent = 'Thao tác';
            th.setAttribute('data-ego-pay-actions-head', '1');
            headerRow.appendChild(th);
        }

        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));

        if (!rows.length) {
            rows = Array.prototype.slice.call(table.querySelectorAll('tr')).slice(1);
        }

        rows.forEach(function (tr, index) {
            if (tr.querySelector('[data-ego-pay-actions-cell]')) {
                return;
            }

            var row = receipts[index];

            if (!row || !row.id) {
                return;
            }

            var td = document.createElement('td');
            td.setAttribute('data-ego-pay-actions-cell', '1');

            var wrap = document.createElement('div');
            wrap.className = 'ego-pay-actions';

            var edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'ego-pay-btn ego-pay-btn-edit';
            edit.textContent = 'Sửa';
            edit.addEventListener('click', function () {
                openEdit(row);
            });

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'ego-pay-btn ego-pay-btn-delete';
            del.textContent = 'Xóa';
            del.addEventListener('click', function () {
                deletePayment(row);
            });

            wrap.appendChild(edit);
            wrap.appendChild(del);
            td.appendChild(wrap);
            tr.appendChild(td);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        enhanceTable();

        var closeBtn = document.getElementById('egoPaymentCloseBtn');
        var modal = document.getElementById('egoPaymentEditModal');

        if (closeBtn && modal) {
            closeBtn.addEventListener('click', function () {
                modal.style.display = 'none';
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    });
})();
</script>
{{-- EGO_SITE_PAYMENT_TERM_ONLY_VIEW_END --}}


{{-- EGO_FIX_PAYMENT_HISTORY_SHOW_DATE_START --}}
{{-- $egoFixPayments và $egoFixTermOptions do
     App\Services\Projects\SitePaymentEditorService cấp qua view composer.
     Trước đây đúng chỗ này là 74 dòng PHP với hai truy vấn có leftJoin, rồi định
     dạng tiền/ngày/tên người tạo ngay giữa HTML của một tệp 2.100 dòng. --}}

<style> .ego-fix-pay-actions{display:flex;gap:6px;align-items:center;justify-content:flex-start}.ego-fix-pay-btn{border:0;border-radius:8px;padding:6px 9px;font-weight:800;font-size:12px;cursor:pointer;white-space:nowrap}.ego-fix-pay-edit{background:#e0f2fe;color:#075985}.ego-fix-pay-delete{background:#fee2e2;color:#991b1b}.ego-fix-pay-money{font-weight:900;color:#0066ff;white-space:nowrap}.ego-fix-pay-badge{display:inline-flex;align-items:center;border:1px solid #dbe3ef;border-radius:999px;padding:4px 8px;font-size:12px;font-weight:800;background:#fff;white-space:nowrap}.ego-fix-pay-table th,
    .ego-fix-pay-table td{vertical-align:middle}.ego-fix-pay-modal-mask{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:9999;display:none;align-items:center;justify-content:center;padding:16px}.ego-fix-pay-modal{width:min(560px,100%);background:#fff;border-radius:16px;box-shadow:0 24px 80px rgba(0,0,0,.22);padding:18px}.ego-fix-pay-modal h3{margin:0 0 14px;font-size:18px;font-weight:900}.ego-fix-pay-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ego-fix-pay-field{display:flex;flex-direction:column;gap:6px;margin-bottom:12px}.ego-fix-pay-field label{font-size:13px;font-weight:800;color:#334155}.ego-fix-pay-field input,
    .ego-fix-pay-field select,
    .ego-fix-pay-field textarea{border:1px solid #cbd5e1;border-radius:10px;padding:9px 10px;width:100%}.ego-fix-pay-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:12px}.ego-fix-pay-modal-actions button{border:0;border-radius:10px;padding:9px 14px;font-weight:900;cursor:pointer}.ego-fix-pay-cancel{background:#e5e7eb;color:#111827}.ego-fix-pay-save{background:#10b981;color:#fff}@media(max-width:640px){.ego-fix-pay-grid{grid-template-columns:1fr}}
</style>

<div class="ego-fix-pay-modal-mask" id="egoFixPayModal">
    <div class="ego-fix-pay-modal">
        <h3>Sửa thanh toán</h3>

        <form method="POST" id="egoFixPayForm">
            @csrf
            @method('PUT')

            <div class="ego-fix-pay-field">
                <label>Thanh toán cho đợt *</label>
                <select name="site_payment_term_id" id="egoFixPayTerm" required>
                    @foreach($egoFixTermOptions as $term)
                        <option value="{{ $term['id'] }}">{{ $term['name'] }} - {{ $term['amount_text'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ego-fix-pay-grid">
                <div class="ego-fix-pay-field">
                    <label>Số tiền *</label>
                    <input name="amount" id="egoFixPayAmount" required>
                </div>

                <div class="ego-fix-pay-field">
                    <label>Ngày thu *</label>
                    <input type="date" name="payment_date" id="egoFixPayDate" required>
                </div>
            </div>

            <div class="ego-fix-pay-field">
                <label>Hình thức *</label>
                <select name="payment_method" id="egoFixPayMethod" required>
                    <option value="cash">Tiền mặt</option>
                    <option value="bank_transfer">Chuyển khoản</option>
                    <option value="transfer">Chuyển khoản</option>
                    <option value="card">Thẻ</option>
                    <option value="other">Khác</option>
                </select>
            </div>

            <div class="ego-fix-pay-field">
                <label>Ghi chú</label>
                <textarea name="note" id="egoFixPayNote" rows="3"></textarea>
            </div>

            <div class="ego-fix-pay-modal-actions">
                <button type="button" class="ego-fix-pay-cancel" id="egoFixPayClose">Đóng</button>
                <button type="submit" class="ego-fix-pay-save">Lưu cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var rows = @json($egoFixPayments);
    var siteId = @json($egoFixSiteId);
    var csrf = @json(csrf_token());
    var baseUrl = @json(url('/cong-trinh/' . $egoFixSiteId . '/thanh-toan'));

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function findPaymentHistoryTable() {
        var tables = Array.prototype.slice.call(document.querySelectorAll('table'));

        return tables.find(function (table) {
            var text = (table.innerText || '').toLowerCase();

            return text.indexOf('số tiền') !== -1
                && text.indexOf('hình thức') !== -1
                && text.indexOf('ngày thu') !== -1
                && text.indexOf('thao tác') !== -1;
        });
    }

    function openEdit(row) {
        var modal = document.getElementById('egoFixPayModal');
        var form = document.getElementById('egoFixPayForm');

        form.action = baseUrl + '/' + row.id + '/cap-nhat';

        document.getElementById('egoFixPayTerm').value = row.term_id || '';
        document.getElementById('egoFixPayAmount').value = Number(row.amount || 0).toLocaleString('vi-VN');
        document.getElementById('egoFixPayDate').value = row.receipt_date || '';
        document.getElementById('egoFixPayMethod').value = row.payment_method || 'bank_transfer';
        document.getElementById('egoFixPayNote').value = row.note === '—' ? '' : (row.note || '');

        modal.style.display = 'flex';
    }

    function submitDelete(row) {
        if (!confirm('Xóa thanh toán #' + row.id + '?')) {
            return;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = baseUrl + '/' + row.id + '/xoa';
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<input type="hidden" name="_method" value="DELETE">';

        document.body.appendChild(form);
        form.submit();
    }

    function renderPaymentHistory() {
        var table = findPaymentHistoryTable();

        if (!table) {
            return;
        }

        table.classList.add('ego-fix-pay-table');

        table.innerHTML =
            '<thead>' +
                '<tr>' +
                    '<th>#</th>' +
                    '<th>Đợt</th>' +
                    '<th>Số tiền</th>' +
                    '<th>Hình thức</th>' +
                    '<th>Ngày thu</th>' +
                    '<th>Người ghi nhận</th>' +
                    '<th>Ghi chú</th>' +
                    '<th>Thao tác</th>' +
                '</tr>' +
            '</thead>' +
            '<tbody></tbody>';

        var tbody = table.querySelector('tbody');

        if (!rows.length) {
            tbody.innerHTML =
                '<tr><td colspan="8" style="text-align:center;color:#64748b;padding:14px">Chưa có thanh toán theo đợt.</td></tr>';
            return;
        }

        rows.forEach(function (row, index) {
            var tr = document.createElement('tr');

            tr.innerHTML =
                '<td>' + (index + 1) + '</td>' +
                '<td>' + escapeHtml(row.term_name) + '</td>' +
                '<td class="ego-fix-pay-money">' + escapeHtml(row.amount_text) + '</td>' +
                '<td><span class="ego-fix-pay-badge">' + escapeHtml(row.payment_method_text) + '</span></td>' +
                '<td>' + escapeHtml(row.receipt_date_text) + '</td>' +
                '<td>' + escapeHtml(row.creator_text) + '</td>' +
                '<td>' + escapeHtml(row.note) + '</td>' +
                '<td><div class="ego-fix-pay-actions">' +
                    '<button type="button" class="ego-fix-pay-btn ego-fix-pay-edit">Sửa</button>' +
                    '<button type="button" class="ego-fix-pay-btn ego-fix-pay-delete">Xóa</button>' +
                '</div></td>';

            tr.querySelector('.ego-fix-pay-edit').addEventListener('click', function () {
                openEdit(row);
            });

            tr.querySelector('.ego-fix-pay-delete').addEventListener('click', function () {
                submitDelete(row);
            });

            tbody.appendChild(tr);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderPaymentHistory();
        setTimeout(renderPaymentHistory, 80);
        setTimeout(renderPaymentHistory, 300);

        var modal = document.getElementById('egoFixPayModal');
        var closeBtn = document.getElementById('egoFixPayClose');

        if (modal && closeBtn) {
            closeBtn.addEventListener('click', function () {
                modal.style.display = 'none';
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    });
})();
</script>
{{-- EGO_FIX_PAYMENT_HISTORY_SHOW_DATE_END --}}
