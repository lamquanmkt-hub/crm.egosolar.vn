{{-- EGO_HR_EXCEL_SIDEBAR_V1 --}}

@php
    use Illuminate\Support\Facades\Route;

    $egoHrRoute = static function (
        string $name,
        array $params = []
    ): ?string {
        return Route::has($name)
            ? route($name, $params)
            : null;
    };

    $egoHrActive = static function (
        string|array $patterns
    ): bool {
        foreach ((array)$patterns as $pattern) {
            if (request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    };

    $egoHrFileLink = static function (
        string $route,
        array $params = []
    ) use ($egoHrRoute): array {

        $url = $egoHrRoute($route, $params);

        return [
            'url' => $url ?: '#',
            'disabled' => !$url,
        ];
    };

    $egoHrApplication =
        $egoHrFileLink(
            'hr.records.index',
            ['type' => 'application']
        );

    $egoHrInterview =
        $egoHrFileLink(
            'hr.recruitment.interviews'
        );

    $egoHrOffer =
        $egoHrFileLink(
            'hr.recruitment.offers'
        );

    $egoHrProbation =
        $egoHrFileLink(
            'hr.records.index',
            ['type' => 'probation_contract']
        );

    $egoHrLabor =
        $egoHrFileLink(
            'hr.records.index',
            ['type' => 'labor_contract']
        );

    $egoHrDegree =
        $egoHrFileLink(
            'hr.records.index',
            ['type' => 'degree']
        );
@endphp


@once
<style id="ego-hr-excel-sidebar-style">

/* ==========================================================
   NHÂN SỰ - SIDEBAR THEO FILE EXCEL
   ========================================================== */

#sidebar .ego-hr-head{
    margin:6px 8px 9px;
    padding:9px;

    border:1px solid rgba(56,189,248,.20);
    border-radius:11px;

    background:
        linear-gradient(
            135deg,
            rgba(14,165,233,.12),
            rgba(15,23,42,.035)
        );
}

#sidebar .ego-hr-head small{
    display:block;

    margin-bottom:4px;

    color:rgba(148,163,184,.60);

    font-size:7px;
    line-height:1;
    font-weight:900;

    letter-spacing:.09em;
    text-transform:uppercase;
}

#sidebar .ego-hr-head__name{
    display:flex;
    align-items:center;
    gap:7px;

    color:#fff;

    font-size:11px;
    line-height:1.2;
    font-weight:900;
}

#sidebar .ego-hr-head__icon{
    width:21px;
    height:21px;

    display:inline-flex;
    align-items:center;
    justify-content:center;

    border-radius:6px;

    color:#67e8f9;
    background:rgba(34,211,238,.12);
}

#sidebar .ego-hr-head__actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:5px;

    margin-top:7px;
}

#sidebar .ego-hr-head__actions a{
    min-height:25px;

    display:flex;
    align-items:center;
    justify-content:center;
    gap:4px;

    border:1px solid rgba(255,255,255,.075);
    border-radius:7px;

    color:rgba(226,232,240,.84)!important;
    background:rgba(255,255,255,.025);

    text-decoration:none!important;

    font-size:8px;
    font-weight:750;
}


/* ==========================================================
   SECTION
   ========================================================== */

#sidebar .ego-hr-section{
    list-style:none;

    margin:12px 14px 5px;

    color:rgba(148,163,184,.55);

    font-size:8px;
    line-height:1;
    font-weight:900;

    letter-spacing:.10em;
    text-transform:uppercase;
}


/* ==========================================================
   MAIN ITEM
   ========================================================== */

#sidebar .ego-hr-item{
    list-style:none;
    margin:0;
}

#sidebar .ego-hr-item > .ego-link{
    min-height:38px;

    margin:1px 7px;
    padding:7px 9px;

    border-radius:8px;

    transition:
        background .10s ease,
        color .10s ease;
}

#sidebar .ego-hr-item > .ego-link:hover{
    background:rgba(34,211,238,.065);
}

#sidebar .ego-hr-item > .ego-link.active{
    color:#fff;

    background:
        linear-gradient(
            90deg,
            rgba(6,182,212,.16),
            rgba(6,182,212,.035)
        );

    box-shadow:
        inset 2px 0 0 #22d3ee;
}


/* Tổng quan */

