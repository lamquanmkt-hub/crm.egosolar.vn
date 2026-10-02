{{-- EGO_FINANCE_EXCEL_SIDEBAR_V1 --}}

@php
    use Illuminate\Support\Facades\Route;

    $egoFinanceWorkspace = 'finance';

    $egoFinanceUrl = static function (
        string $route,
        array $params = []
    ): ?string {
        return Route::has($route)
            ? route($route, $params)
            : null;
    };

    $egoFinanceActive = static function (
        string|array $routes
    ): bool {
        foreach ((array) $routes as $route) {
            if (request()->routeIs($route)) {
                return true;
            }
        }

        return false;
    };

    $egoFinanceDnttCount =
        (int)($egoPendingPaymentRequestsCount ?? 0);

    $egoFinanceTaskCount =
        (int)($egoMyUnfinishedTasksCount ?? 0);
@endphp


@once
<style id="ego-finance-excel-sidebar-style">

/* ==========================================================
   TÀI CHÍNH KẾ TOÁN
   TÊN NGHIỆP VỤ = ĐÚNG FILE EXCEL
   ========================================================== */

#sidebar .ego-finance-excel-head{
    margin:6px 8px 9px;
    padding:9px;

    border:1px solid rgba(34,197,94,.20);
    border-radius:10px;

    background:
        linear-gradient(
            135deg,
            rgba(22,163,74,.12),
            rgba(15,23,42,.04)
        );
}

#sidebar .ego-finance-excel-head small{
    display:block;

    margin-bottom:4px;

    color:rgba(148,163,184,.62);

    font-size:7px;
    font-weight:900;

    letter-spacing:.09em;
    text-transform:uppercase;
}

#sidebar .ego-finance-excel-title{
    display:flex;
    align-items:center;
    gap:7px;

    color:#fff;

    font-size:10.5px;
    line-height:1.2;
    font-weight:900;
}

#sidebar .ego-finance-excel-title i{
    width:20px;
    height:20px;

    display:inline-flex;
    align-items:center;
    justify-content:center;

    border-radius:6px;

    color:#4ade80;
    background:rgba(34,197,94,.11);
}

#sidebar .ego-finance-excel-actions{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:5px;

    margin-top:7px;
}

#sidebar .ego-finance-excel-actions a{
    min-height:25px;

    display:flex;
    align-items:center;
    justify-content:center;
    gap:4px;

    border:1px solid rgba(255,255,255,.075);
    border-radius:7px;

    color:rgba(226,232,240,.85)!important;
    background:rgba(255,255,255,.025);

    text-decoration:none!important;

    font-size:8px;
    font-weight:750;
}


/* Tổng quan */

#sidebar .ego-finance-overview .ego-link{
    margin:2px 7px 7px;

    min-height:39px;

    border:1px solid rgba(34,197,94,.25);
    border-radius:8px;

    background:
        linear-gradient(
            90deg,
            rgba(22,163,74,.15),
            rgba(22,163,74,.035)
        );

    box-shadow:
        inset 2px 0 0 rgba(74,222,128,.85);
}


/* Nhãn lớn */

#sidebar .ego-finance-section{
    list-style:none;

    margin:12px 14px 5px;

    color:rgba(148,163,184,.55);

    font-size:8px;
    line-height:1;
    font-weight:900;

    letter-spacing:.10em;
    text-transform:uppercase;
}


/* Heading đúng Excel */

#sidebar .ego-finance-excel-group{
    list-style:none;

    margin:12px 11px 4px;
    padding:0 8px 4px;

    color:#cbd5e1;

    border-bottom:1px solid rgba(148,163,184,.10);

    font-size:9.5px;
    line-height:1.35;
    font-weight:900;

    letter-spacing:.015em;
}


/* menu trực tiếp */

#sidebar .ego-finance-item{
    list-style:none;
}

#sidebar .ego-finance-item .ego-link{
    min-height:35px;

    margin:1px 7px;
    padding:7px 9px;

    border-radius:8px;
}

#sidebar .ego-finance-item--child .ego-link{
    min-height:33px;

    padding-left:39px;

    font-size:10.5px;
}

#sidebar .ego-finance-item--subchild .ego-link{
    min-height:31px;
    padding-left:52px;
    font-size:9.7px;
    color:rgba(203,213,225,.84);
}

