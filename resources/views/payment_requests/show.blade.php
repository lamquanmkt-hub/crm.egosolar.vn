{{-- Bỏ truy vấn lặp bảng payment_attachments ở đây: controller đã eager load
     quan hệ $item->attachments (PaymentRequest::with([...,'attachments'])), và
     quan hệ đó nay ghim orderBy('id') đúng như truy vấn cũ. --}}
@extends('layouts.app')

{{-- EGO_DNTT_ENTERPRISE_ASSETS_START --}}
@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/ego-payment-requests-enterprise.css') }}?v={{ filemtime(public_path('css/ego-payment-requests-enterprise.css')) }}"
    >
@endpush

@push('scripts')
    <script
        src="{{ asset('js/ego-payment-requests-enterprise.js') }}?v={{ filemtime(public_path('js/ego-payment-requests-enterprise.js')) }}"
        defer
    ></script>
@endpush
{{-- EGO_DNTT_ENTERPRISE_ASSETS_END --}}


@section('title', 'Chi tiết đề nghị thanh toán')

@section('content')

<div class="payx ego-pr-detail-page" x-data>
    @if(session('success'))
        <div class="payx-alert success payx-animate">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="payx-alert danger payx-animate">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="payx-alert danger payx-animate">{{ $errors->first() }}</div>
    @endif

    
    <header class="ego-pr-detail-header ego-pr-reveal">
        <div class="ego-pr-detail-heading">
            <span class="ego-pr-detail-icon"><i class="bi bi-receipt-cutoff"></i></span>
            <div>
                <div class="ego-pr-eyebrow">HỒ SƠ THANH TOÁN</div>
                <div class="ego-pr-detail-title-row">
                    <h1>{{ $detail->code }}</h1>
                    <span class="ego-pr-status ego-pr-status--{{ $detail->statusSlug }}">{{ $detail->statusLabel }}</span>
                </div>
                <div class="ego-pr-detail-meta">
                    <span><i class="bi bi-calendar3"></i> {{ $detail->createdAtText }}</span>
                    <span><i class="bi bi-person"></i> {{ $detail->creatorDisplay }}</span>
                    <span><i class="bi bi-building"></i> {{ $detail->companyHeader }}</span>
                </div>
            </div>
        </div>

        <div class="ego-pr-detail-summary">
            <div class="ego-pr-detail-amount">
                <span>Số tiền đề nghị</span>
                <strong>{{ $detail->amountText }}</strong>
            </div>
            <div class="ego-pr-detail-actions">
                <a href="{{ route('payment_requests.index') }}" class="ego-pr-button ego-pr-button--secondary"><i class="bi bi-arrow-left"></i><span>Quay lại</span></a>
                @if($detail->canEdit)
                    <a href="{{ route('payment_requests.edit', $detail->id) }}" class="ego-pr-button ego-pr-button--primary"><i class="bi bi-pencil"></i><span>Sửa phiếu</span></a>
                @endif
                @if($detail->showPdf)
                    <a href="{{ route('payment_requests.invoice', $detail->id) }}" class="ego-pr-button ego-pr-button--secondary"><i class="bi bi-filetype-pdf"></i><span>PDF</span></a>
                @endif
            </div>
        </div>
    </header>
