{{--
    EGO_VIEW_CHET — VIEW CHẾT, KHÔNG AI RENDER (rà soát 2026-09-04)

    MarketingPerformanceController có 0 route (kiểm bằng route:list). Ngoài ra view gọi route('marketing.metrics') — route KHÔNG tồn tại; gọi thẳng action ra "View [marketing.metrics] not found" nên thêm route cũng chỉ đổi 404 thành 500.

    Cụm chỉ số marketing bị xoá ở commit 7a7f538 (20/07/2026); phần dùng được nay nằm trong marketing/budget.

    CHƯA XOÁ theo yêu cầu: chỉ đánh dấu để lần sau khỏi rà lại.
    Nếu bạn đấu view này vào một route/@include, hãy XOÁ dấu này —
    tests/Feature/View/DeadViewsMarkedTest.php sẽ báo đỏ để nhắc.
--}}
@extends('layouts.app')

@section('content')
<div class="container-fluid tw:px-6 tw:mt-4">
    <div class="tw:flex tw:justify-between tw:items-center tw:mb-4">
        <div>
            <h4 class="tw:font-bold tw:mb-0">Marketing Performance</h4>
            <small class="tw:text-[rgba(33,37,41,0.75)]">Gộp: Ngân sách + Chỉ số</small>
        </div>

        <div class="tw:flex tw:gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('marketing.budget') }}">Trang Ngân sách</a>
            <a class="btn btn-outline-secondary" href="{{ route('marketing.metrics') }}">Trang Chỉ số</a>
        </div>
    </div>

    {{-- Filter --}}
    <x-ui.card class="border-0 shadow-sm tw:mb-4">
        <x-ui.card-body>
            <form class="tw:row tw:g-2 tw:items-end" method="GET" action="{{ route('marketing.performance') }}">
                <div class="tw:md:col12-3">
                    <label class="form-label small tw:text-[rgba(33,37,41,0.75)]">Tháng</label>
                    <input type="month" name="month" value="{{ $month }}" class="form-control">
                </div>

                <div class="tw:md:col12-3">
                    <label class="form-label small tw:text-[rgba(33,37,41,0.75)]">Kênh</label>
                    <select name="platform" class="form-select">
                        <option value="">-- Tất cả --</option>
                        <option value="Facebook" {{ ($platform ?? '')=='Facebook' ? 'selected' : '' }}>Facebook</option>
                        <option value="Google"   {{ ($platform ?? '')=='Google' ? 'selected' : '' }}>Google</option>
                        <option value="TikTok"   {{ ($platform ?? '')=='TikTok' ? 'selected' : '' }}>TikTok</option>
                        <option value="Zalo"     {{ ($platform ?? '')=='Zalo' ? 'selected' : '' }}>Zalo</option>
                        <option value="Khác"     {{ ($platform ?? '')=='Khác' ? 'selected' : '' }}>Khác</option>
                    </select>
                </div>

                <div class="tw:md:col12-3 tw:flex tw:gap-2">
                    <button class="btn btn-outline-secondary tw:w-full">Lọc</button>
                    <a href="{{ route('marketing.performance') }}" class="btn btn-light tw:w-full">Xóa</a>
                </div>
            </form>
        </x-ui.card-body>
    </x-ui.card>

    {{-- KPI --}}
    <div class="tw:row tw:g-3 tw:mb-4">
        <div class="tw:md:col12-3"><x-ui.card class="border-0 shadow-sm"><x-ui.card-body>
            <div class="tw:text-[rgba(33,37,41,0.75)] small">Ngân sách</div>
            <div class="fs-5 tw:font-bold">{{ number_format($kpi['budget']) }} đ</div>
        </x-ui.card-body></x-ui.card></div>

        <div class="tw:md:col12-3"><x-ui.card class="border-0 shadow-sm"><x-ui.card-body>
            <div class="tw:text-[rgba(33,37,41,0.75)] small">Chi tiêu</div>
            <div class="fs-5 tw:font-bold">{{ number_format($kpi['spend']) }} đ</div>
        </x-ui.card-body></x-ui.card></div>

        <div class="tw:md:col12-2"><x-ui.card class="border-0 shadow-sm"><x-ui.card-body>
            <div class="tw:text-[rgba(33,37,41,0.75)] small">Lead</div>
            <div class="fs-5 tw:font-bold">{{ number_format($kpi['leads']) }}</div>
        </x-ui.card-body></x-ui.card></div>

        <div class="tw:md:col12-2"><x-ui.card class="border-0 shadow-sm"><x-ui.card-body>
            <div class="tw:text-[rgba(33,37,41,0.75)] small">Đơn</div>
            <div class="fs-5 tw:font-bold">{{ number_format($kpi['orders']) }}</div>
        </x-ui.card-body></x-ui.card></div>

        <div class="tw:md:col12-2"><x-ui.card class="border-0 shadow-sm"><x-ui.card-body>
            <div class="tw:text-[rgba(33,37,41,0.75)] small">ROAS</div>
            <div class="fs-5 tw:font-bold">{{ $kpi['roas'] }}</div>
            <div class="tw:text-[rgba(33,37,41,0.75)] small">CPL: {{ number_format($kpi['cpl']) }} | CPO: {{ number_format($kpi['cpo']) }}</div>
        </x-ui.card-body></x-ui.card></div>
    </div>

    {{-- Performance table --}}
    <x-ui.card class="border-0 shadow-sm">
        <x-ui.card-header class="bg-white tw:font-semibold tw:flex tw:justify-between">
            <span>Bảng tổng hợp</span>
            <span class="tw:text-[rgba(33,37,41,0.75)] small">Gộp theo tháng & kênh</span>
        </x-ui.card-header>

        <div class="table-responsive">
            <table class="table table-hover align-middle tw:mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tháng</th>
                        <th>Kênh</th>
                        <th class="tw:text-right">Ngân sách</th>
                        <th class="tw:text-right">Chi tiêu (thực tế)</th>
                        <th class="tw:text-right">Lead</th>
                        <th class="tw:text-right">CPL</th>
                        <th class="tw:text-right">Đơn</th>
                        <th class="tw:text-right">CPO</th>
                        <th class="tw:text-right">Doanh thu</th>
                        <th class="tw:text-right">ROAS</th>
                        <th class="tw:text-right">% tiêu ngân sách</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($performance as $p)
                        @php
                            $cpl  = $p['leads']  > 0 ? round($p['spend'] / $p['leads']) : 0;
                            $cpo  = $p['orders'] > 0 ? round($p['spend'] / $p['orders']) : 0;
                            $roas = $p['spend']  > 0 ? round($p['revenue'] / $p['spend'], 2) : 0;
                            $percent = $p['budget'] > 0 ? round(($p['spend'] / $p['budget']) * 100) : 0;
                        @endphp
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $p['month'])->format('m/Y') }}</td>
                            <td>{{ $p['platform'] }}</td>
                            <td class="tw:text-right">{{ number_format($p['budget']) }} đ</td>
                            <td class="tw:text-right">{{ number_format($p['spend']) }} đ</td>
                            <td class="tw:text-right">{{ number_format($p['leads']) }}</td>
                            <td class="tw:text-right">{{ number_format($cpl) }}</td>
                            <td class="tw:text-right">{{ number_format($p['orders']) }}</td>
                            <td class="tw:text-right">{{ number_format($cpo) }}</td>
                            <td class="tw:text-right">{{ number_format($p['revenue']) }} đ</td>
                            <td class="tw:text-right">{{ $roas }}</td>
                            <td class="tw:text-right">{{ $percent }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-6">Chưa có dữ liệu</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

</div>
@endsection
