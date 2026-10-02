@extends('layouts.app')

@section('content')
@php
    $priorityOptions = $priorities ?? [
        'low' => 'Thấp',
        'medium' => 'Bình thường',
        'high' => 'Cao',
    ];
    $selectedIds = array_map('strval', (array) old('assignee_ids', []));
@endphp

<style> .tcv6{--ink:#102139;--muted:#75849a;--line:#e3e9f0;--teal:#0b776f;min-height:100vh;background:#f3f6fa;padding:15px 18px 96px;color:var(--ink);font-size:13px}.tcv6-shell{max-width:1510px;margin:auto}.tcv6-head{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:6px 2px 13px}.tcv6-eyebrow{font-size:10px;text-transform:uppercase;letter-spacing:.12em;font-weight:900;color:#0f8d86}.tcv6-title{font-size:26px;font-weight:950;letter-spacing:-.04em;margin:3px 0 0}.tcv6-sub{font-size:12px;color:var(--muted);font-weight:650;margin-top:4px}.tcv6-back{display:inline-flex;align-items:center;gap:6px;color:#4a5c73;text-decoration:none;font-size:11px;font-weight:900;background:#fff;border:1px solid #dce3eb;border-radius:10px;padding:8px 11px}.tcv6-layout{display:grid;grid-template-columns:minmax(0,1fr) 338px;gap:13px;align-items:start}.tcv6-sheet,
    .tcv6-summary{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.045);overflow:hidden}.tcv6-sheet-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-bottom:1px solid var(--line);background:#fbfcfd}.tcv6-sheet-title{font-size:14px;font-weight:950}.tcv6-sheet-note{font-size:10px;color:#7b899c;margin-top:2px}.tcv6-progress{display:flex;gap:5px}.tcv6-progress span{width:26px;height:4px;border-radius:99px;background:#dfe6ed}.tcv6-progress span:first-child{background:var(--teal)}.tcv6-section{display:grid;grid-template-columns:180px minmax(0,1fr);gap:20px;padding:18px 18px;border-bottom:1px solid #edf1f5}.tcv6-section:last-child{border-bottom:0}.tcv6-section-label{display:flex;gap:10px;align-items:flex-start}.tcv6-step{width:28px;height:28px;border-radius:9px;background:#eaf8f6;color:var(--teal);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:950;flex:0 0 auto}.tcv6-section-title{font-size:12px;font-weight:950;margin-top:1px}.tcv6-section-sub{font-size:10px;color:#8290a2;line-height:1.45;margin-top:3px}.tcv6-fields{min-width:0}.tcv6-grid2{display:grid;grid-template-columns:1fr 1fr;gap:11px}.tcv6-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:11px}.tcv6-label{font-size:11px;font-weight:900;color:#40536b;margin-bottom:5px}.tcv6-required{color:#e11d48}.tcv6 .tc-input{min-height:40px;border-radius:10px;border-color:#dce3eb;font-size:12px}.tcv6 textarea.tc-input{min-height:118px;resize:vertical;line-height:1.55}.tcv6 .tc-input:focus{border-color:#3ba9a2;box-shadow:0 0 0 .2rem rgba(15,141,134,.1)}.tcv6-title-input{font-size:15px!important;font-weight:850}.tcv6-help{font-size:10px;color:#8a97a8;margin-top:5px}.tcv6-picker{position:relative}.tcv6-search{position:relative}.tcv6-search i{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#7c8b9e}.tcv6-search input{padding-left:36px}.tcv6-results{display:none;position:absolute;left:0;right:0;top:45px;z-index:50;max-height:280px;overflow:auto;background:#fff;border:1px solid #dbe3ec;border-radius:13px;padding:6px;box-shadow:0 18px 46px rgba(15,23,42,.17)}.tcv6-results.show{display:block}.tcv6-user{display:grid;grid-template-columns:34px minmax(0,1fr) 28px;gap:9px;align-items:center;padding:8px;border-radius:10px;cursor:pointer}.tcv6-user:hover{background:#f1f6f7}.tcv6-avatar{width:34px;height:34px;border-radius:10px;background:#edf3f7;color:#36516e;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:950}.tcv6-user-name{font-weight:900;font-size:11px}.tcv6-user-meta{font-size:9px;color:#7b899c;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.tcv6-plus{width:27px;height:27px;border-radius:8px;background:#eaf8f6;color:var(--teal);display:flex;align-items:center;justify-content:center}.tcv6-selected-wrap{margin-top:10px;border:1px solid #e3e9f0;background:#f9fbfc;border-radius:12px;padding:9px;min-height:54px}.tcv6-selected-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:7px}.tcv6-selected-label{font-size:10px;color:#7a899c;font-weight:900}.tcv6-selected-count{font-size:9px;font-weight:900;color:var(--teal);background:#eaf8f6;border-radius:7px;padding:3px 6px}.tcv6-selected{display:flex;flex-wrap:wrap;gap:6px}.tcv6-chip{display:inline-flex;align-items:center;gap:6px;background:#fff;border:1px solid #cfe4e2;border-radius:9px;padding:6px 7px;color:#234b4a;font-size:10px;font-weight:900}.tcv6-chip button{border:0;background:transparent;color:#718096;padding:0;line-height:1}.tcv6-empty{font-size:10px;color:#98a4b3;padding:4px}.tcv6-mode{display:flex;align-items:flex-start;gap:9px;margin-top:10px;padding:9px 10px;border-radius:10px;background:#f0f9ff;border:1px solid #cceaf8;color:#24536a;font-size:10px;line-height:1.45}.tcv6-mode i{font-size:14px;margin-top:1px}.tcv6-upload{border:1.5px dashed #c9d4df;border-radius:13px;background:#f9fbfc;padding:14px;text-align:center;cursor:pointer;transition:.15s}.tcv6-upload:hover,
    .tcv6-upload.drag{border-color:#46aaa4;background:#f0fbfa}.tcv6-upload-icon{width:38px;height:38px;border-radius:11px;background:#eaf8f6;color:var(--teal);display:inline-flex;align-items:center;justify-content:center;font-size:19px;margin-bottom:6px}.tcv6-file-input{display:none}.tcv6-files{display:grid;gap:6px;margin-top:8px}.tcv6-file{display:flex;align-items:center;justify-content:space-between;gap:10px;border:1px solid #e2e8ef;border-radius:10px;padding:7px 9px}.tcv6-file-name{font-size:10px;font-weight:900;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.tcv6-file-meta{font-size:9px;color:#8491a2}.tcv6-file button{border:0;background:#feecef;color:#bc2941;width:26px;height:26px;border-radius:7px}.tcv6-side{position:sticky;top:82px}.tcv6-summary-head{padding:14px 15px;border-bottom:1px solid var(--line);background:linear-gradient(135deg,#0b1d33,#0c716d);color:#fff}.tcv6-summary-kicker{font-size:9px;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.68);font-weight:900}.tcv6-summary-title{font-size:14px;font-weight:950;margin-top:3px}.tcv6-summary-body{padding:13px}.tcv6-preview-title{font-size:15px;line-height:1.35;font-weight:950;color:#12233a;min-height:40px;word-break:break-word}.tcv6-status{display:inline-flex;align-items:center;gap:5px;border-radius:7px;background:#e6f5fd;color:#075985;padding:4px 7px;font-size:9px;font-weight:950;margin-top:8px}.tcv6-summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin-top:12px}.tcv6-summary-box{border:1px solid #e4eaf1;border-radius:11px;padding:9px;background:#fafcfd}.tcv6-summary-label{font-size:8px;text-transform:uppercase;letter-spacing:.06em;color:#8794a5;font-weight:900}.tcv6-summary-value{font-size:11px;font-weight:950;color:#263b54;margin-top:3px}.tcv6-summary-block{margin-top:12px;padding-top:12px;border-top:1px solid #edf1f5}.tcv6-summary-block-title{font-size:10px;font-weight:950;margin-bottom:8px}.tcv6-assignee-preview{font-size:10px;color:#596b81;line-height:1.5}.tcv6-flow{display:grid;grid-template-columns:repeat(4,1fr);position:relative}.tcv6-flow:before{content:"";position:absolute;top:12px;left:10%;right:10%;height:2px;background:#e2e8ef}.tcv6-flow-item{position:relative;text-align:center;z-index:1}.tcv6-flow-dot{width:25px;height:25px;margin:auto;border-radius:50%;background:#fff;border:2px solid #cdd7e2;color:#738397;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:950}.tcv6-flow-item:first-child .tcv6-flow-dot{border-color:var(--teal);background:#eaf8f6;color:var(--teal)}.tcv6-flow-label{font-size:8px;color:#748399;font-weight:850;margin-top:5px}.tcv6-ready{margin-top:11px;border-radius:11px;padding:9px 10px;background:#fff8e8;border:1px solid #f6df9d;color:#7a5610;font-size:10px;display:flex;gap:8px}.tcv6-ready.ok{background:#edf9f0;border-color:#bfe9c8;color:#216638}.tcv6-footer{position:fixed;left:var(--sidebar-width,240px);right:0;bottom:0;z-index:100;background:rgba(255,255,255,.96);backdrop-filter:blur(12px);border-top:1px solid #dce3eb;padding:10px 18px}.tcv6-footer-inner{max-width:1510px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:12px}.tcv6-footer-note{font-size:10px;color:#748399}.tcv6-footer-actions{display:flex;gap:7px}.tcv6-btn{min-height:37px;border-radius:10px;font-size:11px;font-weight:900;padding:7px 13px}@media(max-width:1180px){.tcv6-layout{grid-template-columns:1fr}.tcv6-side{position:static}.tcv6-footer{left:0}}@media(max-width:760px){.tcv6{padding:12px 12px 105px}.tcv6-head{align-items:flex-start;flex-direction:column}.tcv6-section{grid-template-columns:1fr;gap:10px;padding:14px}.tcv6-section-label{align-items:center}.tcv6-grid2,
    .tcv6-grid3{grid-template-columns:1fr}.tcv6-footer-inner{align-items:flex-start;flex-direction:column}.tcv6-footer-actions{width:100%}.tcv6-footer-actions .tcv6-btn{flex:1}}