#sidebar .ego-hr-overview > .ego-link{
    margin-top:3px;
    margin-bottom:7px;

    min-height:40px;

    border:1px solid rgba(34,211,238,.26);

    background:
        linear-gradient(
            90deg,
            rgba(6,182,212,.17),
            rgba(8,145,178,.04)
        );
}


/* ==========================================================
   DANH SÁCH NHÂN VIÊN
   ========================================================== */

#sidebar .ego-hr-employee-main{
    align-items:flex-start!important;
}

#sidebar .ego-hr-employee-copy{
    min-width:0;

    display:flex;
    flex-direction:column;
    gap:3px;
}

#sidebar .ego-hr-employee-copy strong{
    color:inherit;

    font-size:10.5px;
    line-height:1.2;
    font-weight:850;
}

#sidebar .ego-hr-employee-copy small{
    color:rgba(148,163,184,.66);

    font-size:7.5px;
    line-height:1.35;
    font-weight:600;

    white-space:normal;
}


/* ==========================================================
   HỒ SƠ NHÂN VIÊN
   ========================================================== */

#sidebar .ego-hr-group-title{
    list-style:none;

    margin:10px 10px 3px;
    padding:7px 9px;

    border-radius:8px;

    color:#e2e8f0;

    background:rgba(148,163,184,.045);
    border:1px solid rgba(148,163,184,.075);

    font-size:10px;
    line-height:1.2;
    font-weight:900;
}

#sidebar .ego-hr-subitem{
    list-style:none;
}

#sidebar .ego-hr-subitem > a{
    min-height:32px;

    margin:1px 7px;
    padding:6px 10px 6px 40px;

    display:flex;
    align-items:center;
    gap:8px;

    border-radius:7px;

    color:rgba(203,213,225,.80)!important;

    text-decoration:none!important;

    font-size:9.5px;
    line-height:1.15;
    font-weight:700;
}

#sidebar .ego-hr-subitem > a::before{
    content:"";

    width:5px;
    height:5px;

    flex:0 0 auto;

    border-radius:999px;

    background:rgba(34,211,238,.55);
}

#sidebar .ego-hr-subitem > a:hover{
    color:#fff!important;

    background:rgba(34,211,238,.055);
}

#sidebar .ego-hr-subitem > a.active{
    color:#fff!important;

    background:rgba(34,211,238,.09);

    box-shadow:
        inset 2px 0 0 rgba(34,211,238,.8);
}

#sidebar .ego-hr-subitem--disabled{
    opacity:.42;
}

#sidebar .ego-hr-subitem--disabled > a{
    cursor:not-allowed;
}


/* Không xổ dropdown */

#sidebar .ego-hr-head,
#sidebar .ego-hr-item,
#sidebar .ego-hr-group-title,
#sidebar .ego-hr-subitem{
    position:relative;
}

</style>
@endonce


{{-- ======================================================
     WORKSPACE
     ====================================================== --}}

<li class="ego-workspace-static">

    <div class="ego-hr-head">

        <small>Không gian làm việc</small>

        <div class="ego-hr-head__name">

            <span class="ego-hr-head__icon">
                <i class="bi bi-people-fill"></i>
            </span>

            <span>NHÂN SỰ</span>

        </div>

        <div class="ego-hr-head__actions">

            <a href="{{
                route(
                    'ego.workspace.department',
                    ['workspace' => 'hr']
                )
            }}">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                Ứng dụng
            </a>

            <a href="{{ route('ego.workspace.index') }}">
                <i class="bi bi-arrow-left-right"></i>
                Đổi phòng
            </a>

        </div>

    </div>

</li>


{{-- ======================================================
     TỔNG QUAN
     ====================================================== --}}

<li class="ego-item ego-hr-item ego-hr-overview">

    <a href="{{
        route(
            'ego.workspace.dashboard',
            ['workspace' => 'hr']
        )
    }}"
       class="
            ego-link
            {{
                request()->routeIs('ego.workspace.dashboard')
                && request()->route('workspace') === 'hr'
                    ? 'active'
                    : ''
            }}
       ">

        <span class="ego-ic">
            <i class="bi bi-speedometer2"></i>
        </span>

        <span class="ego-txt">
            Tổng quan
        </span>

    </a>

</li>


<li class="ego-hr-section">
    Nghiệp vụ
