@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-payment-requests-enterprise.css') }}?v={{ @filemtime(public_path('css/ego-payment-requests-enterprise.css')) ?: time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/ego-payment-requests-enterprise.js') }}?v={{ @filemtime(public_path('js/ego-payment-requests-enterprise.js')) ?: time() }}" defer></script>
@endpush

@section('content')
{{-- `x-init` thay khối JS nội tuyến cũ, vốn chỉ để gỡ hiệu ứng ẩn của `.ego-pr-reveal`. Dùng đúng
     móc mà tệp CSS dùng chung đã có (`.ego-pr-ui-ready .ego-pr-reveal`). --}}
<div class="ego-pr-page" x-data x-init="$el.classList.add('ego-pr-ui-ready')">
    <header class="ego-pr-page-header ego-pr-reveal">
        <div class="ego-pr-page-heading">
            <span class="ego-pr-page-icon" aria-hidden="true"><i class="bi bi-arrow-counterclockwise"></i></span>
            <div class="ego-pr-page-copy">
                <div class="ego-pr-eyebrow">TRUNG TÂM TÀI CHÍNH</div>
                <h1>Đề nghị hoàn ứng</h1>
                <p>Chọn phiếu tạm ứng đã được kế toán chi, đối soát chi phí thực tế và hoàn ứng theo chứng từ.</p>
                <div class="tw:flex tw:items-center tw:gap-2 tw:flex-wrap tw:mt-[10px]" aria-label="Quy trình hoàn ứng">
                    <x-settlement.step><i class="bi bi-receipt"></i>Chọn phiếu tạm ứng</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step><i class="bi bi-cash-stack"></i>Chi thực tế</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step><i class="bi bi-calculator"></i>Đối soát</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step><i class="bi bi-paperclip"></i>Đính kèm chứng từ</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step><i class="bi bi-person-check"></i>Duyệt</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step><i class="bi bi-check2-circle"></i>Hoàn tất</x-settlement.step>
                </div>
            </div>
        </div>
        <div class="ego-pr-page-actions">
            <button class="ego-pr-button ego-pr-button--primary" type="button" x-on:click="$dispatch('open-modal', 'createSettlement')">
                <i class="bi bi-plus-lg"></i><span>Tạo phiếu mới</span>
            </button>
        </div>
    </header>

    @if(session('success'))
        <div class="ego-pr-alert ego-pr-alert--success ego-pr-reveal" role="alert"><i class="bi bi-check-circle"></i><span>{{ session('success') }}</span></div>
    @endif
    @if($errors->any())
        <div class="ego-pr-alert ego-pr-alert--danger ego-pr-reveal" role="alert">
            <i class="bi bi-exclamation-triangle"></i><div><strong>Chưa lưu được phiếu</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        </div>
    @endif

    <section class="ego-pr-kpis ego-pr-reveal" aria-label="Thống kê đề nghị hoàn ứng">
        <article class="ego-pr-kpi ego-pr-kpi--cyan"><div><span>Tổng đề nghị</span><strong>{{ $kpi->total }}</strong><small>phiếu trong phạm vi lọc</small></div><i class="bi bi-receipt"></i></article>
        <article class="ego-pr-kpi ego-pr-kpi--orange"><div><span>Chờ xử lý</span><strong>{{ $kpi->pending }}</strong><small>đang ở luồng phê duyệt</small></div><i class="bi bi-hourglass-split"></i></article>
        <article class="ego-pr-kpi ego-pr-kpi--green"><div><span>Đã hoàn tất</span><strong>{{ $kpi->done }}</strong><small>đã kết thúc quy trình</small></div><i class="bi bi-check2-circle"></i></article>
        <article class="ego-pr-kpi ego-pr-kpi--blue"><div><span>Tổng tiền hoàn lại</span><strong class="ego-pr-kpi-money">{{ $kpi->refundText }}</strong><small>giá trị hoàn về công ty</small></div><i class="bi bi-arrow-return-left"></i></article>
    </section>

    <section class="ego-pr-filter-card ego-pr-reveal" aria-label="Bộ lọc đề nghị hoàn ứng">
        <div class="ego-pr-filter-head">
            <div><span class="ego-pr-filter-icon"><i class="bi bi-sliders2"></i></span><div><strong>Bộ lọc &amp; tìm kiếm</strong><small>Thu hẹp dữ liệu theo đúng nhu cầu xử lý.</small></div></div>
        </div>
        <form method="GET" action="{{ route('settlement_requests.index') }}" class="ego-pr-filter-form">
            <div class="ego-pr-field ego-pr-field--search"><label>Tìm kiếm</label><div class="ego-pr-input-icon"><i class="bi bi-search"></i><input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Mã phiếu / Người đề nghị / Nội dung..."></div></div>
            <div class="ego-pr-field"><label>Trạng thái</label><select name="status"><option value="">Tất cả trạng thái</option>@foreach($labels as $k=>$v)<option value="{{ $k }}" @selected($filters['status']===$k)>{{ $v }}</option>@endforeach</select></div>
            @if($canViewAll)<div class="ego-pr-field"><label>Người tạo</label><select name="created_by"><option value="">Tất cả nhân sự</option>@foreach($creators as $u)<option value="{{ $u->id }}" @selected($filters['created_by']===(string)$u->id)>{{ $u->name }}</option>@endforeach</select></div>@endif
            <div class="ego-pr-field"><label>Từ ngày</label><input type="date" name="date_from" value="{{ $filters['date_from'] }}"></div>
            <div class="ego-pr-field"><label>Đến ngày</label><input type="date" name="date_to" value="{{ $filters['date_to'] }}"></div>
            <div class="ego-pr-filter-actions"><a class="ego-pr-button ego-pr-button--ghost" href="{{ route('settlement_requests.index') }}"><i class="bi bi-arrow-counterclockwise"></i><span>Xóa lọc</span></a><button class="ego-pr-button ego-pr-button--primary" type="submit"><i class="bi bi-funnel"></i><span>Áp dụng</span></button></div>
        </form>
    </section>

    {{-- ⚠️ Bốn lớp thẻ/bảng của bản cũ KHÔNG tồn tại trong tệp CSS dùng chung, cũng không ở đâu khác
         trong repo, nên thẻ bảng của trang này mất hẳn vỏ: đo được nền trong suốt, viền 0px, bo góc
         0px, không đổ bóng. Hai trang anh em cùng module (advance_requests, payment_requests) dùng
         bộ tên đúng; tên cũ liệt kê trong SettlementRequestListPageTest để guard chặn quay lại. --}}
    <section class="ego-pr-data-card ego-pr-reveal">
        <div class="ego-pr-data-head"><div><strong>Danh sách đề nghị hoàn ứng</strong><span>Hiển thị {{ $kpi->pageCount }} phiếu trên trang hiện tại</span></div><span class="ego-pr-data-count">{{ $kpi->totalCount }} phiếu</span></div>
        <div class="ego-pr-desktop-table">
        <div class="ego-pr-table-scroll">
            <table class="ego-pr-table">
                <thead><tr><th scope="col">Mã hoàn ứng</th><th scope="col">Phiếu tạm ứng</th><th scope="col">Người đề nghị</th><th scope="col">Nội dung / lý do</th><th scope="col">Đã tạm ứng</th><th scope="col">Đã chi</th><th scope="col">Kết quả đối soát</th><th scope="col">Chứng từ</th><th scope="col">Trạng thái</th><th scope="col">Thao tác</th></tr></thead>
                <tbody>
                @forelse($settlementRows as $row)
                    <tr>
                        <td><a href="{{ route('settlement_requests.show',$row->id) }}" class="tw:text-[#087f98] tw:font-extrabold tw:no-underline">{{ $row->code }}</a><br><small class="tw:text-[rgba(33,37,41,0.75)]!">{{ $row->createdAtText }}</small></td>
                        <td>@if($row->advanceCode)@if(\Illuminate\Support\Facades\Route::has('advance_requests.show'))<a href="{{ route('advance_requests.show',$row->advanceRequestId) }}" class="tw:text-[#087f98] tw:font-extrabold tw:no-underline">{{ $row->advanceCode }}</a>@else<strong>{{ $row->advanceCode }}</strong>@endif @else<span class="tw:text-[rgba(33,37,41,0.75)]!">—</span>@endif</td>
                        <td><strong>{{ $row->recipientName }}</strong><br><small class="tw:text-[rgba(33,37,41,0.75)]!">{{ $row->company }}</small></td>
                        <td class="tw:min-w-[240px]">{{ $row->reason }}@if($row->note)<br><small class="tw:text-[rgba(33,37,41,0.75)]!">{{ $row->note }}</small>@endif</td>
                        <td class="tw:font-extrabold tw:whitespace-nowrap">{{ $row->advanceAmountText }}</td>
                        <td class="tw:font-extrabold tw:whitespace-nowrap">{{ $row->actualAmountText }}</td>
                        <td class="tw:font-extrabold tw:whitespace-nowrap"><span class="{{ $row->outcomeClass }}">{{ $row->outcomeText }}</span></td>
                        <td><div class="tw:flex tw:gap-[6px] tw:flex-wrap">@forelse($row->attachments as $file)<a class="tw:inline-flex tw:items-center tw:gap-[5px] tw:px-[9px] tw:py-[6px] tw:border tw:border-solid tw:border-[#d8e7ef] tw:rounded-[9px] tw:bg-[#f8fbfd] tw:text-[#0f6f82] tw:no-underline tw:text-[12px] tw:font-bold" target="_blank" href="{{ asset('storage/'.$file['path']) }}"><i class="bi bi-paperclip"></i>CT {{ $file['index'] }}</a>@empty<span class="tw:text-[rgba(33,37,41,0.75)]!">—</span>@endforelse</div></td>
                        <td><span class="tw:inline-flex tw:items-center tw:px-[9px] tw:py-[5px] tw:rounded-[999px] tw:bg-[#eef6ff] tw:text-[#23516f] tw:text-[12px] tw:font-bold">{{ $row->statusLabel }}</span></td>
                        <td><div class="tw:flex tw:gap-[6px] tw:flex-wrap tw:[&>form]:inline">
                            <x-settlement.action :href="route('settlement_requests.show',$row->id)"><i class="bi bi-eye"></i>Xem</x-settlement.action>
                            @if($row->canSubmit)<form method="POST" action="{{ route('settlement_requests.submit',$row->id) }}">@csrf<x-settlement.action tone="primary">Gửi duyệt</x-settlement.action></form>@endif
                            @if($row->canManagementApprove)<form method="POST" action="{{ route('settlement_requests.management_approve',$row->id) }}">@csrf<x-settlement.action tone="ok">Duyệt</x-settlement.action></form><form method="POST" action="{{ route('settlement_requests.management_reject',$row->id) }}">@csrf<x-settlement.action tone="danger">Từ chối</x-settlement.action></form>@endif
                            @if($row->canAccountingApprove)<form method="POST" action="{{ route('settlement_requests.accounting_approve',$row->id) }}">@csrf<x-settlement.action tone="ok">Hoàn tất</x-settlement.action></form><form method="POST" action="{{ route('settlement_requests.accounting_reject',$row->id) }}">@csrf<x-settlement.action tone="danger">Từ chối</x-settlement.action></form>@endif
                            @if($row->canDelete)<form method="POST" action="{{ route('settlement_requests.destroy',$row->id) }}" x-on:submit="window.confirm(@js($row->deleteConfirmText)) || $event.preventDefault()">@csrf @method('DELETE')<x-settlement.action>Xóa</x-settlement.action></form>@endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-9">Chưa có đề nghị hoàn ứng.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        </div>
        <footer class="ego-pr-table-footer"><div>{{ $items->links() }}</div></footer>
    </section>
