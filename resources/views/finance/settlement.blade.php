@extends('layouts.app')
@section('content')
@php
$money=fn($v)=>number_format((float)($v??0),0,',','.').' đ';
$summary=$summary??[];
@endphp
<style>
.fsp{padding:18px;background:#f5f7fb;min-height:100%}.fsp-hero{padding:28px;border-radius:26px;color:#fff;background:linear-gradient(135deg,#071124,#173a79 58%,#2563eb);box-shadow:0 18px 50px rgba(37,99,235,.16)}.fsp-card{background:#fff;border:1px solid #e5edf7;border-radius:18px;padding:20px;box-shadow:0 8px 24px rgba(15,23,42,.05)}.fsp-kpi{font-size:26px;font-weight:900;color:#0f172a}.fsp-label{font-size:12px;color:#64748b;font-weight:800}.fsp-title{font-size:32px;font-weight:950;margin:8px 0}.fsp-table td,.fsp-table th{padding:12px;border-color:#edf2f7}.fsp-check{display:flex;gap:10px;align-items:flex-start;padding:12px;border-radius:12px;background:#f8fafc;margin-bottom:8px}.fsp-check i{color:#0ea5e9}
@media(max-width:768px){.fsp{padding:10px}.fsp-hero{padding:20px}.fsp-title{font-size:25px}}
</style>
<div class="fsp">
<div class="fsp-hero tw:mb-6">
<div class="tw:flex flex-column flex-lg-row tw:justify-between tw:gap-4">
<div><div class="small tw:font-bold tw:uppercase opacity-75">FINANCE CLOSING CENTER</div><h1 class="fsp-title">QUYẾT TOÁN</h1><div>Tổng hợp số liệu năm {{ $year }} phục vụ rà soát, khóa số liệu và chuẩn bị hồ sơ quyết toán.</div></div>
<form method="GET" class="tw:flex tw:gap-2 tw:items-end"><div><label class="small tw:font-bold">Năm quyết toán</label><x-ui.input type="number" min="2000" max="2100" name="year" value="{{ $year }}" /></div><x-ui.button variant="warning" type="submit" class="tw:font-bold">Áp dụng</x-ui.button><x-ui.button variant="light" type="button" onclick="window.print()" class="tw:font-bold">In</x-ui.button></form>
</div><div class="tw:mt-4 small opacity-75">Kỳ dữ liệu: {{ date('d/m/Y',strtotime($rangeStart)) }} – {{ date('d/m/Y',strtotime($rangeEnd)) }}</div>
</div>
<div class="tw:row tw:g-3 tw:mb-6">
@foreach([['Tổng thu',$summary['total_receipts']??0,'text-success'],['Tổng chi',$summary['total_payments']??0,'text-danger'],['Dòng tiền ròng',$summary['net_cash_flow']??0,($summary['net_cash_flow']??0)>=0?'text-success':'text-danger'],['Công nợ phải thu còn lại',$summary['customer_remain_total']??0,'text-danger']] as [$l,$v,$c])
<div class="tw:md:col12-6 tw:min-[75rem]:col12-3"><div class="fsp-card"><div class="fsp-label">{{ $l }}</div><div class="fsp-kpi {{ $c }}">{{ $money($v) }}</div></div></div>
@endforeach
</div>
<div class="tw:row tw:g-4">
<div class="tw:min-[75rem]:col12-7"><div class="fsp-card tw:h-full"><h4 class="tw:font-bold tw:mb-4">Tổng hợp quyết toán năm {{ $year }}</h4><div class="table-responsive"><table class="table fsp-table"><tbody>
<tr><td>Phiếu thu trong năm</td><td class="tw:text-right tw:font-bold">{{ $summary['receipt_count']??0 }}</td><td class="tw:text-right tw:font-bold tw:text-[#198754]!">{{ $money($summary['total_receipts']??0) }}</td></tr>
<tr><td>Chi phí đã duyệt kế toán</td><td class="tw:text-right tw:font-bold">{{ $summary['payment_count']??0 }}</td><td class="tw:text-right tw:font-bold tw:text-[#dc3545]!">{{ $money($summary['total_payments']??0) }}</td></tr>
<tr><td>Ngân sách năm</td><td class="tw:text-right tw:font-bold">{{ $summary['budget_count']??0 }} hạng mục/tháng</td><td class="tw:text-right tw:font-bold">{{ $money($summary['total_budget']??0) }}</td></tr>
<tr><td>Đề nghị thanh toán còn chờ</td><td class="tw:text-right tw:font-bold">{{ $summary['pending_requests_count']??0 }}</td><td class="tw:text-right tw:font-bold tw:text-[#ffc107]!">{{ $money($summary['pending_requests_amount']??0) }}</td></tr>
</tbody></table></div></div></div>
<div class="tw:min-[75rem]:col12-5"><div class="fsp-card tw:h-full"><h4 class="tw:font-bold tw:mb-4">Quỹ & tài khoản hiện tại</h4>
@if($accounts->count())<div class="table-responsive"><table class="table fsp-table"><thead><tr><th>Tài khoản</th><th>Loại</th><th class="tw:text-right">Số dư</th></tr></thead><tbody>@foreach($accounts as $a)<tr><td><strong>{{ $a->name }}</strong></td><td>{{ $a->type }}</td><td class="tw:text-right tw:font-bold">{{ $money($a->current_balance) }}</td></tr>@endforeach</tbody></table></div>@else<div class="tw:text-[rgba(33,37,41,0.75)]">Chưa có tài khoản/quỹ hoạt động.</div>@endif
</div></div>
</div>
<div class="fsp-card tw:mt-6"><h4 class="tw:font-bold tw:mb-4">Checklist trước khi chốt quyết toán</h4>
@foreach(['Đối chiếu phiếu thu với thanh toán khách hàng','Đối chiếu chi phí đã được kế toán phê duyệt','Xử lý các đề nghị thanh toán còn chờ','Đối chiếu công nợ phải thu với khách hàng','Đối chiếu số dư quỹ ngân hàng và quỹ tiền mặt','Kiểm tra chứng từ/hồ sơ liên quan trước khi khóa số liệu'] as $x)<div class="fsp-check"><i class="bi bi-check2-square"></i><div>{{ $x }}</div></div>@endforeach
</div>
</div>
@endsection
