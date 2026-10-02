@extends('layouts.app')

@section('title', 'Thêm công việc')

@section('content')
@php
    $oldAssignees = old('assignees', []);
    if (!is_array($oldAssignees)) $oldAssignees = [];

    // fallback nếu trước đây chỉ có assignee dạng chuỗi "A, B"
    if (empty($oldAssignees) && old('assignee')) {
        $oldAssignees = array_values(array_filter(array_map('trim', explode(',', old('assignee')))));
    }

    $oldLinks = old('links', []);
    if (!is_array($oldLinks)) $oldLinks = [];
@endphp

<div class="container-fluid tw:px-6 tw:mt-4 weekly-task-create">

    {{-- Header --}}
    <div class="tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2 tw:mb-4">
        <div>
            <h3 class="tw:font-bold tw:mb-1">Thêm công việc</h3>
            <div class="text-muted">Tạo công việc hàng tuần</div>
        </div>

        <x-ui.button variant="none" size="none" class="btn-ego-soft wt-btn" href="{{ route('marketing.reports.weekly-tasks') }}">
            <i class="bi bi-arrow-left"></i> Quay lại
        </x-ui.button>
    </div>

    <x-ui.card class="wt-card">
        <x-ui.card-body class="tw:p-0">

            <form method="POST"
                  action="{{ route('marketing.reports.weekly-tasks.store') }}"
                  enctype="multipart/form-data"
                  class="tw:p-4">
                @csrf

                {{-- ====== Khối 1: Thông tin task ====== --}}
                <div class="wt-section tw:mb-4">
                    <div class="wt-section-head">
                        <div class="wt-section-title">
                            <i class="bi bi-clipboard-check"></i>
                            Thông tin công việc
                        </div>
                        <div class="wt-section-sub text-muted">Nhập nội dung và mốc thời gian</div>
                    </div>

                    <div class="tw:row tw:g-3 tw:mt-1">
                        <div class="tw:md:col12-6">
                            <x-ui.label>Tên công việc <span class="tw:text-[#dc3545]!">*</span></x-ui.label>
                            <x-ui.input name="title"
                                   class="wt-control @error('title') is-invalid @enderror"
                                   placeholder="VD: Viết content + edit ảnh..."
                                   value="{{ old('title') }}"
                                   required />
                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="tw:md:col12-3">
                            <x-ui.label>Priority <span class="tw:text-[#dc3545]!">*</span></x-ui.label>
                            <x-ui.select name="priority"
                                    class="wt-control @error('priority') is-invalid @enderror"
                                    required>
                                <option value="high" {{ old('priority','high')==='high'?'selected':'' }}>High</option>
                                <option value="medium" {{ old('priority')==='medium'?'selected':'' }}>Medium</option>
                                <option value="low" {{ old('priority')==='low'?'selected':'' }}>Low</option>
                            </x-ui.select>
                            @error('priority') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="tw:md:col12-3">
                            <x-ui.label>Hạng mục</x-ui.label>
                            <x-ui.input name="category"
                                   class="wt-control @error('category') is-invalid @enderror"
                                   placeholder="Content / Digital / Event..."
                                   value="{{ old('category') }}" />
                            @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="tw:md:col12-3">
                            <x-ui.label>Ngày bắt đầu</x-ui.label>
                            <x-ui.input type="date"
                                   name="start_date"
                                   class="wt-control @error('start_date') is-invalid @enderror"
                                   value="{{ old('start_date') }}" />
                            @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="tw:md:col12-3">
                            <x-ui.label>Hạn</x-ui.label>
                            <x-ui.input type="date"
                                   name="due_date"
                                   class="wt-control @error('due_date') is-invalid @enderror"
                                   value="{{ old('due_date') }}" />
                            @error('due_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="tw:md:col12-3">
                            <x-ui.label>Trạng thái <span class="tw:text-[#dc3545]!">*</span></x-ui.label>
                            {{-- dùng pending để hợp với list (badge pending/done/doing) --}}
                            <x-ui.select name="status"
                                    class="wt-control @error('status') is-invalid @enderror"
                                    required>
                                <option value="pending" {{ old('status','pending')==='pending'?'selected':'' }}>Todo</option>
                                <option value="doing" {{ old('status')==='doing'?'selected':'' }}>Doing</option>
                                <option value="done" {{ old('status')==='done'?'selected':'' }}>Done</option>
                            </x-ui.select>
                            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="tw:md:col12-3">
                            <x-ui.label>Tiến độ (%)</x-ui.label>
                            <x-ui.input type="number"
                                   name="progress"
                                   class="wt-control @error('progress') is-invalid @enderror"
                                   min="0" max="100"
                                   value="{{ old('progress', 0) }}" />
                            @error('progress') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">0–100</div>
                        </div>
                    </div>
                </div>

                {{-- ====== Khối 2: Người phụ trách + Link + File ====== --}}
                <div class="tw:row tw:g-3">

                    {{-- Người phụ trách --}}
                    <div class="tw:min-[62rem]:col12-6">
                        <div class="wt-section tw:h-full">
                            <div class="wt-section-head">
                                <div class="wt-section-title">
                                    <i class="bi bi-people"></i>
                                    Chịu trách nhiệm
                                </div>
                                <div class="wt-section-sub text-muted">Thêm 2–3 người (hoặc nhiều hơn nếu cần)</div>
                            </div>

                            <x-ui.label class="tw:mt-2">Danh sách người phụ trách</x-ui.label>

                            <div class="input-group">
                                <x-ui.input type="text"
                                       id="assigneeInput"
                                       class="wt-control"
                                       placeholder="Nhập tên... (Enter để thêm)" />
                                <x-ui.button variant="none" size="none" class="btn-ego-soft wt-btn tw:relative tw:z-[2] tw:focus:z-[5]" type="button" id="addAssigneeBtn">
                                    <i class="bi bi-plus-lg"></i> Thêm người
                                </x-ui.button>
                            </div>

                            <div id="assigneeChips" class="wt-chips tw:mt-4"></div>

                            {{-- hidden: assignees[] + assignee (string join) --}}
                            <div id="assigneeHidden"></div>
                            <input type="hidden" name="assignee" id="assigneeJoined" value="{{ old('assignee','') }}">

                            <div class="form-text">
                                Mẹo: Enter để thêm nhanh. Bấm “x” để xoá.
                            </div>
                        </div>
                    </div>

                    {{-- Links --}}
                    <div class="tw:min-[62rem]:col12-6">
                        <div class="wt-section tw:h-full">
                            <div class="wt-section-head">
                                <div class="wt-section-title">
                                    <i class="bi bi-link-45deg"></i>
                                    Link liên quan
                                </div>
                                <div class="wt-section-sub text-muted">Drive / Zalo / FB / Tài liệu / Landing...</div>
                            </div>

                            <x-ui.label class="tw:mt-2">Thêm link</x-ui.label>

                            <div class="input-group">
                                <x-ui.input type="url"
                                       id="linkInput"
                                       class="wt-control"
                                       placeholder="https://... (Enter để thêm)" />
                                <x-ui.button variant="none" size="none" class="btn-ego-soft wt-btn tw:relative tw:z-[2] tw:focus:z-[5]" type="button" id="addLinkBtn">
                                    <i class="bi bi-plus-lg"></i> Thêm link
                                </x-ui.button>
                            </div>

                            <div id="linkChips" class="wt-chips tw:mt-4"></div>
                            <div id="linkHidden"></div>

                            <div class="form-text">
                                Hỗ trợ nhiều link. Bấm “x” để xoá.
                            </div>
                        </div>
                    </div>

                    {{-- File đính kèm / ảnh --}}
                    <div class="tw:col12-12">
                        <div class="wt-section">
                            <div class="wt-section-head">
                                <div class="wt-section-title">
                                    <i class="bi bi-paperclip"></i>
                                    File đính kèm / Ảnh
                                </div>
                                <div class="wt-section-sub text-muted">Upload nhiều file, có preview ảnh</div>
                            </div>

                            <div class="tw:row tw:g-3 tw:mt-1">
                                <div class="tw:min-[62rem]:col12-6">
                                    <x-ui.label>Chọn file</x-ui.label>
                                    <x-ui.input type="file"
                                           name="attachments[]"
                                           id="attachments"
                                           class="wt-control @error('attachments') is-invalid @enderror"
                                           multiple
                                           accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.txt" />
                                    @error('attachments') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    <div class="form-text">
                                        Gợi ý: ảnh (jpg/png/webp), pdf, doc/xls/ppt, zip...
                                    </div>
                                </div>

                                <div class="tw:min-[62rem]:col12-6">
                                    <x-ui.label>Danh sách file đã chọn</x-ui.label>
                                    <div id="fileList" class="wt-filelist">
                                        <div class="text-muted small">Chưa chọn file nào.</div>
                                    </div>
                                </div>

                                <div class="tw:col12-12">
                                    <x-ui.label>Preview ảnh</x-ui.label>
                                    <div id="imagePreview" class="wt-preview"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- ====== Ghi chú ====== --}}
                <div class="wt-section tw:mt-4">
                    <div class="wt-section-head">
                        <div class="wt-section-title">
                            <i class="bi bi-journal-text"></i>
                            Ghi chú
                        </div>
                        <div class="wt-section-sub text-muted">Thông tin thêm (nếu có)</div>
                    </div>

                    <div class="tw:mt-2">
                        <x-ui.input as="textarea" name="note"
                                  class="wt-control @error('note') is-invalid @enderror"
                                  rows="4"
                                  placeholder="Ghi chú...">{{ old('note') }}</x-ui.input>
                        @error('note') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                {{-- Actions --}}
                <div class="tw:flex flex-wrap tw:gap-2 tw:mt-6">
                    <x-ui.button variant="none" size="none" type="submit" class="btn-ego wt-btn">
                        <i class="bi bi-check2-circle"></i> Lưu
                    </x-ui.button>

                    <x-ui.button variant="none" size="none" class="btn-ego-soft wt-btn" href="{{ route('marketing.reports.weekly-tasks') }}">
                        Hủy
                    </x-ui.button>
                </div>

            </form>
        </x-ui.card-body>
    </x-ui.card>