<div class="payx-layout">
        <main class="payx-main">
            
            <section class="payx-card ego-pr-payment-info ego-pr-reveal">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title"><span class="ego-pay-section-icon"><i class="bi bi-wallet2"></i></span>Thông tin thanh toán</h2>
                        <div class="payx-card-desc">Thông tin người nhận, thời hạn, loại phiếu và dữ liệu chuyển khoản.</div>
                    </div>
                </div>

                <div class="payx-card-body">
                    <div class="ego-pr-detail-grid">
                        <div class="ego-pr-detail-field"><span>Người nhận</span><strong>{{ $detail->receiverName }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Đơn vị</span><strong>{{ $detail->department }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Công ty</span><strong>{{ $detail->companyField }}</strong></div>
                        <div class="ego-pr-detail-field ego-pr-detail-field--money"><span>Số tiền</span><strong>{{ $detail->amountText }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Hạn thanh toán</span><strong>{{ $detail->paymentDueDateText }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Loại phiếu</span><strong>{{ $detail->docTypeLabel }}</strong></div>
                        {{-- EGO_DNTT_BANK_DETAIL_V3 --}}
                        <div class="ego-pr-detail-field"><span>Ngân hàng</span><strong>{{ $detail->bankName }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Số tài khoản</span><strong>{{ $detail->bankAccount }}</strong></div>
                        <div class="ego-pr-detail-field"><span>Chủ tài khoản</span><strong>{{ $detail->bankAccountName }}</strong></div>
                    </div>

                    <div class="ego-pr-detail-notes">
                        <div><span>Nội dung thanh toán</span><p>{!! nl2br(e($detail->paymentContent)) !!}</p></div>
                        <div><span>Lý do / diễn giải</span><p>{!! nl2br(e($detail->reason)) !!}</p></div>
                    </div>
                </div>
            </section>
<section class="payx-card payx-animate" style="animation-delay:.06s">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title">Chứng từ đính kèm</h2>
                        <div class="payx-card-desc">File hóa đơn, ảnh, PDF, Word hoặc Excel liên quan đến phiếu.</div>
                    </div>
                </div>

                <div class="payx-card-body">
                    @if($detail->attachments)
                        {{-- ⚠️ `ego-pr-preview-ready` của bản cũ là lớp CHẾT: không CSS, không JS nào
                             bắt, chỉ xuất hiện đúng một lần ở chính chỗ gán. Đã bỏ. --}}
                        <div class="payx-files">
                            @foreach($detail->attachments as $att)
                                <div class="payx-file payx-file-preview-card" role="button" tabindex="0"
                                     x-on:click="$dispatch('xem-truoc', { url: @js($att->previewUrl), ten: @js($att->name) })"
                                     x-on:keydown.enter.prevent="$dispatch('xem-truoc', { url: @js($att->previewUrl), ten: @js($att->name) })"
                                     x-on:keydown.space.prevent="$dispatch('xem-truoc', { url: @js($att->previewUrl), ten: @js($att->name) })">
                                    <div class="payx-file-icon">{{ $att->badge }}</div>
                                    <div class="payx-file-main">
                                        <div class="payx-file-name">{{ $att->name }}</div>
                                        <div class="payx-file-meta">{{ $att->mimeText }}</div>
                                    </div>
                                    <div class="payx-file-actions">
                                        <button type="button" class="payx-file-action view">👁 Xem trước</button>
                                        <a class="payx-file-action download" href="{{ $att->downloadUrl }}" target="_blank"
                                           x-on:click.stop>⬇ Tải xuống</a>
                                        <span class="payx-file-size">{{ $att->sizeText }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="payx-empty">Chưa có chứng từ đính kèm.</div>
                    @endif
                </div>
            </section>


            @if($detail->hasAnyAction)
                <section class="ego-pr-action-center ego-pr-reveal" data-action-center>
                    <div class="ego-pr-action-center-head">
                        <div>
                            <span class="ego-pr-eyebrow">XỬ LÝ PHIẾU</span>
                            <h3>{{ $detail->actionTitle }}</h3>
                            <p>Chọn hành động, nhập ghi chú và xác nhận trước khi cập nhật trạng thái.</p>
                        </div>
                    </div>

                    @if($detail->canAdminAction || $detail->canAccAction)
                        <div class="ego-pr-action-tabs" role="tablist">
                            <button type="button" class="is-active" data-action-tab="approve"><i class="bi bi-check2-circle"></i>Duyệt</button>
                            <button type="button" data-action-tab="reject"><i class="bi bi-x-circle"></i>Từ chối</button>
                        </div>

                        <div class="ego-pr-action-panel is-active" data-action-panel="approve">
                            <form method="POST" action="{{ $detail->approveUrl }}" class="ego-pr-detail-action-form ego-pr-confirm-form" data-confirm="{{ $detail->approveConfirm }}">
                                @csrf
                                <label for="egoPrApproveNote">Ghi chú xử lý</label>
                                <textarea id="egoPrApproveNote" name="note" placeholder="Ghi chú không bắt buộc..."></textarea>
                                <button type="submit" class="ego-pr-button ego-pr-button--success"><i class="bi bi-check2-circle"></i>{{ $detail->approveButtonLabel }}</button>
                            </form>
                        </div>

                        <div class="ego-pr-action-panel" data-action-panel="reject" hidden>
                            <form method="POST" action="{{ $detail->rejectUrl }}" class="ego-pr-detail-action-form">
                                @csrf
                                <label for="egoPrRejectNote">Lý do từ chối</label>
                                <textarea id="egoPrRejectNote" name="note" required minlength="2" maxlength="2000" placeholder="Nhập lý do từ chối..."></textarea>
                                <button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-x-circle"></i>Từ chối phiếu</button>
                            </form>
                        </div>
                    @else
                        <div class="ego-pr-inline-actions">
                            @if($detail->canSubmit)
                                <form method="POST" action="{{ route('payment_requests.submit', $detail->id) }}" class="ego-pr-confirm-form" data-confirm="Gửi duyệt phiếu này?">
                                    @csrf
                                    <button type="submit" class="ego-pr-button ego-pr-button--primary"><i class="bi bi-send-check"></i>Gửi duyệt</button>
                                </form>
                            @endif
                            @if($detail->canDelete)
                                <form method="POST" action="{{ route('payment_requests.destroy', $detail->id) }}" class="ego-pr-confirm-form" data-confirm="Xóa phiếu này? Hành động không thể hoàn tác.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-trash"></i>Xóa phiếu</button>
                                </form>
                            @endif
                        </div>
                    @endif
                </section>
            @endif

        </main>

        <aside class="payx-side">
            <section class="payx-card payx-animate" style="animation-delay:.12s">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title">Luồng duyệt</h2>
                        <div class="payx-card-desc">Theo dõi tiến độ xử lý phiếu.</div>
                    </div>
                </div>

                <div class="payx-card-body">
                    <div class="payx-timeline">
                        <div class="payx-step done">
                            <div class="payx-step-num">1</div>
                            <div class="payx-step-box">
                                <div class="payx-step-title">Tạo phiếu</div>
                                <div class="payx-step-text">
                                    {{ $detail->creatorDisplay }}<br>
                                    {{ $detail->createdAtText }}
                                </div>
                            </div>
                        </div>

                        <div class="payx-step {{ $detail->step2State }}">
                            <div class="payx-step-num">2</div>
                            <div class="payx-step-box">
                                <div class="payx-step-title">Quản lý tài chính</div>
                                <div class="payx-step-text">
                                    {{ $detail->step2Text }}@if($detail->step2State === 'done')<br>{{ $detail->adminApprovedAtText }}@endif
                                </div>
                            </div>
                        </div>

                        <div class="payx-step {{ $detail->step3State }}">
                            <div class="payx-step-num">3</div>
                            <div class="payx-step-box">
                                <div class="payx-step-title">Kế toán</div>
                                <div class="payx-step-text">
                                    {{ $detail->step3Text }}@if($detail->step3State === 'done')<br>{{ $detail->accApprovedAtText }}@endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="payx-card payx-animate" style="animation-delay:.15s">
                <div class="payx-card-head">
                    <div>
                        <h2 class="payx-card-title">Ghi chú xử lý</h2>
                        <div class="payx-card-desc">Ghi chú của quản lý tài chính và kế toán.</div>
                    </div>
                </div>

                <div class="payx-card-body">
                    <div class="payx-mini">
                        <div class="payx-mini-row">
                            <span class="payx-label">Ghi chú Admin</span>
                            <div class="payx-text">{!! nl2br(e($detail->adminNote)) !!}</div>
                        </div>

                        <div class="payx-mini-row">
                            <span class="payx-label">Ghi chú Kế toán</span>
                            <div class="payx-text">{!! nl2br(e($detail->accountingNote)) !!}</div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</div>


{{-- Hộp xem trước chứng từ: giữ NGUYÊN hệ class `payx-attachment-preview-*` (có sẵn trong tệp CSS
     dùng chung, kể cả luật `.show` để hiện) và chỉ thay 60 dòng JS thuần bằng Alpine. Các thẻ chứng
     từ ở trên phát sự kiện `xem-truoc` kèm `{ url, ten }`. --}}
<div class="payx-attachment-preview-modal" id="payxAttachmentPreviewModal"
     x-data="{ mo: false, url: 'about:blank', ten: 'Xem trước chứng từ' }"
     x-on:xem-truoc.window="ten = $event.detail.ten || 'Xem trước chứng từ'; url = $event.detail.url; mo = true"
     x-on:keydown.escape.window="mo = false; url = 'about:blank'"
     x-on:click.self="mo = false; url = 'about:blank'"
     x-effect="document.body.style.overflow = mo ? 'hidden' : ''"
     x-bind:class="{ 'show': mo }"
     x-bind:aria-hidden="mo ? 'false' : 'true'"
     aria-hidden="true">
    <div class="payx-attachment-preview-box">
        <div class="payx-attachment-preview-head">
            <div class="payx-attachment-preview-title" x-text="ten">Xem trước chứng từ</div>
            <button type="button" class="payx-attachment-preview-close"
                    x-on:click="mo = false; url = 'about:blank'">Đóng</button>
        </div>
        <iframe class="payx-attachment-preview-frame" x-bind:src="url" src="about:blank"></iframe>
    </div>
</div>


{{-- ⚠️ Include này trước nằm SAU `@endsection`. Nội dung của nó vì thế được in ra TRƯỚC
     `<!DOCTYPE html>` của layout, khiến trình duyệt bỏ qua doctype và render trang ở QUIRKS MODE
     (`document.compatMode = BackCompat`) — đo được với tài khoản khớp điều kiện bên trong partial.
     Đưa vào trong section thì nội dung nằm đúng chỗ và doctype còn nguyên. --}}
@includeIf('payment-requests._buibichthao_actions')
@endsection

