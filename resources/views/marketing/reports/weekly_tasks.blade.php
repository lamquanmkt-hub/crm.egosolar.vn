@extends('layouts.app')

@section('title', 'Công việc hàng tuần')

@section('content')

<div class="container-fluid tw:px-6 weekly-task-page tw:mt-4">

 {{-- Header --}}
<div class="tw:flex flex-wrap tw:justify-between tw:items-end tw:gap-2 tw:mb-4">
    <div>
        <h3 class="tw:font-bold tw:mb-1">Công việc hàng tuần</h3>
        <div class="tw:text-[rgba(33,37,41,0.75)]">Tổng quan + danh sách công việc theo khoảng ngày</div>
    </div>

    <div class="tw:flex flex-wrap tw:gap-2 tw:items-end">
        {{-- Filter --}}
        <form method="GET" class="tw:flex flex-wrap tw:gap-2 tw:items-end wt-filter">
            <div>
                <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Từ ngày</x-ui.label>
                <x-ui.input type="date" name="from" class="wt-input"
                       value="{{ old('from', request('from')) }}" />
            </div>

            <div>
                <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Đến ngày</x-ui.label>
                <x-ui.input type="date" name="to" class="wt-input"
                       value="{{ old('to', request('to')) }}" />
            </div>

            <div>
                <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Hạng mục</x-ui.label>
                <x-ui.select name="category" class="wt-input">
                    <option value="">Tất cả</option>
                    @foreach($categories as $c)
                        <option value="{{ $c }}" {{ (request('category')===$c) ? 'selected' : '' }}>
                            {{ $c }}
                        </option>
                    @endforeach
                </x-ui.select>
            </div>

            <div>
                <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Trạng thái</x-ui.label>
                <x-ui.select name="status" class="wt-input">
                    <option value="">Tất cả</option>
                    <option value="pending" {{ request('status')==='pending' ? 'selected' : '' }}>Todo</option>
                    <option value="doing"   {{ request('status')==='doing' ? 'selected' : '' }}>Doing</option>
                    <option value="done"    {{ request('status')==='done' ? 'selected' : '' }}>Done</option>
                </x-ui.select>
            </div>

            <div class="pb-1 tw:flex tw:gap-2">
                <x-ui.button variant="none" size="none" class="btn-ego wt-btn" type="submit">
                    <i class="bi bi-funnel"></i> Lọc
                </x-ui.button>

                <x-ui.button variant="none" size="none" class="btn-ego-soft wt-btn" href="{{ route('marketing.reports.weekly-tasks') }}" title="Reset">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </x-ui.button>
            </div>
        </form>

        {{-- Nút tạo mới: để NGOÀI form để không bị form nuốt click --}}
        <div class="pb-1">
            <x-ui.button variant="none" size="none" class="btn-ego-soft wt-btn" href="{{ route('marketing.reports.weekly-tasks.create') }}">
                <i class="bi bi-plus-circle"></i> Thêm công việc
            </x-ui.button>
        </div>
    </div>