#sidebar .ego-finance-item--subchild .ego-link .ego-ic{
    transform:scale(.82);
    opacity:.88;
}

#sidebar .ego-finance-item .ego-link:hover{
    background:rgba(34,197,94,.055);
}

#sidebar .ego-finance-item .ego-link.active{
    color:#fff;

    background:
        linear-gradient(
            90deg,
            rgba(22,163,74,.14),
            rgba(22,163,74,.025)
        );

    box-shadow:
        inset 2px 0 0 #4ade80;
}


/* Chưa có page */

#sidebar .ego-finance-disabled{
    opacity:.46;
}

#sidebar .ego-finance-disabled .ego-link{
    cursor:not-allowed;
}

#sidebar .ego-finance-disabled .ego-link::after{
    content:"";

    width:5px;
    height:5px;

    margin-left:auto;

    flex:0 0 auto;

    border-radius:999px;

    background:#64748b;
}


/* badge */

#sidebar .ego-finance-badge{
    margin-left:auto;

    min-width:20px;
    height:18px;

    display:inline-flex;
    align-items:center;
    justify-content:center;

    padding:0 5px;

    border-radius:999px;

    color:#dffaff;
    background:rgba(6,182,212,.14);
    border:1px solid rgba(34,211,238,.18);

    font-size:8px;
    font-weight:850;
}

</style>
@endonce


{{-- ======================================================
     WORKSPACE
     ====================================================== --}}

<li class="ego-workspace-static">

    <div class="ego-finance-excel-head">

        <small>Không gian làm việc</small>

        <div class="ego-finance-excel-title">
            <i class="bi bi-calculator-fill"></i>
            <span>TÀI CHÍNH KẾ TOÁN</span>
        </div>

        <div class="ego-finance-excel-actions">

            <a href="{{
                route(
                    'ego.workspace.department',
                    ['workspace' => 'finance']
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
     TỔNG QUAN - LUÔN DÒNG ĐẦU
     ====================================================== --}}

<li class="ego-item ego-finance-item ego-finance-overview">

    <a href="{{
        route(
            'ego.workspace.dashboard',
            ['workspace' => 'finance']
        )
    }}"
       class="
            ego-link
            {{
                request()->routeIs('ego.workspace.dashboard')
                && request()->route('workspace') === 'finance'
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


<li class="ego-finance-section">
    Nghiệp vụ
</li>


{{-- ======================================================
     1. BẢNG TIỀN LƯƠNG
     ====================================================== --}}

<li class="ego-item ego-finance-item">

    <a href="{{ route('finance.salary') }}"
       class="
            ego-link
            {{ request()->routeIs('finance.salary*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-cash-stack"></i>
        </span>

        <span class="ego-txt">
            1. BẢNG TIỀN LƯƠNG
        </span>

    </a>

</li>


{{-- ======================================================
     2. BÁO CÁO TÀI CHÍNH/THUẾ
     ====================================================== --}}

<li class="ego-finance-excel-group">
    2. BÁO CÁO TÀI CHÍNH/THUẾ
</li>


<li class="ego-item ego-finance-item ego-finance-item--child">

    <a href="{{
        route(
            'finance.reports',
            ['period' => 'quarter']
        )
    }}"
       class="
            ego-link
            {{
                request()->routeIs('finance.reports')
                && request('period') === 'quarter'
                    ? 'active'
                    : ''
            }}
       ">

        <span class="ego-ic">
            <i class="bi bi-calendar3"></i>
        </span>

        <span class="ego-txt">
            BÁO CÁO QUÝ
        </span>

    </a>

</li>


<li class="ego-item ego-finance-item ego-finance-item--child">

    <a href="{{
        route(
            'finance.reports',
            ['period' => 'year']
        )
    }}"
       class="
            ego-link
            {{
                request()->routeIs('finance.reports')
                && request('period') === 'year'
                    ? 'active'
                    : ''
            }}
       ">

        <span class="ego-ic">
            <i class="bi bi-calendar2-range"></i>
        </span>

        <span class="ego-txt">
            BÁO CÁO NĂM
        </span>

    </a>

</li>


<li class="ego-item ego-finance-item ego-finance-item--child">

    <a href="{{ route('finance.settlement') }}"
       class="ego-link {{ request()->routeIs('finance.settlement') ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-file-earmark-check"></i></span>
        <span class="ego-txt">QUYẾT TOÁN</span>
    </a>

</li>


<li class="ego-item ego-finance-item ego-finance-item--child">

    <a href="{{ route('finance.audit') }}"
       class="ego-link {{ request()->routeIs('finance.audit') ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-search"></i></span>
        <span class="ego-txt">KIỂM TOÁN</span>
    </a>

</li>


<li class="ego-finance-excel-group">
    BÁO CÁO TỒN QUỸ: QUỸ NGÂN HÀNG, QUỸ TIỀN MẶT
</li>


<li class="ego-item ego-finance-item ego-finance-item--child">

    <a href="{{
        route(
            'finance.accounts.index',
            ['type' => 'bank']
        )
    }}"
       class="
            ego-link
            {{
                request()->routeIs('finance.accounts.*')
                && request('type') === 'bank'
                    ? 'active'
                    : ''
            }}
       ">

        <span class="ego-ic">
            <i class="bi bi-bank2"></i>
        </span>

        <span class="ego-txt">
            QUỸ NGÂN HÀNG
        </span>

    </a>

