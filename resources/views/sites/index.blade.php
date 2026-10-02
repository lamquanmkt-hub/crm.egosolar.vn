{{-- $egoSiteCompanyOptions và $egoSiteCompanyMap do EgoDefaultCompany cấp qua
     view composer. Trước đây cùng một khối truy vấn companies được chép vào cả
     ba view sites/index, sites/create, sites/edit — sửa điều kiện lọc ở một chỗ
     mà quên hai chỗ kia là ra ba danh sách khác nhau trên ba trang. --}}
@extends('layouts.app')

@section('content')

<div class="tw:w-full tw:mx-auto tw:px-6 tw:py-4 tw:rounded-[22px] tw:[background:radial-gradient(circle_at_top_left,rgba(11,201,170,.13),transparent_26%),linear-gradient(180deg,rgba(11,201,170,.10)_0%,rgba(11,201,170,.06)_22%,rgba(255,255,255,0)_70%)]
     tw:max-[993px]:px-0">

    {{-- HEADER --}}
    <div class="tw:flex tw:flex-wrap tw:justify-between tw:items-start tw:gap-2 tw:mt-[6px] tw:mb-4">
        <div>
            <div class="tw:flex tw:items-center tw:gap-2 tw:mb-1">
                <span class="tw:w-[42px] tw:h-[42px] tw:rounded-[16px] tw:inline-flex tw:items-center tw:justify-center
                      tw:text-[#0f766e] tw:border tw:border-solid tw:border-[rgba(11,201,170,0.22)]
                      tw:[background:linear-gradient(135deg,rgba(11,201,170,.20),rgba(59,130,246,.13))]
                      tw:shadow-[0_12px_26px_rgba(2,44,34,0.08)]">
                    <i class="bi bi-buildings"></i>
                </span>
                <h4 class="tw:font-bold tw:mb-0">{{ $pageTitle }}</h4>
            </div>
            <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">
                {{ $pageSubtitle }}
                @if($canSeeCost)
                    <span>Admin/kế toán/quản lý có thêm chi phí và lợi nhuận.</span>
                @endif
            </div>
        </div>

        <x-ui.button variant="none" size="none" class="tw:[background:linear-gradient(135deg,#0BC9AA,#08b79b)] tw:[border:0] tw:text-[#ffffff] tw:rounded-[14px] tw:py-[10px] tw:px-[14px] tw:font-extrabold tw:shadow-[0_10px_22px_rgba(11,201,170,0.24)] tw:hover:text-[#ffffff] tw:hover:-translate-y-px tw:hover:shadow-[0_14px_28px_rgba(11,201,170,0.32)]" href="{{ $projectCreateUrl }}">
            <i class="bi bi-plus-lg"></i> Tạo {{ $projectLabel }}
        </x-ui.button>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success" class="tw:py-2 tw:mb-4 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:[border:0]" style="border-radius:14px;">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" class="tw:py-2 tw:mb-4 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:[border:0]" style="border-radius:14px;">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
        </x-ui.alert>
    @endif

    {{-- FILTER BAR --}}
    <x-sites.card class="tw:mb-4" style="border-radius:18px;">
        <x-ui.card-body>
            <form method="GET" class="tw:row tw:g-2 tw:items-end">
                <div class="tw:lg:col12-4">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Tìm nhanh</x-ui.label>
                    <div class="tw:relative tw:flex tw:flex-wrap tw:items-stretch tw:w-full">
                        <span class="tw:flex tw:items-center tw:text-center tw:py-[6px] tw:px-[12px] tw:text-[16px] tw:font-normal
                                     tw:text-[#212529] tw:bg-[#ffffff] tw:whitespace-nowrap
                                     tw:border tw:border-solid tw:border-[rgba(15,23,42,0.12)]
                                     tw:rounded-l-[13px] tw:rounded-r-none">
                            <i class="bi bi-search"></i>
                        </span>
                        <x-ui.input type="text" name="q" value="{{ $q }}"
                               class="tw:relative tw:[flex:1_1_0%] tw:min-w-0 tw:-ml-px
                                      tw:border-[rgba(15,23,42,0.12)]
                                      tw:rounded-l-none tw:rounded-r-[13px]
                                      tw:focus:border-[rgba(11,201,170,0.7)] tw:focus:shadow-[0_0_0_.2rem_rgba(11,201,170,0.12)]"
                               placeholder="Tên công trình, địa chỉ, liên hệ, SĐT..." />
                    </div>
                </div>

                <div class="tw:lg:col12-2">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Trạng thái</x-ui.label>
                    <x-ui.select class="tw:focus:border-[rgba(11,201,170,0.7)] tw:focus:shadow-[0_0_0_.2rem_rgba(11,201,170,0.12)]" name="status" style="border-radius:13px;">
                        <option value="">-- Tất cả --</option>
                        <option value="planning"   {{ $status=='planning'?'selected':'' }}>Chuẩn bị</option>
                        <option value="installing" {{ $status=='installing'?'selected':'' }}>Đang lắp đặt</option>
                        <option value="done"       {{ $status=='done'?'selected':'' }}>Đã hoàn thành</option>
                        <option value="warranty"   {{ $status=='warranty'?'selected':'' }}>Đang bảo hành</option>
                    </x-ui.select>
                </div>


                {{-- EGO_SITE_COMPANY_FILTER_START --}}
                <div class="tw:lg:col12-2">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Công ty</x-ui.label>
                    <x-ui.select class="tw:focus:border-[rgba(11,201,170,0.7)] tw:focus:shadow-[0_0_0_.2rem_rgba(11,201,170,0.12)]" name="company_id" style="border-radius:13px;">
                        <option value="">-- Tất cả --</option>
                        @foreach($egoSiteCompanyOptions as $company)
                            <option value="{{ $company->id }}" {{ (string)$companyFilter === (string)$company->id ? 'selected' : '' }}>
                                {{ trim(($company->code ?? '') . ' - ' . ($company->name ?? '')) }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>
                {{-- EGO_SITE_COMPANY_FILTER_END --}}



                <div class="tw:lg:col12-2">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Lắp đặt từ</x-ui.label>
                    <x-ui.input type="date" name="installed_from" value="{{ $installedFrom }}" class="tw:focus:border-[rgba(11,201,170,0.7)] tw:focus:shadow-[0_0_0_.2rem_rgba(11,201,170,0.12)]" style="border-radius:13px;" />
                </div>

                <div class="tw:lg:col12-2">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Lắp đặt đến</x-ui.label>
                    <x-ui.input type="date" name="installed_to" value="{{ $installedTo }}" class="tw:focus:border-[rgba(11,201,170,0.7)] tw:focus:shadow-[0_0_0_.2rem_rgba(11,201,170,0.12)]" style="border-radius:13px;" />
                </div>

                <div class="tw:lg:col12-2">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Hoàn thành từ</x-ui.label>
                    <x-ui.input type="date" name="completed_from" value="{{ $completedFrom }}" class="tw:focus:border-[rgba(11,201,170,0.7)] tw:focus:shadow-[0_0_0_.2rem_rgba(11,201,170,0.12)]" style="border-radius:13px;" />
                </div>

                <div class="tw:lg:col12-2">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mb-1">Hoàn thành đến</x-ui.label>
                    <x-ui.input type="date" name="completed_to" value="{{ $completedTo }}" class="tw:focus:border-[rgba(11,201,170,0.7)] tw:focus:shadow-[0_0_0_.2rem_rgba(11,201,170,0.12)]" style="border-radius:13px;" />
                </div>

                <div class="tw:lg:col12-10 tw:flex tw:gap-2 tw:lg:justify-end">
                    <x-ui.button variant="none" size="none" type="submit" class="tw:border tw:border-solid tw:border-[rgba(11,201,170,0.55)] tw:text-[#0f766e]
                                   tw:bg-[rgba(11,201,170,0.10)] tw:rounded-[13px] tw:font-bold
                                   tw:hover:border-[rgba(11,201,170,0.75)] tw:hover:bg-[rgba(11,201,170,0.16)]
                                   tw:hover:text-[#0f766e] tw:py-[6px] tw:px-3" style="border-radius:13px;">
                        <i class="bi bi-funnel"></i> Lọc
                    </x-ui.button>

                    <x-ui.button variant="outline-secondary" href="{{ $projectIndexUrl }}" title="Reset" style="border-radius:13px;">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-body>
    </x-sites.card>

    {{-- KPI FINANCE --}}
    <div class="tw:row tw:g-3 tw:mb-4">
        <div class="{{ $canSeeCost ? 'tw:md:col12-3' : 'tw:md:col12-4' }}">
            <x-sites.card border="rgba(11,201,170,0.14)" class="tw:min-h-[112px] tw:[background:radial-gradient(circle_at_top_right,rgba(11,201,170,.18),transparent_42%),rgba(255,255,255,.95)]" style="border-radius:18px;">
                <x-ui.card-body>
                    <div class="tw:flex tw:items-start tw:justify-between tw:gap-2">
                        <div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Tổng doanh thu dự án</div>
                            <div class="tw:text-[20px] tw:font-black tw:text-[#198754]">{{ $totalContractText }}</div>
                            <div class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Giá trị hợp đồng các công trình đang hiển thị</div>
                        </div>
                        <div class="tw:w-[46px] tw:h-[46px] tw:rounded-[15px] tw:flex tw:items-center tw:justify-center tw:text-[18px] tw:border tw:border-solid tw:border-[rgba(0,0,0,0.06)] tw:shrink-0 tw:grow-0 tw:basis-auto tw:bg-[rgba(11,201,170,0.12)] tw:text-[#0f766e]">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                    </div>
                </x-ui.card-body>
            </x-sites.card>
        </div>

        <div class="{{ $canSeeCost ? 'tw:md:col12-3' : 'tw:md:col12-4' }}">
            <x-sites.card border="rgba(11,201,170,0.14)" class="tw:min-h-[112px]" style="border-radius:18px;">
                <x-ui.card-body>
                    <div class="tw:flex tw:items-start tw:justify-between tw:gap-2">
                        <div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Đã thu</div>
                            <div class="tw:text-[20px] tw:font-black tw:text-[#0d6efd]">{{ $totalReceivedText }}</div>
                            <div class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">{{ $totalPaidPercentText }} tổng doanh thu</div>
                        </div>
                        <div class="tw:w-[46px] tw:h-[46px] tw:rounded-[15px] tw:flex tw:items-center tw:justify-center tw:text-[18px] tw:border tw:border-solid tw:border-[rgba(0,0,0,0.06)] tw:shrink-0 tw:grow-0 tw:basis-auto tw:bg-[rgba(59,130,246,0.10)] tw:text-[#1d4ed8]">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                    <div class="tw:flex tw:h-[8px] tw:overflow-hidden tw:rounded-[999px] tw:bg-[rgba(15,23,42,0.08)] tw:text-[12px] tw:mt-2">
                        <div class="tw:flex tw:flex-col tw:justify-center tw:overflow-hidden tw:text-center tw:whitespace-nowrap tw:text-[#ffffff] tw:rounded-[999px] tw:transition-[width] tw:duration-[250ms] tw:ease-[ease] tw:[background:linear-gradient(90deg,#0BC9AA,#10b981)]" style="width:{{ $totalPaidPercent }}%"></div>
                    </div>
                </x-ui.card-body>
            </x-sites.card>
        </div>

        <div class="{{ $canSeeCost ? 'tw:md:col12-3' : 'tw:md:col12-4' }}">
            <x-sites.card border="rgba(11,201,170,0.14)" class="tw:min-h-[112px]" style="border-radius:18px;">
                <x-ui.card-body>
                    <div class="tw:flex tw:items-start tw:justify-between tw:gap-2">
                        <div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Công nợ còn lại</div>
                            <div class="tw:text-[20px] tw:font-black {{ $totalDebt > 0 ? 'tw:text-[#dc3545]' : 'tw:text-[#198754]' }}">
                                {{ $totalDebtText }}
                            </div>
                            <div class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">{{ $totalDebt > 0 ? 'Cần theo dõi thu tiền' : 'Không còn công nợ' }}</div>
                        </div>
                        <div class="tw:w-[46px] tw:h-[46px] tw:rounded-[15px] tw:flex tw:items-center tw:justify-center tw:text-[18px] tw:border tw:border-solid tw:border-[rgba(0,0,0,0.06)] tw:shrink-0 tw:grow-0 tw:basis-auto tw:bg-[rgba(245,158,11,0.12)] tw:text-[#b45309]">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>
                    </div>
                </x-ui.card-body>
            </x-sites.card>
        </div>

        @if($canSeeCost)
            <div class="tw:md:col12-3">
                <x-sites.card border="rgba(11,201,170,0.14)" class="tw:min-h-[112px]" style="border-radius:18px;">
                    <x-ui.card-body>
                        <div class="tw:flex tw:items-start tw:justify-between tw:gap-2">
                            <div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Lợi nhuận tạm tính</div>
                                <div class="tw:text-[20px] tw:font-black {{ $totalProfit >= 0 ? 'tw:text-[#198754]' : 'tw:text-[#dc3545]' }}">
                                    {{ $totalProfitText }}
                                </div>
                                <div class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Doanh thu - tổng chi phí</div>
                            </div>
                            <div class="tw:w-[46px] tw:h-[46px] tw:rounded-[15px] tw:flex tw:items-center tw:justify-center tw:text-[18px] tw:border tw:border-solid tw:border-[rgba(0,0,0,0.06)] tw:shrink-0 tw:grow-0 tw:basis-auto tw:bg-[rgba(34,197,94,0.10)] tw:text-[#047857]">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-sites.card>
            </div>
        @endif
    </div>

    {{-- KPI STATUS --}}
    <div class="tw:row tw:g-3 tw:mb-4">
        @foreach($kpiCards as $k)
            <div class="tw:md:col12-3">
                <x-sites.card border="rgba(11,201,170,0.14)" class="tw:min-h-[112px]" style="border-radius:18px;">
                    <x-ui.card-body class="tw:flex tw:items-center tw:justify-between">
                        <div>
                            <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">{{ $k->label }}</div>
                            <div class="tw:text-[20px] tw:font-bold">{{ $k->valueText }}</div>
                        </div>
                        <div class="tw:w-[46px] tw:h-[46px] tw:rounded-[15px] tw:flex tw:items-center tw:justify-center tw:text-[18px] tw:border tw:border-solid tw:border-[rgba(0,0,0,0.06)] tw:shrink-0 tw:grow-0 tw:basis-auto {{ $k->toneClass }}">
                            <i class="bi {{ $k->icon }}"></i>
                        </div>
                    </x-ui.card-body>
                </x-sites.card>
            </div>
        @endforeach
    </div>
    {{-- TABLE --}}
    <x-sites.card style="border-radius:18px;">
        <x-ui.card-header class="tw:bg-white tw:[border:0] tw:py-4 tw:flex tw:flex-wrap tw:justify-between tw:items-center tw:gap-2" style="border-radius:18px 18px 0 0;">
            <div>
                <div class="tw:font-bold">Danh sách công trình</div>
                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Theo dõi kỹ thuật + tài chính dự án trên cùng một bảng.</div>
            </div>

            <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">
                Hiển thị: <b>{{ $sites->count() ?? 0 }}</b>
            </div>
        </x-ui.card-header>

        <x-ui.card-body class="tw:p-0">
            <x-ui.table-wrap>
                <x-ui.table class="tw:align-middle tw:mb-0
                               tw:[&>tbody>tr>td]:[padding-top:1rem] tw:[&>tbody>tr>td]:[padding-bottom:1rem]
                               tw:[&>tbody>tr>td]:align-middle
                               tw:[&>tbody>tr>td]:[border-top:1px_solid_rgba(0,0,0,0.04)]
                               tw:[&>tbody>tr:hover]:[background:rgba(11,201,170,0.05)]
                               tw:[&>tbody>tr]:[transition:.15s_ease]">
                    <x-ui.table-head class="tw:[--ego-table-bg:#f8fafc] tw:[--ego-table-border:rgba(0,0,0,0.06)]
                                         tw:[--ego-table-color:#334155]
                                         tw:[&>tr>th]:text-[.82rem] tw:[&>tr>th]:whitespace-nowrap
                                         tw:[&>tr>th]:align-middle">
                    <tr>
                        <th style="width:58px;" class="tw:text-center">#</th>
                        <th style="min-width:310px;">Công trình</th>
                        <th style="min-width:210px;">Liên hệ</th>
                        <th style="width:170px;" class="tw:text-center">Hệ</th>

                        <th style="min-width:220px;">Tài chính</th>
                        <th style="width:135px;" class="tw:text-center">Lắp đặt</th>
                        <th style="width:135px;" class="tw:text-center">BH đến</th>
                        <th style="width:140px;" class="tw:text-center">Trạng thái</th>
                        <th style="min-width:170px;">Phụ trách</th>
                        <th style="width:88px;" class="tw:text-center">Thao tác</th>
                    </tr>
                    </x-ui.table-head>

                    <tbody>
                    @forelse($sites as $row)

                        <tr>
                            <td class="tw:text-center tw:text-[rgba(33,37,41,0.75)]!">{{ $row->rowNo }}</td>

                            <td>
                                <a href="{{ url('/cong-trinh/'.$row->site->id) }}" class="tw:text-[#0f172a] tw:font-extrabold tw:no-underline tw:hover:text-[#0f766e]">
                                    {{ $row->site->name ?? '—' }}
                                </a>


                                {{-- EGO_SITE_COMPANY_ROW_START --}}
                                @if(!empty($row->site->company_id))
                                    <div class="tw:mt-1">
                                        <x-sites.pill tone="slate">
                                            <i class="bi bi-building"></i>
                                            {{ $egoSiteCompanyMap[(int)$row->site->company_id] ?? ('Công ty #' . $row->site->company_id) }}
                                        </x-sites.pill>
                                    </div>
                                @else
                                    <div class="tw:mt-1">
                                        <x-sites.pill tone="muted">
                                            <i class="bi bi-building"></i> Chưa chọn công ty
                                        </x-sites.pill>
                                    </div>
                                @endif
                                {{-- EGO_SITE_COMPANY_ROW_END --}}


                                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:flex tw:flex-wrap tw:gap-2 tw:mt-1">
                                    <span class="tw:inline-flex tw:items-center tw:gap-1">
                                        <i class="bi bi-geo-alt"></i> {{ $row->site->address ?? '—' }}
                                    </span>

                                    @if(!empty($row->site->note))
                                        <span class="tw:truncate tw:inline-flex tw:items-center tw:gap-1" style="max-width: 420px;">
                                            <i class="bi bi-journal-text"></i> {{ $row->site->note }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td>
                                <div class="tw:font-semibold">{{ $row->site->contact_name ?? '—' }}</div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:inline-flex tw:items-center tw:gap-1 tw:mt-1">
                                    <i class="bi bi-telephone"></i> {{ $row->site->contact_phone ?? '—' }}
                                </div>
                            </td>

                            {{-- HỆ --}}
                            <td class="tw:text-center">
                                @if(!$row->kwpText && !$row->kwText)
                                    <x-sites.pill tone="muted">Chưa nhập</x-sites.pill>
                                @else
                                    <div class="tw:flex flex-column tw:items-center tw:gap-1">
                                        <x-sites.pill tone="ego">{{ $row->kwpText ?? '—' }} <span class="tw:opacity-75 tw:font-semibold">kWp</span></x-sites.pill>
                                        <x-sites.pill tone="slate">{{ $row->kwText ?? '—' }} <span class="tw:opacity-75 tw:font-semibold">kW</span></x-sites.pill>
                                    </div>
                                @endif
                            </td>

                            {{-- TÀI CHÍNH --}}
                            <td>
                                <div class="tw:min-w-[200px] tw:max-[993px]:min-w-[220px] tw:rounded-[14px] tw:p-[10px]
                                            tw:border tw:border-solid tw:border-[rgba(15,23,42,0.06)]
                                            tw:bg-[rgba(248,250,252,0.75)]">
                                    <div class="tw:flex tw:justify-between tw:items-center tw:gap-2">
                                        <span class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Doanh thu</span>
                                        <b class="tw:text-[#198754]">{{ $row->contractText }}</b>
                                    </div>

                                    <div class="tw:flex tw:justify-between tw:items-center tw:gap-2 tw:mt-1">
                                        <span class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Đã thu</span>
                                        <b class="tw:text-[#0d6efd]">{{ $row->receivedText }}</b>
                                    </div>

                                    <div class="tw:flex tw:h-[8px] tw:overflow-hidden tw:rounded-[999px] tw:bg-[rgba(15,23,42,0.08)] tw:text-[12px] tw:mt-2">
                                        <div class="tw:flex tw:flex-col tw:justify-center tw:overflow-hidden tw:text-center tw:whitespace-nowrap tw:text-[#ffffff] tw:rounded-[999px] tw:transition-[width] tw:duration-[250ms] tw:ease-[ease] tw:[background:linear-gradient(90deg,#0BC9AA,#10b981)]" style="width:{{ $row->paidPercent }}%"></div>
                                    </div>

                                    <div class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mt-1">
                                        {{ $row->paidPercentText }} đã thu
                                    </div>
                                </div>
                            </td>

                            <td class="tw:text-center">
                                <x-sites.pill tone="date">{{ $row->installedAtText }}</x-sites.pill>
                            </td>

                            <td class="tw:text-center">
                                <x-sites.pill tone="date">{{ $row->warrantyToText }}</x-sites.pill>
                            </td>

                            <td class="tw:text-center">
                                <span class="tw:inline-block tw:py-[7px] tw:px-[10px] tw:rounded-[999px] tw:text-[12px] tw:font-bold
                                             tw:leading-none tw:text-center tw:align-baseline tw:whitespace-nowrap
                                             {{ $row->statusBadgeClass }}">
                                    <i class="bi {{ $row->statusIcon }}"></i> {{ $row->statusLabel }}
                                </span>
                            </td>

                            <td>
                                @if(count($row->ownerChips) > 0 && $row->ownerChips[0] !== '—')
                                    <div class="tw:flex tw:flex-wrap tw:gap-1">
                                        @foreach($row->ownerChips as $oc)
                                            <x-sites.pill tone="owner">{{ $oc }}</x-sites.pill>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="tw:text-[rgba(33,37,41,0.75)]">—</span>
                                @endif
                            </td>

                            <td class="tw:text-center">
                                <x-ui.dropdown align="end"
                                                menu-class="tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[14px]">
                                    <x-slot:trigger>
                                        {{-- `size="none"` để component không khai cỡ: lớp nút cũ của
                                             trang đè đệm + bo góc của cỡ `sm`, để cả hai cùng khai là
                                             tranh nhau theo thứ tự tệp CSS. Cỡ CHỮ của `sm` thì khai
                                             lại tay (14px/21px) vì lớp cũ không đụng tới.
                                             ⚠️ Chú thích phải ở NGOÀI thẻ: `{{ }}` ở vị trí thuộc tính
                                             của `<x-...>` làm vỡ PHP sinh ra. --}}
                                        <x-ui.button variant="none" size="none" type="button"
                                                     class="tw:text-[14px]/[21px] tw:font-normal
                                                            tw:border tw:border-solid tw:border-[rgba(0,0,0,0.08)]
                                                            tw:bg-[rgba(255,255,255,0.95)] tw:rounded-[12px]
                                                            tw:py-[6px] tw:px-[8px] tw:text-[#212529]
                                                            tw:hover:bg-[rgba(11,201,170,0.10)]
                                                            tw:hover:border-[rgba(11,201,170,0.35)]
                                                            tw:hover:text-[#0f766e]">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </x-ui.button>
                                    </x-slot:trigger>

                                    <x-ui.dropdown-item :href="url('/cong-trinh/'.$row->site->id)">
                                        <i class="bi bi-eye tw:mr-2"></i> Xem chi tiết
                                    </x-ui.dropdown-item>

                                    <x-ui.dropdown-item :href="url('/cong-trinh/'.$row->site->id.'/edit')">
                                        <i class="bi bi-pencil-square tw:mr-2"></i> Sửa
                                    </x-ui.dropdown-item>

                                    <x-ui.dropdown-item :href="url('/don-vat-tu/create?site_id='.$row->site->id)">
                                        <i class="bi bi-box-seam tw:mr-2"></i> Tạo đơn vật tư
                                    </x-ui.dropdown-item>

                                    <x-ui.dropdown-divider />

                                    {{-- Nút xoá nằm trong form nên <li> do form giữ, không dùng
                                         <x-ui.dropdown-item> (nó tự bọc <li> quanh đúng một thẻ). --}}
                                    <li>
                                        <form method="POST"
                                              action="{{ url('/cong-trinh/'.$row->site->id) }}"
                                              onsubmit="return confirm('Xoá công trình này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="tw:block tw:w-full tw:py-1 tw:px-4 tw:text-left tw:font-normal tw:bg-transparent tw:whitespace-nowrap tw:[border:0] tw:cursor-pointer tw:hover:bg-[#e9ecef] tw:text-[#dc3545]">
                                                <i class="bi bi-trash tw:mr-2"></i> Xoá
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-12">
                                <div class="tw:p-5">
                                    <div class="tw:w-[60px] tw:h-[60px] tw:mx-auto tw:rounded-[20px] tw:flex tw:items-center tw:justify-center
                                                tw:text-[26px] tw:bg-[rgba(11,201,170,0.10)] tw:text-[#0f766e]">
                                        <i class="bi bi-buildings"></i>
                                    </div>
                                    <div class="tw:font-bold tw:mt-2">Chưa có {{ $projectLabel }} nào</div>
                                    <div class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Tạo công trình đầu tiên để bắt đầu theo dõi doanh thu, công nợ và chi phí.</div>
                                    <x-ui.button variant="none" size="none" class="tw:[background:linear-gradient(135deg,#0BC9AA,#08b79b)] tw:[border:0] tw:text-[#ffffff] tw:rounded-[14px] tw:py-[10px] tw:px-[14px] tw:font-extrabold tw:shadow-[0_10px_22px_rgba(11,201,170,0.24)] tw:hover:text-[#ffffff] tw:hover:-translate-y-px tw:hover:shadow-[0_14px_28px_rgba(11,201,170,0.32)] tw:mt-4" href="{{ $projectCreateUrl }}">
                                        <i class="bi bi-plus-lg"></i> Tạo {{ $projectLabel }}
                                    </x-ui.button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.table-wrap>
        </x-ui.card-body>

        @if(method_exists($sites, 'links'))
            <x-ui.card-footer class="tw:bg-white tw:[border:0] tw:py-4">
                {{ $sites->links() }}
            </x-ui.card-footer>
        @endif
    </x-sites.card>

</div>
@endsection