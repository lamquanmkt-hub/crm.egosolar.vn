@extends('layouts.app')

@section('title', 'KPI công trình · EGO Solar')

@section('content')
{{-- Ba nấc của hai khối @media cũ (1000px và 600px) viết `max-[1001px]` / `max-[601px]`:
     Tailwind sinh `not all and (min-width:N)` = `< N`, còn CSS gốc là `<= N`. --}}
<div class="tw:min-h-screen tw:bg-[#f4f7fb] tw:p-5 tw:max-[601px]:p-3">
<div class="tw:max-w-[1500px] tw:[margin:auto]">
    <header class="tw:flex tw:justify-between tw:gap-4 tw:items-start tw:mb-[14px] tw:max-[601px]:flex-col">
        <div>
            <div class="tw:text-[10px] tw:[font-weight:850] tw:text-[#718298] tw:mb-[6px]">Kỹ thuật › KPIs › Công trình</div>
            <h1 class="tw:text-[25px] tw:[font-weight:950] tw:m-0 tw:text-[#10253d] tw:tracking-[-0.03em]">KPI công trình · {{ $kpiHeader->projectCode }}</h1>
            <div class="tw:text-[11px] tw:text-[#728196] tw:mt-[5px]">{{ $kpiHeader->projectName }} · Kỳ {{ $month }} · Dữ liệu tại đây là nguồn bằng chứng cho KPI tháng của kỹ sư.</div>
        </div>
        <div class="tw:flex tw:gap-2 tw:flex-wrap">
            <a class="tw:h-[37px] tw:border tw:border-solid tw:border-[#d8e4ef] tw:bg-white tw:text-[#27445e] tw:rounded-[10px] tw:px-[13px] tw:py-0 tw:inline-flex tw:items-center tw:gap-[7px] tw:no-underline tw:text-[11px] tw:font-black" href="{{ route('projects-unified.show', $site) }}"><i class="bi bi-building"></i>Về công trình</a>
            <a class="tw:h-[37px] tw:border tw:border-solid tw:border-[#d8e4ef] tw:bg-white tw:text-[#27445e] tw:rounded-[10px] tw:px-[13px] tw:py-0 tw:inline-flex tw:items-center tw:gap-[7px] tw:no-underline tw:text-[11px] tw:font-black" href="{{ route('ky-thuat.kpis.index', ['month'=>$month]) }}"><i class="bi bi-bar-chart"></i>Dashboard KPI</a>
        </div>
    </header>

    @if(session('success'))<div class="tw:px-3 tw:py-[9px] tw:rounded-[10px] tw:mb-[10px] tw:bg-[#ecfdf5] tw:text-[#166534] tw:text-[11px] tw:font-extrabold"><i class="bi bi-check-circle tw:mr-1"></i>{{ session('success') }}</div>@endif

    <section class="tw:bg-white tw:border tw:border-solid tw:border-[#e3ebf3] tw:rounded-[15px] tw:shadow-[0_10px_28px_rgba(15,23,42,0.04)] tw:mb-3 tw:overflow-hidden tw:max-[1001px]:overflow-auto">
        <div class="tw:grid tw:grid-cols-[repeat(5,1fr)] tw:gap-2 tw:p-3 tw:max-[1001px]:grid-cols-2 tw:max-[601px]:grid-cols-1 tw:[&>div]:px-3 tw:[&>div]:py-[11px] tw:[&>div]:border tw:[&>div]:border-solid tw:[&>div]:border-[#e8eef5] tw:[&>div]:rounded-[12px] tw:[&>div]:bg-[#fbfdff] tw:[&_span]:text-[9.5px] tw:[&_span]:text-[#708298] tw:[&_span]:[font-weight:850] tw:[&_span]:uppercase tw:[&_strong]:block tw:[&_strong]:mt-[5px] tw:[&_strong]:text-[16px] tw:[&_strong]:text-[#173b59]">
            <div><span>Công trình</span><strong>{{ $kpiHeader->projectCode }}</strong></div>
            <div><span>Tiến độ</span><strong>{{ $kpiHeader->progressText }}</strong></div>
            <div><span>Hạn hoàn thành</span><strong>{{ $kpiHeader->targetCompletionText }}</strong></div>
            <div><span>Ngày hoàn thành</span><strong>{{ $kpiHeader->completedText }}</strong></div>
            <div><span>Kỹ sư liên quan</span><strong>{{ $kpiHeader->engineerCountText }}</strong></div>
        </div>
        <div class="tw:px-3 tw:py-[10px] tw:bg-[#f8fbff] tw:[border-top:1px_solid_#e8eef5] tw:text-[#6d7e90] tw:text-[10px]"><i class="bi bi-link-45deg"></i> Tiến độ được đọc tự động từ workflow Công trình. Chất lượng, HSE, hao hụt vật tư và EVN/App được xác nhận tại màn hình này để tránh nhập lại ở bảng KPI tháng.</div>
    </section>

    <form method="POST" action="{{ route('ky-thuat.kpis.project.save', ['site'=>$site->id, 'month'=>$month]) }}" class="tw:bg-white tw:border tw:border-solid tw:border-[#e3ebf3] tw:rounded-[15px] tw:shadow-[0_10px_28px_rgba(15,23,42,0.04)] tw:mb-3 tw:overflow-hidden tw:max-[1001px]:overflow-auto">
        @csrf
        <input type="hidden" name="month" value="{{ $month }}">
        @if($engineerRows === [])
            <div class="tw:p-7 tw:text-center tw:text-[#8090a0]">Chưa có kỹ sư được phân công cho công trình này.</div>
        @else
        <div class="tw:overflow-auto">
        <table class="tw:w-full tw:[border-collapse:collapse] tw:text-[10.5px] tw:max-[1001px]:min-w-[1100px] tw:[&>thead>tr>th]:bg-[#f7fafc] tw:[&>thead>tr>th]:text-[#5a7086] tw:[&>thead>tr>th]:text-left tw:[&>thead>tr>th]:p-[9px] tw:[&>thead>tr>th]:[border-bottom:1px_solid_#e5edf4] tw:[&>thead>tr>th]:text-[9px] tw:[&>thead>tr>th]:uppercase tw:[&>tbody>tr>td]:p-[9px] tw:[&>tbody>tr>td]:[border-bottom:1px_solid_#edf2f7] tw:[&>tbody>tr>td]:align-top">
            <thead><tr><th scope="col">Kỹ sư</th><th scope="col">Tiến độ tự động</th><th scope="col">Chất lượng</th><th scope="col">Hao hụt vật tư</th><th scope="col">HSE</th><th scope="col">EVN / App</th><th scope="col">Điểm phạt</th><th scope="col">Ghi chú</th></tr></thead>
            <tbody>
            @foreach($engineerRows as $row)
                <tr>
                    <td><strong class="tw:block tw:text-[#12324d] tw:text-[11.5px]">{{ $row->name }}</strong><small class="tw:block tw:text-[#8390a0] tw:mt-[3px]">{{ $row->email }}</small><span class="tw:inline-flex tw:items-center tw:px-[7px] tw:py-1 tw:rounded-[999px] tw:bg-[#edf6ff] tw:text-[#2563a2] tw:text-[9px] tw:[font-weight:850]">Kỹ sư #{{ $row->userId }}</span></td>
                    <td>
                        <div class="tw:text-[10px] tw:text-[#48647e] tw:leading-[1.45]">
                            @if($row->hasSignal)
                                <div>Hạn: <b>{{ $row->deadlineText }}</b></div>
                                <div>Hoàn thành: <b>{{ $row->completedText }}</b></div>
                                @if($row->onTime === 'ok')<div class="tw:text-[#15803d] tw:[font-weight:850]">✓ Đúng hạn</div>@elseif($row->onTime === 'late')<div class="tw:text-[#c2410c] tw:[font-weight:850]">! Trễ hạn</div>@else<div>Chưa đủ dữ liệu</div>@endif
                            @else
                                <div>Chưa có hoạt động workflow trong kỳ.</div>
                            @endif
                        </div>
                        <label class="tw:flex tw:items-center tw:gap-[6px] tw:text-[10px] tw:font-extrabold tw:text-[#51677c] tw:mt-[6px]"><input type="checkbox" name="evidence[{{ $row->userId }}][timeline_excluded]" value="1" @checked($row->timelineExcluded) @disabled(!$canManage)> Loại trừ tiến độ</label>
                        <input class="tw:w-full tw:h-[34px] tw:border tw:border-solid tw:border-[#d8e4ef] tw:rounded-[9px] tw:px-[9px] tw:py-0 tw:bg-white tw:text-[#183751] tw:text-[10.5px] tw:[font-weight:750] tw:mt-[6px]" type="text" name="evidence[{{ $row->userId }}][timeline_exclusion_reason]" value="{{ $row->exclusionReason }}" placeholder="Lý do loại trừ" @disabled(!$canManage)>
                    </td>
                    <td><select class="tw:w-full tw:h-[34px] tw:border tw:border-solid tw:border-[#d8e4ef] tw:rounded-[9px] tw:px-[9px] tw:py-0 tw:bg-white tw:text-[#183751] tw:text-[10.5px] tw:[font-weight:750]" name="evidence[{{ $row->userId }}][quality_first_pass]" @disabled(!$canManage)><option value="">Chưa xác nhận</option><option value="1" @selected($row->qualityFirstPass === 1)>Đạt lần đầu</option><option value="0" @selected($row->qualityFirstPass === 0)>Phải sửa / nghiệm thu lại</option></select></td>
                    <td><input class="tw:w-full tw:h-[34px] tw:border tw:border-solid tw:border-[#d8e4ef] tw:rounded-[9px] tw:px-[9px] tw:py-0 tw:bg-white tw:text-[#183751] tw:text-[10.5px] tw:[font-weight:750]" type="number" step="0.01" min="0" max="100" name="evidence[{{ $row->userId }}][material_waste_percent]" value="{{ $row->materialWastePercent }}" placeholder="% hao hụt" @disabled(!$canManage)></td>
                    <td><select class="tw:w-full tw:h-[34px] tw:border tw:border-solid tw:border-[#d8e4ef] tw:rounded-[9px] tw:px-[9px] tw:py-0 tw:bg-white tw:text-[#183751] tw:text-[10.5px] tw:[font-weight:750]" name="evidence[{{ $row->userId }}][hse_pass]" @disabled(!$canManage)><option value="">Chưa xác nhận</option><option value="1" @selected($row->hsePass === 1)>Đạt HSE</option><option value="0" @selected($row->hsePass === 0)>Không đạt HSE</option></select></td>
                    <td>
                        <select class="tw:w-full tw:h-[34px] tw:border tw:border-solid tw:border-[#d8e4ef] tw:rounded-[9px] tw:px-[9px] tw:py-0 tw:bg-white tw:text-[#183751] tw:text-[10.5px] tw:[font-weight:750]" name="evidence[{{ $row->userId }}][evn_app_required]" @disabled(!$canManage)><option value="">Chưa xác nhận</option><option value="0" @selected($row->evnAppRequired === 0)>N/A · Không yêu cầu</option><option value="1" @selected($row->evnAppRequired === 1)>Có yêu cầu</option></select>
                        <select class="tw:w-full tw:h-[34px] tw:border tw:border-solid tw:border-[#d8e4ef] tw:rounded-[9px] tw:px-[9px] tw:py-0 tw:bg-white tw:text-[#183751] tw:text-[10.5px] tw:[font-weight:750] tw:mt-[6px]" name="evidence[{{ $row->userId }}][evn_app_completed]" @disabled(!$canManage)><option value="">Trạng thái</option><option value="1" @selected($row->evnAppCompleted === 1)>Đã hoàn tất</option><option value="0" @selected($row->evnAppCompleted === 0)>Chưa hoàn tất</option></select>
                    </td>
                    <td><select class="tw:w-full tw:h-[34px] tw:border tw:border-solid tw:border-[#d8e4ef] tw:rounded-[9px] tw:px-[9px] tw:py-0 tw:bg-white tw:text-[#183751] tw:text-[10.5px] tw:[font-weight:750]" name="evidence[{{ $row->userId }}][penalty_points]" @disabled(!$canManage)><option value="0" @selected($row->penaltyPoints === 0.0)>0 điểm</option><option value="10" @selected($row->penaltyPoints === 10.0)>-10 điểm</option><option value="20" @selected($row->penaltyPoints === 20.0)>-20 điểm</option></select></td>
                    <td><textarea class="tw:w-full tw:border tw:border-solid tw:border-[#d8e4ef] tw:rounded-[9px] tw:bg-white tw:text-[#183751] tw:text-[10.5px] tw:[font-weight:750] tw:h-[58px] tw:p-2 tw:resize-y" name="evidence[{{ $row->userId }}][note]" placeholder="Minh chứng / ghi chú" @disabled(!$canManage)>{{ $row->note }}</textarea></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
        <div class="tw:flex tw:justify-end tw:p-3 tw:[border-top:1px_solid_#e8eef5]">
            @if($canManage)<button class="tw:h-[37px] tw:border tw:border-solid tw:rounded-[10px] tw:px-[13px] tw:py-0 tw:inline-flex tw:items-center tw:gap-[7px] tw:no-underline tw:text-[11px] tw:font-black tw:bg-[#0d355c] tw:border-[#0d355c] tw:text-white" type="submit"><i class="bi bi-save"></i>Lưu KPI công trình</button>@else<span class="tw:inline-flex tw:items-center tw:px-[7px] tw:py-1 tw:rounded-[999px] tw:bg-[#edf6ff] tw:text-[#2563a2] tw:text-[9px] tw:[font-weight:850]"><i class="bi bi-lock"></i> Chỉ Trưởng kỹ thuật / Quản lý / Admin được cập nhật</span>@endif
        </div>
    </form>
</div>
</div>
@endsection
