@extends('layouts.app')

@section('title', 'Lịch biên tập nội dung')

@section('content')
@use('Illuminate\Support\Str')

{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="container-fluid tw:px-6 content-calendar-page tw:py-4">

    {{-- HEADER + CONTROLS --}}
    <div class="cc-head tw:mb-4 tw:mt-4">
        <div class="d-flex flex-wrap tw:justify-between tw:items-start tw:gap-4">
            <div>
                <h3 class="tw:mb-1 cc-head-title">Lịch biên tập nội dung</h3>
                <p class="tw:mb-0 cc-head-sub">Quản lý kế hoạch nội dung marketing theo ngày</p>
            </div>

            <div class="d-flex tw:items-center tw:gap-2 flex-wrap tw:justify-end">
                <div class="btn-group cc-view-switch" role="group" aria-label="View switch">
                    <x-ui.button variant="outline-secondary" type="button" class="cc-view-btn active" data-cc-view="list">
                        <i class="bi bi-list-ul"></i> Danh sách
                    </x-ui.button>
                    <x-ui.button variant="outline-secondary" type="button" class="cc-view-btn" data-cc-view="calendar">
                        <i class="bi bi-calendar3"></i> Lịch
                    </x-ui.button>
                    <x-ui.button variant="outline-secondary" type="button" class="cc-view-btn" data-cc-view="kanban">
                        <i class="bi bi-columns-gap"></i> Kanban
                    </x-ui.button>
                </div>

                <x-ui.button variant="none" size="none" class="btn-ego" data-bs-toggle="modal" data-bs-target="#contentCreateModal">
                    <i class="bi bi-plus-circle"></i> Thêm nội dung
                </x-ui.button>
            </div>
        </div>

        {{-- STATS + FILTER BAR --}}
        <div class="cc-toolbar tw:mt-4">
            <div class="d-flex flex-wrap tw:items-center tw:justify-between tw:gap-2">
                <div class="cc-stats d-flex flex-wrap tw:gap-2">
                    <span class="cc-chip">
                        <span class="cc-chip-dot"></span> Tổng: <strong id="ccCountAll">{{ $countAll }}</strong>
                    </span>
                    <span class="cc-chip">
                        <span class="cc-chip-dot cc-dot-draft"></span> Nháp: <strong id="ccCountDraft">{{ $countDraft }}</strong>
                    </span>
                    <span class="cc-chip">
                        <span class="cc-chip-dot cc-dot-scheduled"></span> Lên lịch: <strong id="ccCountScheduled">{{ $countSch }}</strong>
                    </span>
                    <span class="cc-chip">
                        <span class="cc-chip-dot cc-dot-posted"></span> Đã đăng: <strong id="ccCountPosted">{{ $countPost }}</strong>
                    </span>
                </div>

                <div class="d-flex tw:items-center tw:gap-2 flex-wrap">
                    <span class="text-muted small d-none d-md-inline">Mẹo: dùng “Tuần này” để lọc nhanh.</span>
                </div>
            </div>

            <div class="cc-filters tw:mt-2">
                <div class="tw:row tw:g-2 tw:items-end">
                    <div class="tw:col12-12 tw:md:col12-3 tw:min-[62rem]:col12-2">
                        <label class="cc-label">Từ ngày</label>
                        <x-ui.input class="cc-input" type="date" id="ccFrom" />
                    </div>

                    <div class="tw:col12-12 tw:md:col12-3 tw:min-[62rem]:col12-2">
                        <label class="cc-label">Đến ngày</label>
                        <x-ui.input class="cc-input" type="date" id="ccTo" />
                    </div>

                    <div class="tw:col12-6 tw:md:col12-3 tw:min-[62rem]:col12-2">
                        <label class="cc-label">Nền tảng</label>
                        <x-ui.select class="cc-input" id="ccPlatform">
                            <option value="">Tất cả</option>
                            @foreach($platformOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div class="tw:col12-6 tw:md:col12-3 tw:min-[62rem]:col12-2">
                        <label class="cc-label">Trạng thái</label>
                        <x-ui.select class="cc-input" id="ccStatus">
                            <option value="">Tất cả</option>
                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div class="tw:col12-12 tw:md:col12-6 tw:min-[62rem]:col12-2">
                        <label class="cc-label">Phụ trách</label>
                        <x-ui.select class="cc-input" id="ccAssignee">
                            <option value="">Tất cả</option>
                            @foreach(($marketingUsers ?? []) as $u)
                                <option value="{{ $u->id }}"
    {{ (isset($item) && $item->assigneeUserId == $u->id) ? 'selected' : '' }}>
    {{ $u->name }}
</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div class="tw:col12-12 tw:md:col12-6 tw:min-[62rem]:col12-3">
                        <label class="cc-label">Tìm kiếm</label>
                        <div class="input-group">
                            <span class="input-group-text cc-ig">
                                <i class="bi bi-search"></i>
                            </span>
                            <x-ui.input class="cc-input" type="text" id="ccSearch" placeholder="Tiêu đề, mô tả, link, phụ trách..." />
                            <x-ui.button :border-base="false" variant="outline-secondary" size="none" class="tw:px-3 tw:py-[6px] tw:text-[16px]/[24px] tw:font-normal tw:relative tw:z-[2] cc-clear" type="button" id="ccClear" title="Xóa lọc">
                                <i class="bi bi-x-lg"></i>
                            </x-ui.button>
                        </div>
                    </div>

                    <div class="tw:col12-12 tw:min-[62rem]:col12-4">
    <label class="cc-label">Lọc nhanh</label>
    <div class="d-flex flex-wrap tw:gap-2">
        <x-ui.button :border-base="false" variant="outline-secondary" size="none" class="tw:px-3 tw:py-[6px] tw:text-[16px]/[24px] cc-btn-quick" type="button" id="ccQuickThisWeek">Tuần này</x-ui.button>
        <x-ui.button :border-base="false" variant="outline-secondary" size="none" class="tw:px-3 tw:py-[6px] tw:text-[16px]/[24px] cc-btn-quick" type="button" id="ccQuickLastWeek">Tuần trước</x-ui.button>
        <x-ui.button :border-base="false" variant="outline-secondary" size="none" class="tw:px-3 tw:py-[6px] tw:text-[16px]/[24px] cc-btn-quick" type="button" id="ccQuickThisMonth">Tháng này</x-ui.button>
        <x-ui.button :border-base="false" variant="outline-secondary" size="none" class="tw:px-3 tw:py-[6px] tw:text-[16px]/[24px] cc-btn-quick" type="button" id="ccQuickLastMonth">Tháng trước</x-ui.button>
    </div>
</div>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN LAYOUT --}}
    <div class="tw:row tw:g-3 tw:mt-4 cc-layout">
        <div class="tw:col12-12 tw:min-[62rem]:col12-9">

            {{-- DASHBOARD TUẦN --}}
            <x-ui.card class="cc-card tw:mb-4" id="ccWeeklyWrap">
                <x-ui.card-body class="tw:p-4">
                    <div class="d-flex flex-wrap tw:items-start tw:justify-between tw:gap-2">
                        <div>
                            <div class="fw-semibold" style="font-size:16px;">Dashboard tuần</div>
                            <div class="text-muted small">
                                Tự động lấy theo bộ lọc bên dưới • Tuần: <span class="badge tw:text-[#000000] tw:bg-[#f8f9fa] border" id="ccDashRange">—</span>
                            </div>
                        </div>
                        <div class="d-flex tw:items-center tw:gap-2">
                            <x-ui.button variant="outline-primary" size="sm" type="button" id="ccDashRefresh">
                                <i class="bi bi-arrow-repeat"></i> Làm mới
                            </x-ui.button>
                        </div>
                    </div>

                    <div class="tw:row tw:g-2 tw:mt-2">
                        <div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Reach</div><div class="cc-kpi-num" id="ccKpiReach">0</div></div></div>
                        <div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Views</div><div class="cc-kpi-num" id="ccKpiViews">0</div></div></div>
                        <div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Engagement</div><div class="cc-kpi-num" id="ccKpiEng">0</div></div></div>
                        <div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Leads</div><div class="cc-kpi-num" id="ccKpiLeads">0</div></div></div>
                        <div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Bài viết</div><div class="cc-kpi-num" id="ccKpiPost">0</div></div></div>
