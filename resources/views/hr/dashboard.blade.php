@extends('layouts.app')

@section('content')
<div class="container-fluid tw:py-6 hr-dashboard-page">

    {{-- Header --}}
    <div class="hr-hero-card tw:mb-6">
        <div class="tw:flex tw:items-start tw:justify-between flex-wrap tw:gap-4">
            <div>
                <div class="hr-page-kicker tw:mb-2">
                    <span class="hr-page-kicker-dot"></span>
                    HR Dashboard
                </div>
                <h1 class="hr-page-title tw:mb-2">Nhân sự</h1>
                <div class="hr-page-subtitle">
                    Tổng quan nhân sự • Nghỉ phép • Chấm công • Theo dõi hiệu suất đội ngũ
                </div>
            </div>

            <div class="tw:flex tw:gap-2 flex-wrap">
                <x-ui.button variant="none" size="none" class="hr-action-btn hr-action-btn-light" href="{{ route('hr.departments.index') }}">
                    <i class="bi bi-diagram-3 me-1"></i> Phòng ban
                </x-ui.button>
                <x-ui.button variant="none" size="none" class="hr-action-btn hr-action-btn-light" href="{{ route('hr.positions.index') }}">
                    <i class="bi bi-award me-1"></i> Chức vụ
                </x-ui.button>
                <x-ui.button variant="none" size="none" class="hr-action-btn hr-action-btn-primary" href="{{ route('hr.employees.index') }}">
                    <i class="bi bi-people me-1"></i> Nhân viên
                </x-ui.button>
            </div>
        </div>

        <div class="tw:row tw:g-3 tw:mt-2">
            <div class="tw:col12-12 tw:md:col12-4">
                <div class="hr-hero-mini-card">
                    <div class="hr-hero-mini-label">Khoảng lọc</div>
                    <div class="hr-hero-mini-value">
                        {{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }}
                        -
                        {{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}
                    </div>
                </div>
            </div>

            <div class="tw:col12-12 tw:md:col12-4">
                <div class="hr-hero-mini-card">
                    <div class="hr-hero-mini-label">Tỷ lệ check-in hôm nay</div>
                    <div class="hr-hero-mini-value">{{ $checkInRate ?? 0 }}%</div>
                </div>
            </div>

            <div class="tw:col12-12 tw:md:col12-4">
                <div class="hr-hero-mini-card">
                    <div class="hr-hero-mini-label">Trạng thái hệ thống</div>
                    <div class="hr-hero-mini-value tw:text-[#198754]!">Đang hoạt động ổn định</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <x-ui.card class="hr-glass-card tw:mb-6">
        {{-- `p-lg-4` (Bootstrap, `padding:1.5rem!important` từ ≥992px) ĐÃ BỎ: ở HEAD
             `tw:p-4!` luôn thắng nên nó chưa bao giờ có tác dụng. --}}
        <x-ui.card-body class="tw:p-4">
            <form method="GET" action="{{ route('hr.dashboard') }}">
                <div class="tw:row tw:g-3 tw:items-end">
                    <div class="tw:col12-12 tw:md:col12-3">
                        <x-ui.label class="hr-form-label">Từ ngày</x-ui.label>
                        <x-ui.input
                            type="date"
                            name="from_date"
                            class="hr-input"
                            value="{{ request('from_date', now()->startOfMonth()->toDateString()) }}" />
                    </div>

                    <div class="tw:col12-12 tw:md:col12-3">
                        <x-ui.label class="hr-form-label">Đến ngày</x-ui.label>
                        <x-ui.input
                            type="date"
                            name="to_date"
                            class="hr-input"
                            value="{{ request('to_date', now()->toDateString()) }}" />
                    </div>

                    <div class="tw:col12-12 tw:md:col12-4">
                        <x-ui.label class="hr-form-label">Theo kỳ</x-ui.label>
                        <x-ui.select class="hr-input" name="period">
                            <option value="">-- Tuỳ chọn --</option>
                            <option value="this_month" {{ request('period') == 'this_month' ? 'selected' : '' }}>Tháng này</option>
                            <option value="last_month" {{ request('period') == 'last_month' ? 'selected' : '' }}>Tháng trước</option>
                            <option value="this_year" {{ request('period') == 'this_year' ? 'selected' : '' }}>Năm nay</option>
                        </x-ui.select>
                    </div>

                    <div class="tw:col12-12 tw:md:col12-2 d-grid">
                        <x-ui.button variant="none" size="none" class="hr-action-btn hr-action-btn-cyan" type="submit">
                            <i class="bi bi-funnel me-1"></i> Lọc dữ liệu
                        </x-ui.button>
                    </div>
                </div>
            </form>
        </x-ui.card-body>
    </x-ui.card>

    {{-- KPI Cards --}}
    <div class="tw:row tw:g-3 tw:mb-6">
        <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-3">
            <x-ui.card class="hr-kpi-card hr-kpi-blue tw:h-full">
                <x-ui.card-body>
                    <div class="hr-kpi-top">
                        <div>
                            <div class="hr-kpi-label">Tổng nhân sự</div>
                            <div class="hr-kpi-value">{{ $totalEmployees ?? 0 }}</div>
                            <div class="hr-kpi-sub">Đang hoạt động</div>
                        </div>
                        <div class="hr-kpi-icon">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                    <div class="hr-kpi-progress">
                        <span style="width: 78%;"></span>
                    </div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-3">
            <x-ui.card class="hr-kpi-card hr-kpi-orange tw:h-full">
                <x-ui.card-body>
                    <div class="hr-kpi-top">
                        <div>
                            <div class="hr-kpi-label">Nghỉ phép chờ duyệt</div>
                            <div class="hr-kpi-value">{{ $pendingLeaves ?? 0 }}</div>
                            <div class="hr-kpi-sub">Trong kỳ lọc</div>
                        </div>
                        <div class="hr-kpi-icon">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                    <div class="hr-kpi-progress">
                        <span style="width: {{ min((($pendingLeaves ?? 0) * 12), 100) }}%;"></span>
                    </div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-3">
            <x-ui.card class="hr-kpi-card hr-kpi-green tw:h-full">
                <x-ui.card-body>
                    <div class="hr-kpi-top">
                        <div>
                            <div class="hr-kpi-label">Chấm công hôm nay</div>
                            <div class="hr-kpi-value">{{ $todayAttendance ?? 0 }}</div>
                            <div class="hr-kpi-sub">Đã check-in</div>
                        </div>
                        <div class="hr-kpi-icon">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                    </div>
                    <div class="hr-kpi-progress">
                        <span style="width: {{ min(($checkInRate ?? 0), 100) }}%;"></span>
                    </div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-3">
            <x-ui.card class="hr-kpi-card hr-kpi-purple tw:h-full">
                <x-ui.card-body>
                    <div class="hr-kpi-top">
                        <div>
                            <div class="hr-kpi-label">Đi muộn</div>
                            <div class="hr-kpi-value">{{ $lateCount ?? 0 }}</div>
                            <div class="hr-kpi-sub">Trong kỳ lọc</div>
                        </div>
                        <div class="hr-kpi-icon">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="hr-kpi-progress">
                        <span style="width: {{ min((($lateCount ?? 0) * 10), 100) }}%;"></span>
                    </div>
                </x-ui.card-body>
            </x-ui.card>
        </div>
    </div>

    {{-- Main panels --}}
    <div class="tw:row tw:g-4 tw:mb-6">
        <div class="tw:col12-12 tw:min-[75rem]:col12-8">
            <x-ui.card class="hr-glass-card tw:h-full">
                <x-ui.card-header class="hr-card-header border-0 bg-transparent">
                    <div class="tw:flex tw:items-center tw:justify-between flex-wrap tw:gap-2">
                        <div class="tw:font-bold">
                            <i class="bi bi-bar-chart-line tw:mr-2"></i>Tổng quan nhanh
                        </div>
                        <span class="hr-chip">HR Overview</span>
                    </div>
                </x-ui.card-header>

                <x-ui.card-body class="pt-0">
                    <div class="tw:row tw:g-3 tw:mb-4">
                        <div class="tw:col12-12 tw:md:col12-4">
                            <div class="hr-stat-tile">
                                <div class="hr-stat-title">Nhân sự hoạt động</div>
                                <div class="hr-stat-value">{{ $totalEmployees ?? 0 }}</div>
                                <div class="hr-stat-note">Tổng headcount hiện tại</div>
                            </div>
                        </div>

                        <div class="tw:col12-12 tw:md:col12-4">
                            <div class="hr-stat-tile">
                                <div class="hr-stat-title">Check-in hôm nay</div>
                                <div class="hr-stat-value">{{ $todayAttendance ?? 0 }}</div>
                                <div class="hr-stat-note">Tỷ lệ: {{ $checkInRate ?? 0 }}%</div>
                            </div>
                        </div>

                        <div class="tw:col12-12 tw:md:col12-4">
                            <div class="hr-stat-tile">
                                <div class="hr-stat-title">Đơn chờ duyệt</div>
                                <div class="hr-stat-value">{{ $pendingLeaves ?? 0 }}</div>
                                <div class="hr-stat-note">Cần xử lý sớm</div>
                            </div>
                        </div>
                    </div>

                    <div class="hr-chart-box">
                        <div class="hr-chart-grid"></div>
                        <div class="hr-chart-content">
                            <div class="tw:row tw:g-3 tw:h-full tw:items-center">
                                <div class="tw:col12-12 tw:min-[62rem]:col12-5">
                                    <div class="hr-chart-side">
                                        <div class="hr-mini-kpi tw:mb-4">
                                            <div class="hr-mini-kpi-label">Tỷ lệ đi làm đúng giờ</div>
                                            <div class="hr-mini-kpi-value">{{ 100 - min(($lateCount ?? 0) * 5, 100) }}%</div>
                                        </div>

                                        <div class="hr-mini-kpi tw:mb-4">
                                            <div class="hr-mini-kpi-label">Hiệu suất check-in</div>
                                            <div class="hr-mini-kpi-value">{{ $checkInRate ?? 0 }}%</div>
                                        </div>

                                        <div class="hr-mini-kpi">
                                            <div class="hr-mini-kpi-label">Khoảng theo dõi</div>
                                            <div class="hr-mini-kpi-value" style="font-size: 1rem;">
                                                {{ \Carbon\Carbon::parse($fromDate)->format('d/m/Y') }}
                                                -
                                                {{ \Carbon\Carbon::parse($toDate)->format('d/m/Y') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tw:col12-12 tw:min-[62rem]:col12-7">
                                    <div class="hr-fake-chart">
                                        <div class="hr-bar-wrap">
                                            <div class="hr-bar-label">Check-in</div>
                                            <div class="hr-bar-track">
                                                <div class="hr-bar-fill hr-bar-fill-cyan" style="width: {{ min(($checkInRate ?? 0), 100) }}%;"></div>
                                            </div>
                                            <div class="hr-bar-value">{{ $checkInRate ?? 0 }}%</div>
                                        </div>

                                        <div class="hr-bar-wrap">
                                            <div class="hr-bar-label">Đúng giờ</div>
                                            <div class="hr-bar-track">
                                                <div class="hr-bar-fill hr-bar-fill-green" style="width: {{ max(15, 100 - min(($lateCount ?? 0) * 5, 100)) }}%;"></div>
                                            </div>
                                            <div class="hr-bar-value">{{ 100 - min(($lateCount ?? 0) * 5, 100) }}%</div>
                                        </div>

                                        <div class="hr-bar-wrap">
                                            <div class="hr-bar-label">Đơn chờ duyệt</div>
                                            <div class="hr-bar-track">
                                                <div class="hr-bar-fill hr-bar-fill-orange" style="width: {{ min((($pendingLeaves ?? 0) * 15), 100) }}%;"></div>
                                            </div>
                                            <div class="hr-bar-value">{{ $pendingLeaves ?? 0 }}</div>
                                        </div>

                                        <div class="hr-bar-wrap">
                                            <div class="hr-bar-label">Đi muộn</div>
                                            <div class="hr-bar-track">
                                                <div class="hr-bar-fill hr-bar-fill-purple" style="width: {{ min((($lateCount ?? 0) * 12), 100) }}%;"></div>
                                            </div>
                                            <div class="hr-bar-value">{{ $lateCount ?? 0 }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(isset($employeeAttendanceStats) && $employeeAttendanceStats->count())
                        <div class="tw:mt-6">
                            <div class="tw:flex tw:items-center tw:justify-between flex-wrap tw:gap-2 tw:mb-4">
                                <div class="tw:font-bold">
                                    <i class="bi bi-table tw:mr-2"></i>Thống kê chấm công nhân viên
                                </div>
                                <span class="hr-chip">Manager View</span>
                            </div>

                            <div class="table-responsive hr-table-wrap">
                                <table class="table align-middle hr-modern-table tw:mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nhân viên</th>
                                            <th>Phòng ban</th>
                                            <th>Check-in</th>
                                            <th>Hoàn tất</th>
                                            <th>Đi muộn</th>
                                            <th>Về sớm</th>
                                            <th>Thiếu out</th>
                                            <th>Tổng giờ</th>
                                            <th>Đúng giờ</th>
                                            <th>Xếp hạng</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($employeeAttendanceStats as $item)
                                            <tr>
                                                <td>
                                                    <div class="tw:font-semibold">{{ $item->employee_name }}</div>
                                                </td>
                                                <td>{{ $item->department_name }}</td>
                                                <td>{{ $item->total_checkin_days }}</td>
                                                <td>{{ $item->completed_days }}</td>
                                                <td>{{ $item->late_days }}</td>
                                                <td>{{ $item->early_leave_days }}</td>
                                                <td>{{ $item->incomplete_days }}</td>
                                                <td>{{ $item->total_hours }} giờ</td>
                                                <td>
                                                    <span class="tw:font-semibold">{{ $item->ontime_rate }}%</span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-{{ $item->performance_badge }}">
                                                        {{ $item->performance_label }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:col12-12 tw:min-[75rem]:col12-4">
            <x-ui.card class="hr-glass-card tw:h-full">
                <x-ui.card-header class="hr-card-header border-0 bg-transparent">
                    <div class="tw:flex tw:items-center tw:justify-between">
                        <div class="tw:font-bold">
                            <i class="bi bi-lightning-charge tw:mr-2"></i>Việc cần xử lý
                        </div>
                        <a class="small tw:no-underline tw:font-semibold" href="{{ route('hr.leave.index') }}">Xem tất cả</a>
                    </div>
                </x-ui.card-header>

                <x-ui.card-body class="pt-0">
                    <div class="hr-task-box tw:mb-4">
                        <div class="tw:flex tw:gap-4">
                            <div class="hr-task-icon">
                                <i class="bi bi-inbox"></i>
                            </div>

                            <div class="flex-grow-1">
                                @if(($pendingLeaves ?? 0) > 0)
                                    <div class="tw:font-bold fs-5 tw:mb-1">Có {{ $pendingLeaves }} đơn đang chờ duyệt</div>
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mb-4">
                                        Bạn nên kiểm tra và duyệt các yêu cầu nghỉ phép/làm online để tránh tồn đọng.
                                    </div>
                                    <div class="tw:flex tw:gap-2 flex-wrap">
                                        <x-ui.button variant="none" size="sm" class="hr-action-btn hr-action-btn-primary" href="{{ route('hr.leave.index') }}">
                                            <i class="bi bi-eye me-1"></i> Xem đơn nghỉ
                                        </x-ui.button>
                                        <x-ui.button variant="none" size="sm" class="hr-action-btn hr-action-btn-light" href="{{ route('hr.attendance.index') }}">
                                            <i class="bi bi-calendar-check me-1"></i> Chấm công
                                        </x-ui.button>
                                    </div>
                                @else
                                    <div class="tw:font-bold fs-5 tw:mb-1">Mọi thứ đang ổn</div>
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mb-4">
                                        Hiện chưa có yêu cầu nào tồn đọng. Bạn có thể tạo mới đơn hoặc kiểm tra bảng chấm công.
                                    </div>
                                    <div class="tw:flex tw:gap-2 flex-wrap">
                                        <x-ui.button variant="none" size="sm" class="hr-action-btn hr-action-btn-primary" href="{{ route('hr.leave.create') }}">
                                            <i class="bi bi-plus-circle me-1"></i> Tạo đơn nghỉ
                                        </x-ui.button>
                                        <x-ui.button variant="none" size="sm" class="hr-action-btn hr-action-btn-light" href="{{ route('hr.attendance.index') }}">
                                            <i class="bi bi-calendar-check me-1"></i> Chấm công
                                        </x-ui.button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="hr-side-list">
                        <div class="hr-side-item">
                            <div class="hr-side-item-left">
                                <div class="hr-side-bullet hr-bullet-cyan"></div>
                                <div>
                                    <div class="tw:font-semibold">Tỷ lệ check-in</div>
                                    <div class="small tw:text-[rgba(33,37,41,0.75)]">Theo dữ liệu hôm nay</div>
                                </div>
                            </div>
                            <div class="tw:font-bold">{{ $checkInRate ?? 0 }}%</div>
                        </div>

                        <div class="hr-side-item">
                            <div class="hr-side-item-left">
                                <div class="hr-side-bullet hr-bullet-green"></div>
                                <div>
                                    <div class="tw:font-semibold">Nhân sự hoạt động</div>
                                    <div class="small tw:text-[rgba(33,37,41,0.75)]">Tổng headcount</div>
                                </div>
                            </div>
                            <div class="tw:font-bold">{{ $totalEmployees ?? 0 }}</div>
                        </div>

                        <div class="hr-side-item">
                            <div class="hr-side-item-left">
                                <div class="hr-side-bullet hr-bullet-orange"></div>
                                <div>
                                    <div class="tw:font-semibold">Đơn chờ duyệt</div>
                                    <div class="small tw:text-[rgba(33,37,41,0.75)]">Yêu cầu xử lý</div>
                                </div>
                            </div>
                            <div class="tw:font-bold">{{ $pendingLeaves ?? 0 }}</div>
                        </div>

                        <div class="hr-side-item">
                            <div class="hr-side-item-left">
                                <div class="hr-side-bullet hr-bullet-purple"></div>
                                <div>
                                    <div class="tw:font-semibold">Đi muộn</div>
                                    <div class="small tw:text-[rgba(33,37,41,0.75)]">Trong kỳ lọc</div>
                                </div>
                            </div>
                            <div class="tw:font-bold">{{ $lateCount ?? 0 }}</div>
                        </div>
                    </div>

                    <div class="hr-tip-box tw:mt-4">
                        <div class="tw:font-semibold tw:mb-1">Gợi ý nâng cấp tiếp</div>
                        <div class="small tw:text-[rgba(33,37,41,0.75)]">
                            Bước sau mình có thể làm tiếp biểu đồ thật, bảng duyệt đẹp hơn, và thống kê KPI theo phòng ban.
                        </div>
                    </div>
                </x-ui.card-body>
            </x-ui.card>
        </div>
    </div>

</div>

<style>
.hr-dashboard-page{
    --hr-primary:#18c3f7;
    --hr-primary-dark:#0ea5e9;
    --hr-ink:#0f172a;
    --hr-text:#334155;
    --hr-muted:#64748b;
    --hr-border:rgba(148,163,184,.22);
    --hr-white:rgba(255,255,255,.92);
}

.hr-hero-card{
    position: relative;
    overflow: hidden;
    padding: 28px;
    border-radius: 28px;
    background:
        radial-gradient(circle at top right, rgba(24,195,247,.18), transparent 30%),
        radial-gradient(circle at left bottom, rgba(99,102,241,.10), transparent 32%),
        linear-gradient(135deg, #ffffff 0%, #f7fbff 52%, #f4f8ff 100%);
    border: 1px solid rgba(226,232,240,.95);
    box-shadow: 0 18px 60px rgba(15,23,42,.08);
}

.hr-page-kicker{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding:8px 14px;
    border-radius:999px;
    background: rgba(24,195,247,.10);
    color:#0369a1;
    font-size:.82rem;
    font-weight:800;
    letter-spacing:.3px;
}
.hr-page-kicker-dot{
    width:10px;
    height:10px;
    border-radius:999px;
    background:linear-gradient(135deg,#06b6d4,#3b82f6);
    box-shadow:0 0 0 6px rgba(6,182,212,.10);
}
.hr-page-title{
    font-size: clamp(2rem, 3vw, 2.8rem);
    line-height:1.05;
    font-weight:900;
    color:var(--hr-ink);
    letter-spacing:-.03em;
}
.hr-page-subtitle{
    color:var(--hr-muted);
    font-size:1rem;
    font-weight:500;
}

.hr-hero-mini-card{
    height:100%;
    padding:18px 20px;
    border-radius:20px;
    background:rgba(255,255,255,.66);
    border:1px solid rgba(226,232,240,.9);
    backdrop-filter: blur(8px);
}
.hr-hero-mini-label{
    color:var(--hr-muted);
    font-size:.82rem;
    font-weight:700;
    margin-bottom:8px;
}
.hr-hero-mini-value{
    color:var(--hr-ink);
    font-size:1.08rem;
    font-weight:800;
}

.hr-glass-card{
    border:1px solid rgba(226,232,240,.9) !important;
    border-radius:26px !important;
    background:linear-gradient(180deg, rgba(255,255,255,.96) 0%, rgba(248,250,252,.96) 100%);
    box-shadow:0 18px 60px rgba(15,23,42,.06) !important;
    overflow:hidden;
}

.hr-card-header{
    padding:20px 22px 8px 22px;
    color:var(--hr-ink);
}

.hr-chip{
    display:inline-flex;
    align-items:center;
    padding:7px 12px;
    border-radius:999px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    color:#64748b;
    font-size:.78rem;
    font-weight:800;
}

.hr-form-label{
    font-weight:700;
    color:#334155;
    margin-bottom:8px;
}

.hr-input{
    height:48px;
    border-radius:16px;
    border:1px solid #dbe4ee;
    box-shadow:none !important;
}
.hr-input:focus{
    border-color:#38bdf8;
    box-shadow:0 0 0 4px rgba(56,189,248,.12) !important;
}

.hr-action-btn{
    border-radius:16px;
    padding:10px 18px;
    font-weight:800;
    border:1px solid transparent;
    box-shadow:none !important;
}
.hr-action-btn-primary{
    background:linear-gradient(135deg,#2563eb 0%, #1d4ed8 100%);
    color:#fff;
}
.hr-action-btn-primary:hover{
    color:#fff;
    transform:translateY(-1px);
}
.hr-action-btn-light{
    background:#fff;
    border-color:#dbe4ee;
    color:#0f172a;
}
.hr-action-btn-light:hover{
    background:#f8fbff;
    color:#0f172a;
}
.hr-action-btn-cyan{
    background:linear-gradient(135deg,#22d3ee 0%, #0ea5e9 100%);
    color:#fff;
}
.hr-action-btn-cyan:hover{
    color:#fff;
    transform:translateY(-1px);
}

.hr-kpi-card{
    border:none !important;
    border-radius:24px !important;
    overflow:hidden;
    box-shadow:0 14px 40px rgba(15,23,42,.07) !important;
}
.hr-kpi-card .card-body,
    .hr-kpi-card [data-ego-card-body]{
    padding:22px;
}
.hr-kpi-top{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
}
.hr-kpi-label{
    color:#64748b;
    font-weight:700;
    margin-bottom:8px;
}
.hr-kpi-value{
    color:#0f172a;
    font-size:2rem;
    line-height:1;
    font-weight:900;
    margin-bottom:8px;
}
.hr-kpi-sub{
    color:#64748b;
    font-size:.9rem;
    font-weight:600;
}
.hr-kpi-icon{
    width:58px;
    height:58px;
    border-radius:18px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.5rem;
    background:rgba(255,255,255,.65);
    border:1px solid rgba(255,255,255,.7);
}
.hr-kpi-progress{
    margin-top:16px;
    height:8px;
    border-radius:999px;
    background:rgba(255,255,255,.55);
    overflow:hidden;
}
.hr-kpi-progress span{
    display:block;
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg, rgba(255,255,255,.95), rgba(255,255,255,.65));
}

.hr-kpi-blue{
    background:linear-gradient(135deg,#eef6ff 0%, #dceeff 100%);
}
.hr-kpi-orange{
    background:linear-gradient(135deg,#fff4ea 0%, #ffe6ca 100%);
}
.hr-kpi-green{
    background:linear-gradient(135deg,#eafbf1 0%, #d6f8e3 100%);
}
.hr-kpi-purple{
    background:linear-gradient(135deg,#f2edff 0%, #e6ddff 100%);
}

.hr-stat-tile{
    padding:18px;
    border-radius:20px;
    background:#f8fbff;
    border:1px solid #e8eef5;
    height:100%;
}
.hr-stat-title{
    font-size:.82rem;
    color:#64748b;
    font-weight:700;
    margin-bottom:8px;
}
.hr-stat-value{
    font-size:1.6rem;
    line-height:1;
    color:#0f172a;
    font-weight:900;
    margin-bottom:8px;
}
.hr-stat-note{
    color:#64748b;
    font-size:.88rem;
}

.hr-chart-box{
    position:relative;
    overflow:hidden;
    border-radius:24px;
    min-height:340px;
    border:1px solid #e6edf5;
    background:linear-gradient(135deg,#f8fbff 0%, #f5f7ff 100%);
}
.hr-chart-grid{
    position:absolute;
    inset:0;
    background-image:
        linear-gradient(rgba(148,163,184,.10) 1px, transparent 1px),
        linear-gradient(90deg, rgba(148,163,184,.10) 1px, transparent 1px);
    background-size: 28px 28px;
    pointer-events:none;
}
.hr-chart-content{
    position:relative;
    z-index:1;
    padding:24px;
    height:100%;
}
.hr-chart-side{
    padding:12px;
}
.hr-mini-kpi{
    padding:14px 16px;
    border-radius:18px;
    background:rgba(255,255,255,.75);
    border:1px solid rgba(226,232,240,.9);
}
.hr-mini-kpi-label{
    color:#64748b;
    font-size:.82rem;
    font-weight:700;
    margin-bottom:6px;
}
.hr-mini-kpi-value{
    color:#0f172a;
    font-size:1.35rem;
    font-weight:900;
}

.hr-fake-chart{
    display:flex;
    flex-direction:column;
    gap:18px;
    justify-content:center;
    height:100%;
}
.hr-bar-wrap{
    display:grid;
    grid-template-columns: 80px 1fr 58px;
    gap:12px;
    align-items:center;
}
.hr-bar-label{
    font-weight:700;
    color:#334155;
    font-size:.92rem;
}
.hr-bar-track{
    height:14px;
    border-radius:999px;
    background:#eaf0f6;
    overflow:hidden;
}
.hr-bar-fill{
    height:100%;
    border-radius:999px;
}
.hr-bar-fill-cyan{
    background:linear-gradient(90deg,#22d3ee,#0ea5e9);
}
.hr-bar-fill-green{
    background:linear-gradient(90deg,#34d399,#10b981);
}
.hr-bar-fill-orange{
    background:linear-gradient(90deg,#fb923c,#f97316);
}
.hr-bar-fill-purple{
    background:linear-gradient(90deg,#a78bfa,#8b5cf6);
}
.hr-bar-value{
    text-align:right;
    font-weight:800;
    color:#0f172a;
}

.hr-table-wrap{
    border:1px solid #e8eef5;
    border-radius:20px;
    overflow:hidden;
}
.hr-modern-table thead th{
    background:#f8fbff;
    color:#475569;
    font-size:.82rem;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.02em;
    border-bottom:1px solid #e8eef5;
    padding:14px 16px;
    white-space:nowrap;
}
.hr-modern-table tbody td{
    padding:15px 16px;
    border-color:#eef2f7;
    color:#1e293b;
    vertical-align:middle;
}
.hr-modern-table tbody tr:hover{
    background:#fbfdff;
}

.hr-task-box{
    padding:20px;
    border-radius:24px;
    background:linear-gradient(135deg,#f8fbff 0%, #f4f8ff 100%);
    border:1px solid #e4edf7;
}
.hr-task-icon{
    width:56px;
    height:56px;
    border-radius:18px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#e0f2fe,#dbeafe);
    color:#2563eb;
    font-size:1.45rem;
    flex:0 0 auto;
}

.hr-side-list{
    display:flex;
    flex-direction:column;
    gap:12px;
}
.hr-side-item{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    padding:14px 16px;
    border-radius:18px;
    background:#fbfdff;
    border:1px solid #ebf1f6;
}
.hr-side-item-left{
    display:flex;
    align-items:center;
    gap:12px;
}
.hr-side-bullet{
    width:12px;
    height:12px;
    border-radius:999px;
    flex:0 0 auto;
}
.hr-bullet-cyan{ background:#06b6d4; }
.hr-bullet-green{ background:#10b981; }
.hr-bullet-orange{ background:#f97316; }
.hr-bullet-purple{ background:#8b5cf6; }

.hr-tip-box{
    padding:16px 18px;
    border-radius:18px;
    background:#fffaf0;
    border:1px solid #fde7bf;
}

@media (max-width: 991px){
    .hr-hero-card{
        padding:22px;
        border-radius:22px;
    }

    .hr-page-title{
        font-size:2rem;
    }

    .hr-bar-wrap{
        grid-template-columns: 72px 1fr 48px;
        gap:10px;
    }
}

@media (max-width: 767px){
    .hr-dashboard-page{
        padding-top:1rem !important;
    }

    .hr-hero-card{
        padding:18px;
        border-radius:20px;
    }

    .hr-page-subtitle{
        font-size:.92rem;
    }

    .hr-kpi-value{
        font-size:1.8rem;
    }

    .hr-chart-content{
        padding:18px;
    }

    .hr-bar-wrap{
        grid-template-columns: 1fr;
    }

    .hr-bar-value{
        text-align:left;
    }
}
</style>
@endsection