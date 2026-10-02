@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ego-payment-requests-enterprise.css') }}?v={{ @filemtime(public_path('css/ego-payment-requests-enterprise.css')) ?: time() }}">
<style>
.ego-tu-detail-flow{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-top:10px}.ego-tu-detail-step{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:999px;background:#eff9fb;color:#0e7490;font-size:11px;font-weight:800}.ego-tu-detail-arrow{color:#94a3b8}.ego-tu-action-row{display:flex;gap:8px;flex-wrap:wrap}.ego-tu-action-row form{margin:0}
.ego-tu-bank-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:0;border:1px solid #e8eef2;border-radius:14px;overflow:hidden}.ego-tu-bank-field{padding:14px 16px;background:#fff;border-right:1px solid #edf2f5}.ego-tu-bank-field:last-child{border-right:0}.ego-tu-bank-field span,.ego-tu-settle-stat span{display:block;color:#80909d;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;margin-bottom:5px}.ego-tu-bank-field strong,.ego-tu-settle-stat strong{font-size:14px;color:#213746;overflow-wrap:anywhere}
.ego-tu-settle-card{border:1px solid #ccebf0;background:linear-gradient(145deg,#fff 0%,#f4fcfd 100%)}.ego-tu-settle-callout{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px;border:1px dashed #9edce5;border-radius:14px;background:#f4fcfd}.ego-tu-settle-callout.overdue{border-color:#efb4b4;background:#fff5f5}.ego-tu-settle-callout h3{font-size:16px;margin:0 0 4px;color:#183b4b}.ego-tu-settle-callout.overdue h3{color:#a92f2f}.ego-tu-settle-callout p{margin:0;color:#6e8592;font-size:12px}
.ego-tu-settle-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.ego-tu-settle-stat{padding:13px 14px;border:1px solid #e6eef2;border-radius:12px;background:#fff}.ego-tu-settle-stat--accent strong{color:#0798aa;font-size:17px}.ego-tu-settle-result{margin-top:12px;padding:12px 14px;border-radius:12px;background:#eef9f5;border:1px solid #d4eee5;display:flex;justify-content:space-between;gap:12px;align-items:center}.ego-tu-settle-result b{color:#14785e}
.ego-tu-settle-files{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px}.ego-tu-file{display:inline-flex;align-items:center;gap:6px;padding:7px 9px;border:1px solid #e1ebef;border-radius:9px;background:#fff;color:#22657a;text-decoration:none;font-size:11px;font-weight:700}
.ego-tu-modal-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;margin-bottom:14px}.ego-tu-modal-summary>div{border:1px solid #e5edf1;border-radius:11px;padding:10px 12px;background:#f9fcfd}.ego-tu-modal-summary span{display:block;font-size:10px;text-transform:uppercase;color:#83939f;font-weight:800;margin-bottom:3px}.ego-tu-modal-summary strong{font-size:14px;color:#203a48}.ego-tu-calc{margin-top:10px;border-radius:12px;padding:11px 13px;background:#eef9fb;color:#1d6678;font-size:12px;font-weight:700}.ego-tu-calc strong{font-size:15px;color:#078da0}.ego-tu-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ego-tu-form-grid .wide{grid-column:1/-1}.ego-tu-form-grid label{display:block;font-size:11px;font-weight:800;color:#415966;margin-bottom:5px}.ego-tu-form-grid input,.ego-tu-form-grid textarea{width:100%;border:1px solid #dbe6eb;border-radius:10px;padding:10px 11px;outline:none}.ego-tu-form-grid input:focus,.ego-tu-form-grid textarea:focus{border-color:#3bb7c5;box-shadow:0 0 0 3px rgba(45,177,192,.08)}
.ego-tu-stage-banner{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;margin:0 0 14px;border-radius:14px;border:1px solid #d5e9ee;background:#f2fafc}.ego-tu-stage-banner strong{display:block;color:#194657;font-size:14px}.ego-tu-stage-banner span{display:block;color:#708691;font-size:11px;margin-top:2px}.ego-tu-stage-banner.warning{background:#fff7e9;border-color:#f1ddb7}.ego-tu-stage-banner.warning strong{color:#9a620c}.ego-tu-stage-banner.danger{background:#fff2f2;border-color:#efcccc}.ego-tu-stage-banner.danger strong{color:#a63131}.ego-tu-stage-banner.success{background:#eef9f4;border-color:#d1eadf}.ego-tu-stage-banner.success strong{color:#137154}
.ego-tu-phase-label{font-size:10px;font-weight:900;color:#0b829a;letter-spacing:.06em;text-transform:uppercase;margin:4px 0 8px}.ego-tu-phase-separator{height:1px;background:#e8eef1;margin:12px 0}.ego-tu-current-chip{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:850;background:#eef9fb;color:#087f98;border:1px solid #cfecef}
@media(max-width:900px){.ego-tu-detail-flow{display:none}.ego-tu-bank-grid,.ego-tu-settle-grid,.ego-tu-modal-summary{grid-template-columns:1fr}.ego-tu-bank-field{border-right:0;border-bottom:1px solid #edf2f5}.ego-tu-bank-field:last-child{border-bottom:0}.ego-tu-settle-callout,.ego-tu-stage-banner{align-items:flex-start;flex-direction:column}.ego-tu-form-grid{grid-template-columns:1fr}.ego-tu-form-grid .wide{grid-column:auto}}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/ego-payment-requests-enterprise.js') }}?v={{ @filemtime(public_path('js/ego-payment-requests-enterprise.js')) ?: time() }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.ego-pr-reveal').forEach(el=>{el.style.opacity='1';el.style.transform='none'});
  const actual=document.getElementById('settlementActualAmount');
  const result=document.getElementById('settlementCalcResult');
  const advance={{ (float)($item->amount ?? 0) }};
  const money=n=>new Intl.NumberFormat('vi-VN').format(Math.max(0,Math.round(n)))+' đ';
  const calculate=()=>{
    if(!actual||!result)return;
    const spent=Number(actual.value||0);const diff=spent-advance;
    if(diff<0) result.innerHTML='Nhân viên hoàn lại: <strong>'+money(Math.abs(diff))+'</strong>';
    else if(diff>0) result.innerHTML='Công ty thanh toán thêm: <strong>'+money(diff)+'</strong>';
    else result.innerHTML='Đối soát: <strong>Đã quyết toán đủ</strong>';
  };
  actual?.addEventListener('input',calculate);calculate();
  if(location.hash==='#hoan-ung' && document.getElementById('createSettlementModal') && window.bootstrap){new bootstrap.Modal(document.getElementById('createSettlementModal')).show();}
});
</script>
@endpush

@section('title', 'Chi tiết đề nghị tạm ứng')

@section('content')
@php
    $user = auth()->user();
    $labels = $labels ?? [];
    $approvers = $approvers ?? collect();
    $settlement = $item->settlementRequest;

    $isOwner = (int)($item->created_by ?? 0) === (int)($user->id ?? 0);
    $canSubmit = $isOwner && in_array((string)$item->status, ['draft','admin_rejected','accounting_rejected'], true);
    $canDelete = in_array((string)$item->status, ['draft','admin_rejected','accounting_rejected'], true) && (($canViewAll ?? false) || $isOwner) && !$settlement;
    $canAdminAction = ($canApproveManagement ?? false) && in_array((string)$item->status, ['pending','submitted'], true);
    $canAccAction = ($canApproveAccounting ?? false) && (string)$item->status === 'admin_approved';
    $canCreateSettlement = $isOwner && (string)$item->status === 'accounting_approved' && !$settlement;

    $settlementLabels = [
        'draft'=>'Hoàn ứng nháp',
        'pending'=>'Chờ QL tài chính duyệt hoàn ứng',
        'submitted'=>'Chờ QL tài chính duyệt hoàn ứng',
        'admin_approved'=>'Chờ kế toán đối soát',
        'admin_rejected'=>'QL tài chính từ chối hoàn ứng',
        'accounting_rejected'=>'Kế toán từ chối đối soát',
        'accounting_approved'=>'Đã quyết toán'
    ];
    $settlementStatus = $settlement ? ($settlementLabels[$settlement->status] ?? $settlement->status) : null;
    $canSettlementSubmit = $settlement && $isOwner && in_array((string)$settlement->status,['draft','admin_rejected','accounting_rejected'],true);
    $canSettlementManagement = $settlement && ($canApproveManagement ?? false) && in_array((string)$settlement->status, ['pending','submitted'], true);
    $canSettlementAccounting = $settlement && ($canApproveAccounting ?? false) && (string)$settlement->status === 'admin_approved';
    $canSettlementDelete = $settlement && in_array((string)$settlement->status,['draft','admin_rejected','accounting_rejected'],true) && ($isOwner || ($canViewAll ?? false));

    $overdueDays = 0;
    if ((string)$item->status === 'accounting_approved' && !$settlement && $item->settlement_due_date) {
        $due = \Carbon\Carbon::parse($item->settlement_due_date)->startOfDay();
        if ($due->lt(now()->startOfDay())) $overdueDays = $due->diffInDays(now()->startOfDay());
    }

    $statusLabel = $labels[$item->status] ?? ($item->status ?: '-');
    $stageTone = 'normal';
    $stageHint = '';
    if (in_array((string)$item->status, ['pending','submitted'], true)) { $statusLabel='Chờ QL tài chính duyệt'; $stageHint='Phiếu tạm ứng đang chờ Quản lý tài chính phê duyệt.'; $stageTone='warning'; }
    elseif ((string)$item->status === 'admin_approved') { $statusLabel='Chờ kế toán chi'; $stageHint='Quản lý tài chính đã duyệt. Kế toán cần xác nhận chi tiền.'; $stageTone='warning'; }
    elseif ((string)$item->status === 'admin_rejected') { $statusLabel='QL tài chính từ chối'; $stageHint='Người lập cần kiểm tra và gửi lại phiếu.'; $stageTone='danger'; }
    elseif ((string)$item->status === 'accounting_rejected') { $statusLabel='Kế toán từ chối chi'; $stageHint='Người lập cần kiểm tra và gửi lại phiếu.'; $stageTone='danger'; }
    elseif ((string)$item->status === 'accounting_approved') {
        if (!$settlement) {
            $statusLabel = $overdueDays > 0 ? 'Quá hạn hoàn ứng '.$overdueDays.' ngày' : 'Cần hoàn ứng';
            $stageHint = $overdueDays > 0 ? 'Khoản tạm ứng đã quá hạn. Người nhận cần lập hoàn ứng ngay.' : 'Kế toán đã chi. Người nhận cần lập hoàn ứng trước hạn.';
            $stageTone = $overdueDays > 0 ? 'danger' : 'warning';
        } elseif ((string)$settlement->status === 'draft') { $statusLabel='Hoàn ứng nháp'; $stageHint='Hoàn ứng đã tạo nhưng chưa gửi Quản lý tài chính.'; }
        elseif (in_array((string)$settlement->status, ['pending','submitted'], true)) { $statusLabel='Chờ QL tài chính duyệt hoàn ứng'; $stageHint='Hồ sơ quyết toán đang chờ Quản lý tài chính duyệt.'; $stageTone='warning'; }
        elseif ((string)$settlement->status === 'admin_rejected') { $statusLabel='QL tài chính từ chối hoàn ứng'; $stageHint='Người lập cần bổ sung và gửi lại hoàn ứng.'; $stageTone='danger'; }
        elseif ((string)$settlement->status === 'admin_approved') { $statusLabel='Chờ kế toán đối soát'; $stageHint='Quản lý tài chính đã duyệt hoàn ứng. Kế toán cần đối soát.'; $stageTone='warning'; }
        elseif ((string)$settlement->status === 'accounting_rejected') { $statusLabel='Kế toán từ chối đối soát'; $stageHint='Người lập cần bổ sung hồ sơ và gửi lại.'; $stageTone='danger'; }
        elseif ((string)$settlement->status === 'accounting_approved') { $statusLabel='Đã quyết toán'; $stageHint='Hồ sơ đã hoàn ứng, đối soát và khép kín.'; $stageTone='success'; }
    }

    $createdAt = $item->created_at ? $item->created_at->format('d/m/Y H:i') : '-';
    $submittedAt = $item->submitted_at ? $item->submitted_at->format('d/m/Y H:i') : '-';
    $adminApprovedAt = $item->admin_approved_at ? $item->admin_approved_at->format('d/m/Y H:i') : '-';
    $accountingApprovedAt = $item->accounting_approved_at ? $item->accounting_approved_at->format('d/m/Y H:i') : '-';
    $neededDate = $item->needed_date ? \Carbon\Carbon::parse($item->needed_date)->format('d/m/Y') : '-';
    $settlementDueDate = $item->settlement_due_date ? \Carbon\Carbon::parse($item->settlement_due_date)->format('d/m/Y') : '-';
    $adminApproverName = !empty($item->admin_approved_by) ? ($approvers[$item->admin_approved_by] ?? '#'.$item->admin_approved_by) : '-';
    $accountingApproverName = !empty($item->accounting_approved_by) ? ($approvers[$item->accounting_approved_by] ?? '#'.$item->accounting_approved_by) : '-';
    $settlementAdminName = $settlement && !empty($settlement->admin_approved_by) ? ($approvers[$settlement->admin_approved_by] ?? '#'.$settlement->admin_approved_by) : '-';
    $settlementAccountingName = $settlement && !empty($settlement->accounting_approved_by) ? ($approvers[$settlement->accounting_approved_by] ?? '#'.$settlement->accounting_approved_by) : '-';
    $settlementCreatedAt = $settlement && $settlement->created_at ? $settlement->created_at->format('d/m/Y H:i') : '-';
    $settlementAdminAt = $settlement && $settlement->admin_approved_at ? $settlement->admin_approved_at->format('d/m/Y H:i') : '-';
    $settlementApprovedAt = $settlement && $settlement->accounting_approved_at ? $settlement->accounting_approved_at->format('d/m/Y H:i') : '-';
    $settlementOutcomeLabel = null; $settlementOutcomeAmount = 0;
    if ($settlement) {
        if ($settlement->settlement_type === 'refund') { $settlementOutcomeLabel='Nhân viên hoàn lại'; $settlementOutcomeAmount=(float)$settlement->refund_amount; }
        elseif ($settlement->settlement_type === 'pay_more') { $settlementOutcomeLabel='Công ty thanh toán thêm'; $settlementOutcomeAmount=abs((float)$settlement->difference_amount); }
        else { $settlementOutcomeLabel='Đã quyết toán đủ'; }
    }
@endphp

<div class="payx ego-pr-detail-page">
    @if(session('success'))<div class="payx-alert success payx-animate">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="payx-alert danger payx-animate">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="payx-alert danger payx-animate">{{ $errors->first() }}</div>@endif

    <header class="ego-pr-detail-header ego-pr-reveal">
        <div class="ego-pr-detail-heading">
            <span class="ego-pr-detail-icon"><i class="bi bi-cash-coin"></i></span>
            <div>
                <div class="ego-pr-eyebrow">HỒ SƠ TẠM ỨNG • QUYẾT TOÁN</div>
                <div class="ego-pr-detail-title-row"><h1>{{ $item->code }}</h1><span class="ego-pr-status">{{ $statusLabel }}</span></div>
                <div class="ego-pr-detail-meta"><span><i class="bi bi-calendar3"></i> {{ $createdAt }}</span><span><i class="bi bi-person"></i> {{ $item->creator->name ?? $item->recipient_name ?? ('#'.$item->created_by) }}</span><span><i class="bi bi-building"></i> {{ $item->company ?: 'Chưa có công ty' }}</span></div>
                <div class="ego-tu-detail-flow" aria-label="Quy trình tạm ứng và hoàn ứng"><span class="ego-tu-detail-step">Tạo phiếu</span><span class="ego-tu-detail-arrow">→</span><span class="ego-tu-detail-step">QL tài chính duyệt</span><span class="ego-tu-detail-arrow">→</span><span class="ego-tu-detail-step">Kế toán chi</span><span class="ego-tu-detail-arrow">→</span><span class="ego-tu-detail-step">Hoàn ứng</span><span class="ego-tu-detail-arrow">→</span><span class="ego-tu-detail-step">QL tài chính duyệt</span><span class="ego-tu-detail-arrow">→</span><span class="ego-tu-detail-step">Kế toán đối soát</span><span class="ego-tu-detail-arrow">→</span><span class="ego-tu-detail-step">Hoàn tất</span></div>
            </div>
        </div>
        <div class="ego-pr-detail-summary"><div class="ego-pr-detail-amount"><span>Số tiền tạm ứng</span><strong>{{ number_format((float)($item->amount ?? 0),0,',','.') }} đ</strong></div><div class="ego-pr-detail-actions">@if($canCreateSettlement)<button type="button" class="ego-pr-button ego-pr-button--primary" data-bs-toggle="modal" data-bs-target="#createSettlementModal"><i class="bi bi-receipt-cutoff"></i><span>Tạo hoàn ứng</span></button>@endif<a href="{{ route('advance_requests.index') }}" class="ego-pr-button ego-pr-button--secondary"><i class="bi bi-arrow-left"></i><span>Quay lại</span></a></div></div>
    </header>

    <div class="ego-tu-stage-banner {{ $stageTone }} ego-pr-reveal"><div><strong>{{ $statusLabel }}</strong><span>{{ $stageHint }}</span></div>@if($overdueDays>0)<span class="ego-tu-current-chip"><i class="bi bi-exclamation-triangle"></i>Quá hạn {{ $overdueDays }} ngày</span>@elseif($settlement && (string)$settlement->status==='accounting_approved')<span class="ego-tu-current-chip"><i class="bi bi-check2-circle"></i>Đã đóng hồ sơ</span>@endif</div>

    <div class="payx-layout">
        <main class="payx-main">
            <section class="payx-card ego-pr-payment-info ego-pr-reveal"><div class="payx-card-head"><div><h2 class="payx-card-title"><span class="ego-pay-section-icon"><i class="bi bi-wallet2"></i></span>Thông tin tạm ứng</h2><div class="payx-card-desc">Thông tin người nhận, thời hạn và giá trị khoản tạm ứng.</div></div></div><div class="payx-card-body"><div class="ego-pr-detail-grid"><div class="ego-pr-detail-field"><span>Người nhận tạm ứng</span><strong>{{ $item->recipient_name ?: ($item->creator->name ?? '-') }}</strong></div><div class="ego-pr-detail-field"><span>Công ty</span><strong>{{ $item->company ?: '-' }}</strong></div><div class="ego-pr-detail-field ego-pr-detail-field--money"><span>Số tiền</span><strong>{{ number_format((float)($item->amount ?? 0),0,',','.') }} đ</strong></div><div class="ego-pr-detail-field"><span>Ngày cần tạm ứng</span><strong>{{ $neededDate }}</strong></div><div class="ego-pr-detail-field"><span>Hạn hoàn ứng</span><strong>{{ $settlementDueDate }}</strong></div><div class="ego-pr-detail-field"><span>Ngày gửi duyệt</span><strong>{{ $submittedAt }}</strong></div></div><div class="ego-pr-detail-notes"><div><span>Nội dung / lý do tạm ứng</span><p>{!! nl2br(e($item->reason ?: '-')) !!}</p></div><div><span>Ghi chú</span><p>{!! nl2br(e($item->note ?: '-')) !!}</p></div></div></div></section>

            <section class="payx-card ego-pr-reveal"><div class="payx-card-head"><div><h2 class="payx-card-title"><span class="ego-pay-section-icon"><i class="bi bi-bank"></i></span>Thông tin nhận tiền</h2><div class="payx-card-desc">Thông tin ngân hàng phục vụ việc chi tạm ứng.</div></div></div><div class="payx-card-body"><div class="ego-tu-bank-grid"><div class="ego-tu-bank-field"><span>Ngân hàng</span><strong>{{ $item->bank_name ?: '-' }}</strong></div><div class="ego-tu-bank-field"><span>Số tài khoản</span><strong>{{ $item->bank_account ?: '-' }}</strong></div><div class="ego-tu-bank-field"><span>Chủ tài khoản</span><strong>{{ $item->bank_account_name ?: '-' }}</strong></div></div></div></section>

            @if((string)$item->status === 'accounting_approved')
                <section id="hoan-ung" class="payx-card ego-tu-settle-card ego-pr-reveal"><div class="payx-card-head"><div><h2 class="payx-card-title"><span class="ego-pay-section-icon"><i class="bi bi-receipt-cutoff"></i></span>Hoàn ứng / Quyết toán</h2><div class="payx-card-desc">Hoàn ứng nằm trong chính hồ sơ tạm ứng và đi tiếp qua Quản lý tài chính → Kế toán.</div></div>@if($settlement)<span class="ego-pr-status">{{ $settlementStatus }}</span>@endif</div><div class="payx-card-body">
                    @if(!$settlement)
                        <div class="ego-tu-settle-callout {{ $overdueDays>0?'overdue':'' }}"><div><h3>{{ $overdueDays>0 ? 'Đã quá hạn hoàn ứng '.$overdueDays.' ngày' : 'Khoản tạm ứng đang cần hoàn ứng' }}</h3><p>Tạm ứng {{ number_format((float)$item->amount,0,',','.') }} đ • Hạn {{ $settlementDueDate }}. Nhập chi phí thực tế và chứng từ để gửi Quản lý tài chính duyệt.</p></div>@if($canCreateSettlement)<button type="button" class="ego-pr-button ego-pr-button--primary" data-bs-toggle="modal" data-bs-target="#createSettlementModal"><i class="bi bi-plus-lg"></i>Tạo hoàn ứng</button>@else<span class="ego-pr-status">Chờ người tạm ứng thực hiện</span>@endif</div>
                    @else
                        <div class="ego-tu-settle-grid"><div class="ego-tu-settle-stat"><span>Tạm ứng</span><strong>{{ number_format((float)$settlement->advance_amount,0,',','.') }} đ</strong></div><div class="ego-tu-settle-stat"><span>Đã chi thực tế</span><strong>{{ number_format((float)$settlement->actual_amount,0,',','.') }} đ</strong></div><div class="ego-tu-settle-stat ego-tu-settle-stat--accent"><span>{{ $settlementOutcomeLabel }}</span><strong>{{ $settlementOutcomeAmount > 0 ? number_format($settlementOutcomeAmount,0,',','.') .' đ' : '0 đ' }}</strong></div></div>
                        <div class="ego-tu-settle-result"><span><b>{{ $settlement->code }}</b> • {{ $settlementStatus }}</span><span>{{ $settlementCreatedAt }}</span></div>
                        <div class="ego-pr-detail-notes" style="margin-top:12px"><div><span>Nội dung quyết toán</span><p>{!! nl2br(e($settlement->reason ?: '-')) !!}</p></div><div><span>Ghi chú</span><p>{!! nl2br(e($settlement->note ?: '-')) !!}</p></div></div>
                        @if(count((array)$settlement->attachments))<div class="ego-tu-settle-files">@foreach((array)$settlement->attachments as $i=>$path)<a class="ego-tu-file" href="{{ asset('storage/'.$path) }}" target="_blank"><i class="bi bi-paperclip"></i>Chứng từ {{ $i+1 }}</a>@endforeach</div>@endif
                        @if($canSettlementSubmit || $canSettlementManagement || $canSettlementAccounting || $canSettlementDelete)
                            <div class="ego-tu-action-row" style="margin-top:14px">
                                @if($canSettlementSubmit)<form method="POST" action="{{ route('settlement_requests.submit',$settlement) }}">@csrf<button class="ego-pr-button ego-pr-button--primary"><i class="bi bi-send-check"></i>Gửi duyệt hoàn ứng</button></form>@endif
                                @if($canSettlementManagement)<form method="POST" action="{{ route('settlement_requests.management_approve',$settlement) }}">@csrf<button class="ego-pr-button ego-pr-button--success"><i class="bi bi-person-check"></i>QL tài chính duyệt</button></form><form method="POST" action="{{ route('settlement_requests.management_reject',$settlement) }}">@csrf<button class="ego-pr-button ego-pr-button--danger"><i class="bi bi-x-circle"></i>Từ chối</button></form>@endif
                                @if($canSettlementAccounting)<form method="POST" action="{{ route('settlement_requests.accounting_approve',$settlement) }}" onsubmit="return confirm('Xác nhận đã đối soát và hoàn tất hồ sơ?')">@csrf<button class="ego-pr-button ego-pr-button--success"><i class="bi bi-calculator"></i>Kế toán đối soát & hoàn tất</button></form><form method="POST" action="{{ route('settlement_requests.accounting_reject',$settlement) }}">@csrf<button class="ego-pr-button ego-pr-button--danger"><i class="bi bi-x-circle"></i>Từ chối đối soát</button></form>@endif
                                @if($canSettlementDelete)<form method="POST" action="{{ route('settlement_requests.destroy',$settlement) }}" onsubmit="return confirm('Xóa bản hoàn ứng này để tạo lại?')">@csrf @method('DELETE')<button class="ego-pr-button ego-pr-button--secondary"><i class="bi bi-trash"></i>Xóa & tạo lại</button></form>@endif
                            </div>
                        @endif
                    @endif
                </div></section>
            @endif

            @if(!$settlement && (string)$item->status !== 'accounting_approved')
                <section class="ego-pr-action-center ego-pr-reveal"><div class="ego-pr-action-center-head"><div><span class="ego-pr-eyebrow">XỬ LÝ PHÊ DUYỆT</span><h3>Phê duyệt tạm ứng</h3><p>@if(in_array((string)$item->status,['pending','submitted'],true))Đang chờ Quản lý tài chính xử lý.@elseif((string)$item->status==='admin_approved')Đã được Quản lý tài chính duyệt, đang chờ Kế toán xác nhận chi.@else Các nút thao tác sẽ xuất hiện theo đúng người có quyền và giai đoạn hồ sơ.@endif</p></div></div><div class="ego-tu-action-row">
                    @if($canSubmit)<form method="POST" action="{{ route('advance_requests.submit',$item) }}" onsubmit="return confirm('Gửi duyệt phiếu {{ $item->code }}?')">@csrf<button type="submit" class="ego-pr-button ego-pr-button--primary"><i class="bi bi-send-check"></i>Gửi QL tài chính duyệt</button></form>@endif
                    @if($canAdminAction)<form method="POST" action="{{ route('advance_requests.management_approve',$item) }}">@csrf<button type="submit" class="ego-pr-button ego-pr-button--success"><i class="bi bi-check2-circle"></i>QL tài chính duyệt</button></form><form method="POST" action="{{ route('advance_requests.management_reject',$item) }}">@csrf<button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-x-circle"></i>Từ chối</button></form>@endif
                    @if($canAccAction)<form method="POST" action="{{ route('advance_requests.accounting_approve',$item) }}" onsubmit="return confirm('Xác nhận đã chi phiếu {{ $item->code }}?')">@csrf<button type="submit" class="ego-pr-button ego-pr-button--success"><i class="bi bi-cash-coin"></i>Kế toán xác nhận đã chi</button></form><form method="POST" action="{{ route('advance_requests.accounting_reject',$item) }}">@csrf<button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-x-circle"></i>Từ chối</button></form>@endif
                    @if($canDelete)<form method="POST" action="{{ route('advance_requests.destroy',$item) }}" onsubmit="return confirm('Xóa phiếu {{ $item->code }}?')">@csrf @method('DELETE')<button type="submit" class="ego-pr-button ego-pr-button--danger"><i class="bi bi-trash"></i>Xóa phiếu</button></form>@endif
                    @if(!$canSubmit && !$canAdminAction && !$canAccAction && !$canDelete)
                        <span class="ego-tu-current-chip"><i class="bi bi-hourglass-split"></i>Đang chờ người có quyền xử lý</span>
                    @endif
                </div></section>
            @endif
        </main>

        <aside class="payx-side">
            <section class="payx-card payx-animate"><div class="payx-card-head"><div><h2 class="payx-card-title">Tiến độ hồ sơ</h2><div class="payx-card-desc">Một luồng từ tạm ứng đến quyết toán.</div></div></div><div class="payx-card-body"><div class="payx-timeline">
                <div class="ego-tu-phase-label">TẠM ỨNG</div>
                <div class="payx-step done"><div class="payx-step-num">1</div><div class="payx-step-box"><div class="payx-step-title">Tạo phiếu</div><div class="payx-step-text">{{ $item->creator->name ?? $item->recipient_name ?? ('#'.$item->created_by) }}<br>{{ $createdAt }}</div></div></div>
                <div class="payx-step {{ in_array((string)$item->status,['admin_approved','accounting_approved','accounting_rejected'],true) ? 'done' : (in_array((string)$item->status,['pending','submitted'],true) ? 'active' : '') }}"><div class="payx-step-num">2</div><div class="payx-step-box"><div class="payx-step-title">QL tài chính duyệt</div><div class="payx-step-text">@if(in_array((string)$item->status,['admin_approved','accounting_approved','accounting_rejected'],true)){{ $adminApproverName }}<br>{{ $adminApprovedAt }}@elseif(in_array((string)$item->status,['pending','submitted'],true))Đang chờ duyệt@elseif((string)$item->status==='admin_rejected')Đã từ chối@else Chưa đến bước duyệt @endif</div></div></div>
                <div class="payx-step {{ (string)$item->status==='accounting_approved' ? 'done' : ((string)$item->status==='admin_approved' ? 'active' : '') }}"><div class="payx-step-num">3</div><div class="payx-step-box"><div class="payx-step-title">Kế toán chi</div><div class="payx-step-text">@if((string)$item->status==='accounting_approved'){{ $accountingApproverName }}<br>{{ $accountingApprovedAt }}@elseif((string)$item->status==='admin_approved')Đang chờ kế toán chi@elseif((string)$item->status==='accounting_rejected')Đã từ chối chi@else Chưa đến bước kế toán @endif</div></div></div>
                <div class="ego-tu-phase-separator"></div><div class="ego-tu-phase-label">HOÀN ỨNG / QUYẾT TOÁN</div>
                <div class="payx-step {{ $settlement ? 'done' : ((string)$item->status==='accounting_approved' ? 'active' : '') }}"><div class="payx-step-num">4</div><div class="payx-step-box"><div class="payx-step-title">Nhân sự hoàn ứng</div><div class="payx-step-text">@if($settlement){{ $settlement->code }}<br>{{ $settlementCreatedAt }}@elseif((string)$item->status==='accounting_approved'){{ $overdueDays>0 ? 'Quá hạn '.$overdueDays.' ngày' : 'Đang chờ hoàn ứng' }}@else Chưa đến bước hoàn ứng @endif</div></div></div>
                <div class="payx-step {{ $settlement && in_array((string)$settlement->status,['admin_approved','accounting_approved','accounting_rejected'],true) ? 'done' : ($settlement && in_array((string)$settlement->status,['pending','submitted'],true) ? 'active' : '') }}"><div class="payx-step-num">5</div><div class="payx-step-box"><div class="payx-step-title">QL tài chính duyệt hoàn ứng</div><div class="payx-step-text">@if($settlement && in_array((string)$settlement->status,['admin_approved','accounting_approved','accounting_rejected'],true)){{ $settlementAdminName }}<br>{{ $settlementAdminAt }}@elseif($settlement && in_array((string)$settlement->status,['pending','submitted'],true))Đang chờ duyệt hoàn ứng@elseif($settlement && (string)$settlement->status==='admin_rejected')Đã từ chối hoàn ứng@else Chưa đến bước duyệt @endif</div></div></div>
                <div class="payx-step {{ $settlement && (string)$settlement->status==='accounting_approved' ? 'done' : ($settlement && (string)$settlement->status==='admin_approved' ? 'active' : '') }}"><div class="payx-step-num">6</div><div class="payx-step-box"><div class="payx-step-title">Kế toán đối soát</div><div class="payx-step-text">@if($settlement && (string)$settlement->status==='accounting_approved'){{ $settlementAccountingName }}<br>{{ $settlementApprovedAt }}@elseif($settlement && (string)$settlement->status==='admin_approved')Đang chờ đối soát@elseif($settlement && (string)$settlement->status==='accounting_rejected')Đã từ chối đối soát@else Chưa đến bước kế toán @endif</div></div></div>
                <div class="payx-step {{ $settlement && (string)$settlement->status==='accounting_approved' ? 'done' : '' }}"><div class="payx-step-num">7</div><div class="payx-step-box"><div class="payx-step-title">Hoàn tất</div><div class="payx-step-text">@if($settlement && (string)$settlement->status==='accounting_approved')Đã quyết toán & đóng hồ sơ@else Chưa hoàn tất @endif</div></div></div>
            </div></div></section>

            <section class="payx-card payx-animate"><div class="payx-card-head"><div><h2 class="payx-card-title">Tóm tắt hồ sơ</h2><div class="payx-card-desc">Thông tin nhanh của khoản tạm ứng.</div></div></div><div class="payx-card-body"><div class="payx-mini"><div class="payx-mini-row"><span class="payx-label">Giai đoạn hiện tại</span><div class="payx-text">{{ $statusLabel }}</div></div><div class="payx-mini-row"><span class="payx-label">Tạm ứng</span><div class="payx-text">{{ number_format((float)($item->amount ?? 0),0,',','.') }} đ</div></div>@if($settlement)<div class="payx-mini-row"><span class="payx-label">Đã chi thực tế</span><div class="payx-text">{{ number_format((float)$settlement->actual_amount,0,',','.') }} đ</div></div><div class="payx-mini-row"><span class="payx-label">Đối soát</span><div class="payx-text">{{ $settlementOutcomeLabel }}{{ $settlementOutcomeAmount > 0 ? ': '.number_format($settlementOutcomeAmount,0,',','.').' đ' : '' }}</div></div>@endif<div class="payx-mini-row"><span class="payx-label">Hạn hoàn ứng</span><div class="payx-text">{{ $settlementDueDate }}</div></div></div></div></section>
        </aside>
    </div>
</div>

@if($canCreateSettlement)
<div class="modal fade ego-fin-modal" id="createSettlementModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><form method="POST" action="{{ route('advance_requests.settlement.store',$item) }}" enctype="multipart/form-data">@csrf
    <div class="modal-header"><div><div class="ego-pr-eyebrow">QUYẾT TOÁN TẠM ỨNG</div><h5 class="modal-title tw:mb-0">Hoàn ứng {{ $item->code }}</h5><small class="tw:text-[rgba(33,37,41,0.75)]!">Sau khi gửi: Quản lý tài chính duyệt → Kế toán đối soát → Hoàn tất.</small></div><x-ui.close-button in="modal" type="button" data-bs-dismiss="modal" /></div>
    <div class="modal-body"><div class="ego-tu-modal-summary"><div><span>Người tạm ứng</span><strong>{{ $item->recipient_name }}</strong></div><div><span>Số tiền tạm ứng</span><strong>{{ number_format((float)$item->amount,0,',','.') }} đ</strong></div><div><span>Hạn hoàn ứng</span><strong>{{ $settlementDueDate }}</strong></div></div><div class="ego-tu-form-grid"><div class="wide"><label>Số tiền đã chi thực tế *</label><input id="settlementActualAmount" type="number" min="0" step="1000" name="actual_amount" value="{{ old('actual_amount') }}" required><div id="settlementCalcResult" class="ego-tu-calc"></div></div><div class="wide"><label>Nội dung / lý do quyết toán *</label><textarea rows="3" name="reason" required>{{ old('reason') }}</textarea></div><div class="wide"><label>Chứng từ *</label><input type="file" name="attachments[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx"><small class="tw:text-[rgba(33,37,41,0.75)]!">Tối đa 10 file, 10MB/file.</small></div><div class="wide"><label>Ghi chú</label><textarea rows="2" name="note">{{ old('note') }}</textarea></div></div></div>
    <div class="modal-footer"><button type="button" class="ego-pr-button ego-pr-button--secondary" data-bs-dismiss="modal">Hủy</button><button class="ego-pr-button ego-pr-button--secondary" name="submit_now" value="0"><i class="bi bi-save"></i>Lưu nháp</button><button class="ego-pr-button ego-pr-button--primary" name="submit_now" value="1"><i class="bi bi-send-check"></i>Gửi QL tài chính duyệt</button></div>
</form></div></div></div>
@endif

@if($errors->any() && $canCreateSettlement)<script>document.addEventListener('DOMContentLoaded',()=>{if(window.bootstrap)new bootstrap.Modal(document.getElementById('createSettlementModal')).show()})</script>@endif
@endsection
