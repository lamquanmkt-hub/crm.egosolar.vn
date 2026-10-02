{{-- Bỏ truy vấn lặp bảng payment_attachments ở đây: controller đã eager load
     quan hệ $item->attachments (PaymentRequest::with([...,'attachments'])), và
     quan hệ đó nay ghim orderBy('id') đúng như truy vấn cũ. --}}
<!-- EGO_DNTT_EDIT_KEEP_VALUE_V2 -->
@extends('layouts.app')

@section('title', 'Sửa đề nghị thanh toán')

@section('content')

<style id="egoDnttAutoUploadCssV4">
.ego-auto-upload{
    border:1px solid #dceaf0;
    border-radius:16px;
    padding:14px;
    background:linear-gradient(180deg,#fbfeff 0%,#f6fcfe 100%);
}

.ego-auto-upload-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom:12px;
}

.ego-auto-upload-title{
    color:#153747;
    font-size:13px;
    font-weight:900;
}

.ego-auto-upload-desc{
    color:#7d909a;
    font-size:11px;
    font-weight:600;
    margin-top:2px;
}

.ego-auto-upload-limit{
    white-space:nowrap;
    border-radius:999px;
    padding:5px 9px;
    background:#e4f9fc;
    color:#07889a;
    font-size:10.5px;
    font-weight:900;
}

.ego-auto-upload-zone{
    display:flex;
    min-height:116px;
    padding:18px;
    align-items:center;
    justify-content:center;
    flex-direction:column;
    text-align:center;
    border:1.5px dashed #80d7e8;
    border-radius:14px;
    background:#fff;
    cursor:pointer;
    transition:.18s ease;
}

.ego-auto-upload-zone:hover,
.ego-auto-upload-zone.is-dragover{
    border-color:#05a6c0;
    background:#f1fcff;
    transform:translateY(-1px);
}

.ego-auto-upload-zone.is-uploading{
    pointer-events:none;
    opacity:.7;
}

.ego-auto-upload-icon{
    width:42px;
    height:42px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#e5f8fb;
    font-size:20px;
    margin-bottom:8px;
}

.ego-auto-upload-main{
    color:#143544;
    font-size:13px;
    font-weight:900;
}

.ego-auto-upload-sub{
    color:#82959e;
    font-size:11.5px;
    margin-top:3px;
}

.ego-auto-upload-sub b{
    color:#049db4;
}

.ego-auto-upload-types{
    color:#9aa8af;
    font-size:10.5px;
    margin-top:7px;
}

.ego-auto-progress{
    display:grid;
    gap:7px;
    margin-top:10px;
}

.ego-auto-file{
    display:grid;
    grid-template-columns:34px minmax(0,1fr) auto;
    align-items:center;
    gap:9px;
    padding:9px 10px;
    background:#fff;
    border:1px solid #e1ebef;
    border-radius:11px;
}

.ego-auto-file-icon{
    width:32px;
    height:32px;
    border-radius:9px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eaf8fb;
}

.ego-auto-file-info{
    min-width:0;
}

