@extends('layouts.app')
{{-- Ba ô chọn dưới đây do Tom Select quản lý nên PHẢI giữ class Bootstrap.
     Theme tom-select.bootstrap5 chép class của thẻ gốc lên `.ts-wrapper` rồi tô
     theo `.form-select`; bỏ class đi thì `.ts-wrapper:not(.form-control,.form-select)`
     xoá luôn viền/nền của wrapper và `.form-select .ts-control input` không còn
     đặt màu chữ (đo được: #212529 -> #343a40). Chuyển được chúng là việc riêng,
     phải làm cùng lúc với theme của Tom Select. --}}

@section('title', 'Bảo trì & Bảo hành công trình')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v4.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v4.css')) ? filemtime(public_path('css/technical-maintenance-v4.css')) : time() }}">
@endsection

@section('content')

{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="ego-container tm4-page tw:py-4">
    <nav class="tm4-breadcrumb"><a href="{{ route('dashboard') }}">Trang chủ</a><i class="bi bi-chevron-right"></i><span>Dự án</span><i class="bi bi-chevron-right"></i><strong>Bảo trì &amp; Bảo hành</strong></nav>

    @if(session('success'))<div class="tm4-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="tm4-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($allFormErrors->isNotEmpty())
        <div class="tm4-alert danger align-start"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể lưu dữ liệu</strong><ul>@foreach($allFormErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <header class="tm4-hero">
        <div class="tm4-hero-main">
            <span class="tm4-kicker">TRUNG TÂM ĐIỀU HÀNH O&amp;M</span>
            <h1>Bảo trì &amp; Bảo hành công trình</h1>
            <p>Trang tổng quan điều hành dành cho Ban Giám đốc — theo dõi lịch bảo trì, sự cố, serial, kho đổi bảo hành và tiến độ xử lý theo từng công trình.</p>
        </div>
        <div class="tm4-hero-actions">
            <x-ui.button variant="light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[41px] tw:rounded-[10px] tw:text-[13px]/[19.5px] tw:font-[750] tw:whitespace-nowrap tw:max-[721px]:flex-auto" href="{{ route('products.serials.index') }}"><i class="bi bi-upc-scan"></i> Tra cứu serial</x-ui.button>
            @if($permissions['claim_create'])<x-ui.button variant="outline-light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[41px] tw:rounded-[10px] tw:text-[13px]/[19.5px] tw:font-[750] tw:whitespace-nowrap tw:max-[721px]:flex-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#tm4ClaimDrawer"><i class="bi bi-shield-plus"></i> Tạo phiếu sự cố</x-ui.button>@endif
            @if($permissions['create'])<x-ui.button variant="warning" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[41px] tw:rounded-[10px] tw:text-[13px]/[19.5px] tw:font-[750] tw:whitespace-nowrap tw:max-[721px]:flex-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#tm4CreateDrawer"><i class="bi bi-calendar-plus"></i> Tạo kế hoạch</x-ui.button>@endif
        </div>
    </header>

    <nav class="tm4-tabs tm4-primary-tabs" aria-label="Điều hướng O&M">
        @foreach($tabs as $key => $tab)
            <a class="{{ $activeView === $key ? 'active' : '' }}" href="{{ route('projects-unified.maintenance.index', ['view'=>$key]) }}">
                <i class="bi {{ $tab['icon'] }}"></i><span>{{ $tab['label'] }}</span>
                @if($key === 'claims' && ($warrantySummary['open'] ?? 0) > 0)<b>{{ $warrantySummary['open'] }}</b>@endif
                @if($key === 'stock' && ($warrantySummary['stock_pending'] ?? 0) > 0)<b>{{ $warrantySummary['stock_pending'] }}</b>@endif
            </a>
        @endforeach
    </nav>

    @if($activeView === 'overview')
        <section class="tm4-kpi-grid tm4-executive-kpis">
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}" class="tm4-kpi"><span class="blue"><i class="bi bi-calendar2-week"></i></span><div><small>Tổng lịch bảo trì</small><strong>{{ number_format($summary['total']) }}</strong><em>{{ number_format($summary['in_progress']) }} đang thực hiện</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','date_from'=>now()->toDateString(),'date_to'=>now()->addDays(7)->toDateString()]) }}" class="tm4-kpi"><span class="amber"><i class="bi bi-clock-history"></i></span><div><small>Sắp đến hạn</small><strong>{{ number_format($summary['upcoming']) }}</strong><em>Còn trong 7 ngày</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','overdue'=>1]) }}" class="tm4-kpi is-danger"><span class="red"><i class="bi bi-exclamation-triangle"></i></span><div><small>Quá hạn</small><strong>{{ number_format($summary['overdue']) }}</strong><em>Cần xử lý ngay</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'claims']) }}" class="tm4-kpi"><span class="violet"><i class="bi bi-tools"></i></span><div><small>Đang xử lý sự cố</small><strong>{{ number_format($warrantySummary['open'] ?? 0) }}</strong><em>{{ number_format($warrantySummary['urgent_open'] ?? 0) }} phiếu khẩn cấp</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'pending_approval']) }}" class="tm4-kpi"><span class="violet"><i class="bi bi-file-earmark-check"></i></span><div><small>Phiếu chờ duyệt</small><strong>{{ number_format($pendingApprovalTotal) }}</strong><em>Chờ Ban quản lý duyệt</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'completed']) }}" class="tm4-kpi"><span class="green"><i class="bi bi-check2-circle"></i></span><div><small>Hoàn thành tháng này</small><strong>{{ number_format($completedThisMonth) }}</strong><em>Tỷ lệ {{ \App\Support\DisplayFormat::percent($summary['completion_rate'] ?? 0) }} tổng lịch</em></div></a>
        </section>

        <div class="tm4-dashboard-grid tm4-executive-grid">
            <section class="tm4-card tm4-span-8 tm4-watch-card">
                <div class="tm4-card-head tm4-toolbar-head">
                    <div><span>ĐIỀU HÀNH TRỰC TIẾP</span><h2>Công trình cần theo dõi</h2></div>
                    <div class="tm4-head-tools">
                        <a class="tm4-inline-search" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance']) }}"><i class="bi bi-search"></i><span>Tìm kiếm công trình...</span></a>
                        <a class="tm4-tool-button" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}">Tất cả trạng thái <i class="bi bi-chevron-down"></i></a>
                        <a class="tm4-tool-button" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','overdue'=>1]) }}">Cần ưu tiên <i class="bi bi-sliders"></i></a>
                    </div>
                </div>
                <div class="tm4-table-wrap">
                    <table class="tm4-table tm4-exec-table">
                        <thead><tr><th>Công trình</th><th>Mã lịch</th><th>Hạng mục</th><th>Đợt bảo trì</th><th>Ngày dự kiến</th><th>Tiến độ thời gian</th><th>Kỹ thuật phụ trách</th><th>Trạng thái</th><th>Ưu tiên</th></tr></thead>
                        <tbody>
                        @forelse($overviewRows as $row)
                            <tr class="{{ $row->overdue ? 'is-overdue' : '' }}">
                                <td><a class="tm4-main-link" href="{{ route('projects-unified.maintenance.show',$row->schedule) }}">{{ $row->schedule->site?->name ?: $row->schedule->site_name ?: 'Công trình chưa đặt tên' }}</a><small>{{ \Illuminate\Support\Str::limit($row->schedule->site?->address ?: $row->schedule->address, 40) }}</small></td>
                                <td><a class="tm4-code" href="{{ route('projects-unified.maintenance.show',$row->schedule) }}">{{ $row->schedule->schedule_code ?: '#'.$row->schedule->id }}</a></td>
                                <td><strong>{{ $types[$row->schedule->type] ?? $row->schedule->type }}</strong><small>{{ $row->capacity ? number_format((float)$row->capacity,0).' kWp' : 'Chưa cập nhật công suất' }}</small></td>
                                <td><strong>Đợt {{ $row->roundNo }}</strong><small>{{ $row->roundNo }}/{{ $row->totalRounds }} chu kỳ</small></td>
                                <td><strong class="{{ $row->overdue ? 'text-danger' : '' }}">{{ optional($row->scheduledDate)->format('d/m/Y') ?: '—' }}</strong></td>
                                <td><span class="tm4-time-pill {{ $row->timeTone }}">{{ $row->timeLabel }}</span></td>
                                <td>
                                    <div class="tm4-person {{ $row->isUnassigned ? 'unassigned' : '' }}">
                                        <span class="tm4-avatar">{{ $row->avatarInitial }}</span>
                                        <div><strong>{{ $row->leaderName ?: 'Chưa phân công' }}</strong><small>{{ $row->isUnassigned ? 'Cần điều phối kỹ thuật' : ($row->schedule->assignees->count().' người trong nhóm') }}</small></div>
                                    </div>
                                </td>
                                <td><span class="tm4-badge {{ $statusTone[$row->schedule->status] ?? 'muted' }}">{{ $statuses[$row->schedule->status] ?? $row->schedule->status }}</span></td>
                                <td><span class="tm4-badge {{ $priorityTone[$row->schedule->priority] ?? 'muted' }}">{{ $priorities[$row->schedule->priority] ?? $row->schedule->priority }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="tm4-empty"><i class="bi bi-calendar2-check"></i><h3>Chưa có lịch cần theo dõi</h3><p>Các lịch mới, quá hạn và sắp đến hạn sẽ xuất hiện tại đây.</p></div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="tm4-table-footer"><span>Hiển thị {{ $overviewSchedules->count() }} lịch ưu tiên</span><a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}">Mở toàn bộ danh sách <i class="bi bi-arrow-right"></i></a></div>
            </section>

            <section class="tm4-card tm4-span-4 tm4-command-card">
                <div class="tm4-card-head"><div><span>THEO DÕI THÁNG {{ now()->format('m/Y') }}</span><h2>Tổng quan điều hành</h2></div><a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance']) }}">Chi tiết</a></div>
                <div class="tm4-command-summary">
                    <div class="tm4-operation-donut" style="--scheduled-deg:{{ $scheduledDeg }}deg;--processing-end-deg:{{ $processingEndDeg }}deg;--approval-end-deg:{{ $approvalEndDeg }}deg;--completed-end-deg:{{ $completedEndDeg }}deg"><div><strong>{{ number_format($summary['total']) }}</strong><small>Tổng lịch</small></div></div>
                    <div class="tm4-operation-legend">
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}"><i class="scheduled"></i><span>Đã lên lịch</span><strong>{{ $summary['scheduled_bucket'] ?? 0 }} ({{ $scheduledPct }}%)</strong></a>
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'in_progress']) }}"><i class="processing"></i><span>Đang xử lý</span><strong>{{ $summary['processing_bucket'] ?? 0 }} ({{ $processingPct }}%)</strong></a>
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','overdue'=>1]) }}"><i class="overdue"></i><span>Quá hạn</span><strong>{{ $summary['overdue'] ?? 0 }}</strong></a>
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'pending_approval']) }}"><i class="approval"></i><span>Chờ duyệt</span><strong>{{ $summary['approval_bucket'] ?? 0 }} ({{ $approvalPct }}%)</strong></a>
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'completed']) }}"><i class="completed"></i><span>Hoàn thành</span><strong>{{ $summary['completed'] ?? 0 }} ({{ $completedPct }}%)</strong></a>
                    </div>
                </div>
                <div class="tm4-command-alerts">
                    <h3>Cảnh báo &amp; cần chú ý</h3>
                    <a class="danger" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','overdue'=>1]) }}"><i class="bi bi-exclamation-triangle"></i><span><strong>{{ number_format($summary['overdue']) }}</strong> lịch bảo trì quá hạn</span></a>
                    <a class="warning" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'unassigned']) }}"><i class="bi bi-person-x"></i><span><strong>{{ number_format($summary['unassigned']) }}</strong> lịch chưa phân công kỹ thuật</span></a>
                    <a class="violet" href="{{ route('projects-unified.maintenance.index',['view'=>'claims','claim_status'=>'pending_approval']) }}"><i class="bi bi-file-earmark-check"></i><span><strong>{{ number_format($warrantySummary['pending_approval'] ?? 0) }}</strong> phiếu bảo hành chờ duyệt</span></a>
                    <a class="blue" href="{{ route('projects-unified.maintenance.index',['view'=>'stock','stock_status'=>'pending']) }}"><i class="bi bi-box-seam"></i><span><strong>{{ number_format($warrantySummary['stock_pending'] ?? 0) }}</strong> phiếu chờ kho xuất đổi</span></a>
                </div>
                <div class="tm4-activity-list">
                    <h3>Hoạt động nổi bật</h3>
                    @forelse($overviewSchedules->take(3) as $activitySchedule)
                        <a href="{{ route('projects-unified.maintenance.show',$activitySchedule) }}"><i class="{{ $activitySchedule->isOverdue() ? 'danger' : 'info' }}"></i><span>{{ $activitySchedule->site?->name ?: $activitySchedule->site_name }} — Đợt {{ max(1,(int)($activitySchedule->round_no ?: 1)) }} {{ $activitySchedule->isOverdue() ? 'đang quá hạn' : 'cần theo dõi' }}</span><small>{{ optional($activitySchedule->updated_at)->diffForHumans() }}</small></a>
                    @empty<p>Chưa có hoạt động mới.</p>@endforelse
                </div>
            </section>

            <section class="tm4-card tm4-span-4 tm4-workload-card">
                <div class="tm4-card-head"><div><span>NGUỒN LỰC KỸ THUẬT</span><h2>Kỹ thuật đang phụ trách</h2></div><a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}">Điều phối</a></div>
                <div class="tm4-workload-table">
                    <div class="tm4-workload-head"><span>Kỹ thuật viên</span><span>Công việc</span><span>Quá hạn</span><span>Hôm nay</span><span>Hoàn thành</span></div>
                    @forelse($technicianWorkload as $workload)
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','assignee_id'=>$workload['user']->id]) }}" class="tm4-workload-row">
                            <span class="tech"><b>{{ $workload['initial'] }}</b><span><strong>{{ $workload['user']->name }}</strong><small>{{ $workload['active'] > 0 ? $workload['active_sites'].' công trình / '.$workload['active'].' việc' : 'Chưa có lịch đang xử lý' }}</small></span></span>
                            <span>{{ $workload['active'] }}</span>
                            <span class="{{ $workload['overdue'] > 0 ? 'text-danger fw-bold' : '' }}">{{ $workload['overdue'] }}</span>
                            <span class="tw:text-[#0d6efd]! tw:font-bold">{{ $workload['today'] }}</span>
                            <span class="rate"><i><u style="width:{{ $workload['completion_rate'] }}%"></u></i><strong>{{ $workload['completion_rate'] }}%</strong></span>
                        </a>
                    @empty<div class="tm4-empty small"><i class="bi bi-people"></i><p>Chưa tìm thấy nhân sự kỹ thuật khả dụng.</p></div>@endforelse
                </div>
            </section>

            <section class="tm4-card tm4-span-8 tm4-round-card">
                <div class="tm4-card-head"><div><span>TIẾN ĐỘ THEO CHU KỲ</span><h2>Lịch bảo trì sắp tới theo đợt</h2></div><a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}"><i class="bi bi-chevron-right"></i></a></div>
                <div class="tm4-round-grid">
                    @forelse($maintenanceRounds as $round)
                        <a class="tm4-round-item {{ $round['tone'] }}" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}">
                            <h3>Đợt {{ $round['round'] }}</h3>
                            <strong>{{ number_format($round['total']) }} công trình</strong>
                            <span>{{ $round['dateLabel'] }}</span>
                            <small>{{ $round['timeLabel'] }}</small>
                            <i><u style="width:{{ $round['progress'] }}%"></u></i>
                            <em>{{ $round['progress'] }}% đã lên lịch</em>
                        </a>
                    @empty<div class="tm4-empty small"><i class="bi bi-calendar-range"></i><p>Chưa có dữ liệu chu kỳ bảo trì.</p></div>@endforelse
                </div>
            </section>

            <section class="tm4-card tm4-span-6 tm4-flow-card">
                <div class="tm4-card-head"><div><span>QUY TRÌNH 8 BƯỚC</span><h2>Luồng bảo trì định kỳ</h2></div></div>
                <div class="tm4-flow green-flow compact-flow">
                    @foreach($maintenanceFlow as $i => $step)
                        <div><span>{{ $i+1 }}</span><i class="bi bi-{{ $step['icon'] }}"></i><small>{{ $step['label'] }}</small></div>@if($i<7)<b><i class="bi bi-arrow-right"></i></b>@endif
                    @endforeach
                </div>
            </section>

            <section class="tm4-card tm4-span-6 tm4-flow-card">
                <div class="tm4-card-head"><div><span>QUY TRÌNH 10 BƯỚC</span><h2>Luồng bảo hành / xử lý sự cố</h2></div></div>
                <div class="tm4-flow violet-flow compact-flow">
                    @foreach($warrantyFlow as $i => $step)
                        <div><span>{{ $i+1 }}</span><i class="bi bi-{{ $step['icon'] }}"></i><small>{{ $step['label'] }}</small></div>@if($i<9)<b><i class="bi bi-arrow-right"></i></b>@endif
                    @endforeach
                </div>
            </section>
        </div>
    @endif

    @if($activeView === 'maintenance')
        <section class="tm4-kpi-grid maintenance-kpi">
            @foreach($maintenanceKpis as $kpi)
                <a href="{{ route('projects-unified.maintenance.index', $kpi['params']) }}" class="tm4-kpi"><span class="{{ $kpi['tone'] }}"><i class="bi bi-{{ $kpi['icon'] }}"></i></span><div><small>{{ $kpi['label'] }}</small><strong>{{ number_format($kpi['value']) }}</strong></div></a>
            @endforeach
        </section>

        <section class="tm4-card tm4-filter-card"><form method="GET" action="{{ route('projects-unified.maintenance.index') }}" class="tm4-filter"><input type="hidden" name="view" value="maintenance"><label class="search"><i class="bi bi-search"></i><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Mã lịch, công trình, khách hàng, SĐT..."></label><x-ui.input class="tm4-input" type="month" name="month" value="{{ $filters['month'] ?? '' }}" /><x-ui.select class="tm4-input" name="status"><option value="">Tất cả trạng thái</option>@foreach($statuses as $v=>$l)<option value="{{ $v }}" @selected(($filters['status']??'')===$v)>{{ $l }}</option>@endforeach</x-ui.select><x-ui.select class="tm4-input" name="type"><option value="">Tất cả hạng mục</option>@foreach($types as $v=>$l)<option value="{{ $v }}" @selected(($filters['type']??'')===$v)>{{ $l }}</option>@endforeach</x-ui.select><x-ui.select class="tm4-input" name="assignee_id"><option value="">Mọi kỹ thuật viên</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)($filters['assignee_id']??'')===(string)$user->id)>{{ $user->name }}</option>@endforeach</x-ui.select><label class="tm4-check"><input type="checkbox" name="overdue" value="1" @checked(!empty($filters['overdue']))><span>Quá hạn</span></label><x-ui.button variant="primary" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" type="submit"><i class="bi bi-funnel"></i> Lọc</x-ui.button><x-ui.button variant="light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance']) }}"><i class="bi bi-arrow-counterclockwise"></i></x-ui.button></form></section>

        <section class="tm4-card">
            <div class="tm4-card-head"><div><span>ĐIỀU PHỐI KỸ THUẬT</span><h2>Danh sách lịch O&amp;M</h2><p>{{ number_format($schedules->total()) }} kết quả</p></div>@if($permissions['create'])<x-ui.button variant="primary" size="none" class="tw:px-3 tw:py-[6px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-[750]" data-bs-toggle="offcanvas" data-bs-target="#tm4CreateDrawer"><i class="bi bi-plus-lg"></i> Tạo kế hoạch</x-ui.button>@endif</div>
            <div class="tm4-table-wrap"><table class="tm4-table"><thead><tr><th>Mã &amp; công trình</th><th>Hạng mục / chu kỳ</th><th>Ngày dự kiến</th><th>Phụ trách</th><th>Trạng thái</th><th class="tw:text-right">Thao tác</th></tr></thead><tbody>
                @forelse($scheduleRows as $row)
                    <tr class="{{ $row->overdue?'is-overdue':'' }}"><td><div class="tm4-code-line"><a class="tm4-code" href="{{ route('projects-unified.maintenance.show',$row->schedule) }}">{{ $row->schedule->schedule_code ?: '#'.$row->schedule->id }}</a><span class="tm4-badge {{ $priorityTone[$row->schedule->priority]??'muted' }}">{{ $priorities[$row->schedule->priority]??$row->schedule->priority }}</span></div><a class="tm4-main-link" href="{{ $row->schedule->site_id ? route('projects-unified.maintenance.site',$row->schedule->site_id) : route('projects-unified.maintenance.show',$row->schedule) }}">{{ $row->schedule->site?->name ?: $row->schedule->site_name ?: 'Công trình chưa đặt tên' }}</a><small><i class="bi bi-geo-alt"></i> {{ \Illuminate\Support\Str::limit($row->schedule->site?->address ?: $row->schedule->address,75) }}</small></td><td><strong>{{ $types[$row->schedule->type]??$row->schedule->type }}</strong><small>Đợt {{ $row->roundNo }}/{{ $row->totalRounds }}</small><div class="tm4-progress"><span style="width:{{ min(100,round($row->roundNo/$row->totalRounds*100)) }}%"></span></div></td><td><strong class="{{ $row->overdue?'text-danger':'' }}">{{ optional($row->schedule->scheduled_date)->format('d/m/Y') }}</strong><small>{{ $row->overdue?'Quá hạn '.optional($row->schedule->scheduled_date)->diffInDays(today()).' ngày':optional($row->schedule->scheduled_date)->translatedFormat('l') }}</small></td><td><div class="tm4-person"><i class="bi bi-people"></i><div><strong>{{ $row->schedule->leader?->user?->name ?: $row->schedule->assignee_names }}</strong><small>{{ $row->schedule->assignees->count() }} người</small></div></div></td><td><span class="tm4-badge {{ $statusTone[$row->schedule->status]??'muted' }}">{{ $statuses[$row->schedule->status]??$row->schedule->status }}</span></td><td><div class="tm4-actions"><a href="{{ route('projects-unified.maintenance.show',$row->schedule) }}" title="Chi tiết"><i class="bi bi-eye"></i></a>@can('update',$row->schedule)@if(!in_array($row->schedule->status,['pending_approval','approved','completed'],true))<button type="button" title="Sửa nhanh" data-tm3-edit-url="{{ route('projects-unified.maintenance.json',$row->schedule) }}" data-tm3-update-url="{{ route('projects-unified.maintenance.update',$row->schedule) }}"><i class="bi bi-pencil-square"></i></button>@endif @endcan @can('changeStatus',$row->schedule)@if(!in_array($row->schedule->status,['pending_approval','approved','completed'],true))<button type="button" title="Đổi trạng thái" data-tm3-status-url="{{ route('projects-unified.maintenance.status',$row->schedule) }}" data-tm3-current-status="{{ $row->schedule->status }}" data-tm3-code="{{ $row->schedule->schedule_code }}"><i class="bi bi-arrow-repeat"></i></button>@endif @endcan</div></td></tr>
                @empty<tr><td colspan="6"><div class="tm4-empty"><i class="bi bi-calendar2-x"></i><h3>Chưa có lịch phù hợp</h3><p>Thử đổi bộ lọc hoặc tạo kế hoạch mới.</p></div></td></tr>@endforelse
            </tbody></table></div>@if(method_exists($schedules,'hasPages') && $schedules->hasPages())<div class="tm4-pagination">{{ $schedules->links() }}</div>@endif
        </section>
    @endif

    @if($activeView === 'claims')
        <section class="tm4-kpi-grid maintenance-kpi">
            @foreach($claimKpis as $claimKpi)
                <div class="tm4-kpi"><span class="{{ $claimKpi['tone'] }}"><i class="bi bi-{{ $claimKpi['icon'] }}"></i></span><div><small>{{ $claimKpi['label'] }}</small><strong>{{ number_format($claimKpi['value']) }}</strong></div></div>
            @endforeach
        </section>
        <section class="tm4-card tm4-filter-card"><form class="tm4-filter claim-filter" method="GET"><input type="hidden" name="view" value="claims"><label class="search"><i class="bi bi-search"></i><input name="claim_q" value="{{ request('claim_q') }}" placeholder="Mã phiếu, công trình, serial, hiện tượng..."></label><x-ui.select class="tm4-input" name="claim_status"><option value="">Tất cả trạng thái</option>@foreach($claimStatuses as $v=>$l)<option value="{{ $v }}" @selected(request('claim_status')===$v)>{{ $l }}</option>@endforeach</x-ui.select><x-ui.select class="tm4-input" name="claim_type"><option value="">Tất cả loại phiếu</option>@foreach($claimTypes as $v=>$l)<option value="{{ $v }}" @selected(request('claim_type')===$v)>{{ $l }}</option>@endforeach</x-ui.select><x-ui.button variant="primary" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" type="submit"><i class="bi bi-funnel"></i> Lọc</x-ui.button><x-ui.button variant="light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" href="{{ route('projects-unified.maintenance.index',['view'=>'claims']) }}"><i class="bi bi-arrow-counterclockwise"></i></x-ui.button></form></section>
        <section class="tm4-card"><div class="tm4-card-head"><div><span>TIẾP NHẬN &amp; XỬ LÝ</span><h2>Phiếu sự cố &amp; bảo hành</h2><p>{{ method_exists($claims,'total') ? number_format($claims->total()) : 0 }} phiếu</p></div>@if($permissions['claim_create'])<x-ui.button variant="danger" size="none" class="tw:px-3 tw:py-[6px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-[750]" data-bs-toggle="offcanvas" data-bs-target="#tm4ClaimDrawer"><i class="bi bi-plus-lg"></i> Tạo phiếu</x-ui.button>@endif</div>
            <div class="tm4-table-wrap"><table class="tm4-table"><thead><tr><th>Mã phiếu / công trình</th><th>Thiết bị &amp; hiện tượng</th><th>Ngày tiếp nhận</th><th>Phụ trách</th><th>Trạng thái</th><th class="tw:text-right">Xử lý</th></tr></thead><tbody>
            @forelse($claims as $claim)
                <tr><td><div class="tm4-code-line"><strong class="tm4-code">{{ $claim->claim_code ?: '#'.$claim->id }}</strong><span class="tm4-badge {{ $priorityTone[$claim->priority]??'muted' }}">{{ $claimPriorities[$claim->priority]??$claim->priority }}</span></div><a class="tm4-main-link" href="{{ $claim->site_id ? route('projects-unified.maintenance.site',$claim->site_id) : '#' }}">{{ $claim->site?->name ?: 'Chưa liên kết công trình' }}</a><small>{{ $claimTypes[$claim->claim_type]??$claim->claim_type }}</small></td><td><strong>{{ $claim->serial_code ?: 'Sự cố không gắn serial' }}</strong><small>{{ \Illuminate\Support\Str::limit($claim->issue_description,100) }}</small>@if($claim->replacement_serial_code)<small class="tw:text-[#198754]!"><i class="bi bi-arrow-repeat"></i> Serial thay: {{ $claim->replacement_serial_code }}</small>@endif</td><td><strong>{{ optional($claim->received_at)->format('d/m/Y') }}</strong><small>{{ optional($claim->created_at)->format('H:i') }}</small></td><td><div class="tm4-person"><i class="bi bi-person-gear"></i><div><strong>{{ $claim->assignee?->name ?: $claim->assigned_name ?: 'Chưa phân công' }}</strong><small>{{ $claim->stockMovements->count() }} phiếu kho</small></div></div></td><td><span class="tm4-badge {{ $claimTone[$claim->status]??'muted' }}">{{ $claimStatuses[$claim->status]??$claim->status }}</span></td><td><div class="tm4-actions">@if($permissions['manager'] || (int)$claim->assigned_to===(int)auth()->id())<button type="button" data-tm4-claim-status-url="{{ route('projects-unified.maintenance.claims.status',$claim) }}" data-tm4-claim-code="{{ $claim->claim_code }}" data-tm4-claim-status="{{ $claim->status }}" data-tm4-claim-assigned="{{ $claim->assigned_to }}" data-tm4-claim-diagnosis="{{ $claim->diagnosis }}" data-tm4-claim-solution="{{ $claim->proposed_solution }}" data-tm4-claim-resolution="{{ $claim->resolution }}" title="Cập nhật phiếu"><i class="bi bi-pencil-square"></i></button>@endif @if($permissions['stock_manage'] && in_array($claim->status,['approved','waiting_stock','replacing','waiting_customer']))<button type="button" data-tm4-stock-claim="{{ $claim->id }}" data-bs-toggle="offcanvas" data-bs-target="#tm4StockDrawer" title="Tạo phiếu kho"><i class="bi bi-box-seam"></i></button>@endif</div></td></tr>
            @empty<tr><td colspan="6"><div class="tm4-empty"><i class="bi bi-shield-check"></i><h3>Chưa có phiếu sự cố</h3><p>Mọi phiếu mới sẽ được liên kết theo công trình và serial.</p></div></td></tr>@endforelse
            </tbody></table></div>@if(method_exists($claims,'hasPages') && $claims->hasPages())<div class="tm4-pagination">{{ $claims->links() }}</div>@endif
        </section>
    @endif

    @if($activeView === 'stock')
        <section class="tm4-card tm4-filter-card"><form class="tm4-filter stock-filter" method="GET"><input type="hidden" name="view" value="stock"><x-ui.select class="tm4-input" name="stock_status"><option value="">Tất cả trạng thái</option>@foreach($stockStatuses as $v=>$l)<option value="{{ $v }}" @selected(request('stock_status')===$v)>{{ $l }}</option>@endforeach</x-ui.select><x-ui.select class="tm4-input" name="stock_type"><option value="">Tất cả nghiệp vụ</option>@foreach($stockTypes as $v=>$l)<option value="{{ $v }}" @selected(request('stock_type')===$v)>{{ $l }}</option>@endforeach</x-ui.select><x-ui.button variant="primary" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" type="submit"><i class="bi bi-funnel"></i> Lọc</x-ui.button><x-ui.button variant="light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" href="{{ route('projects-unified.maintenance.index',['view'=>'stock']) }}"><i class="bi bi-arrow-counterclockwise"></i></x-ui.button></form></section>
        <section class="tm4-card"><div class="tm4-card-head"><div><span>KHO &amp; SERIAL</span><h2>Xuất đổi và thu hồi thiết bị</h2><p>{{ method_exists($stockMovements,'total') ? number_format($stockMovements->total()) : 0 }} phiếu kho</p></div>@if($permissions['stock_manage'])<x-ui.button variant="success" size="none" class="tw:px-3 tw:py-[6px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-[750]" data-bs-toggle="offcanvas" data-bs-target="#tm4StockDrawer"><i class="bi bi-box-arrow-up-right"></i> Tạo phiếu kho</x-ui.button>@endif</div>
        <div class="tm4-table-wrap"><table class="tm4-table"><thead><tr><th>Mã phiếu kho</th><th>Nghiệp vụ</th><th>Phiếu bảo hành / công trình</th><th>Serial / kho</th><th>Ngày tạo</th><th>Trạng thái</th><th class="tw:text-right">Xử lý</th></tr></thead><tbody>
        @forelse($stockMovements as $movement)
            <tr><td><strong class="tm4-code">{{ $movement->movement_code ?: '#'.$movement->id }}</strong><small>{{ $movement->requester?->name ?: 'Hệ thống' }}</small></td><td><strong>{{ $stockTypes[$movement->movement_type]??$movement->movement_type }}</strong><small>{{ \Illuminate\Support\Str::limit($movement->note,70) }}</small></td><td><strong>{{ $movement->claim?->claim_code }}</strong><small>{{ $movement->site?->name ?: 'Chưa liên kết công trình' }}</small></td><td><strong>{{ $movement->serial_code ?: '—' }}</strong><small>{{ $movement->warehouse?->name ?: 'Chưa chọn kho' }}</small>@if($movement->related_serial_code)<small>Liên quan: {{ $movement->related_serial_code }}</small>@endif</td><td><strong>{{ optional($movement->requested_at)->format('d/m/Y') }}</strong><small>{{ optional($movement->requested_at)->format('H:i') }}</small></td><td><span class="tm4-badge {{ $stockTone[$movement->status]??'muted' }}">{{ $stockStatuses[$movement->status]??$movement->status }}</span></td><td><div class="tm4-actions">@if($permissions['stock_manage'])<button type="button" data-tm4-stock-status-url="{{ route('projects-unified.maintenance.stock.status',$movement) }}" data-tm4-stock-code="{{ $movement->movement_code }}" data-tm4-stock-status="{{ $movement->status }}" title="Cập nhật"><i class="bi bi-arrow-repeat"></i></button>@endif</div></td></tr>
        @empty<tr><td colspan="7"><div class="tm4-empty"><i class="bi bi-box-seam"></i><h3>Chưa có phiếu kho bảo hành</h3><p>Tạo phiếu để theo dõi xuất đổi, thu hồi và gửi nhà cung cấp.</p></div></td></tr>@endforelse
        </tbody></table></div>@if(method_exists($stockMovements,'hasPages') && $stockMovements->hasPages())<div class="tm4-pagination">{{ $stockMovements->links() }}</div>@endif</section>
    @endif

    @if($activeView === 'files')
        <section class="tm4-card"><div class="tm4-card-head"><div><span>LƯU TRỮ THEO MÃ DỰ ÁN</span><h2>Hồ sơ công trình O&amp;M</h2><p>Hợp đồng, bản vẽ, nghiệm thu, bảo hành và báo cáo được gom theo từng công trình.</p></div></div>
            <div class="tm4-project-grid">
            @forelse($documentSites as $site)
                <a href="{{ route('projects-unified.maintenance.site',$site->id) }}"><span><i class="bi bi-folder2-open"></i></span><div><h3>{{ $site->name }}</h3><p>{{ $site->contact_name ?: 'Chưa có khách hàng' }}</p><small><b>{{ number_format($site->documents_count) }}</b> hồ sơ · <b>{{ number_format($site->maintenance_count) }}</b> lịch O&amp;M</small></div><i class="bi bi-chevron-right"></i></a>
            @empty<div class="tm4-empty"><i class="bi bi-folder-x"></i><h3>Chưa có hồ sơ công trình</h3><p>Hồ sơ tải lên từ trang chi tiết dự án sẽ xuất hiện tại đây.</p></div>@endforelse
            </div>
        </section>
    @endif
