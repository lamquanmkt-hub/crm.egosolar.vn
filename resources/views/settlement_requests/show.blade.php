@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-payment-requests-enterprise.css') }}?v={{ @filemtime(public_path('css/ego-payment-requests-enterprise.css')) ?: time() }}">
@endpush

@push('scripts')
<script src="{{ asset('js/ego-payment-requests-enterprise.js') }}?v={{ @filemtime(public_path('js/ego-payment-requests-enterprise.js')) ?: time() }}" defer></script>
@endpush

@section('title', 'Chi tiết đề nghị hoàn ứng')

@section('content')
{{-- `x-init` thay khối JS nội tuyến cũ, vốn chỉ để gỡ hiệu ứng ẩn của `.ego-pr-reveal`. --}}
<div class="payx ego-pr-detail-page" x-data x-init="$el.classList.add('ego-pr-ui-ready')">
    @if(session('success'))<div class="payx-alert success payx-animate">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="payx-alert danger payx-animate">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="payx-alert danger payx-animate">{{ $errors->first() }}</div>@endif

    <header class="ego-pr-detail-header ego-pr-reveal">
        <div class="ego-pr-detail-heading">
            <span class="ego-pr-detail-icon"><i class="bi bi-arrow-counterclockwise"></i></span>
            <div>
                <div class="ego-pr-eyebrow">HỒ SƠ HOÀN ỨNG</div>
                <div class="ego-pr-detail-title-row">
                    <h1>{{ $detail->code }}</h1>
                    <span class="ego-pr-status ego-pr-status--{{ $detail->statusSlug }}">{{ $detail->statusLabel }}</span>
                </div>
                <div class="ego-pr-detail-meta">
                    <span><i class="bi bi-calendar3"></i> {{ $detail->createdAtText }}</span>
                    <span><i class="bi bi-person"></i> {{ $detail->creatorDisplay }}</span>
                    <span><i class="bi bi-building"></i> {{ $detail->companyHeader }}</span>
                </div>
                {{-- Dải quy trình: ẩn hẳn dưới 900px, đúng `@media(max-width:900px)` của khối style cũ
                     (viết `max-[901px]` vì Tailwind sinh `< N` còn CSS gốc là `<= N`). --}}
                <div class="tw:flex tw:items-center tw:gap-[7px] tw:flex-wrap tw:mt-[10px] tw:max-[901px]:hidden" aria-label="Quy trình hoàn ứng">
                    <x-settlement.step size="sm"><i class="bi bi-receipt"></i>Phiếu tạm ứng</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step size="sm"><i class="bi bi-cash-stack"></i>Chi thực tế</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step size="sm"><i class="bi bi-calculator"></i>Đối soát</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step size="sm"><i class="bi bi-paperclip"></i>Chứng từ</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step size="sm"><i class="bi bi-person-check"></i>Duyệt</x-settlement.step><span class="tw:text-[#94a3b8]">→</span>
                    <x-settlement.step size="sm"><i class="bi bi-check2-circle"></i>Hoàn tất</x-settlement.step>
                </div>
            </div>
        </div>

        <div class="ego-pr-detail-summary">
            {{-- Màu tiền của ô tổng kết: bản cũ phải dùng `!important` vì luật của tệp CSS dùng chung
                 nhắm `<strong>` bên trong thẻ này (0,1,1) và thắng utility đặt thẳng trên ô (0,1,0).
                 Biến thể CON TRỰC TIẾP cũng là (0,1,1) nhưng Tailwind nạp SAU nên thắng, không cần `!`. --}}
            <div class="ego-pr-detail-amount tw:[&>strong]:text-[#087f98]">
                <span>{{ $detail->resultLabel }}</span>
                <strong>{{ $detail->resultAmountText }}</strong>
            </div>
            <div class="ego-pr-detail-actions">
                <a href="{{ route('settlement_requests.index') }}" class="ego-pr-button ego-pr-button--secondary"><i class="bi bi-arrow-left"></i><span>Quay lại</span></a>
            </div>
        </div>
    </header>

    <div class="payx-layout">
        <main class="payx-main">
            <section class="payx-card ego-pr-payment-info ego-pr-reveal">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title"><span class="ego-pay-section-icon"><i class="bi bi-wallet2"></i></span>Thông tin hoàn ứng</h2>
                        <div class="payx-card-desc">Liên kết phiếu tạm ứng nguồn, số tiền đã nhận, chi thực tế và kết quả đối soát.</div>
                    </div>
                </div>
                <div class="payx-card-body">
                    <div class="ego-pr-detail-grid">
                        <div class="ego-pr-detail-field"><span>Phiếu tạm ứng nguồn</span><strong>@if($detail->advanceCode)@if(\Illuminate\Support\Facades\Route::has('advance_requests.show'))<a href="{{ route('advance_requests.show',$detail->advanceRequestId) }}" class="tw:text-[#087f98] tw:no-underline">{{ $detail->advanceCode }}</a>@else {{ $detail->advanceCode }} @endif @else Phiếu cũ chưa liên kết @endif</strong></div>
                        <div class="ego-pr-detail-field"><span>Người tạm ứng</span><strong>{{ $detail->recipientText }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Công ty</span><strong>{{ $detail->companyField }}</strong></div>
                        <div class="ego-pr-detail-field ego-pr-detail-field--money"><span>Số tiền tạm ứng</span><strong>{{ $detail->advanceAmountText }}</strong></div>
                        <div class="ego-pr-detail-field ego-pr-detail-field--money"><span>Số tiền đã chi</span><strong>{{ $detail->actualAmountText }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Kết quả đối soát</span><strong>{{ $detail->settlementTypeText }}</strong></div>
                        @if($detail->settlementType === 'refund')
                            <div class="ego-pr-detail-field ego-pr-detail-field--money"><span>Nhân sự hoàn lại</span><strong>{{ $detail->refundAmountText }}</strong></div>
                        @elseif($detail->settlementType === 'pay_more')
                            <div class="ego-pr-detail-field ego-pr-detail-field--money"><span>Công ty thanh toán thêm</span><strong class="tw:text-[#c2410c]">{{ $detail->payMoreAmountText }}</strong></div>
                        @else
                            <div class="ego-pr-detail-field ego-pr-detail-field--money"><span>Chênh lệch</span><strong class="tw:text-[#15803d]">0 đ</strong></div>
                        @endif
                        <div class="ego-pr-detail-field"><span>Ngày gửi duyệt</span><strong>{{ $detail->submittedAtText }}</strong></div>
                    </div>
                    <div class="ego-pr-detail-notes">
                        <div><span>Nội dung / lý do hoàn ứng</span><p>{!! nl2br(e($detail->reasonText)) !!}</p></div>
                        <div><span>Ghi chú</span><p>{!! nl2br(e($detail->noteText)) !!}</p></div>
                    </div>
                </div>
            </section>

            <section class="payx-card ego-pr-reveal">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title"><span class="ego-pay-section-icon"><i class="bi bi-paperclip"></i></span>Chứng từ đính kèm</h2>
                        <div class="payx-card-desc">Ảnh, PDF, Word hoặc Excel liên quan đến khoản hoàn ứng.</div>
                    </div>
                </div>
                <div class="payx-card-body">
                    @if($detail->attachments)
                        <div class="tw:grid tw:gap-[10px]">
                            @foreach($detail->attachments as $file)
                                <a class="tw:flex tw:items-center tw:gap-3 tw:px-[14px] tw:py-3 tw:border tw:border-solid tw:border-[#e5edf2] tw:rounded-[12px] tw:no-underline tw:text-[#243746] tw:bg-white tw:[transition:.16s] tw:hover:border-[#8ad7e1] tw:hover:shadow-[0_6px_18px_rgba(20,115,135,0.08)] tw:hover:[transform:translateY(-1px)]" href="{{ asset('storage/'.$file->path) }}" target="_blank" rel="noopener">
                                    <span class="tw:w-[42px] tw:h-[42px] tw:rounded-[10px] tw:grid tw:[place-items:center] tw:bg-[#eaf8fb] tw:text-[#087f98] tw:font-black tw:text-[11px]">{{ $file->extLabel }}</span>
                                    <span class="tw:min-w-0 tw:flex-1"><span class="tw:font-extrabold tw:whitespace-nowrap tw:overflow-hidden tw:text-ellipsis">{{ $file->name }}</span><span class="tw:text-[12px] tw:text-[#7b8b98] tw:mt-[2px]">Nhấn để xem / tải chứng từ</span></span>
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <div class="payx-empty">Chưa có chứng từ đính kèm.</div>
                    @endif
                </div>
            </section>

            @if($detail->hasAnyAction)
                <section class="ego-pr-action-center ego-pr-reveal">
                    <div class="ego-pr-action-center-head">
                        <div><span class="ego-pr-eyebrow">XỬ LÝ PHIẾU</span><h3>Thao tác đề nghị hoàn ứng</h3><p>Hành động được hiển thị theo đúng trạng thái và quyền hiện tại.</p></div>
                    </div>
                    <div class="tw:flex tw:gap-2 tw:flex-wrap tw:[&>form]:m-0">
                        @if($detail->canSubmit)
                            <form method="POST" action="{{ route('settlement_requests.submit',$detail->id) }}" x-on:submit="window.confirm(@js('Gửi duyệt phiếu '.$detail->code.'?')) || $event.preventDefault()">@csrf<button type="submit" class="ego-pr-button ego-pr-button--primary"><i class="bi bi-send-check"></i>Gửi duyệt</button></form>
                        @endif
                        @if($detail->canAdminAction)
                            <form method="POST" action="{{ route('settlement_requests.management_approve',$detail->id) }}" x-on:submit="window.confirm(@js('Duyệt phiếu '.$detail->code.'?')) || $event.preventDefault()">@csrf<button type="submit" class="ego-pr-button ego-pr-button--success"><i class="bi bi-check2-circle"></i>Duyệt</button></form>
                            <form method="POST" action="{{ route('settlement_requests.management_reject',$detail->id) }}" x-on:submit="window.confirm(@js('Từ chối phiếu '.$detail->code.'?')) || $event.preventDefault()">@csrf<button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-x-circle"></i>Từ chối</button></form>
                        @endif
                        @if($detail->canAccAction)
                            <form method="POST" action="{{ route('settlement_requests.accounting_approve',$detail->id) }}" x-on:submit="window.confirm(@js('Xác nhận hoàn tất phiếu '.$detail->code.'?')) || $event.preventDefault()">@csrf<button type="submit" class="ego-pr-button ego-pr-button--success"><i class="bi bi-check2-circle"></i>Hoàn tất</button></form>
                            <form method="POST" action="{{ route('settlement_requests.accounting_reject',$detail->id) }}" x-on:submit="window.confirm(@js('Từ chối phiếu '.$detail->code.'?')) || $event.preventDefault()">@csrf<button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-x-circle"></i>Từ chối</button></form>
                        @endif
                        @if($detail->canDelete)
                            <form method="POST" action="{{ route('settlement_requests.destroy',$detail->id) }}" x-on:submit="window.confirm(@js('Xóa phiếu '.$detail->code.'? Hành động không thể hoàn tác.')) || $event.preventDefault()">@csrf @method('DELETE')<button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-trash"></i>Xóa phiếu</button></form>
                        @endif
                    </div>
                </section>
            @endif
        </main>

        <aside class="payx-side">
            <section class="payx-card payx-animate">
                <div class="payx-card-head"><div><h2 class="payx-card-title">Luồng duyệt</h2><div class="payx-card-desc">Theo dõi tiến độ xử lý phiếu.</div></div></div>
                <div class="payx-card-body">
                    <div class="payx-timeline">
                        <div class="payx-step done"><div class="payx-step-num">1</div><div class="payx-step-box"><div class="payx-step-title">Tạo phiếu</div><div class="payx-step-text">{{ $detail->creatorDisplay }}<br>{{ $detail->createdAtText }}</div></div></div>
                        <div class="payx-step {{ $detail->step2State }}"><div class="payx-step-num">2</div><div class="payx-step-box"><div class="payx-step-title">Quản lý tài chính</div><div class="payx-step-text">{{ $detail->step2Text }}@if($detail->step2State === 'done')<br>{{ $detail->adminApprovedAtText }}@endif</div></div></div>
                        <div class="payx-step {{ $detail->step3State }}"><div class="payx-step-num">3</div><div class="payx-step-box"><div class="payx-step-title">Kế toán</div><div class="payx-step-text">{{ $detail->step3Text }}@if($detail->step3State === 'done')<br>{{ $detail->accountingApprovedAtText }}@endif</div></div></div>
                    </div>
                </div>
            </section>

            <section class="payx-card payx-animate">
                <div class="payx-card-head"><div><h2 class="payx-card-title">Tóm tắt hồ sơ</h2><div class="payx-card-desc">Thông tin nhanh của đề nghị hoàn ứng.</div></div></div>
                <div class="payx-card-body">
                    <div class="payx-mini">
                        <div class="payx-mini-row"><span class="payx-label">Trạng thái</span><div class="payx-text">{{ $detail->statusLabel }}</div></div>
                        <div class="payx-mini-row"><span class="payx-label">Phiếu tạm ứng</span><div class="payx-text">{{ $detail->advanceCode ?: 'Chưa liên kết' }}</div></div>
                        <div class="payx-mini-row"><span class="payx-label">Đã tạm ứng</span><div class="payx-text">{{ $detail->advanceAmountText }}</div></div>
                        <div class="payx-mini-row"><span class="payx-label">Số tiền đã chi</span><div class="payx-text">{{ $detail->actualAmountText }}</div></div>
                        <div class="payx-mini-row"><span class="payx-label">Kết quả</span><div class="payx-text">{{ $detail->settlementTypeText }}@if($detail->hasResultAmount): {{ $detail->resultAmountText }} @endif</div></div>
                        <div class="payx-mini-row"><span class="payx-label">Chứng từ</span><div class="payx-text">{{ $detail->attachmentCountText }} file</div></div>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
