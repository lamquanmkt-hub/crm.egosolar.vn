@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-payment-requests-enterprise.css') }}?v={{ @filemtime(public_path('css/ego-payment-requests-enterprise.css')) ?: time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/ego-payment-requests-enterprise.js') }}?v={{ @filemtime(public_path('js/ego-payment-requests-enterprise.js')) ?: time() }}" defer></script>
@endpush

@section('content')
{{-- `x-init` thay khối JS nội tuyến cũ, vốn chỉ để gỡ hiệu ứng ẩn của `.ego-pr-reveal`. Dùng đúng móc mà
     CSS dùng chung đã có (`.ego-pr-ui-ready .ego-pr-reveal`) thay vì gán inline style từng phần tử. --}}
<div class="ego-pr-page" x-data x-init="$el.classList.add('ego-pr-ui-ready')">
<header class="ego-pr-page-header ego-pr-reveal">
  <div class="ego-pr-page-heading"><span class="ego-pr-page-icon"><i class="bi bi-cash-coin"></i></span><div class="ego-pr-page-copy"><div class="ego-pr-eyebrow">TRUNG TÂM TÀI CHÍNH</div><h1>Đề nghị tạm ứng</h1><p>Một hồ sơ xuyên suốt từ xin tạm ứng đến hoàn ứng và quyết toán.</p></div></div>
  <div class="ego-pr-page-actions"><button class="ego-pr-button ego-pr-button--primary" type="button" x-on:click="$dispatch('open-modal', 'createAdvance')"><i class="bi bi-plus-lg"></i><span>Tạo phiếu mới</span></button></div>
</header>

@if(session('success'))<div class="ego-pr-alert ego-pr-alert--success ego-pr-reveal"><i class="bi bi-check-circle"></i><span>{{ session('success') }}</span></div>@endif
@if($errors->any())<div class="ego-pr-alert ego-pr-alert--danger ego-pr-reveal"><i class="bi bi-exclamation-triangle"></i><div><strong>Chưa xử lý được phiếu</strong><ul class="tw:mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>@endif

<section class="ego-pr-kpis ego-pr-reveal">
 <article class="ego-pr-kpi ego-pr-kpi--cyan"><div><span>Tổng tạm ứng</span><strong>{{ $kpi->total }}</strong><small>hồ sơ trong phạm vi lọc</small></div><i class="bi bi-receipt"></i></article>
 <article class="ego-pr-kpi ego-pr-kpi--orange"><div><span>Chờ duyệt / xử lý</span><strong>{{ $kpi->pending }}</strong><small>QL tài chính hoặc kế toán cần xử lý</small></div><i class="bi bi-hourglass-split"></i></article>
 <article class="ego-pr-kpi ego-pr-kpi--blue{{ $kpi->hasOverdue ? ' tw:[&>div>small]:text-[#a74d2d]' : '' }}"><div><span>Cần hoàn ứng</span><strong>{{ $kpi->needSettlement }}</strong><small>{{ $kpi->settlementHint }}</small></div><i class="bi bi-receipt-cutoff"></i></article>
 <article class="ego-pr-kpi ego-pr-kpi--green"><div><span>Đã quyết toán</span><strong>{{ $kpi->done }}</strong><small>đã hoàn ứng và khép hồ sơ</small></div><i class="bi bi-check2-circle"></i></article>
</section>

<section class="ego-pr-filter-card ego-pr-reveal">
 <div class="ego-pr-filter-head"><div><span class="ego-pr-filter-icon"><i class="bi bi-sliders2"></i></span><div><strong>Bộ lọc &amp; tìm kiếm</strong><small>Thu hẹp dữ liệu theo đúng nhu cầu xử lý.</small></div></div></div>
 <form method="GET" action="{{ route('advance_requests.index') }}" class="ego-pr-filter-form">
  <div class="ego-pr-field ego-pr-field--search"><label>Tìm kiếm</label><div class="ego-pr-input-icon"><i class="bi bi-search"></i><input name="q" value="{{ $filters['q'] }}" placeholder="Mã phiếu / Người nhận / Nội dung..."></div></div>
  <div class="ego-pr-field"><label>Trạng thái tạm ứng</label><select name="status"><option value="">Tất cả trạng thái</option>@foreach($labels as $k=>$v)<option value="{{ $k }}" @selected($filters['status']===$k)>{{ $v }}</option>@endforeach</select></div>
  @if($canViewAll)<div class="ego-pr-field"><label>Người tạo</label><select name="created_by"><option value="">Tất cả nhân sự</option>@foreach($creators as $u)<option value="{{ $u->id }}" @selected($filters['created_by']===(string)$u->id)>{{ $u->name }}</option>@endforeach</select></div>@endif
  <div class="ego-pr-field"><label>Từ ngày</label><input type="date" name="date_from" value="{{ $filters['date_from'] }}"></div>
  <div class="ego-pr-field"><label>Đến ngày</label><input type="date" name="date_to" value="{{ $filters['date_to'] }}"></div>
  <div class="ego-pr-filter-actions"><a class="ego-pr-button ego-pr-button--ghost" href="{{ route('advance_requests.index') }}"><i class="bi bi-arrow-counterclockwise"></i><span>Xóa lọc</span></a><button class="ego-pr-button ego-pr-button--primary" type="submit"><i class="bi bi-funnel"></i><span>Áp dụng</span></button></div>
 </form>