</li>


<li class="ego-item ego-finance-item ego-finance-item--child">

    <a href="{{
        route(
            'finance.accounts.index',
            ['type' => 'cash']
        )
    }}"
       class="
            ego-link
            {{
                request()->routeIs('finance.accounts.*')
                && request('type') === 'cash'
                    ? 'active'
                    : ''
            }}
       ">

        <span class="ego-ic">
            <i class="bi bi-cash-coin"></i>
        </span>

        <span class="ego-txt">
            QUỸ TIỀN MẶT
        </span>

    </a>

</li>


{{-- ======================================================
     3. NỢ PHẢI THU
     ====================================================== --}}

<li class="ego-finance-excel-group">
    3. NỢ PHẢI THU
</li>

<li class="ego-item ego-finance-item ego-finance-item--child">
    <a href="{{ route('finance.project-receivables.index') }}"
       class="ego-link {{ request()->routeIs('finance.project-receivables.*') ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-buildings"></i></span>
        <span class="ego-txt">PHẢI THU CÔNG TRÌNH</span>
    </a>
</li>

<li class="ego-item ego-finance-item ego-finance-item--subchild">
    <a href="{{ route('finance.customer-debts.index', ['debt_type' => 'walk_in']) }}"
       class="ego-link {{ request()->routeIs('finance.customer-debts.*') && request('debt_type') === 'walk_in' ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-person-walking"></i></span>
        <span class="ego-txt">PHẢI THU KHÁCH VÃNG LAI</span>
    </a>
</li>

<li class="ego-item ego-finance-item ego-finance-item--child">
    <a href="{{ route('finance.customer-debts.index', ['debt_type' => 'dealer']) }}"
       class="ego-link {{ request()->routeIs('finance.customer-debts.*') && request('debt_type') === 'dealer' ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-shop"></i></span>
        <span class="ego-txt">PHẢI THU ĐẠI LÝ</span>
    </a>
</li>

<li class="ego-item ego-finance-item ego-finance-item--child">
    <a href="{{ route('finance.customer-debts.index', ['debt_type' => 'investment']) }}"
       class="ego-link {{ request()->routeIs('finance.customer-debts.*') && request('debt_type') === 'investment' ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-diagram-3"></i></span>
        <span class="ego-txt">PHẢI THU DỰ ÁN ĐẦU TƯ</span>
    </a>
</li>


{{-- ======================================================
     4. NỢ PHẢI TRẢ
     ====================================================== --}}

<li class="ego-finance-excel-group">
    4. NỢ PHẢI TRẢ
</li>

<li class="ego-item ego-finance-item ego-finance-item--child">
    <a href="{{ route('finance.supplier-debts.index') }}"
       class="ego-link {{ request()->routeIs('finance.supplier-debts.*') && !in_array(request('scope'), ['domestic','import'], true) ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-truck"></i></span>
        <span class="ego-txt">PHẢI TRẢ NHÀ CUNG CẤP</span>
    </a>
</li>