</div>


    {{-- Dashboard cards --}}
    <div class="tw:row tw:g-3 tw:mb-4">
        <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-3">
            <x-ui.card class="wt-card">
                <x-ui.card-body>
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="wt-label">Tổng task</div>
                            <div class="wt-value">{{ $total ?? 0 }}</div>
                        </div>
                        <div class="wt-icon"><i class="bi bi-list-task"></i></div>
                    </div>
                    <div class="wt-sub">Trong khoảng lọc hiện tại</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-3">
            <x-ui.card class="wt-card">
                <x-ui.card-body>
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="wt-label">Hoàn thành</div>
                            <div class="wt-value">{{ $done ?? 0 }}</div>
                        </div>
                        <div class="wt-icon"><i class="bi bi-check2-circle"></i></div>
                    </div>
                    <div class="wt-sub">Status: done</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-3">
            <x-ui.card class="wt-card">
                <x-ui.card-body>
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="wt-label">Đang làm</div>
                            <div class="wt-value">{{ $doing ?? 0 }}</div>
                        </div>
                        <div class="wt-icon"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                    <div class="wt-sub">Status: doing</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>

        <div class="tw:col12-12 tw:md:col12-6 tw:min-[75rem]:col12-3">
            <x-ui.card class="wt-card wt-card-danger">
                <x-ui.card-body>
                    <div class="tw:flex tw:justify-between tw:items-start">
                        <div>
                            <div class="wt-label">Quá hạn</div>
                            <div class="wt-value">{{ $overdue ?? 0 }}</div>
                        </div>
                        <div class="wt-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <div class="wt-sub">Due date &lt; hôm nay & chưa done</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>
    </div>

    {{-- Priority mini --}}
    <x-ui.card class="wt-card tw:mb-4">
        <x-ui.card-body>
            <div class="tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2">
                <div class="tw:font-semibold">
                    Ưu tiên:
                    <span class="wt-pill wt-high">High: {{ $priorityCount['high'] ?? 0 }}</span>
                    <span class="wt-pill wt-medium">Medium: {{ $priorityCount['medium'] ?? 0 }}</span>
                    <span class="wt-pill wt-low">Low: {{ $priorityCount['low'] ?? 0 }}</span>
                </div>
                <div class="tw:text-[rgba(33,37,41,0.75)] small">
                    Tip: Thêm chart (pie/bar) sau.
                </div>
            </div>
        </x-ui.card-body>
    </x-ui.card>

    {{-- Table --}}
    <x-ui.card class="wt-card">
        <x-ui.card-header class="wt-card-header tw:flex tw:justify-between tw:items-center">
            <div class="tw:font-bold">Task list</div>
            <div class="small tw:text-[rgba(33,37,41,0.75)]">Hiển thị: {{ $taskCount }} dòng</div>
        </x-ui.card-header>

        <x-ui.card-body class="tw:p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle tw:mb-0 wt-table">
                    <thead>
                        <tr>
                            <th style="width: 64px;">STT</th>
                            <th>Tên công việc</th>
                            <th style="width: 120px;">Priority</th>
                            <th style="width: 140px;">Hạng mục</th>
                            <th style="width: 230px;">Chịu trách nhiệm</th>
                            <th style="width: 120px;">Ngày bắt đầu</th>
                            <th style="width: 120px;">Hạn</th>
                            <th style="width: 120px;">Trạng thái</th>
                            <th style="width: 260px;" class="tw:text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($taskRows as $row)

                        <tr>
                            {{-- ✅ STT theo danh sách hiển thị --}}
                            <td class="tw:text-[rgba(33,37,41,0.75)]! tw:font-semibold">{{ $loop->iteration }}</td>

                            {{-- Title: click xem chi tiết --}}
                            <td class="tw:font-semibold">
                                <a class="wt-link" href="{{ route('marketing.reports.weekly-tasks.show', $row->id) }}">
                                    {{ $row->title }}
                                </a>
                            </td>

                            <td>
                                <span class="badge {{ $row->priorityBadge }} wt-badge">{{ $row->priorityText }}</span>
                            </td>

                            <td class="tw:text-[rgba(33,37,41,0.75)]!">{{ $row->category }}</td>

                            <td class="wt-ellipsis" title="{{ $row->assignee }}">
                                {{ $row->assignee }}
                            </td>

                            <td class="tw:text-[rgba(33,37,41,0.75)]! wt-nowrap">{{ $row->startDateText }}</td>
                            <td class="tw:text-[rgba(33,37,41,0.75)]! wt-nowrap">{{ $row->dueDateText }}</td>

                            <td>
                                <span class="badge {{ $row->statusBadge }} wt-badge">{{ $row->statusText }}</span>
                            </td>

                            <td class="tw:text-right">
                                <div class="tw:inline-flex tw:gap-2">

                                    {{-- Xem --}}
                                    <x-ui.button variant="none" size="sm" class="btn-ego-soft wt-action-btn" href="{{ route('marketing.reports.weekly-tasks.show', $row->id) }}">
                                        <i class="bi bi-eye"></i> Xem
                                    </x-ui.button>

                                    {{-- Sửa (chỉ 1 nút) --}}
                                    <x-ui.button variant="none" size="sm" class="btn-ego-soft wt-action-btn" href="{{ route('marketing.reports.weekly-tasks.edit', $row->id) }}">
                                        <i class="bi bi-pencil-square"></i> Sửa
                                    </x-ui.button>

                                    {{-- Xóa --}}
                                    <form method="POST"
                                          action="{{ route('marketing.reports.weekly-tasks.destroy', $row->id) }}"
                                          class="wt-del-form m-0">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="danger" size="sm" type="submit" class="wt-action-btn wt-danger">
                                            <i class="bi bi-trash"></i> Xóa
                                        </x-ui.button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-6">
                                Chưa có dữ liệu.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card-body>
    </x-ui.card>

