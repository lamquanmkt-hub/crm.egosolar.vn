@extends('layouts.app')

@section('content')
@php
    $priorityOptions = $priorities ?? ['low' => 'Thấp', 'medium' => 'Bình thường', 'high' => 'Cao'];
    $statusOptions = $statuses ?? [
        'new' => 'Mới giao',
        'in_progress' => 'Đang thực hiện',
        'submitted' => 'Chờ duyệt',
        'revision' => 'Cần bổ sung',
        'rejected' => 'Đã từ chối',
        'approved' => 'Hoàn thành',
    ];
    $selectedIds = array_map('strval', old('assignee_ids', [$task->assignee_id]));
@endphp

<style> .task-edit-page{--ink:#102139;--muted:#75849a;--line:#e3e9f0;--teal:#0b776f;min-height:100vh;background:#f3f6fa;padding:15px 18px 96px;color:var(--ink);font-size:13px}.te-shell{max-width:1500px;margin:auto}.te-hero{padding:6px 2px 13px;margin:0;background:transparent;color:var(--ink);box-shadow:none;border-radius:0}.te-hero-row{display:flex;justify-content:space-between;align-items:center;gap:12px}.te-hero h1{margin:0;font-size:26px;font-weight:950;letter-spacing:-.04em}.te-hero p{margin:4px 0 0;color:var(--muted);font-size:12px;font-weight:650}.te-code{border:1px solid #dce3eb;background:#fff;color:#4b5d74;border-radius:10px;padding:8px 11px;font-size:10px;font-weight:900}.te-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:13px;align-items:start}.te-layout main{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 10px 30px rgba(15,23,42,.045);overflow:hidden}.te-layout main .te-card{margin:0;border:0;border-radius:0;box-shadow:none;border-bottom:1px solid #edf1f5}.te-layout main .te-card:last-child{border-bottom:0}.te-card{background:#fff;border:1px solid var(--line);border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.045);margin-bottom:11px}.te-head{display:flex;align-items:center;gap:10px;padding:12px 15px;border-bottom:1px solid #edf1f5;background:#fbfcfd}.te-step{width:27px;height:27px;border-radius:9px;background:#eaf8f6;color:var(--teal);display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:950}.te-title{font-size:13px;font-weight:950}.te-sub{font-size:10px;color:#8290a2;margin-top:2px}.te-body{padding:15px 16px}.te-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:11px}.te-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:11px}.te-label{font-size:11px;font-weight:900;color:#40536b;margin-bottom:5px}.task-edit-page .te-input{border-radius:10px;border-color:#dce3eb;font-size:12px;min-height:40px}.task-edit-page textarea.te-input{min-height:120px}.task-edit-page .te-input:focus{border-color:#3ba9a2;box-shadow:0 0 0 .2rem rgba(15,141,134,.1)}.te-picker{position:relative}.te-search-wrap{position:relative}.te-search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#7b899c}.te-search{padding-left:36px}.te-results{display:none;position:absolute;z-index:40;left:0;right:0;top:45px;max-height:275px;overflow:auto;background:#fff;border:1px solid #dbe3ec;border-radius:13px;padding:6px;box-shadow:0 18px 46px rgba(15,23,42,.17)}.te-results.show{display:block}.te-user{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px;border-radius:10px;cursor:pointer}.te-user:hover{background:#f1f6f7}.te-user-name{font-weight:900;font-size:11px}.te-user-meta{font-size:9px;color:#7b899c}.te-plus{width:27px;height:27px;border-radius:8px;background:#eaf8f6;color:var(--teal);display:flex;align-items:center;justify-content:center}.te-selected{display:flex;flex-wrap:wrap;gap:6px;margin-top:9px;min-height:30px}.te-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 7px;border-radius:9px;background:#fff;border:1px solid #cfe4e2;color:#234b4a;font-size:10px;font-weight:900}.te-chip button{border:0;background:transparent;color:#718096;padding:0}.te-note{display:flex;gap:8px;border:1px solid #cceaf8;background:#f0f9ff;color:#24536a;border-radius:10px;padding:9px 10px;font-size:10px;margin-top:9px}.te-upload{border:1.5px dashed #c9d4df;border-radius:13px;background:#f9fbfc;padding:15px;text-align:center;cursor:pointer}.te-upload:hover,
    .te-upload.drag{border-color:#46aaa4;background:#f0fbfa}.te-upload-icon{width:38px;height:38px;border-radius:11px;background:#eaf8f6;color:var(--teal);display:inline-flex;align-items:center;justify-content:center;font-size:19px;margin-bottom:6px}.te-file-input{display:none}.te-file-list{display:grid;gap:6px;margin-top:8px}.te-file{display:flex;justify-content:space-between;align-items:center;gap:10px;border:1px solid #e2e8ef;border-radius:10px;padding:7px 9px;background:#fff}.te-file-name{font-size:10px;font-weight:900;max-width:650px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.te-file-meta{font-size:9px;color:#8491a2}.te-remove{border:0;background:#feecef;color:#bc2941;width:26px;height:26px;border-radius:7px}.te-side{position:sticky;top:82px}.te-info{padding:13px}.te-info-row{display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px dashed #e2e8f0}.te-info-row:last-child{border-bottom:0}.te-info-label{font-size:10px;color:#75849a;font-weight:800}.te-info-value{font-size:11px;font-weight:900;text-align:right}.te-status{display:inline-flex;border-radius:7px;padding:4px 7px;font-size:9px;font-weight:950}.st-new{background:#e6f5fd;color:#075985}.st-in_progress{background:#fff4d6;color:#8a4b08}.st-submitted{background:#eee9ff;color:#5b21b6}.st-revision{background:#fff1e8;color:#9a3412}.st-rejected{background:#fee8e8;color:#991b1b}.st-approved{background:#e4f8e9;color:#166534}.te-progress{height:6px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:6px}.te-progress span{display:block;height:100%;background:linear-gradient(90deg,#11a4d7,#1fb77c)}.te-footer{position:fixed;left:var(--sidebar-width,240px);right:0;bottom:0;z-index:100;background:rgba(255,255,255,.96);backdrop-filter:blur(12px);border-top:1px solid #dce3eb;padding:10px 18px}.te-footer-inner{max-width:1500px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:12px}.te-footer-note{font-size:10px;color:#748399}.te-actions{display:flex;gap:7px}.te-btn{border-radius:10px;font-size:11px;font-weight:900;padding:7px 13px}@media(max-width:1100px){.te-layout{grid-template-columns:1fr}.te-side{position:static}.te-footer{left:0}}@media(max-width:700px){.task-edit-page{padding:12px 12px 108px}.te-hero-row{align-items:flex-start;flex-direction:column}.te-grid-2,
    .te-grid-3{grid-template-columns:1fr}.te-footer-inner{align-items:flex-start;flex-direction:column}.te-actions{width:100%}.te-actions .te-btn{flex:1}}
</style>

<div class="task-edit-page">
    <div class="te-shell">
        <section class="te-hero"><div class="te-hero-row"><div><h1>Chỉnh sửa phiếu giao việc</h1><p>Cập nhật yêu cầu, người thực hiện, hạn hoàn thành và trạng thái xử lý.</p></div><span class="te-code">Mã công việc #{{ $task->id }}</span></div></section>

        @if($errors->any())<x-ui.alert variant="danger" class="tw:rounded-[1rem]"><strong>Chưa thể lưu thay đổi.</strong><ul class="tw:mb-0 tw:mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-ui.alert>@endif

        <form id="taskEditForm" method="POST" action="{{ route('tasks.update', $task) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="te-layout">
                <main>
                    <section class="te-card">
                        <div class="te-head"><span class="te-step">1</span><div><div class="te-title">Nội dung công việc</div><div class="te-sub">Thông tin này xuất hiện trong hồ sơ Báo cáo việc.</div></div></div>
                        <div class="te-body">
                            <div class="tw:mb-4"><label class="te-label">Tiêu đề công việc <span class="tw:text-[#dc3545]">*</span></label><x-ui.input type="text" name="title" value="{{ old('title', $task->title) }}" class="te-input" required maxlength="255" /></div>
                            <div class="te-grid-2"><div><label class="te-label">Link liên quan</label><x-ui.input type="url" name="link_url" value="{{ old('link_url', $task->link_url) }}" class="te-input" placeholder="https://..." /></div><div><label class="te-label">Người giao</label><x-ui.input type="text" class="te-input" value="{{ optional($task->requester)->name ?: 'Không xác định' }}" disabled /></div></div>
                            <div class="tw:mt-4"><label class="te-label">Mô tả / yêu cầu công việc</label><x-ui.input as="textarea" name="description" class="te-input" placeholder="Mô tả đầu việc, đầu ra và tiêu chuẩn hoàn thành...">{{ old('description', $task->description) }}</x-ui.input></div>
                        </div>
                    </section>

                    <section class="te-card">
                        <div class="te-head"><span class="te-step">2</span><div><div class="te-title">Người thực hiện</div><div class="te-sub">Người đầu tiên cập nhật phiếu hiện tại; người bổ sung sẽ nhận phiếu riêng.</div></div></div>
                        <div class="te-body">
                            <div class="te-picker" id="editAssigneePicker">
                                <label class="te-label">Người nhận việc <span class="tw:text-[#dc3545]">*</span></label>
                                <div class="te-search-wrap"><i class="bi bi-search te-search-icon"></i><x-ui.input id="editAssigneeSearch" type="text" class="te-input te-search" autocomplete="off" placeholder="Tìm tên, email hoặc phòng ban..." /><div class="te-results" id="editAssigneeResults">@foreach($users as $user)<div class="te-user" data-user-row data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-search="{{ \Illuminate\Support\Str::lower(trim($user->name.' '.$user->email.' '.optional($user->department)->name)) }}"><div><div class="te-user-name">{{ $user->name }}</div><div class="te-user-meta">{{ $user->email }} @if(optional($user->department)->name) · {{ optional($user->department)->name }} @endif</div></div><span class="te-plus"><i class="bi bi-plus-lg"></i></span></div>@endforeach</div></div>
                                <div class="te-selected" id="editSelectedAssignees">@foreach($users as $user)@if(in_array((string)$user->id,$selectedIds,true))<span class="te-chip" data-selected-user data-id="{{ $user->id }}" data-name="{{ $user->name }}"><i class="bi bi-person-check"></i>{{ $user->name }}<button type="button"><i class="bi bi-x-lg"></i></button></span>@endif @endforeach</div>
                                <div id="editAssigneeInputs"></div>
                                <div class="te-note"><i class="bi bi-info-circle"></i><div>Thay người đầu tiên sẽ chuyển phiếu hiện tại. Chọn thêm người khác sẽ tạo công việc mới và gửi thông báo riêng.</div></div>
                            </div>
                        </div>
                    </section>

                    <section class="te-card">
                        <div class="te-head"><span class="te-step">3</span><div><div class="te-title">Tiến độ quản lý</div><div class="te-sub">Trạng thái đồng bộ với Báo cáo việc.</div></div></div>
                        <div class="te-body"><div class="te-grid-3"><div><label class="te-label">Mức ưu tiên</label><x-ui.select name="priority" class="te-input">@foreach($priorityOptions as $key=>$label)<option value="{{ $key }}" {{ old('priority',$task->priority) === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</x-ui.select></div><div><label class="te-label">Trạng thái</label><x-ui.select name="status" class="te-input">@foreach($statusOptions as $key=>$label)<option value="{{ $key }}" {{ old('status',$task->status) === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</x-ui.select></div><div><label class="te-label">Hạn hoàn thành</label><x-ui.input type="date" name="due_at" value="{{ old('due_at', $task->due_at ? \Illuminate\Support\Carbon::parse($task->due_at)->format('Y-m-d') : '') }}" class="te-input" /></div></div></div>
                    </section>

                    <section class="te-card">
                        <div class="te-head"><span class="te-step">4</span><div><div class="te-title">Bổ sung tài liệu</div><div class="te-sub">File cũ giữ nguyên; file mới sẽ được thêm vào hồ sơ.</div></div></div>
                        <div class="te-body"><div class="te-upload" id="editUploadZone"><div class="te-upload-icon"><i class="bi bi-cloud-arrow-up"></i></div><div class="tw:font-bold">Kéo thả file hoặc bấm để chọn</div><div class="tw:text-[rgba(33,37,41,0.75)] small tw:mt-1">Tối đa 50MB/file.</div><input id="editTaskFiles" class="te-file-input" type="file" name="attachments[]" multiple></div><div class="te-file-list" id="editTaskFileList"></div></div>
                    </section>
                </main>

                <aside class="te-side">
                    <section class="te-card"><div class="te-head"><i class="bi bi-info-circle"></i><div><div class="te-title">Thông tin hiện tại</div><div class="te-sub">Tóm tắt hồ sơ công việc.</div></div></div><div class="te-info">
                        <div class="te-info-row"><span class="te-info-label">Trạng thái</span><span class="te-status st-{{ $task->status ?? 'new' }}">{{ $statusOptions[$task->status ?? 'new'] ?? $task->status }}</span></div>
                        <div class="te-info-row"><span class="te-info-label">Người nhận</span><span class="te-info-value">{{ optional($task->assignee)->name ?: '-' }}</span></div>
                        <div class="te-info-row"><span class="te-info-label">Ngày giao</span><span class="te-info-value">{{ optional($task->created_at)->format('d/m/Y H:i') }}</span></div>
                        <div class="te-info-row"><span class="te-info-label">Ngày cập nhật</span><span class="te-info-value">{{ optional($task->updated_at)->format('d/m/Y H:i') }}</span></div>
                        <div class="te-info-row"><span class="te-info-label">Tiến độ</span><span class="te-info-value">{{ (int)($task->progress_percent ?? 0) }}%</span></div>
                        <div class="te-progress"><span style="width:{{ max(0,min(100,(int)($task->progress_percent ?? 0))) }}%"></span></div>
                    </div></section>
                    <x-ui.button href="{{ route('tasks.show',$task) }}" variant="outline-primary" size="none" class="tw:w-full te-btn tw:leading-[1.5]"><i class="bi bi-eye"></i> Xem hồ sơ công việc</x-ui.button>
                </aside>
            </div>
        </form>
    </div>
</div>

<footer class="te-footer"><div class="te-footer-inner"><div class="te-footer-note">Thay đổi được lưu vào cùng dữ liệu với Báo cáo việc.</div><div class="te-actions"><x-ui.button href="{{ route('tasks.show',$task) }}" variant="outline-secondary" size="none" class="te-btn tw:leading-[1.5]">Hủy</x-ui.button><x-ui.button variant="success" type="submit" form="taskEditForm" size="none" class="tw:px-6 te-btn tw:leading-[1.5]"><i class="bi bi-save"></i> Lưu thay đổi</x-ui.button></div></div></footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const picker=document.getElementById('editAssigneePicker'),search=document.getElementById('editAssigneeSearch'),results=document.getElementById('editAssigneeResults'),selected=document.getElementById('editSelectedAssignees'),inputs=document.getElementById('editAssigneeInputs'),rows=Array.from(document.querySelectorAll('[data-user-row]'));
    function normalize(v){return(v||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');}
    function ids(){return Array.from(selected.querySelectorAll('[data-selected-user]')).map(function(el){return String(el.dataset.id);});}
    function esc(v){return String(v||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
    function sync(){const chosen=ids();inputs.innerHTML='';chosen.forEach(function(id){const i=document.createElement('input');i.type='hidden';i.name='assignee_ids[]';i.value=id;inputs.appendChild(i);});const q=normalize(search.value);rows.forEach(function(row){row.style.display=!chosen.includes(String(row.dataset.id))&&(!q||normalize(row.dataset.search).includes(q))?'':'none';});}
    function add(row){if(ids().includes(String(row.dataset.id)))return;const chip=document.createElement('span');chip.className='te-chip';chip.dataset.selectedUser='';chip.dataset.id=row.dataset.id;chip.dataset.name=row.dataset.name;chip.innerHTML='<i class="bi bi-person-check"></i>'+esc(row.dataset.name)+'<button type="button"><i class="bi bi-x-lg"></i></button>';selected.appendChild(chip);sync();}
    search.addEventListener('focus',function(){results.classList.add('show');sync();});search.addEventListener('input',function(){results.classList.add('show');sync();});results.addEventListener('click',function(e){const row=e.target.closest('[data-user-row]');if(row){add(row);search.value='';search.focus();}});selected.addEventListener('click',function(e){const b=e.target.closest('button');if(!b)return;const chip=b.closest('[data-selected-user]');if(chip){chip.remove();sync();}});document.addEventListener('click',function(e){if(!picker.contains(e.target))results.classList.remove('show');});
    const zone=document.getElementById('editUploadZone'),fileInput=document.getElementById('editTaskFiles'),fileList=document.getElementById('editTaskFileList');let files=[];
    function key(f){return[f.name,f.size,f.lastModified,f.type].join('|');}function size(bytes){const u=['B','KB','MB','GB'];let s=bytes,i=0;while(s>=1024&&i<u.length-1){s/=1024;i++;}return s.toFixed(i?1:0)+' '+u[i];}function syncFiles(){const dt=new DataTransfer();files.forEach(function(f){dt.items.add(f);});fileInput.files=dt.files;fileList.innerHTML='';files.forEach(function(f,index){const row=document.createElement('div');row.className='te-file';row.innerHTML='<div class="min-w-0"><div class="te-file-name"></div><div class="te-file-meta"></div></div><button type="button" class="te-remove" data-remove="'+index+'"><i class="bi bi-x-lg"></i></button>';row.querySelector('.te-file-name').textContent=f.name;row.querySelector('.te-file-meta').textContent=size(f.size);fileList.appendChild(row);});}function addFiles(newFiles){const keys=new Set(files.map(key));newFiles.forEach(function(f){if(!keys.has(key(f))){files.push(f);keys.add(key(f));}});syncFiles();}
    zone.addEventListener('click',function(){fileInput.click();});fileInput.addEventListener('change',function(){addFiles(Array.from(fileInput.files||[]));});['dragenter','dragover'].forEach(function(t){zone.addEventListener(t,function(e){e.preventDefault();zone.classList.add('drag');});});['dragleave','drop'].forEach(function(t){zone.addEventListener(t,function(e){e.preventDefault();zone.classList.remove('drag');});});zone.addEventListener('drop',function(e){addFiles(Array.from(e.dataTransfer.files||[]));});fileList.addEventListener('click',function(e){const b=e.target.closest('[data-remove]');if(!b)return;files.splice(Number(b.dataset.remove),1);syncFiles();});
    document.getElementById('taskEditForm').addEventListener('submit',function(e){if(!ids().length){e.preventDefault();results.classList.add('show');search.focus();alert('Vui lòng chọn ít nhất một người nhận việc.');}});
    sync();syncFiles();
});
</script>
@endsection
