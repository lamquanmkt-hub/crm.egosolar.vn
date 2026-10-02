@extends('layouts.app')

@section('content')

<style>
.pay-slip-page{max-width:1500px;margin:0 auto;padding:18px 18px 36px;color:#0f2742}
.pay-slip-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:14px}
.pay-slip-breadcrumb{font-size:12px;font-weight:800;color:#75879a;margin-bottom:7px}
.pay-slip-title{font-size:27px;line-height:1.1;font-weight:900;margin:0;color:#10243e}
.pay-slip-subtitle{font-size:13px;color:#708299;margin-top:7px}
.pay-slip-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.ps-btn{height:38px;border-radius:10px;padding:0 14px;border:1px solid #d7e2ec;background:#fff;color:#123452;font-weight:800;font-size:13px;display:inline-flex;align-items:center;gap:7px;text-decoration:none;cursor:pointer}
.ps-btn:hover{color:#123452;transform:translateY(-1px)}
.ps-btn-primary{background:#0a3c68;border-color:#0a3c68;color:#fff}.ps-btn-primary:hover{color:#fff}
.ps-btn-danger{color:#ba2f3b;border-color:#f0c5ca;background:#fff8f8}
.ps-month{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border:1px solid #d7e3ec;border-radius:999px;background:#fff;font-size:12px;font-weight:800;margin-left:8px}
.ps-hero{background:linear-gradient(135deg,#0b3357 0%,#0a4f77 100%);border-radius:18px;padding:22px;color:#fff;display:grid;grid-template-columns:1.5fr 1fr;gap:22px;margin-bottom:14px;box-shadow:0 12px 35px rgba(16,49,78,.10)}
.ps-employee{display:flex;gap:15px;align-items:center}.ps-avatar{width:60px;height:60px;border-radius:17px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:24px}
.ps-name{font-weight:900;font-size:21px}.ps-meta{font-size:12px;opacity:.82;margin-top:5px;line-height:1.7}
.ps-takehome{text-align:right}.ps-takehome small{font-size:11px;letter-spacing:.06em;text-transform:uppercase;opacity:.75;font-weight:800}.ps-takehome strong{display:block;font-size:31px;margin-top:5px}
.ps-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:14px}.ps-stat{background:#fff;border:1px solid #e1e9f0;border-radius:15px;padding:15px}.ps-stat span{font-size:11px;text-transform:uppercase;font-weight:900;color:#73859a}.ps-stat strong{display:block;font-size:20px;margin-top:6px;color:#102d4a}.ps-stat.green strong{color:#0a8b68}.ps-stat.red strong{color:#d14a54}
.ps-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.ps-card{background:#fff;border:1px solid #e0e9f0;border-radius:17px;overflow:hidden}.ps-card-head{padding:15px 17px;border-bottom:1px solid #edf2f6;display:flex;justify-content:space-between;align-items:center;gap:10px}.ps-card-head h3{margin:0;font-size:14px;font-weight:900}.ps-chip{font-size:10px;font-weight:900;padding:5px 8px;border-radius:999px;background:#eef5fb;color:#31516e}.ps-table{width:100%;border-collapse:collapse}.ps-table td{padding:12px 17px;border-bottom:1px solid #eef2f5;font-size:13px}.ps-table tr:last-child td{border-bottom:0}.ps-table td:first-child{color:#64788c}.ps-table td:last-child{text-align:right;font-weight:850;color:#123350}.ps-value-income{color:#09825f!important}.ps-value-deduction{color:#c64650!important}.ps-input{width:100%;height:36px;border:1px solid #d5e0e9;border-radius:9px;padding:0 10px;text-align:right;font-weight:800;color:#123350}.ps-textarea{width:100%;min-height:85px;border:1px solid #d5e0e9;border-radius:10px;padding:10px 12px;color:#123350}.ps-wide{grid-column:1/-1}.ps-footer{margin-top:14px;padding:13px 16px;background:#f6f9fb;border:1px solid #e1e9f0;border-radius:13px;color:#657a8f;font-size:12px}.ps-savebar{margin-top:14px;background:#fff;border:1px solid #dce7ef;border-radius:15px;padding:12px 15px;display:flex;justify-content:space-between;align-items:center;gap:12px;position:sticky;bottom:12px;box-shadow:0 10px 30px rgba(20,54,80,.12)}
@media(max-width:900px){.ps-hero{grid-template-columns:1fr}.ps-takehome{text-align:left}.ps-summary{grid-template-columns:1fr 1fr}.ps-grid{grid-template-columns:1fr}.pay-slip-head{flex-direction:column}.pay-slip-actions{justify-content:flex-start}.ps-wide{grid-column:auto}}
@media print{.pay-slip-actions,.ps-savebar,.app-sidebar,.sidebar,.navbar{display:none!important}.pay-slip-page{padding:0}.ps-card,.ps-stat,.ps-hero{box-shadow:none;break-inside:avoid}}
</style>

<div class="pay-slip-page">
    <div class="pay-slip-head">
        <div>
            <div class="pay-slip-breadcrumb">Tài chính kế toán &nbsp;›&nbsp; Bảng lương &nbsp;›&nbsp; Phiếu lương</div>
            <h1 class="pay-slip-title">{{ $slipSettings['title'] ?? 'PHIẾU LƯƠNG NHÂN VIÊN' }} <span class="ps-month"><i class="bi bi-calendar3"></i>{{ $month }}</span></h1>
            <div class="pay-slip-subtitle">{{ $slipSettings['subtitle'] ?? '' }}</div>
        </div>
        <div class="pay-slip-actions">
            <a class="ps-btn" href="{{ route('finance.salary', ['month' => $month]) }}"><i class="bi bi-arrow-left"></i>Bảng lương</a>
            @if($isAdmin)
                <a class="ps-btn" href="{{ route('finance.salary.settings') }}"><i class="bi bi-sliders"></i>Cấu hình phiếu lương</a>
            @endif
            @if($canEditSlip && !$editMode)
                <a class="ps-btn ps-btn-primary" href="{{ route('finance.salary.detail', ['user' => $employee->id, 'month' => $month, 'edit' => 1]) }}"><i class="bi bi-pencil-square"></i>Sửa phiếu lương</a>
            @endif
            <button type="button" class="ps-btn" onclick="window.print()"><i class="bi bi-printer"></i>In</button>
        </div>
    </div>

    @if(session('success'))<x-ui.alert variant="success" class="tw:border-0 tw:rounded-[1rem]">{{ session('success') }}</x-ui.alert>@endif
    @if(session('error'))<x-ui.alert variant="danger" class="tw:border-0 tw:rounded-[1rem]">{{ session('error') }}</x-ui.alert>@endif

    <div class="ps-hero">
        <div class="ps-employee">
            <div class="ps-avatar">{{ strtoupper(mb_substr($employee->name ?? 'N',0,1)) }}</div>
            <div>
                <div class="ps-name">{{ $employee->name ?? '-' }}</div>
                <div class="ps-meta">
                    {{ optional($employee->department)->name ?? 'Chưa có phòng ban' }} · {{ optional($employee->position)->name ?? 'Chưa có chức vụ' }}<br>
                    Mã nhân viên: #{{ $employee->id }} · Trạng thái phiếu: {{ $payroll ? 'Đã lưu' : 'Tự tính / chưa chốt' }}
                </div>
            </div>
        </div>
        <div class="ps-takehome">
            <small>Thực nhận kỳ này</small>
            <strong>{{ number_format($calculatedNet,0,',','.') }} đ</strong>
        </div>
    </div>

    <div class="ps-summary">
        <div class="ps-stat"><span>Công thực tế</span><strong>{{ rtrim(rtrim(number_format($workingDays,2,'.',','),'0'),'.') }} / {{ rtrim(rtrim(number_format($standardDays,2,'.',','),'0'),'.') }}</strong></div>
        <div class="ps-stat green"><span>Tổng thu nhập</span><strong>{{ number_format($incomeTotal,0,',','.') }} đ</strong></div>
        <div class="ps-stat red"><span>Tổng khấu trừ</span><strong>{{ number_format($deductionTotal,0,',','.') }} đ</strong></div>
        <div class="ps-stat"><span>KPI kỹ thuật</span><strong>{{ $kpiPercentText }}</strong></div>
    </div>

    <form method="POST" action="{{ route('finance.salary.detail.update', ['user' => $employee->id]) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="month" value="{{ $month }}">

        <div class="ps-grid">
            @if(($slipSettings['show_attendance'] ?? '1') === '1' || $infoRows->isNotEmpty())
            <section class="ps-card">
                <div class="ps-card-head"><h3>Thông tin & chấm công</h3><span class="ps-chip">TỰ ĐỒNG + CẤU HÌNH</span></div>
                <table class="ps-table"><tbody>
                    @foreach($infoRows as $row)
                        <tr>
                            <td>{{ $row->label }}</td>
                            <td>
                                @if($editMode && $canEditSlip && $row->editable)
                                    <input class="ps-input" type="number" step="0.01" min="0" name="components[{{ $row->id }}]" value="{{ $row->amountValue }}">
                                @else
                                    {{ $row->valueText }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if(($slipSettings['show_attendance'] ?? '1') === '1')
                    <tr><td>Tổng thời gian làm việc</td><td>{{ number_format($totalMinutes) }} phút</td></tr>
                    <tr><td>Số lần đi trễ</td><td>{{ $lateCount }} lần</td></tr>
                    @endif
                </tbody></table>
            </section>
            @endif

            @if(($slipSettings['show_kpi_summary'] ?? '1') === '1')
            <section class="ps-card">
                <div class="ps-card-head"><h3>KPI & dữ liệu liên kết</h3><span class="ps-chip">KỸ THUẬT</span></div>
                <table class="ps-table"><tbody>
                    <tr><td>Trạng thái KPI</td><td>{{ $technicalKpi->status ?? 'Chưa có KPI đã duyệt' }}</td></tr>
                    <tr><td>Điểm KPI</td><td>{{ $kpiPercentText }}</td></tr>
                    <tr><td>Lương KPI thực nhận</td><td>{{ number_format((float)($technicalKpi->real_kpi_salary ?? 0),0,',','.') }} đ</td></tr>
                    <tr><td>Tổng thu nhập KPI kỹ thuật</td><td>{{ number_format((float)($technicalKpi->total_income ?? 0),0,',','.') }} đ</td></tr>
                </tbody></table>
            </section>
            @endif

            <section class="ps-card">
                <div class="ps-card-head"><h3>Các khoản thu nhập</h3><span class="ps-chip">+ {{ number_format($incomeTotal,0,',','.') }} đ</span></div>
                <table class="ps-table"><tbody>
                    @forelse($incomeRows as $row)
                        <tr>
                            <td>{{ $row->label }}</td>
                            <td class="ps-value-income">
                                @if($editMode && $canEditSlip && $row->editable)
                                    <input class="ps-input" type="number" step="1" min="0" name="components[{{ $row->id }}]" value="{{ $row->amountValue }}">
                                @else
                                    {{ $row->valueText }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2">Chưa có thành phần thu nhập được bật.</td></tr>
                    @endforelse
                </tbody></table>
            </section>

            <section class="ps-card">
                <div class="ps-card-head"><h3>Các khoản khấu trừ</h3><span class="ps-chip">- {{ number_format($deductionTotal,0,',','.') }} đ</span></div>
                <table class="ps-table"><tbody>
                    @forelse($deductionRows as $row)
                        <tr>
                            <td>{{ $row->label }}</td>
                            <td class="ps-value-deduction">
                                @if($editMode && $canEditSlip && $row->editable)
                                    <input class="ps-input" type="number" step="1" min="0" name="components[{{ $row->id }}]" value="{{ $row->amountValue }}">
                                @else
                                    {{ $row->valueText }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="2">Chưa có thành phần khấu trừ được bật.</td></tr>
                    @endforelse
                </tbody></table>
            </section>

            @if(($slipSettings['show_note'] ?? '1') === '1')
            <section class="ps-card ps-wide">
                <div class="ps-card-head"><h3>Ghi chú phiếu lương</h3><span class="ps-chip">NỘI BỘ</span></div>
                <div style="padding:15px 17px">
                    @if($editMode && $canEditSlip)
                        <textarea class="ps-textarea" name="note_text" placeholder="Ghi chú đối chiếu lương, điều chỉnh, xác nhận...">{{ $noteText }}</textarea>
                    @else
                        <div style="font-size:13px;color:#5f7488">{{ $noteText ?: 'Không có ghi chú.' }}</div>
                    @endif
                </div>
            </section>
            @endif
        </div>

        <div class="ps-footer">{{ $slipSettings['footer_note'] ?? '' }}</div>

        @if($editMode && $canEditSlip)
        <div class="ps-savebar">
            <div><strong>Đang chỉnh sửa phiếu lương</strong><div style="font-size:12px;color:#74879a">Các dòng tự động sẽ không cho nhập tay. Quản trị viên có thể thay đổi quyền sửa trong Cấu hình phiếu lương.</div></div>
            <div class="pay-slip-actions">
                <a class="ps-btn" href="{{ route('finance.salary.detail', ['user'=>$employee->id,'month'=>$month]) }}">Hủy</a>
                <button class="ps-btn ps-btn-primary" type="submit"><i class="bi bi-check2-circle"></i>Lưu phiếu lương</button>
            </div>
        </div>
        @endif
    </form>
</div>
@endsection