<div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Video AI</div><div class="cc-kpi-num" id="ccKpiVideoAI">0</div></div></div>
<div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Video Review</div><div class="cc-kpi-num" id="ccKpiVideoReview">0</div></div></div>
<div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Livestream</div><div class="cc-kpi-num" id="ccKpiLive">0</div></div></div>
                        <div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Bài có số liệu</div><div class="cc-kpi-num" id="ccKpiHave">0</div></div></div>
                        <div class="tw:col12-6 tw:md:col12-4 tw:min-[75rem]:col12-2"><div class="cc-kpi-tile"><div class="text-muted small">Chưa nhập</div><div class="cc-kpi-num tw:text-[#dc3545]" id="ccKpiMissing">0</div></div></div>
                    </div>

                    <div class="tw:row tw:g-2 tw:mt-2">
                        <div class="tw:col12-12 tw:min-[62rem]:col12-5">
                            <div class="cc-panel tw:h-full">
                                <div class="cc-panel-hd">
                                    <div class="fw-semibold"><i class="bi bi-trophy-fill text-warning me-1"></i> Ranking nhân viên</div>
                                    <div class="text-muted small">Top theo Leads → Reach</div>
                                </div>
                                <div class="cc-panel-bd tw:p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm tw:mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Nhân viên</th>
                                                    <th class="text-end">Reach</th>
                                                    <th class="text-end">Eng</th>
                                                    <th class="text-end">Leads</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ccRankBody">
                                                <tr><td colspan="4" class="text-muted small tw:p-4">Chưa có dữ liệu</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tw:col12-12 tw:min-[62rem]:col12-7">
                            <div class="cc-panel tw:h-full">
                                <div class="cc-panel-hd">
                                    <div class="fw-semibold"><i class="bi bi-fire tw:text-[#dc3545] me-1"></i> Top content tuần</div>
                                    <div class="text-muted small">Ưu tiên Engagement rate</div>
                                </div>
                                <div class="cc-panel-bd" id="ccTopContent">
                                    <div class="text-muted small">Chưa có dữ liệu</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.card-body>
            </x-ui.card>

            {{-- VIEWS --}}
            <div class="cc-views">

                {{-- LIST VIEW --}}
                <div class="cc-view" id="ccView-list">
                    <x-ui.card class="cc-card">
                        <x-ui.card-body class="tw:p-0">
                            <div class="table-responsive cc-table-wrap">
                                <table class="table table-hover tw:mb-0 align-middle cc-table">
                                    <thead>
                                        <tr>
                                            <th class="cc-col-date">Ngày</th>
                                            <th class="cc-col-main">Nội dung</th>
                                            <th class="cc-col-assignee">Phụ trách</th>
                                            <th class="cc-col-status">Trạng thái</th>
                                            <th class="cc-col-actions text-end">Thao tác</th>
                                        </tr>
                                    </thead>

                                    <tbody id="ccListBody">
                                    @forelse($items as $item)

                                        <tr class="cc-row"
                                            data-cc-item
                                            data-date="{{ $item->publishYmd }}"
                                            data-platform="{{ $item->platformType }}"
                                            data-status="{{ $item->status }}"
                                            data-assignee-id="{{ $item->assigneeUserId ?? '' }}"
                                            data-title="{{ $item->searchText }}"
                                        >
                                            <td class="cc-date">
                                                <div class="cc-date-wrap">
                                                    <div class="cc-date-main">{{ $item->publishDmy }}</div>
                                                    <div class="cc-date-sub">
                                                        <span class="badge cc-badge-platform">
                                                            <i class="bi {{ $item->platformIcon }}"></i>
                                                            {{ $item->platformLabel }}
                                                            @if(!empty($item->platformAccount))
                                                                <span class="cc-plat-acc">• {{ Str::limit($item->platformAccount, 22) }}</span>
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="cc-main">
                                                <div class="cc-title">{{ $item->title }}</div>

                                                <div class="cc-meta tw:mt-2">
                                                    <span class="cc-pill">
                                                        <i class="bi bi-tag"></i> {{ $item->contentType }}
                                                    </span>

                                                    <span class="cc-pill">
                                                        <i class="bi bi-bullseye"></i>
                                                        {{ $item->campaignText }}
                                                    </span>

                                                    @if($item->filesCount)
                                                        <span class="cc-pill">
                                                            <i class="bi bi-paperclip"></i> {{ $item->filesCount }} file
                                                        </span>
                                                    @endif

                                                    @if(!empty($item->description))
                                                        <span class="cc-pill cc-pill-muted">
                                                            <i class="bi bi-chat-left-text"></i>
                                                            {{ Str::limit($item->description, 70) }}
                                                        </span>
                                                    @endif
                                                </div>

                                                @if(!empty($item->link))
                                                    <div class="cc-linkbar tw:mt-2">
                                                        <span class="cc-linkhost">
                                                            <i class="bi bi-link-45deg"></i> {{ $item->linkHost ?: 'Link' }}
                                                        </span>
                                                        <x-ui.button :border-base="false" variant="outline-secondary" size="none" class="tw:px-2 tw:py-1 tw:text-[14px]/[21px] cc-mini-btn" type="button" data-cc-copy="{{ $item->link }}"
                                                                title="Copy link">
                                                            <i class="bi bi-clipboard"></i>
                                                        </x-ui.button>
                                                        <x-ui.button :border-base="false" variant="outline-secondary" size="none" class="tw:px-2 tw:py-1 tw:text-[14px]/[21px] cc-mini-btn" :href="$item->link" target="_blank" rel="noopener" title="Mở link">
                                                            <i class="bi bi-box-arrow-up-right"></i>
                                                        </x-ui.button>
                                                    </div>
                                                @endif
                                            </td>

                                            <td class="cc-assignee-cell">
                                                @if(count($item->assignees))
                                                    <div class="cc-people">
                                                        <div class="cc-avatars">
                                                            @foreach(array_slice($item->assignees, 0, 3) as $n)
                                                                <span class="cc-avatar" title="{{ $n }}">{{ $item->assigneeInitials[$n] }}</span>
                                                            @endforeach

                                                            @if(count($item->assignees) > 3)
                                                                <span class="cc-avatar cc-avatar-more" title="{{ implode(', ', $item->assignees) }}">
                                                                    +{{ count($item->assignees) - 3 }}
                                                                </span>
                                                            @endif
                                                        </div>

                                                        <div class="cc-people-text">
                                                            <div class="cc-people-main">
                                                                {{ Str::limit(implode(', ', array_slice($item->assignees, 0, 2)), 26) }}
                                                                @if(count($item->assignees) > 2)
                                                                    <span class="cc-people-more">+{{ count($item->assignees) - 2 }}</span>
                                                                @endif
                                                            </div>
                                                            <div class="cc-people-sub text-muted">Assignees</div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="text-muted">—</div>
                                                @endif
                                            </td>

                                            <td>
                                                <span class="badge bg-{{ $item->statusColor }} cc-badge-status">
                                                    {{ $item->statusLabel }}
                                                </span>
                                            </td>

                                            <td class="text-end">
                                                <div class="dropdown">
                                                    <x-ui.button :border-base="false" variant="outline-secondary" size="none" class="tw:px-3 tw:py-[6px] tw:text-[16px]/[24px] cc-menu-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-three-dots"></i>
                                                    </x-ui.button>

                                                    <ul class="dropdown-menu dropdown-menu-end cc-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('marketing.reports.content-calendar.show', $item->id) }}">
                                                                <i class="bi bi-eye"></i> Xem chi tiết
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#editModal-{{ $item->id }}">
                                                                <i class="bi bi-pencil"></i> Sửa lịch
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button class="dropdown-item jsWeeklyMetricsBtn"
        type="button"
        data-item-id="{{ $item->id }}"
        data-item-title="{{ e($item->title) }}"
        data-content-type="{{ e($item->contentType) }}">
                                                                <i class="bi bi-bar-chart"></i> Nhập số liệu tuần
                                                            </button>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST"
                                                                  action="{{ route('marketing.reports.content-calendar.destroy', $item->id) }}"
                                                                  onsubmit="return confirm('Xóa nội dung này nhé?');">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item tw:text-[#dc3545]">
                                                                    <i class="bi bi-trash"></i> Xóa
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr id="ccEmptyRow">
                                            <td colspan="5" class="tw:text-center text-muted tw:py-6">Chưa có nội dung nào</td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="cc-empty d-none" id="ccNoResult">
                                <div class="cc-empty-inner">
                                    <div class="cc-empty-icon"><i class="bi bi-inbox"></i></div>
                                    <div class="fw-semibold">Không có kết quả</div>
                                    <div class="text-muted small">Hãy thử đổi bộ lọc hoặc từ khóa tìm kiếm.</div>
                                    <x-ui.button variant="outline-secondary" class="tw:mt-4" type="button" id="ccReset2">Xóa lọc</x-ui.button>
                                </div>
                            </div>

                        </x-ui.card-body>
                    </x-ui.card>
                </div>

                {{-- CALENDAR VIEW (Agenda modern) --}}
                <div class="cc-view d-none" id="ccView-calendar">
                    <x-ui.card class="cc-card">
                        <x-ui.card-body>
                            <div class="cc-agenda">
                                @if(count($days))
                                    @foreach($days as $day)
                                        <div class="cc-day">
                                            <div class="cc-day-head">
                                                <div class="cc-day-title">{{ $day['label'] }}</div>
                                                <div class="cc-day-count">{{ count($day['items']) }} mục</div>
                                            </div>

                                            <div class="cc-day-body">
                                                @foreach($day['items'] as $item)

                                                    <div class="cc-card-item"
                                                         data-cc-item
                                                         data-date="{{ $item->publishYmd }}"
                                                         data-platform="{{ $item->platformType }}"
                                                         data-status="{{ $item->status }}"
                                                         data-assignee-id="{{ $item->assigneeUserId ?? '' }}"
                                                         data-title="{{ $item->searchText }}"
                                                    >
                                                        <div class="cc-card-item-top">
                                                            <div class="cc-card-item-title">{{ $item->title }}</div>
                                                            <span class="badge bg-{{ $item->statusColor }} cc-badge-status">
                                                                {{ $item->statusLabel }}
                                                            </span>
                                                        </div>

                                                        <div class="cc-card-item-meta">
                                                            <span class="cc-pill">
                                                                <i class="bi {{ $item->platformIcon }}"></i>
                                                                {{ $item->platformLabel }}
                                                                @if(!empty($item->platformAccount))
                                                                    <span class="cc-plat-acc">• {{ Str::limit($item->platformAccount, 18) }}</span>
                                                                @endif
                                                            </span>

                                                            <span class="cc-pill">
                                                                <i class="bi bi-tag"></i> {{ $item->contentType }}
                                                            </span>

                                                            @if(count($item->assignees))
                                                                <span class="cc-pill">
                                                                    <i class="bi bi-people"></i> {{ Str::limit(implode(', ', $item->assignees), 28) }}
                                                                </span>
                                                            @endif
                                                        </div>

                                                        @if(!empty($item->description))
                                                            <div class="cc-card-item-desc text-muted">
                                                                {{ Str::limit($item->description, 120) }}
                                                            </div>
                                                        @endif

                                                        <div class="cc-card-item-actions">
                                                            <x-ui.button variant="outline-secondary" size="sm" href="{{ route('marketing.reports.content-calendar.show', $item->id) }}"><i class="bi bi-eye"></i></x-ui.button>
                                                            <x-ui.button variant="outline-secondary" size="sm" type="button" data-bs-toggle="modal" data-bs-target="#editModal-{{ $item->id }}"><i class="bi bi-pencil"></i></x-ui.button>
                                                            <x-ui.button variant="outline-secondary" size="sm" class="jsWeeklyMetricsBtn" type="button" data-item-id="{{ $item->id }}"
        data-item-title="{{ e($item->title) }}"
        data-content-type="{{ e($item->contentType) }}"><i class="bi bi-bar-chart"></i></x-ui.button>
                                                            @if(!empty($item->link))
                                                                <x-ui.button variant="outline-secondary" size="sm" type="button" data-cc-copy="{{ $item->link }}" title="Copy link"><i class="bi bi-clipboard"></i></x-ui.button>
                                                                <x-ui.button variant="outline-secondary" size="none" class="tw:px-2 tw:py-1 tw:text-[14px]/[21px] tw:rounded-[12px] tw:font-normal" :href="$item->link" target="_blank" rel="noopener" title="Mở link"><i class="bi bi-box-arrow-up-right"></i></x-ui.button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="tw:text-center text-muted py-5">Chưa có nội dung nào</div>
                                @endif
                            </div>

                            <div class="cc-empty d-none" id="ccNoResultCal">
                                <div class="cc-empty-inner">
                                    <div class="cc-empty-icon"><i class="bi bi-inbox"></i></div>
                                    <div class="fw-semibold">Không có kết quả</div>
                                    <div class="text-muted small">Hãy thử đổi bộ lọc hoặc từ khóa tìm kiếm.</div>
                                    <x-ui.button variant="outline-secondary" class="tw:mt-4" type="button" id="ccReset3">Xóa lọc</x-ui.button>
                                </div>
                            </div>

                        </x-ui.card-body>
                    </x-ui.card>
                </div>

                {{-- KANBAN VIEW --}}
                <div class="cc-view d-none" id="ccView-kanban">
                    <div class="cc-kanban">

                        @foreach($kanbanColumns as $column)

                            <div class="cc-kanban-col">
                                <div class="cc-kanban-head">
                                    <div class="cc-kanban-title">
                                        <span class="cc-kanban-dot cc-dot-{{ $column['key'] }}"></span>
                                        {{ $column['label'] }}
                                    </div>
                                    <div class="cc-kanban-count">{{ count($column['items']) }}</div>
                                </div>

                                <div class="cc-kanban-body">
                                    @foreach($column['items'] as $item)

                                        <div class="cc-kanban-card"
                                             data-cc-item
                                             data-date="{{ $item->publishYmd }}"
                                             data-platform="{{ $item->platformType }}"
                                             data-status="{{ $item->status }}"
                                             data-assignee-id="{{ $item->assigneeUserId ?? '' }}"
                                             data-title="{{ $item->searchText }}"
                                        >
                                            <div class="cc-kanban-card-top">
                                                <div class="cc-kanban-card-title">{{ $item->title }}</div>
                                                <div class="cc-kanban-card-date">{{ $item->publishDm }}</div>
                                            </div>

                                            <div class="cc-kanban-card-meta">
                                                <span class="cc-pill">
                                                    <i class="bi {{ $item->platformIcon }}"></i> {{ $item->platformLabel }}
                                                    @if(!empty($item->platformAccount))
                                                        <span class="cc-plat-acc">• {{ Str::limit($item->platformAccount, 14) }}</span>
                                                    @endif
                                                </span>

                                                <span class="cc-pill"><i class="bi bi-tag"></i> {{ $item->contentType }}</span>

                                                @if(count($item->assignees))
                                                    <span class="cc-pill"><i class="bi bi-people"></i> {{ Str::limit(implode(', ', $item->assignees), 22) }}</span>
                                                @endif
                                            </div>

                                            @if(!empty($item->description))
                                                <div class="cc-kanban-card-desc text-muted">
                                                    {{ Str::limit($item->description, 90) }}
                                                </div>
                                            @endif

                                            <div class="cc-kanban-card-actions">
                                                <x-ui.button variant="outline-secondary" size="sm" href="{{ route('marketing.reports.content-calendar.show', $item->id) }}"><i class="bi bi-eye"></i></x-ui.button>
                                                <x-ui.button variant="outline-secondary" size="sm" type="button" data-bs-toggle="modal" data-bs-target="#editModal-{{ $item->id }}"><i class="bi bi-pencil"></i></x-ui.button>
                                               <x-ui.button variant="outline-secondary" size="sm" class="jsWeeklyMetricsBtn" type="button" data-item-id="{{ $item->id }}"
        data-item-title="{{ e($item->title) }}"
        data-content-type="{{ e($item->contentType) }}"><i class="bi bi-bar-chart"></i></x-ui.button>
                                                @if(!empty($item->link))
                                                    <x-ui.button variant="outline-secondary" size="sm" type="button" data-cc-copy="{{ $item->link }}" title="Copy link"><i class="bi bi-clipboard"></i></x-ui.button>
                                                    <x-ui.button variant="outline-secondary" size="none" class="tw:px-2 tw:py-1 tw:text-[14px]/[21px] tw:rounded-[12px] tw:font-normal" :href="$item->link" target="_blank" rel="noopener" title="Mở link"><i class="bi bi-box-arrow-up-right"></i></x-ui.button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach

                                    @if(count($column['items']) === 0)
                                        <div class="cc-kanban-empty text-muted small">Trống</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="cc-empty d-none" id="ccNoResultKanban">
                        <div class="cc-empty-inner">
                            <div class="cc-empty-icon"><i class="bi bi-inbox"></i></div>
                            <div class="fw-semibold">Không có kết quả</div>
                            <div class="text-muted small">Hãy thử đổi bộ lọc hoặc từ khóa tìm kiếm.</div>
                            <x-ui.button variant="outline-secondary" class="tw:mt-4" type="button" id="ccReset4">Xóa lọc</x-ui.button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        {{-- SIDEBAR --}}
        <div class="tw:col12-12 tw:min-[62rem]:col12-3">
            <div class="cc-side">
                <div class="cc-side-sticky">

                    <div class="cc-panel tw:h-full">
                        <div class="cc-panel-hd">
                            <div class="fw-semibold"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i> Cảnh báo</div>
                            <div class="text-muted small">Bài trong tuần nhưng chưa nhập số liệu</div>
                        </div>
                        <div class="cc-panel-bd" id="ccAlerts">
                            <div class="text-muted small">Không có cảnh báo 🎉</div>
                        </div>
                    </div>

                    <x-ui.card class="cc-card tw:mt-4">
                        <x-ui.card-body class="tw:p-4">
                            <div class="fw-semibold tw:mb-1"><i class="bi bi-lightning-charge-fill text-warning me-1"></i> Gợi ý nhanh</div>
                            <div class="text-muted small">
                                • Dùng <strong>Tuần này</strong> để xem lịch hiện tại.<br>
                                • Nhấn <strong>...</strong> ở mỗi dòng để sửa/xóa/copy link.<br>
                                • Chuyển <strong>Danh sách / Lịch / Kanban</strong> để theo dõi theo cách bạn muốn.
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>

                    <x-ui.card class="cc-card tw:mt-4">
                        <x-ui.card-body class="tw:p-4">
                            <div class="fw-semibold tw:mb-2"><i class="bi bi-info-circle-fill tw:text-[#0d6efd] me-1"></i> Quy ước trạng thái</div>
                            <div class="d-flex flex-wrap tw:gap-2">
                                <span class="badge rounded-pill tw:text-[#ffffff] tw:bg-[#6c757d]">Nháp</span>
                                <span class="badge rounded-pill tw:text-[#000000] tw:bg-[#ffc107]">Lên lịch</span>
                                <span class="badge rounded-pill tw:text-[#ffffff] tw:bg-[#198754]">Đã đăng</span>
                                <span class="badge rounded-pill tw:text-[#000000] tw:bg-[#0dcaf0]">Chờ duyệt</span>
                                <span class="badge rounded-pill tw:text-[#ffffff] tw:bg-[#0d6efd]">Đã duyệt</span>
                                <span class="badge rounded-pill tw:text-[#ffffff] tw:bg-[#dc3545]">Từ chối</span>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>

                </div>
            </div>
        </div>

    </div>

    {{-- MODAL CREATE --}}
<div class="modal fade" id="contentCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-semibold">Thêm nội dung</h5>
                <x-ui.close-button in="modal" type="button" data-bs-dismiss="modal" />
            </div>

            <form method="POST" action="{{ route('marketing.reports.content-calendar.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="modal-body">
                    <div class="tw:row tw:g-3">
                        <div class="tw:md:col12-6">
                            <x-ui.label>Ngày đăng</x-ui.label>
                            <x-ui.input class="cc-input" type="date" name="publish_date" value="{{ now()->format('Y-m-d') }}" required />
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Nền tảng</x-ui.label>
                            <div class="tw:row tw:g-2 tw:items-center" data-platform-builder>
                                <div class="tw:col12-5">
                                    <x-ui.select class="cc-input cc-platform-type" required>
                                        <option value="facebook">Facebook</option>
                                        <option value="tiktok">TikTok</option>
                                        <option value="website">Website</option>
                                        <option value="youtube">YouTube</option>
                                    </x-ui.select>
                                </div>
                                <div class="tw:col12-7">
                                    <x-ui.input class="cc-input cc-platform-account" type="text" placeholder="Tên trang/tài khoản (nếu có)" />
                                </div>
                                <input type="hidden" name="platform" class="cc-platform-final" value="facebook">
                            </div>
                            <div class="form-text">VD: facebook|EGO Solar - Page A</div>
                        </div>

                        <div class="tw:col12-12">
                            <x-ui.label>Tiêu đề</x-ui.label>
                            <x-ui.input class="cc-input" type="text" name="title" placeholder="VD: Giới thiệu inverter 6kW" required />
                        </div>

                        <div class="tw:col12-12">
                            <x-ui.label>Mô tả ngắn</x-ui.label>
                            <x-ui.input as="textarea" class="cc-input" name="description" rows="3" placeholder="Mô tả ngắn..."></x-ui.input>
                        </div>

                        <div class="tw:md:col12-6">
    <x-ui.label>Loại nội dung</x-ui.label>
    <x-ui.input as="select" class="cc-input" name="content_type" required>
        <option value="">-- Chọn loại nội dung --</option>
        <option value="Bài viết">Bài viết</option>
        <option value="Video AI">Video AI</option>
        <option value="Video Review">Video Review</option>
        <option value="Livestream">Livestream</option>
        <option value="Trend Video">Trend Video</option>
    </x-ui.input>
</div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Trạng thái</x-ui.label>
                            <x-ui.select class="cc-input" name="status" required>
                                @foreach($statusLabels as $key => $lb)
                                    <option value="{{ $key }}">{{ $lb }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Phụ trách</x-ui.label>
                            <x-ui.select class="cc-input" name="assignee_user_id">
                                <option value="">— Chọn nhân viên marketing —</option>
                                @foreach(($marketingUsers ?? []) as $u)
                                    <option value="{{ $u->id }}"
    {{ (isset($item) && $item->assigneeUserId == $u->id) ? 'selected' : '' }}>
    {{ $u->name }}
</option>
                                @endforeach
                            </x-ui.select>
                            <div class="form-text">Chọn từ danh sách để lưu đúng người (KPI/Lương).</div>

                            {{-- legacy hidden --}}
                            <input type="hidden" name="assignee" value="">
                            <input type="hidden" name="assignees[]" value="">
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Link</x-ui.label>
                            <x-ui.input class="cc-input" type="url" name="link" placeholder="https://..." />
                        </div>

                        <div class="tw:col12-12">
                            <x-ui.label>File đính kèm</x-ui.label>
                            <x-ui.input class="cc-input" type="file" name="attachment" />
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <x-ui.button variant="outline-secondary" size="none" class="tw:px-3 tw:py-[6px] tw:text-[16px]/[24px] cc-btn" type="button" data-bs-dismiss="modal">Hủy</x-ui.button>
                    <x-ui.button variant="none" size="none" class="btn-ego cc-btn" type="submit">
                        <i class="bi bi-check2-circle"></i> Tạo nội dung
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODALS EDIT + UPLOAD --}}
@foreach($items as $item)

    {{-- MODAL EDIT --}}
    <div class="modal fade" id="editModal-{{ $item->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content cc-modal">
                <div class="modal-header">
                    <h5 class="modal-title fw-semibold">Sửa lịch nội dung</h5>
                    <x-ui.close-button in="modal" type="button" data-bs-dismiss="modal" />
                </div>

                <form method="POST" action="{{ route('marketing.reports.content-calendar.update', $item->id) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="modal-body">
                        <div class="tw:row tw:g-3">
                            <div class="tw:md:col12-6">
                                <x-ui.label>Ngày đăng</x-ui.label>
                                <x-ui.input class="cc-input" type="date" name="publish_date" value="{{ $item->publishYmd }}" required />
                            </div>

                            <div class="tw:md:col12-6">
                                <x-ui.label>Nền tảng</x-ui.label>
                                <div class="tw:row tw:g-2 tw:items-center" data-platform-builder>
                                    <div class="tw:col12-5">
                                        <x-ui.select class="cc-input cc-platform-type" required>
                                            <option value="facebook" {{ $item->platformType==='facebook'?'selected':'' }}>Facebook</option>
                                            <option value="tiktok" {{ $item->platformType==='tiktok'?'selected':'' }}>TikTok</option>
                                            <option value="website" {{ $item->platformType==='website'?'selected':'' }}>Website</option>
                                            <option value="youtube" {{ $item->platformType==='youtube'?'selected':'' }}>YouTube</option>
                                        </x-ui.select>
                                    </div>
                                    <div class="tw:col12-7">
                                        <x-ui.input class="cc-input cc-platform-account" type="text" placeholder="Tên trang/tài khoản (nếu có)" value="{{ $item->platformAccount ?? '' }}" />
                                    </div>
                                    <input type="hidden" name="platform" class="cc-platform-final" value="{{ $item->platform }}">
                                </div>
                                <div class="form-text">VD: facebook|EGO Solar - Page A</div>
                            </div>

                            <div class="tw:col12-12">
                                <x-ui.label>Tiêu đề</x-ui.label>
                                <x-ui.input class="cc-input" type="text" name="title" value="{{ $item->title }}" required />
                            </div>

                            <div class="tw:col12-12">
                                <x-ui.label>Mô tả ngắn</x-ui.label>
                                <x-ui.input as="textarea" class="cc-input" name="description" rows="3" placeholder="Mô tả ngắn...">{{ $item->description ?? '' }}</x-ui.input>
                            </div>

                           <div class="tw:md:col12-6">
    <x-ui.label>Loại nội dung</x-ui.label>
    <x-ui.input as="select" class="cc-input" name="content_type" required>
        <option value="">-- Chọn loại nội dung --</option>
        <option value="Bài viết">Bài viết</option>
        <option value="Video AI">Video AI</option>
        <option value="Video Review">Video Review</option>
        <option value="Livestream">Livestream</option>
        <option value="Trend Video">Trend Video</option>
    </x-ui.input>
