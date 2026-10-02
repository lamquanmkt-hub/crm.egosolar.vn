@extends('layouts.app')

@section('content')
@php
    $unfinishedStatuses = ['new', 'in_progress', 'submitted', 'revision', 'rejected'];
    $statusLabels = $statuses ?? [
        'new' => 'Mới giao',
        'in_progress' => 'Đang thực hiện',
        'submitted' => 'Chờ duyệt',
        'revision' => 'Cần bổ sung',
        'rejected' => 'Cần bổ sung',
        'approved' => 'Hoàn thành',
    ];
    $priorityLabels = $priorities ?? [
        'low' => 'Thấp',
        'medium' => 'Bình thường',
        'high' => 'Cao',
    ];
    $currentUserId = (int) auth()->id();

    // Controller hiện truyền biến assignableUsers. Chuẩn hóa thành assignees để bộ lọc
    // luôn hoạt động, đồng thời có phương án dự phòng từ danh sách công việc hiện có.
    $assignees = collect($assignableUsers ?? [])
        ->filter(fn ($person) => !empty($person) && !empty($person->id))
        ->unique('id')
        ->sortBy(fn ($person) => mb_strtolower((string) ($person->name ?? '')))
        ->values();

    if ($assignees->isEmpty() && isset($allTasks)) {
        $assignees = collect($allTasks)
            ->pluck('assignee')
            ->filter(fn ($person) => !empty($person) && !empty($person->id))
            ->unique('id')
            ->sortBy(fn ($person) => mb_strtolower((string) ($person->name ?? '')))
            ->values();
    }
@endphp

