@extends('layouts.app')

@section('content')
@php
    $status = (string) ($task->status ?? 'new');
    $priority = (string) ($task->priority ?? 'medium');
    $progress = max(0, min(100, (int) ($task->progress_percent ?? 0)));

    $attachmentsCollection = collect($attachments ?? []);
    $taskFiles = $attachmentsCollection->where('type', 'task')->values();
    $resultFiles = $attachmentsCollection->where('type', 'result')->values();
    $revisionFiles = $attachmentsCollection->where('type', 'revision')->values();
    $workReports = collect($workReports ?? []);
    $latestWorkReport = $workReports->first();

    $relatedTasks = collect($relatedTasks ?? [$task]);
    $assigneeNames = $relatedTasks
        ->map(fn ($item) => optional($item->assignee)->name)
        ->filter()
        ->unique()
        ->values();

    $requesterName = optional($task->requester)->name ?? 'Không rõ';
    $assigneeName = $assigneeNames->count()
        ? $assigneeNames->implode(', ')
        : (optional($task->assignee)->name ?? 'Không rõ');

    $isSubmitted = $status === 'submitted';
    $isRevision = in_array($status, ['revision', 'rejected'], true);
    $isApproved = $status === 'approved';
    $isAssignee = (int) ($task->assignee_id ?? 0) === (int) auth()->id();
    $returnReason = $task->revision_reason ?? $task->rejection_reason ?? null;
    $managerFeedback = $task->manager_feedback ?? null;
    $canBossEdit = (bool) ($canApprove ?? false);
    $canEditResult = (((bool) ($canUpdate ?? false)) && ! $isApproved) || ($canBossEdit && ! $isAssignee);
    $canDeleteTaskFiles = (bool) ($canApprove ?? false);
    $canManageRevisionFiles = (bool) ($canApprove ?? false)
        || (int) ($task->requester_id ?? 0) === (int) auth()->id();

    $statusLabels = array_merge($statuses ?? [], [
        'new' => 'Chưa báo cáo',
        'in_progress' => 'Đang thực hiện',
        'submitted' => 'Chờ duyệt',
        'revision' => 'Cần bổ sung',
        'rejected' => 'Cần bổ sung',
        'approved' => 'Đã duyệt',
    ]);

    $priorityLabels = array_merge($priorities ?? [], [
        'low' => 'Thấp',
        'medium' => 'Bình thường',
        'high' => 'Cao',
    ]);

    $formatDateTime = static function ($value, string $fallback = '-') {
        if (! $value) {
            return $fallback;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    };

    $formatDate = static function ($value, string $fallback = '-') {
        if (! $value) {
            return $fallback;
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    };

    $formatBytes = static function ($bytes) {
        $bytes = max(0, (int) $bytes);
        if ($bytes === 0) {
            return 'Không rõ dung lượng';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }
        return number_format($bytes / 1024, 1).' KB';
    };

    $fileIcon = static function ($file) {
        $mime = strtolower((string) ($file->file_mime ?? ''));
        $name = strtolower((string) ($file->file_name ?? ''));
        if (str_contains($mime, 'image')) return 'bi-image';
        if (str_contains($mime, 'pdf') || str_ends_with($name, '.pdf')) return 'bi-file-earmark-pdf';
        if (str_contains($mime, 'spreadsheet') || preg_match('/\.(xls|xlsx|csv)$/', $name)) return 'bi-file-earmark-spreadsheet';
        if (str_contains($mime, 'word') || preg_match('/\.(doc|docx)$/', $name)) return 'bi-file-earmark-word';
        if (str_contains($mime, 'presentation') || preg_match('/\.(ppt|pptx)$/', $name)) return 'bi-file-earmark-slides';
        if (preg_match('/\.(zip|rar|7z)$/', $name)) return 'bi-file-earmark-zip';
        return 'bi-file-earmark-text';
    };

    $initials = static function ($name) {
        $parts = collect(preg_split('/\s+/u', trim((string) $name)))->filter()->values();
        if ($parts->isEmpty()) return '?';
        $chosen = $parts->count() > 1 ? collect([$parts->first(), $parts->last()]) : $parts;
        return $chosen->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    };

    $submittedAt = $task->submitted_at ?? null;
    $approvedAt = $task->approved_at ?? null;
    $completedAt = $task->completed_at ?? null;
    $defaultReportDate = optional($latestWorkReport)->report_date ?: now()->toDateString();
    if (! optional($latestWorkReport)->report_date && $submittedAt) {
        try {
            $defaultReportDate = \Illuminate\Support\Carbon::parse($submittedAt)->toDateString();
        } catch (\Throwable $e) {
            $defaultReportDate = now()->toDateString();
        }
    }
    $reportDateValue = old('report_date', $defaultReportDate);

    $isOverdue = false;
    if (! $isApproved && ! empty($task->due_at)) {
        try {
            $isOverdue = \Illuminate\Support\Carbon::parse($task->due_at)->isPast();
        } catch (\Throwable $e) {
            $isOverdue = false;
        }
    }

    $stageIndex = match ($status) {
        'new' => 1,
        'in_progress' => 2,
        'submitted' => 3,
        'revision', 'rejected' => 2,
        'approved' => 4,
        default => 1,
    };

    $historyStatusLabels = [
        'submitted' => 'Đã nộp báo cáo',
        'resubmitted' => 'Đã nộp lại báo cáo',
        'revision' => 'Quản lý yêu cầu bổ sung',
        'rejected' => 'Quản lý yêu cầu bổ sung',
        'approved' => 'Báo cáo đã được duyệt',
        'manager_updated' => 'Quản lý cập nhật báo cáo',
        'in_progress' => 'Cập nhật tiến độ',
    ];
@endphp

<style>
    .wrk-page{
        --wrk-navy:#071a31;
        --wrk-navy-2:#0b2c4c;
        --wrk-blue:#1674d1;
        --wrk-blue-soft:#edf6ff;
        --wrk-teal:#0e968c;
        --wrk-green:#199661;
        --wrk-amber:#b96d00;
        --wrk-red:#c43c3c;
        --wrk-text:#152237;
        --wrk-muted:#66768b;
        --wrk-line:#dde6ef;
        --wrk-bg:#f4f7fb;
        --wrk-card:#ffffff;
        min-height:100vh;
        padding:18px 18px 86px;
        background:
            radial-gradient(circle at 88% 2%, rgba(22,116,209,.08), transparent 24%),
            var(--wrk-bg);
        color:var(--wrk-text);
        font-size:13px;
    }
    .wrk-shell{max-width:1500px;margin:0 auto}
    .wrk-topline{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:12px}
    .wrk-breadcrumb{display:flex;align-items:center;gap:8px;color:#7a899c;font-size:12px;font-weight:700;min-width:0}
    .wrk-breadcrumb strong{color:#1c2b40;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:420px}
    .wrk-top-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .wrk-btn{min-height:36px;border-radius:11px;padding:7px 13px;display:inline-flex;align-items:center;justify-content:center;gap:7px;font-size:12px;font-weight:850;text-decoration:none;white-space:nowrap}
      .wrk-btn-wide{padding-left:24px;padding-right:24px}
    .wrk-btn-icon{width:36px;height:36px;padding:0;border-radius:11px}

    .wrk-header{position:relative;overflow:hidden;background:linear-gradient(118deg,var(--wrk-navy) 0%,var(--wrk-navy-2) 73%,#0d4d61 100%);border-radius:20px;color:#fff;box-shadow:0 18px 50px rgba(7,26,49,.16);margin-bottom:14px}
    .wrk-header:before{content:"";position:absolute;right:-95px;top:-180px;width:390px;height:390px;border-radius:50%;border:70px solid rgba(255,255,255,.045)}
    .wrk-header-main{position:relative;z-index:1;display:grid;grid-template-columns:minmax(0,1fr) 180px;gap:24px;padding:22px 24px 18px}
    .wrk-header-tags{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:10px}
    .wrk-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:900;line-height:1;white-space:nowrap}
    .wrk-header .wrk-chip{border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.1);color:#fff}
    .wrk-header-title{font-size:22px;line-height:1.3;font-weight:950;letter-spacing:-.025em;margin:0;word-break:break-word}
    .wrk-people{display:flex;align-items:center;gap:10px 22px;flex-wrap:wrap;margin-top:14px;color:rgba(255,255,255,.78)}
    .wrk-person{display:flex;align-items:center;gap:9px;min-width:0}
    .wrk-avatar{width:34px;height:34px;border-radius:11px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:950;flex:0 0 auto}
    .wrk-person-label{font-size:10px;text-transform:uppercase;letter-spacing:.055em;font-weight:850;color:rgba(255,255,255,.56)}
    .wrk-person-name{margin-top:1px;color:#fff;font-size:12px;font-weight:900;max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .wrk-deadline{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:850;color:#fff}
    .wrk-progress-ring{--value:0;align-self:center;justify-self:end;width:132px;height:132px;border-radius:50%;display:grid;place-items:center;background:conic-gradient(#44d5b5 calc(var(--value)*1%),rgba(255,255,255,.14) 0);position:relative;box-shadow:inset 0 0 0 1px rgba(255,255,255,.05)}
    .wrk-progress-ring:before{content:"";position:absolute;inset:10px;border-radius:50%;background:#0b2c4c;box-shadow:inset 0 0 26px rgba(0,0,0,.15)}
    .wrk-progress-center{position:relative;text-align:center;z-index:1}
    .wrk-progress-value{font-size:26px;line-height:1;font-weight:950}
    .wrk-progress-label{margin-top:5px;font-size:10px;color:rgba(255,255,255,.63);font-weight:800;text-transform:uppercase;letter-spacing:.055em}

    .wrk-flow{position:relative;z-index:1;display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid rgba(255,255,255,.1);background:rgba(0,0,0,.08);padding:0 15px}
    .wrk-flow-step{position:relative;display:flex;align-items:center;justify-content:center;gap:8px;padding:13px 8px;color:rgba(255,255,255,.48);font-weight:850;min-width:0}
    .wrk-flow-step:not(:last-child):after{content:"";position:absolute;right:-14%;top:50%;width:28%;height:1px;background:rgba(255,255,255,.16)}
    .wrk-flow-dot{width:25px;height:25px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.1);font-size:11px;flex:0 0 auto}
    .wrk-flow-step.done{color:#8fe4c8}.wrk-flow-step.done .wrk-flow-dot{background:#1f9b6b;color:#fff}
    .wrk-flow-step.current{color:#fff}.wrk-flow-step.current .wrk-flow-dot{background:#2f90ee;color:#fff;box-shadow:0 0 0 5px rgba(47,144,238,.16)}

    .wrk-banner{display:flex;align-items:flex-start;gap:11px;border-radius:14px;padding:13px 15px;margin-bottom:14px;border:1px solid}
    .wrk-banner-icon{width:30px;height:30px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;font-size:15px}
    .wrk-banner-title{font-weight:950;margin-bottom:3px}
    .wrk-banner-copy{line-height:1.55;white-space:pre-line}
    .wrk-banner-warning{background:#fff8ea;border-color:#ffdca2;color:#84500a}.wrk-banner-warning .wrk-banner-icon{background:#ffeac2;color:#9e6000}
    .wrk-banner-success{background:#edf9f3;border-color:#c5ead7;color:#236143}.wrk-banner-success .wrk-banner-icon{background:#d8f3e4;color:#17784c}
    .wrk-banner-danger{background:#fff0f0;border-color:#f6caca;color:#922f2f}.wrk-banner-danger .wrk-banner-icon{background:#ffe0e0;color:#b62e2e}

    .wrk-layout{display:grid;grid-template-columns:minmax(0,1fr) 340px;gap:14px;align-items:start}
    .wrk-dossier{background:var(--wrk-card);border:1px solid var(--wrk-line);border-radius:18px;box-shadow:0 9px 28px rgba(15,35,60,.045);overflow:hidden}
    .wrk-dossier-head{padding:14px 18px;border-bottom:1px solid var(--wrk-line);display:flex;align-items:center;justify-content:space-between;gap:12px;background:linear-gradient(180deg,#fff,#fbfdff)}
    .wrk-dossier-title{display:flex;align-items:center;gap:9px;font-size:14px;font-weight:950;margin:0}
    .wrk-title-icon{width:30px;height:30px;border-radius:10px;background:var(--wrk-blue-soft);color:#156bb6;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
    .wrk-dossier-body{display:flex;flex-direction:column}
    .wrk-section{padding:18px}
    .wrk-section + .wrk-section{border-top:1px solid var(--wrk-line)}
    .wrk-assignment-section{order:1}.wrk-report-section{order:2}.wrk-timeline-section{order:3}
    .wrk-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:13px}
    .wrk-section-title-wrap{display:flex;align-items:flex-start;gap:10px;min-width:0}
    .wrk-section-number{width:27px;height:27px;border-radius:9px;display:flex;align-items:center;justify-content:center;background:#edf3f9;color:#516579;font-size:11px;font-weight:950;flex:0 0 auto}
    .wrk-section-title{font-size:14px;font-weight:950;margin:1px 0 0;color:#132238}
    .wrk-section-subtitle{font-size:11px;color:var(--wrk-muted);margin-top:3px}
    .wrk-section-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap;justify-content:flex-end}
    .wrk-label{font-size:10px;font-weight:950;text-transform:uppercase;letter-spacing:.06em;color:#7a889b;margin-bottom:7px}
    .wrk-copy{line-height:1.7;color:#35455a;white-space:pre-line;word-break:break-word}
    .wrk-copy-box{padding:13px 14px;border:1px solid #e3eaf2;background:#f8fafc;border-radius:13px}
    .wrk-empty{padding:17px;border:1px dashed #cfd9e4;background:#fafcff;border-radius:13px;text-align:center;color:#77869a;line-height:1.55}
    .wrk-link{display:inline-flex;align-items:center;gap:7px;margin-top:12px;color:#126bc1;font-weight:850;text-decoration:none}

    .wrk-file-list{display:grid;gap:7px;margin-top:12px}
    .wrk-file-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 10px;border:1px solid #e2e9f1;background:#fbfcfe;border-radius:12px}
    .wrk-file-main{display:flex;align-items:center;gap:10px;min-width:0}
    .wrk-file-icon{width:34px;height:34px;border-radius:10px;background:#eaf4ff;color:#156bb6;display:flex;align-items:center;justify-content:center;font-size:16px;flex:0 0 auto}
    .wrk-file-name{font-weight:900;color:#1a293e;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:520px}
    .wrk-file-meta{font-size:10px;color:#7a899b;margin-top:2px}
    .wrk-file-actions{display:flex;align-items:center;gap:5px;flex:0 0 auto}
    .wrk-file-actions form{margin:0}
    .wrk-mini-btn{height:31px;border-radius:9px;padding:0 10px;display:inline-flex;align-items:center;justify-content:center;gap:5px;font-size:11px;font-weight:850;white-space:nowrap}
    .wrk-mini-icon{width:31px;padding:0}

    .wrk-report-panel{position:relative;border:1px solid #dbe8f5;background:linear-gradient(180deg,#fafdff,#f7fbff);border-radius:15px;padding:15px;overflow:hidden}
    .wrk-report-panel:before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,var(--wrk-blue),var(--wrk-teal))}
    .wrk-report-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-bottom:14px}
    .wrk-stat{padding:10px 11px;border:1px solid #e0e8f1;background:#fff;border-radius:11px}
    .wrk-stat-label{font-size:9px;text-transform:uppercase;letter-spacing:.055em;color:#8492a4;font-weight:950}
    .wrk-stat-value{font-size:12px;font-weight:950;color:#17273c;margin-top:4px}
    .wrk-progress-line{height:7px;background:#e2e9f1;border-radius:999px;overflow:hidden;margin-top:7px}
    .wrk-progress-line > span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#1d86df,#26b981)}
    .wrk-report-block + .wrk-report-block{margin-top:14px}
    .wrk-result-copy{font-size:13px;line-height:1.7;color:#2e4056;white-space:pre-line;word-break:break-word}
    .wrk-note-box{border-left:3px solid #d2dee9;background:#fff;padding:10px 12px;border-radius:0 10px 10px 0;color:#4a5c70;line-height:1.6;white-space:pre-line}
    .wrk-feedback-box{border:1px solid #cce6d9;background:#effaf4;padding:11px 12px;border-radius:11px;color:#276246;line-height:1.6;white-space:pre-line}

    .wrk-revision-files{margin-top:14px;padding-top:14px;border-top:1px dashed #d8e2ec}

    .wrk-timeline{position:relative;display:grid;gap:0;padding-left:8px}
    .wrk-timeline:before{content:"";position:absolute;left:19px;top:10px;bottom:14px;width:1px;background:#d9e3ed}
    .wrk-event{position:relative;display:grid;grid-template-columns:24px minmax(0,1fr) auto;gap:11px;padding:0 0 16px;align-items:start}
    .wrk-event:last-child{padding-bottom:0}
    .wrk-event-dot{width:24px;height:24px;border-radius:50%;background:#fff;border:2px solid #9bb2c7;display:flex;align-items:center;justify-content:center;color:#5b748d;font-size:9px;z-index:1}
    .wrk-event-dot.success{border-color:#40a779;color:#24835b}.wrk-event-dot.warning{border-color:#d99a31;color:#b76b00}.wrk-event-dot.primary{border-color:#4d9be2;color:#1674d1}
    .wrk-event-title{font-weight:950;color:#17273b;line-height:1.35}
    .wrk-event-meta{display:flex;gap:7px 13px;flex-wrap:wrap;margin-top:4px;color:#748397;font-size:11px}
    .wrk-event-time{font-size:10px;color:#8492a4;white-space:nowrap;padding-top:2px}
    .wrk-event-details{grid-column:2 / 4;margin-top:6px}
    .wrk-event-details summary{cursor:pointer;color:#166ab8;font-size:11px;font-weight:850;list-style:none}
    .wrk-event-details summary::-webkit-details-marker{display:none}
    .wrk-event-content{margin-top:7px;padding:10px 11px;background:#f8fafc;border:1px solid #e2e9f1;border-radius:10px;color:#4a5a6d;line-height:1.55;white-space:pre-line}

    .wrk-side{display:grid;gap:12px;position:sticky;top:82px}
    .wrk-side-card{background:#fff;border:1px solid var(--wrk-line);border-radius:16px;box-shadow:0 8px 24px rgba(15,35,60,.045);overflow:hidden}
    .wrk-side-head{display:flex;align-items:center;justify-content:space-between;gap:9px;padding:12px 14px;border-bottom:1px solid var(--wrk-line)}
    .wrk-side-title{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:950;color:#17263a}
    .wrk-side-body{padding:13px}
    .wrk-action-card .wrk-side-head{background:var(--wrk-navy);border-bottom:0}.wrk-action-card .wrk-side-title{color:#fff}
    .wrk-action-stack{display:grid;gap:8px}
    .wrk-primary-action{width:100%;min-height:41px;border-radius:11px;font-size:12px;font-weight:950;display:flex;align-items:center;justify-content:center;gap:7px}
    .wrk-action-note{font-size:11px;color:#738296;line-height:1.5;margin-bottom:10px}
    .wrk-action-panel{display:none;margin-top:10px;padding-top:11px;border-top:1px solid var(--wrk-line)}
    .wrk-action-panel.show{display:block}
    .wrk-form-label{display:block;font-size:11px;font-weight:900;color:#42546a;margin-bottom:6px}
    .wrk-control{border-radius:11px;border-color:#d7e1eb;font-size:12px;box-shadow:none!important}
    .wrk-control:focus{border-color:#6aa7dd}
    .wrk-help{font-size:10px;color:#8592a3;margin-top:5px;line-height:1.45}
    .wrk-divider{height:1px;background:var(--wrk-line);margin:12px 0}
    .wrk-info-list{display:grid}
    .wrk-info-row{display:grid;grid-template-columns:112px minmax(0,1fr);gap:12px;padding:9px 0;border-bottom:1px dashed #e3e9f0;align-items:start}
    .wrk-info-row:last-child{border-bottom:0}
    .wrk-info-key{font-size:11px;color:#7a899b;font-weight:800}
    .wrk-info-value{font-size:11px;color:#1b2b40;font-weight:900;text-align:right;word-break:break-word}
    .wrk-side-status{display:flex;align-items:center;gap:9px;padding:10px;border-radius:11px;background:#f5f8fb;margin-bottom:11px}
    .wrk-side-status-icon{width:32px;height:32px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#e6f2ff;color:#126bc1;flex:0 0 auto}
    .wrk-side-status-title{font-weight:950}.wrk-side-status-sub{font-size:10px;color:#7b899a;margin-top:2px}

    .wrk-status-new{background:#e8f4ff;color:#075d96}.wrk-status-in_progress{background:#fff2cf;color:#8a5800}.wrk-status-submitted{background:#eee8ff;color:#6031a1}.wrk-status-revision,.wrk-status-rejected{background:#fff0df;color:#a65000}.wrk-status-approved{background:#e0f6e9;color:#17613c}
    .wrk-priority-low{background:#eef2f6;color:#566578}.wrk-priority-medium{background:#e2f3ff;color:#075b8c}.wrk-priority-high{background:#ffe5e5;color:#a62d2d}.wrk-overdue{background:#ffe2e2;color:#aa2929}

    .wrk-menu{position:relative}
    .wrk-menu summary{list-style:none;cursor:pointer}.wrk-menu summary::-webkit-details-marker{display:none}
    .wrk-menu-pop{position:absolute;right:0;top:42px;width:205px;background:#fff;border:1px solid #dce5ee;border-radius:12px;box-shadow:0 16px 45px rgba(15,35,60,.18);padding:6px;z-index:50}
    .wrk-menu-item{width:100%;min-height:36px;border:0;background:transparent;border-radius:8px;padding:7px 9px;display:flex;align-items:center;gap:8px;text-align:left;color:#26364a;font-size:12px;font-weight:850;text-decoration:none}
    .wrk-menu-item:hover{background:#f2f6fa}.wrk-menu-item.danger{color:#b72f2f}.wrk-menu-pop form{margin:0}

    .wrk-drawer-backdrop{position:fixed;inset:0;background:rgba(5,18,34,.54);z-index:99980;display:none;opacity:0;transition:opacity .2s ease}
    .wrk-drawer-backdrop.show{display:block;opacity:1}
    .wrk-drawer{position:fixed;right:0;top:0;bottom:0;width:min(640px,96vw);background:#fff;z-index:99990;transform:translateX(104%);transition:transform .24s ease;box-shadow:-28px 0 70px rgba(6,24,45,.22);display:flex;flex-direction:column}
    .wrk-drawer.show{transform:translateX(0)}
    .wrk-drawer-head{min-height:66px;padding:13px 16px;border-bottom:1px solid var(--wrk-line);display:flex;align-items:center;justify-content:space-between;gap:12px;background:linear-gradient(180deg,#fff,#fbfdff)}
    .wrk-drawer-title{font-size:16px;font-weight:950;color:#15243a}.wrk-drawer-sub{font-size:11px;color:#758397;margin-top:2px}
    .wrk-drawer-body{flex:1;min-height:0;overflow:auto;padding:16px}
    .wrk-drawer-footer{padding:12px 16px;border-top:1px solid var(--wrk-line);display:flex;align-items:center;justify-content:flex-end;gap:8px;background:#fff}
    .wrk-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .wrk-upload{padding:12px;border:1px dashed #bdccda;border-radius:12px;background:#fafcff}

    .wrk-preview-backdrop{position:fixed;inset:0;background:rgba(5,18,34,.66);z-index:100000;display:none;align-items:center;justify-content:center;padding:18px}
    .wrk-preview-backdrop.show{display:flex}
    .wrk-preview-modal{width:min(1180px,97vw);height:min(790px,93vh);background:#fff;border-radius:18px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 32px 90px rgba(0,0,0,.35)}
    .wrk-preview-head{min-height:58px;padding:10px 14px;border-bottom:1px solid var(--wrk-line);display:flex;align-items:center;justify-content:space-between;gap:12px;background:#f8fbfe}
    .wrk-preview-title{min-width:0;font-size:14px;font-weight:950;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .wrk-preview-actions{display:flex;gap:7px;align-items:center}
    .wrk-preview-body{flex:1;min-height:0;background:#f4f7fa}.wrk-preview-frame{width:100%;height:100%;border:0;background:#fff}

    .wrk-mobile-dock{display:none}
    body.wrk-no-scroll{overflow:hidden}

    @media(max-width:1180px){
        .wrk-layout{grid-template-columns:minmax(0,1fr) 310px}
        .wrk-file-name{max-width:340px}
    }
    @media(max-width:980px){
        .wrk-layout{grid-template-columns:1fr}
        .wrk-side{position:static;grid-template-columns:repeat(2,minmax(0,1fr))}
        .wrk-action-card{order:1}.wrk-info-card{order:2}
    }
    @media(max-width:760px){
        .wrk-page{padding:11px 10px 88px}
        .wrk-topline{align-items:flex-start}.wrk-breadcrumb strong{max-width:190px}.wrk-top-actions .wrk-text-action{display:none}
        .wrk-header{border-radius:16px}
        .wrk-header-main{grid-template-columns:minmax(0,1fr) 86px;gap:12px;padding:17px 15px 14px}
        .wrk-header-title{font-size:18px}
        .wrk-people{gap:10px;margin-top:12px}.wrk-person:nth-child(2){width:100%}
        .wrk-person-name{max-width:220px}
        .wrk-progress-ring{width:78px;height:78px}.wrk-progress-ring:before{inset:7px}.wrk-progress-value{font-size:18px}.wrk-progress-label{display:none}
        .wrk-flow{padding:0 5px}.wrk-flow-step{padding:11px 2px;gap:4px;font-size:9px}.wrk-flow-step:not(:last-child):after{display:none}.wrk-flow-dot{width:22px;height:22px}
        .wrk-dossier{border-radius:15px}.wrk-dossier-head{padding:12px 13px}.wrk-section{padding:14px 13px}
        .wrk-report-section{order:1}.wrk-assignment-section{order:2}.wrk-timeline-section{order:3}
        .wrk-section-head{align-items:center}.wrk-section-subtitle{display:none}
        .wrk-report-stats{grid-template-columns:1fr 1fr}.wrk-report-stats .wrk-stat:last-child{grid-column:1/-1}
        .wrk-file-row{align-items:flex-start}.wrk-file-actions{flex-wrap:wrap;justify-content:flex-end}.wrk-file-name{max-width:175px}
        .wrk-side{grid-template-columns:1fr}.wrk-action-card{display:none}
        .wrk-info-row{grid-template-columns:105px minmax(0,1fr)}
        .wrk-event{grid-template-columns:24px minmax(0,1fr)}.wrk-event-time{grid-column:2;margin-top:-8px}.wrk-event-details{grid-column:2}
        .wrk-form-grid{grid-template-columns:1fr}
        .wrk-drawer{width:100vw}.wrk-drawer-footer{padding-bottom:max(12px,env(safe-area-inset-bottom))}
        .wrk-mobile-dock{position:fixed;left:0;right:0;bottom:0;z-index:9000;display:flex;gap:8px;padding:9px 10px max(9px,env(safe-area-inset-bottom));background:rgba(255,255,255,.96);border-top:1px solid #dbe4ed;box-shadow:0 -10px 30px rgba(15,35,60,.09);backdrop-filter:blur(10px)}
        .wrk-mobile-dock .wrk-dock-btn{flex:1;min-height:42px;border-radius:11px;font-size:12px;line-height:1.5;font-weight:950}.wrk-preview-actions .wrk-btn{flex:1}
        .wrk-preview-backdrop{padding:7px}.wrk-preview-modal{width:100%;height:91vh;border-radius:14px}.wrk-preview-head{align-items:flex-start;flex-direction:column}.wrk-preview-actions{width:100%}
    }
    @media(max-width:420px){
        .wrk-header-main{grid-template-columns:1fr}.wrk-progress-ring{position:absolute;right:13px;top:15px;width:68px;height:68px}.wrk-header-copy{padding-right:76px}.wrk-header-tags{padding-right:4px}.wrk-header-title{font-size:17px}.wrk-deadline{width:100%}
        .wrk-file-row{flex-direction:column}.wrk-file-actions{width:100%;justify-content:flex-start}.wrk-file-name{max-width:245px}
        .wrk-section-actions .wrk-btn{padding:6px 9px}
    }
</style>

<div class="wrk-page">
    <div class="wrk-shell">
        <div class="wrk-topline">
            <div class="wrk-breadcrumb">
                <span>Công việc</span><i class="bi bi-chevron-right"></i><span>Báo cáo việc</span><i class="bi bi-chevron-right"></i><strong>#{{ $task->id }}</strong>
            </div>

            <div class="wrk-top-actions">
                <x-ui.button variant="outline-secondary" size="none" class="wrk-btn wrk-text-action" :href="route('tasks.my')"><i class="bi bi-arrow-left"></i> Báo cáo việc</x-ui.button>
                @if($canApprove)
                    <x-ui.button variant="outline-primary" size="none" class="wrk-btn wrk-text-action" :href="route('tasks.index')"><i class="bi bi-list-task"></i> Danh sách việc</x-ui.button>
                @endif
                <details class="wrk-menu">
                    <x-ui.button variant="light" size="none" as="summary" class="border wrk-btn wrk-btn-icon" aria-label="Tùy chọn"><i class="bi bi-three-dots"></i></x-ui.button>
                    <div class="wrk-menu-pop">
                        @if($canApprove)
                            <a href="{{ route('tasks.edit', $task) }}" class="wrk-menu-item"><i class="bi bi-pencil-square"></i> Sửa công việc</a>
                        @endif
                        <a href="{{ route('tasks.my') }}" class="wrk-menu-item"><i class="bi bi-journal-check"></i> Về Báo cáo việc</a>
                        @if($canApprove)
                            <div class="wrk-divider"></div>
                            <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Bạn chắc chắn muốn xóa công việc này? Dữ liệu và file liên quan sẽ bị xóa.')">
                                @csrf
                                @method('DELETE')
                                <button class="wrk-menu-item danger"><i class="bi bi-trash3"></i> Xóa công việc</button>
                            </form>
                        @endif
                    </div>
                </details>
            </div>
        </div>

        @if(session('success'))
            <div class="wrk-banner wrk-banner-success">
                <span class="wrk-banner-icon"><i class="bi bi-check-circle"></i></span>
                <div><div class="wrk-banner-title">Thao tác thành công</div><div class="wrk-banner-copy">{{ session('success') }}</div></div>
            </div>
        @endif
        @if(session('error'))
            <div class="wrk-banner wrk-banner-danger">
                <span class="wrk-banner-icon"><i class="bi bi-exclamation-triangle"></i></span>
                <div><div class="wrk-banner-title">Không thể thực hiện</div><div class="wrk-banner-copy">{{ session('error') }}</div></div>
            </div>
        @endif
        @if($errors->any())
            <div class="wrk-banner wrk-banner-danger">
                <span class="wrk-banner-icon"><i class="bi bi-exclamation-octagon"></i></span>
                <div>
                    <div class="wrk-banner-title">Vui lòng kiểm tra lại thông tin</div>
                    <div class="wrk-banner-copy">{{ $errors->first() }}</div>
                </div>
            </div>
        @endif

        <header class="wrk-header">
            <div class="wrk-header-main">
                <div class="wrk-header-copy">
                    <div class="wrk-header-tags">
                        <span class="wrk-chip"><i class="bi bi-hash"></i>{{ $task->id }}</span>
                        <span class="wrk-chip"><i class="bi bi-circle-fill" style="font-size:6px"></i>{{ $statusLabels[$status] ?? $status }}</span>
                        <span class="wrk-chip"><i class="bi bi-flag"></i>Ưu tiên {{ mb_strtolower($priorityLabels[$priority] ?? $priority) }}</span>
                        @if($isOverdue)<span class="wrk-chip" style="background:rgba(255,89,89,.18);border-color:rgba(255,130,130,.25)"><i class="bi bi-alarm"></i> Quá hạn</span>@endif
                    </div>
                    <h1 class="wrk-header-title">{{ $task->title }}</h1>
                    <div class="wrk-people">
                        <div class="wrk-person">
                            <span class="wrk-avatar">{{ $initials($requesterName) }}</span>
                            <div><div class="wrk-person-label">Người giao</div><div class="wrk-person-name">{{ $requesterName }}</div></div>
                        </div>
                        <div class="wrk-person">
                            <span class="wrk-avatar">{{ $initials($assigneeName) }}</span>
                            <div><div class="wrk-person-label">Người thực hiện</div><div class="wrk-person-name">{{ $assigneeName }}</div></div>
                        </div>
                        <div class="wrk-deadline"><i class="bi bi-calendar2-check"></i> Hạn {{ $formatDateTime($task->due_at) }}</div>
                    </div>
                </div>
                <div class="wrk-progress-ring" style="--value:{{ $progress }}">
                    <div class="wrk-progress-center"><div class="wrk-progress-value">{{ $progress }}%</div><div class="wrk-progress-label">Tiến độ</div></div>
                </div>
            </div>

            <div class="wrk-flow">
                @foreach([
                    1 => ['icon' => 'bi-inbox', 'label' => 'Được giao'],
                    2 => ['icon' => 'bi-gear', 'label' => 'Đang làm'],
                    3 => ['icon' => 'bi-send-check', 'label' => 'Chờ duyệt'],
                    4 => ['icon' => 'bi-patch-check', 'label' => 'Hoàn thành'],
                ] as $stage => $stageInfo)
                    <div class="wrk-flow-step {{ $stage < $stageIndex ? 'done' : ($stage === $stageIndex ? 'current' : '') }}">
                        <span class="wrk-flow-dot"><i class="bi {{ $stageInfo['icon'] }}"></i></span>
                        <span>{{ $stageInfo['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </header>

        @if($isRevision && $returnReason)
            <div class="wrk-banner wrk-banner-warning">
                <span class="wrk-banner-icon"><i class="bi bi-arrow-counterclockwise"></i></span>
                <div>
                    <div class="wrk-banner-title">Báo cáo cần sửa đổi / bổ sung</div>
                    <div class="wrk-banner-copy">{{ $returnReason }}</div>
                    @if($canUpdate && ! $isApproved)
                        <x-ui.button variant="warning" size="none" class="tw:mt-2 wrk-btn" type="button" data-open-report-drawer><i class="bi bi-pencil-square"></i> Bổ sung báo cáo ngay</x-ui.button>
                    @endif
                </div>
            </div>
        @endif

        <div class="wrk-layout">
            <main class="wrk-dossier">
                <div class="wrk-dossier-head">
                    <h2 class="wrk-dossier-title"><span class="wrk-title-icon"><i class="bi bi-folder2-open"></i></span> Hồ sơ công việc</h2>
                    <span class="wrk-chip wrk-status-{{ $status }}">{{ $statusLabels[$status] ?? $status }}</span>
                </div>

                <div class="wrk-dossier-body">
                    <section class="wrk-section wrk-assignment-section">
                        <div class="wrk-section-head">
                            <div class="wrk-section-title-wrap">
                                <span class="wrk-section-number">01</span>
                                <div><h3 class="wrk-section-title">Nội dung được giao</h3><div class="wrk-section-subtitle">Yêu cầu, tài liệu và liên kết từ người giao việc</div></div>
                            </div>
                        </div>

                        <div class="wrk-label">Mô tả / yêu cầu công việc</div>
                        <div class="wrk-copy wrk-copy-box">{{ $task->description ?: 'Chưa có nội dung mô tả.' }}</div>

                        @if(! empty($task->link_url))
                            <a href="{{ $task->link_url }}" target="_blank" rel="noopener" class="wrk-link"><i class="bi bi-box-arrow-up-right"></i> Mở liên kết công việc</a>
                        @endif

                        @if($taskFiles->count())
                            <div class="wrk-label tw:mt-4">Tài liệu giao việc</div>
                            <div class="wrk-file-list">
                                @foreach($taskFiles as $file)
                                    <div class="wrk-file-row">
                                        <div class="wrk-file-main">
                                            <span class="wrk-file-icon"><i class="bi {{ $fileIcon($file) }}"></i></span>
                                            <div style="min-width:0">
                                                <div class="wrk-file-name">{{ $file->file_name }}</div>
                                                <div class="wrk-file-meta">{{ strtoupper(pathinfo((string) $file->file_name, PATHINFO_EXTENSION) ?: 'FILE') }} · {{ $formatBytes($file->file_size ?? 0) }}</div>
                                            </div>
                                        </div>
                                        <div class="wrk-file-actions">
                                            <x-ui.button variant="outline-primary" size="none" class="wrk-mini-btn wrk-preview-btn" type="button" data-file-url="{{ asset('storage/'.$file->file_path) }}" data-file-name="{{ $file->file_name }}" data-file-mime="{{ $file->file_mime }}"><i class="bi bi-eye"></i> Xem</x-ui.button>
                                            <x-ui.button variant="outline-secondary" size="none" class="wrk-mini-btn" :href="asset('storage/'.$file->file_path)" download><i class="bi bi-download"></i> Tải</x-ui.button>
                                            @if($canDeleteTaskFiles)
                                                <form method="POST" action="{{ route('tasks.attachments.destroy', [$task, $file->id]) }}" onsubmit="return confirm('Xóa file giao việc này?')">
                                                    @csrf @method('DELETE')
                                                    <x-ui.button variant="outline-danger" size="none" class="wrk-mini-btn wrk-mini-icon" type="submit" title="Xóa file"><i class="bi bi-trash"></i></x-ui.button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="wrk-section wrk-report-section">
                        <div class="wrk-section-head">
                            <div class="wrk-section-title-wrap">
                                <span class="wrk-section-number">02</span>
                                <div><h3 class="wrk-section-title">Báo cáo thực hiện</h3><div class="wrk-section-subtitle">Kết quả, tiến độ và file minh chứng của nhân viên</div></div>
                            </div>
                            <div class="wrk-section-actions">
                                <span class="wrk-chip wrk-status-{{ $status }}">{{ $statusLabels[$status] ?? $status }}</span>
                                @if($canEditResult)
                                    <x-ui.button variant="primary" size="none" class="wrk-btn" type="button" data-open-report-drawer><i class="bi bi-pencil-square"></i> Chỉnh sửa</x-ui.button>
                                @endif
                            </div>
                        </div>

                        @if($task->result_note || $resultFiles->count() || $submittedAt || $isApproved || $isSubmitted || $isRevision)
                            <div class="wrk-report-panel">
                                <div class="wrk-report-stats">
                                    <div class="wrk-stat"><div class="wrk-stat-label">Ngày thực hiện</div><div class="wrk-stat-value">{{ $formatDate(optional($latestWorkReport)->report_date ?? $submittedAt ?? $completedAt) }}</div></div>
                                    <div class="wrk-stat"><div class="wrk-stat-label">Ngày nộp báo cáo</div><div class="wrk-stat-value">{{ $formatDateTime($submittedAt) }}</div></div>
                                    <div class="wrk-stat"><div class="wrk-stat-label">Tiến độ ghi nhận</div><div class="wrk-stat-value">{{ $progress }}%</div><div class="wrk-progress-line"><span style="width:{{ $progress }}%"></span></div></div>
                                </div>

                                <div class="wrk-report-block">
                                    <div class="wrk-label">Kết quả thực hiện</div>
                                    <div class="wrk-result-copy">{{ $task->result_note ?: 'Chưa có nội dung kết quả.' }}</div>
                                </div>

                                @if(! empty(optional($latestWorkReport)->employee_note))
                                    <div class="wrk-report-block"><div class="wrk-label">Khó khăn / ghi chú / đề xuất</div><div class="wrk-note-box">{{ optional($latestWorkReport)->employee_note }}</div></div>
                                @endif

                                @if($managerFeedback && ($isApproved || $isRevision))
                                    <div class="wrk-report-block"><div class="wrk-label">Phản hồi của quản lý</div><div class="wrk-feedback-box">{{ $managerFeedback }}</div></div>
                                @endif

                                @if($resultFiles->count())
                                    <div class="wrk-report-block">
                                        <div class="wrk-label">File minh chứng / kết quả</div>
                                        <div class="wrk-file-list">
                                            @foreach($resultFiles as $file)
                                                <div class="wrk-file-row">
                                                    <div class="wrk-file-main">
                                                        <span class="wrk-file-icon"><i class="bi {{ $fileIcon($file) }}"></i></span>
                                                        <div style="min-width:0"><div class="wrk-file-name">{{ $file->file_name }}</div><div class="wrk-file-meta">{{ strtoupper(pathinfo((string) $file->file_name, PATHINFO_EXTENSION) ?: 'FILE') }} · {{ $formatBytes($file->file_size ?? 0) }}</div></div>
                                                    </div>
                                                    <div class="wrk-file-actions">
                                                        <x-ui.button variant="outline-primary" size="none" class="wrk-mini-btn wrk-preview-btn" type="button" data-file-url="{{ asset('storage/'.$file->file_path) }}" data-file-name="{{ $file->file_name }}" data-file-mime="{{ $file->file_mime }}"><i class="bi bi-eye"></i> Xem</x-ui.button>
                                                        <x-ui.button variant="outline-secondary" size="none" class="wrk-mini-btn" :href="asset('storage/'.$file->file_path)" download><i class="bi bi-download"></i> Tải</x-ui.button>
                                                        @if($canEditResult)
                                                            <form method="POST" action="{{ route('tasks.attachments.replace', [$task, $file->id]) }}" enctype="multipart/form-data">
                                                                @csrf
                                                                <x-ui.button variant="outline-warning" size="none" as="label" class="wrk-mini-btn wrk-mini-icon tw:mb-0" title="Thay file"><i class="bi bi-arrow-repeat"></i><input type="file" name="file" class="tw:hidden" onchange="this.form.submit()"></x-ui.button>
                                                            </form>
                                                            <form method="POST" action="{{ route('tasks.attachments.destroy', [$task, $file->id]) }}" onsubmit="return confirm('Xóa file kết quả này?')">
                                                                @csrf @method('DELETE')
                                                                <x-ui.button variant="outline-danger" size="none" class="wrk-mini-btn wrk-mini-icon" type="submit" title="Xóa file"><i class="bi bi-trash"></i></x-ui.button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if($revisionFiles->count())
                                    <div class="wrk-revision-files">
                                        <div class="wrk-label">Tài liệu quản lý gửi kèm yêu cầu bổ sung</div>
                                        <div class="wrk-file-list">
                                            @foreach($revisionFiles as $file)
                                                <div class="wrk-file-row">
                                                    <div class="wrk-file-main">
                                                        <span class="wrk-file-icon"><i class="bi {{ $fileIcon($file) }}"></i></span>
                                                        <div style="min-width:0"><div class="wrk-file-name">{{ $file->file_name }}</div><div class="wrk-file-meta">Tài liệu hướng dẫn sửa đổi / bổ sung</div></div>
                                                    </div>
                                                    <div class="wrk-file-actions">
                                                        <x-ui.button variant="outline-primary" size="none" class="wrk-mini-btn wrk-preview-btn" type="button" data-file-url="{{ asset('storage/'.$file->file_path) }}" data-file-name="{{ $file->file_name }}" data-file-mime="{{ $file->file_mime }}"><i class="bi bi-eye"></i> Xem</x-ui.button>
                                                        <x-ui.button variant="outline-secondary" size="none" class="wrk-mini-btn" :href="asset('storage/'.$file->file_path)" download><i class="bi bi-download"></i> Tải</x-ui.button>
                                                        @if($canManageRevisionFiles)
                                                            <form method="POST" action="{{ route('tasks.attachments.replace', [$task, $file->id]) }}" enctype="multipart/form-data">
                                                                @csrf
                                                                <x-ui.button variant="outline-warning" size="none" as="label" class="wrk-mini-btn wrk-mini-icon tw:mb-0" title="Thay file"><i class="bi bi-arrow-repeat"></i><input type="file" name="file" class="tw:hidden" onchange="this.form.submit()"></x-ui.button>
                                                            </form>
                                                            <form method="POST" action="{{ route('tasks.attachments.destroy', [$task, $file->id]) }}" onsubmit="return confirm('Xóa file yêu cầu bổ sung này?')">
                                                                @csrf @method('DELETE')
                                                                <x-ui.button variant="outline-danger" size="none" class="wrk-mini-btn wrk-mini-icon" type="submit" title="Xóa file"><i class="bi bi-trash"></i></x-ui.button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="wrk-empty"><i class="bi bi-journal-plus fs-4 tw:block tw:mb-2"></i>Chưa có báo cáo thực hiện.<br>Nhân viên cập nhật kết quả khi công việc đã có tiến độ.</div>
                        @endif
                    </section>

                    <section class="wrk-section wrk-timeline-section">
                        <div class="wrk-section-head">
                            <div class="wrk-section-title-wrap">
                                <span class="wrk-section-number">03</span>
                                <div><h3 class="wrk-section-title">Hoạt động công việc</h3><div class="wrk-section-subtitle">Lịch sử nộp, bổ sung và phê duyệt báo cáo</div></div>
                            </div>
                            <span class="wrk-chip wrk-priority-low">{{ $workReports->count() + 1 }} mốc</span>
                        </div>

                        <div class="wrk-timeline">
                            @foreach($workReports as $report)
                                <div class="wrk-event">
                                    <span class="wrk-event-dot {{ (string) ($report->status ?? 'submitted') === 'approved' ? 'success' : (in_array((string) ($report->status ?? 'submitted'), ['revision','rejected'], true) ? 'warning' : 'primary') }}"><i class="bi {{ (string) ($report->status ?? 'submitted') === 'approved' ? 'bi-check2' : (in_array((string) ($report->status ?? 'submitted'), ['revision','rejected'], true) ? 'bi-arrow-counterclockwise' : 'bi-dot') }}"></i></span>
                                    <div>
                                        <div class="wrk-event-title">{{ $historyStatusLabels[(string) ($report->status ?? 'submitted')] ?? ucfirst(str_replace('_', ' ', (string) ($report->status ?? 'submitted'))) }}</div>
                                        <div class="wrk-event-meta">
                                            <span>{{ $report->user_name ?? 'Hệ thống' }}</span>
                                            @if(! empty($report->report_date))<span>Ngày thực hiện {{ $formatDate($report->report_date) }}</span>@endif
                                            @if(isset($report->progress_percent))<span>Tiến độ {{ (int) $report->progress_percent }}%</span>@endif
                                            @if(! empty($report->approver_name))<span>Duyệt bởi {{ $report->approver_name }}</span>@endif
                                        </div>
                                    </div>
                                    <time class="wrk-event-time">{{ $formatDateTime($report->approved_at ?? $report->submitted_at ?? $report->created_at ?? null) }}</time>
                                    @if(! empty($report->result_note) || ! empty($report->employee_note) || ! empty($report->manager_feedback) || ! empty($report->revision_reason))
                                        <details class="wrk-event-details">
                                            <summary><i class="bi bi-chevron-down me-1"></i>Xem nội dung tại mốc này</summary>
                                            <div class="wrk-event-content">@if(! empty($report->result_note))<b>Kết quả:</b> {{ $report->result_note }}
@endif
@if(! empty($report->employee_note))<b>Ghi chú nhân viên:</b> {{ $report->employee_note }}
@endif
@if(! empty($report->manager_feedback))<b>Phản hồi quản lý:</b> {{ $report->manager_feedback }}
@elseif(! empty($report->revision_reason))<b>Yêu cầu bổ sung:</b> {{ $report->revision_reason }}
@endif</div>
                                        </details>
                                    @endif
                                </div>
                            @endforeach

                            <div class="wrk-event">
                                <span class="wrk-event-dot"><i class="bi bi-plus"></i></span>
                                <div><div class="wrk-event-title">Công việc được giao</div><div class="wrk-event-meta"><span>{{ $requesterName }} giao cho {{ $assigneeName }}</span></div></div>
                                <time class="wrk-event-time">{{ $formatDateTime($task->created_at) }}</time>
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            <aside class="wrk-side">
                <section class="wrk-side-card wrk-action-card">
                    <div class="wrk-side-head"><div class="wrk-side-title"><i class="bi bi-lightning-charge"></i> Trung tâm xử lý</div></div>
                    <div class="wrk-side-body">
                        <div class="wrk-side-status">
                            <span class="wrk-side-status-icon"><i class="bi {{ $isApproved ? 'bi-patch-check' : ($isRevision ? 'bi-arrow-counterclockwise' : ($isSubmitted ? 'bi-hourglass-split' : 'bi-activity')) }}"></i></span>
                            <div><div class="wrk-side-status-title">{{ $statusLabels[$status] ?? $status }}</div><div class="wrk-side-status-sub">Tiến độ hiện tại {{ $progress }}%</div></div>
                        </div>

                        @if($isApproved)
                            <div class="wrk-banner wrk-banner-success tw:mb-0">
                                <span class="wrk-banner-icon"><i class="bi bi-check2-circle"></i></span>
                                <div><div class="wrk-banner-title">Đã duyệt hoàn thành</div><div class="wrk-banner-copy">{{ $formatDateTime($approvedAt) }}@if($managerFeedback)<br><span style="display:inline-block;margin-top:5px">{{ $managerFeedback }}</span>@endif</div></div>
                            </div>
                            @if($canEditResult)
                                <x-ui.button variant="outline-primary" size="none" class="tw:px-3 tw:py-[6px] tw:mt-2 wrk-primary-action" type="button" data-open-report-drawer><i class="bi bi-pencil-square"></i> Điều chỉnh báo cáo</x-ui.button>
                            @endif
                        @elseif($canApprove && $isSubmitted)
                            <div class="wrk-action-note">Báo cáo đã nộp và đang chờ quản lý xử lý.</div>
                            <div class="wrk-action-stack">
                                <x-ui.button variant="success" size="none" class="tw:px-3 tw:py-[6px] wrk-primary-action" type="button" data-toggle-action="approve"><i class="bi bi-check-circle"></i> Duyệt hoàn thành</x-ui.button>
                                <x-ui.button variant="outline-warning" size="none" class="tw:px-3 tw:py-[6px] wrk-primary-action" type="button" data-toggle-action="revision"><i class="bi bi-arrow-counterclockwise"></i> Yêu cầu bổ sung</x-ui.button>
                            </div>

                            <div id="wrkApprovePanel" class="wrk-action-panel">
                                <form method="POST" action="{{ route('tasks.approve', $task) }}">
                                    @csrf
                                    <label class="wrk-form-label">Nhận xét khi phê duyệt</label>
                                    <x-ui.input as="textarea" name="manager_feedback" rows="3" class="wrk-control" placeholder="Đánh giá kết quả hoặc ghi nhận thành tích...">{{ old('manager_feedback') }}</x-ui.input>
                                    <x-ui.button variant="success" size="none" class="tw:px-3 tw:py-[6px] tw:mt-2 wrk-primary-action" type="submit" onclick="return confirm('Xác nhận duyệt hoàn thành báo cáo này?')"><i class="bi bi-check2-circle"></i> Xác nhận duyệt</x-ui.button>
                                </form>
                            </div>

                            <div id="wrkRevisionPanel" class="wrk-action-panel">
                                <form method="POST" action="{{ route('tasks.return-revision', $task) }}" enctype="multipart/form-data" onsubmit="return confirm('Trả báo cáo cho nhân viên sửa đổi / bổ sung?')">
                                    @csrf
                                    <label class="wrk-form-label">Nội dung cần sửa / bổ sung <span class="tw:text-[#dc3545]">*</span></label>
                                    <x-ui.input as="textarea" name="revision_reason" rows="4" class="wrk-control" required placeholder="Nêu rõ nội dung chưa đạt hoặc file còn thiếu...">{{ old('revision_reason') }}</x-ui.input>
                                    <label class="wrk-form-label tw:mt-2">File hướng dẫn / file mẫu</label>
                                    <x-ui.input type="file" name="revision_attachments[]" class="wrk-control" multiple />
                                    <div class="wrk-help">Có thể gửi file mẫu, hình ảnh hoặc tài liệu hướng dẫn.</div>
                                    <x-ui.button variant="warning" size="none" class="tw:px-3 tw:py-[6px] tw:mt-2 wrk-primary-action" type="submit"><i class="bi bi-send"></i> Gửi yêu cầu bổ sung</x-ui.button>
                                </form>
                            </div>
                        @elseif($canApprove && $isRevision)
                            <div class="wrk-banner wrk-banner-warning tw:mb-0">
                                <span class="wrk-banner-icon"><i class="bi bi-hourglass-split"></i></span>
                                <div><div class="wrk-banner-title">Đang chờ nhân viên bổ sung</div><div class="wrk-banner-copy">{{ $returnReason ?: 'Yêu cầu bổ sung đã được gửi.' }}</div></div>
                            </div>
                        @elseif($canApprove)
                            <div class="wrk-action-note tw:mb-0">Chưa có báo cáo để duyệt. Nhân viên cần cập nhật kết quả và nộp báo cáo trước.</div>
                        @endif

                        @if($canEditResult && ! $isApproved)
                            <div class="wrk-divider"></div>
                            <x-ui.button variant="primary" size="none" class="tw:px-3 tw:py-[6px] wrk-primary-action" type="button" data-open-report-drawer><i class="bi bi-pencil-square"></i> {{ $isRevision ? 'Bổ sung báo cáo' : ($isSubmitted ? 'Chỉnh sửa báo cáo' : 'Cập nhật báo cáo') }}</x-ui.button>
                        @endif

                        @if(($canUpdate ?? false) && ! $isSubmitted && ! $isApproved && ! $isRevision)
                            <div class="wrk-divider"></div>
                            <form method="POST" action="{{ route('tasks.status', $task) }}">
                                @csrf @method('PATCH')
                                <div class="wrk-form-grid">
                                    <div><label class="wrk-form-label">Trạng thái</label><x-ui.select name="status" class="wrk-control"><option value="new" {{ $status === 'new' ? 'selected' : '' }}>Chưa bắt đầu</option><option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>Đang thực hiện</option></x-ui.select></div>
                                    <div><label class="wrk-form-label">Tiến độ (%)</label><x-ui.input type="number" name="progress_percent" class="wrk-control" value="{{ $progress }}" min="0" max="100" /></div>
                                </div>
                                <x-ui.button variant="outline-primary" size="none" class="tw:px-3 tw:py-[6px] tw:mt-2 wrk-primary-action" type="submit"><i class="bi bi-save"></i> Lưu tiến độ nhanh</x-ui.button>
                            </form>
                        @endif
                    </div>
                </section>

                <section class="wrk-side-card wrk-info-card">
                    <div class="wrk-side-head"><div class="wrk-side-title"><i class="bi bi-info-circle"></i> Thông tin nhanh</div></div>
                    <div class="wrk-side-body">
                        <div class="wrk-info-list">
                            <div class="wrk-info-row"><span class="wrk-info-key">Mã công việc</span><span class="wrk-info-value">#{{ $task->id }}</span></div>
                            <div class="wrk-info-row"><span class="wrk-info-key">Ngày giao</span><span class="wrk-info-value">{{ $formatDateTime($task->created_at) }}</span></div>
                            <div class="wrk-info-row"><span class="wrk-info-key">Hạn hoàn thành</span><span class="wrk-info-value {{ $isOverdue ? 'text-danger' : '' }}">{{ $formatDateTime($task->due_at) }}</span></div>
                            <div class="wrk-info-row"><span class="wrk-info-key">Ngày nộp</span><span class="wrk-info-value">{{ $formatDateTime($submittedAt) }}</span></div>
                            <div class="wrk-info-row"><span class="wrk-info-key">Ngày duyệt</span><span class="wrk-info-value">{{ $formatDateTime($approvedAt) }}</span></div>
                            <div class="wrk-info-row"><span class="wrk-info-key">Người duyệt</span><span class="wrk-info-value">{{ optional($task->approver)->name ?? '-' }}</span></div>
                            <div class="wrk-info-row"><span class="wrk-info-key">Cập nhật cuối</span><span class="wrk-info-value">{{ $formatDateTime($task->updated_at) }}</span></div>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</div>

@if($canEditResult)
    <div id="wrkDrawerBackdrop" class="wrk-drawer-backdrop" data-close-report-drawer></div>
    <aside id="wrkReportDrawer" class="wrk-drawer" aria-hidden="true">
        <div class="wrk-drawer-head">
            <div>
                <div class="wrk-drawer-title">{{ $isRevision ? 'Bổ sung và nộp lại báo cáo' : ($isSubmitted ? 'Chỉnh sửa báo cáo đã nộp' : ($isApproved ? 'Điều chỉnh báo cáo đã duyệt' : 'Cập nhật báo cáo công việc')) }}</div>
                <div class="wrk-drawer-sub">Công việc #{{ $task->id }} · {{ $task->title }}</div>
            </div>
            {{-- :border-base="false" + bỏ dấu ! — nếu ghim viền bằng !important thì nó đè
     luôn hover/active/focus của component, nút đứng im khi rê chuột. Đo được:
     viền giữ nguyên rgb(222,226,230) ở CẢ BỐN trạng thái. Sau khi sửa, trạng
     thái nghỉ y hệt còn hover/active/focus đổi màu như thiết kế. --}}
            <x-ui.button :border-base="false" variant="light" size="none"
                class="tw:border-[#dee2e6] wrk-btn wrk-btn-icon" type="button" data-close-report-drawer aria-label="Đóng"><i class="bi bi-x-lg"></i></x-ui.button>
        </div>

        <form method="POST" action="{{ route('tasks.submit', $task) }}" enctype="multipart/form-data" style="display:flex;flex:1;min-height:0;flex-direction:column" id="result-form">
            @csrf
            <div class="wrk-drawer-body">
                @if($isRevision)
                    <div class="wrk-banner wrk-banner-warning"><span class="wrk-banner-icon"><i class="bi bi-arrow-repeat"></i></span><div><div class="wrk-banner-title">Báo cáo đang được yêu cầu bổ sung</div><div class="wrk-banner-copy">Nội dung mới sẽ được lưu thành một mốc lịch sử riêng.</div></div></div>
                @elseif($isSubmitted)
                    <div class="wrk-banner" style="background:#eef7ff;border-color:#c8e4fb;color:#1d5f92"><span class="wrk-banner-icon" style="background:#dceeff"><i class="bi bi-info-circle"></i></span><div><div class="wrk-banner-title">Báo cáo đang chờ duyệt</div><div class="wrk-banner-copy">Bạn vẫn có thể cập nhật trước khi quản lý phê duyệt.</div></div></div>
                @elseif($isApproved && $canBossEdit)
                    <div class="wrk-banner" style="background:#eef7ff;border-color:#c8e4fb;color:#1d5f92"><span class="wrk-banner-icon" style="background:#dceeff"><i class="bi bi-shield-check"></i></span><div><div class="wrk-banner-title">Điều chỉnh bởi quản lý</div><div class="wrk-banner-copy">Báo cáo đã duyệt chỉ được cập nhật theo quyền quản lý.</div></div></div>
                @endif

                <div class="wrk-form-grid">
                    <div><label class="wrk-form-label">Ngày thực hiện</label><x-ui.input type="date" name="report_date" class="wrk-control" value="{{ $reportDateValue }}" /></div>
                    <div><label class="wrk-form-label">Tiến độ hoàn thành (%)</label><x-ui.input type="number" name="progress_percent" class="wrk-control" value="{{ old('progress_percent', $progress ?: 100) }}" min="0" max="100" /></div>
                </div>

                <div class="tw:mt-4"><label class="wrk-form-label">Kết quả thực hiện <span class="tw:text-[#dc3545]">*</span></label><x-ui.input as="textarea" name="result_note" rows="7" class="wrk-control" required placeholder="Mô tả rõ đầu việc đã hoàn thành, số liệu hoặc kết quả đạt được...">{{ old('result_note', $task->result_note) }}</x-ui.input></div>
                <div class="tw:mt-4"><label class="wrk-form-label">Khó khăn / ghi chú / đề xuất</label><x-ui.input as="textarea" name="employee_note" rows="4" class="wrk-control" placeholder="Nêu vấn đề cần hỗ trợ hoặc đề xuất xử lý tiếp theo...">{{ old('employee_note', optional($latestWorkReport)->employee_note ?? '') }}</x-ui.input></div>

                <div class="wrk-upload tw:mt-4">
                    <label class="wrk-form-label">File minh chứng / tài liệu kết quả</label>
                    <x-ui.input type="file" name="result_attachments[]" class="wrk-control" multiple />
                    <div class="wrk-help">Có thể chọn nhiều file, tối đa 50 MB cho mỗi file.</div>
                    @if($resultFiles->count())
                        <div class="form-check tw:mt-2"><input class="form-check-input" type="checkbox" name="clear_result_attachments" value="1" id="clear_result_attachments"><label class="form-check-label small tw:font-semibold" for="clear_result_attachments">Xóa toàn bộ file kết quả cũ trước khi tải file mới.</label></div>
                    @endif
                </div>
            </div>

            <div class="wrk-drawer-footer">
                <x-ui.button variant="outline-secondary" size="none" class="wrk-btn" type="button" data-close-report-drawer>Hủy</x-ui.button>
                <x-ui.button :variant="$isRevision ? 'warning' : 'primary'" size="none" class="wrk-btn wrk-btn-wide" type="submit"><i class="bi {{ $isRevision ? 'bi-arrow-repeat' : 'bi-send-check' }}"></i>{{ $isRevision ? 'Nộp lại báo cáo' : ($isSubmitted ? 'Lưu và cập nhật' : ($isApproved ? 'Lưu điều chỉnh' : 'Nộp báo cáo')) }}</x-ui.button>
            </div>
        </form>
    </aside>
@endif

<div id="wrkPreviewBackdrop" class="wrk-preview-backdrop">
    <div class="wrk-preview-modal" onclick="event.stopPropagation()">
        <div class="wrk-preview-head">
            <div id="wrkPreviewTitle" class="wrk-preview-title">Xem file</div>
            <div class="wrk-preview-actions">
                <x-ui.button variant="primary" size="none" class="wrk-btn" href="#" id="wrkPreviewDownload" target="_blank" rel="noopener"><i class="bi bi-download"></i> Tải file</x-ui.button>
                <x-ui.button variant="outline-secondary" size="none" class="wrk-btn" type="button" data-close-preview>Đóng</x-ui.button>
            </div>
        </div>
        <div class="wrk-preview-body"><iframe id="wrkPreviewFrame" class="wrk-preview-frame" src=""></iframe></div>
    </div>
</div>

<div class="wrk-mobile-dock">
    @if($canApprove && $isSubmitted)
        <x-ui.button variant="outline-warning" type="button" data-mobile-action="revision" class="wrk-dock-btn"><i class="bi bi-arrow-counterclockwise me-1"></i> Bổ sung</x-ui.button>
        <x-ui.button variant="success" type="button" data-mobile-action="approve" class="wrk-dock-btn"><i class="bi bi-check-circle me-1"></i> Duyệt</x-ui.button>
    @elseif($canEditResult)
        <x-ui.button :variant="$isRevision ? 'warning' : 'primary'" type="button" data-open-report-drawer class="wrk-dock-btn"><i class="bi bi-pencil-square me-1"></i> {{ $isRevision ? 'Bổ sung báo cáo' : 'Chỉnh sửa báo cáo' }}</x-ui.button>
    @else
        <x-ui.button variant="outline-primary" :href="route('tasks.my')" class="wrk-dock-btn"><i class="bi bi-arrow-left me-1"></i> Báo cáo việc</x-ui.button>
    @endif
</div>

<script>
(function () {
    const body = document.body;
    const drawer = document.getElementById('wrkReportDrawer');
    const drawerBackdrop = document.getElementById('wrkDrawerBackdrop');

    function openDrawer() {
        if (!drawer || !drawerBackdrop) return;
        drawerBackdrop.classList.add('show');
        drawer.classList.add('show');
        drawer.setAttribute('aria-hidden', 'false');
        body.classList.add('wrk-no-scroll');
    }

    function closeDrawer() {
        if (!drawer || !drawerBackdrop) return;
        drawer.classList.remove('show');
        drawerBackdrop.classList.remove('show');
        drawer.setAttribute('aria-hidden', 'true');
        body.classList.remove('wrk-no-scroll');
    }

    document.querySelectorAll('[data-open-report-drawer]').forEach(button => button.addEventListener('click', openDrawer));
    document.querySelectorAll('[data-close-report-drawer]').forEach(button => button.addEventListener('click', closeDrawer));

    function showActionPanel(type) {
        const approve = document.getElementById('wrkApprovePanel');
        const revision = document.getElementById('wrkRevisionPanel');
        if (!approve || !revision) return;
        const target = type === 'approve' ? approve : revision;
        const other = type === 'approve' ? revision : approve;
        other.classList.remove('show');
        target.classList.toggle('show');
        if (target.classList.contains('show')) {
            target.scrollIntoView({behavior:'smooth', block:'nearest'});
        }
    }

    document.querySelectorAll('[data-toggle-action]').forEach(button => {
        button.addEventListener('click', () => showActionPanel(button.dataset.toggleAction));
    });

    document.querySelectorAll('[data-mobile-action]').forEach(button => {
        button.addEventListener('click', () => {
            const type = button.dataset.mobileAction;
            const actionCard = document.querySelector('.wrk-action-card');
            if (actionCard) {
                actionCard.style.display = 'block';
                actionCard.scrollIntoView({behavior:'smooth', block:'start'});
                setTimeout(() => showActionPanel(type), 260);
            }
        });
    });

    const previewBackdrop = document.getElementById('wrkPreviewBackdrop');
    const previewFrame = document.getElementById('wrkPreviewFrame');
    const previewTitle = document.getElementById('wrkPreviewTitle');
    const previewDownload = document.getElementById('wrkPreviewDownload');

    function buildPreviewUrl(url, mime) {
        const absolute = new URL(url, window.location.origin).href;
        const lower = (absolute + ' ' + (mime || '')).toLowerCase();
        const office = lower.includes('.doc') || lower.includes('.xls') || lower.includes('.ppt') || lower.includes('spreadsheet') || lower.includes('wordprocessingml') || lower.includes('presentationml') || lower.includes('ms-excel') || lower.includes('msword') || lower.includes('powerpoint');
        return office ? 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(absolute) : absolute;
    }

    function openPreview(url, name, mime) {
        if (!previewBackdrop) return;
        const absolute = new URL(url, window.location.origin).href;
        previewTitle.textContent = name || 'Xem file';
        previewDownload.href = absolute;
        previewFrame.src = buildPreviewUrl(absolute, mime);
        previewBackdrop.classList.add('show');
        body.classList.add('wrk-no-scroll');
    }

    function closePreview() {
        if (!previewBackdrop) return;
        previewBackdrop.classList.remove('show');
        previewFrame.src = '';
        body.classList.remove('wrk-no-scroll');
    }

    document.querySelectorAll('.wrk-preview-btn').forEach(button => {
        button.addEventListener('click', () => openPreview(button.dataset.fileUrl, button.dataset.fileName, button.dataset.fileMime));
    });
    document.querySelectorAll('[data-close-preview]').forEach(button => button.addEventListener('click', closePreview));
    if (previewBackdrop) previewBackdrop.addEventListener('click', event => { if (event.target === previewBackdrop) closePreview(); });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closePreview();
            closeDrawer();
        }
    });

    @if($errors->any())
        openDrawer();
    @else
        if (window.location.hash === '#result-form') openDrawer();
    @endif
})();
</script>
@endsection
