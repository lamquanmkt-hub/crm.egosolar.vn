@extends('layouts.app')

@section('title', 'Hồ sơ Bảo trì / Bảo hành')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v3.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v3.css')) ? filemtime(public_path('css/technical-maintenance-v3.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-site-v7.css') }}?v={{ file_exists(public_path('css/technical-maintenance-site-v7.css')) ? filemtime(public_path('css/technical-maintenance-site-v7.css')) : time() }}">
@endsection

@section('content')

<div class="ms7-page" data-ms7-root
    x-data="{
        tab: 'rounds',
        loc: 'all',
        modal: false,
        tong: {{ $tongDot }},
        soDot: { done: {{ $soDotXong }}, open: {{ $soDotMo }}, overdue: {{ $soDotQuaHan }} },
        chonLoc(ten) { this.loc = ten; this.tab = 'rounds'; },
        hienDot(loai, quaHan) {
            return this.loc === 'all' || (this.loc === 'overdue' ? quaHan : loai === this.loc);
        },
        hienChuKy(coXong, coMo, coQuaHan) {
            if (this.loc === 'all') return true;
            if (this.loc === 'overdue') return coQuaHan;
            return this.loc === 'done' ? coXong : coMo;
        },
        soDotHien() { return this.loc === 'all' ? this.tong : this.soDot[this.loc]; },
    }"
    x-effect="document.body.classList.toggle('ms7-modal-open', modal)"
    @keydown.escape.window="modal = false">
    <nav class="ms7-breadcrumb"><a href="{{ route('projects-unified.maintenance.index') }}">Bảo trì &amp; Bảo hành</a><i class="bi bi-chevron-right"></i><span>Hồ sơ công trình</span></nav>

    @if(session('success'))<div class="tm3-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="tm3-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())<div class="tm3-alert danger"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ $errors->first() }}</span></div>@endif

    <header class="ms7-hero">
        <div class="ms7-identity"><span class="ms7-eyebrow">HỒ SƠ BẢO TRÌ / BẢO HÀNH</span><h1>{{ $site->name ?: 'Công trình chưa đặt tên' }}</h1><div class="ms7-identity-meta"><span><i class="bi bi-geo-alt"></i> {{ $site->address ?: 'Chưa cập nhật địa chỉ' }}</span>@if($site->system_kwp)<span><i class="bi bi-lightning-charge"></i> {{ number_format((float) $site->system_kwp, 2, ',', '.') }} kWp</span>@endif</div></div>
        <div class="ms7-hero-actions">@if($site->contact_phone)<a class="ms7-button ghost" href="tel:{{ $site->contact_phone }}"><i class="bi bi-telephone"></i> Gọi khách</a>@endif @if($mapUrl)<a class="ms7-button ghost" href="{{ $mapUrl }}" target="_blank" rel="noopener"><i class="bi bi-geo-alt"></i> Bản đồ</a>@endif @if($permissions['manage'])<a class="ms7-button ghost" href="{{ route('sites.edit', $site->id) }}"><i class="bi bi-sliders"></i> Cấu hình</a>@endif<a class="ms7-button primary" href="{{ route('projects-unified.maintenance.index') }}"><i class="bi bi-arrow-left"></i> Danh sách</a></div>
    </header>

    <section class="ms7-project-strip"><div><small>Khách hàng</small><strong>{{ $site->contact_name ?: 'Chưa cập nhật' }}</strong><span>{{ $site->contact_phone ?: 'Chưa có số điện thoại' }}</span></div><div><small>Ngày nghiệm thu</small><strong>{{ $acceptedDate ? \Carbon\Carbon::parse($acceptedDate)->format('d/m/Y') : 'Chưa cập nhật' }}</strong><span>Ngày bàn giao công trình</span></div><div><small>Chu kỳ bảo trì</small><strong>{{ $cycleLabel }}</strong><span>{{ $cycles->count() }} chu kỳ đang theo dõi</span></div><div><small>Bảo hành đến</small><strong>{{ $site->warranty_to ? \Carbon\Carbon::parse($site->warranty_to)->format('d/m/Y') : 'Chưa cập nhật' }}</strong><span>{{ $site->system_type ?: 'Hệ thống điện mặt trời' }}</span></div><div><small>Đợt đang mở</small><strong>{{ $nextSchedule ? ($nextSchedule->round_no ?: 1).'/'.($nextSchedule->total_rounds ?: 1) : ($schedules->isEmpty() ? 'Chưa có' : 'Đã hoàn tất') }}</strong><span>{{ $nextSchedule ? (optional($nextSchedule->scheduled_date)->format('d/m/Y') ?: 'Chưa đặt ngày') : $completedCount.'/'.$schedules->count().' đợt hoàn thành' }}</span></div></section>

    <section class="ms7-metrics"><button class="ms7-metric" type="button" data-ms7-round-filter="all" :class="{ 'active': loc === 'all' }" @click="chonLoc('all')"><span>Tổng số đợt</span><strong>{{ $schedules->count() }}</strong><small>{{ $cycles->count() }} chu kỳ</small></button><button class="ms7-metric info" type="button" data-ms7-round-filter="open" :class="{ 'active': loc === 'open' }" @click="chonLoc('open')"><span>Đang xử lý</span><strong>{{ $activeSchedules->count() }}</strong><small>{{ $nextSchedule ? 'Gần nhất '.$nextSchedule->scheduled_date?->format('d/m/Y') : 'Không có đợt mở' }}</small></button><button class="ms7-metric success" type="button" data-ms7-round-filter="done" :class="{ 'active': loc === 'done' }" @click="chonLoc('done')"><span>Đã hoàn thành</span><strong>{{ $completedCount }}</strong><small>{{ $progressPercent }}% tiến độ</small></button><button class="ms7-metric danger" type="button" data-ms7-round-filter="overdue" :class="{ 'active': loc === 'overdue' }" @click="chonLoc('overdue')"><span>Quá hạn</span><strong>{{ $overdueCount }}</strong><small>{{ $overdueCount ? 'Cần xử lý ngay' : 'Không có đợt quá hạn' }}</small></button></section>

    <div class="ms7-tabs" role="tablist"><button type="button" data-ms7-tab="rounds" :class="{ 'active': tab === 'rounds' }" @click="tab = 'rounds'"><i class="bi bi-calendar2-week"></i> Chu kỳ &amp; đợt <b>{{ $schedules->count() }}</b></button><button type="button" data-ms7-tab="documents" :class="{ 'active': tab === 'documents' }" @click="tab = 'documents'"><i class="bi bi-folder2-open"></i> Hồ sơ <b>{{ $documents->count() }}</b></button><button type="button" data-ms7-tab="equipment" :class="{ 'active': tab === 'equipment' }" @click="tab = 'equipment'"><i class="bi bi-upc-scan"></i> Thiết bị <b>{{ $serials->count() }}</b></button><button type="button" data-ms7-tab="history" :class="{ 'active': tab === 'history' }" @click="tab = 'history'"><i class="bi bi-clock-history"></i> Lịch sử <b>{{ $activity->count() }}</b></button></div>

    <section class="ms7-panel" data-ms7-panel="rounds" :class="{ 'active': tab === 'rounds' }">
        @forelse($cycles as $cycle)
            <article class="ms7-cycle" :hidden="! hienChuKy({{ $cycle['hasDone'] ? 'true' : 'false' }}, {{ $cycle['hasOpen'] ? 'true' : 'false' }}, {{ $cycle['hasOverdue'] ? 'true' : 'false' }})"><header class="ms7-cycle-header"><div><span class="ms7-eyebrow">CHU KỲ BẢO TRÌ</span><h2>{{ $cycle['title'] }}</h2><p>{{ $cycle['completed'] }}/{{ $cycle['planned'] }} đợt hoàn thành @if($cycle['next'])· Đợt tiếp theo {{ optional($cycle['next']->scheduled_date)->format('d/m/Y') ?: 'chưa đặt lịch' }}@endif</p></div><div class="ms7-cycle-progress"><strong>{{ $cycle['percent'] }}%</strong><div><span style="width:{{ $cycle['percent'] }}%"></span></div></div></header>
                <div class="ms7-round-grid">
                    @foreach($cycle['rows'] as $row)
                        <a class="ms7-round {{ $row['done'] ? 'done' : ($row['overdue'] ? 'overdue' : ($row['active'] ? 'current' : '')) }}" href="{{ route('projects-unified.maintenance.show', ['schedule'=>$row['item']->id]) }}" data-ms7-round="{{ $row['done'] ? 'done' : 'open' }}" data-ms7-overdue="{{ $row['overdue'] ? '1' : '0' }}" :hidden="! hienDot('{{ $row['done'] ? 'done' : 'open' }}', {{ $row['overdue'] ? 'true' : 'false' }})"><div class="ms7-round-top"><span>ĐỢT {{ $row['item']->round_no ?: 1 }}/{{ $row['item']->total_rounds ?: 1 }}</span><i class="bi {{ $row['done'] ? 'bi-check-circle-fill' : 'bi-calendar-event' }}"></i></div><strong>{{ optional($row['item']->scheduled_date)->format('d/m/Y') ?: 'Chưa đặt ngày' }}</strong><span class="ms7-status {{ $statusTone[$row['item']->status] ?? 'muted' }}">{{ $statuses[$row['item']->status] ?? $row['item']->status }}</span><div class="ms7-round-person"><i class="bi bi-person"></i> {{ $row['item']->leader?->user?->name ?: ($row['item']->assignee_names ?: 'Chưa phân công') }}</div><footer><span>{{ $row['item']->schedule_code ?: '#'.$row['item']->id }}</span><i class="bi bi-arrow-right"></i></footer></a>
                    @endforeach
                </div>
            </article>
        @empty<div class="ms7-empty"><i class="bi bi-calendar2-x"></i><strong>Chưa có đợt bảo trì</strong><span>Kích hoạt bảo trì sau nghiệm thu để tạo chu kỳ và các đợt công việc.</span></div>@endforelse
        <div class="ms7-filter-empty" data-ms7-filter-empty hidden :hidden="soDotHien() > 0 || tong === 0">Không có đợt phù hợp bộ lọc đang chọn.</div>
    </section>

    <section class="ms7-panel" data-ms7-panel="documents" :class="{ 'active': tab === 'documents' }"><header class="ms7-section-header"><div><h2>Hồ sơ công trình</h2><p>Hợp đồng, nghiệm thu, phiếu bảo hành và hồ sơ kỹ thuật.</p></div>@if($permissions['upload'])<button class="ms7-button primary" type="button" data-ms7-open-upload @click="modal = true"><i class="bi bi-cloud-arrow-up"></i> Tải hồ sơ</button>@endif</header><div class="ms7-document-list">@forelse($documents as $document)<article class="ms7-document"><span class="ms7-doc-icon"><i class="bi bi-file-earmark-text"></i></span><div><strong title="{{ $document->original_name }}">{{ \Illuminate\Support\Str::limit($document->original_name, 65) }}</strong><small>{{ $documentCategories[$document->category] ?? $document->category }} · {{ number_format($document->file_size / 1024, 1) }} KB · {{ $document->uploader?->name ?: 'Hệ thống' }} · {{ optional($document->created_at)->format('d/m/Y H:i') }}</small></div><div class="ms7-document-actions"><a href="{{ route('projects-unified.maintenance.site-files.preview', ['document'=>$document->id]) }}" target="_blank" title="Xem hồ sơ"><i class="bi bi-eye"></i></a><a href="{{ route('projects-unified.maintenance.site-files.download', ['document'=>$document->id]) }}" title="Tải xuống"><i class="bi bi-download"></i></a>@if($permissions['manage'] || (int) $document->uploaded_by === (int) auth()->id())<form method="POST" action="{{ route('projects-unified.maintenance.site-files.destroy', ['document'=>$document->id]) }}" onsubmit="return confirm('Xóa hồ sơ này?')">@csrf @method('DELETE')<button type="submit" title="Xóa hồ sơ"><i class="bi bi-trash3"></i></button></form>@endif</div></article>@empty<div class="ms7-empty compact"><i class="bi bi-folder2-open"></i><strong>Chưa có hồ sơ</strong><span>Tài liệu công trình sẽ xuất hiện tại đây sau khi tải lên.</span></div>@endforelse</div></section>

    <section class="ms7-panel" data-ms7-panel="equipment" :class="{ 'active': tab === 'equipment' }"><header class="ms7-section-header"><div><h2>Thiết bị &amp; serial bảo hành</h2><p>Theo dõi thiết bị, số serial và thời hạn bảo hành.</p></div></header><div class="ms7-table-wrap"><table class="ms7-table"><thead><tr><th>Thiết bị</th><th>Serial</th><th>Thời hạn bảo hành</th><th>Trạng thái</th></tr></thead><tbody>@forelse($serials as $serial)<tr><td><strong>{{ $serial->product_name ?: 'Thiết bị chưa xác định' }}</strong><small>{{ $serial->sku ?: 'Chưa có SKU' }}</small></td><td><code>{{ $serial->serial_code ?: '#'.$serial->serial_unit_id }}</code></td><td>{{ $serial->warranty_start_at ? \Carbon\Carbon::parse($serial->warranty_start_at)->format('d/m/Y') : '—' }} → {{ $serial->warranty_end_at ? \Carbon\Carbon::parse($serial->warranty_end_at)->format('d/m/Y') : '—' }}</td><td><span class="ms7-status info">{{ $serial->status ?: 'Đang bảo hành' }}</span></td></tr>@empty<tr><td colspan="4"><div class="ms7-empty compact">Chưa có thiết bị hoặc serial bảo hành liên kết.</div></td></tr>@endforelse</tbody></table></div></section>

    <section class="ms7-panel" data-ms7-panel="history" :class="{ 'active': tab === 'history' }"><header class="ms7-section-header"><div><h2>Lịch sử hoạt động</h2><p>Phân công, phê duyệt và thay đổi trạng thái các đợt bảo trì.</p></div></header><div class="ms7-history">@forelse($activity as $event)<article><span><i class="bi {{ $event->kind === 'approval' ? 'bi-patch-check' : 'bi-arrow-repeat' }}"></i></span><div><strong>{{ $event->kind === 'approval' ? 'Phê duyệt / phân công' : 'Cập nhật trạng thái' }}</strong><p>{{ $event->reason ?: (($statuses[$event->to_status] ?? $event->to_status) ?: 'Cập nhật dữ liệu') }}</p><small>{{ $event->actor ?: 'Hệ thống' }} · {{ $event->activity_at ? \Carbon\Carbon::parse($event->activity_at)->format('d/m/Y H:i') : '—' }}</small></div></article>@empty<div class="ms7-empty compact"><i class="bi bi-clock-history"></i><strong>Chưa có hoạt động</strong></div>@endforelse</div></section>

    @if($permissions['upload'])<div class="ms7-modal" data-ms7-upload-modal hidden :hidden="! modal"><div class="ms7-modal-overlay" data-ms7-close-upload @click="modal = false"></div><section class="ms7-modal-dialog" role="dialog" aria-modal="true"><header><div><span class="ms7-eyebrow">HỒ SƠ CÔNG TRÌNH</span><h2>Tải hồ sơ lên</h2></div><button type="button" data-ms7-close-upload @click="modal = false"><i class="bi bi-x-lg"></i></button></header><form method="POST" action="{{ route('projects-unified.maintenance.site-files.store', ['site'=>$site->id]) }}" enctype="multipart/form-data">@csrf<label>Loại hồ sơ<select name="category" required>@foreach($documentCategories as $category=>$label)<option value="{{ $category }}">{{ $label }}</option>@endforeach</select></label><label>Chọn tệp<input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.mp4"></label><label>Ghi chú<input name="description" placeholder="Mô tả nhóm hồ sơ"></label><footer><button class="ms7-button ghost" type="button" data-ms7-close-upload @click="modal = false">Hủy</button><button class="ms7-button primary" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải lên</button></footer></form></section></div>@endif
</div>
@endsection

{{-- Không còn JS tay ở đây. Toàn bộ trạng thái giao diện (tab đang mở, bộ lọc
     đợt, modal tải tệp) nằm trong x-data ở thẻ gốc .ms7-page.

     Bản cũ là 28 dòng: tự gắn sự kiện click, tự dò DOM đếm số đợt còn hiện để
     quyết định ẩn chu kỳ, tự thêm/bớt lớp active. Số đếm nay tính sẵn trong PHP
     ($tongDot, $soDotXong, $soDotMo, $soDotQuaHan) nên phần giao diện chỉ so
     sánh, không phải hỏi lại DOM. --}}