</div>

@if($permissions['create'])
<div class="offcanvas offcanvas-end tm4-drawer" tabindex="-1" id="tm4CreateDrawer"><div class="offcanvas-header tm4-drawer-head"><div><span>TẠO KẾ HOẠCH O&amp;M</span><h2>Lịch bảo trì mới</h2></div><x-ui.close-button in="offcanvas" data-bs-dismiss="offcanvas" /></div><form method="POST" action="{{ route('projects-unified.maintenance.store') }}" class="offcanvas-body tm4-drawer-body" id="tm3CreateForm">@csrf
    <section class="tm4-form-section"><div class="tm4-form-title"><b>1</b><div><strong>Chọn công trình</strong><small>Bắt buộc liên kết đúng mã dự án.</small></div></div><x-ui.label>Công trình <span class="tw:text-[#dc3545]!">*</span></x-ui.label><select class="form-select" name="site_id" id="tm3SiteSelect" data-search-url="{{ route('projects-unified.maintenance.sites-search') }}" required><option value="">Tìm theo công trình, khách hàng hoặc số điện thoại...</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected((string)old('site_id',request('site_id'))===(string)$site->id) data-name="{{ $site->name }}" data-customer="{{ $site->contact_name }}" data-address="{{ $site->address }}" data-kwp="{{ $site->system_kwp }}">{{ $site->name }}{{ $site->contact_name?' — '.$site->contact_name:'' }}</option>@endforeach</select><div class="tm4-readonly-grid"><label>Khách hàng<x-ui.input class="tm4-input" name="customer_name" id="tm3CustomerName" value="{{ old('customer_name') }}" readonly /></label><label>Tên công trình<x-ui.input class="tm4-input" name="site_name" id="tm3SiteName" value="{{ old('site_name') }}" readonly /></label><label class="full">Địa chỉ<x-ui.input class="tm4-input" name="address" id="tm3Address" value="{{ old('address') }}" readonly /></label></div></section>
    <section class="tm4-form-section"><div class="tm4-form-title"><b>2</b><div><strong>Thiết lập chu kỳ</strong><small>Ngày bắt đầu, số đợt và khoảng cách.</small></div></div><div class="tw:row tw:g-3"><div class="tw:md:col12-6"><x-ui.label>Hạng mục</x-ui.label><x-ui.select class="tm4-input" name="type" required>@foreach($types as $v=>$l)<option value="{{ $v }}" @selected(old('type','periodic')===$v)>{{ $l }}</option>@endforeach</x-ui.select></div><div class="tw:md:col12-6"><x-ui.label>Ưu tiên</x-ui.label><x-ui.select class="tm4-input" name="priority" required>@foreach($priorities as $v=>$l)<option value="{{ $v }}" @selected(old('priority','normal')===$v)>{{ $l }}</option>@endforeach</x-ui.select></div><div class="tw:md:col12-6"><x-ui.label>Ngày bắt đầu</x-ui.label><x-ui.input class="tm4-input" type="date" name="scheduled_date" id="tm3BaseDate" value="{{ old('scheduled_date',now()->toDateString()) }}" required /></div><div class="tw:md:col12-3"><x-ui.label>Số đợt</x-ui.label><x-ui.input class="tm4-input" type="number" name="rounds_count" id="tm3RoundsCount" value="{{ old('rounds_count',1) }}" min="1" max="24" /></div><div class="tw:md:col12-3"><x-ui.label>Cách nhau</x-ui.label><x-ui.select class="tm4-input" name="round_interval_months" id="tm3RoundInterval"><option value="1">1 tháng</option><option value="3" selected>3 tháng</option><option value="6">6 tháng</option><option value="12">12 tháng</option></x-ui.select></div></div><div id="tm3RoundsPreview" class="tm4-round-preview"></div></section>
    <section class="tm4-form-section"><div class="tm4-form-title"><b>3</b><div><strong>Phân công &amp; kỹ thuật</strong><small>Người đầu tiên là trưởng nhóm.</small></div></div><x-ui.label>Kỹ thuật viên</x-ui.label><select class="form-select" name="assigned_user_ids[]" id="tm3CreateAssignees" multiple>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><div class="tw:row tw:g-3 tw:mt-1"><div class="tw:md:col12-5"><x-ui.label>Công suất kWp</x-ui.label><x-ui.input class="tm4-input" type="number" step="0.01" name="system_kwp" id="tm3SystemKwp" /></div><div class="tw:md:col12-7"><x-ui.label>Inverter / thiết bị</x-ui.label><x-ui.input class="tm4-input" name="inverter_info" /></div><div class="tw:col12-12"><x-ui.label>Hiện trạng / yêu cầu</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="issue_note" rows="3"></x-ui.input></div><div class="tw:col12-12"><x-ui.label>Ghi chú nội bộ</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="technical_note" rows="2"></x-ui.input></div></div></section>
    <div class="tm4-drawer-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="offcanvas">Đóng</x-ui.button><x-ui.button variant="primary" type="submit"><i class="bi bi-check2-circle"></i> Tạo kế hoạch</x-ui.button></div></form></div>