</div>

                            <div class="tw:md:col12-6">
                                <x-ui.label>Trạng thái</x-ui.label>
                                <x-ui.select class="cc-input" name="status" required>
                                    @foreach($statusLabels as $key => $lb)
                                        <option value="{{ $key }}" {{ $item->status===$key?'selected':'' }}>{{ $lb }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="tw:md:col12-6">
                                <x-ui.label>Phụ trách</x-ui.label>
                                <x-ui.select class="cc-input" name="assignee_user_id">
                                    <option value="">— Chọn nhân viên marketing —</option>
                                    @foreach(($marketingUsers ?? []) as $u)
                                        <option value="{{ $u->id }}" {{ (int)($item->assigneeUserId ?? 0) === (int)$u->id ? 'selected' : '' }}>
                                            {{ $u->name }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                                <div class="form-text">Chọn từ danh sách để lưu đúng người phụ trách.</div>

                                {{-- legacy hidden (nếu backend cũ còn đọc các field này) --}}
                                <input type="hidden" name="assignee" value="{{ $item->assignee ?? '' }}">
                                <input type="hidden" name="assignees[]" value="">
                            </div>

                            <div class="tw:md:col12-6">
                                <x-ui.label>Link</x-ui.label>
                                <x-ui.input class="cc-input" type="url" name="link" value="{{ $item->link ?? '' }}" placeholder="https://..." />
                            </div>

                            <div class="tw:md:col12-6">
                                <x-ui.label>File đính kèm</x-ui.label>
                                <x-ui.input class="cc-input" type="file" name="attachment" />
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <x-ui.button variant="outline-secondary" size="none" class="tw:px-3 tw:py-[6px] tw:text-[16px]/[24px] cc-btn" type="button" data-bs-dismiss="modal">Hủy</x-ui.button>
                        <x-ui.button variant="none" size="none" class="btn-ego cc-btn" type="submit"><i class="bi bi-check2-circle"></i> Lưu</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endforeach

{{-- MODAL: Nhập số liệu tuần (Like/Cmt/Share/Reach/Views/Leads) --}}
<div class="modal fade" id="weeklyMetricsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-semibold tw:mb-0">Nhập số liệu tuần</h5>
                    <div class="text-muted small" id="wmTitle">—</div>
                </div>
                <x-ui.close-button in="modal" type="button" data-bs-dismiss="modal" />
            </div>

            <div class="modal-body">
                <input type="hidden" id="wmItemId" value="">

                <div class="tw:row tw:g-3">
                    <div class="tw:md:col12-4">
                        <x-ui.label>Tuần bắt đầu (Thứ 2)</x-ui.label>
                        <x-ui.input class="cc-input" type="date" id="wmWeekStart" />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Reach</x-ui.label>
                        <x-ui.input class="cc-input" type="number" min="0" id="wmReach" value="0" />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Views</x-ui.label>
                        <x-ui.input class="cc-input" type="number" min="0" id="wmViews" value="0" />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Likes</x-ui.label>
                        <x-ui.input class="cc-input" type="number" min="0" id="wmLikes" value="0" />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Comments</x-ui.label>
                        <x-ui.input class="cc-input" type="number" min="0" id="wmComments" value="0" />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Shares</x-ui.label>
                        <x-ui.input class="cc-input" type="number" min="0" id="wmShares" value="0" />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Leads</x-ui.label>
                        <x-ui.input class="cc-input" type="number" min="0" id="wmLeads" value="0" />
                    </div>
                    <div class="tw:md:col12-4" id="wmDurationWrap" style="display:none;">
    <x-ui.label>Phút (Livestream)</x-ui.label>
    <x-ui.input class="cc-input" type="number" min="0" id="wmDuration" value="0" />
</div>
                    <div class="tw:col12-12">
                        <x-ui.label>Ghi chú</x-ui.label>
                        <x-ui.input as="textarea" class="cc-input" id="wmNote" rows="3" placeholder="Ví dụ: số liệu lấy từ Facebook Insights / TikTok Analytics..."></x-ui.input>
                    </div>

                    <div class="tw:col12-12">
                        <x-ui.alert variant="light" class="tw:border tw:border-[#dee2e6] tw:mb-0">
                            <div class="small text-muted">
                                Gợi ý: Mỗi cuối tuần nhập 1 lần cho từng bài/video. Dashboard phía trên sẽ tự tổng hợp theo tuần.
                            </div>
                        </x-ui.alert>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <x-ui.button variant="outline-secondary" data-bs-dismiss="modal">Đóng</x-ui.button>
                <x-ui.button variant="primary" type="button" id="wmSaveBtn">
                    <i class="bi bi-save"></i> Lưu số liệu tuần
                </x-ui.button>
            </div>
        </div>
    </div>
</div>


</div>
@endsection


@push('styles')
<style>
/* ==============================
   EGO Solar - Modern Content Calendar (List/Agenda/Kanban) - Updated
   ============================== */
:root{
  --ego-bg:#f3f7fb;
  --ego-card:#ffffff;
  --ego-border:rgba(15,23,42,.09);
  --ego-text:#0f172a;
  --ego-muted:rgba(15,23,42,.62);

  --ego-primary:#0ea5a6;
  --ego-primary-2:#12b3b4;
  --ego-soft:rgba(14,165,166,.12);
  --ego-soft-2:rgba(14,165,166,.18);

  --ego-shadow:0 10px 30px rgba(15,23,42,.06);
  --ego-shadow-sm:0 6px 18px rgba(15,23,42,.06);
  --ego-radius:18px;
  --ego-radius-sm:14px;

  /* FIX sticky header dưới topbar */
  --cc-topbar-h: 72px;

  /* Font giống kiểu modern, không quá đậm */
  --cc-font: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji";
}

body{
  background: var(--ego-bg);
  font-family: var(--cc-font);
  font-weight: 400;
}
/* Modal body chỉ scroll 1 lớp */
.cc-modal .modal-body{
  max-height: calc(100vh - 220px); /* chừa header + footer */
  overflow: auto;
}

/* Textarea editor auto-grow: không scroll trong textarea */
.cc-editor{
  min-height: 260px;
  resize: none;
  overflow: hidden; /* quan trọng: bỏ scrollbar trong textarea */
}

.content-calendar-page{ color: var(--ego-text); }

/* Sticky modern head */
.content-calendar-page .cc-head{
  position: sticky;
  top: calc(var(--cc-topbar-h) + 12px);
  z-index: 5;
  background: linear-gradient(180deg, rgba(243,247,251,.98), rgba(243,247,251,.86));
  border: 1px solid var(--ego-border);
  border-radius: var(--ego-radius);
  padding: 14px 16px;
  backdrop-filter: blur(8px);
  box-shadow: var(--ego-shadow-sm);
}
.cc-head-title{
  letter-spacing: .2px;
  font-weight: 650;
}
.cc-head-sub{
  color: var(--ego-muted) !important;
  font-weight: 450;
}

/* View switch */
/* Hình học của nhóm nút: Bootstrap lo phần này qua `.btn-group > .btn`. Bỏ class
   `.btn` là mất position/flex/chồng viền — đo được ở khổ 390px: nút hết giãn đều
   và mép viền tách ra. Khai lại đúng giá trị Bootstrap đang dùng. */
.cc-view-switch .cc-view-btn{
  position: relative;
  flex: 1 1 auto;
  border-radius: 14px !important;
  font-weight: 600;
  border-color: rgba(15,23,42,.12);
}
.cc-view-switch .cc-view-btn + .cc-view-btn{
  margin-left: calc(-1 * var(--bs-border-width, 1px));
}
.cc-view-switch .cc-view-btn:hover,
.cc-view-switch .cc-view-btn:focus,
.cc-view-switch .cc-view-btn.active{
  z-index: 1;
}
/* `.active` là CLASS do JS bật tắt, KHÔNG phải pseudo-class `:active`. Component
   không sinh trạng thái này, nên luật của trang phải tự lo — bỏ đi thì nút đang
   chọn mất hẳn nền đậm mà ảnh tĩnh vẫn trông bình thường ở nút không active. */
.cc-view-switch .cc-view-btn.active{
  background: rgba(15,23,42,.92);
  border-color: rgba(15,23,42,.92);
  color: #fff;
}

/* Primary button */
.btn-ego{
  background: var(--ego-primary);
  border: 1px solid rgba(0,0,0,0);
  color: #fff;
  font-weight: 650;
  border-radius: 14px;
  padding: 10px 14px;
  box-shadow: 0 12px 26px rgba(14,165,166,.18);
}
.btn-ego:hover{ background: var(--ego-primary-2); color:#fff; }

.cc-btn{
  border-radius: 14px;
  font-weight: 600;
}

/* Toolbar */
.cc-toolbar{
  border: 1px solid rgba(15,23,42,.08);
  border-radius: var(--ego-radius);
  padding: 12px;
  background: rgba(255,255,255,.78);
}

/* Chips */
.cc-chip{
  display:inline-flex;
  align-items:center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(15,23,42,.08);
  background: rgba(255,255,255,.92);
  font-weight: 600;
  color: rgba(15,23,42,.80);
}
.cc-chip strong{ font-weight: 700; }
.cc-chip-dot{
  width: 10px; height: 10px; border-radius: 999px;
  background: rgba(15,23,42,.20);
}
.cc-dot-draft{ background: rgba(100,116,139,.50); }
.cc-dot-scheduled{ background: rgba(245,158,11,.60); }
.cc-dot-posted{ background: rgba(34,197,94,.60); }

/* Filter labels */
.cc-label{
  font-size: 12px;
  letter-spacing: .35px;
  text-transform: uppercase;
  color: rgba(15,23,42,.60);
  margin-bottom: 6px;
  font-weight: 650;
}

/* Inputs */
/*
 * Viết lặp class để nâng độ cụ thể lên 0-2-0.
 *
 * Khối <style> của trang nằm TRƯỚC app.css trong tài liệu (đo được: style ở
 * ~20.7k, app.css ở ~25.4k). Khi ô nhập còn `.form-control` thì đối thủ là
 * Bootstrap trên CDN — nằm rất sớm — nên `.cc-input` thắng nhờ đứng sau. Chuyển
 * sang <x-ui.*> thì đối thủ thành utility Tailwind trong app.css: cùng 0-1-0
 * nhưng đứng SAU, và `.cc-input` thua — bo góc 14px -> 6px, viền
 * rgba(15,23,42,.10) -> #dee2e6 (đo được 25/54 phần tử lệch).
 *
 * Nhân đôi class nâng được độ cụ thể mà không cần `!important` (dự án cấm).
 */
.cc-input.cc-input{
  border-radius: 14px;
  border: 1px solid rgba(15,23,42,.10);
  font-weight: 450;
}
.cc-input.cc-input:focus{
  border-color: rgba(14,165,166,.35);
  box-shadow: 0 0 0 .25rem rgba(14,165,166,.15);
}
.cc-ig{
  border-radius: 14px 0 0 14px !important;
  border: 1px solid rgba(15,23,42,.10);
  background: #fff;
}
.cc-clear{
  border-radius: 0 14px 14px 0 !important;
  border: 1px solid rgba(15,23,42,.10);
}
.cc-filters .cc-btn-quick.active{background:rgba(15,23,42,.92);border-color:rgba(15,23,42,.92);color:#fff}
.cc-btn-quick{
  border-radius: 14px;
  font-weight: 600;
  border-color: rgba(15,23,42,.12);
}

/* Cards */
.content-calendar-page .cc-card{
  border: 1px solid var(--ego-border);
  border-radius: var(--ego-radius);
  overflow: hidden;
  background: var(--ego-card);
  box-shadow: var(--ego-shadow-sm);
}

/* Table */
.cc-table thead th{
  font-size: 12px;
  letter-spacing: .55px;
  text-transform: uppercase;
  color: rgba(15,23,42,.62);
  white-space: nowrap;
  border-bottom: 1px solid rgba(15,23,42,.08);
  padding: 14px 14px;
  background: linear-gradient(180deg, rgba(255,255,255,1), rgba(255,255,255,.92));
  font-weight: 650;
}
.cc-table tbody td{
  padding: 16px 14px;
  vertical-align: middle;
  border-top: 1px solid rgba(15,23,42,.06);
}
.cc-table.table-hover tbody tr:hover{
  background: rgba(14,165,166,.05);
}
.cc-col-date{ width: 170px; }
.cc-col-assignee{ width: 260px; }
.cc-col-status{ width: 150px; }
.cc-col-actions{ width: 90px; }
.cc-col-main{ width: auto; }

/* Date cell */
.cc-date-wrap{ display:flex; flex-direction:column; gap: 8px; }
.cc-date-main{ font-weight: 700; letter-spacing: .2px; }

/* Platform badge */
.cc-badge-platform{
  background: var(--ego-soft);
  color: #0b5f60;
  border: 1px solid var(--ego-soft-2);
  border-radius: 999px;
  padding: 6px 10px;
  font-weight: 600;
  letter-spacing: .1px;
  display:inline-flex;
  align-items:center;
  gap: 8px;
}
.cc-plat-acc{
  color: rgba(11,95,96,.88);
  font-weight: 500;
}

/* Content title + meta */
.cc-title{
  font-weight: 650;
  letter-spacing: .1px;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.cc-meta{ display:flex; flex-wrap: wrap; gap: 8px; }
.cc-pill{
  display:inline-flex;
  align-items:center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid rgba(15,23,42,.08);
  background: rgba(255,255,255,.92);
  font-weight: 550;
  font-size: 12px;
  color: rgba(15,23,42,.78);
}
.cc-pill i{ opacity: .8; }
.cc-pill-muted{
  color: rgba(15,23,42,.62);
  background: rgba(15,23,42,.03);
}

/* Link bar */
.cc-linkbar{
  display:flex;
  align-items:center;
  gap: 8px;
  flex-wrap: wrap;
}
.cc-linkhost{
  display:inline-flex;
  align-items:center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(14,165,166,.10);
  border: 1px solid rgba(14,165,166,.18);
  color: #0b5f60;
  font-weight: 600;
  font-size: 12px;
}
.cc-mini-btn{
  border-radius: 12px;
  border-color: rgba(15,23,42,.12);
  font-weight: 600;
}

/* People */
.cc-people{
  display:flex;
  align-items:center;
  gap: 10px;
  min-width: 0;
}
.cc-avatars{
  display:flex;
  align-items:center;
}
.cc-avatar{
  width: 34px; height: 34px;
  border-radius: 999px;
  display:flex;
  align-items:center;
  justify-content:center;
  background: rgba(15,23,42,.06);
  border: 1px solid rgba(15,23,42,.08);
  color: rgba(15,23,42,.78);
  font-weight: 650;
  margin-left: -8px;
}
.cc-avatar:first-child{ margin-left: 0; }
.cc-avatar-more{
  background: rgba(14,165,166,.10);
  border-color: rgba(14,165,166,.18);
  color: #0b5f60;
}
.cc-people-text{ min-width:0; }
.cc-people-main{
  font-weight: 650;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cc-people-more{
  margin-left: 6px;
  font-size: 12px;
  font-weight: 650;
  color: rgba(15,23,42,.60);
}
.cc-people-sub{ font-size: 12px; }

/* Status badge */
.cc-badge-status{
  border-radius: 999px;
  padding: 7px 10px;
  font-weight: 650;
  font-size: 12px;
  letter-spacing: .35px;
  border: 1px solid rgba(15,23,42,.08);
}

/* Softer bootstrap badge palette */
.badge.bg-secondary{ background: rgba(100,116,139,.14) !important; color:#334155 !important; }
.badge.bg-warning{ background: rgba(245,158,11,.16) !important; color:#92400e !important; }
.badge.bg-success{ background: rgba(34,197,94,.14) !important; color:#166534 !important; }
.badge.bg-info{ background: rgba(59,130,246,.14) !important; color:#1d4ed8 !important; }
.badge.bg-primary{ background: rgba(99,102,241,.14) !important; color:#4338ca !important; }
.badge.bg-danger{ background: rgba(239,68,68,.14) !important; color:#991b1b !important; }

/* Menu */
.cc-menu-btn{
  border-radius: 14px;
  border-color: rgba(15,23,42,.12);
  font-weight: 600;
}
.cc-menu{
  border-radius: 14px;
  border: 1px solid rgba(15,23,42,.10);
  box-shadow: var(--ego-shadow-sm);
  padding: 8px;
}
.cc-menu .dropdown-item{
  border-radius: 12px;
  font-weight: 600;
  padding: 10px 12px;
  display:flex;
  gap: 10px;
  align-items:center;
}
.cc-menu .dropdown-item:hover{
  background: rgba(14,165,166,.10);
}

/* Agenda */
.cc-agenda{ display:flex; flex-direction:column; gap: 12px; }
.cc-day{
  border: 1px solid rgba(15,23,42,.08);
  border-radius: var(--ego-radius);
  background: rgba(255,255,255,.86);
  overflow:hidden;
}
.cc-day-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding: 12px 14px;
  background: rgba(15,23,42,.02);
  border-bottom: 1px solid rgba(15,23,42,.06);
}
.cc-day-title{ font-weight: 650; }
.cc-day-count{ font-weight: 550; color: rgba(15,23,42,.55); }
.cc-day-body{
  padding: 12px;
  display:grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
@media (max-width: 991px){
  .cc-day-body{ grid-template-columns: 1fr; }
}

.cc-card-item{
  border: 1px solid rgba(15,23,42,.08);
  border-radius: 16px;
  background: #fff;
  padding: 12px;
  box-shadow: 0 8px 20px rgba(15,23,42,.04);
}
.cc-card-item-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 10px;
}
.cc-card-item-title{
  font-weight: 650;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.cc-card-item-meta{
  display:flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}
.cc-card-item-desc{ margin-top: 8px; font-size: 13px; }
.cc-card-item-actions{
  margin-top: 10px;
  display:flex;
  gap: 8px;
  flex-wrap: wrap;
}


/* Kanban */
.cc-kanban{
  display:grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
@media (max-width: 1199px){
  .cc-kanban{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767px){
  .cc-kanban{ grid-template-columns: 1fr; }
}

.cc-kanban-col{
  border: 1px solid rgba(15,23,42,.08);
  border-radius: var(--ego-radius);
  background: rgba(255,255,255,.86);
  overflow:hidden;
  display:flex;
  flex-direction:column;
  min-height: 240px;
}
.cc-kanban-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding: 12px 14px;
  background: rgba(15,23,42,.02);
  border-bottom: 1px solid rgba(15,23,42,.06);
}
.cc-kanban-title{ font-weight: 650; display:flex; align-items:center; gap: 10px; }
.cc-kanban-count{ font-weight: 550; color: rgba(15,23,42,.55); }

.cc-kanban-dot{
  width: 10px; height: 10px; border-radius: 999px; background: rgba(15,23,42,.20);
}
.cc-dot-draft{ background: rgba(100,116,139,.50); }
.cc-dot-scheduled{ background: rgba(245,158,11,.60); }
.cc-dot-posted{ background: rgba(34,197,94,.60); }
.cc-dot-submitted{ background: rgba(59,130,246,.60); }
.cc-dot-approved{ background: rgba(99,102,241,.60); }
.cc-dot-rejected{ background: rgba(239,68,68,.60); }

.cc-kanban-body{
  padding: 12px;
  display:flex;
  flex-direction:column;
  gap: 10px;
}
.cc-kanban-card{
  border: 1px solid rgba(15,23,42,.08);
  border-radius: 16px;
  background: #fff;
  padding: 12px;
  box-shadow: 0 8px 20px rgba(15,23,42,.04);
}
.cc-kanban-card-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap: 10px;
}
.cc-kanban-card-title{
  font-weight: 650;
  line-height: 1.3;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.cc-kanban-card-date{
  font-weight: 600;
  color: rgba(15,23,42,.55);
  white-space: nowrap;
}
.cc-kanban-card-meta{ margin-top: 10px; display:flex; flex-wrap:wrap; gap: 8px; }
.cc-kanban-card-desc{ margin-top: 8px; font-size: 13px; }
.cc-kanban-card-actions{ margin-top: 10px; display:flex; gap: 8px; flex-wrap: wrap; }
/* Bám thẻ đã render chứ không bám `.btn`: các nút này đã chuyển sang x-ui.button nên
   không còn class `.btn`, và luật cũ lặng lẽ hết tác dụng — 4/5 nút tụt về bo 4px.
   Không lộ ra lúc đo vì thẻ kanban chỉ render khi CÓ dữ liệu. */
.cc-kanban-card-actions > a,
.cc-kanban-card-actions > button{ border-radius: 12px; }
.cc-kanban-empty{
  border: 1px dashed rgba(15,23,42,.18);
  border-radius: 16px;
  padding: 18px 12px;
  text-align:center;
  background: rgba(15,23,42,.02);
}

/* Empty state */
.cc-empty{
  padding: 46px 18px;
  text-align: center;
}
.cc-empty-inner{
  max-width: 420px;
  margin: 0 auto;
}
.cc-empty-icon{
  width: 56px; height: 56px;
  margin: 0 auto 12px;
  display:flex; align-items:center; justify-content:center;
  border-radius: 18px;
  background: rgba(15,23,42,.04);
  border: 1px solid rgba(15,23,42,.08);
  font-size: 22px;
  color: rgba(15,23,42,.55);
}

/* Modal */
.cc-modal{ border-radius: 18px; overflow: hidden; }
.modal-header{ border-bottom: 1px solid rgba(15,23,42,.08); }
.modal-footer{ border-top: 1px solid rgba(15,23,42,.08); }
.cc-editor{
  border-radius: 16px;
  border: 1px solid rgba(15,23,42,.10);
  padding: 12px;
  line-height: 1.6;
  font-weight: 450;
}
.cc-editor:focus{
  border-color: rgba(14,165,166,.35);
  box-shadow: 0 0 0 .25rem rgba(14,165,166,.15);
}

/* Responsive */
@media (max-width: 991px){
  .cc-col-assignee{ width: 220px; }
  .cc-col-actions{ width: 70px; }
}

/* ===== Assignee chips (Create/Edit) ===== */
.cc-assignee-box{
  border: 1px solid rgba(15,23,42,.10);
  border-radius: 16px;
  padding: 12px;
  background: rgba(255,255,255,.78);
}
.cc-assignee-chips{
  display:flex;
  flex-wrap:wrap;
  gap: 10px;
}
.cc-assignee-chip{
  display:inline-flex;
  align-items:center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 999px;
  background: #fff;
  border: 1px solid rgba(15,23,42,.10);
  box-shadow: 0 10px 18px rgba(15, 23, 42, .04);
  font-weight: 550;
  font-size: 12px;
  color: rgba(15,23,42,.85);
}
.cc-assignee-chip .cc-x{
  border: none;
  width: 26px; height: 26px;
  border-radius: 999px;
  display:grid;
  place-items:center;
  background: rgba(239,68,68,.10);
  color: #991b1b;
  font-weight: 700;
  line-height: 1;
}
.cc-assignee-chip .cc-x:hover{ filter: brightness(.98); }

        .cc-kpi{
            background: linear-gradient(180deg, rgba(255,255,255,.9), rgba(255,255,255,.75));
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 14px;
            padding: 10px 12px;
            box-shadow: 0 10px 22px rgba(0,0,0,.06);
        }
        .cc-kpi-label{ font-size: 12px; color: rgba(0,0,0,.55); font-weight: 800; }
        .cc-kpi-val{ font-size: 18px; font-weight: 950; letter-spacing: -.02em; }

        .cc-panel{
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 16px;
            overflow:hidden;
            background: rgba(255,255,255,.92);
            box-shadow: 0 12px 26px rgba(0,0,0,.07);
        }
        .cc-panel-hd{
            padding: 10px 12px;
            border-bottom: 1px solid rgba(0,0,0,.06);
            background: rgba(255,255,255,.86);
            backdrop-filter: blur(10px);
            display:flex;
            justify-content:space-between;
            align-items:flex-end;
            gap:10px;
        }
        .cc-panel-bd{ padding: 10px 12px; }

.cc-kpi-tile{
  background: linear-gradient(180deg, rgba(255,255,255,.92), rgba(255,255,255,1));
  border: 1px solid var(--ego-border);
  border-radius: var(--ego-radius-sm);
  padding: 14px 14px;
  box-shadow: var(--ego-shadow-sm);
  height: 100%;
}
.cc-kpi-num{
  font-size: 18px;
  font-weight: 800;
  letter-spacing: .2px;
  margin-top: 2px;
}

</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const $  = (s, root=document) => root.querySelector(s);
  const $$ = (s, root=document) => Array.from(root.querySelectorAll(s));

  // =========================
  // Helpers Date
  // =========================
  function toYMD(d){
    const x = (d instanceof Date) ? d : new Date(d);
    if(isNaN(x.getTime())) return '';
    const y = x.getFullYear();
    const m = String(x.getMonth()+1).padStart(2,'0');
    const da = String(x.getDate()).padStart(2,'0');
    return `${y}-${m}-${da}`;
  }

  function toMonday(dateLike){
    const x = (dateLike instanceof Date) ? new Date(dateLike) : new Date(String(dateLike));
    if (isNaN(x.getTime())) return null;
    const day = x.getDay(); // 0 CN, 1 T2...
    const diff = (day === 0 ? -6 : 1 - day);
    x.setDate(x.getDate() + diff);
    return toYMD(x);
  }

  function inRange(dateStr, fromStr, toStr){
    if(!dateStr) return false;
    if(fromStr && dateStr < fromStr) return false;
    if(toStr && dateStr > toStr) return false;
    return true;
  }

  // =========================
  // Auto grow textarea (cc-editor)
  // =========================
  function autoGrowTextarea(el){
    if(!el) return;
    el.style.height = 'auto';
    const cap = Math.floor(window.innerHeight * 0.45);
    el.style.height = Math.min(el.scrollHeight + 2, cap) + 'px';
  }

  document.addEventListener('input', (e) => {
    if(e.target && e.target.classList.contains('cc-editor')){
      autoGrowTextarea(e.target);
    }
  });

  document.addEventListener('shown.bs.modal', (e) => {
    const root = e.target;
    if(!root) return;
    root.querySelectorAll('.cc-editor').forEach(autoGrowTextarea);
  });

  // =========================
  // Elements
  // =========================
  const views = {
    list: $('#ccView-list'),
    calendar: $('#ccView-calendar'),
    kanban: $('#ccView-kanban'),
  };
  const viewBtns = $$('.cc-view-btn');

  const fromEl     = $('#ccFrom');
  const toEl       = $('#ccTo');
  const platformEl = $('#ccPlatform');
  const statusEl   = $('#ccStatus');
  const assigneeEl = $('#ccAssignee');
  const searchEl   = $('#ccSearch');

  const clearBtn = $('#ccClear');
  const reset2   = $('#ccReset2');
  const reset3   = $('#ccReset3');
  const reset4   = $('#ccReset4');

  const quickThisWeek  = $('#ccQuickThisWeek');
  const quickLastWeek  = $('#ccQuickLastWeek');
  const quickThisMonth = $('#ccQuickThisMonth');
  const quickLastMonth = $('#ccQuickLastMonth');

  const emptyList = $('#ccNoResult');
  const emptyCal  = $('#ccNoResultCal');
  const emptyKan  = $('#ccNoResultKanban');

  let activeView = 'list';

  // =========================
  // View switch
  // =========================
  function setView(v){
    activeView = v;
    Object.keys(views).forEach(k => {
      if(!views[k]) return;
      views[k].classList.toggle('d-none', k !== v);
    });
    viewBtns.forEach(b => b.classList.toggle('active', b.dataset.ccView === v));
    applyFilters();
  }

  viewBtns.forEach(btn => {
    btn.addEventListener('click', () => setView(btn.dataset.ccView));
  });

  // =========================
  // Filters
  // =========================
  function applyFilters(){
    const fromV = (fromEl?.value || '').trim();
    const toV   = (toEl?.value || '').trim();
    const platV = (platformEl?.value || '').trim().toLowerCase();
    const statV = (statusEl?.value || '').trim().toLowerCase();
    const asgV  = (assigneeEl?.value || '').trim();
    const q     = (searchEl?.value || '').trim().toLowerCase();

    const root  = views[activeView];
    if(!root) return;

    const items = $$('[data-cc-item]', root);
    let shown = 0;

    items.forEach(el => {
      const d = (el.dataset.date || '').trim();
      const p = (el.dataset.platform || '').trim().toLowerCase();
      const s = (el.dataset.status || '').trim().toLowerCase();
      const t = (el.dataset.title || '').trim().toLowerCase();
      const a = (el.dataset.assigneeId || '').trim();

      const ok =
        inRange(d, fromV, toV) &&
        (!platV || p === platV) &&
        (!statV || s === statV) &&
        (!asgV  || a === asgV) &&
        (!q     || t.includes(q));

      el.classList.toggle('d-none', !ok);
      if(ok) shown++;
    });

    // Update header chips dựa trên list view
    try{
      const listRows = $$('tr.cc-row[data-cc-item]', views.list);
      let all=0, d=0, sch=0, post=0;

      listRows.forEach(el=>{
        const dte = (el.dataset.date || '').trim();
        const p = (el.dataset.platform || '').trim().toLowerCase();
        const s = (el.dataset.status || '').trim().toLowerCase();
        const t = (el.dataset.title || '').trim().toLowerCase();
        const a = (el.dataset.assigneeId || '').trim();

        const ok =
          inRange(dte, fromV, toV) &&
          (!platV || p === platV) &&
          (!statV || s === statV) &&
          (!asgV  || a === asgV) &&
          (!q     || t.includes(q));

        if(!ok) return;
        all++;
        if(s === 'draft') d++;
        if(s === 'scheduled') sch++;
        if(s === 'posted') post++;
      });

      const set = (id, val) => { const x = document.getElementById(id); if(x) x.textContent = String(val); };
      set('ccCountAll', all);
      set('ccCountDraft', d);
      set('ccCountScheduled', sch);
      set('ccCountPosted', post);
    }catch(e){}

    if(activeView === 'list'){
      emptyList && emptyList.classList.toggle('d-none', shown !== 0);
    }else if(activeView === 'calendar'){
      emptyCal && emptyCal.classList.toggle('d-none', shown !== 0);
    }else{
      emptyKan && emptyKan.classList.toggle('d-none', shown !== 0);
    }
  }

  function clearFilters(){
    if(fromEl) fromEl.value = '';
    if(toEl) toEl.value = '';
    if(platformEl) platformEl.value = '';
    if(statusEl) statusEl.value = '';
    if(assigneeEl) assigneeEl.value = '';
    if(searchEl) searchEl.value = '';
    applyFilters();
    refreshDashboard(); // ✅ clear lọc thì refresh luôn dashboard
  }

  [fromEl, toEl, platformEl, statusEl, assigneeEl].forEach(el => el && el.addEventListener('change', () => {
    applyFilters();
    refreshDashboard();
  }));
  searchEl && searchEl.addEventListener('input', () => {
    applyFilters();
    // search gõ liên tục, delay nhẹ
    setTimeout(refreshDashboard, 250);
  });

  clearBtn && clearBtn.addEventListener('click', clearFilters);
  reset2 && reset2.addEventListener('click', clearFilters);
  reset3 && reset3.addEventListener('click', clearFilters);
  reset4 && reset4.addEventListener('click', clearFilters);

  // =========================
  // Quick ranges
  // =========================
  function setRange(fromDate, toDate){
    if(fromEl) fromEl.value = toYMD(fromDate);
    if(toEl)   toEl.value   = toYMD(toDate);
    applyFilters();
    refreshDashboard();
  }

  function rangeThisWeek(){
    const now = new Date();
    const day = now.getDay();
    const diffToMon = (day === 0 ? -6 : 1 - day);
    const mon = new Date(now);
    mon.setDate(now.getDate() + diffToMon);
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);
    setRange(mon, sun);
  }

  function rangeLastWeek(){
    const now = new Date();
    const day = now.getDay();
    const diffToMon = (day === 0 ? -6 : 1 - day);
    const mon = new Date(now);
    mon.setDate(now.getDate() + diffToMon - 7);
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);
    setRange(mon, sun);
  }

  function rangeThisMonth(){
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth(), 1);
    const last = new Date(now.getFullYear(), now.getMonth()+1, 0);
    setRange(first, last);
  }

  function rangeLastMonth(){
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth()-1, 1);
    const last = new Date(now.getFullYear(), now.getMonth(), 0);
    setRange(first, last);
  }

  // Đánh dấu nút lọc nhanh đang chọn. Trước 2026-09-03 nhóm này KHÔNG có trạng thái
  // nào: bấm xong không biết mình đang lọc theo mốc nào.
  const quickBtns = [quickThisWeek, quickLastWeek, quickThisMonth, quickLastMonth].filter(Boolean);
  const markQuickActive = (btn) => quickBtns.forEach(b => b.classList.toggle('active', b === btn));

  const bindQuick = (btn, fn) => btn && btn.addEventListener('click', () => { fn(); markQuickActive(btn); });

  bindQuick(quickThisWeek,  rangeThisWeek);
  bindQuick(quickLastWeek,  rangeLastWeek);
  bindQuick(quickThisMonth, rangeThisMonth);
  bindQuick(quickLastMonth, rangeLastMonth);

  // =========================
  // Copy link
  // =========================
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-cc-copy]');
    if(!btn) return;

    const text = btn.getAttribute('data-cc-copy') || '';
    if(!text) return;

    try{
      await navigator.clipboard.writeText(text);
      btn.innerHTML = '<i class="bi bi-check2"></i>';
      setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 900);
    }catch(err){
      const ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
  });

  // =========================
  // Platform builder: type + account -> hidden platform
  // =========================
  function buildPlatform(type, account){
    type = (type || '').trim().toLowerCase();
    account = (account || '').trim();
    if(!type) return '';
    return account ? `${type}|${account}` : type;
  }

  function parsePlatform(raw){
    raw = (raw || '').trim();
    if(!raw) return {type:'facebook', account:''};
    if(raw.includes('|')){
      const parts = raw.split('|');
      return {type:(parts[0]||'').trim().toLowerCase(), account:(parts.slice(1).join('|')||'').trim()};
    }
    return {type:raw.toLowerCase(), account:''};
  }

  $$('[data-platform-builder]').forEach(wrap => {
    const sel = $('.cc-platform-type', wrap);
    const acc = $('.cc-platform-account', wrap);
    const hid = $('.cc-platform-final', wrap);
    if(!hid) return;

    const init = parsePlatform(hid.value || '');
    if(sel && init.type) sel.value = init.type;
    if(acc) acc.value = init.account || '';

    const sync = () => { hid.value = buildPlatform(sel ? sel.value : '', acc ? acc.value : ''); };
    sel && sel.addEventListener('change', sync);
    acc && acc.addEventListener('input', sync);
    sync();
  });

  // =========================
  // Weekly Metrics + Dashboard
  // =========================
  const CSRF = '{{ csrf_token() }}';
  const fmt = (n) => (new Intl.NumberFormat('vi-VN')).format(Number(n||0));
  const escapeHtml = (s) => String(s||'').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));

  // ✅ weekStart: nếu user đang lọc cả tháng thì dashboard lấy tuần hiện tại (đỡ bị “—”)
 function getWeekStart(){
  const from = fromEl?.value || '';
  const to   = toEl?.value || '';

  if(from && to){
    const a = new Date(from), b = new Date(to);
    const diffDays = Math.round((b - a) / 86400000);

    // Nếu đang lọc đúng 1 tuần
    if(diffDays <= 6) return toMonday(from);

    // Nếu đang lọc cả tháng -> lấy tuần của ngày "Đến ngày"
    return toMonday(to);
  }

  if(from) return toMonday(from);
  return toMonday(new Date());
}

  function getFilters(){
    // platform dropdown gửi "facebook", backend phải xử lý SUBSTRING_INDEX (anh đã fix controller)
    return {
      platform: platformEl?.value || '',
      status: statusEl?.value || '',
      assignee_user_id: assigneeEl?.value || '',
    };
  }

  // ===== Weekly Metrics Modal =====
  const modalEl = document.getElementById('weeklyMetricsModal');
  const modal = (modalEl && window.bootstrap && bootstrap.Modal) ? new bootstrap.Modal(modalEl) : null;

  async function apiGetMetrics(itemId, weekStart){
    const url = `{{ url('/marketing/reports/content-calendar') }}/${itemId}/weekly-metrics?week_start=${encodeURIComponent(weekStart)}`;
    const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
    return await res.json();
  }

  async function apiSaveMetrics(itemId, payload){
    const url = `{{ url('/marketing/reports/content-calendar') }}/${itemId}/weekly-metrics`;
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': CSRF,
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    });
    return await res.json();
  }

  document.addEventListener('click', async function(e){
    const btn = e.target.closest('.jsWeeklyMetricsBtn');
    if(!btn) return;

    const itemId = btn.getAttribute('data-item-id');
    const title  = btn.getAttribute('data-item-title') || '';
    const ctype = (btn.getAttribute('data-content-type') || '').trim();
const isLive = (ctype.toLowerCase() === 'livestream');

const wrap = document.getElementById('wmDurationWrap');
if (wrap) wrap.style.display = isLive ? 'block' : 'none';

const durEl = document.getElementById('wmDuration');
if (durEl) durEl.value = 0; // reset mặc định
    $('#wmItemId').value = itemId;
    $('#wmTitle').textContent = title;

    const ws = getWeekStart();
    $('#wmWeekStart').value = ws;

    // reset
    ['Reach','Views','Likes','Comments','Shares','Leads'].forEach(k => {
      const el = document.getElementById('wm'+k);
      if(el) el.value = 0;
    });
    $('#wmNote').value = '';

    try{
      const json = await apiGetMetrics(itemId, ws);
      const row = json?.data;
      if(row){
        $('#wmReach').value    = row.reach ?? 0;
        $('#wmViews').value    = row.views ?? 0;
        $('#wmLikes').value    = row.likes ?? 0;
        $('#wmComments').value = row.comments ?? 0;
        $('#wmShares').value   = row.shares ?? 0;
        $('#wmLeads').value    = row.leads ?? 0;
        $('#wmNote').value     = row.note ?? '';
        const durEl = document.getElementById('wmDuration');
if (durEl) durEl.value = row.duration_min ?? 0;
      }
    }catch(err){
      console.warn(err);
    }

    modal?.show();
  });

  $('#wmSaveBtn')?.addEventListener('click', async function(){
    const itemId = $('#wmItemId').value;
    const payload = {
  week_start: $('#wmWeekStart').value,
  reach: parseInt($('#wmReach').value || '0', 10),
  views: parseInt($('#wmViews').value || '0', 10),
  likes: parseInt($('#wmLikes').value || '0', 10),
  comments: parseInt($('#wmComments').value || '0', 10),
  shares: parseInt($('#wmShares').value || '0', 10),
  leads: parseInt($('#wmLeads').value || '0', 10),
  note: $('#wmNote').value || '',
  duration_min: parseInt((document.getElementById('wmDuration')?.value || '0'), 10),
};

    const json = await apiSaveMetrics(itemId, payload);
    if(json?.ok){
      modal?.hide();
      await refreshDashboard();
    }else{
      alert('❌ Lỗi lưu số liệu tuần. Mở Console để xem chi tiết.');
      console.log(json);
    }
  });

  // ===== Dashboard API =====
async function dashboardApi(){
  const ws = getWeekStart(); // có thể giữ để hiển thị, không quan trọng nữa
  const f = getFilters();

  const qs = new URLSearchParams({
    week_start: ws || '',

    // 👇 THÊM 2 cái này để backend lọc theo tháng/tuần đang chọn
    from_date: fromEl?.value || '',
    to_date:   toEl?.value || '',

    platform: f.platform || 'all',
    status: f.status || 'all',
    assignee_user_id: f.assignee_user_id || '',
  }).toString();

  const url = `{{ url('/marketing/reports/content-calendar/weekly-dashboard') }}?${qs}`;
  const res = await fetch(url, { headers: { 'Accept': 'application/json' }});
  return await res.json();
}

  function setText(id, val){
    const el = document.getElementById(id);
    if(el) el.textContent = String(val);
  }

  function renderRanking(list){
    const body = document.getElementById('ccRankBody');
    if(!body) return;
    if(!list || !list.length){
      body.innerHTML = `<tr><td colspan="4" class="text-muted small tw:p-4">Chưa có dữ liệu</td></tr>`;
      return;
    }
    const map = @json(($marketingUsers ?? collect())->keyBy('id')->map->name);
    body.innerHTML = list.map(r=>{
      const name = map[String(r.assignee_user_id)] || (r.assignee_user_id ? ('#'+r.assignee_user_id) : 'Chưa gán');
      const eng = (Number(r.likes||0)+Number(r.comments||0)+Number(r.shares||0));
      return `<tr>
        <td>${escapeHtml(name)}</td>
        <td class="text-end">${fmt(r.reach)}</td>
        <td class="text-end">${fmt(eng)}</td>
        <td class="text-end fw-semibold">${fmt(r.leads)}</td>
      </tr>`;
    }).join('');
  }

  function renderTopContent(list){
    const wrap = document.getElementById('ccTopContent');
    if(!wrap) return;
    if(!list || !list.length){
      wrap.innerHTML = `<div class="text-muted small">Chưa có dữ liệu</div>`;
      return;
    }
    wrap.innerHTML = list.map(r=>{
      const denom = Math.max(1, Math.max(Number(r.reach||0), Number(r.views||0)));
      const rate = (Number(r.engagement||0) / denom * 100).toFixed(2);
      return `<div class="d-flex tw:justify-between tw:items-start tw:gap-2 tw:py-2 border-bottom">
        <div style="min-width:0">
          <div class="fw-semibold tw:truncate">${escapeHtml(r.title)}</div>
          <div class="text-muted small">${escapeHtml(r.platform || '')} • ${escapeHtml(r.publish_date || '')} • ER ${rate}%</div>
        </div>
        <div class="text-end">
          <div class="fw-semibold">${fmt(r.engagement)}</div>
          <div class="text-muted small">Reach ${fmt(r.reach)} • Leads ${fmt(r.leads)}</div>
        </div>
      </div>`;
    }).join('');
  }

  function renderAlerts(list){
    const wrap = document.getElementById('ccAlerts');
    if(!wrap) return;
    if(!list || !list.length){
      wrap.innerHTML = `<div class="text-muted small">Không có cảnh báo 🎉</div>`;
      return;
    }
    const map = @json(($marketingUsers ?? collect())->keyBy('id')->map->name);
    wrap.innerHTML = list.map(r=>{
      const name = map[String(r.assignee_user_id)] || (r.assignee_user_id ? ('#'+r.assignee_user_id) : 'Chưa gán');
      return `<div class="d-flex tw:items-start tw:gap-2 tw:py-2 border-bottom">
        <div class="text-warning"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div style="min-width:0">
          <div class="fw-semibold tw:truncate">${escapeHtml(r.title)}</div>
          <div class="text-muted small">${escapeHtml(r.publish_date)} • ${escapeHtml(name)} • ${escapeHtml(r.platform || '')}</div>
        </div>
      </div>`;
    }).join('');
  }

  async function refreshDashboard(){
    try{
      const json = await dashboardApi();
      if(!json || !json.ok){
        // nếu API lỗi => hiện "—" để anh biết
        setText('ccDashRange', '—');
        return;
      }

      const totals = json.totals || {};
      const eng = Number(json.engagement || 0);

      setText('ccDashRange', `${json.week_start} → ${json.week_end}`);
      setText('ccKpiPost', fmt(totals.cnt_post || 0));
setText('ccKpiVideoAI', fmt(totals.cnt_video_ai || 0));
setText('ccKpiVideoReview', fmt(totals.cnt_video_review || 0));
setText('ccKpiLive', fmt(totals.cnt_livestream || 0));
      setText('ccKpiReach', fmt(totals.reach || 0));
      setText('ccKpiViews', fmt(totals.views || 0));
      setText('ccKpiEng', fmt(eng));
      setText('ccKpiLeads', fmt(totals.leads || 0));

      // have/missing
      const missing = (json.alerts_missing?.length || 0);

      // totalInWeek = đếm row visible trong list view (tuần) để tính "Bài có số liệu"
      let totalInWeek = 0;
      try{
        const ws = json.week_start;
        const we = json.week_end;
        const rows = document.querySelectorAll('tr.cc-row[data-cc-item]');
        rows.forEach(tr=>{
          const visible = (tr.offsetParent !== null) && !tr.classList.contains('d-none');
          if(!visible) return;
          const d = tr.getAttribute('data-date') || '';
          if(d && d >= ws && d <= we) totalInWeek++;
        });
      }catch(e){}

      const have = Math.max(0, totalInWeek - missing);
      setText('ccKpiHave', fmt(have));
      setText('ccKpiMissing', fmt(missing));

      renderRanking(json.ranking || []);
      renderTopContent(json.top_content || []);
      renderAlerts(json.alerts_missing || []);
    }catch(err){
      console.warn('refreshDashboard error:', err);
      setText('ccDashRange', '—');
    }
  }

  // nút làm mới
  $('#ccDashRefresh')?.addEventListener('click', refreshDashboard);

  // Init view + dashboard
  setView('list');
  // auto load dashboard
  setTimeout(refreshDashboard, 300);
});
</script>
@endpush