</style>

<div class="tcv6">
    <div class="tcv6-shell">
        <header class="tcv6-head">
            <div>
                <div class="tcv6-eyebrow">Tạo đầu việc mới</div>
                <h1 class="tcv6-title">Phiếu giao việc</h1>
                <div class="tcv6-sub">Giao rõ người, rõ hạn và rõ kết quả cần bàn giao.</div>
            </div>
            <a href="{{ route('tasks.index') }}" class="tcv6-back">
                <i class="bi bi-arrow-left"></i> Quay lại danh sách
            </a>
        </header>

        @if ($errors->any())
            <x-ui.alert variant="danger" class="tw:rounded-[1rem]">
                <strong>Chưa thể giao việc.</strong>
                <ul class="tw:mb-0 tw:mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form id="taskCreateForm" method="POST" action="{{ route('tasks.store') }}" enctype="multipart/form-data">
            @csrf

            {{-- EGO_PROJECT_TASK_LINK_START --}}
            @if(!empty($projectSite))
                <input type="hidden" name="site_id" value="{{ $projectSite->id }}">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:10px;padding:10px 12px;border:1px solid #bfe3df;background:#effaf8;border-radius:12px;">
                    <div>
                        <div style="font-size:9px;text-transform:uppercase;letter-spacing:.08em;font-weight:900;color:#0b776f;">Công việc thuộc dự án</div>
                        <strong style="font-size:12px;color:#173d3a;">{{ $projectSite->project_code ?: 'DA-SITE-'.$projectSite->id }} · {{ $projectSite->name }}</strong>
                    </div>
                    <a href="{{ route('projects-unified.show', $projectSite) }}" style="font-size:10px;font-weight:900;color:#0b776f;text-decoration:none;">Mở dự án</a>
                </div>
            @endif
            {{-- EGO_PROJECT_TASK_LINK_END --}}
            <div class="tcv6-layout">
                <main class="tcv6-sheet">
                    <div class="tcv6-sheet-head">
                        <div>
                            <div class="tcv6-sheet-title">Thông tin phiếu</div>
                            <div class="tcv6-sheet-note">Các trường có dấu * là bắt buộc.</div>
                        </div>
                        <div class="tcv6-progress" aria-hidden="true">
                            <span></span><span></span><span></span><span></span>
                        </div>
                    </div>

                    <section class="tcv6-section">
                        <div class="tcv6-section-label">
                            <span class="tcv6-step">1</span>
                            <div>
                                <div class="tcv6-section-title">Nội dung công việc</div>
                                <div class="tcv6-section-sub">Nêu đầu việc và đầu ra cần bàn giao.</div>
                            </div>
                        </div>

                        <div class="tcv6-fields">
                            <div>
                                <label class="tcv6-label" for="taskTitle">
                                    Tiêu đề công việc <span class="tcv6-required">*</span>
                                </label>
                                <x-ui.input
                                    id="taskTitle"
                                    type="text"
                                    name="title"
                                    value="{{ old('title', request('title')) }}"
                                    class="tc-input tcv6-title-input"
                                    maxlength="255"
                                    placeholder="Ví dụ: Hoàn thiện báo cáo tồn kho tháng 8"
                                    required />
                            </div>

                            <div class="tcv6-grid2 tw:mt-4">
                                <div>
                                    <label class="tcv6-label" for="taskDescription">Mô tả / yêu cầu</label>
                                    <x-ui.input as="textarea"
                                        id="taskDescription"
                                        name="description"
                                        class="tc-input"
                                        placeholder="Việc cần làm, kết quả cần bàn giao, tiêu chuẩn hoàn thành..."
                                    >{{ old('description') }}</x-ui.input>
                                </div>

                                <div>
                                    <div>
                                        <label class="tcv6-label" for="taskLink">Link liên quan</label>
                                        <x-ui.input
                                            id="taskLink"
                                            type="url"
                                            name="link_url"
                                            value="{{ old('link_url') }}"
                                            class="tc-input"
                                            placeholder="https://..." />
                                    </div>

                                    <div class="tw:mt-4">
                                        <label class="tcv6-label">Phạm vi giao việc</label>
                                        <x-ui.input
                                            class="tc-input"
                                            value="{{ $isDepartmentScoped && $managedDepartment ? $managedDepartment->name : 'Toàn công ty theo quyền quản lý' }}"
                                            disabled />
                                    </div>

                                    <div class="tcv6-help">
                                        <i class="bi bi-shield-check"></i>
                                        Hệ thống chỉ hiển thị nhân sự bạn được quyền quản lý.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="tcv6-section">
                        <div class="tcv6-section-label">
                            <span class="tcv6-step">2</span>
                            <div>
                                <div class="tcv6-section-title">Người thực hiện</div>
                                <div class="tcv6-section-sub">Tìm theo tên, email hoặc phòng ban.</div>
                            </div>
                        </div>

                        <div class="tcv6-fields">
                            <div class="tcv6-picker" id="assigneePicker">
                                <label class="tcv6-label" for="assigneeSearch">
                                    Người nhận việc <span class="tcv6-required">*</span>
                                </label>

                                <div class="tcv6-search">
                                    <i class="bi bi-search"></i>
                                    <x-ui.input
                                        id="assigneeSearch"
                                        type="search"
                                        class="tc-input"
                                        autocomplete="off"
                                        placeholder="Tìm nhân sự..." />

                                    <div class="tcv6-results" id="assigneeResults">
                                        @foreach ($users as $user)
                                            <div
                                                class="tcv6-user"
                                                data-user-row
                                                data-id="{{ $user->id }}"
                                                data-name="{{ $user->name }}"
                                                data-search="{{ \Illuminate\Support\Str::lower(trim($user->name.' '.$user->email.' '.optional($user->department)->name)) }}"
                                            >
                                                <span class="tcv6-avatar">
                                                    {{ mb_strtoupper(mb_substr(trim($user->name), 0, 2)) ?: 'NV' }}
                                                </span>
                                                <div>
                                                    <div class="tcv6-user-name">{{ $user->name }}</div>
                                                    <div class="tcv6-user-meta">
                                                        {{ $user->email }}
                                                        @if (optional($user->department)->name)
                                                            · {{ optional($user->department)->name }}
                                                        @endif
                                                    </div>
                                                </div>
                                                <span class="tcv6-plus"><i class="bi bi-plus-lg"></i></span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="tcv6-selected-wrap">
                                    <div class="tcv6-selected-head">
                                        <span class="tcv6-selected-label">Đã chọn</span>
                                        <span class="tcv6-selected-count"><strong id="selectedCount">0</strong> người</span>
                                    </div>

                                    <div class="tcv6-selected" id="selectedAssignees">
                                        @foreach ($users as $user)
                                            @if (in_array((string) $user->id, $selectedIds, true))
                                                <span
                                                    class="tcv6-chip"
                                                    data-selected-user
                                                    data-id="{{ $user->id }}"
                                                    data-name="{{ $user->name }}"
                                                >
                                                    <i class="bi bi-person-check"></i>
                                                    {{ $user->name }}
                                                    <button type="button" aria-label="Bỏ chọn {{ $user->name }}">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>

                                    <div class="tcv6-empty" id="selectedEmpty">Chưa chọn người thực hiện.</div>
                                </div>

                                <div id="assigneeInputs"></div>

                                <div class="tcv6-mode">
                                    <i class="bi bi-diagram-3"></i>
                                    <div>
                                        <strong>Mỗi người tạo một phiếu riêng.</strong><br>
                                        Nhân viên báo cáo và quản lý duyệt độc lập trong cùng danh sách.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="tcv6-section">
                        <div class="tcv6-section-label">
                            <span class="tcv6-step">3</span>
                            <div>
                                <div class="tcv6-section-title">Thời gian & ưu tiên</div>
                                <div class="tcv6-section-sub">Đặt hạn rõ để hệ thống cảnh báo.</div>
                            </div>
                        </div>

                        <div class="tcv6-fields">
                            <div class="tcv6-grid3">
                                <div>
                                    <label class="tcv6-label" for="taskPriority">Mức ưu tiên</label>
                                    <x-ui.select id="taskPriority" name="priority" class="tc-input">
                                        @foreach ($priorityOptions as $key => $label)
                                            <option
                                                value="{{ $key }}"
                                                {{ old('priority', 'medium') === $key ? 'selected' : '' }}
                                            >{{ $label }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>

                                <div>
                                    <label class="tcv6-label" for="taskDue">Hạn hoàn thành</label>
                                    <x-ui.input
                                        id="taskDue"
                                        type="date"
                                        name="due_at"
                                        value="{{ old('due_at') }}"
                                        class="tc-input" />
                                </div>

                                <div>
                                    <label class="tcv6-label">Trạng thái khởi tạo</label>
                                    <x-ui.input class="tc-input" value="Mới giao" disabled />
                                </div>
                            </div>
                            <div id="dueWarning" class="tcv6-help"></div>
                        </div>
                    </section>

                    <section class="tcv6-section">
                        <div class="tcv6-section-label">
                            <span class="tcv6-step">4</span>
                            <div>
                                <div class="tcv6-section-title">Tài liệu giao việc</div>
                                <div class="tcv6-section-sub">Excel, Word, PDF, ảnh hoặc ZIP.</div>
                            </div>
                        </div>

                        <div class="tcv6-fields">
                            <div class="tcv6-upload" id="uploadZone">
                                <div class="tcv6-upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                <div class="tw:font-bold">Thả file vào đây hoặc bấm để chọn</div>
                                <div class="tcv6-help">Tối đa 50MB/file · có thể chọn nhiều file</div>
                                <input
                                    id="taskFiles"
                                    class="tcv6-file-input"
                                    type="file"
                                    name="attachments[]"
                                    multiple
                                >
                            </div>
                            <div class="tcv6-files" id="taskFileList"></div>
                        </div>
                    </section>
                </main>

                <aside class="tcv6-side">
                    <section class="tcv6-summary">
                        <div class="tcv6-summary-head">
                            <div class="tcv6-summary-kicker">Xem trước tức thời</div>
                            <div class="tcv6-summary-title">Tóm tắt phiếu giao việc</div>
                        </div>

                        <div class="tcv6-summary-body">
                            <div class="tcv6-preview-title" id="previewTitle">Chưa nhập tiêu đề</div>
                            <span class="tcv6-status"><i class="bi bi-send"></i>Mới giao</span>

                            <div class="tcv6-summary-grid">
                                <div class="tcv6-summary-box">
                                    <div class="tcv6-summary-label">Người nhận</div>
                                    <div class="tcv6-summary-value" id="previewCount">0 người</div>
                                </div>
                                <div class="tcv6-summary-box">
                                    <div class="tcv6-summary-label">Hạn hoàn thành</div>
                                    <div class="tcv6-summary-value" id="previewDue">Chưa đặt</div>
                                </div>
                                <div class="tcv6-summary-box">
                                    <div class="tcv6-summary-label">Ưu tiên</div>
                                    <div class="tcv6-summary-value" id="previewPriority">Bình thường</div>
                                </div>
                                <div class="tcv6-summary-box">
                                    <div class="tcv6-summary-label">Tài liệu</div>
                                    <div class="tcv6-summary-value" id="previewFiles">0 file</div>
                                </div>
                            </div>

                            <div class="tcv6-summary-block">
                                <div class="tcv6-summary-block-title">Người thực hiện</div>
                                <div class="tcv6-assignee-preview" id="previewAssignees">Chưa chọn</div>
                            </div>

                            <div class="tcv6-summary-block">
                                <div class="tcv6-summary-block-title">Luồng xử lý đồng bộ</div>
                                <div class="tcv6-flow">
                                    <div class="tcv6-flow-item"><span class="tcv6-flow-dot">1</span><div class="tcv6-flow-label">Mới giao</div></div>
                                    <div class="tcv6-flow-item"><span class="tcv6-flow-dot">2</span><div class="tcv6-flow-label">Đang làm</div></div>
                                    <div class="tcv6-flow-item"><span class="tcv6-flow-dot">3</span><div class="tcv6-flow-label">Chờ duyệt</div></div>
                                    <div class="tcv6-flow-item"><span class="tcv6-flow-dot">4</span><div class="tcv6-flow-label">Hoàn thành</div></div>
                                </div>
                            </div>

                            <div class="tcv6-ready" id="readiness">
                                <i class="bi bi-info-circle"></i>
                                <span>Nhập tiêu đề và chọn người thực hiện để sẵn sàng giao việc.</span>
                            </div>
                        </div>
                    </section>
                </aside>
            </div>
        </form>
    </div>
</div>

<footer class="tcv6-footer">
    <div class="tcv6-footer-inner">
        <div class="tcv6-footer-note">
            <strong id="footerAssigneeCount">0</strong> người được chọn · mỗi người tạo một phiếu riêng.
        </div>
        <div class="tcv6-footer-actions">
            <x-ui.button href="{{ route('tasks.index') }}" variant="outline-secondary" size="none" class="tcv6-btn tw:leading-[1.5]">Hủy</x-ui.button>
            <x-ui.button variant="success" type="submit" form="taskCreateForm" size="none" class="tw:px-6 tcv6-btn tw:leading-[1.5]">
                <i class="bi bi-send"></i> Giao việc
            </x-ui.button>
        </div>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded',function(){const picker=document.getElementById('assigneePicker'),search=document.getElementById('assigneeSearch'),results=document.getElementById('assigneeResults'),selected=document.getElementById('selectedAssignees'),inputs=document.getElementById('assigneeInputs'),rows=Array.from(document.querySelectorAll('[data-user-row]')),title=document.getElementById('taskTitle'),priority=document.getElementById('taskPriority'),due=document.getElementById('taskDue'),selectedEmpty=document.getElementById('selectedEmpty');function norm(v){return(v||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'')}function ids(){return Array.from(selected.querySelectorAll('[data-selected-user]')).map(e=>String(e.dataset.id))}function esc(v){return String(v||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}function add(row){if(ids().includes(String(row.dataset.id)))return;const chip=document.createElement('span');chip.className='tcv6-chip';chip.dataset.selectedUser='';chip.dataset.id=row.dataset.id;chip.dataset.name=row.dataset.name;chip.innerHTML='<i class="bi bi-person-check"></i>'+esc(row.dataset.name)+'<button type="button"><i class="bi bi-x-lg"></i></button>';selected.appendChild(chip);syncUsers()}function syncUsers(){const chosen=ids();inputs.innerHTML='';chosen.forEach(id=>{const i=document.createElement('input');i.type='hidden';i.name='assignee_ids[]';i.value=id;inputs.appendChild(i)});const q=norm(search.value);rows.forEach(row=>row.style.display=!chosen.includes(String(row.dataset.id))&&(!q||norm(row.dataset.search).includes(q))?'':'none');const names=Array.from(selected.querySelectorAll('[data-selected-user]')).map(e=>e.dataset.name);selectedEmpty.style.display=names.length?'none':'';document.getElementById('selectedCount').textContent=names.length;document.getElementById('previewCount').textContent=names.length+' người';document.getElementById('previewAssignees').textContent=names.length?names.join(', '):'Chưa chọn';document.getElementById('footerAssigneeCount').textContent=names.length;updateReady()}search.addEventListener('focus',()=>{results.classList.add('show');syncUsers()});search.addEventListener('input',()=>{results.classList.add('show');syncUsers()});results.addEventListener('click',e=>{const row=e.target.closest('[data-user-row]');if(row){add(row);search.value='';search.focus()}});selected.addEventListener('click',e=>{const b=e.target.closest('button');if(b){const chip=b.closest('[data-selected-user]');if(chip){chip.remove();syncUsers()}}});document.addEventListener('click',e=>{if(!picker.contains(e.target))results.classList.remove('show')});function dateText(v){if(!v)return'Chưa đặt';const p=v.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:v}function updateReady(){const ok=title.value.trim()&&ids().length;const box=document.getElementById('readiness');box.classList.toggle('ok',!!ok);box.innerHTML=ok?'<i class="bi bi-check-circle"></i><span>Phiếu đã đủ thông tin chính và sẵn sàng giao.</span>':'<i class="bi bi-info-circle"></i><span>Nhập tiêu đề và chọn người thực hiện để sẵn sàng giao việc.</span>'}function preview(){document.getElementById('previewTitle').textContent=title.value.trim()||'Chưa nhập tiêu đề';document.getElementById('previewPriority').textContent=priority.options[priority.selectedIndex].text;document.getElementById('previewDue').textContent=dateText(due.value);const warn=document.getElementById('dueWarning');if(due.value&&new Date(due.value+'T23:59:59')<new Date()){warn.innerHTML='<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> Hạn hoàn thành đang ở trong quá khứ.</span>'}else warn.innerHTML='';updateReady()}[title,priority,due].forEach(el=>el.addEventListener(el.tagName==='SELECT'?'change':'input',preview));const zone=document.getElementById('uploadZone'),fileInput=document.getElementById('taskFiles'),fileList=document.getElementById('taskFileList');let files=[];function key(f){return[f.name,f.size,f.lastModified,f.type].join('|')}function size(b){const u=['B','KB','MB','GB'];let s=b,i=0;while(s>=1024&&i<u.length-1){s/=1024;i++}return s.toFixed(i?1:0)+' '+u[i]}function syncFiles(){const dt=new DataTransfer();files.forEach(f=>dt.items.add(f));fileInput.files=dt.files;fileList.innerHTML='';files.forEach((f,index)=>{const row=document.createElement('div');row.className='tcv6-file';row.innerHTML='<div class="min-w-0"><div class="tcv6-file-name"></div><div class="tcv6-file-meta"></div></div><button type="button" data-remove="'+index+'"><i class="bi bi-x-lg"></i></button>';row.querySelector('.tcv6-file-name').textContent=f.name;row.querySelector('.tcv6-file-meta').textContent=size(f.size);fileList.appendChild(row)});document.getElementById('previewFiles').textContent=files.length+' file'}function addFiles(incoming){const keys=new Set(files.map(key));incoming.forEach(f=>{if(!keys.has(key(f))){files.push(f);keys.add(key(f))}});syncFiles()}zone.addEventListener('click',()=>fileInput.click());fileInput.addEventListener('change',()=>addFiles(Array.from(fileInput.files||[])));['dragenter','dragover'].forEach(t=>zone.addEventListener(t,e=>{e.preventDefault();zone.classList.add('drag')}));['dragleave','drop'].forEach(t=>zone.addEventListener(t,e=>{e.preventDefault();zone.classList.remove('drag')}));zone.addEventListener('drop',e=>addFiles(Array.from(e.dataTransfer.files||[])));fileList.addEventListener('click',e=>{const b=e.target.closest('[data-remove]');if(b){files.splice(Number(b.dataset.remove),1);syncFiles()}});document.getElementById('taskCreateForm').addEventListener('submit',e=>{if(!ids().length){e.preventDefault();results.classList.add('show');search.focus();alert('Vui lòng chọn ít nhất một người nhận việc.')}});syncUsers();preview();syncFiles()});
</script>
@endsection