</div>
@endsection

@push('styles')
<style>
/* ===== Create Task - EGO style ===== */
.weekly-task-create{
    --ego: #0E7C86;
    --ego2: #0B5E66;
    --border: rgba(12, 92, 100, .12);
    --muted: #64748b;
    --text: #0f172a;
}

.weekly-task-create .wt-card{
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .04);
}

.weekly-task-create .wt-btn{
    height: 42px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 12px;
    font-weight: 900;
    padding: 0 14px;
}

.weekly-task-create .btn-ego{
    background: linear-gradient(135deg, var(--ego), var(--ego2));
    border: none;
    color: #fff;
    box-shadow: 0 10px 22px rgba(14, 124, 134, .18);
}
.weekly-task-create .btn-ego:hover{ filter: brightness(.98); color:#fff; }

.weekly-task-create .btn-ego-soft{
    background: rgba(14, 124, 134, .10);
    border: 1px solid var(--border);
    color: var(--ego2);
    border-radius: 12px;
    font-weight: 900;
}

.weekly-task-create .wt-control{
    border-radius: 12px;
    border: 1px solid var(--border);
    min-height: 42px;
}

.weekly-task-create .wt-section{
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 14px;
    background: linear-gradient(135deg, rgba(14,124,134,.06), rgba(14,124,134,.02));
}

.weekly-task-create .wt-section-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 10px;
}
.weekly-task-create .wt-section-title{
    font-weight: 950;
    color: var(--text);
    display:flex;
    align-items:center;
    gap:10px;
}
.weekly-task-create .wt-section-title i{
    width: 34px; height: 34px;
    border-radius: 12px;
    display:grid;
    place-items:center;
    background: rgba(14,124,134,.12);
    color: var(--ego2);
}
.weekly-task-create .wt-section-sub{
    font-size: 12px;
    margin-top: 4px;
}