</div>
@endsection

@push('styles')
<style> /* ===== Weekly Task - EGO style ===== */ .weekly-task-page{
    --ego: #0E7C86;
    --ego2: #0B5E66;
    --border: rgba(12, 92, 100, .10);
    --text: #0f172a;
    --muted: #64748b;
}.weekly-task-page .wt-filter .wt-input{
    border-radius: 12px;
    border: 1px solid var(--border);
    min-height: 42px;
}.weekly-task-page .wt-btn{
    height: 42px;
    display:inline-flex;
    align-items:center;
    gap: 8px;
    border-radius: 12px;
    font-weight: 900;
    padding: 0 14px;
}.weekly-task-page .btn-ego{
    background: linear-gradient(135deg, var(--ego), var(--ego2));
    border: none;
    color: #fff;
    box-shadow: 0 10px 22px rgba(14, 124, 134, .18);
}.weekly-task-page .btn-ego:hover{ filter: brightness(.98); color:#fff; }.weekly-task-page .btn-ego-soft{
    background: rgba(14, 124, 134, .10);
    border: 1px solid var(--border);
    color: var(--ego2);
    border-radius: 12px;
    font-weight: 900;
}.weekly-task-page .wt-card{
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .04);
}.weekly-task-page .wt-card-danger{
    border-color: rgba(220, 53, 69, .20);
}.weekly-task-page .wt-label{
    font-size: 13px;
    color: var(--muted);
    font-weight: 800;
}.weekly-task-page .wt-value{
    font-size: 30px;
    font-weight: 900;
    color: var(--text);
    line-height: 1.1;
}.weekly-task-page .wt-sub{
    margin-top: 6px;
    font-size: 12px;
    color: var(--muted);
}.weekly-task-page .wt-icon{
    width: 44px; height: 44px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: rgba(14, 124, 134, .12);
    color: var(--ego2);
    font-size: 20px;
}.weekly-task-page .wt-pill{
    display: inline-flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 999px;
    font-weight: 900;
    font-size: 12px;
    margin-left: 8px;
    border: 1px solid var(--border);
}.weekly-task-page .wt-high{ background: rgba(220,53,69,.10); color: #b4232c; border-color: rgba(220,53,69,.18); }.weekly-task-page .wt-medium{ background: rgba(255,193,7,.18); color: #7a5b00; border-color: rgba(255,193,7,.25); }.weekly-task-page .wt-low{ background: rgba(13,202,240,.16); color: #075d6d; border-color: rgba(13,202,240,.22); }.weekly-task-page .wt-card-header{
    background: linear-gradient(135deg, rgba(14,124,134,.10), rgba(14,124,134,.03));
    border-bottom: 1px solid var(--border);
    padding: 14px 16px;
}.weekly-task-page .wt-table thead th{
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .4px;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}.weekly-task-page .wt-table td{
    border-top: 1px solid rgba(15,23,42,.06);
    padding: 12px 12px;
}.weekly-task-page .wt-badge{
    border-radius: 999px;
    padding: 7px 10px;
    font-weight: 900;
    letter-spacing: .2px;
}.weekly-task-page .wt-link{
    text-decoration: none;
    color: var(--text);
}.weekly-task-page .wt-link:hover{
    color: var(--ego2);
    text-decoration: underline;
}.weekly-task-page .wt-nowrap{
    white-space: nowrap;
}.weekly-task-page .wt-ellipsis{
    max-width: 240px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}.weekly-task-page .wt-action-btn{
    border-radius: 12px;
    font-weight: 900;
    padding: 7px 10px;
    height: 38px;
    display:inline-flex;
    align-items:center;
    gap: 8px;
}.weekly-task-page .wt-danger{
    box-shadow: 0 10px 18px rgba(220,53,69,.12);
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.wt-del-form').forEach(form => {
    form.addEventListener('submit', (e) => {
      if (!confirm('Bạn chắc chắn muốn xóa công việc này? Hành động không thể hoàn tác.')) {
        e.preventDefault();
      }
    });
  });
});
</script>
@endpush