@endif

@if($permissions['claim_create'])
<div class="offcanvas offcanvas-end tm4-drawer" tabindex="-1" id="tm4ClaimDrawer"><div class="offcanvas-header tm4-drawer-head red"><div><span>TIẾP NHẬN SỰ CỐ</span><h2>Tạo phiếu bảo hành</h2></div><x-ui.close-button in="offcanvas" data-bs-dismiss="offcanvas" /></div><form method="POST" action="{{ route('projects-unified.maintenance.claims.store') }}" class="offcanvas-body tm4-drawer-body">@csrf
    <section class="tm4-form-section"><div class="tm4-form-title red"><b>1</b><div><strong>Công trình &amp; thiết bị</strong><small>Phiếu luôn gắn vào một dự án cụ thể.</small></div></div><x-ui.label>Công trình <span class="tw:text-[#dc3545]!">*</span></x-ui.label><x-ui.select class="tm4-input" name="site_id" required><option value="">Chọn công trình...</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected((string)old('site_id')===(string)$site->id)>{{ $site->name }}{{ $site->contact_name?' — '.$site->contact_name:'' }}</option>@endforeach</x-ui.select><x-ui.label class="tw:mt-4">Serial thiết bị lỗi</x-ui.label><x-ui.input class="tm4-input" name="serial_code" value="{{ old('serial_code') }}" placeholder="Để trống nếu sự cố toàn hệ thống" /><small class="tm4-help">Serial nhập vào sẽ được đối chiếu với kho và hồ sơ bảo hành.</small></section>
    <section class="tm4-form-section"><div class="tm4-form-title red"><b>2</b><div><strong>Thông tin tiếp nhận</strong><small>Phân loại, ưu tiên và người xử lý.</small></div></div><div class="tw:row tw:g-3"><div class="tw:md:col12-6"><x-ui.label>Loại phiếu</x-ui.label><x-ui.select class="tm4-input" name="claim_type" required>@foreach($claimTypes as $v=>$l)<option value="{{ $v }}" @selected(old('claim_type','warranty')===$v)>{{ $l }}</option>@endforeach</x-ui.select></div><div class="tw:md:col12-6"><x-ui.label>Ưu tiên</x-ui.label><x-ui.select class="tm4-input" name="priority" required>@foreach($claimPriorities as $v=>$l)<option value="{{ $v }}" @selected(old('priority','normal')===$v)>{{ $l }}</option>@endforeach</x-ui.select></div><div class="tw:col12-12"><x-ui.label>Kỹ thuật viên phụ trách</x-ui.label>@if($permissions['technician_only'])<input type="hidden" name="assigned_to" value="{{ auth()->id() }}"><div class="tm4-assignee-fixed"><i class="bi bi-person-check"></i><strong>{{ auth()->user()->name }}</strong><small>Tự tiếp nhận</small></div>@else<x-ui.select class="tm4-input" name="assigned_to"><option value="">Chưa phân công</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)old('assigned_to')===(string)$user->id)>{{ $user->name }}</option>@endforeach</x-ui.select>@endif</div><div class="tw:col12-12"><x-ui.label>Mô tả hiện tượng <span class="tw:text-[#dc3545]!">*</span></x-ui.label><x-ui.input as="textarea" class="tm4-input" name="issue_description" rows="5" required placeholder="Khách hàng phản ánh gì, thiết bị báo lỗi gì, thời điểm phát sinh...">{{ old('issue_description') }}</x-ui.input></div><div class="tw:col12-12"><x-ui.label>Ghi chú nội bộ</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="internal_note" rows="2">{{ old('internal_note') }}</x-ui.input></div><div class="tw:md:col12-6"><label class="tm4-switch"><input type="checkbox" name="is_chargeable" value="1" @checked(old('is_chargeable'))><span></span> Sửa chữa tính phí</label></div><div class="tw:md:col12-6"><x-ui.label>Chi phí dự kiến</x-ui.label><x-ui.input class="tm4-input" type="number" name="estimated_cost" min="0" value="{{ old('estimated_cost',0) }}" /></div></div></section>
    <div class="tm4-drawer-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="offcanvas">Đóng</x-ui.button><x-ui.button variant="danger" type="submit"><i class="bi bi-shield-plus"></i> Tạo phiếu</x-ui.button></div></form></div>