<li class="ego-item ego-finance-item ego-finance-item--subchild">
    <a href="{{ route('finance.supplier-debts.index', ['scope' => 'domestic']) }}"
       class="ego-link {{ request()->routeIs('finance.supplier-debts.*') && request('scope') === 'domestic' ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-house-check"></i></span>
        <span class="ego-txt">PHẢI TRẢ NHÀ CUNG CẤP TRONG NƯỚC</span>
    </a>
</li>

<li class="ego-item ego-finance-item ego-finance-item--child">
    <a href="{{ route('finance.supplier-debts.index', ['scope' => 'import']) }}"
       class="ego-link {{ request()->routeIs('finance.supplier-debts.*') && request('scope') === 'import' ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-box-arrow-in-down"></i></span>
        <span class="ego-txt">PHẢI TRẢ HÀNG NHẬP KHẨU</span>
    </a>
</li>

@foreach([
    ['bank', 'PHẢI TRẢ NGÂN HÀNG', 'bi-bank'],
    ['loan', 'PHẢI TRẢ NỢ VAY', 'bi-currency-dollar'],
    ['shareholder', 'PHẢI TRẢ CỔ ĐÔNG', 'bi-people']
] as [$ledgerCategory, $label, $icon])
    <li class="ego-item ego-finance-item ego-finance-item--child">
        <a href="{{ route('finance.ledger.index', ['direction' => 'payable', 'category' => $ledgerCategory]) }}"
           class="ego-link {{ request()->routeIs('finance.ledger.*') && request()->route('direction') === 'payable' && request()->route('category') === $ledgerCategory ? 'active' : '' }}">
            <span class="ego-ic"><i class="bi {{ $icon }}"></i></span>
            <span class="ego-txt">{{ $label }}</span>
        </a>
    </li>
@endforeach


{{-- ======================================================
     PHỐI HỢP
     ====================================================== --}}

<li class="ego-finance-section">
    Phối hợp
</li>


<li class="ego-item ego-finance-item">

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

        @if($egoFinanceDnttCount > 0)
            <span class="ego-finance-badge">
                {{ $egoFinanceDnttCount }}
            </span>
        @endif

    </a>

</li>

@if(\Illuminate\Support\Facades\Route::has('advance_requests.index'))
<li class="ego-item ego-finance-item">
    <a href="{{ route('advance_requests.index') }}"
       class="ego-link {{ request()->routeIs('advance_requests.*') ? 'active' : '' }}">
        <span class="ego-ic"><i class="bi bi-cash-coin"></i></span>
        <span class="ego-txt">Đề nghị tạm ứng</span>
    </a>
</li>
@endif


<li class="ego-item ego-finance-item">

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

        @if($egoFinanceTaskCount > 0)
            <span class="ego-finance-badge">
                {{ $egoFinanceTaskCount }}
            </span>
        @endif

    </a>

</li>




{{-- EGO_SHARED_COMPANY_DOCUMENTS_V1_START --}}
@if(\Illuminate\Support\Facades\Route::has('company-documents.index'))

<li class="ego-item ego-finance-item">

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
{{-- EGO_SHARED_COMPANY_DOCUMENTS_V1_END --}}


{{-- ======================================================
     CÁ NHÂN
     ====================================================== --}}

<li class="ego-finance-section">
    Cá nhân
</li>


<li class="ego-item ego-finance-item">

    <a href="{{ route('hr.attendance.my') }}"
       class="
            ego-link
            {{ request()->routeIs('hr.attendance.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-check2-circle"></i>
        </span>

        <span class="ego-txt">
            Chấm công
        </span>

    </a>

</li>


<li class="ego-item ego-finance-item">

    <a href="{{ route('hr.leave.index', ['tab' => 'mine']) }}"
       class="
            ego-link
            {{ request()->routeIs('hr.leave.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-calendar2-x"></i>
        </span>

        <span class="ego-txt">
            Nghỉ phép
        </span>

    </a>

</li>


<li class="ego-item ego-finance-item">

    <a href="{{ route('hr.overtime.index') }}"
       class="
            ego-link
            {{ request()->routeIs('hr.overtime.*') ? 'active' : '' }}
       ">

        <span class="ego-ic">
            <i class="bi bi-clock-history"></i>
        </span>

        <span class="ego-txt">
            Tăng ca
        </span>

    </a>

</li>
