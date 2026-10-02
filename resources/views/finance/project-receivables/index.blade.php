@extends('layouts.app')

@section('title', 'Phải thu công trình')

@section('content')
<div class="tw:pt-6 tw:px-7 tw:pb-[38px] tw:min-h-full tw:text-[#0f172a] tw:bg-[linear-gradient(180deg,#f5fbff_0,#f8fafc_45%,#fff_100%)] tw:max-[701px]:p-4">
    <div class="tw:flex tw:gap-[18px] tw:justify-between tw:items-start tw:mb-[18px] tw:max-[701px]:block">
        <div>
            <h1 class="tw:text-[27px] tw:font-black tw:tracking-[-0.04em] tw:[margin:0_0_6px]">Phải thu công trình</h1>
            <p class="tw:m-0 tw:text-[#64748b]">Mỗi dòng là một Dự án/Công trình. Tiền thu lấy từ đợt thanh toán và phiếu thu của chính công trình, không lấy từ Đơn hàng CRM.</p>
        </div>
        <span class="tw:bg-[#e6f8fb] tw:text-[#087990] tw:border tw:border-solid tw:border-[#bcebf1] tw:rounded-[999px] tw:px-3 tw:py-2 tw:text-[12px] tw:font-extrabold tw:whitespace-nowrap tw:max-[701px]:inline-flex tw:max-[701px]:mt-3"><i class="bi bi-diagram-3"></i> Nguồn: Dự án → Đợt thu → Phiếu thu → Quỹ</span>
    </div>

    {{-- Ba nấc lưới của khối @media cũ: 6 cột → 2 cột ở ≤1100px → 1 cột ở ≤700px.
         Viết `max-[1101px]`/`max-[701px]` vì Tailwind sinh `< N` còn CSS gốc là `<= N`. --}}
    <form method="GET" class="tw:grid tw:grid-cols-[minmax(240px,1.5fr)_190px_150px_150px_165px_auto] tw:gap-[10px] tw:bg-white tw:border tw:border-solid tw:border-[#e6eef7] tw:rounded-[18px] tw:p-[14px] tw:shadow-[0_12px_32px_rgba(15,23,42,0.05)] tw:mb-4 tw:max-[1101px]:grid-cols-2 tw:max-[701px]:grid-cols-1 tw:[&_input]:h-[44px] tw:[&_select]:h-[44px] tw:[&_input]:border tw:[&_select]:border tw:[&_input]:border-solid tw:[&_select]:border-solid tw:[&_input]:border-[#dbe5f0] tw:[&_select]:border-[#dbe5f0] tw:[&_input]:rounded-[12px] tw:[&_select]:rounded-[12px] tw:[&_input]:px-3 tw:[&_select]:px-3 tw:[&_input]:py-0 tw:[&_select]:py-0 tw:[&_input]:bg-white tw:[&_select]:bg-white">
        <input name="keyword" value="{{ $keyword }}" placeholder="Mã CT / tên công trình / chủ đầu tư / SĐT...">
        <select name="company_id">
            <option value="0">Tất cả công ty</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" @selected((int)$companyId === (int)$company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from_date" value="{{ $fromDate }}" title="Từ ngày ký hợp đồng">
        <input type="date" name="to_date" value="{{ $toDate }}" title="Đến ngày ký hợp đồng">
        <select name="status">
            <option value="">Tất cả trạng thái</option>
            <option value="unpaid" @selected($status==='unpaid')>Chưa thu</option>
            <option value="partial" @selected($status==='partial')>Đang thu</option>
            <option value="overdue" @selected($status==='overdue')>Quá hạn</option>
            <option value="paid" @selected($status==='paid')>Đã thu đủ</option>
            <option value="overpaid" @selected($status==='overpaid')>Thu vượt / cần đối chiếu</option>
        </select>
        <button class="tw:h-[44px] tw:[border:0] tw:rounded-[12px] tw:px-4 tw:py-0 tw:font-extrabold tw:inline-flex tw:items-center tw:justify-center tw:gap-[7px] tw:no-underline tw:bg-[#0ea5b7] tw:text-white"><i class="bi bi-funnel"></i> Lọc</button>
    </form>

    <div class="tw:grid tw:grid-cols-[repeat(6,minmax(0,1fr))] tw:gap-3 tw:mb-4 tw:max-[1101px]:grid-cols-2 tw:max-[701px]:grid-cols-1 tw:[&>div]:bg-white tw:[&>div]:border tw:[&>div]:border-solid tw:[&>div]:border-[#e6eef7] tw:[&>div]:rounded-[18px] tw:[&>div]:p-4 tw:[&>div]:shadow-[0_10px_28px_rgba(15,23,42,0.04)] tw:[&_small]:block tw:[&_small]:text-[#64748b] tw:[&_small]:font-bold tw:[&_small]:mb-[6px] tw:[&_strong]:text-[21px] tw:[&_strong]:tracking-[-0.02em]">
        <div><small>Số công trình</small><strong>{{ $kpi->projectCount }}</strong></div>
        <div><small>Giá trị hợp đồng</small><strong>{{ $kpi->contractAmount }}</strong></div>
        <div><small>Đã thu</small><strong class="tw:text-[#059669]">{{ $kpi->receivedAmount }}</strong></div>
        <div><small>Còn phải thu</small><strong class="tw:text-[#dc2626]">{{ $kpi->receivableAmount }}</strong></div>
        <div><small>Đang quá hạn</small><strong class="tw:text-[#b45309]">{{ $kpi->overdueAmount }}</strong></div>
        <div><small>Thu vượt / chênh lệch</small><strong class="tw:text-[#7c3aed]">{{ $kpi->overpaidAmount }}</strong></div>
    </div>

    {{-- ≤1100px: vỏ bảng cho cuộn ngang và bảng có bề rộng tối thiểu, đúng khối @media cũ. --}}
    <div class="tw:bg-white tw:border tw:border-solid tw:border-[#e6eef7] tw:rounded-[20px] tw:overflow-hidden tw:shadow-[0_12px_32px_rgba(15,23,42,0.05)] tw:max-[1101px]:overflow-auto">
        @if($receivableRows)
        <table class="tw:w-full tw:[border-collapse:collapse] tw:max-[1101px]:min-w-[1050px] tw:[&>thead>tr>th]:bg-[#f8fbfe] tw:[&>thead>tr>th]:text-[#607089] tw:[&>thead>tr>th]:text-[11px] tw:[&>thead>tr>th]:uppercase tw:[&>thead>tr>th]:tracking-[0.04em] tw:[&>thead>tr>th]:px-[14px] tw:[&>thead>tr>th]:py-3 tw:[&>thead>tr>th]:text-left tw:[&>thead>tr>th]:[border-bottom:1px_solid_#e8eef5] tw:[&>tbody>tr>td]:p-[14px] tw:[&>tbody>tr>td]:[border-bottom:1px_solid_#eef3f8] tw:[&>tbody>tr>td]:align-top tw:[&>tbody>tr:last-child>td]:[border-bottom:0]">
            <thead><tr><th scope="col">Công trình</th><th scope="col">Chủ đầu tư / khách hàng</th><th scope="col">Giá trị HĐ</th><th scope="col">Đã thu</th><th scope="col">Còn phải thu</th><th scope="col">Đợt kế tiếp</th><th scope="col">Trạng thái</th><th scope="col"></th></tr></thead>
            <tbody>
            @foreach($receivableRows as $row)
                <tr>
                    <td>
                        <strong class="tw:block tw:text-[14px]">{{ $row->codeText }} · {{ $row->name }}</strong>
                        <small class="tw:block tw:text-[#64748b] tw:text-[12px] tw:mt-[3px]">{{ $row->addressText }}</small>
                        @if($row->companyText)<small class="tw:block tw:text-[#64748b] tw:text-[12px] tw:mt-[3px]">{{ $row->companyText }}</small>@endif
                        <small class="tw:block tw:text-[#64748b] tw:text-[12px] tw:mt-[3px]">{{ $row->progressText }}</small>
                    </td>
                    <td><strong>{{ $row->customerName }}</strong><span class="tw:block tw:text-[#64748b] tw:text-[12px] tw:mt-[3px]">{{ $row->phoneText }}</span></td>
                    <td><span class="tw:[font-weight:850] tw:whitespace-nowrap">{{ $row->contractText }}</span></td>
                    <td><span class="tw:[font-weight:850] tw:whitespace-nowrap tw:text-[#059669]">{{ $row->receivedText }}</span><span class="tw:block tw:text-[#64748b] tw:text-[12px] tw:mt-[3px]">{{ $row->paymentsCountText }}</span>@if($row->overpaidText)<span class="tw:block tw:text-[12px] tw:mt-[3px] tw:text-[#7c3aed]">{{ $row->overpaidText }}</span>@endif</td>
                    <td><span class="tw:[font-weight:850] tw:whitespace-nowrap tw:text-[#dc2626]">{{ $row->receivableText }}</span>@if($row->overdueText)<span class="tw:block tw:text-[12px] tw:mt-[3px] tw:text-[#b45309]">{{ $row->overdueText }}</span>@endif</td>
                    <td class="tw:text-[12px] tw:text-[#475569]">
                        @if($row->hasNextTerm)
                            <strong class="tw:block tw:text-[#0f172a] tw:mb-[3px]">{{ $row->nextTermName }}</strong>
                            <span>{{ $row->nextTermAmountText }}</span>
                            <span>{{ $row->nextTermDueText }}</span>
                        @else
                            <span>Không còn đợt chờ thu</span>
                        @endif
                    </td>
                    <td><span class="tw:inline-flex tw:px-[9px] tw:py-[6px] tw:rounded-[999px] tw:text-[11px] tw:[font-weight:850] {{ $row->statusToneClass }}">{{ $row->statusText }}</span></td>
                    <td>
                        @if(\Illuminate\Support\Facades\Route::has('projects-unified.show'))
                            <a class="tw:h-auto tw:[border:0] tw:rounded-[12px] tw:font-extrabold tw:inline-flex tw:items-center tw:justify-center tw:gap-[7px] tw:no-underline tw:bg-[#eef9fb] tw:text-[#087990] tw:px-[10px] tw:py-2 tw:text-[12px]" href="{{ route('projects-unified.show', ['site'=>$row->id, 'workspace'=>'finance']) }}"><i class="bi bi-box-arrow-up-right"></i> Mở tài chính CT</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @else
            <div class="tw:p-[42px] tw:text-center tw:text-[#64748b]"><i class="bi bi-inbox"></i><br>Không có công trình phù hợp bộ lọc.</div>
        @endif
    </div>

    <div class="tw:px-1 tw:py-[14px]">{{ $projects->links() }}</div>
</div>
@endsection