.ego-auto-file-name{
    color:#183744;
    font-size:11.5px;
    font-weight:800;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.ego-auto-file-size{
    color:#95a3aa;
    font-size:10.5px;
    margin-top:2px;
}

.ego-auto-file-status{
    border-radius:999px;
    padding:5px 8px;
    font-size:10.5px;
    font-weight:900;
    white-space:nowrap;
}

.ego-auto-file-status.uploading{
    background:#eaf7ff;
    color:#1779a6;
}

.ego-auto-file-status.done{
    background:#e8faef;
    color:#198754;
}

.ego-auto-file-status.error{
    background:#fff0f1;
    color:#d83b4c;
}

@media(max-width:700px){
    .ego-auto-upload-head{
        flex-direction:column;
    }
}
</style>


<style>
    .pay-edit {
        max-width: 1180px;
        margin: 0 auto;
        padding: 18px 12px 36px;
        color: #071b33;
        font-size: 13px;
    }

    .pay-edit * {
        box-sizing: border-box;
    }

    .pay-edit a {
        text-decoration: none;
    }

    .pay-edit-animate {
        animation: payEditFade .28s ease both;
    }

    @keyframes payEditFade {
        from {
            opacity: 0;
            transform: translateY(8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .pay-edit-hero {
        position: relative;
        overflow: hidden;
        border-radius: 22px;
        padding: 18px 20px;
        margin-bottom: 14px;
        background:
            radial-gradient(circle at top left, rgba(6,182,212,.20), transparent 32%),
            radial-gradient(circle at bottom right, rgba(34,197,94,.14), transparent 32%),
            linear-gradient(135deg, #ffffff 0%, #f6fbff 100%);
        border: 1px solid #d8eef8;
        box-shadow: 0 18px 45px rgba(15,23,42,.075);
    }

    .pay-edit-hero::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #06b6d4, #0ea5e9, #22c55e);
    }

    .pay-edit-hero-inner {
        position: relative;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 14px;
        flex-wrap: wrap;
    }

    .pay-edit-title-wrap {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        min-width: 0;
    }

    .pay-edit-icon {
        width: 42px;
        height: 42px;
        min-width: 42px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, #ecfeff, #dbeafe);
        border: 1px solid #c7eef8;
        color: #0284c7;
        font-size: 18px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,.9);
    }

    .pay-edit-title {
        margin: 0;
        font-size: 24px;
        font-weight: 900;
        line-height: 1.2;
        color: #071b33;
        letter-spacing: -.02em;
    }

    .pay-edit-sub {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 7px;
        font-size: 12px;
        color: #64748b;
        font-weight: 700;
    }

    .pay-edit-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 27px;
        padding: 0 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 850;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .pay-edit-chip.neutral { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
    .pay-edit-chip.info { background: #e0f2fe; color: #0369a1; border-color: #bae6fd; }
    .pay-edit-chip.primary { background: #cffafe; color: #0e7490; border-color: #a5f3fc; }
    .pay-edit-chip.success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
    .pay-edit-chip.danger { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
    .pay-edit-chip.warning { background: #ffedd5; color: #9a3412; border-color: #fed7aa; }

    .pay-edit-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .pay-edit-btn {
        border: 0;
        min-height: 36px;
        padding: 0 13px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 850;
        cursor: pointer;
        transition: .16s ease;
        white-space: nowrap;
    }

    .pay-edit-btn:hover {
        transform: translateY(-1px);
        filter: brightness(1.02);
    }

    .pay-edit-btn.light {
        color: #0f172a;
        background: #fff;
        border: 1px solid #d8e4ef;
    }

    .pay-edit-btn.blue {
        color: #fff;
        background: linear-gradient(135deg, #06b6d4, #0284c7);
        box-shadow: 0 10px 22px rgba(14,165,233,.23);
    }

    .pay-edit-btn.dark {
        color: #fff;
        background: linear-gradient(135deg, #1e293b, #0f172a);
    }

    .pay-edit-card {
        border-radius: 20px;
        background: rgba(255,255,255,.96);
        border: 1px solid #e2eaf3;
        box-shadow: 0 14px 38px rgba(15,23,42,.06);
        overflow: hidden;
        margin-bottom: 14px;
    }

    .pay-edit-card-head {
        padding: 15px 17px 0;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
    }

    .pay-edit-card-title {
        margin: 0;
        font-size: 14px;
        font-weight: 900;
        color: #071b33;
    }

    .pay-edit-card-desc {
        margin-top: 4px;
        color: #64748b;
        font-size: 12px;
        font-weight: 600;
    }

    .pay-edit-card-body {
        padding: 15px 17px 17px;
    }

    .pay-edit-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .pay-edit-grid-3 {
        display: grid;
        grid-template-columns: 1.3fr .7fr 1fr;
        gap: 12px;
    }

    .pay-edit-field {
        position: relative;
    }

    .pay-edit-field.full {
        grid-column: 1 / -1;
    }

    .pay-edit-label {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-bottom: 6px;
        color: #475569;
        font-size: 11.5px;
        font-weight: 850;
        letter-spacing: .015em;
    }

    .pay-edit-required {
        color: #e11d48;
    }

    .pay-edit-control {
        width: 100%;
        min-height: 38px;
        border-radius: 12px !important;
        border: 1px solid #d8e4ef !important;
        background: #fff !important;
        padding: 8px 11px !important;
        color: #0f172a !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        outline: none !important;
        transition: .16s ease !important;
        box-shadow: none !important;
    }

    .pay-edit-control:focus {
        border-color: #38bdf8 !important;
        box-shadow: 0 0 0 4px rgba(14,165,233,.12) !important;
    }

    textarea.pay-edit-control {
        min-height: 115px;
        resize: vertical;
        line-height: 1.65;
    }

    .pay-edit-help {
        margin-top: 6px;
        color: #64748b;
        font-size: 11.5px;
        line-height: 1.45;
        font-weight: 600;
    }

    .pay-edit-file-list {
        display: grid;
        gap: 8px;
    }

    .pay-edit-file {
        display: grid;
        grid-template-columns: 34px 1fr auto;
        gap: 10px;
        align-items: center;
        padding: 10px;
        border-radius: 14px;
        border: 1px solid #e2eaf3;
        background: #f8fbff;
        color: #0f172a;
        transition: .16s ease;
    }

    .pay-edit-file:hover {
        transform: translateY(-1px);
        border-color: #93c5fd;
        color: #0f172a;
    }

    .pay-edit-file-icon {
        width: 34px;
        height: 34px;
        border-radius: 11px;
        display: grid;
        place-items: center;
        background: #e0f2fe;
        color: #0284c7;
        font-size: 11px;
        font-weight: 900;
    }

    .pay-edit-file-name {
        font-size: 12.5px;
        font-weight: 850;
        line-height: 1.35;
        word-break: break-word;
    }

    .pay-edit-file-meta {
        font-size: 11.5px;
        color: #64748b;
        font-weight: 700;
    }

    .pay-edit-empty {
        padding: 12px 14px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        color: #64748b;
        text-align: center;
        font-size: 12px;
        font-weight: 700;
    }

    .pay-edit-footer {
        position: sticky;
        bottom: 0;
        z-index: 10;
        margin-top: 14px;
        padding: 12px;
        border-radius: 18px;
        background: rgba(255,255,255,.86);
        border: 1px solid #dbe7f3;
        box-shadow: 0 -8px 28px rgba(15,23,42,.06);
        backdrop-filter: blur(12px);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .pay-edit-footer-note {
        color: #64748b;
        font-size: 12px;
        font-weight: 700;
    }

    .pay-edit-alert {
        padding: 12px 14px;
        border-radius: 14px;
        margin-bottom: 12px;
        font-size: 12.5px;
        font-weight: 750;
        border: 1px solid transparent;
    }

    .pay-edit-alert.danger {
        color: #991b1b;
        background: #fef2f2;
        border-color: #fecaca;
    }

    .pay-edit-alert.success {
        color: #166534;
        background: #ecfdf5;
        border-color: #bbf7d0;
    }

    @media (max-width: 900px) {
        .pay-edit-grid,
        .pay-edit-grid-3 {
            grid-template-columns: 1fr;
        }

        .pay-edit-hero-inner {
            align-items: flex-start;
        }

        .pay-edit-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 560px) {
        .pay-edit {
            padding-left: 6px;
            padding-right: 6px;
        }

        .pay-edit-title {
            font-size: 20px;
        }

        .pay-edit-title-wrap {
            align-items: flex-start;
        }

        .pay-edit-footer {
            position: static;
        }
    }
</style>

<div class="pay-edit">
    @if(session('success'))
        <div class="pay-edit-alert success pay-edit-animate">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="pay-edit-alert danger pay-edit-animate">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="pay-edit-alert danger pay-edit-animate">
            <strong>Cần kiểm tra lại:</strong> {{ $errors->first() }}
        </div>
    @endif

    <section class="pay-edit-hero pay-edit-animate">
        <div class="pay-edit-hero-inner">
            <div class="pay-edit-title-wrap">
                <div class="pay-edit-icon">✎</div>
                <div>
                    <h1 class="pay-edit-title">Sửa đề nghị thanh toán: {{ $item->code }}</h1>
                    <div class="pay-edit-sub">
                        <span>ID #{{ $item->id }}</span>
                        <span>Ngày tạo: {{ $createdAt }}</span>
                        <span>Trạng thái: {{ $statusLabel }}</span>
                    </div>
                    <div style="margin-top:9px;">
                        <span class="pay-edit-chip {{ $statusTone }}">{{ $statusLabel }}</span>
                    </div>
                </div>
            </div>

            <div class="pay-edit-actions">
                <a href="{{ route('payment_requests.show', $item->id) }}" class="pay-edit-btn light">← Quay lại</a>
                <a href="{{ route('payment_requests.index') }}" class="pay-edit-btn light">Danh sách</a>
            </div>
        </div>
    </section>

    <form method="POST" action="{{ route('payment_requests.update', $item->id) }}">
        @csrf
        @method('PUT')

        <section class="pay-edit-card pay-edit-animate" style="animation-delay:.03s;">
            <div class="pay-edit-card-head">
                <div>
                    <h2 class="pay-edit-card-title">Thông tin cơ bản</h2>
                    <div class="pay-edit-card-desc">Các trường chính của phiếu, đồng bộ với form tạo phiếu.</div>
                </div>
            </div>

            <div class="pay-edit-card-body">
                <div class="pay-edit-grid">
                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Người nhận</label>
                        <input type="text"
                               name="receiver_name"
                               class="pay-edit-control"
                               value="{{ old('receiver_name', $item->receiver_name) }}"
                               placeholder="VD: Nguyễn Văn A">
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Công ty</label>
                        <select name="company" class="pay-edit-control">
                            <option value="">-- Chọn công ty --</option>
                            @foreach($companyOptions as $company)
                                <option value="{{ $company }}" @selected(old('company', $item->company) === $company)>
                                    {{ $company }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Bộ phận / Đơn vị</label>
                        <input type="text"
                               name="department"
                               class="pay-edit-control"
                               value="{{ old('department', $item->department) }}"
                               placeholder="VD: Marketing & Sales">
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Số tiền (VNĐ)</label>
                        <input type="number"
                               name="amount"
                               class="pay-edit-control"
                               value="{{ old('amount', $item->amount) }}"
                               min="0"
                               step="1"
                               placeholder="VD: 2500000">
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Ngày phải thanh toán</label>
                        <input type="date"
                               name="payment_due_date"
                               class="pay-edit-control"
                               value="{{ $dueValue }}">
                    </div>
                    {{-- EGO_DNTT_BANK_EDIT_V3 --}}
                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Ngân hàng</label>
                        <input type="text" name="bank_name" class="pay-edit-control" value="{{ old('bank_name', $item->bank_name) }}" placeholder="VD: Vietcombank, MB Bank...">
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Số tài khoản</label>
                        <input type="text" name="bank_account" class="pay-edit-control" value="{{ old('bank_account', $item->bank_account) }}" inputmode="numeric" placeholder="Nhập số tài khoản">
                    </div>

                    <div class="pay-edit-field">
                        <label class="pay-edit-label">Chủ tài khoản</label>
                        <input type="text" name="bank_account_name" class="pay-edit-control tw:uppercase" value="{{ old('bank_account_name', $item->bank_account_name) }}" placeholder="NGUYEN VAN A">
                    </div>

                    {{-- EGO_FIX_REASON_FIELD_START --}}
                    <!-- EGO_DNTT_EDIT_CONTENT_SPLIT_V1 -->

<div class="tw:row tw:g-3">
    <div class="tw:md:col12-6">
        <x-ui.label class="tw:font-semibold">
            Nội dung thanh toán
        </x-ui.label>

        <textarea
            name="payment_content"
            class="pay-edit-control"
            rows="4"
            placeholder="VD: Thanh toán đợt 1, tạm ứng vật tư..."
        >{{ old('payment_content', $item->payment_content ?? '') }}</textarea>
    </div>

    <div class="tw:md:col12-6">
        <x-ui.label class="tw:font-semibold">
            Lý do / Diễn giải
        </x-ui.label>

        <textarea
            name="reason"
            class="pay-edit-control"
            rows="4"
            placeholder="Mô tả mục đích và nội dung khoản thanh toán..."
        >{{ old('reason', $item->reason ?? '') }}</textarea>
    </div>
</div>



                    {{-- EGO_FIX_REASON_FIELD_END --}}
                </div>
            </div>
        </section>



        
        {{-- EGO_AJAX_ATTACHMENTS_START --}}

        <section class="pay-edit-card pay-edit-animate" style="animation-delay:.09s;" id="ego-pr-attachments-card">
            <div class="pay-edit-card-head">
                <div>
                    <h2 class="pay-edit-card-title">Chứng từ liên có</h2>
                    <div class="pay-edit-card-desc">Thêm chứng từ mới, thay file chứng từ cũ hoặc xóa chứng từ ngay tại màn hình sửa phiếu.</div>
                </div>
            </div>

            <div class="pay-edit-card-body">
                <div id="ego-pr-attachment-message" style="display:none;margin-bottom:10px;padding:10px 12px;border-radius:12px;font-weight:800;"></div>

                @if($egoAttachments)
                    <div class="pay-edit-file-list" style="margin-bottom:14px;">
                        @foreach($egoAttachments as $row)

                            <div class="pay-edit-file" style="align-items:center;">
                                <a href="{{ url($row->downloadPath) }}" target="_blank" style="display:flex;align-items:center;gap:10px;flex:1;min-width:220px;color:inherit;text-decoration:none;">
                                    <div class="pay-edit-file-icon">FILE</div>
                                    <div>
                                        <div class="pay-edit-file-name">{{ $row->fileName }}</div>
                                        <div class="pay-edit-file-meta">{{ $row->fileMeta }}</div>
                                    </div>
                                </a>

                                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
                                    <input type="file"
                                           class="ego-pr-replace-file"
                                           data-attachment-id="{{ $row->attachment->id }}"
                                           style="max-width:210px;font-size:12px;">

                                    <button type="button"
                                            class="pay-edit-btn yellow ego-pr-replace-btn"
                                            data-attachment-id="{{ $row->attachment->id }}"
                                            style="padding:8px 10px;">
                                        Sửa
                                    </button>

                                    <button type="button"
                                            class="pay-edit-btn danger ego-pr-delete-btn"
                                            data-attachment-id="{{ $row->attachment->id }}"
                                            style="padding:8px 10px;">
                                        Xóa
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="pay-edit-empty" style="margin-bottom:14px;">Phiếu này chưa có chứng từ đính kèm.</div>
                @endif

                {{-- EGO_DNTT_AUTO_UPLOAD_V4 --}}
                <div class="ego-auto-upload">
                    <div class="ego-auto-upload-head">
                        <div>
                            <div class="ego-auto-upload-title">
                                Thêm chứng từ
                            </div>
                            <div class="ego-auto-upload-desc">
                                Chọn hoặc kéo thả file. Hệ thống tự tải lên ngay.
                            </div>
                        </div>

                        <span class="ego-auto-upload-limit">
                            20MB / file
                        </span>
                    </div>

                    <input type="file"
                           id="ego-pr-new-attachments"
                           multiple
                           accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"
                           style="display:none;">

                    <label for="ego-pr-new-attachments"
                           id="ego-pr-drop-zone"
                           class="ego-auto-upload-zone">

                        <div class="ego-auto-upload-icon">📎</div>

                        <div class="ego-auto-upload-main">
                            Kéo thả chứng từ vào đây
                        </div>

                        <div class="ego-auto-upload-sub">
                            hoặc <b>bấm để chọn file</b>
                        </div>

                        <div class="ego-auto-upload-types">
                            JPG, PNG, WEBP, PDF, Word, Excel
                        </div>
                    </label>

                    <div id="ego-pr-upload-progress"
                         class="ego-auto-progress"
                         style="display:none;">
                    </div>
                </div>
            </div>
        </section>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var paymentRequestId = '{{ $egoPrId }}';
            var tokenInput = document.querySelector('input[name="_token"]');
            var metaToken = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = tokenInput ? tokenInput.value : (metaToken ? metaToken.getAttribute('content') : '{{ csrf_token() }}');

            var messageBox = document.getElementById('ego-pr-attachment-message');

            function showMessage(type, text) {
                if (!messageBox) {
                    alert(text);
                    return;
                }

                messageBox.style.display = 'block';
                messageBox.textContent = text;

                if (type === 'success') {
                    messageBox.style.background = '#dcfce7';
                    messageBox.style.color = '#166534';
                    messageBox.style.border = '1px solid #86efac';
                } else {
                    messageBox.style.background = '#fee2e2';
                    messageBox.style.color = '#991b1b';
                    messageBox.style.border = '1px solid #fecaca';
                }
            }

            function setBusy(button, text) {
                var old = button.textContent;
                button.disabled = true;
                button.textContent = text;
                return function () {
                    button.disabled = false;
                    button.textContent = old;
                };
            }

            function postForm(url, formData) {
                formData.append('_token', csrfToken);

                return fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                }).then(function (res) {
                    return res.text().then(function (text) {
                        var data = null;

                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            data = null;
                        }

                        if (!res.ok) {
                            var msg = 'Lỗi upload HTTP ' + res.status;

                            if (data && data.message) {
                                msg = data.message;
                            } else if (text) {
                                msg = text.substring(0, 300);
                            }

                            throw new Error(msg);
                        }

                        return data || {ok: true};
                    });
                });
            }

            /* EGO_DNTT_AUTO_UPLOAD_JS_V4 */
            var fileInput = document.getElementById('ego-pr-new-attachments');
            var dropZone = document.getElementById('ego-pr-drop-zone');
            var progressBox = document.getElementById('ego-pr-upload-progress');
            var isUploading = false;

            function egoFormatSize(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) {
                    return (bytes / 1024).toFixed(1) + ' KB';
                }
                return (bytes / 1024 / 1024).toFixed(1) + ' MB';
            }

            function egoEscape(text) {
                var div = document.createElement('div');
                div.textContent = text || '';
                return div.innerHTML;
            }

            function egoShowFiles(files, state) {
                if (!progressBox) return;

                progressBox.style.display = 'grid';

                var html = '';

                Array.prototype.forEach.call(files, function (file) {
                    html +=
                        '<div class="ego-auto-file">' +
                            '<div class="ego-auto-file-icon">📄</div>' +
                            '<div class="ego-auto-file-info">' +
                                '<div class="ego-auto-file-name">' +
                                    egoEscape(file.name) +
                                '</div>' +
                                '<div class="ego-auto-file-size">' +
                                    egoFormatSize(file.size) +
                                '</div>' +
                            '</div>' +
                            '<div class="ego-auto-file-status ' + state + '">' +
                                (state === 'uploading'
                                    ? 'Đang tải...'
                                    : state === 'done'
                                        ? 'Đã tải'
                                        : 'Lỗi') +
                            '</div>' +
                        '</div>';
                });

                progressBox.innerHTML = html;
            }

            function egoUploadFiles(files) {
                files = Array.prototype.slice.call(files || []);

                if (!files.length || isUploading) {
                    return;
                }

                var validFiles = [];

                files.forEach(function (file) {
                    if (file.size > 20 * 1024 * 1024) {
                        showMessage(
                            'error',
                            'File "' + file.name + '" vượt quá 20MB.'
                        );
                        return;
                    }

                    validFiles.push(file);
                });

                if (!validFiles.length) {
                    return;
                }

                isUploading = true;

                if (dropZone) {
                    dropZone.classList.add('is-uploading');
                }

                egoShowFiles(validFiles, 'uploading');

                var fd = new FormData();

                validFiles.forEach(function (file) {
                    fd.append('attachments[]', file);
                });

                postForm(
                    '/payment-requests/' +
                    paymentRequestId +
                    '/attachments-thao',
                    fd
                )
                .then(function (data) {
                    egoShowFiles(validFiles, 'done');

                    showMessage(
                        'success',
                        data && data.message
                            ? data.message
                            : 'Đã tải chứng từ thành công.'
                    );

                    window.setTimeout(function () {
                        window.location.reload();
                    }, 650);
                })
                .catch(function (err) {
                    isUploading = false;

                    if (dropZone) {
                        dropZone.classList.remove('is-uploading');
                    }

                    egoShowFiles(validFiles, 'error');

                    showMessage(
                        'error',
                        err.message || 'Không tải được chứng từ.'
                    );

                    if (fileInput) {
                        fileInput.value = '';
                    }
                });
            }

            if (fileInput) {
                fileInput.addEventListener('change', function () {
                    if (this.files && this.files.length) {
                        egoUploadFiles(this.files);
                    }
                });
            }

            if (dropZone) {
                ['dragenter', 'dragover'].forEach(function (name) {
                    dropZone.addEventListener(name, function (event) {
                        event.preventDefault();
                        event.stopPropagation();

                        if (!isUploading) {
                            dropZone.classList.add('is-dragover');
                        }
                    });
                });

                ['dragleave', 'drop'].forEach(function (name) {
                    dropZone.addEventListener(name, function (event) {
                        event.preventDefault();
                        event.stopPropagation();

                        dropZone.classList.remove('is-dragover');
                    });
                });

                dropZone.addEventListener('drop', function (event) {
                    if (
                        !isUploading &&
                        event.dataTransfer &&
                        event.dataTransfer.files &&
                        event.dataTransfer.files.length
                    ) {
                        egoUploadFiles(event.dataTransfer.files);
                    }
                });
            }

            document.querySelectorAll('.ego-pr-replace-btn').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var id = btn.getAttribute('data-attachment-id');
                    var input = document.querySelector('.ego-pr-replace-file[data-attachment-id="' + id + '"]');

                    if (!input || !input.files || input.files.length === 0) {
                        showMessage('error', 'Bạn chưa chọn file mới để sửa chứng từ.');
                        return false;
                    }

                    var done = setBusy(btn, 'Đang sửa...');
                    var fd = new FormData();
                    fd.append('attachment', input.files[0]);

                    postForm('/payment-requests/' + paymentRequestId + '/attachments-thao/' + id + '/cap-nhat', fd)
                        .then(function () {
                            showMessage('success', 'Đã sửa chứng từ. Đang tải lại...');
                            window.location.reload();
                        })
                        .catch(function (err) {
                            showMessage('error', err.message || 'Không sửa được chứng từ.');
                            done();
                        });

                    return false;
                });
            });

            document.querySelectorAll('.ego-pr-delete-btn').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    var id = btn.getAttribute('data-attachment-id');

                    if (!confirm('Xóa chứng từ này?')) {
                        return false;
                    }

                    var done = setBusy(btn, 'Đang xóa...');
                    var fd = new FormData();
                    fd.append('_method', 'DELETE');

                    postForm('/payment-requests/' + paymentRequestId + '/attachments-thao/' + id, fd)
                        .then(function () {
                            showMessage('success', 'Đã xóa chứng từ. Đang tải lại...');
                            window.location.reload();
                        })
                        .catch(function (err) {
                            showMessage('error', err.message || 'Không xóa được chứng từ.');
                            done();
                        });

                    return false;
                });
            });
        });
        </script>
        {{-- EGO_AJAX_ATTACHMENTS_END --}}

<div class="pay-edit-footer pay-edit-animate" style="animation-delay:.12s;">
            <div class="pay-edit-footer-note">
                Kiểm tra kỹ công ty, số tiền và ngày phải thanh toán trước khi lưu.
            </div>

            <div class="pay-edit-actions">
                <a href="{{ route('payment_requests.show', $item->id) }}" class="pay-edit-btn light">Hủy</a>
                <button type="submit" class="pay-edit-btn blue">Lưu thay đổi</button>
            </div>
        </div>
    </form>
</div>
@endsection
