@extends('layouts.app')

@section('content')
<style> .proposal-show{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
        font-family:inherit;
    }.page-shell{
        padding:22px;
    }.page-head{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        margin-bottom:16px;
    }.page-head h4{
        margin:0;
        font-size:22px;
        font-weight:950;
        color:#0f172a;
    }.page-head .desc{
        color:#64748b;
        font-size:12px;
        margin-top:2px;
    }.btn-pill{
        border-radius:999px;
        font-weight:800;
        font-size:13px;
        padding:8px 16px;
    }.status-hero{
        border-radius:24px;
        padding:22px;
        color:#fff;
        background:
            radial-gradient(650px 260px at 90% 0%, rgba(34,211,238,.23), transparent 60%),
            linear-gradient(135deg,#020617,#075985 55%,#0f766e);
        box-shadow:0 18px 44px rgba(15,23,42,.18);
        margin-bottom:16px;
    }.status-hero h3{
        font-size:24px;
        font-weight:950;
        margin:0;
        letter-spacing:-.03em;
    }.status-hero p{
        margin:5px 0 0;
        opacity:.82;
        font-size:13px;
    }.info-grid{
        display:grid;
        grid-template-columns:repeat(5,1fr);
        gap:12px;
        margin-bottom:16px;
    }.info-box{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:18px;
        padding:13px 15px;
        box-shadow:0 10px 28px rgba(15,23,42,.055);
    }.info-label{
        color:#64748b;
        font-size:12px;
        font-weight:800;
        margin-bottom:5px;
    }.info-value{
        color:#0f172a;
        font-size:15px;
        font-weight:950;
    }.layout{
        display:grid;
        grid-template-columns:1fr 370px;
        gap:16px;
        align-items:start;
    }.soft-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
        margin-bottom:16px;
    }.card-head{
        padding:14px 16px;
        border-bottom:1px solid #e5eaf1;
        background:#fff;
        font-size:15px;
        font-weight:950;
        color:#0f172a;
    }.card-head.dark{
        background:#0f172a;
        color:#fff;
        border-bottom:0;
    }.card-body-custom{
        padding:18px;
    }.soft-badge{
        display:inline-flex;
        align-items:center;
        gap:4px;
        border-radius:999px;
        padding:5px 10px;
        font-size:12px;
        font-weight:900;
        white-space:nowrap;
    }.st-pending{background:#fef3c7;color:#92400e}.st-approved{background:#dcfce7;color:#166534}.st-rejected{background:#fee2e2;color:#991b1b}.pr-low{background:#f1f5f9;color:#475569}.pr-normal{background:#e0f2fe;color:#075985}.pr-high{background:#ffedd5;color:#9a3412}.pr-urgent{background:#fee2e2;color:#991b1b}.content-title{
        font-size:20px;
        font-weight:950;
        color:#0f172a;
        margin-bottom:14px;
    }.section-block{
        margin-bottom:20px;
    }.section-block:last-child{
        margin-bottom:0;
    }.section-label{
        font-size:12px;
        font-weight:950;
        color:#0f172a;
        margin-bottom:6px;
        text-transform:uppercase;
        letter-spacing:.02em;
    }.section-content{
        color:#475569;
        line-height:1.7;
        white-space:pre-line;
        background:#f8fafc;
        border:1px solid #e5eaf1;
        border-radius:14px;
        padding:13px;
    }.file-grid{
        display:grid;
        gap:10px;
    }.file-item{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        padding:12px;
        border:1px solid #e5eaf1;
        border-radius:14px;
        background:#f8fafc;
    }.file-left{
        display:flex;
        align-items:center;
        gap:10px;
        min-width:0;
    }.file-icon{
        width:40px;
        height:40px;
        display:flex;
        align-items:center;
        justify-content:center;
        border-radius:13px;
        background:#e0f2fe;
        color:#0369a1;
        font-size:20px;
        flex:0 0 auto;
    }.file-name{
        font-weight:900;
        color:#0f172a;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        max-width:360px;
    }.file-meta{
        color:#64748b;
        font-size:12px;
    }.psh-label{
        font-size:12px;
        font-weight:850;
        color:#334155;
    }.psh-input{
        border-radius:12px;
        border-color:#dbe3ee;
        font-size:13px;
    }.payment-summary{border:1px solid #cfe8f5;border-radius:18px;background:linear-gradient(135deg,#f8fcff,#f0fdfa);padding:16px}.payment-summary-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px}.payment-summary-title{font-size:14px;font-weight:950;color:#0f172a;display:flex;align-items:center;gap:8px}.payment-summary-title i{width:32px;height:32px;border-radius:11px;background:#dff7ff;color:#087ea4;display:inline-flex;align-items:center;justify-content:center}.payment-data-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.payment-data{padding:10px 12px;background:rgba(255,255,255,.85);border:1px solid #e2e8f0;border-radius:13px}.payment-data .k{font-size:11px;color:#64748b;font-weight:800;margin-bottom:3px}.payment-data .v{font-weight:900;color:#0f172a;word-break:break-word}.action-stack{display:grid;gap:9px}.action-main{border-radius:14px;padding:11px 14px;font-weight:900}.linked-pr{border:1px solid #bae6fd;background:#f0f9ff;border-radius:16px;padding:13px;margin-bottom:10px}.linked-pr-code{font-weight:950;color:#075985;font-size:15px}.linked-pr-status{font-size:11px;font-weight:900;padding:4px 8px;border-radius:999px;background:#dbeafe;color:#1d4ed8}.linked-task{border:1px solid #bbf7d0;background:#f0fdf4;border-radius:16px;padding:13px;margin-bottom:10px}.linked-task-code{font-weight:950;color:#166534;font-size:15px}.linked-task-status{font-size:11px;font-weight:900;padding:4px 8px;border-radius:999px;background:#dcfce7;color:#166534}.assign-workflow{border:1px solid #99f6e4;background:linear-gradient(145deg,#f0fdfa,#f8fafc);border-radius:16px;padding:14px;margin-top:4px}.assign-workflow-title{font-weight:950;color:#0f172a;font-size:14px;margin-bottom:4px;display:flex;align-items:center;gap:7px}.assign-workflow-note{font-size:12px;color:#64748b;line-height:1.55;margin-bottom:12px}.assign-workflow .psh-input{border-radius:12px;border-color:#cbd5e1;font-size:13px}.timeline-box{
        padding:13px;
        border-radius:16px;
        background:#f8fafc;
        border:1px solid #e5eaf1;
    }@media(max-width:1200px){.layout{grid-template-columns:1fr}.info-grid{grid-template-columns:repeat(2,1fr)}
    }@media(max-width:768px){.page-shell{padding:14px}.page-head{flex-direction:column;align-items:flex-start}.info-grid{grid-template-columns:1fr}.file-item{align-items:flex-start;flex-direction:column}
    }