.weekly-task-create .wt-chips{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
}
.weekly-task-create .wt-chip{
    display:inline-flex;
    align-items:center;
    gap:10px;
    padding: 8px 10px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid var(--border);
    box-shadow: 0 10px 18px rgba(15, 23, 42, .04);
    font-weight: 800;
    color: var(--text);
}
.weekly-task-create .wt-chip small{
    color: var(--muted);
    font-weight: 800;
}
.weekly-task-create .wt-chip button{
    border:none;
    width: 26px; height: 26px;
    border-radius: 999px;
    background: rgba(220, 53, 69, .10);
    color: #b4232c;
    display:grid;
    place-items:center;
    font-weight: 900;
}

.weekly-task-create .wt-filelist{
    min-height: 42px;
    border-radius: 12px;
    border: 1px dashed rgba(12, 92, 100, .25);
    background: #fff;
    padding: 10px;
}
.weekly-task-create .wt-fileitem{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 12px;
    border: 1px solid rgba(15,23,42,.06);
    margin-bottom: 8px;
}
.weekly-task-create .wt-fileitem:last-child{ margin-bottom: 0; }
.weekly-task-create .wt-filemeta{
    display:flex;
    flex-direction:column;
    gap: 2px;
}
.weekly-task-create .wt-filemeta .name{
    font-weight: 900;
    color: var(--text);
    font-size: 13px;
}
.weekly-task-create .wt-filemeta .size{
    font-size: 12px;
    color: var(--muted);
    font-weight: 800;
}
.weekly-task-create .wt-fileitem button{
    border:none;
    height: 32px;
    border-radius: 10px;
    padding: 0 10px;
    font-weight: 900;
    background: rgba(220, 53, 69, .10);
    color: #b4232c;
}

