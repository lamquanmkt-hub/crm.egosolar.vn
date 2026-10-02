@extends('layouts.app')

@section('content')
<style>
    .create-page{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
        font-family:inherit;
    }

    .page-shell{
        padding:22px;
    }

    .page-head{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        margin-bottom:16px;
    }

    .page-head h4{
        margin:0;
        font-size:22px;
        font-weight:950;
        color:#0f172a;
    }

    .page-head .desc{
        color:#64748b;
        font-size:12px;
        margin-top:2px;
    }

    .btn-pill{
        border-radius:999px;
        font-weight:800;
        font-size:13px;
        padding:8px 16px;
    }

    .create-hero{
        border-radius:24px;
        padding:22px;
        color:#fff;
        background:
            radial-gradient(650px 260px at 90% 0%, rgba(34,211,238,.23), transparent 60%),
            linear-gradient(135deg,#020617,#075985 55%,#0f766e);
        box-shadow:0 18px 44px rgba(15,23,42,.18);
        margin-bottom:16px;
    }

    .create-hero h3{
        font-size:24px;
        font-weight:950;
        margin:0;
        letter-spacing:-.03em;
    }

    .create-hero p{
        margin:5px 0 0;
        opacity:.82;
        font-size:13px;
    }

    .layout{
        display:grid;
        grid-template-columns:1fr 360px;
        gap:16px;
        align-items:start;
    }

    .soft-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
    }

    .card-head{
        padding:14px 16px;
        border-bottom:1px solid #e5eaf1;
        background:#fff;
        font-size:15px;
        font-weight:950;
        color:#0f172a;
    }

    .form-body{
        padding:18px;
    }

    .pp-label{
        color:#334155;
        font-size:12px;
        font-weight:850;
        margin-bottom:5px;
    }

    .pp-input{
        border-radius:12px;
        border-color:#dbe3ee;
        font-size:13px;
    }

    .pp-input:focus{
        border-color:#0ea5e9;
        box-shadow:0 0 0 .2rem rgba(14,165,233,.12);
    }

    .payment-panel{border:1px solid #cfe8f5;border-radius:18px;padding:16px;background:linear-gradient(135deg,#f8fcff,#f0fdfa);box-shadow:inset 0 1px 0 rgba(255,255,255,.7)}
    .payment-panel-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
    .payment-panel-title{font-size:14px;font-weight:950;color:#0f172a;display:flex;align-items:center;gap:8px}
    .payment-panel-title i{width:32px;height:32px;border-radius:11px;background:#dff7ff;color:#087ea4;display:inline-flex;align-items:center;justify-content:center}
    .sync-pill{font-size:11px;font-weight:900;color:#047857;background:#d1fae5;padding:5px 9px;border-radius:999px}
    .payment-help{font-size:11px;color:#64748b;margin-top:8px}

    .upload-zone{
        border:2px dashed #cbd5e1;
        border-radius:18px;
        padding:20px;
        text-align:center;
        background:#f8fafc;
        transition:.15s ease;
    }

    .upload-zone:hover{
        border-color:#38bdf8;
        background:#f0f9ff;
    }

    .upload-icon{
        width:54px;
        height:54px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:18px;
        background:#e0f2fe;
        color:#0369a1;
        font-size:27px;
        margin-bottom:10px;
    }

    .side-tip{
        padding:15px;
        border-bottom:1px solid #e5eaf1;
    }

    .side-tip:last-child{
        border-bottom:0;
    }

    .side-tip .num{
        width:28px;
        height:28px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:10px;
        background:#e0f2fe;
        color:#0369a1;
        font-weight:950;
        margin-right:8px;
    }

    .side-tip .title{
        font-weight:950;
        color:#0f172a;
    }

    .side-tip .desc{
        color:#64748b;
        font-size:12px;
        margin-top:5px;
        line-height:1.55;
    }

    .sticky-actions{
        position:sticky;
        bottom:12px;
        z-index:30;
        margin-top:14px;
        padding:12px;
        background:rgba(244,247,251,.88);
        backdrop-filter:blur(10px);
        display:flex;
        justify-content:flex-end;
        gap:8px;
    }

    @media(max-width:1100px){
        .layout{grid-template-columns:1fr}
    }

    @media(max-width:768px){
        .page-shell{padding:14px}
        .page-head{flex-direction:column;align-items:flex-start}
    }
</style>

<div class="create-page">
    <div class="page-shell">

        <div class="page-head">
            <div>
                <h4>Tạo đề xuất mới</h4>
                <div class="desc">Điền nội dung rõ ràng và đính kèm file/ảnh để sếp duyệt nhanh hơn.</div>
            </div>

            <x-ui.button href="{{ route('de-xuat.index') }}" variant="outline-secondary" size="none" class="btn-pill tw:leading-[1.5]">
                <i class="bi bi-arrow-left"></i> Quay lại
            </x-ui.button>
        </div>

        <div class="create-hero">
            <h3>Phiếu đề xuất nội bộ</h3>
            <p>Hỗ trợ nhiều loại đề xuất: mua sắm, tạm ứng, sửa chữa, nhân sự, quy trình, công việc hoặc đề xuất khác.</p>
        </div>

        @if($errors->any())
            <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[1rem]">
                <b>Chưa gửi được đề xuất.</b>
                <ul class="tw:mb-0 tw:mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <form method="POST" action="{{ route('de-xuat.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="layout">
                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-pencil-square"></i> Thông tin đề xuất
                    </div>

                    <div class="form-body">
                        <div class="tw:mb-4">
                            <x-ui.label class="pp-label">Tiêu đề đề xuất <span class="tw:text-[#dc3545]">*</span></x-ui.label>
                            <x-ui.input type="text"
                                   name="title"
                                   class="pp-input"
                                   value="{{ old('title') }}"
                                   required
                                   placeholder="Ví dụ: Đề xuất mua máy khoan cho đội thi công" />
                        </div>

                        <div class="tw:row tw:g-3">
                            <div class="tw:md:col12-4 tw:mb-4">
                                <x-ui.label class="pp-label">Loại đề xuất</x-ui.label>
                                <x-ui.select name="proposal_type" class="pp-input">
                                    @foreach($types as $key => $label)
                                        <option value="{{ $key }}" {{ old('proposal_type') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="tw:md:col12-4 tw:mb-4">
                                <x-ui.label class="pp-label">Mức ưu tiên</x-ui.label>
                                <x-ui.select name="priority" class="pp-input">
                                    @foreach($priorities as $key => $label)
                                        <option value="{{ $key }}" {{ old('priority', 'normal') === $key ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="tw:md:col12-4 tw:mb-4">
                                <x-ui.label class="pp-label">Ngày cần xử lý</x-ui.label>
                                <x-ui.input type="date"
                                       name="needed_date"
                                       class="pp-input"
                                       value="{{ old('needed_date') }}" />
                            </div>
                        </div>

                        <div class="tw:row tw:g-3">
                            <div class="tw:md:col12-6 tw:mb-4">
                                <x-ui.label class="pp-label">Phòng ban</x-ui.label>
                                <x-ui.input type="text"
                                       name="department_name"
                                       class="pp-input"
                                       value="{{ old('department_name', $departmentName ?? '') }}"
                                       placeholder="Ví dụ: Kỹ thuật, Sales, Marketing" />
                            </div>

                            <div class="tw:md:col12-6 tw:mb-4">
                                <x-ui.label class="pp-label">Số tiền dự kiến</x-ui.label>
                                <x-ui.input type="number"
                                       name="amount"
                                       class="pp-input"
                                       value="{{ old('amount', 0) }}"
                                       min="0"
                                       step="any" />
                            </div>
                        </div>

                        <div class="payment-panel tw:mb-4">
                            <div class="payment-panel-head">
                                <div class="payment-panel-title"><i class="bi bi-bank"></i> Thông tin thanh toán</div>
                                <span class="sync-pill"><i class="bi bi-link-45deg"></i> Đồng bộ sang DNTT</span>
                            </div>
                            <div class="tw:row tw:g-3">
                                <div class="tw:md:col12-6"><x-ui.label class="pp-label">Người / đơn vị nhận tiền</x-ui.label><x-ui.input type="text" name="payment_receiver" class="pp-input" value="{{ old('payment_receiver') }}" placeholder="Tên cá nhân, NCC hoặc đơn vị nhận" /></div>
                                <div class="tw:md:col12-6"><x-ui.label class="pp-label">Ngân hàng</x-ui.label><x-ui.input type="text" name="bank_name" class="pp-input" value="{{ old('bank_name') }}" placeholder="VD: Vietcombank, MB Bank..." /></div>
                                <div class="tw:md:col12-6"><x-ui.label class="pp-label">Số tài khoản</x-ui.label><x-ui.input type="text" name="bank_account" class="pp-input" value="{{ old('bank_account') }}" inputmode="numeric" placeholder="Nhập số tài khoản" /></div>
                                <div class="tw:md:col12-6"><x-ui.label class="pp-label">Chủ tài khoản</x-ui.label><x-ui.input type="text" name="bank_account_name" class="pp-input tw:uppercase" value="{{ old('bank_account_name') }}" placeholder="NGUYEN VAN A" /></div>
                            </div>
                            <div class="payment-help"><i class="bi bi-info-circle"></i> Khi tạo Đề nghị thanh toán, các thông tin này sẽ tự động chuyển sang phiếu và phiếu được gửi duyệt ngay.</div>
                        </div>

                        <div class="tw:mb-4">
                            <x-ui.label class="pp-label">Nội dung đề xuất</x-ui.label>
                            <x-ui.input as="textarea" name="content"
                                      rows="4"
                                      class="pp-input"
                                      placeholder="Bạn cần đề xuất việc gì?">{{ old('content') }}</x-ui.input>
                        </div>

                        <div class="tw:mb-4">
                            <x-ui.label class="pp-label">Lý do đề xuất</x-ui.label>
                            <x-ui.input as="textarea" name="reason"
                                      rows="4"
                                      class="pp-input"
                                      placeholder="Vì sao đề xuất này cần được duyệt?">{{ old('reason') }}</x-ui.input>
                        </div>

                        <div class="tw:mb-4">
                            <x-ui.label class="pp-label">Kết quả kỳ vọng</x-ui.label>
                            <x-ui.input as="textarea" name="expected_result"
                                      rows="3"
                                      class="pp-input"
                                      placeholder="Sau khi được duyệt sẽ mang lại kết quả gì?">{{ old('expected_result') }}</x-ui.input>
                        </div>

                        <div class="upload-zone">
                            <div class="upload-icon">
                                <i class="bi bi-cloud-arrow-up"></i>
                            </div>

                            <div class="tw:font-bold">Đính kèm file / hình ảnh</div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mb-4">
                                Có thể chọn nhiều file. Tối đa 20MB mỗi file.
                            </div>

                            <x-ui.input type="file"
                                   name="attachments[]"
                                   class="pp-input"
                                   multiple />
                        </div>
                    </div>
                </div>

                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-stars"></i> Gợi ý để được duyệt nhanh
                    </div>

                    <div class="side-tip">
                        <div>
                            <span class="num">1</span>
                            <span class="title">Tiêu đề rõ ràng</span>
                        </div>
                        <div class="desc">
                            Nên ghi rõ mục tiêu và đối tượng sử dụng, ví dụ: “Đề xuất mua 2 máy khoan cho đội kỹ thuật”.
                        </div>
                    </div>

                    <div class="side-tip">
                        <div>
                            <span class="num">2</span>
                            <span class="title">Lý do cụ thể</span>
                        </div>
                        <div class="desc">
                            Trình bày vấn đề hiện tại, ảnh hưởng nếu không xử lý và vì sao cần duyệt.
                        </div>
                    </div>

                    <div class="side-tip">
                        <div>
                            <span class="num">3</span>
                            <span class="title">Có kết quả kỳ vọng</span>
                        </div>
                        <div class="desc">
                            Nêu đề xuất giúp tiết kiệm chi phí, tăng hiệu suất, giảm rủi ro hoặc phục vụ công việc thế nào.
                        </div>
                    </div>

                    <div class="side-tip">
                        <div>
                            <span class="num">4</span>
                            <span class="title">Đính kèm bằng chứng</span>
                        </div>
                        <div class="desc">
                            Ảnh hiện trạng, báo giá, hóa đơn, PDF hoặc Excel sẽ giúp người duyệt ra quyết định nhanh hơn.
                        </div>
                    </div>
                </div>
            </div>

            <div class="sticky-actions">
                <x-ui.button href="{{ route('de-xuat.index') }}" variant="outline-secondary" size="none" class="btn-pill tw:leading-[1.5]">
                    Hủy
                </x-ui.button>

                <x-ui.button variant="success" type="submit" size="none" class="px-5 btn-pill tw:leading-[1.5]">
                    <i class="bi bi-send"></i> Gửi đề xuất
                </x-ui.button>
            </div>
        </form>

    </div>
</div>
@endsection