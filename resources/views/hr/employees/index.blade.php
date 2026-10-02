@extends('layouts.app')

@section('content')

<style> .hr-actions form.employee-delete-form{
        flex:1;
        margin:0;
        min-width:0;
    }.hr-actions .btn-delete-employee{
        width:100%;
        height:38px;
        border-radius:14px;
        border:1px solid #fecdd3;
        background:#fff1f2;
        color:#be123c;
        font-size:13px;
        font-weight:800;
        cursor:pointer;
        display:flex;
        align-items:center;
        justify-content:center;
        gap:6px;
        transition:.18s ease;
    }.hr-actions .btn-delete-employee:hover{
        background:#ffe4e6;
        border-color:#fb7185;
        transform:translateY(-1px);
    }
</style>


<style> .btn-delete-employee{
        width:100%;
        height:36px;
        border-radius:10px;
        border:1px solid #fecdd3;
        background:#fff1f2;
        color:#be123c;
        font-size:13px;
        font-weight:800;
        cursor:pointer;
        display:flex;
        align-items:center;
        justify-content:center;
        gap:6px;
        transition:.18s ease;
    }.btn-delete-employee:hover{
        background:#ffe4e6;
        border-color:#fb7185;
        transform:translateY(-1px);
    }
</style>


<style> /* EGO_HR_EMPLOYEE_SALARY_CURRENT_CSS_START */ .hr-salary-current{
    margin-top:10px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    min-height:38px;
    padding:8px 10px;
    border-radius:14px;
    background:linear-gradient(135deg,#f0fdfa,#ffffff);
    border:1px solid #bdeee7;
}.hr-salary-current-label{
    display:flex;
    align-items:center;
    gap:6px;
    min-width:0;
    color:#0f766e;
    font-size:12px;
    font-weight:900;
}.hr-salary-current-label span{
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}.hr-salary-current-value{
    color:#047857;
    font-size:13px;
    font-weight:950;
    white-space:nowrap;
}
/* EGO_HR_EMPLOYEE_SALARY_CURRENT_CSS_END */

</style>


<style> :root{
        --hr-bg:#f4f7fb;
        --hr-surface:#ffffff;
        --hr-line:#e6eef7;
        --hr-text:#0f172a;
        --hr-muted:#64748b;
        --hr-primary:#2563eb;
        --hr-primary-2:#1d4ed8;
        --hr-success:#059669;
        --hr-danger:#dc2626;
        --hr-violet:#7c3aed;
        --hr-shadow:0 18px 50px rgba(15,23,42,.08);
        --hr-shadow-soft:0 10px 28px rgba(15,23,42,.05);
    }.hr-modern-page{background:linear-gradient(180deg,#f8fbff 0%,#f4f7fb 100%);min-height:100vh;}.hr-hero{position:relative;overflow:hidden;border:none;border-radius:34px;color:#fff;background:radial-gradient(circle at top right, rgba(125,211,252,.32), transparent 24%),radial-gradient(circle at left bottom, rgba(37,99,235,.20), transparent 24%),linear-gradient(135deg,#07111f 0%,#102a56 44%,#2563eb 100%);box-shadow:0 28px 70px rgba(2,6,23,.18);}.hr-hero::after{content:"";position:absolute;width:280px;height:280px;right:-70px;bottom:-80px;border-radius:50%;background:rgba(255,255,255,.08);filter:blur(8px);}.hr-chip{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;background:rgba(255,255,255,.11);border:1px solid rgba(255,255,255,.14);font-size:13px;font-weight:700;}.hr-hero-stat{background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.14);border-radius:22px;padding:18px;min-height:118px;}.hr-hero-stat .value{font-size:32px;font-weight:800;line-height:1;margin-top:10px;}.hr-filter-card,
    .hr-department-card,
    .hr-empty-card{background:var(--hr-surface);border:1px solid var(--hr-line);border-radius:26px;box-shadow:var(--hr-shadow-soft);}.hr-section-title{font-size:18px;font-weight:800;color:var(--hr-text);margin-bottom:4px;}.hr-section-subtitle{color:var(--hr-muted);font-size:13px;}.hr-filter-chip-wrap{display:flex;gap:10px;overflow:auto;padding-bottom:4px;scrollbar-width:thin;}.hr-filter-chip{flex:0 0 auto;display:inline-flex;align-items:center;gap:8px;padding:12px 16px;border-radius:999px;border:1px solid var(--hr-line);background:#fff;color:var(--hr-text);text-decoration:none;font-size:13px;font-weight:800;transition:.2s ease;}.hr-filter-chip:hover,
    .hr-filter-chip.is-active{background:#eff6ff;color:var(--hr-primary);border-color:#bfdbfe;transform:translateY(-1px);}.hr-modern-page .hri-label{font-size:13px;font-weight:700;color:#334155;margin-bottom:8px;}.hr-modern-page .hri-input{min-height:48px;border-radius:14px;border-color:#dbe4ef;padding-left:14px;padding-right:14px;box-shadow:none !important;}.hr-modern-page .hri-input:focus{border-color:#93c5fd;}.hr-modern-page .hr-btn{border-radius:14px;font-weight:700;}/* `border:none` đặt border-color về currentColor (trắng). Không vẽ ra vì rộng 0,
    nhưng khai rõ `0 solid transparent` để giá trị tính toán khớp hệt bản Bootstrap cũ. */ .hr-modern-page .hr-btn-primary{background:linear-gradient(135deg,var(--hr-primary),var(--hr-primary-2));border:0 none transparent;}.hr-department-card{overflow:hidden;box-shadow:var(--hr-shadow);}.hr-department-header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;padding:22px 24px;border-bottom:1px solid var(--hr-line);background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);}.hr-badge{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border-radius:999px;border:1px solid transparent;font-size:12px;font-weight:800;white-space:nowrap;}.hr-badge-blue{background:#eff6ff;color:#1d4ed8;border-color:#dbeafe;}.hr-badge-green{background:#ecfdf5;color:#047857;border-color:#d1fae5;}.hr-badge-violet{background:#f5f3ff;color:#7c3aed;border-color:#ede9fe;}.hr-badge-amber{background:#fff7ed;color:#b45309;border-color:#fed7aa;}.hr-badge-slate{background:#f8fafc;color:#475569;border-color:#e2e8f0;}.hr-badge-red{background:#fef2f2;color:#b91c1c;border-color:#fecaca;}.hr-members-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:18px;padding:24px;}.hr-person-card{grid-column:span 3;border:1px solid var(--hr-line);border-radius:22px;background:#fff;box-shadow:var(--hr-shadow-soft);padding:18px;position:relative;min-height:100%;}.hr-person-card.is-leader{background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);border-color:#cfe0ff;}.hr-person-top{display:flex;align-items:center;gap:14px;margin-bottom:14px;}.hr-avatar,
    .hr-avatar-fallback{width:58px;height:58px;border-radius:20px;object-fit:cover;border:2px solid #e5edf7;flex-shrink:0;}.hr-avatar-fallback{display:flex;align-items:center;justify-content:center;font-weight:800;color:#0f172a;background:linear-gradient(135deg,#dbeafe,#bfdbfe);}.hr-person-name{font-size:18px;font-weight:800;color:var(--hr-text);line-height:1.3;margin-bottom:0;}.hr-person-body{display:flex;flex-direction:column;gap:10px;}.hr-meta-highlight{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
        margin-bottom:2px;
    }.hr-meta-pill{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:7px 11px;
        border-radius:12px;
        border:1px solid #dbeafe;
        background:linear-gradient(180deg,#f8fbff 0%,#eef6ff 100%);
        color:#0f172a;
        font-size:12px;
        line-height:1.3;
        min-height:36px;
    }.hr-meta-pill i{
        color:#2563eb;
        font-size:13px;
        margin-top:0;
    }.hr-meta-pill .label{
        font-weight:800;
        color:#0f172a;
        white-space:nowrap;
    }.hr-meta-pill .value{
        color:#1e3a8a;
        font-weight:700;
    }.hr-meta-pill-department{
        border-color:#bfdbfe;
        background:linear-gradient(180deg,#f8fbff 0%,#eff6ff 100%);
    }.hr-meta-pill-position{
        border-color:#ddd6fe;
        background:linear-gradient(180deg,#faf5ff 0%,#f5f3ff 100%);
    }.hr-meta-pill-position i{
        color:#7c3aed;
    }.hr-meta-pill-position .value{
        color:#5b21b6;
    }.hr-info-line{
        display:flex;
        align-items:flex-start;
        gap:8px;
        color:#334155;
        font-size:13px;
        line-height:1.45;
        word-break:break-word;
    }.hr-info-line i{color:#94a3b8;margin-top:2px;}.hr-info-line strong{color:#0f172a;font-weight:800;}.hr-actions{display:flex;gap:8px;margin-top:14px;}.hr-actions .hr-btn{flex:1;}.hr-empty-card{padding:72px 24px;text-align:center;}.hr-empty-icon{width:72px;height:72px;border-radius:24px;display:inline-flex;align-items:center;justify-content:center;background:#eff6ff;color:#2563eb;font-size:30px;margin-bottom:16px;}@media (max-width:1399.98px){.hr-person-card{grid-column:span 4;}
    }@media (max-width:991.98px){.hr-person-card{grid-column:span 6;}.hr-hero{border-radius:28px;}
    }@media (max-width:767.98px){.hr-members-grid{padding:16px;}.hr-person-card{grid-column:span 12;}.hr-department-header{padding:18px;}.hr-meta-highlight{flex-direction:column;}
    }
</style>

<div class="container-fluid tw:py-6 hr-modern-page">
    <x-ui.card class="hr-hero tw:mb-6">
        <x-ui.card-body class="tw:p-6">
            <div class="tw:row tw:g-4 tw:items-center">
                <div class="tw:min-[75rem]:col12-7">
                    <div class="tw:flex flex-wrap tw:gap-2 tw:mb-4">
                        <span class="hr-chip"><i class="bi bi-stars"></i> HR Command Center</span>
                        <span class="hr-chip"><i class="bi bi-diagram-3"></i> Cấu trúc theo phòng ban</span>
                    </div>

                    <h1 class="tw:font-bold tw:mb-4" style="font-size:clamp(28px,4vw,42px);line-height:1.1;">
                        Xem nhanh toàn bộ nhân sự theo <span style="color:#93c5fd;">phòng ban</span>,
                        nhận diện rõ <span style="color:#bfdbfe;">ban giám đốc</span>, trưởng nhóm và từng thành viên.
                    </h1>

                    <div class="tw:mb-6" style="max-width:760px; opacity:.92; font-size:15px;">
                        Giao diện mới ưu tiên cho admin: mỗi cụm phòng ban là một khu vực riêng, người ngừng hợp tác được gom xuống cuối cùng, và mỗi card hiển thị rõ ảnh đại diện, tên, phòng ban và chức vụ.
                    </div>

                    <div class="tw:flex flex-wrap tw:gap-2">
                        <x-ui.button href="{{ route('hr.dashboard') }}" variant="light" size="none" class="hr-btn tw:px-4 tw:py-[6px] tw:text-[16px]/[24px]">
                            <i class="bi bi-arrow-left me-1"></i> Dashboard
                        </x-ui.button>
                        <x-ui.button href="{{ route('hr.employees.create') }}" variant="primary" size="none" class="hr-btn hr-btn-primary tw:px-4 tw:py-[6px] tw:text-[16px]/[24px]">
                            <i class="bi bi-plus-circle me-1"></i> Thêm nhân viên
                        </x-ui.button>
                        <x-ui.button href="{{ route('hr.departments.index') }}" variant="outline-light" size="none" class="hr-btn tw:px-4 tw:py-[6px] tw:text-[16px]/[24px]">
                            <i class="bi bi-building me-1"></i> Phòng ban
                        </x-ui.button>
                    </div>
                </div>

                <div class="tw:min-[75rem]:col12-5">
                    <div class="tw:row tw:g-3">
                        <div class="tw:col12-6">
                            <div class="hr-hero-stat">
                                <div class="small opacity-75">Tổng nhân sự</div>
                                <div class="value">{{ $totalEmployees }}</div>
                                <div class="small opacity-75 tw:mt-2">Toàn bộ hồ sơ hệ thống</div>
                            </div>
                        </div>
                        <div class="tw:col12-6">
                            <div class="hr-hero-stat">
                                <div class="small opacity-75">Đang hoạt động</div>
                                <div class="value">{{ $activeEmployees }}</div>
                                <div class="small opacity-75 tw:mt-2">Đội ngũ đang vận hành</div>
                            </div>
                        </div>
                        <div class="tw:col12-6">
                            <div class="hr-hero-stat">
                                <div class="small opacity-75">Đã ngừng hợp tác</div>
                                <div class="value">{{ $inactiveEmployees }}</div>
                                <div class="small opacity-75 tw:mt-2">Gom xuống cuối cùng</div>
                            </div>
                        </div>
                        <div class="tw:col12-6">
                            <div class="hr-hero-stat">
                                <div class="small opacity-75">Phòng ban</div>
                                <div class="value">{{ $departmentCount }}</div>
                                <div class="small opacity-75 tw:mt-2">Cơ cấu tổ chức</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.card-body>
    </x-ui.card>

    <div class="hr-filter-card tw:p-6 tw:mb-4">
        <div class="tw:flex tw:justify-between tw:items-center flex-wrap tw:gap-2 tw:mb-4">
            <div>
                <div class="hr-section-title">Lọc nhanh theo bộ phận</div>
                <div class="hr-section-subtitle">Chọn một nhóm để xem riêng phòng ban mong muốn</div>
            </div>
            <x-ui.button href="{{ route('hr.departments.index') }}" variant="outline-primary" size="none" class="hr-btn tw:px-4 tw:py-1 tw:text-[14px]/[21px]">
                <i class="bi bi-gear me-1"></i> Quản lý phòng ban
            </x-ui.button>
        </div>

        <div class="hr-filter-chip-wrap">
            <a href="{{ route('hr.employees.index') }}" class="hr-filter-chip {{ request('department_id') ? '' : 'is-active' }}">
                <i class="bi bi-grid"></i> Tất cả
            </a>
            @foreach($departments as $department)
                <a href="{{ route('hr.employees.index', array_merge(request()->query(), ['department_id' => $department->id])) }}"
                   class="hr-filter-chip {{ (string) request('department_id') === (string) $department->id ? 'is-active' : '' }}">
                    <i class="bi bi-building"></i>
                    {{ $department->name }}
                    <span class="tw:text-[rgba(33,37,41,0.75)]">{{ $department->users_count ?? 0 }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="hr-filter-card tw:p-6 tw:mb-6">
        <div class="hr-section-title">Bộ lọc thông minh</div>
        <div class="hr-section-subtitle tw:mb-4">Tìm nhanh theo tên, email, số điện thoại, chức vụ, vai trò và trạng thái</div>

        <form method="GET" action="{{ route('hr.employees.index') }}">
            <div class="tw:row tw:g-3 tw:items-end">
                <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-3">
                    <x-ui.label class="hri-label">Từ khóa</x-ui.label>
                    <x-ui.input type="text" name="keyword" class="hri-input" value="{{ request('keyword') }}" placeholder="Tên, email, số điện thoại..." />
                </div>
                <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-2">
                    <x-ui.label class="hri-label">Phòng ban</x-ui.label>
                    <x-ui.select name="department_id" class="hri-input">
                        <option value="">Tất cả phòng ban</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-2">
                    <x-ui.label class="hri-label">Chức vụ</x-ui.label>
                    <x-ui.select name="position_id" class="hri-input">
                        <option value="">Tất cả chức vụ</option>
                        @foreach($positions as $position)
                            <option value="{{ $position->id }}" {{ request('position_id') == $position->id ? 'selected' : '' }}>
                                {{ $position->name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-2">
                    <x-ui.label class="hri-label">Vai trò</x-ui.label>
                    <x-ui.select name="role" class="hri-input">
                        <option value="">Tất cả vai trò</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ request('role') == $role->name ? 'selected' : '' }}>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-2">
                    <x-ui.label class="hri-label">Trạng thái</x-ui.label>
                    <x-ui.select name="is_active" class="hri-input">
                        <option value="">Tất cả trạng thái</option>
                        <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Đang hoạt động</option>
                        <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Đã ngừng hợp tác</option>
                    </x-ui.select>
                </div>
                <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-1 d-grid">
                    <x-ui.button variant="primary" type="submit" class="hr-btn hr-btn-primary tw:h-full">
                        <i class="bi bi-search me-1"></i> Lọc
                    </x-ui.button>
                </div>
            </div>
        </form>
    </div>

    @if($boardCards->count())
        <div class="hr-department-card tw:mb-6">
            <div class="hr-department-header">
                <div>
                    <div class="hr-section-title">Ban giám đốc</div>
                    <div class="hr-section-subtitle">Nhóm điều hành và quản trị cấp cao</div>
                </div>
                <div class="tw:flex flex-wrap tw:gap-2 tw:justify-end">
                    <span class="hr-badge hr-badge-amber"><i class="bi bi-stars"></i> Leadership</span>
                    <span class="hr-badge hr-badge-slate"><i class="bi bi-people"></i> {{ $boardCards->count() }} người</span>
                </div>
            </div>

            <div class="hr-members-grid">
                @foreach($boardCards as $card)
                    @include('hr.employees.partials.person-card', ['card' => $card])
                @endforeach
            </div>
        </div>
    @endif

    @forelse($departmentGroups as $group)
        @if($group['name'] === 'Ban giám đốc')
            @continue
        @endif


        <div class="hr-department-card tw:mb-6">
            <div class="hr-department-header">
                <div>
                    <div class="hr-section-title">{{ $group['name'] }}</div>
                    <div class="hr-section-subtitle">{{ $group['subtitle'] ?? 'Danh sách nhân sự theo phòng ban' }}</div>
                </div>
                <div class="tw:flex flex-wrap tw:gap-2 tw:justify-end">
                    @if(!empty($group['leader']))
                        <span class="hr-badge hr-badge-amber"><i class="bi bi-stars"></i> 1 trưởng nhóm chính</span>
                    @endif
                    <span class="hr-badge hr-badge-blue"><i class="bi bi-people"></i> {{ count($group['cards']) }} người</span>
                </div>
            </div>

            @if(count($group['cards']))
                <div class="hr-members-grid">
                    @foreach($group['cards'] as $card)
                        @include('hr.employees.partials.person-card', ['card' => $card])
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <div class="hr-empty-card">
            <div class="hr-empty-icon"><i class="bi bi-people"></i></div>
            <div class="tw:font-bold tw:mb-2" style="font-size:20px;color:var(--hr-text);">Chưa có nhân sự phù hợp bộ lọc</div>
            <div class="hr-section-subtitle tw:mb-4">Hãy nới điều kiện lọc hoặc thêm nhân viên mới để bắt đầu quản lý.</div>
            <x-ui.button href="{{ route('hr.employees.create') }}" variant="primary" size="none" class="hr-btn hr-btn-primary tw:px-4 tw:py-[6px] tw:text-[16px]/[24px]">
                <i class="bi bi-plus-circle me-1"></i> Thêm nhân viên
            </x-ui.button>
        </div>
    @endforelse

    @if($inactiveCards->count())
        <div class="hr-department-card tw:mb-6">
            <div class="hr-department-header">
                <div>
                    <div class="hr-section-title">Đã ngừng hợp tác</div>
                    <div class="hr-section-subtitle">Toàn bộ nhân sự không còn hoạt động được gom về một khu cuối cùng</div>
                </div>
                <div class="tw:flex flex-wrap tw:gap-2 tw:justify-end">
                    <span class="hr-badge hr-badge-red">
                        <i class="bi bi-person-x"></i> {{ $inactiveCards->count() }} người
                    </span>
                </div>
            </div>

            <div class="hr-members-grid">
                @foreach($inactiveCards as $card)
                    @include('hr.employees.partials.person-card', ['card' => $card])
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