.weekly-task-create .wt-preview{
    display:flex;
    flex-wrap:wrap;
    gap: 12px;
    min-height: 70px;
    padding: 10px;
    border-radius: 12px;
    border: 1px dashed rgba(12, 92, 100, .25);
    background: #fff;
}
.weekly-task-create .wt-thumb{
    width: 96px; height: 96px;
    border-radius: 14px;
    overflow:hidden;
    border: 1px solid rgba(15,23,42,.08);
    box-shadow: 0 10px 18px rgba(15, 23, 42, .06);
    position: relative;
}
.weekly-task-create .wt-thumb img{
    width: 100%;
    height: 100%;
    object-fit: cover;
    display:block;
}
.weekly-task-create .wt-thumb span{
    position:absolute;
    left: 8px;
    bottom: 8px;
    font-size: 11px;
    font-weight: 900;
    padding: 4px 8px;
    border-radius: 999px;
    background: rgba(0,0,0,.55);
    color: #fff;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    // ===== Helpers =====
    const $ = (id) => document.getElementById(id);

    const uniq = (arr) => {
        const seen = new Set();
        return arr.filter(x => {
            const k = (x || '').trim();
            if (!k) return false;
            const key = k.toLowerCase();
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    };

    const humanSize = (bytes) => {
        if (!bytes && bytes !== 0) return '';
        const units = ['B','KB','MB','GB'];
        let b = bytes, i = 0;
        while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
        return `${b.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    };

    // ===== Assignees =====
    const assigneeInput = $('assigneeInput');
    const addAssigneeBtn = $('addAssigneeBtn');
    const assigneeChips = $('assigneeChips');
    const assigneeHidden = $('assigneeHidden');
    const assigneeJoined = $('assigneeJoined');

    let assignees = @json($oldAssignees);

    function renderAssignees() {
        assignees = uniq(assignees).slice(0, 8); // giới hạn mềm, bạn muốn 2-3 nhưng cho phép nhiều hơn chút
        assigneeChips.innerHTML = '';
        assigneeHidden.innerHTML = '';

        if (assignees.length === 0) {
            assigneeChips.innerHTML = `<div class="text-muted small">Chưa có người phụ trách.</div>`;
        } else {
            assignees.forEach((name, idx) => {
                const chip = document.createElement('div');
                chip.className = 'wt-chip';
                chip.innerHTML = `<span>${escapeHtml(name)}</span><button type="button" title="Xóa">×</button>`;
                chip.querySelector('button').addEventListener('click', () => {
                    assignees.splice(idx, 1);
                    renderAssignees();
                });
                assigneeChips.appendChild(chip);

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'assignees[]';
                hidden.value = name;
                assigneeHidden.appendChild(hidden);
            });
        }

        // fallback string để tương thích DB cũ
        assigneeJoined.value = assignees.join(', ');
    }

    function addAssignee() {
        const val = (assigneeInput.value || '').trim();
        if (!val) return;
        assignees.push(val);
        assigneeInput.value = '';
        renderAssignees();
    }

    addAssigneeBtn.addEventListener('click', addAssignee);
    assigneeInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addAssignee();
        }
    });

    // ===== Links =====
    const linkInput = $('linkInput');
    const addLinkBtn = $('addLinkBtn');
    const linkChips = $('linkChips');
    const linkHidden = $('linkHidden');

    let links = @json($oldLinks);

    function isValidUrl(str) {
        try {
            const u = new URL(str);
            return u.protocol === 'http:' || u.protocol === 'https:';
        } catch (e) { return false; }
    }

    function renderLinks() {
        links = uniq(links);
        linkChips.innerHTML = '';
        linkHidden.innerHTML = '';

        if (links.length === 0) {
            linkChips.innerHTML = `<div class="text-muted small">Chưa có link.</div>`;
        } else {
            links.forEach((url, idx) => {
                const chip = document.createElement('div');
                chip.className = 'wt-chip';
                chip.innerHTML = `
                    <span style="max-width:420px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(url)}</span>
                    <button type="button" title="Xóa">×</button>
                `;
                chip.querySelector('button').addEventListener('click', () => {
                    links.splice(idx, 1);
                    renderLinks();
                });
                linkChips.appendChild(chip);

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'links[]';
                hidden.value = url;
                linkHidden.appendChild(hidden);
            });
        }
    }

    function addLink() {
        const val = (linkInput.value || '').trim();
        if (!val) return;
        const url = val.startsWith('http') ? val : `https://${val}`;
        if (!isValidUrl(url)) {
            linkInput.focus();
            linkInput.classList.add('is-invalid');
            setTimeout(() => linkInput.classList.remove('is-invalid'), 1200);
            return;
        }
        links.push(url);
        linkInput.value = '';
        renderLinks();
    }

    addLinkBtn.addEventListener('click', addLink);
    linkInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addLink();
        }
    });

    // ===== Attachments (preview + remove) =====
    const attachments = $('attachments');
    const fileList = $('fileList');
    const imagePreview = $('imagePreview');

    // Giữ state file bằng DataTransfer để có thể xóa từng file
    let dt = new DataTransfer();

    function renderFiles() {
        const files = Array.from(dt.files || []);
        fileList.innerHTML = '';
        imagePreview.innerHTML = '';

        if (files.length === 0) {
            fileList.innerHTML = `<div class="text-muted small">Chưa chọn file nào.</div>`;
            imagePreview.innerHTML = `<div class="text-muted small">Chưa có ảnh.</div>`;
            return;
        }

        files.forEach((f, idx) => {
            // File list
            const item = document.createElement('div');
            item.className = 'wt-fileitem';

            item.innerHTML = `
                <div class="wt-filemeta">
                    <div class="name">${escapeHtml(f.name)}</div>
                    <div class="size">${humanSize(f.size)}</div>
                </div>
                <button type="button"><i class="bi bi-trash"></i> Xóa</button>
            `;
            item.querySelector('button').addEventListener('click', () => {
                // remove file idx
                const next = new DataTransfer();
                files.forEach((ff, j) => { if (j !== idx) next.items.add(ff); });
                dt = next;
                attachments.files = dt.files;
                renderFiles();
            });
            fileList.appendChild(item);

            // Image preview
            if (f.type && f.type.startsWith('image/')) {
                const thumb = document.createElement('div');
                thumb.className = 'wt-thumb';
                const img = document.createElement('img');
                img.alt = f.name;
                thumb.appendChild(img);

                const tag = document.createElement('span');
                tag.textContent = 'Ảnh';
                thumb.appendChild(tag);

                const reader = new FileReader();
                reader.onload = (e) => { img.src = e.target.result; };
                reader.readAsDataURL(f);

                imagePreview.appendChild(thumb);
            }
        });

        if (imagePreview.children.length === 0) {
            imagePreview.innerHTML = `<div class="text-muted small">Không có file ảnh trong danh sách đã chọn.</div>`;
        }
    }

    attachments.addEventListener('change', () => {
        const chosen = Array.from(attachments.files || []);
        // cộng dồn vào dt (không đè)
        chosen.forEach(f => dt.items.add(f));
        attachments.files = dt.files;
        renderFiles();
    });

    // ===== Safe HTML =====
    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, (m) => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        }[m]));
    }

    // init
    renderAssignees();
    renderLinks();
    renderFiles();
})();
</script>
@endpush