</li>


{{-- ======================================================
     1. DANH SÁCH NHÂN VIÊN
     ====================================================== --}}

<li class="ego-item ego-hr-item">

    <a href="{{ route('hr.employees.index') }}"
       class="
            ego-link
            ego-hr-employee-main
            {{ request()->routeIs('hr.employees.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-people-fill"></i>
        </span>

        <span class="ego-hr-employee-copy">

            <strong>
                1. DANH SÁCH NHÂN VIÊN
            </strong>

            <small>
                Họ tên · Chức vụ · Phòng ban · Số LH · Ngày vào
            </small>

        </span>

    </a>

</li>


{{-- ======================================================
     2. HỒ SƠ NHÂN VIÊN
     ====================================================== --}}

<li class="ego-hr-group-title">
    2. HỒ SƠ NHÂN VIÊN
</li>


<li class="
    ego-hr-subitem
    {{ $egoHrApplication['disabled'] ? 'ego-hr-subitem--disabled' : '' }}
">
    <a href="{{ $egoHrApplication['url'] }}"
       @if($egoHrApplication['disabled'])
           onclick="return false;"
       @endif>
        ĐƠN XIN VIỆC
    </a>
</li>


<li class="
    ego-hr-subitem
    {{ $egoHrInterview['disabled'] ? 'ego-hr-subitem--disabled' : '' }}
">
    <a href="{{ $egoHrInterview['url'] }}"
       @if($egoHrInterview['disabled'])
           onclick="return false;"
       @endif
       class="{{ request()->routeIs('hr.recruitment.interviews') ? 'active' : '' }}">
        THƯ MỜI PV
    </a>
</li>


<li class="
    ego-hr-subitem
    {{ $egoHrOffer['disabled'] ? 'ego-hr-subitem--disabled' : '' }}
">
    <a href="{{ $egoHrOffer['url'] }}"
       @if($egoHrOffer['disabled'])
           onclick="return false;"
       @endif
       class="{{ request()->routeIs('hr.recruitment.offers') ? 'active' : '' }}">
        THƯ MỜI NHẬN VIỆC
    </a>
</li>


<li class="
    ego-hr-subitem
    {{ $egoHrProbation['disabled'] ? 'ego-hr-subitem--disabled' : '' }}
">
    <a href="{{ $egoHrProbation['url'] }}"
       @if($egoHrProbation['disabled'])
           onclick="return false;"
       @endif>
        HĐ THỬ VIỆC
    </a>
</li>


<li class="
    ego-hr-subitem
    {{ $egoHrLabor['disabled'] ? 'ego-hr-subitem--disabled' : '' }}
">
    <a href="{{ $egoHrLabor['url'] }}"
       @if($egoHrLabor['disabled'])
           onclick="return false;"
       @endif>
        HĐ LAO ĐỘNG
    </a>
</li>


<li class="
    ego-hr-subitem
    {{ $egoHrDegree['disabled'] ? 'ego-hr-subitem--disabled' : '' }}
">
    <a href="{{ $egoHrDegree['url'] }}"
       @if($egoHrDegree['disabled'])
           onclick="return false;"
       @endif>
        BẰNG CẤP
    </a>
</li>


{{-- ======================================================
     3. CHẤM CÔNG
     ====================================================== --}}

<li class="ego-item ego-hr-item">

    <a href="{{ route('hr.attendance.index') }}"
       class="
            ego-link
            {{ request()->routeIs('hr.attendance.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-check2-circle"></i>
        </span>

        <span class="ego-txt">
            3. CHẤM CÔNG
        </span>

    </a>

</li>


{{-- ======================================================
     4. TĂNG CA
     ====================================================== --}}

<li class="ego-item ego-hr-item">

    <a href="{{ route('hr.overtime.index') }}"
       class="
            ego-link
            {{ request()->routeIs('hr.overtime.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-clock-history"></i>
        </span>

        <span class="ego-txt">
            4. TĂNG CA
        </span>

    </a>

</li>


{{-- ======================================================
     5. ĐƠN NGHỈ PHÉP
     ====================================================== --}}

<li class="ego-item ego-hr-item">

    <a href="{{ route('hr.leave.index') }}"
       class="
            ego-link
            {{ request()->routeIs('hr.leave.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-calendar2-x-fill"></i>
        </span>

        <span class="ego-txt">
            5. ĐƠN NGHỈ PHÉP
        </span>

    </a>

</li>



{{-- EGO_HR_SHARED_APPS_V2_START --}}

{{-- ======================================================
     PHỐI HỢP
     Không thuộc Excel nghiệp vụ Nhân sự.
     Đây là ứng dụng CRM dùng chung.
     ====================================================== --}}

<li class="ego-hr-section">
    Phối hợp
</li>


{{-- BOOKING PHÒNG HỌP --}}
@if(\Illuminate\Support\Facades\Route::has('booking-rooms.index'))

<li class="ego-item ego-hr-item">

    <a href="{{ route('booking-rooms.index') }}"
       class="
            ego-link
            {{ request()->routeIs('booking-rooms.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-calendar2-week"></i>
        </span>

        <span class="ego-txt">
            Booking phòng họp
        </span>

    </a>

</li>

@elseif(\Illuminate\Support\Facades\Route::has('meeting-rooms.index'))

<li class="ego-item ego-hr-item">

    <a href="{{ route('meeting-rooms.index') }}"
       class="
            ego-link
            {{ request()->routeIs('meeting-rooms.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-calendar2-week"></i>
        </span>

        <span class="ego-txt">
            Booking phòng họp
        </span>

    </a>

</li>

@endif


{{-- ĐỀ NGHỊ THANH TOÁN --}}
@if(\Illuminate\Support\Facades\Route::has('payment_requests.index'))

<li class="ego-item ego-hr-item">

    <a href="{{ route('payment_requests.index') }}"
       class="
            ego-link
            {{ request()->routeIs('payment_requests.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-receipt-cutoff"></i>
        </span>

        <span class="ego-txt">
            Đề nghị thanh toán
        </span>

        @if((int)($egoPendingPaymentRequestsCount ?? 0) > 0)

            <span class="ego-ws-server-badge">
                {{ (int)$egoPendingPaymentRequestsCount }}
            </span>

        @endif

    </a>

</li>

@endif

{{-- ĐỀ NGHỊ TẠM ỨNG --}}
@if(\Illuminate\Support\Facades\Route::has('advance_requests.index'))
<li class="ego-item ego-hr-item">
    <a href="{{ route('advance_requests.index') }}"
       class="ego-link {{ request()->routeIs('advance_requests.*') ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-cash-coin"></i></span>
        <span class="ego-txt">Đề nghị tạm ứng</span>
    </a>
</li>
@endif


{{-- ĐỀ XUẤT --}}
@if(\Illuminate\Support\Facades\Route::has('de-xuat.index'))

<li class="ego-item ego-hr-item">

    <a href="{{ route('de-xuat.index') }}"
       class="
            ego-link
            {{ request()->routeIs('de-xuat.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-lightbulb-fill"></i>
        </span>

        <span class="ego-txt">
            Đề xuất
        </span>

        @if((int)($egoPendingProposalsCount ?? 0) > 0)

            <span class="ego-ws-server-badge">
                {{ (int)$egoPendingProposalsCount }}
            </span>

        @endif

    </a>

</li>

@endif


{{-- CÔNG VIỆC --}}
@if(\Illuminate\Support\Facades\Route::has('tasks.index'))

<li class="ego-item ego-hr-item">

    <a href="{{ route('tasks.index') }}"
       class="
            ego-link
            {{ request()->routeIs('tasks.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-briefcase-fill"></i>
        </span>

        <span class="ego-txt">
            Công việc
        </span>

        @if((int)($egoMyUnfinishedTasksCount ?? 0) > 0)

            <span class="ego-ws-server-badge">
                {{ (int)$egoMyUnfinishedTasksCount }}
            </span>

        @endif

    </a>

</li>

@endif


{{-- HỒ SƠ CÔNG TY --}}
@if(\Illuminate\Support\Facades\Route::has('company-documents.index'))

<li class="ego-item ego-hr-item">

    <a href="{{ route('company-documents.index') }}"
       class="
            ego-link
            {{ request()->routeIs('company-documents.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-folder2-open"></i>
        </span>

        <span class="ego-txt">
            Hồ sơ công ty
        </span>

    </a>

</li>

@endif

{{-- EGO_HR_SHARED_APPS_V2_END --}}