</section>

<section class="ego-pr-data-card ego-pr-reveal">
 <div class="ego-pr-data-head tw:[&>div]:flex tw:[&>div]:flex-col tw:[&>div]:gap-[3px] tw:[&>div]:min-w-0 tw:[&>div>strong]:text-[13px] tw:[&>div>strong]:leading-[1.35] tw:[&>div>strong]:text-[#183847] tw:[&>div>span]:text-[11px] tw:[&>div>span]:leading-[1.4] tw:[&>div>span]:text-[#7a8e99]">
  <div>
   <strong>Danh sách hồ sơ tạm ứng</strong>
   <span>Hiển thị {{ $kpi->pageCount }} phiếu trên trang hiện tại • Theo dõi trực tiếp tiến độ và quyết toán.</span>
  </div>
  <span class="ego-pr-data-count">{{ $kpi->totalCount }} phiếu</span>
 </div>
 <div class="ego-pr-desktop-table">
  <div class="ego-pr-table-scroll tw:overflow-x-auto tw:overflow-y-hidden tw:[scrollbar-width:thin]">
   {{-- Đệm ngang và chiều cao dòng phải khai bằng biến thể CON TRỰC TIẾP: luật `.ego-pr-table
        tbody td` của tệp CSS dùng chung có độ đặc hiệu (0,1,2), utility đặt thẳng trên `td` chỉ
        (0,1,0) nên sẽ thua. Bản cũ giải quyết bằng `!important`; cách này không cần dấu `!` nào. --}}
   <table class="ego-pr-table tw:w-full tw:min-w-[1220px] tw:[table-layout:fixed] tw:max-[1101px]:min-w-[1180px] tw:[&>thead>tr>th]:px-3 tw:[&>tbody>tr>td]:px-3 tw:[&>tbody>tr>td]:leading-[1.45]">
    <thead><tr>
     <th class="tw:w-[145px]">Mã phiếu</th>
     <th class="tw:w-[145px]">Người nhận</th>
     <th class="tw:w-[210px]">Nội dung / lý do</th>
     <th class="tw:w-[112px]">Số tiền</th>
     <th class="tw:w-[105px]">Hạn hoàn ứng</th>
     <th class="tw:w-[205px]">Tiến độ hồ sơ</th>
     <th class="tw:w-[180px]">Quyết toán</th>
     <th class="tw:w-[110px]">Người tạo</th>
     <th class="tw:w-[178px]">Thao tác</th>
    </tr></thead>
    <tbody>
 @forelse($advanceRows as $row)
 <tr>
  <td class="tw:[overflow-wrap:normal]"><a href="{{ route('advance_requests.show',$row->id) }}" class="tw:[font-weight:850] tw:text-[#087f98] tw:no-underline">{{ $row->code }}</a><br><span class="tw:text-[11px] tw:text-[#78909c]">{{ $row->createdAtText }}</span></td>
  <td class="tw:[overflow-wrap:anywhere]"><strong>{{ $row->recipientName }}</strong><br><span class="tw:text-[11px] tw:text-[#78909c]">{{ $row->company }}</span></td>
  <td class="tw:[overflow-wrap:anywhere]">{{ $row->reasonText }}@if($row->noteText)<br><span class="tw:text-[11px] tw:text-[#78909c]">{{ $row->noteText }}</span>@endif</td>
  <td class="tw:[overflow-wrap:normal] tw:[font-weight:850] tw:text-[#07856f] tw:whitespace-nowrap">{{ $row->amountText }}</td>
  {{-- Màu quá hạn đặt trên <span> chứ không trên <td>: luật `.ego-pr-table tbody td{color:…}`
       (0,1,2) sẽ nuốt utility đặt thẳng trên ô, và bản cũ phải dùng `!important` vì vậy. --}}
  <td class="tw:[overflow-wrap:normal]"><span @class(['tw:[font-weight:850] tw:text-[#b42318]' => $row->stage->overdue])>{{ $row->dueDateText }}</span></td>
  <td class="tw:[overflow-wrap:anywhere]"><span class="tw:inline-flex tw:items-start tw:gap-[6px] tw:max-w-[190px] tw:px-[9px] tw:py-[6px] tw:rounded-[10px] tw:text-[11px] tw:[font-weight:850] tw:leading-[1.35] tw:[white-space:normal] tw:border tw:border-solid {{ $row->stage->toneClass }}"><i class="bi {{ $row->stage->icon }} tw:text-[11px]"></i><span>{{ $row->stage->label }}</span></span>@if($row->stage->note)<span class="tw:block tw:max-w-[190px] tw:mt-[5px] tw:text-[#81919d] tw:text-[10px] tw:leading-[1.4]">{{ $row->stage->note }}</span>@endif</td>
  <td class="tw:[overflow-wrap:anywhere]"><div class="tw:min-w-[160px] tw:max-w-[190px]"><strong class="tw:block tw:text-[#213d4b] tw:text-[12px] tw:leading-[1.35]">{{ $row->outcomeLabel }}</strong><small class="tw:block tw:text-[#758b97] tw:mt-1 tw:text-[10px] tw:leading-[1.4]">{{ $row->outcomeSub }}</small></div></td>
  <td class="tw:[overflow-wrap:anywhere]"><strong>{{ $row->creatorName }}</strong></td>
  <td class="tw:[overflow-wrap:anywhere]"><div class="tw:flex tw:items-center tw:gap-[5px] tw:flex-wrap tw:justify-start tw:[align-content:flex-start] tw:[&>form]:inline-flex">
   <x-advance.action :href="route('advance_requests.show',$row->id)"><i class="bi bi-eye"></i> Xem</x-advance.action>
   @if($row->canCreateSettlement)<x-advance.action tone="primary" :href="route('advance_requests.show',$row->id).'#hoan-ung'"><i class="bi bi-receipt-cutoff"></i> Hoàn ứng</x-advance.action>@endif
   @if($row->canSubmit)<form method="POST" action="{{ route('advance_requests.submit',$row->id) }}">@csrf<x-advance.action tone="ok">Gửi duyệt</x-advance.action></form>@endif
   @if($row->canManagementApprove)<form method="POST" action="{{ route('advance_requests.management_approve',$row->id) }}">@csrf<x-advance.action tone="ok">Duyệt tạm ứng</x-advance.action></form><form method="POST" action="{{ route('advance_requests.management_reject',$row->id) }}">@csrf<x-advance.action tone="danger">Từ chối</x-advance.action></form>@endif
   @if($row->canAccountingApprove)<form method="POST" action="{{ route('advance_requests.accounting_approve',$row->id) }}">@csrf<x-advance.action tone="ok">Xác nhận chi</x-advance.action></form><form method="POST" action="{{ route('advance_requests.accounting_reject',$row->id) }}">@csrf<x-advance.action tone="danger">Từ chối</x-advance.action></form>@endif
   @if($row->canApproveSettlement)<form method="POST" action="{{ route('settlement_requests.management_approve',$row->settlementId) }}">@csrf<x-advance.action tone="ok">Duyệt hoàn ứng</x-advance.action></form>@endif
   @if($row->canReconcileSettlement)<form method="POST" action="{{ route('settlement_requests.accounting_approve',$row->settlementId) }}" x-on:submit="window.confirm(@js('Xác nhận đối soát và hoàn tất hồ sơ?')) || $event.preventDefault()">@csrf<x-advance.action tone="ok">Đối soát</x-advance.action></form>@endif
  </div></td>
 </tr>
 @empty
 <tr><td colspan="9" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] py-5">Chưa có đề nghị tạm ứng.</td></tr>
 @endforelse
    </tbody>
   </table>
  </div>
 </div>
 <footer class="ego-pr-table-footer"><div>{{ $items->links() }}</div></footer>