</div>

{{-- Bộ đối soát: `x-data` mang thẳng danh sách phiếu tạm ứng từ presenter, thay cho việc nhồi dữ
     liệu vào `data-*` của từng `<option>` rồi JS đọc lại bằng `option.dataset`. --}}
<x-ui.modal name="createSettlement" title="Tạo đề nghị hoàn ứng" subtitle="TRUNG TÂM TÀI CHÍNH" size="lg">
 <form method="POST" action="{{ route('settlement_requests.store') }}" enctype="multipart/form-data"
       x-data="{
         phieu: @js(collect($advanceOptions)->keyBy('id')),
         chon: @js(collect($advanceOptions)->firstWhere('selected', true)->id ?? ''),
         daChi: @js($createForm['actual_amount']),
         get hienTai() { return this.chon ? (this.phieu[this.chon] ?? null) : null },
         tien(v) { return new Intl.NumberFormat('vi-VN').format(Math.max(0, Number(v) || 0)) + ' đ' },
         get chenhLech() { return this.hienTai ? Math.max(0, Number(this.daChi || 0)) - this.hienTai.amount : 0 },
       }">
  @csrf
  <div class="tw:flex tw:items-center tw:gap-2 tw:flex-wrap tw:mb-6">
   <x-settlement.step>1. Chọn phiếu tạm ứng</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
   <x-settlement.step>2. Chi thực tế</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
   <x-settlement.step>3. Đối soát</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
   <x-settlement.step>4. Chứng từ</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
   <x-settlement.step>5. Duyệt</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
   <x-settlement.step>6. Hoàn tất</x-settlement.step>
  </div>

  <div class="tw:grid tw:grid-cols-[1fr_1fr] tw:gap-[14px] tw:max-[769px]:grid-cols-1 tw:[&_label]:block tw:[&_label]:font-bold tw:[&_label]:text-[13px] tw:[&_label]:mb-[6px] tw:[&_label]:text-[#334155] tw:[&_input]:w-full tw:[&_textarea]:w-full tw:[&_select]:w-full tw:[&_input]:border tw:[&_textarea]:border tw:[&_select]:border tw:[&_input]:border-solid tw:[&_textarea]:border-solid tw:[&_select]:border-solid tw:[&_input]:border-[#d7e3ea] tw:[&_textarea]:border-[#d7e3ea] tw:[&_select]:border-[#d7e3ea] tw:[&_input]:rounded-[10px] tw:[&_textarea]:rounded-[10px] tw:[&_select]:rounded-[10px] tw:[&_input]:px-3 tw:[&_textarea]:px-3 tw:[&_select]:px-3 tw:[&_input]:py-[10px] tw:[&_textarea]:py-[10px] tw:[&_select]:py-[10px] tw:[&_input]:bg-white tw:[&_textarea]:bg-white tw:[&_select]:bg-white tw:[&_input]:outline-none tw:[&_textarea]:outline-none tw:[&_input:focus]:border-[#30b6c7] tw:[&_textarea:focus]:border-[#30b6c7] tw:[&_input:focus]:shadow-[0_0_0_3px_rgba(48,182,199,0.11)] tw:[&_textarea:focus]:shadow-[0_0_0_3px_rgba(48,182,199,0.11)]">
   <div class="tw:[grid-column:1/-1] tw:max-[769px]:[grid-column:auto]">
    <label for="settlementAdvanceRequest">Phiếu tạm ứng cần hoàn *</label>
    <select name="advance_request_id" id="settlementAdvanceRequest" required @disabled($advanceOptions === []) x-model="chon">
     <option value="">-- Chọn phiếu tạm ứng đã được kế toán chi --</option>
     @foreach($advanceOptions as $option)
      <option value="{{ $option->id }}" @selected($option->selected)>{{ $option->label }}</option>
     @endforeach
    </select>
    @if($advanceOptions === [])
     <div class="tw:text-[12px] tw:mt-[5px] tw:text-[#b45309]">Chưa có phiếu tạm ứng nào ở trạng thái “Kế toán đã chi” và chưa hoàn ứng.</div>
    @else
     <div class="tw:text-[12px] tw:mt-[5px] tw:text-[#64748b]">Chỉ hiển thị phiếu tạm ứng đã được kế toán xác nhận chi và chưa có phiếu hoàn ứng.</div>
    @endif
   </div>

   <div class="tw:[grid-column:1/-1] tw:max-[769px]:[grid-column:auto] tw:grid tw:grid-cols-[repeat(4,minmax(0,1fr))] tw:max-[769px]:grid-cols-2 tw:gap-[10px] tw:p-3 tw:border tw:border-solid tw:border-[#dbeaf0] tw:bg-[#f8fcfd] tw:rounded-[12px] tw:[&>div]:min-w-0 tw:[&_span]:block tw:[&_span]:text-[11px] tw:[&_span]:text-[#64748b] tw:[&_span]:mb-1 tw:[&_strong]:block tw:[&_strong]:text-[13px] tw:[&_strong]:text-[#183b4b] tw:[&_strong]:whitespace-nowrap tw:[&_strong]:overflow-hidden tw:[&_strong]:text-ellipsis"
        x-show="hienTai" x-cloak>
    <div><span>Mã tạm ứng</span><strong x-text="hienTai?.code ?? '—'">—</strong></div>
    <div><span>Người tạm ứng</span><strong x-text="hienTai?.recipient ?? '—'">—</strong></div>
    <div><span>Số tiền tạm ứng</span><strong x-text="hienTai ? tien(hienTai.amount) : '0 đ'">0 đ</strong></div>
    <div><span>Hạn hoàn ứng</span><strong x-text="hienTai?.dueText ?? '—'">—</strong></div>
   </div>

   <div><label for="settlementActualAmount">Số tiền đã chi *</label><input type="number" min="0" step="1000" name="actual_amount" id="settlementActualAmount" x-model="daChi" required placeholder="0"><div class="tw:text-[12px] tw:mt-[5px] tw:text-[#64748b]">Nhập tổng chi phí thực tế theo chứng từ.</div></div>
   <div><label for="settlementAdvanceAmountReadonly">Số tiền tạm ứng</label><input type="text" id="settlementAdvanceAmountReadonly" readonly x-bind:value="hienTai ? tien(hienTai.amount) : '0 đ'" value="0 đ"><div class="tw:text-[12px] tw:mt-[5px] tw:text-[#64748b]">Tự động lấy từ phiếu tạm ứng đã chọn.</div></div>

   <div class="tw:[grid-column:1/-1] tw:max-[769px]:[grid-column:auto] tw:px-[13px] tw:py-[11px] tw:rounded-[11px] tw:border tw:border-solid tw:text-[13px]"
        x-bind:class="! hienTai || ! daChi ? 'tw:bg-[#f0f9fb] tw:border-[#cfeef3]' : (chenhLech > 0 ? 'tw:bg-[#fff8ed] tw:border-[#fed7aa]' : (chenhLech < 0 ? 'tw:bg-[#f0f9fb] tw:border-[#cfeef3]' : 'tw:bg-[#effaf4] tw:border-[#ccebd8]'))">
    <template x-if="! hienTai"><span>Chọn phiếu tạm ứng và nhập số tiền đã chi để hệ thống tự đối soát.</span></template>
    <template x-if="hienTai && ! daChi"><span>Đã chọn <strong class="tw:text-[#087f98]" x-text="hienTai.code"></strong>. Nhập số tiền đã chi để tính số tiền cần hoàn.</span></template>
    <template x-if="hienTai && daChi && chenhLech < 0"><span>Nhân sự cần hoàn lại công ty: <strong class="tw:text-[#087f98]" x-text="tien(Math.abs(chenhLech))"></strong></span></template>
    <template x-if="hienTai && daChi && chenhLech > 0"><span>Chi vượt tạm ứng — công ty cần thanh toán thêm: <strong class="tw:text-[#c2410c]" x-text="tien(chenhLech)"></strong></span></template>
    <template x-if="hienTai && daChi && chenhLech === 0"><span><strong class="tw:text-[#15803d]">Khớp đủ.</strong> Không phát sinh tiền hoàn lại hoặc thanh toán thêm.</span></template>
   </div>

   <div class="tw:[grid-column:1/-1] tw:max-[769px]:[grid-column:auto]"><label for="settlementReason">Nội dung / lý do hoàn ứng *</label><textarea id="settlementReason" name="reason" rows="3" required placeholder="Mô tả nội dung chi phí và lý do hoàn ứng...">{{ $createForm['reason'] }}</textarea></div>
   <div class="tw:[grid-column:1/-1] tw:max-[769px]:[grid-column:auto]"><label for="settlementAttachments">Đính kèm chứng từ *</label><input id="settlementAttachments" type="file" name="attachments[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"><div class="tw:text-[12px] tw:mt-[5px] tw:text-[#64748b]">Tối đa 10 file, mỗi file 10MB. Hỗ trợ ảnh, PDF, Word và Excel.</div></div>
   <div class="tw:[grid-column:1/-1] tw:max-[769px]:[grid-column:auto]"><label for="settlementNote">Ghi chú</label><textarea id="settlementNote" name="note" rows="2" placeholder="Thông tin bổ sung nếu có...">{{ $createForm['note'] }}</textarea></div>
  </div>

  <div class="tw:flex tw:flex-wrap tw:justify-end tw:gap-2 tw:mt-4 tw:pt-[14px] tw:[border-top:1px_solid_#e8eef2]">
   <button type="button" class="ego-pr-button ego-pr-button--ghost" x-on:click="$dispatch('close-modal', 'createSettlement')">Hủy</button>
   <button type="submit" class="ego-pr-button ego-pr-button--secondary" name="submit_now" value="0" @disabled($advanceOptions === [])>Lưu nháp</button>
   <button type="submit" class="ego-pr-button ego-pr-button--primary" name="submit_now" value="1" @disabled($advanceOptions === [])><i class="bi bi-send"></i> Lưu &amp; gửi duyệt</button>
  </div>
 </form>
</x-ui.modal>

{{-- Có lỗi xác thực thì mở lại hộp thoại — thay khối JS cũ gọi `new bootstrap.Modal(...).show()`. --}}
@if($errors->any())<div x-data x-init="$nextTick(() => $dispatch('open-modal', 'createSettlement'))"></div>@endif
@endsection