</style>

<div class="proposal-show">
    <div class="page-shell">

        <div class="page-head">
            <div>
                <h4>Chi tiết đề xuất</h4>
                <div class="desc">{{ $proposal->title }}</div>
            </div>

            <x-ui.button href="{{ route('de-xuat.index') }}" variant="outline-secondary" size="none" class="btn-pill tw:leading-[1.5]">
                <i class="bi bi-arrow-left"></i> Quay lại
            </x-ui.button>
        </div>

        @if(session('success'))
            <x-ui.alert variant="success" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[1rem]">
                {{ session('success') }}
            </x-ui.alert>
        @endif

        @if(session('error'))
            <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[1rem]">
                {{ session('error') }}
            </x-ui.alert>
        @endif

        @if($errors->any())
            <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[1rem]">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </x-ui.alert>
        @endif

        <div class="status-hero">
            <h3>{{ $proposal->title }}</h3>
            <p>
                Đề xuất bởi <b>{{ $proposal->employee_name }}</b>
                @if($proposal->department_name)
                    · {{ $proposal->department_name }}
                @endif
            </p>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Người tạo</div>
                <div class="info-value">{{ $proposal->employee_name }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Phòng ban</div>
                <div class="info-value">{{ $proposal->department_name ?: '-' }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Số tiền</div>
                <div class="info-value">{{ number_format($proposal->amount, 0, ',', '.') }} đ</div>
            </div>

            <div class="info-box">
                <div class="info-label">Ngày cần xử lý</div>
                <div class="info-value">{{ $proposal->needed_date ?: '-' }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Trạng thái</div>
                <div class="info-value">
                    @if($proposal->status === 'approved')
                        <span class="soft-badge st-approved">Đã duyệt</span>
                    @elseif($proposal->status === 'rejected')
                        <span class="soft-badge st-rejected">Từ chối</span>
                    @else
                        <span class="soft-badge st-pending">Chờ duyệt</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="layout">
            <div>
                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-file-text"></i> Nội dung đề xuất
                    </div>

                    <div class="card-body-custom">
                        <div class="tw:flex flex-wrap tw:gap-2 tw:mb-4">
                            <span class="soft-badge pr-{{ $proposal->priority ?? 'normal' }}">
                                Ưu tiên: {{ $priorities[$proposal->priority ?? 'normal'] ?? 'Bình thường' }}
                            </span>

                            <span class="soft-badge pr-normal">
                                Loại: {{ $types[$proposal->proposal_type] ?? $proposal->proposal_type }}
                            </span>
                        </div>

                        <div class="content-title">{{ $proposal->title }}</div>

                        <div class="section-block">
                            <div class="section-label">Nội dung</div>
                            <div class="section-content">{{ $proposal->content ?: 'Không có nội dung.' }}</div>
                        </div>

                        <div class="section-block">
                            <div class="section-label">Lý do đề xuất</div>
                            <div class="section-content">{{ $proposal->reason ?: 'Không có lý do.' }}</div>
                        </div>

                        <div class="section-block">
                            <div class="section-label">Kết quả kỳ vọng</div>
                            <div class="section-content">{{ $proposal->expected_result ?: 'Không có.' }}</div>
                        </div>
                    </div>
                </div>

                <div class="soft-card">
                    <div class="card-head"><i class="bi bi-credit-card-2-front"></i> Thông tin thanh toán</div>
                    <div class="card-body-custom">
                        <div class="payment-summary">
                            <div class="payment-summary-head">
                                <div class="payment-summary-title"><i class="bi bi-bank"></i> Tài khoản nhận thanh toán</div>
                                <span class="soft-badge st-approved"><i class="bi bi-link-45deg"></i> Liên kết DNTT</span>
                            </div>
                            <div class="payment-data-grid">
                                <div class="payment-data"><div class="k">Người / đơn vị nhận</div><div class="v">{{ $proposal->payment_receiver ?: $proposal->employee_name }}</div></div>
                                <div class="payment-data"><div class="k">Ngân hàng</div><div class="v">{{ $proposal->bank_name ?: '-' }}</div></div>
                                <div class="payment-data"><div class="k">Số tài khoản</div><div class="v">{{ $proposal->bank_account ?: '-' }}</div></div>
                                <div class="payment-data"><div class="k">Chủ tài khoản</div><div class="v">{{ $proposal->bank_account_name ?: '-' }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-paperclip"></i> File đính kèm
                    </div>

                    <div class="card-body-custom">
                        <div class="file-grid">
                            @forelse($attachments ?? [] as $file)
                                <div class="file-item">
                                    <div class="file-left">
                                        <div class="file-icon">
                                            @if(str_contains($file->file_mime ?? '', 'image'))
                                                <i class="bi bi-image"></i>
                                            @elseif(str_contains($file->file_mime ?? '', 'pdf'))
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            @else
                                                <i class="bi bi-file-earmark"></i>
                                            @endif
                                        </div>

                                        <div style="min-width:0">
                                            <div class="file-name">{{ $file->file_name }}</div>
                                            <div class="file-meta">
                                                {{ $file->file_mime ?: 'file' }}
                                                · {{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB
                                            </div>
                                        </div>
                                    </div>

                                    <x-ui.button href="{{ asset('storage/' . $file->file_path) }}"
                                       target="_blank"
                                       variant="outline-primary" size="none" class="btn-pill tw:leading-[1.5]">
                                        Xem / tải
                                    </x-ui.button>
                                </div>
                            @empty
                                <div class="tw:text-[rgba(33,37,41,0.75)]">
                                    Không có file đính kèm.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="soft-card">
                    <div class="card-head dark">
                        <i class="bi bi-check2-square"></i> Xử lý đề xuất
                    </div>

                    <div class="card-body-custom">
                        <div class="timeline-box tw:mb-4">
                            <div class="tw:mb-2">
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Người duyệt</div>
                                <div class="tw:font-bold">{{ $proposal->approved_name ?: '-' }}</div>
                            </div>

                            <div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">Thời gian xử lý</div>
                                <div class="tw:font-bold">{{ $proposal->approved_at ?: '-' }}</div>
                            </div>
                        </div>

                        @if($proposal->status === 'approved')
                            <x-ui.alert variant="success" class="tw:rounded-[1rem]">
                                <b>Đã duyệt.</b>
                                <div class="tw:mt-1">{{ $proposal->approved_note ?: 'Không có ghi chú.' }}</div>
                            </x-ui.alert>
                        @elseif($proposal->status === 'rejected')
                            <x-ui.alert variant="danger" class="tw:rounded-[1rem]">
                                <b>Đã từ chối.</b>
                                <div class="tw:mt-1">{{ $proposal->reject_reason ?: 'Không có lý do.' }}</div>
                            </x-ui.alert>
                        @endif

                        @php
                            $isOwner = (int)$proposal->user_id === (int)auth()->id();
                            $prStatusLabels = [
                                'draft' => 'Nháp', 'submitted' => 'Đã gửi duyệt',
                                'admin_approved' => 'Giám đốc đã duyệt', 'admin_rejected' => 'Giám đốc từ chối',
                                'accounting_approved' => 'Kế toán đã chi', 'accounting_rejected' => 'Kế toán từ chối',
                            ];
                            $taskStatusLabels = [
                                'new' => 'Mới giao', 'in_progress' => 'Đang làm', 'submitted' => 'Đã nộp',
                                'revision' => 'Cần bổ sung', 'rejected' => 'Trả lại', 'approved' => 'Hoàn tất',
                            ];
                        @endphp

                        <div class="action-stack tw:mb-4">
                            @if($isOwner && $proposal->status === 'pending')
                                <x-ui.button href="{{ route('de-xuat.edit', $proposal->id) }}" variant="outline-primary" size="none" class="action-main tw:text-[16px]/[24px]"><i class="bi bi-pencil-square"></i> Chỉnh sửa đề xuất</x-ui.button>
                            @endif

                            @if($paymentRequest)
                                <div class="linked-pr">
                                    <div class="tw:flex tw:justify-between tw:items-center tw:gap-2 tw:mb-2">
                                        <div>
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Đề nghị thanh toán liên kết</div>
                                            <div class="linked-pr-code">{{ $paymentRequest->code ?? ('#'.$paymentRequest->id) }}</div>
                                        </div>
                                        <span class="linked-pr-status">{{ $prStatusLabels[$paymentRequest->status] ?? $paymentRequest->status }}</span>
                                    </div>
                                    <x-ui.button href="{{ route('payment_requests.show', $paymentRequest->id) }}" variant="info" size="none" class="tw:w-full action-main tw:text-[#ffffff] tw:text-[16px]/[24px]"><i class="bi bi-receipt"></i> Xem đề nghị thanh toán</x-ui.button>
                                </div>
                            @endif

                            @if($proposalTask)
                                <div class="linked-task">
                                    <div class="tw:flex tw:justify-between tw:items-start tw:gap-2 tw:mb-2">
                                        <div>
                                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Công việc đã giao</div>
                                            <div class="linked-task-code">#{{ $proposalTask->id }} · {{ $proposalTask->assignee_name }}</div>
                                            @if($proposalTask->due_at)
                                                <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-1">Hạn: {{ \Illuminate\Support\Carbon::parse($proposalTask->due_at)->format('d/m/Y H:i') }}</div>
                                            @endif
                                        </div>
                                        <span class="linked-task-status">{{ $taskStatusLabels[$proposalTask->status] ?? $proposalTask->status }}</span>
                                    </div>
                                    <x-ui.button href="{{ route('tasks.show', $proposalTask->id) }}" variant="success" size="none" class="tw:w-full action-main tw:text-[16px]/[24px]"><i class="bi bi-clipboard-check"></i> Xem công việc</x-ui.button>
                                </div>
                            @endif

                            @if($canApprove && $proposal->status === 'approved' && (!$paymentRequest || !$proposalTask))
                                <div class="assign-workflow">
                                    <div class="assign-workflow-title"><i class="bi bi-person-check"></i> Hoàn tất sau khi duyệt</div>
                                    <div class="assign-workflow-note">
                                        @if(!$paymentRequest && !$proposalTask)
                                            Tạo ĐNTT, gửi duyệt và giao việc cho người đảm nhận trong cùng một lần xử lý.
                                        @elseif(!$proposalTask)
                                            ĐNTT đã có. Chọn người đảm nhận để giao công việc.
                                        @else
                                            Công việc đã có. Tạo ĐNTT và gửi duyệt để hoàn tất liên kết.
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ route('de-xuat.create-payment-request', $proposal->id) }}" onsubmit="return confirm('Xác nhận tạo ĐNTT/gửi duyệt và giao việc theo thông tin đã chọn?')">
                                        @csrf

                                        @if(!$proposalTask)
                                            <div class="tw:mb-2">
                                                <x-ui.label class="psh-label">Người đảm nhận <span class="tw:text-[#dc3545]">*</span></x-ui.label>
                                                <x-ui.select name="assignee_id" class="psh-input" required>
                                                    <option value="">-- Chọn người đảm nhận --</option>
                                                    @foreach(($assignableUsers ?? collect())->groupBy('department_name') as $departmentName => $departmentUsers)
                                                        <optgroup label="{{ $departmentName }}">
                                                            @foreach($departmentUsers as $person)
                                                                <option value="{{ $person->id }}" @selected((string)old('assignee_id') === (string)$person->id)>
                                                                    {{ $person->name }}{{ $person->email ? ' · '.$person->email : '' }}
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </x-ui.select>
                                            </div>

                                            <div class="tw:mb-4">
                                                <x-ui.label class="psh-label">Hạn hoàn thành <span class="tw:text-[#dc3545]">*</span></x-ui.label>
                                                <x-ui.input type="datetime-local" name="task_due_at" class="psh-input" required value="{{ old('task_due_at', $suggestedTaskDueAt) }}" />
                                                <div class="form-text">Hệ thống gợi ý từ ngày/kết quả kỳ vọng của đề xuất; có thể chỉnh lại trước khi giao.</div>
                                            </div>
                                        @endif

                                        <x-ui.button variant="primary" type="submit" size="none" class="tw:w-full action-main tw:text-[16px]/[24px]">
                                            <i class="bi bi-send-check"></i>
                                            @if(!$paymentRequest && !$proposalTask)
                                                Tạo ĐNTT & giao việc
                                            @elseif(!$proposalTask)
                                                Giao việc cho người đảm nhận
                                            @else
                                                Tạo ĐNTT & gửi duyệt
                                            @endif
                                        </x-ui.button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        @if($canApprove && $proposal->status === 'pending')
                            <form method="POST" action="{{ route('de-xuat.approve', $proposal->id) }}" class="tw:mb-4">
                                @csrf

                                <x-ui.label class="psh-label">Ghi chú duyệt</x-ui.label>
                                <x-ui.input as="textarea" name="approved_note"
                                          class="psh-input tw:mb-2"
                                          rows="3"
                                          placeholder="Ghi chú nếu cần..."></x-ui.input>

                                <x-ui.button variant="success" type="submit" size="none" class="tw:w-full btn-pill tw:leading-[1.5]">
                                    <i class="bi bi-check-circle"></i> Duyệt đề xuất
                                </x-ui.button>
                            </form>

                            <form method="POST" action="{{ route('de-xuat.reject', $proposal->id) }}">
                                @csrf

                                <x-ui.label class="psh-label">Lý do từ chối</x-ui.label>
                                <x-ui.input as="textarea" name="reject_reason"
                                          class="psh-input tw:mb-2"
                                          rows="3"
                                          placeholder="Nhập lý do từ chối..."></x-ui.input>

                                <x-ui.button variant="danger" type="submit" size="none" class="tw:w-full btn-pill tw:leading-[1.5]">
                                    <i class="bi bi-x-circle"></i> Từ chối
                                </x-ui.button>
                            </form>
                        @elseif(!$canApprove && !$isOwner)
                            <x-ui.alert variant="secondary" class="tw:rounded-[1rem] tw:mb-0">Bạn chỉ có quyền xem trạng thái đề xuất.</x-ui.alert>
                        @endif

                        <hr>

                        @php
                            $canDelete = $canApprove || ((int)$proposal->user_id === (int)auth()->id() && $proposal->status === 'pending');
                        @endphp

                        @if($canDelete)
                            <form method="POST"
                                  action="{{ route('de-xuat.destroy', $proposal->id) }}"
                                  onsubmit="return confirm('Bạn chắc chắn muốn xóa đề xuất này?')">
                                @csrf
                                @method('DELETE')
                                <x-ui.button variant="outline-danger" type="submit" size="none" class="tw:w-full btn-pill tw:leading-[1.5]">
                                    <i class="bi bi-trash"></i> Xóa đề xuất
                                </x-ui.button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-info-circle"></i> Thông tin nhanh
                    </div>

                    <div class="card-body-custom">
                        <div class="tw:mb-2">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Ngày tạo</div>
                            <div class="tw:font-bold">{{ $proposal->created_at }}</div>
                        </div>

                        <div class="tw:mb-2">
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Cập nhật cuối</div>
                            <div class="tw:font-bold">{{ $proposal->updated_at }}</div>
                        </div>

                        <div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] small">Mã đề xuất</div>
                            <div class="tw:font-bold">#{{ $proposal->id }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection