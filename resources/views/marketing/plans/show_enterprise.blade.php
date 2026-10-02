{{--
    EGO_VIEW_CHET — VIEW CHẾT, KHÔNG AI RENDER (rà soát 2026-10-02)

    Rà đủ 4 cách viết tên (`marketing.plans.show_enterprise`, `marketing/plans/show_enterprise`,
    `plans.show_enterprise`, `show_enterprise`) trên `app/`, `routes/`, `resources/`, `config/`:
    0 tham chiếu. `MarketingPlanController` chỉ render `plans.index|create|show|edit`.

    Bản "enterprise" của trang xem kế hoạch (4 ô KPI + breakdown theo kênh) nên dựng xong rồi
    không nối dây; `marketing/plans/show` mới là bản đang chạy.

    CHƯA XOÁ theo yêu cầu: chỉ đánh dấu để lần sau khỏi rà lại và để chương trình dọn view bỏ qua.
    Nếu bạn đấu view này vào một route/@include, hãy XOÁ dấu này —
    tests/Feature/View/DeadViewsMarkedTest.php sẽ báo đỏ để nhắc.
--}}
@extends('layouts.app')

@section('content')
<div class="container-fluid tw:py-6">

    <h3 class="tw:font-bold tw:mb-6">
        {{ $plan->name }}
        <small class="tw:text-[rgba(33,37,41,0.75)]">
            {{ \Carbon\Carbon::parse($plan->month)->format('m/Y') }}
        </small>
    </h3>

    <div class="tw:row tw:g-4 tw:mb-6">

        <div class="tw:min-[62rem]:col12-3">
            <x-ui.card class="shadow-sm rounded-4 tw:p-6">
                <div class="tw:text-[rgba(33,37,41,0.75)] small">Budget Used</div>
                <div class="fs-4 tw:font-bold tw:text-[#198754]">
                    {{ number_format($actual['spend'],0,',','.') }} đ
                </div>
                <div class="progress tw:mt-2">
                    <div class="progress-bar" style="width: {{ $kpi['progress_budget'] }}%"></div>
                </div>
                <small>{{ $kpi['progress_budget'] }}%</small>
            </x-ui.card>
        </div>

        <div class="tw:min-[62rem]:col12-3">
            <x-ui.card class="shadow-sm rounded-4 tw:p-6">
                <div class="tw:text-[rgba(33,37,41,0.75)] small">Leads</div>
                <div class="fs-4 tw:font-bold">
                    {{ number_format($actual['leads']) }}
                </div>
                <div class="progress tw:mt-2">
                    <div class="progress-bar bg-success" style="width: {{ $kpi['progress_leads'] }}%"></div>
                </div>
                <small>{{ $kpi['progress_leads'] }}%</small>
            </x-ui.card>
        </div>

        <div class="tw:min-[62rem]:col12-3">
            <x-ui.card class="shadow-sm rounded-4 tw:p-6">
                <div class="tw:text-[rgba(33,37,41,0.75)] small">Revenue</div>
                <div class="fs-4 tw:font-bold tw:text-[#0d6efd]">
                    {{ number_format($actual['revenue'],0,',','.') }} đ
                </div>
                <div class="progress tw:mt-2">
                    <div class="progress-bar bg-info" style="width: {{ $kpi['progress_rev'] }}%"></div>
                </div>
                <small>{{ $kpi['progress_rev'] }}%</small>
            </x-ui.card>
        </div>

        <div class="tw:min-[62rem]:col12-3">
            <x-ui.card class="shadow-sm rounded-4 tw:p-6">
                <div class="tw:text-[rgba(33,37,41,0.75)] small">ROAS</div>
                <div class="fs-4 tw:font-bold">{{ $kpi['actual_roas'] }}</div>
                <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mt-1">
                    CPL {{ number_format($kpi['actual_cpl'],0,',','.') }} đ
                </div>
            </x-ui.card>
        </div>

    </div>

    <x-ui.card class="shadow-sm rounded-4 tw:p-6">
        <h6 class="tw:font-bold tw:mb-4">Channel Breakdown</h6>
        <table class="table">
            <thead>
                <tr>
                    <th>Channel</th>
                    <th class="tw:text-right">Spend</th>
                </tr>
            </thead>
            <tbody>
                @foreach($channels as $ch)
                    <tr>
                        <td>{{ $ch->channel }}</td>
                        <td class="tw:text-right">
                            {{ number_format($ch->spend,0,',','.') }} đ
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>

</div>
@endsection