</section>
</div>

<x-ui.modal name="createAdvance" title="Tạo đề nghị tạm ứng" subtitle="Khởi tạo phiếu và gửi theo luồng phê duyệt." size="lg">
 <form method="POST" action="{{ route('advance_requests.store') }}">@csrf
  <div class="ego-pr-eyebrow tw:mb-3">TÀI CHÍNH NỘI BỘ</div>
  <div class="tw:grid tw:grid-cols-[repeat(2,minmax(0,1fr))] tw:gap-[14px] tw:max-[769px]:grid-cols-1 tw:[&_label]:flex tw:[&_label]:justify-between tw:[&_label]:gap-2 tw:[&_label]:mb-[6px] tw:[&_label]:text-[12px] tw:[&_label]:font-extrabold tw:[&_label]:text-[#334155] tw:[&_input]:w-full tw:[&_textarea]:w-full tw:[&_select]:w-full tw:[&_input]:border tw:[&_textarea]:border tw:[&_select]:border tw:[&_input]:border-solid tw:[&_textarea]:border-solid tw:[&_select]:border-solid tw:[&_input]:border-[#d9e5eb] tw:[&_textarea]:border-[#d9e5eb] tw:[&_select]:border-[#d9e5eb] tw:[&_input]:rounded-[11px] tw:[&_textarea]:rounded-[11px] tw:[&_select]:rounded-[11px] tw:[&_input]:bg-white tw:[&_textarea]:bg-white tw:[&_select]:bg-white tw:[&_input]:px-3 tw:[&_textarea]:px-3 tw:[&_select]:px-3 tw:[&_input]:py-[10px] tw:[&_textarea]:py-[10px] tw:[&_select]:py-[10px] tw:[&_input]:outline-none tw:[&_textarea]:outline-none tw:[&_select]:outline-none tw:[&_input:focus]:border-[#30b6c7] tw:[&_textarea:focus]:border-[#30b6c7] tw:[&_select:focus]:border-[#30b6c7] tw:[&_input:focus]:shadow-[0_0_0_3px_rgba(48,182,199,0.11)] tw:[&_textarea:focus]:shadow-[0_0_0_3px_rgba(48,182,199,0.11)] tw:[&_select:focus]:shadow-[0_0_0_3px_rgba(48,182,199,0.11)]">
   <div><label>Người nhận tạm ứng *</label><input name="recipient_name" value="{{ $createForm['recipient_name'] }}" required></div>
   <div><label>Số tiền *</label><input type="text" inputmode="numeric" pattern="[0-9]*" name="amount" value="{{ $createForm['amount'] }}" required></div>
   <div><label>Ngày cần tạm ứng</label><input type="date" name="needed_date" value="{{ $createForm['needed_date'] }}"></div>
   <div><label>Hạn hoàn ứng</label><input type="date" name="settlement_due_date" value="{{ $createForm['settlement_due_date'] }}"></div>
   <div class="tw:[grid-column:1/-1] tw:max-[769px]:[grid-column:auto]"><label>Lý do / Nội dung tạm ứng *</label><textarea rows="3" name="reason" required>{{ $createForm['reason'] }}</textarea></div>
   <div><label>Ngân hàng</label><input name="bank_name" value="{{ $createForm['bank_name'] }}"></div>
   <div><label>Số tài khoản</label><input name="bank_account" value="{{ $createForm['bank_account'] }}"></div>
   <div class="tw:[grid-column:1/-1] tw:max-[769px]:[grid-column:auto]"><label>Chủ tài khoản</label><input name="bank_account_name" value="{{ $createForm['bank_account_name'] }}"></div>
   <div class="tw:[grid-column:1/-1] tw:max-[769px]:[grid-column:auto]"><label>Ghi chú</label><textarea rows="2" name="note">{{ $createForm['note'] }}</textarea></div>
  </div>
  <div class="tw:flex tw:flex-wrap tw:justify-end tw:gap-2 tw:mt-4 tw:pt-[14px] tw:[border-top:1px_solid_#e8eef2]">
   <button type="button" class="ego-pr-button ego-pr-button--ghost" x-on:click="$dispatch('close-modal', 'createAdvance')">Đóng</button>
   <button class="ego-pr-button ego-pr-button--secondary" name="submit_now" value="0">Lưu nháp</button>
   <button class="ego-pr-button ego-pr-button--primary" name="submit_now" value="1"><i class="bi bi-send"></i><span>Lưu &amp; gửi duyệt</span></button>
  </div>
 </form>
</x-ui.modal>

{{-- Có lỗi xác thực thì mở lại hộp thoại — thay khối JS cũ gọi `new bootstrap.Modal(...).show()`. --}}
@if($errors->any())<div x-data x-init="$nextTick(() => $dispatch('open-modal', 'createAdvance'))"></div>@endif
@endsection