<style>
.task-v7-page{
    --v7-ink:#10233c;
    --v7-muted:#74849a;
    --v7-line:#e3e9f0;
    --v7-soft:#f5f7fa;
    --v7-teal:#087d76;
    --v7-teal-dark:#06665f;
    --v7-blue:#169bd5;
    min-height:100vh;
    background:#f2f5f9;
    padding:16px 18px 48px;
    color:var(--v7-ink);
    font-size:13px;
}
.task-v7-shell{max-width:1660px;margin:0 auto}
.task-v7-page-header{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:13px;padding:1px 2px}
.task-v7-kicker{font-size:10px;font-weight:900;letter-spacing:.13em;text-transform:uppercase;color:#0b8c84;margin-bottom:5px}
.task-v7-title{font-size:27px;line-height:1.06;font-weight:950;letter-spacing:-.035em;margin:0;color:#10233c}
.task-v7-subtitle{margin-top:6px;color:var(--v7-muted);font-size:12px;font-weight:650}
.task-v7-header-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.task-v7-btn{min-height:38px;border-radius:11px;padding:8px 13px;display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;font-size:11px;font-weight:900;border:1px solid transparent;white-space:nowrap}
.task-v7-btn-primary{background:var(--v7-teal);color:#fff;box-shadow:0 8px 20px rgba(8,125,118,.16)}
.task-v7-btn-primary:hover{background:var(--v7-teal-dark);color:#fff}
.task-v7-btn-secondary{background:#fff;border-color:#d9e1ea;color:#31445d}
.task-v7-btn-secondary:hover{background:#f8fafc;color:#10233c}
.task-v7-count-badge{height:20px;min-width:20px;border-radius:7px;padding:0 6px;display:inline-flex;align-items:center;justify-content:center;background:#e11d48;color:#fff;font-size:9px;font-weight:950}

.task-v7-workspace{padding:0!important;margin:0!important;background:#fff;border:1px solid var(--v7-line);border-radius:18px;box-shadow:0 12px 34px rgba(15,23,42,.045);overflow:hidden}
.task-v7-command{position:sticky;top:70px;z-index:20;background:rgba(255,255,255,.97);backdrop-filter:blur(12px);border-bottom:1px solid var(--v7-line)}
.task-v7-kpis{display:grid;grid-template-columns:1.15fr repeat(6,1fr);border-bottom:1px solid var(--v7-line)}
.task-v7-kpi{--tone:#0ea5e9;position:relative;padding:12px 14px;border-right:1px solid var(--v7-line);min-width:0}
.task-v7-kpi:last-child{border-right:0}
.task-v7-kpi::after{content:"";position:absolute;left:14px;right:14px;bottom:-1px;height:2px;border-radius:999px;background:var(--tone)}
.task-v7-kpi-label{display:flex;align-items:center;gap:6px;color:#7a899c;font-size:9px;font-weight:900;white-space:nowrap}
.task-v7-kpi-label i{color:var(--tone)}
.task-v7-kpi-value{margin-top:6px;font-size:22px;line-height:1;font-weight:950;letter-spacing:-.04em;color:#14263d}

.task-v7-filters{display:grid;grid-template-columns:minmax(250px,1.65fr) repeat(3,minmax(135px,.72fr)) minmax(150px,.72fr) auto;gap:9px;padding:10px 12px;align-items:end}
.task-v7-label{margin-bottom:5px;color:#75859a;font-size:9px;font-weight:900}
.task-v7-control{min-height:37px!important;border-radius:10px!important;border-color:#dce3eb!important;font-size:11px!important;color:#31445d!important}
.task-v7-control:focus{border-color:#42aaa4!important;box-shadow:0 0 0 .2rem rgba(8,125,118,.10)!important}
.task-v7-search{position:relative}
.task-v7-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#8b99ab}
.task-v7-search input{padding-left:35px}
.task-v7-tool-actions{display:flex;gap:6px}
.task-v7-icon-btn{width:37px;height:37px;border:1px solid #dce3eb;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#5d6e83}
.task-v7-icon-btn:hover,.task-v7-icon-btn.active{background:#eaf8f6;border-color:#9edbd5;color:var(--v7-teal)}

.task-v7-scopes{display:flex;align-items:center;gap:10px;padding:9px 12px;border-top:1px solid #f0f3f7}
.task-v7-scope-label{font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.1em;color:#8a97a9;white-space:nowrap}
.task-v7-scope-strip{display:flex;gap:6px;overflow-x:auto;scrollbar-width:thin;padding:1px}
.task-v7-scope{border:0;background:#f1f4f7;color:#4c5d71;border-radius:9px;min-height:30px;padding:6px 9px;font-size:9px;font-weight:900;white-space:nowrap;display:flex;align-items:center;gap:6px}
.task-v7-scope.active{background:var(--v7-teal);color:#fff;box-shadow:0 5px 13px rgba(8,125,118,.16)}
.task-v7-scope-count{height:18px;min-width:18px;padding:0 5px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;background:rgba(100,116,139,.14);font-size:8px}
.task-v7-scope.active .task-v7-scope-count{background:rgba(255,255,255,.20)}

.task-v7-list-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:11px 13px;background:#fbfcfd;border-bottom:1px solid var(--v7-line)}
.task-v7-list-title{font-size:13px;font-weight:950;color:#152840}
.task-v7-list-subtitle{font-size:10px;color:#8190a3;margin-top:2px}
.task-v7-visible{font-size:9px;font-weight:900;color:#64758a;background:#edf2f6;border-radius:8px;padding:6px 8px;white-space:nowrap}

.task-v7-table-wrap{overflow-x:auto;position:relative}
.task-v7-table{min-width:1120px}
.task-v7-grid{display:grid;grid-template-columns:minmax(285px,2.25fr) minmax(178px,1.25fr) minmax(158px,1.05fr) minmax(128px,.82fr) minmax(142px,.95fr) minmax(125px,.78fr);align-items:center}
.task-v7-head{min-height:38px;background:#f7f9fb;border-bottom:1px solid var(--v7-line);color:#7b899b;font-size:9px;font-weight:950;text-transform:uppercase;letter-spacing:.07em}
.task-v7-head > div{padding:0 13px;border-right:1px solid #edf1f5;height:100%;display:flex;align-items:center}
.task-v7-head > div:last-child{border-right:0;justify-content:flex-end}
.task-v7-row{--status-color:#38bdf8;position:relative;min-height:76px;border-bottom:1px solid #edf1f5;background:#fff;transition:background .15s ease,box-shadow .15s ease;cursor:pointer}
.task-v7-row::before{content:"";position:absolute;left:0;top:12px;bottom:12px;width:3px;border-radius:0 4px 4px 0;background:var(--status-color)}
.task-v7-row:hover{background:#fbfdfd;box-shadow:inset 0 0 0 1px rgba(8,125,118,.035)}
.task-v7-row.dense{min-height:62px}
.task-v7-row > div{padding:11px 13px;min-width:0}
.task-v7-row > div + div{border-left:1px solid #f1f4f7}

.task-v7-work-cell{display:flex;align-items:flex-start;gap:10px}
.task-v7-dept-icon{width:34px;height:34px;border-radius:10px;border:1px solid #dce6ee;background:#edf3f7;color:#34516e;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:950;flex:0 0 auto}
.task-v7-work-copy{min-width:0}
.task-v7-work-title{font-size:12px;line-height:1.35;font-weight:950;color:#10233c;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:5px}
.task-v7-work-meta{display:flex;gap:8px;align-items:center;flex-wrap:wrap;font-size:9px;color:#8190a3;font-weight:750}
.task-v7-work-meta span{display:inline-flex;align-items:center;gap:4px}
.task-v7-work-meta strong{color:#51647b;font-weight:900}
.task-v7-link-indicator{color:#0f766e!important}

.task-v7-person{display:flex;align-items:center;gap:9px;min-width:0}
.task-v7-avatar{width:33px;height:33px;border-radius:10px;background:linear-gradient(145deg,#e9f5f4,#edf3f8);border:1px solid #dbe6ec;color:#31536b;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:950;flex:0 0 auto}
.task-v7-person-copy{min-width:0}
.task-v7-person-name{font-size:11px;font-weight:950;color:#263b54;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.task-v7-person-dept{font-size:9px;color:#8190a3;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.task-v7-requester{font-size:8.5px;color:#8b99aa;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.task-v7-requester strong{color:#627389}

.task-v7-time-main{font-size:10px;font-weight:900;color:#32475f;display:flex;align-items:center;gap:5px}
.task-v7-time-sub{font-size:9px;color:#8291a4;margin-top:5px;display:flex;align-items:center;gap:5px}
.task-v7-time-alert{font-size:9px;font-weight:950;margin-top:5px;display:flex;align-items:center;gap:5px}
.task-v7-time-alert.danger{color:#c5163c}
.task-v7-time-alert.warning{color:#b5680c}

.task-v7-status-cell{display:flex;flex-direction:column;align-items:flex-start;gap:6px}
.task-v7-status{display:inline-flex;align-items:center;gap:5px;border-radius:8px;padding:5px 7px;font-size:9px;font-weight:950;white-space:nowrap}
.task-v7-status-new{background:#e8f5fc;color:#075985}
.task-v7-status-in_progress{background:#fff3d8;color:#8a4b08}
.task-v7-status-submitted{background:#eee9ff;color:#5b21b6}
.task-v7-status-revision,.task-v7-status-rejected{background:#fff0e6;color:#9a3412}
.task-v7-status-approved{background:#e5f8e9;color:#166534}
.task-v7-priority{display:inline-flex;align-items:center;gap:5px;color:#74849a;font-size:9px;font-weight:850}
.task-v7-priority-dot{width:7px;height:7px;border-radius:50%;background:#94a3b8}
.task-v7-priority-low .task-v7-priority-dot{background:#94a3b8}
.task-v7-priority-medium .task-v7-priority-dot{background:#0ea5e9}
.task-v7-priority-high .task-v7-priority-dot{background:#e11d48;box-shadow:0 0 0 3px rgba(225,29,72,.09)}

.task-v7-progress-top{display:flex;align-items:center;justify-content:space-between;gap:8px}
.task-v7-progress-value{font-size:12px;font-weight:950;color:#263b54}
.task-v7-progress-note{font-size:8.5px;color:#8b99aa;white-space:nowrap}
.task-v7-progress-track{height:6px;background:#e6ebf0;border-radius:999px;overflow:hidden;margin-top:7px}
.task-v7-progress-fill{height:100%;border-radius:inherit;background:linear-gradient(90deg,#14a3d7,#1fb77c)}
.task-v7-progress-fill.waiting{background:linear-gradient(90deg,#7766e8,#9b72f2)}
.task-v7-progress-fill.done{background:linear-gradient(90deg,#18a768,#28c27f)}

.task-v7-action-cell{display:flex;align-items:center;justify-content:flex-end;gap:6px}
.task-v7-action{min-height:33px;border-radius:9px!important;padding:6px 9px!important;font-size:9px!important;font-weight:950!important;white-space:nowrap}
.task-v7-more{position:relative}
.task-v7-more summary{list-style:none;width:33px;height:33px;border:1px solid #dce3eb;background:#fff;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#53647a;cursor:pointer}
.task-v7-more summary::-webkit-details-marker{display:none}
.task-v7-menu{position:absolute;right:0;top:38px;z-index:50;width:190px;background:#fff;border:1px solid #dce3eb;border-radius:12px;padding:5px;box-shadow:0 18px 42px rgba(15,23,42,.17)}
.task-v7-menu a,.task-v7-menu button{width:100%;border:0;background:transparent;text-align:left;padding:8px 9px;border-radius:8px;font-size:10px;font-weight:850;color:#40536a;text-decoration:none;display:flex;align-items:center;gap:8px}
.task-v7-menu a:hover,.task-v7-menu button:hover{background:#f1f5f8}
.task-v7-menu .danger{color:#d42842}

.task-v7-empty{padding:52px 20px;text-align:center;color:#718197}
.task-v7-empty-icon{width:55px;height:55px;border-radius:16px;background:#eaf8f6;color:var(--v7-teal);display:inline-flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:10px}

.task-v7-drawer-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.34);z-index:1040;opacity:0;visibility:hidden;transition:.2s}
.task-v7-drawer-backdrop.open{opacity:1;visibility:visible}
.task-v7-drawer{position:fixed;top:0;right:0;width:min(455px,94vw);height:100vh;background:#fff;z-index:1050;box-shadow:-18px 0 50px rgba(15,23,42,.18);transform:translateX(105%);transition:transform .24s ease;display:flex;flex-direction:column}
.task-v7-drawer.open{transform:translateX(0)}
.task-v7-drawer-head{padding:17px 18px;border-bottom:1px solid var(--v7-line);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;background:linear-gradient(135deg,#071d36,#087d76)}
.task-v7-drawer-kicker{font-size:9px;text-transform:uppercase;letter-spacing:.11em;font-weight:900;color:#98e3dc;margin-bottom:5px}
.task-v7-drawer-title{font-size:18px;line-height:1.25;font-weight:950;color:#fff;margin:0}
.task-v7-drawer-close{width:34px;height:34px;border:1px solid rgba(255,255,255,.26);background:rgba(255,255,255,.11);border-radius:10px;color:#fff;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.task-v7-drawer-body{padding:16px 18px;overflow-y:auto;flex:1;background:#f7f9fb}
.task-v7-drawer-card{background:#fff;border:1px solid var(--v7-line);border-radius:14px;padding:14px;margin-bottom:11px}
.task-v7-drawer-card-title{font-size:10px;font-weight:950;color:#7a899c;text-transform:uppercase;letter-spacing:.08em;margin-bottom:11px}
.task-v7-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.task-v7-info{background:#f6f8fa;border-radius:10px;padding:10px}
.task-v7-info-label{font-size:8.5px;color:#8795a7;font-weight:850;margin-bottom:4px}
.task-v7-info-value{font-size:11px;color:#2d425a;font-weight:950;line-height:1.35}
.task-v7-drawer-description{font-size:11px;line-height:1.65;color:#52657c;white-space:pre-line}
.task-v7-drawer-footer{padding:12px 18px;border-top:1px solid var(--v7-line);display:flex;gap:8px;background:#fff}
.task-v7-drawer-footer .task-v7-btn{flex:1}
body.task-v7-no-scroll{overflow:hidden}

@media(max-width:1250px){
    .task-v7-kpis{grid-template-columns:repeat(4,1fr)}
    .task-v7-kpi:nth-child(4){border-right:0}
    .task-v7-filters{grid-template-columns:1.45fr repeat(2,1fr)}
}
@media(max-width:900px){
    .task-v7-page{padding:12px}
    .task-v7-page-header{align-items:flex-start;flex-direction:column}
    .task-v7-header-actions{width:100%}
    .task-v7-header-actions .task-v7-btn{flex:1}
    .task-v7-kpis{grid-template-columns:repeat(2,1fr)}
    .task-v7-filters{grid-template-columns:1fr}
    .task-v7-scopes{align-items:flex-start;flex-direction:column}
    .task-v7-table{min-width:0}
    .task-v7-head{display:none}
    .task-v7-row{display:grid;grid-template-columns:1fr auto;min-height:0;padding:13px 12px 13px 15px;gap:9px}
    .task-v7-row > div{padding:0;border:0!important}
    .task-v7-row > div:nth-child(1){grid-column:1/3}
    .task-v7-row > div:nth-child(2){grid-column:1/2}
    .task-v7-row > div:nth-child(3){grid-column:2/3;text-align:right}
    .task-v7-row > div:nth-child(4){grid-column:1/2}
    .task-v7-row > div:nth-child(5){grid-column:1/3}
    .task-v7-row > div:nth-child(6){grid-column:1/3}
    .task-v7-action-cell{justify-content:flex-start}
    .task-v7-action{flex:1}
}
@media(max-width:560px){
    .task-v7-title{font-size:23px}
    .task-v7-dept-icon{display:none}
    .task-v7-work-title{white-space:normal}
    .task-v7-visible{display:none}
    .task-v7-info-grid{grid-template-columns:1fr}
}

/* EGO_TASK_V7_REMOVE_GAP_FIX_START */

/* Khóa toàn bộ khoảng trắng do CSS global tác động */
.task-v7-page .task-v7-workspace {
    display: block !important;
    position: relative !important;
    height: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Bỏ sticky và khoảng đệm ẩn phía trên KPI */
.task-v7-page .task-v7-workspace > .task-v7-command {
    display: block !important;
    position: relative !important;
    top: auto !important;
    right: auto !important;
    bottom: auto !important;
    left: auto !important;
    inset: auto !important;
    width: 100% !important;
    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;
    margin: 0 !important;
    padding: 0 !important;
    transform: none !important;
}

/* KPI phải nằm sát mép trên workspace */
.task-v7-page .task-v7-command > .task-v7-kpis {
    position: relative !important;
    top: auto !important;
    display: grid !important;
    width: 100% !important;
    height: auto !important;
    min-height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Loại bỏ pseudo-element hoặc spacer do giao diện chung sinh ra */
.task-v7-page .task-v7-workspace::before,
.task-v7-page .task-v7-workspace::after,
.task-v7-page .task-v7-command::before,
.task-v7-page .task-v7-command::after,
.task-v7-page .task-v7-kpis::before,
.task-v7-page .task-v7-kpis::after {
    content: none !important;
    display: none !important;
    width: 0 !important;
    height: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* EGO_TASK_V7_REMOVE_GAP_FIX_END */
</style>

<div class="task-v7-page">
    <div class="task-v7-shell">
        <header class="task-v7-page-header">
            <div>
                <div class="task-v7-kicker">Không gian điều hành</div>
                <h1 class="task-v7-title">Quản lý giao việc</h1>
                <div class="task-v7-subtitle">So sánh nhanh người thực hiện, hạn xử lý, trạng thái và tiến độ trong cùng một bảng quản trị.</div>
            </div>
            <div class="task-v7-header-actions">
                <button type="button" class="task-v7-btn task-v7-btn-secondary" data-quick-scope="awaiting">
                    <i class="bi bi-inbox"></i>
                    Chờ tôi duyệt
                    <span class="task-v7-count-badge">{{ $allTasks->where('status', 'submitted')->count() }}</span>
                </button>
                <a href="{{ route('tasks.create') }}" class="task-v7-btn task-v7-btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    Tạo công việc
                </a>
            </div>
        </header>

        <div class="task-v7-workspace">
            <div class="task-v7-command">
                <div class="task-v7-kpis">
                    <div class="task-v7-kpi" style="--tone:#0ea5e9">
                        <div class="task-v7-kpi-label"><i class="bi bi-collection"></i>Tổng việc</div>
                        <div class="task-v7-kpi-value" data-kpi="total">{{ $allTasks->count() }}</div>
                    </div>
                    <div class="task-v7-kpi" style="--tone:#38bdf8">
                        <div class="task-v7-kpi-label"><i class="bi bi-send"></i>Mới giao</div>
                        <div class="task-v7-kpi-value" data-kpi="new">{{ $allTasks->where('status', 'new')->count() }}</div>
                    </div>
                    <div class="task-v7-kpi" style="--tone:#f59e0b">
                        <div class="task-v7-kpi-label"><i class="bi bi-hourglass-split"></i>Đang làm</div>
                        <div class="task-v7-kpi-value" data-kpi="in_progress">{{ $allTasks->where('status', 'in_progress')->count() }}</div>
                    </div>
                    <div class="task-v7-kpi" style="--tone:#8b5cf6">
                        <div class="task-v7-kpi-label"><i class="bi bi-clipboard-check"></i>Chờ duyệt</div>
                        <div class="task-v7-kpi-value" data-kpi="submitted">{{ $allTasks->where('status', 'submitted')->count() }}</div>
                    </div>
                    <div class="task-v7-kpi" style="--tone:#f97316">
                        <div class="task-v7-kpi-label"><i class="bi bi-arrow-repeat"></i>Cần bổ sung</div>
                        <div class="task-v7-kpi-value" data-kpi="revision">{{ $allTasks->whereIn('status', ['revision', 'rejected'])->count() }}</div>
                    </div>
                    <div class="task-v7-kpi" style="--tone:#22c55e">
                        <div class="task-v7-kpi-label"><i class="bi bi-check-circle"></i>Hoàn thành</div>
                        <div class="task-v7-kpi-value" data-kpi="approved">{{ $allTasks->where('status', 'approved')->count() }}</div>
                    </div>
                    <div class="task-v7-kpi" style="--tone:#e11d48">
                        <div class="task-v7-kpi-label"><i class="bi bi-exclamation-circle"></i>Quá hạn</div>
                        <div class="task-v7-kpi-value" data-kpi="overdue">{{ $summary['overdue'] ?? 0 }}</div>
                    </div>
                </div>

                <div class="task-v7-filters">
                    <div>
                        <div class="task-v7-label">Tìm nhanh</div>
                        <div class="task-v7-search">
                            <i class="bi bi-search"></i>
                            <x-ui.input type="search" id="taskV7Search" class="task-v7-control" placeholder="Tên việc, người giao, người nhận..." />
                        </div>
                    </div>
                    <div>
                        <div class="task-v7-label">Trạng thái</div>
                        <x-ui.select id="taskV7Status" class="task-v7-control">
                            <option value="">Tất cả</option>
                            <option value="new">Mới giao</option>
                            <option value="in_progress">Đang thực hiện</option>
                            <option value="submitted">Chờ duyệt</option>
                            <option value="revision">Cần bổ sung</option>
                            <option value="approved">Hoàn thành</option>
                            <option value="overdue">Quá hạn</option>
                        </x-ui.select>
                    </div>
                    <div>
                        <div class="task-v7-label">Ưu tiên</div>
                        <x-ui.select id="taskV7Priority" class="task-v7-control">
                            <option value="">Tất cả</option>
                            @foreach($priorityLabels as $priorityKey => $priorityName)
                                <option value="{{ $priorityKey }}">{{ $priorityName }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div>
                        <div class="task-v7-label">Người nhận</div>
                        <x-ui.select id="taskV7Assignee" class="task-v7-control">
                            <option value="">Tất cả</option>
                            @foreach($assignees as $assignee)
                                <option value="{{ $assignee->id }}">{{ $assignee->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div>
                        <div class="task-v7-label">Sắp xếp</div>
                        <x-ui.select id="taskV7Sort" class="task-v7-control">
                            <option value="updated_desc">Cập nhật mới nhất</option>
                            <option value="due_asc">Hạn gần nhất</option>
                            <option value="progress_desc">Tiến độ cao nhất</option>
                            <option value="title_asc">Tên A–Z</option>
                        </x-ui.select>
                    </div>
                    <div class="task-v7-tool-actions">
                        <button id="taskV7Reset" class="task-v7-icon-btn" type="button" title="Xóa bộ lọc"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <button id="taskV7Density" class="task-v7-icon-btn" type="button" title="Hiển thị gọn"><i class="bi bi-list"></i></button>
                    </div>
                </div>

                <div class="task-v7-scopes">
                    <div class="task-v7-scope-label">Phạm vi</div>
                    <div class="task-v7-scope-strip" id="taskV7Scopes">
                        <button type="button" class="task-v7-scope active" data-scope="all">Tất cả <span class="task-v7-scope-count">{{ $allTasks->count() }}</span></button>
                        <button type="button" class="task-v7-scope" data-scope="mine">Việc tôi giao <span class="task-v7-scope-count">{{ $allTasks->where('requester_id', $currentUserId)->count() }}</span></button>
                        <button type="button" class="task-v7-scope" data-scope="awaiting">Chờ tôi duyệt <span class="task-v7-scope-count">{{ $allTasks->where('status', 'submitted')->count() }}</span></button>
                        <button type="button" class="task-v7-scope" data-scope="overdue">Quá hạn <span class="task-v7-scope-count">{{ $summary['overdue'] ?? 0 }}</span></button>
                        @foreach($taskGroups as $group)
                            <button type="button" class="task-v7-scope" data-scope="dept-{{ $group['id'] }}">{{ $group['name'] }} <span class="task-v7-scope-count">{{ $group['total'] }}</span></button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="task-v7-list-bar">
                <div>
                    <div class="task-v7-list-title">Bảng công việc</div>
                    <div class="task-v7-list-subtitle">Chạm vào một dòng để mở hồ sơ nhanh; dùng nút chính để xử lý theo trạng thái.</div>
                </div>
                <div class="task-v7-visible"><strong id="taskV7Visible">0</strong> việc đang hiển thị</div>
            </div>

            <div class="task-v7-table-wrap">
                <div class="task-v7-table" id="taskV7Table">
                    <div class="task-v7-grid task-v7-head">
                        <div>Công việc</div>
                        <div>Người thực hiện</div>
                        <div>Thời gian</div>
                        <div>Trạng thái</div>
                        <div>Tiến độ</div>
                        <div>Xử lý</div>
                    </div>

                    @forelse($allTasks as $task)
                        @php
                            $rawStatus = (string) ($task->status ?: 'new');
                            $progress = max(0, min(100, (int) ($task->progress_percent ?? 0)));
                            $effectiveStatus = ($rawStatus === 'new' && $progress > 0)
                                ? 'in_progress'
                                : (($rawStatus === 'rejected') ? 'revision' : $rawStatus);
                            $priority = (string) ($task->priority ?: 'medium');
                            $dueAt = !empty($task->due_at) ? \Illuminate\Support\Carbon::parse($task->due_at) : null;
                            $isOverdue = $dueAt && in_array($rawStatus, $unfinishedStatuses, true) && $dueAt->lt(now());
                            $daysOverdue = $isOverdue ? max(1, $dueAt->startOfDay()->diffInDays(now()->startOfDay())) : 0;
                            $daysRemaining = (!$isOverdue && $dueAt && in_array($rawStatus, $unfinishedStatuses, true))
                                ? now()->startOfDay()->diffInDays($dueAt->copy()->startOfDay(), false)
                                : null;
                            $departmentId = (int) (optional($task->assignee)->department_id ?? 0);
                            $requesterName = optional($task->requester)->name ?: '[Đã xóa]';
                            $assigneeName = optional($task->assignee)->name ?: '[Đã xóa]';
                            $departmentName = optional(optional($task->assignee)->department)->name ?: 'Chưa xác định';
                            $searchText = trim(implode(' ', [$task->title, $task->description, $requesterName, $assigneeName, $departmentName]));
                            $actionLabel = $effectiveStatus === 'submitted'
                                ? 'Xem báo cáo'
                                : ($effectiveStatus === 'revision'
                                    ? 'Xem bổ sung'
                                    : ($effectiveStatus === 'approved' ? 'Xem hồ sơ' : 'Xem chi tiết'));
                            $words = preg_split('/\s+/u', trim($assigneeName)) ?: [];
                            $words = array_values(array_filter($words));
                            $initials = '';
                            foreach (array_slice($words, -2) as $word) {
                                $initials .= mb_strtoupper(mb_substr($word, 0, 1));
                            }
                            $departmentShort = 'NV';
                            $normalizedDepartment = mb_strtolower($departmentName);
                            if (str_contains($normalizedDepartment, 'kỹ thuật')) {
                                $departmentShort = 'KT';
                            } elseif (str_contains($normalizedDepartment, 'marketing')) {
                                $departmentShort = 'MKT';
                            } elseif (str_contains($normalizedDepartment, 'hành chính') || str_contains($normalizedDepartment, 'nhân sự')) {
                                $departmentShort = 'HR';
                            } elseif (str_contains($normalizedDepartment, 'kế toán') || str_contains($normalizedDepartment, 'kho')) {
                                $departmentShort = 'KTK';
                            } elseif (str_contains($normalizedDepartment, 'giám đốc')) {
                                $departmentShort = 'BGĐ';
                            }
                            $statusTone = [
                                'new' => '#38bdf8',
                                'in_progress' => '#f59e0b',
                                'submitted' => '#8b5cf6',
                                'revision' => '#f97316',
                                'rejected' => '#f97316',
                                'approved' => '#22c55e',
                            ][$effectiveStatus] ?? '#64748b';
                            $progressClass = $effectiveStatus === 'approved' ? 'done' : ($effectiveStatus === 'submitted' ? 'waiting' : '');
                            $descriptionPreview = \Illuminate\Support\Str::limit(trim(strip_tags((string) ($task->description ?? ''))), 420);
                        @endphp

                        <article
                            class="task-v7-grid task-v7-row"
                            style="--status-color:{{ $isOverdue ? '#e11d48' : $statusTone }}"
                            data-task-v7-item
                            data-task-id="{{ $task->id }}"
                            data-search="{{ \Illuminate\Support\Str::lower($searchText) }}"
                            data-title="{{ \Illuminate\Support\Str::lower($task->title) }}"
                            data-display-title="{{ $task->title }}"
                            data-status="{{ $effectiveStatus }}"
                            data-status-label="{{ $statusLabels[$effectiveStatus] ?? $effectiveStatus }}"
                            data-raw-status="{{ $rawStatus }}"
                            data-priority="{{ $priority }}"
                            data-priority-label="{{ $priorityLabels[$priority] ?? $priority }}"
                            data-assignee="{{ (int) $task->assignee_id }}"
                            data-assignee-name="{{ $assigneeName }}"
                            data-requester="{{ (int) $task->requester_id }}"
                            data-requester-name="{{ $requesterName }}"
                            data-department="{{ $departmentId }}"
                            data-department-name="{{ $departmentName }}"
                            data-overdue="{{ $isOverdue ? 1 : 0 }}"
                            data-progress="{{ $progress }}"
                            data-due="{{ $dueAt ? $dueAt->format('d/m/Y') : 'Chưa đặt' }}"
                            data-due-ts="{{ $dueAt ? $dueAt->timestamp : 9999999999 }}"
                            data-updated="{{ optional($task->updated_at)->format('d/m/Y H:i') }}"
                            data-updated-ts="{{ optional($task->updated_at)->timestamp ?? 0 }}"
                            data-description="{{ $descriptionPreview }}"
                            data-link="{{ $task->link_url ?? '' }}"
                            data-show-url="{{ route('tasks.show', $task) }}"
                        >
                            <div class="task-v7-work-cell">
                                <div class="task-v7-dept-icon">{{ $departmentShort }}</div>
                                <div class="task-v7-work-copy">
                                    <div class="task-v7-work-title" title="{{ $task->title }}">{{ $task->title }}</div>
                                    <div class="task-v7-work-meta">
                                        <span><i class="bi bi-hash"></i><strong>{{ $task->id }}</strong></span>
                                        <span><i class="bi bi-diagram-3"></i>{{ $departmentName }}</span>
                                        @if(!empty($task->link_url))
                                            <span class="task-v7-link-indicator"><i class="bi bi-link-45deg"></i>Có liên kết</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="task-v7-person">
                                <div class="task-v7-avatar">{{ $initials ?: 'NV' }}</div>
                                <div class="task-v7-person-copy">
                                    <div class="task-v7-person-name">{{ $assigneeName }}</div>
                                    <div class="task-v7-person-dept">{{ $departmentName }}</div>
                                    <div class="task-v7-requester">Giao bởi <strong>{{ $requesterName }}</strong></div>
                                </div>
                            </div>

                            <div>
                                <div class="task-v7-time-main"><i class="bi bi-calendar3"></i>{{ $dueAt ? $dueAt->format('d/m/Y') : 'Chưa đặt hạn' }}</div>
                                <div class="task-v7-time-sub"><i class="bi bi-clock-history"></i>{{ optional($task->updated_at)->format('d/m/Y H:i') }}</div>
                                @if($isOverdue)
                                    <div class="task-v7-time-alert danger"><i class="bi bi-exclamation-triangle"></i>Quá hạn {{ $daysOverdue }} ngày</div>
                                @elseif($daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= 3)
                                    <div class="task-v7-time-alert warning"><i class="bi bi-hourglass-split"></i>Còn {{ $daysRemaining }} ngày</div>
                                @endif
                            </div>

                            <div class="task-v7-status-cell">
                                <span class="task-v7-status task-v7-status-{{ $effectiveStatus }}">{{ $statusLabels[$effectiveStatus] ?? $effectiveStatus }}</span>
                                <span class="task-v7-priority task-v7-priority-{{ $priority }}"><span class="task-v7-priority-dot"></span>{{ $priorityLabels[$priority] ?? $priority }}</span>
                            </div>

                            <div>
                                <div class="task-v7-progress-top">
                                    <span class="task-v7-progress-value">{{ $progress }}%</span>
                                    <span class="task-v7-progress-note">{{ $effectiveStatus === 'submitted' ? 'Đang chờ duyệt' : ($effectiveStatus === 'approved' ? 'Đã hoàn tất' : 'Tiến độ') }}</span>
                                </div>
                                <div class="task-v7-progress-track"><div class="task-v7-progress-fill {{ $progressClass }}" style="width:{{ $progress }}%"></div></div>
                            </div>

                            <div class="task-v7-action-cell" data-no-row-open>
                                <x-ui.button href="{{ route('tasks.show', $task) }}" variant="outline-primary" size="none" class="task-v7-action tw:leading-[1.5]">{{ $actionLabel }}</x-ui.button>
                                <details class="task-v7-more">
                                    <summary><i class="bi bi-three-dots"></i></summary>
                                    <div class="task-v7-menu">
                                        <a href="{{ route('tasks.show', $task) }}"><i class="bi bi-eye"></i>Xem hồ sơ</a>
                                        <a href="{{ route('tasks.edit', $task) }}"><i class="bi bi-pencil-square"></i>Sửa công việc</a>
                                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Xóa công việc #{{ $task->id }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="danger"><i class="bi bi-trash"></i>Xóa công việc</button>
                                        </form>
                                    </div>
                                </details>
                            </div>
                        </article>
                    @empty
                        <div class="task-v7-empty">
                            <div class="task-v7-empty-icon"><i class="bi bi-clipboard2-check"></i></div>
                            <div class="tw:font-bold tw:text-[#212529]">Chưa có công việc</div>
                            <div class="tw:mt-1">Tạo phiếu giao việc đầu tiên để bắt đầu theo dõi.</div>
                        </div>
                    @endforelse

                    <div class="task-v7-empty d-none" id="taskV7FilteredEmpty">
                        <div class="task-v7-empty-icon"><i class="bi bi-search"></i></div>
                        <div class="tw:font-bold tw:text-[#212529]">Không tìm thấy công việc phù hợp</div>
                        <div class="tw:mt-1">Thử thay đổi phạm vi hoặc bộ lọc.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="task-v7-drawer-backdrop" id="taskV7Backdrop"></div>
<aside class="task-v7-drawer" id="taskV7Drawer" aria-hidden="true">
    <div class="task-v7-drawer-head">
        <div>
            <div class="task-v7-drawer-kicker">Hồ sơ công việc</div>
            <h2 class="task-v7-drawer-title" id="taskV7DrawerTitle">Công việc</h2>
        </div>
        <button type="button" class="task-v7-drawer-close" id="taskV7DrawerClose" aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="task-v7-drawer-body">
        <div class="task-v7-drawer-card">
            <div class="task-v7-drawer-card-title">Thông tin nhanh</div>
            <div class="task-v7-info-grid">
                <div class="task-v7-info"><div class="task-v7-info-label">Người thực hiện</div><div class="task-v7-info-value" id="taskV7DrawerAssignee">—</div></div>
                <div class="task-v7-info"><div class="task-v7-info-label">Người giao</div><div class="task-v7-info-value" id="taskV7DrawerRequester">—</div></div>
                <div class="task-v7-info"><div class="task-v7-info-label">Hạn hoàn thành</div><div class="task-v7-info-value" id="taskV7DrawerDue">—</div></div>
                <div class="task-v7-info"><div class="task-v7-info-label">Phòng ban</div><div class="task-v7-info-value" id="taskV7DrawerDepartment">—</div></div>
                <div class="task-v7-info"><div class="task-v7-info-label">Trạng thái</div><div class="task-v7-info-value" id="taskV7DrawerStatus">—</div></div>
                <div class="task-v7-info"><div class="task-v7-info-label">Ưu tiên</div><div class="task-v7-info-value" id="taskV7DrawerPriority">—</div></div>
            </div>
        </div>
        <div class="task-v7-drawer-card">
            <div class="task-v7-drawer-card-title">Tiến độ</div>
            <div class="task-v7-progress-top"><span class="task-v7-progress-value" id="taskV7DrawerProgressValue">0%</span><span class="task-v7-progress-note" id="taskV7DrawerUpdated">—</span></div>
            <div class="task-v7-progress-track"><div class="task-v7-progress-fill" id="taskV7DrawerProgress" style="width:0%"></div></div>
        </div>
        <div class="task-v7-drawer-card">
            <div class="task-v7-drawer-card-title">Mô tả / yêu cầu</div>
            <div class="task-v7-drawer-description" id="taskV7DrawerDescription">Chưa có mô tả.</div>
        </div>
    </div>
    <div class="task-v7-drawer-footer">
        <button type="button" class="task-v7-btn task-v7-btn-secondary" id="taskV7DrawerDismiss">Đóng</button>
        <a href="#" class="task-v7-btn task-v7-btn-primary" id="taskV7DrawerOpen"><i class="bi bi-box-arrow-up-right"></i>Xem đầy đủ hồ sơ</a>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('taskV7Table');
    const items = Array.from(document.querySelectorAll('[data-task-v7-item]'));
    const scopes = Array.from(document.querySelectorAll('[data-scope]'));
    const search = document.getElementById('taskV7Search');
    const status = document.getElementById('taskV7Status');
    const priority = document.getElementById('taskV7Priority');
    const assignee = document.getElementById('taskV7Assignee');
    const sort = document.getElementById('taskV7Sort');
    const visible = document.getElementById('taskV7Visible');
    const empty = document.getElementById('taskV7FilteredEmpty');
    const density = document.getElementById('taskV7Density');
    const backdrop = document.getElementById('taskV7Backdrop');
    const drawer = document.getElementById('taskV7Drawer');
    let activeScope = 'all';
    let dense = false;

    function normalize(value) {
        return (value || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function matchesScope(item) {
        if (activeScope === 'all') return true;
        if (activeScope === 'mine') return Number(item.dataset.requester) === {{ $currentUserId }};
        if (activeScope === 'awaiting') return item.dataset.rawStatus === 'submitted';
        if (activeScope === 'overdue') return item.dataset.overdue === '1';
        if (activeScope.indexOf('dept-') === 0) return item.dataset.department === activeScope.replace('dept-', '');
        return true;
    }

    function updateKpi(name, value) {
        const element = document.querySelector('[data-kpi="' + name + '"]');
        if (element) element.textContent = value;
    }

    function orderRows(rows) {
        const mode = sort.value;
        rows.sort(function (a, b) {
            if (mode === 'due_asc') return Number(a.dataset.dueTs) - Number(b.dataset.dueTs);
            if (mode === 'progress_desc') return Number(b.dataset.progress) - Number(a.dataset.progress);
            if (mode === 'title_asc') return (a.dataset.title || '').localeCompare(b.dataset.title || '', 'vi');
            return Number(b.dataset.updatedTs) - Number(a.dataset.updatedTs);
        });
        rows.forEach(function (row) {
            table.insertBefore(row, empty);
        });
    }

    function applyFilters() {
        const query = normalize(search.value);
        const selectedStatus = status.value;
        const selectedPriority = priority.value;
        const selectedAssignee = assignee.value;
        const shown = [];

        items.forEach(function (item) {
            const show = matchesScope(item)
                && (!query || normalize(item.dataset.search).includes(query))
                && (!selectedStatus || (selectedStatus === 'overdue' ? item.dataset.overdue === '1' : item.dataset.status === selectedStatus))
                && (!selectedPriority || item.dataset.priority === selectedPriority)
                && (!selectedAssignee || item.dataset.assignee === selectedAssignee);

            item.classList.toggle('d-none', !show);
            item.classList.toggle('dense', dense);
            if (show) shown.push(item);
        });

        orderRows(shown);
        const counts = {total: shown.length, new: 0, in_progress: 0, submitted: 0, revision: 0, approved: 0, overdue: 0};
        shown.forEach(function (item) {
            const currentStatus = item.dataset.status;
            if (Object.prototype.hasOwnProperty.call(counts, currentStatus)) counts[currentStatus] += 1;
            if (item.dataset.overdue === '1') counts.overdue += 1;
        });
        Object.keys(counts).forEach(function (key) { updateKpi(key, counts[key]); });
        visible.textContent = shown.length;
        empty.classList.toggle('d-none', shown.length > 0 || items.length === 0);
    }

    function openDrawer(item) {
        document.getElementById('taskV7DrawerTitle').textContent = item.dataset.displayTitle || 'Công việc';
        document.getElementById('taskV7DrawerAssignee').textContent = item.dataset.assigneeName || '—';
        document.getElementById('taskV7DrawerRequester').textContent = item.dataset.requesterName || '—';
        document.getElementById('taskV7DrawerDue').textContent = item.dataset.due || '—';
        document.getElementById('taskV7DrawerDepartment').textContent = item.dataset.departmentName || '—';
        document.getElementById('taskV7DrawerStatus').textContent = item.dataset.statusLabel || '—';
        document.getElementById('taskV7DrawerPriority').textContent = item.dataset.priorityLabel || '—';
        document.getElementById('taskV7DrawerDescription').textContent = item.dataset.description || 'Chưa có mô tả.';
        document.getElementById('taskV7DrawerUpdated').textContent = item.dataset.updated ? 'Cập nhật ' + item.dataset.updated : '—';
        document.getElementById('taskV7DrawerProgressValue').textContent = (item.dataset.progress || '0') + '%';
        document.getElementById('taskV7DrawerProgress').style.width = (item.dataset.progress || '0') + '%';
        document.getElementById('taskV7DrawerOpen').href = item.dataset.showUrl || '#';
        drawer.classList.add('open');
        backdrop.classList.add('open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('task-v7-no-scroll');
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        backdrop.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('task-v7-no-scroll');
    }

    scopes.forEach(function (button) {
        button.addEventListener('click', function () {
            activeScope = button.dataset.scope || 'all';
            scopes.forEach(function (scopeButton) { scopeButton.classList.toggle('active', scopeButton === button); });
            applyFilters();
        });
    });

    document.querySelectorAll('[data-quick-scope]').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.querySelector('[data-scope="' + button.dataset.quickScope + '"]');
            if (target) target.click();
        });
    });

    [search, status, priority, assignee, sort].forEach(function (element) {
        element.addEventListener(element.tagName === 'INPUT' ? 'input' : 'change', applyFilters);
    });

    document.getElementById('taskV7Reset').addEventListener('click', function () {
        search.value = '';
        status.value = '';
        priority.value = '';
        assignee.value = '';
        sort.value = 'updated_desc';
        const allScope = document.querySelector('[data-scope="all"]');
        if (allScope) allScope.click(); else applyFilters();
    });

    density.addEventListener('click', function () {
        dense = !dense;
        density.classList.toggle('active', dense);
        applyFilters();
    });

    items.forEach(function (item) {
        item.addEventListener('click', function (event) {
            if (event.target.closest('[data-no-row-open], a, button, details, summary, form')) return;
            openDrawer(item);
        });
    });

    document.getElementById('taskV7DrawerClose').addEventListener('click', closeDrawer);
    document.getElementById('taskV7DrawerDismiss').addEventListener('click', closeDrawer);
    backdrop.addEventListener('click', closeDrawer);
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeDrawer(); });
    document.addEventListener('click', function (event) {
        document.querySelectorAll('details.task-v7-more[open]').forEach(function (detail) {
            if (!detail.contains(event.target)) detail.removeAttribute('open');
        });
    });

    applyFilters();
});
</script>
@endsection