@endif

@if($permissions['stock_manage'])
<div class="offcanvas offcanvas-end tm4-drawer" tabindex="-1" id="tm4StockDrawer"><div class="offcanvas-header tm4-drawer-head green"><div><span>KHO BẢO HÀNH</span><h2>Tạo phiếu xuất / thu hồi</h2></div><x-ui.close-button in="offcanvas" data-bs-dismiss="offcanvas" /></div><form method="POST" action="{{ route('projects-unified.maintenance.stock.store') }}" class="offcanvas-body tm4-drawer-body">@csrf
    <section class="tm4-form-section"><div class="tm4-form-title green"><b>1</b><div><strong>Chọn phiếu bảo hành</strong><small>Kho chỉ xử lý theo yêu cầu đã ghi nhận.</small></div></div><x-ui.label>Phiếu sự cố / bảo hành</x-ui.label><x-ui.select class="tm4-input" name="warranty_claim_id" id="tm4StockClaim" required><option value="">Chọn phiếu...</option>@foreach($openClaims as $claim)<option value="{{ $claim->id }}" @selected((string)old('warranty_claim_id')===(string)$claim->id)>{{ $claim->claim_code }} — {{ $claim->site?->name }}{{ $claim->serial_code?' — '.$claim->serial_code:'' }}</option>@endforeach</x-ui.select></section>
    <section class="tm4-form-section"><div class="tm4-form-title green"><b>2</b><div><strong>Nghiệp vụ &amp; serial</strong><small>Ghi nhận chính xác thiết bị xuất/thu hồi.</small></div></div><div class="tw:row tw:g-3"><div class="tw:md:col12-6"><x-ui.label>Nghiệp vụ</x-ui.label><x-ui.select class="tm4-input" name="movement_type" required>@foreach($stockTypes as $v=>$l)<option value="{{ $v }}" @selected(old('movement_type','warranty_out')===$v)>{{ $l }}</option>@endforeach</x-ui.select></div><div class="tw:md:col12-6"><x-ui.label>Kho</x-ui.label><x-ui.select class="tm4-input" name="warehouse_id" required><option value="">Chọn kho...</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)old('warehouse_id')===(string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</x-ui.select></div><div class="tw:md:col12-6"><x-ui.label>Serial xử lý <span class="tw:text-[#dc3545]!">*</span></x-ui.label><x-ui.input class="tm4-input" name="serial_code" value="{{ old('serial_code') }}" required placeholder="Serial xuất hoặc thu hồi" /></div><div class="tw:md:col12-6"><x-ui.label>Serial liên quan</x-ui.label><x-ui.input class="tm4-input" name="related_serial_code" value="{{ old('related_serial_code') }}" placeholder="Ví dụ serial cũ/mới đối ứng" /></div><div class="tw:md:col12-4"><x-ui.label>Số lượng</x-ui.label><x-ui.input class="tm4-input" type="number" name="quantity" step="1" min="1" max="1" value="1" readonly /></div><div class="tw:md:col12-8"><x-ui.label>Ghi chú</x-ui.label><x-ui.input class="tm4-input" name="note" value="{{ old('note') }}" placeholder="Tình trạng hàng, phụ kiện đi kèm..." /></div></div></section>
    <div class="tm4-drawer-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="offcanvas">Đóng</x-ui.button><x-ui.button variant="success" type="submit"><i class="bi bi-box-arrow-up-right"></i> Tạo phiếu kho</x-ui.button></div></form></div>
@endif

<div class="offcanvas offcanvas-end tm4-drawer" tabindex="-1" id="tm3EditDrawer"><div class="offcanvas-header tm4-drawer-head"><div><span>SỬA THÔNG TIN</span><h2 id="tm3EditTitle">Đang tải...</h2></div><x-ui.close-button in="offcanvas" data-bs-dismiss="offcanvas" /></div><form method="POST" action="#" class="offcanvas-body tm4-drawer-body" id="tm3EditForm">@csrf @method('PUT')<div class="tm4-loading" id="tm3EditLoading">Đang tải dữ liệu...</div><div id="tm3EditContent" hidden><div class="tm4-reference"><div><small>Công trình</small><strong id="tm3EditSite">—</strong></div><div><small>Chu kỳ</small><strong id="tm3EditRound">—</strong></div><div><small>Khách hàng</small><strong id="tm3EditCustomer">—</strong></div></div><section class="tm4-form-section"><div class="tw:row tw:g-3"><div class="tw:md:col12-6"><x-ui.label>Ngày dự kiến</x-ui.label><x-ui.input class="tm4-input" type="date" name="scheduled_date" data-edit-field="scheduled_date" /></div><div class="tw:md:col12-6"><x-ui.label>Ưu tiên</x-ui.label><x-ui.select class="tm4-input" name="priority" data-edit-field="priority">@foreach($priorities as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</x-ui.select></div><div class="tw:md:col12-6"><x-ui.label>Hạng mục</x-ui.label><x-ui.select class="tm4-input" name="type" data-edit-field="type">@foreach($types as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</x-ui.select></div><div class="tw:col12-12"><x-ui.label>Người phụ trách</x-ui.label><select class="form-select" name="assigned_user_ids[]" data-edit-field="assigned_user_ids" multiple>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div><div class="tw:col12-12"><x-ui.label>Ghi chú kỹ thuật</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="technical_note" rows="4" data-edit-field="technical_note"></x-ui.input></div></div><div class="tm4-notice"><i class="bi bi-shield-lock"></i><span>Trạng thái phê duyệt không thể đổi tại form sửa nhanh. Hãy dùng đúng nút trong quy trình.</span></div></section></div><div class="tm4-drawer-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="offcanvas">Đóng</x-ui.button><x-ui.button variant="outline-primary" href="#" id="tm3EditFullLink">Mở chi tiết</x-ui.button><x-ui.button variant="primary" type="submit"><i class="bi bi-save"></i> Lưu</x-ui.button></div></form></div>

<div class="modal fade" id="tm3StatusModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content tm4-modal" method="POST" action="#" id="tm3StatusForm">@csrf<div class="modal-header"><div><small>CẬP NHẬT QUY TRÌNH</small><h2>Đổi trạng thái lịch</h2><span id="tm3StatusCode"></span></div><x-ui.close-button in="modal" data-bs-dismiss="modal" /></div><div class="modal-body"><x-ui.label>Trạng thái mới</x-ui.label><x-ui.select class="tm4-input" name="status" id="tm3StatusSelect" required>@foreach($manualStatuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</x-ui.select><x-ui.label class="tw:mt-4">Lý do</x-ui.label><x-ui.input class="tm4-input" name="reason" placeholder="Bắt buộc khi hoãn, hủy hoặc mở lại" /><x-ui.label class="tw:mt-4">Kết quả / ghi chú</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="result_note" rows="4"></x-ui.input></div><div class="modal-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="modal">Đóng</x-ui.button><x-ui.button variant="primary" type="submit"><i class="bi bi-arrow-repeat"></i> Cập nhật</x-ui.button></div></form></div></div>

<div class="modal fade" id="tm4ClaimStatusModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><form class="modal-content tm4-modal" method="POST" action="#" id="tm4ClaimStatusForm">@csrf<div class="modal-header"><div><small>XỬ LÝ PHIẾU BẢO HÀNH</small><h2 id="tm4ClaimStatusCode">Cập nhật phiếu</h2></div><x-ui.close-button in="modal" data-bs-dismiss="modal" /></div><div class="modal-body"><div class="tw:row tw:g-3"><div class="tw:md:col12-6"><x-ui.label>Trạng thái mới</x-ui.label><x-ui.select class="tm4-input" name="status" id="tm4ClaimStatusSelect">@foreach($claimStatuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</x-ui.select></div><div class="tw:md:col12-6"><x-ui.label>Chi phí thực tế</x-ui.label><x-ui.input class="tm4-input" type="number" name="actual_cost" min="0" /></div>@if($permissions['manager'])<div class="tw:md:col12-6"><x-ui.label>Người phụ trách</x-ui.label><x-ui.select class="tm4-input" name="assigned_to" id="tm4ClaimAssignee"><option value="">Chưa phân công</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</x-ui.select></div>@endif<div class="tw:col12-12"><x-ui.label>Chẩn đoán</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="diagnosis" id="tm4ClaimDiagnosis" rows="3"></x-ui.input></div><div class="tw:col12-12"><x-ui.label>Phương án đề xuất</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="proposed_solution" id="tm4ClaimSolution" rows="3"></x-ui.input></div><div class="tw:col12-12"><x-ui.label>Kết quả xử lý</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="resolution" id="tm4ClaimResolution" rows="3"></x-ui.input></div><div class="tw:col12-12"><x-ui.label>Ghi chú phê duyệt</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="approval_note" rows="2"></x-ui.input></div></div></div><div class="modal-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="modal">Đóng</x-ui.button><x-ui.button variant="danger" type="submit"><i class="bi bi-save"></i> Cập nhật phiếu</x-ui.button></div></form></div></div>

<div class="modal fade" id="tm4StockStatusModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content tm4-modal" method="POST" action="#" id="tm4StockStatusForm">@csrf<div class="modal-header"><div><small>XỬ LÝ PHIẾU KHO</small><h2 id="tm4StockStatusCode">Cập nhật phiếu</h2></div><x-ui.close-button in="modal" data-bs-dismiss="modal" /></div><div class="modal-body"><x-ui.label>Trạng thái</x-ui.label><x-ui.select class="tm4-input" name="status" id="tm4StockStatusSelect">@foreach($stockStatuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</x-ui.select><x-ui.label class="tw:mt-4">Ghi chú</x-ui.label><x-ui.input as="textarea" class="tm4-input" name="note" rows="4"></x-ui.input></div><div class="modal-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="modal">Đóng</x-ui.button><x-ui.button variant="success" type="submit"><i class="bi bi-check2-circle"></i> Cập nhật</x-ui.button></div></form></div></div>
@endsection

@section('scripts')
<script>window.TM3 = @json($tmConfigForJs);</script>
<script src="{{ asset('js/technical-maintenance-v4.js') }}?v={{ file_exists(public_path('js/technical-maintenance-v4.js')) ? filemtime(public_path('js/technical-maintenance-v4.js')) : time() }}"></script>
@endsection
