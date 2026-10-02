@extends('layouts.app')

@section('content')
@php
    $filters = $filters ?? [];
    $search = $filters['search'] ?? request('q', '');
    $statusFilter = $filters['statusFilter'] ?? request('status', '');
    $priorityFilter = $filters['priorityFilter'] ?? request('priority', '');
    $dateFrom = $filters['dateFrom'] ?? request('date_from', '');
    $dateTo = $filters['dateTo'] ?? request('date_to', '');
    $unfinishedStatuses = ['new', 'in_progress', 'submitted', 'revision', 'rejected'];
@endphp

<style> .work-report-page{min-height:100vh;background:#f4f7fb;padding:18px 18px 46px;font-size:12px;color:#0f172a}.work-report-hero{position:relative;overflow:hidden;border-radius:22px;padding:20px 22px;color:#fff;background:radial-gradient(700px 260px at 88% 0%,rgba(34,211,238,.24),transparent 60%),linear-gradient(135deg,#020617,#075985 58%,#0f766e);box-shadow:0 16px 38px rgba(15,23,42,.16);margin-bottom:12px}.work-report-hero h1{font-size:24px;font-weight:950;letter-spacing:-.03em;margin:0}.work-report-hero p{font-size:12px;opacity:.87;margin:4px 0 0;font-weight:650}.summary-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:9px;margin-bottom:12px}.summary-card{display:block;text-decoration:none;background:#fff;border:1px solid #e5eaf1;border-radius:15px;padding:11px 12px;box-shadow:0 8px 22px rgba(15,23,42,.045);transition:.15s ease}.summary-card:hover{transform:translateY(-1px);box-shadow:0 12px 25px rgba(15,23,42,.075)}.summary-label{color:#64748b;font-size:10.5px;font-weight:850;margin-bottom:3px}.summary-value{font-size:23px;font-weight:950;line-height:1;letter-spacing:-.03em}.filter-card{display:grid;grid-template-columns:minmax(210px,1.5fr) repeat(4,minmax(135px,.8fr)) auto;gap:9px;align-items:end;background:#fff;border:1px solid #e5eaf1;border-radius:17px;padding:12px;margin-bottom:12px;box-shadow:0 8px 22px rgba(15,23,42,.04)}.filter-label{font-size:10.5px;font-weight:900;color:#475569;margin-bottom:4px}.filter-card .tm-input{font-size:12px;border-radius:11px;border-color:#dbe3ee;min-height:36px}.filter-actions{display:flex;gap:6px;white-space:nowrap}.btn-pill{border-radius:999px;font-weight:850;font-size:11.5px;padding:7px 12px}.report-card{background:#fff;border:1px solid #e5eaf1;border-radius:18px;overflow:hidden;box-shadow:0 10px 28px rgba(15,23,42,.055)}.report-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 15px;border-bottom:1px solid #e5eaf1}.report-title{font-size:14px;font-weight:950;margin:0}.report-desc{font-size:11px;color:#64748b;margin-top:2px}.result-count{font-weight:850;color:#475569;background:#f1f5f9;padding:5px 9px;border-radius:999px}.report-table-wrap{overflow:auto}.report-table{width:100%;border-collapse:separate;border-spacing:0;min-width:1180px}.report-table th{position:sticky;top:0;z-index:1;background:#f8fafc;color:#475569;text-transform:uppercase;letter-spacing:.025em;font-size:10px;font-weight:950;padding:10px 9px;border-bottom:1px solid #e5eaf1;white-space:nowrap}.report-table td{padding:11px 9px;border-bottom:1px solid #edf1f6;vertical-align:top;color:#334155}.report-table tr:last-child td{border-bottom:0}.report-table tbody tr:hover{background:#fbfdff}.report-table tbody tr.is-overdue{background:#fff8f8}.col-stt{width:44px;text-align:center}.task-name{font-size:12.5px;font-weight:950;color:#0f172a;text-decoration:none;line-height:1.35}.task-name:hover{color:#0369a1}.task-sub{color:#64748b;font-size:10.5px;margin-top:4px;line-height:1.35}.text-limit{max-width:250px;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden;white-space:pre-line;line-height:1.45}.note-limit{max-width:210px;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden;white-space:pre-line;line-height:1.45}.muted-dash{color:#94a3b8}.progress-box{min-width:100px}.progress{height:7px;border-radius:999px;background:#e2e8f0;overflow:hidden}.progress-bar{background:linear-gradient(90deg,#0ea5e9,#22c55e)}.soft-badge{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:900;white-space:nowrap}.st-new{background:#e0f2fe;color:#075985}.st-in_progress{background:#fef3c7;color:#92400e}.st-submitted{background:#ede9fe;color:#5b21b6}.st-revision,
    .st-rejected{background:#ffe4e6;color:#9f1239}.st-approved{background:#dcfce7;color:#166534}.pr-low{background:#f1f5f9;color:#475569}.pr-medium{background:#e0f2fe;color:#075985}.pr-high{background:#fee2e2;color:#991b1b}.overdue-badge{background:#be123c;color:#fff;margin-top:5px}.file-count{display:inline-flex;align-items:center;gap:4px;color:#075985;background:#e0f2fe;padding:4px 8px;border-radius:999px;font-weight:900;white-space:nowrap}.action-stack{display:flex;flex-direction:column;gap:5px;min-width:110px}.empty-state{padding:48px 18px;text-align:center;color:#64748b}.empty-icon{width:54px;height:54px;border-radius:18px;background:#eef6ff;color:#0369a1;display:inline-flex;align-items:center;justify-content:center;font-size:25px;margin-bottom:10px}.pagination-wrap{padding:11px 14px;border-top:1px solid #e5eaf1}@media(max-width:1450px){.summary-grid{grid-template-columns:repeat(4,1fr)}.filter-card{grid-template-columns:repeat(3,1fr)}}@media(max-width:850px){.work-report-page{padding:12px}.summary-grid{grid-template-columns:repeat(2,1fr)}.filter-card{grid-template-columns:1fr}.filter-actions{width:100%}.filter-actions .tm-btn{flex:1}.report-head{align-items:flex-start;flex-direction:column}.report-table-wrap{overflow:visible}.report-table{min-width:0;display:block}.report-table thead{display:none}.report-table tbody{display:grid;gap:10px;padding:10px;background:#f4f7fb}.report-table tr{display:block;background:#fff;border:1px solid #e5eaf1;border-radius:16px;overflow:hidden;box-shadow:0 7px 18px rgba(15,23,42,.04)}.report-table tbody tr.is-overdue{background:#fff8f8}.report-table td{display:grid;grid-template-columns:112px minmax(0,1fr);gap:10px;padding:8px 11px;border-bottom:1px dashed #e5eaf1}.report-table td:last-child{border-bottom:0}.report-table td::before{content:attr(data-label);font-size:10px;text-transform:uppercase;letter-spacing:.02em;color:#64748b;font-weight:950}.report-table td.col-stt{display:none}.text-limit,
    .note-limit{max-width:none;-webkit-line-clamp:4}.action-stack{flex-direction:row;flex-wrap:wrap}.action-stack .tm-btn{flex:1}.progress-box{min-width:0}.result-count{align-self:flex-start}}
</style>

<div class="work-report-page">
    <div class="work-report-hero">
        <h1>Báo cáo việc</h1>
        <p>Công việc được giao và báo cáo kết quả dùng chung một dữ liệu; cập nhật tại đây sẽ chuyển trực tiếp sang màn hình Giao việc để quản lý duyệt.</p>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[1rem]">{{ session('success') }}</x-ui.alert>
    @endif
    @if(session('error'))
        <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[1rem]">{{ session('error') }}</x-ui.alert>
    @endif

    <div class="summary-grid">
        <a class="summary-card" href="{{ route('tasks.my') }}"><div class="summary-label">Tổng công việc</div><div class="summary-value tw:text-[#0d6efd]">{{ $summary['total'] ?? 0 }}</div></a>
        <a class="summary-card" href="{{ route('tasks.my', ['status' => 'new']) }}"><div class="summary-label">Chưa báo cáo</div><div class="summary-value tw:text-[#0dcaf0]">{{ $summary['new'] ?? 0 }}</div></a>
        <a class="summary-card" href="{{ route('tasks.my', ['status' => 'in_progress']) }}"><div class="summary-label">Đang thực hiện</div><div class="summary-value tw:text-[#ffc107]">{{ $summary['in_progress'] ?? 0 }}</div></a>
        <a class="summary-card" href="{{ route('tasks.my', ['status' => 'submitted']) }}"><div class="summary-label">Chờ duyệt</div><div class="summary-value" style="color:#7c3aed">{{ $summary['submitted'] ?? 0 }}</div></a>
        <a class="summary-card" href="{{ route('tasks.my', ['status' => 'revision']) }}"><div class="summary-label">Cần bổ sung</div><div class="summary-value tw:text-[#dc3545]">{{ $summary['revision'] ?? 0 }}</div></a>
        <a class="summary-card" href="{{ route('tasks.my', ['status' => 'approved']) }}"><div class="summary-label">Đã duyệt</div><div class="summary-value tw:text-[#198754]">{{ $summary['approved'] ?? 0 }}</div></a>
        <a class="summary-card" href="{{ route('tasks.my', ['status' => 'overdue']) }}"><div class="summary-label">Quá hạn</div><div class="summary-value tw:text-[#dc3545]">{{ $summary['overdue'] ?? 0 }}</div></a>
    </div>

    <form method="GET" action="{{ route('tasks.my') }}" class="filter-card">
        <div><div class="filter-label">Tìm công việc</div><x-ui.input name="q" value="{{ $search }}" class="tm-input" placeholder="Tên việc, người giao, kết quả..." /></div>
        <div><div class="filter-label">Trạng thái</div><x-ui.select name="status" class="tm-input"><option value="">Tất cả</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" {{ $statusFilter === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach<option value="overdue" {{ $statusFilter === 'overdue' ? 'selected' : '' }}>Quá hạn</option></x-ui.select></div>
        <div><div class="filter-label">Ưu tiên</div><x-ui.select name="priority" class="tm-input"><option value="">Tất cả</option>@foreach($priorities as $key => $label)<option value="{{ $key }}" {{ $priorityFilter === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</x-ui.select></div>
        <div><div class="filter-label">Từ ngày giao</div><x-ui.input type="date" name="date_from" value="{{ $dateFrom }}" class="tm-input" /></div>
        <div><div class="filter-label">Đến ngày giao</div><x-ui.input type="date" name="date_to" value="{{ $dateTo }}" class="tm-input" /></div>
        <div class="filter-actions"><x-ui.button variant="primary" type="submit" size="none" class="tm-btn btn-pill tw:leading-[1.5]"><i class="bi bi-funnel"></i> Lọc</x-ui.button><x-ui.button href="{{ route('tasks.my') }}" variant="outline-secondary" size="none" class="tm-btn btn-pill tw:leading-[1.5]">Xóa lọc</x-ui.button></div>
    </form>

    <div class="report-card">
        <div class="report-head">
            <div><h2 class="report-title"><i class="bi bi-clipboard2-check"></i> Bảng báo cáo công việc</h2><div class="report-desc">Mỗi dòng là một công việc trong module Giao việc; bấm Báo cáo / cập nhật để nộp kết quả và file minh chứng.</div></div>
            <div class="result-count">{{ $tasks->total() }} công việc</div>
        </div>

        @if($tasks->count())
            <div class="report-table-wrap">
                <table class="report-table">
                    <thead><tr><th class="col-stt">STT</th><th>Tên công việc</th><th>Người giao</th><th>Ngày thực hiện</th><th>Tiến độ</th><th>Kết quả thực hiện</th><th>Ghi chú</th><th>File</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    @foreach($tasks as $task)
                        @php
                            $status = $task->status ?? 'new';
                            $priority = $task->priority ?? 'medium';
                            $progress = (int)($task->progress_percent ?? 0);
                            $isOverdue = !empty($task->due_at) && in_array($status, $unfinishedStatuses, true) && \Illuminate\Support\Carbon::parse($task->due_at)->isPast();
                            $latestReport = $latestReports->get($task->id);
                            $reportDateValue = optional($latestReport)->report_date ?? optional($task->submitted_at)->format('Y-m-d') ?? null;
                            $employeeNote = optional($latestReport)->employee_note;
                            $note = in_array($status, ['revision','rejected'], true) ? ($task->revision_reason ?? optional($latestReport)->revision_reason) : ($task->manager_feedback ?? $employeeNote);
                            $resultFileCount = (int)($resultAttachmentCounts[$task->id] ?? 0);
                        @endphp
                        <tr class="{{ $isOverdue ? 'is-overdue' : '' }}">
                            <td class="col-stt" data-label="STT">{{ $tasks->firstItem() + $loop->index }}</td>
                            <td data-label="Tên công việc"><a class="task-name" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a><div class="task-sub">Hạn: {{ $task->due_at ? \Illuminate\Support\Carbon::parse($task->due_at)->format('d/m/Y H:i') : 'Không đặt hạn' }}</div><span class="soft-badge pr-{{ $priority }} tw:mt-1">{{ $priorities[$priority] ?? $priority }}</span></td>
                            <td data-label="Người giao"><b>{{ optional($task->requester)->name ?? 'Không rõ' }}</b><div class="task-sub">{{ optional($task->created_at)->format('d/m/Y H:i') }}</div></td>
                            <td data-label="Ngày thực hiện">@if($reportDateValue){{ \Illuminate\Support\Carbon::parse($reportDateValue)->format('d/m/Y') }}@else<span class="muted-dash">Chưa báo cáo</span>@endif</td>
                            <td data-label="Tiến độ"><div class="progress-box"><div class="tw:flex tw:justify-between tw:mb-1"><span>{{ $progress }}%</span></div><div class="progress"><div class="progress-bar" style="width:{{ $progress }}%"></div></div></div></td>
                            <td data-label="Kết quả thực hiện"><div class="text-limit">{{ $task->result_note ?: 'Chưa có kết quả.' }}</div></td>
                            <td data-label="Ghi chú"><div class="note-limit {{ $note ? '' : 'muted-dash' }}">{{ $note ?: 'Không có ghi chú.' }}</div></td>
                            <td data-label="File">@if($resultFileCount > 0)<span class="file-count"><i class="bi bi-paperclip"></i> {{ $resultFileCount }} file</span>@else<span class="muted-dash">Chưa có</span>@endif</td>
                            <td data-label="Trạng thái"><span class="soft-badge st-{{ $status }}">{{ $statuses[$status] ?? $status }}</span>@if($isOverdue)<br><span class="soft-badge overdue-badge">Quá hạn</span>@endif</td>
                            <td data-label="Thao tác"><div class="action-stack"><x-ui.button href="{{ route('tasks.show', $task) }}{{ $status === 'approved' ? '' : '#result-form' }}" :variant="in_array($status,['revision','rejected'],true) ? 'warning' : 'outline-primary'" size="none" class="tm-btn btn-pill tw:leading-[1.5]">{{ in_array($status,['revision','rejected'],true) ? 'Bổ sung báo cáo' : ($status === 'approved' ? 'Xem báo cáo' : 'Báo cáo / cập nhật') }}</x-ui.button></div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $tasks->links() }}</div>
        @else
            <div class="empty-state"><div class="empty-icon"><i class="bi bi-inbox"></i></div><div class="tw:font-bold tw:text-[#212529]">Không có công việc phù hợp</div><div class="tw:mt-1">Thử xóa bộ lọc hoặc chờ quản lý giao công việc mới.</div></div>
        @endif
    </div>
</div>
@endsection
