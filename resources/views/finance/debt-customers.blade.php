@extends('layouts.app')

@section('title', $debtContext['title'] ?? 'Công nợ khách hàng')

@section('content')
<div class="tw:p-6 tw:max-[769px]:p-4 tw:[background:radial-gradient(900px_500px_at_0%_0%,rgba(59,130,246,.08),transparent_55%),radial-gradient(900px_500px_at_100%_0%,rgba(139,92,246,.08),transparent_55%)]">
    @if(session('success'))
        <x-ui.alert variant="success" :dismissible="true" class="tw:mb-4">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" :dismissible="true" class="tw:mb-4">
            {{ session('error') }}
        </x-ui.alert>
    @endif
    <div class="tw:relative tw:overflow-hidden tw:rounded-[28px] tw:p-6 tw:mb-5 tw:shadow-[0_24px_60px_rgba(37,99,235,0.22)] tw:[background:linear-gradient(135deg,#0f172a_0%,#1e3a8a_45%,#2563eb_100%)]">
        <div class="tw:absolute tw:inset-0 tw:pointer-events-none tw:[background:radial-gradient(circle_at_15%_20%,rgba(255,255,255,.16),transparent_25%),radial-gradient(circle_at_85%_15%,rgba(255,255,255,.14),transparent_20%),radial-gradient(circle_at_70%_80%,rgba(255,255,255,.10),transparent_18%)]"></div>

        <div class="tw:relative tw:z-[1] tw:flex tw:justify-between tw:items-start tw:gap-4 tw:flex-wrap tw:mb-5">
            <div>
                <div class="tw:inline-block tw:py-[7px] tw:px-3 tw:rounded-[999px] tw:bg-[rgba(255,255,255,0.14)] tw:text-[#dbeafe] tw:text-[12px] tw:font-extrabold tw:uppercase tw:tracking-[.08em] tw:mb-3 tw:border tw:border-solid tw:border-[rgba(255,255,255,0.18)] tw:[backdrop-filter:blur(8px)]">{{ $debtContext['kicker'] ?? 'CUSTOMER DEBT MANAGEMENT' }}</div>
                <h1 class="tw:m-0 tw:text-[46px]/[1.02] tw:font-black tw:text-[#ffffff] tw:tracking-[-.02em] tw:max-[769px]:text-[32px]">{{ $debtContext['title'] ?? 'Công nợ khách hàng' }}</h1>
                <p class="tw:mt-3 tw:mr-0 tw:mb-0 tw:ml-0 tw:text-[rgba(255,255,255,0.84)] tw:max-w-[760px] tw:text-[16px]">
                    {{ $debtContext['subtitle'] ?? 'Gộp theo khách hàng, lọc nhanh dữ liệu, theo dõi tổng tiền, số đã thanh toán và phần công nợ còn lại.' }}
                </p>
            </div>

            <div class="tw:flex tw:gap-[10px] tw:flex-wrap">
                <a href="{{ route('finance.customer-debts.by-customer', $filterQuery) }}" class="tw:inline-flex tw:items-center tw:gap-2 tw:rounded-[16px] tw:py-3 tw:px-[18px] tw:no-underline tw:font-extrabold tw:border tw:border-solid tw:[transition:.22s_ease] tw:bg-[rgba(255,255,255,0.12)] tw:text-[#ffffff] tw:border-[rgba(255,255,255,0.18)] tw:[backdrop-filter:blur(10px)] tw:hover:text-[#ffffff] tw:hover:bg-[rgba(255,255,255,0.18)]">
                    <i class="bi bi-bar-chart"></i>
                    <span>Bảng tổng hợp</span>
                </a>
                <a href="{{ route('finance.customer-debts.payment-history', $filterQuery) }}" class="tw:inline-flex tw:items-center tw:gap-2 tw:rounded-[16px] tw:py-3 tw:px-[18px] tw:no-underline tw:font-extrabold tw:border tw:border-solid tw:[transition:.22s_ease] tw:[background:linear-gradient(135deg,#38bdf8,#2563eb)] tw:border-transparent tw:text-[#ffffff] tw:shadow-[0_12px_28px_rgba(2,132,199,0.28)] tw:hover:text-[#ffffff] tw:hover:-translate-y-[2px] tw:hover:shadow-[0_18px_36px_rgba(2,132,199,0.35)]">
                    <i class="bi bi-clock-history"></i>
                    <span>Lịch sử thanh toán</span>
                </a>
            </div>
        </div>

        <form method="GET" class="tw:relative tw:z-[1] tw:bg-[rgba(255,255,255,0.96)] tw:border tw:border-solid tw:border-[rgba(255,255,255,0.35)] tw:rounded-[24px] tw:shadow-[0_14px_40px_rgba(15,23,42,0.10)] tw:p-[18px]">
            <div class="tw:grid tw:[grid-template-columns:2fr_1fr_1fr_1fr] tw:gap-[14px] tw:max-[1201px]:[grid-template-columns:1fr_1fr] tw:max-[769px]:[grid-template-columns:1fr] tw:[&_label]:text-[13px] tw:[&_label]:font-extrabold tw:[&_label]:text-[#334155] tw:[&_input]:h-[48px] tw:[&_input]:rounded-[16px] tw:[&_input]:border tw:[&_input]:[border-style:solid] tw:[&_input]:border-[#d7e3f0] tw:[&_input]:[background:linear-gradient(180deg,#ffffff,#f8fbff)] tw:[&_input]:[padding:0_14px] tw:[&_input]:outline-none tw:[&_input]:[transition:.18s_ease] tw:[&_select]:h-[48px] tw:[&_select]:rounded-[16px] tw:[&_select]:border tw:[&_select]:[border-style:solid] tw:[&_select]:border-[#d7e3f0] tw:[&_select]:[background:linear-gradient(180deg,#ffffff,#f8fbff)] tw:[&_select]:[padding:0_14px] tw:[&_select]:outline-none tw:[&_select]:[transition:.18s_ease] tw:[&_input:focus]:border-[#60a5fa] tw:[&_input:focus]:shadow-[0_0_0_4px_rgba(96,165,250,0.16)] tw:[&_select:focus]:border-[#60a5fa] tw:[&_select:focus]:shadow-[0_0_0_4px_rgba(96,165,250,0.16)]">
                <div class="tw:flex tw:flex-col tw:gap-2">
                    <label>Từ khóa</label>
                    <div class="tw:relative tw:[&_i]:absolute tw:[&_i]:left-[14px] tw:[&_i]:top-1/2 tw:[&_i]:-translate-y-1/2 tw:[&_i]:text-[#94a3b8] tw:[&_input]:w-full tw:[&_input]:pl-10">
                        <i class="bi bi-search"></i>
                        <input
                            type="text"
                            name="keyword"
                            value="{{ $filterKeyword }}"
                            placeholder="Tìm mã đơn, tên khách hàng, người nhận...">
                    </div>
                </div>

                <div class="tw:flex tw:flex-col tw:gap-2">
                    <label>Từ ngày</label>
                    <input type="date" name="from_date" value="{{ $filterFromDate }}">
                </div>

                <div class="tw:flex tw:flex-col tw:gap-2">
                    <label>Đến ngày</label>
                    <input type="date" name="to_date" value="{{ $filterToDate }}">
                </div>

                <div class="tw:flex tw:flex-col tw:gap-2">
                    <label>Trạng thái</label>
                    <select name="payment_status">
                        <option value="">-- Tất cả --</option>
                        <option value="paid" {{ $filterPaymentStatus === 'paid' ? 'selected' : '' }}>Đã hoàn thành</option>
                        <option value="unpaid" {{ $filterPaymentStatus === 'unpaid' ? 'selected' : '' }}>Công nợ</option>
                    </select>
                </div>
            </div>

            <div class="tw:flex tw:gap-[10px] tw:flex-wrap tw:mt-4">
                <button type="submit" class="tw:inline-flex tw:items-center tw:gap-2 tw:rounded-[16px] tw:py-3 tw:px-[18px] tw:no-underline tw:font-extrabold tw:border tw:border-solid tw:[transition:.22s_ease] tw:[background:linear-gradient(135deg,#38bdf8,#2563eb)] tw:border-transparent tw:text-[#ffffff] tw:shadow-[0_12px_28px_rgba(2,132,199,0.28)] tw:hover:text-[#ffffff] tw:hover:-translate-y-[2px] tw:hover:shadow-[0_18px_36px_rgba(2,132,199,0.35)]">
                    <i class="bi bi-funnel"></i>
                    <span>Lọc dữ liệu</span>
                </button>

                <a href="{{ route('finance.customer-debts.index', ['debt_type' => $debtContext['type'] ?? 'construction']) }}" class="tw:inline-flex tw:items-center tw:gap-2 tw:rounded-[16px] tw:py-3 tw:px-[18px] tw:no-underline tw:font-extrabold tw:border tw:border-solid tw:[transition:.22s_ease] tw:bg-[rgba(255,255,255,0.12)] tw:text-[#ffffff] tw:border-[rgba(255,255,255,0.18)] tw:[backdrop-filter:blur(10px)] tw:hover:text-[#ffffff] tw:hover:bg-[rgba(255,255,255,0.18)]">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span>Đặt lại</span>
                </a>
            </div>
        </form>
    </div>

    <div class="tw:grid tw:[grid-template-columns:repeat(4,minmax(0,1fr))] tw:gap-[14px] tw:mb-[18px] tw:max-[1401px]:[grid-template-columns:repeat(2,minmax(0,1fr))] tw:max-[769px]:[grid-template-columns:1fr]">
        <div class="tw:min-w-0 tw:flex tw:items-center tw:gap-[14px] tw:rounded-[22px] tw:py-4 tw:px-[18px] tw:text-[#ffffff] tw:shadow-[0_14px_36px_rgba(15,23,42,0.12)] tw:[&_span]:block tw:[&_span]:text-[13px] tw:[&_span]:opacity-[.92] tw:[&_span]:mb-1 tw:[&_strong]:text-[24px] tw:[&_strong]:text-[#ffffff] tw:[&_strong]:leading-[1.1] tw:[&_strong]:[word-break:break-word] tw:[background:linear-gradient(135deg,#0ea5e9,#2563eb)]">
            <div class="tw:w-12 tw:h-12 tw:rounded-[16px] tw:flex tw:items-center tw:justify-center tw:bg-[rgba(255,255,255,0.16)] tw:border tw:border-solid tw:border-[rgba(255,255,255,0.18)] tw:text-[20px] tw:shrink-0 tw:grow-0 tw:basis-auto">
                <i class="bi bi-people"></i>
            </div>
            <div>
                <span>Tổng khách hàng</span>
                <strong>{{ $totalCustomersText }}</strong>
            </div>
        </div>

        <div class="tw:min-w-0 tw:flex tw:items-center tw:gap-[14px] tw:rounded-[22px] tw:py-4 tw:px-[18px] tw:text-[#ffffff] tw:shadow-[0_14px_36px_rgba(15,23,42,0.12)] tw:[&_span]:block tw:[&_span]:text-[13px] tw:[&_span]:opacity-[.92] tw:[&_span]:mb-1 tw:[&_strong]:text-[24px] tw:[&_strong]:text-[#ffffff] tw:[&_strong]:leading-[1.1] tw:[&_strong]:[word-break:break-word] tw:[background:linear-gradient(135deg,#8b5cf6,#6366f1)]">
            <div class="tw:w-12 tw:h-12 tw:rounded-[16px] tw:flex tw:items-center tw:justify-center tw:bg-[rgba(255,255,255,0.16)] tw:border tw:border-solid tw:border-[rgba(255,255,255,0.18)] tw:text-[20px] tw:shrink-0 tw:grow-0 tw:basis-auto">
                <i class="bi bi-eye"></i>
            </div>
            <div>
                <span>Đang xem</span>
                <strong>{{ $customers->count() }}</strong>
            </div>
        </div>

        <div class="tw:min-w-0 tw:flex tw:items-center tw:gap-[14px] tw:rounded-[22px] tw:py-4 tw:px-[18px] tw:text-[#ffffff] tw:shadow-[0_14px_36px_rgba(15,23,42,0.12)] tw:[&_span]:block tw:[&_span]:text-[13px] tw:[&_span]:opacity-[.92] tw:[&_span]:mb-1 tw:[&_strong]:text-[24px] tw:[&_strong]:text-[#ffffff] tw:[&_strong]:leading-[1.1] tw:[&_strong]:[word-break:break-word] tw:[background:linear-gradient(135deg,#10b981,#059669)]">
            <div class="tw:w-12 tw:h-12 tw:rounded-[16px] tw:flex tw:items-center tw:justify-center tw:bg-[rgba(255,255,255,0.16)] tw:border tw:border-solid tw:border-[rgba(255,255,255,0.18)] tw:text-[20px] tw:shrink-0 tw:grow-0 tw:basis-auto">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div>
                <span>Đã thanh toán</span>
                <strong>{{ $paidTotalText }}</strong>
            </div>
        </div>

        <div class="tw:min-w-0 tw:flex tw:items-center tw:gap-[14px] tw:rounded-[22px] tw:py-4 tw:px-[18px] tw:text-[#ffffff] tw:shadow-[0_14px_36px_rgba(15,23,42,0.12)] tw:[&_span]:block tw:[&_span]:text-[13px] tw:[&_span]:opacity-[.92] tw:[&_span]:mb-1 tw:[&_strong]:text-[24px] tw:[&_strong]:text-[#ffffff] tw:[&_strong]:leading-[1.1] tw:[&_strong]:[word-break:break-word] tw:[background:linear-gradient(135deg,#ef4444,#dc2626)]">
            <div class="tw:w-12 tw:h-12 tw:rounded-[16px] tw:flex tw:items-center tw:justify-center tw:bg-[rgba(255,255,255,0.16)] tw:border tw:border-solid tw:border-[rgba(255,255,255,0.18)] tw:text-[20px] tw:shrink-0 tw:grow-0 tw:basis-auto">
                <i class="bi bi-exclamation-diamond"></i>
            </div>
            <div>
                <span>Tổng công nợ còn lại</span>
                <strong>{{ $debtTotalText }}</strong>
            </div>
        </div>
    </div>

    <div class="tw:flex tw:gap-3 tw:flex-wrap tw:mb-[18px]">
        <div class="tw:flex tw:items-center tw:justify-between tw:gap-4 tw:min-w-[250px] tw:max-[769px]:min-w-full tw:py-[14px] tw:px-4 tw:rounded-[18px] tw:bg-[#ffffff] tw:border tw:border-solid tw:border-[#e6edf5] tw:shadow-[0_10px_24px_rgba(15,23,42,0.05)]">
            <span class="tw:text-[13px] tw:font-bold tw:text-[#475569]">Tổng doanh số sau lọc</span>
            <span class="tw:text-[18px] tw:font-black tw:text-[#0f172a] tw:whitespace-nowrap">{{ $revenueTotalText }}</span>
        </div>

        <div class="tw:flex tw:items-center tw:justify-between tw:gap-4 tw:min-w-[250px] tw:max-[769px]:min-w-full tw:py-[14px] tw:px-4 tw:rounded-[18px] tw:bg-[#ffffff] tw:border tw:border-solid tw:shadow-[0_10px_24px_rgba(15,23,42,0.05)] tw:[background:linear-gradient(180deg,#ecfdf5,#d1fae5)] tw:border-[#a7f3d0]">
            <span class="tw:text-[13px] tw:font-bold tw:text-[#475569]">Đã thanh toán</span>
            <span class="tw:text-[18px] tw:font-black tw:text-[#0f172a] tw:whitespace-nowrap">{{ $paidTotalText }}</span>
        </div>

        <div class="tw:flex tw:items-center tw:justify-between tw:gap-4 tw:min-w-[250px] tw:max-[769px]:min-w-full tw:py-[14px] tw:px-4 tw:rounded-[18px] tw:bg-[#ffffff] tw:border tw:border-solid tw:shadow-[0_10px_24px_rgba(15,23,42,0.05)] tw:[background:linear-gradient(180deg,#fef2f2,#fee2e2)] tw:border-[#fecaca]">
            <span class="tw:text-[13px] tw:font-bold tw:text-[#475569]">Còn phải thu</span>
            <span class="tw:text-[18px] tw:font-black tw:text-[#0f172a] tw:whitespace-nowrap">{{ $debtTotalText }}</span>
        </div>
    </div>

    <div class="tw:[background:linear-gradient(180deg,#ffffff,#fbfdff)] tw:border tw:border-solid tw:border-[#e6edf5] tw:rounded-[28px] tw:overflow-hidden tw:shadow-[0_18px_44px_rgba(15,23,42,0.08)]">
        <div class="tw:py-5 tw:px-[22px] tw:border-b tw:[border-bottom-style:solid] tw:border-b-[#eef3f8] tw:[background:radial-gradient(800px_300px_at_0%_0%,rgba(37,99,235,.06),transparent_50%),linear-gradient(180deg,#ffffff,#fbfdff)] tw:[&_h2]:mt-0 tw:[&_h2]:mr-0 tw:[&_h2]:mb-[6px] tw:[&_h2]:ml-0 tw:[&_h2]:text-[22px] tw:[&_h2]:font-black tw:[&_h2]:text-[#0f172a] tw:[&_p]:m-0 tw:[&_p]:text-[#64748b]">
            <div>
                <h2>Danh sách công nợ gộp theo khách hàng</h2>
                <p>Bấm dấu cộng để mở danh sách đơn hàng của từng khách.</p>
            </div>
        </div>

        <x-ui.table-wrap>
            <x-ui.table class="tw:[&>thead>tr>th]:sticky tw:[&>thead>tr>th]:top-0 tw:[&>thead>tr>th]:z-[2] tw:[&>thead>tr>th]:border-b tw:[&>thead>tr>th]:[border-bottom-style:solid] tw:[&>thead>tr>th]:border-b-[#e6edf5] tw:[&>thead>tr>th]:text-[#475569] tw:[&>thead>tr>th]:text-[12px] tw:[&>thead>tr>th]:font-black tw:[&>thead>tr>th]:uppercase tw:[&>thead>tr>th]:tracking-[.08em] tw:[&>thead>tr>th]:p-4 tw:[&>thead>tr>th]:[background:linear-gradient(180deg,#f8fbff,#f1f6fc)] tw:[&>tbody>tr>td]:p-4 tw:[&>tbody>tr>td]:border-b tw:[&>tbody>tr>td]:[border-bottom-style:solid] tw:[&>tbody>tr>td]:border-b-[#eff4f8] tw:[&>tbody>tr>td]:align-middle tw:align-middle tw:mb-0">
                <thead>
                    <tr>
                        <th style="width: 56px;"></th>
                        <th>Khách hàng</th>
                        <th class="tw:text-right">Số đơn</th>
                        <th class="tw:text-right">Tổng tiền</th>
                        <th class="tw:text-right">Đã thanh toán</th>
                        <th class="tw:text-right">Còn nợ</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody x-data="{ mo: {} }">
                    @forelse($customers as $customer)
                        <tr class="tw:bg-[#ffffff] tw:[transition:.18s_ease] tw:hover:bg-[#f8fbff]">
                            <td>
                                <button class="tw:w-9 tw:h-9 tw:rounded-[999px] tw:border tw:border-solid tw:border-[#c7d6e5] tw:text-[#475569] tw:flex tw:items-center tw:justify-center tw:[transition:.2s_ease] tw:shadow-[0_4px_10px_rgba(15,23,42,0.06)] tw:[background:linear-gradient(180deg,#ffffff,#f1f6fc)] tw:hover:border-[#93c5fd] tw:hover:text-[#1d4ed8] tw:hover:scale-[1.04] tw:hover:[background:linear-gradient(180deg,#eff6ff,#dbeafe)]"
                                        type="button"
                                        x-on:click="mo['{{ $customer->detailId }}'] = ! mo['{{ $customer->detailId }}']"
                                        x-bind:aria-expanded="mo['{{ $customer->detailId }}'] ? 'true' : 'false'"
                                        aria-expanded="false"
                                        aria-controls="detail-{{ $customer->detailId }}">
                                    <i class="bi bi-plus"
                                       x-bind:class="{ 'bi-dash': mo['{{ $customer->detailId }}'], 'bi-plus': ! mo['{{ $customer->detailId }}'] }"></i>
                                </button>
                            </td>

                            <td>
                                <div class="tw:flex tw:items-center tw:gap-3">
                                    <div class="tw:w-[42px] tw:h-[42px] tw:rounded-[14px] tw:flex tw:items-center tw:justify-center tw:font-black tw:text-[16px] tw:text-[#1d4ed8] tw:uppercase tw:shrink-0 tw:grow-0 tw:basis-auto tw:border tw:border-solid tw:border-[rgba(59,130,246,0.14)] tw:[background:linear-gradient(135deg,rgba(59,130,246,.14),rgba(14,165,233,.16))]">
                                        {{ $customer->avatarInitial }}
                                    </div>
                                    <div>
                                        <div class="tw:text-[16px] tw:font-black tw:text-[#0f172a]">{{ $customer->customerName }}</div>
                                        <div class="tw:text-[12px] tw:text-[#64748b] tw:mt-[2px]">Khách hàng công nợ</div>
                                    </div>
                                </div>
                            </td>

                            <td class="tw:text-right">
                                <span class="tw:inline-flex tw:items-center tw:py-2 tw:px-3 tw:rounded-[999px] tw:text-[12px] tw:font-black tw:leading-none tw:whitespace-nowrap tw:shadow-[inset_0_1px_0_rgba(255,255,255,0.5)] tw:[background:linear-gradient(180deg,#f1f5f9,#e2e8f0)] tw:text-[#334155]">{{ $customer->totalOrdersText }} đơn</span>
                            </td>

                            <td class="tw:text-right tw:font-black tw:whitespace-nowrap tw:text-[15px] tw:text-[#0f172a]">
                                {{ $customer->totalText }}
                            </td>

                            <td class="tw:text-right tw:font-black tw:whitespace-nowrap tw:text-[15px] tw:text-[#059669]">
                                {{ $customer->paidText }}
                            </td>

                            <td class="tw:text-right tw:font-black tw:whitespace-nowrap tw:text-[15px] tw:text-[#ef4444]">
                                {{ $customer->debtText }}
                            </td>

                            <td>
                                <span class="{{ $customer->statusClass }}">
                                    <span class="tw:w-2 tw:h-2 tw:rounded-[999px] tw:inline-block"></span>
                                    {{ $customer->statusText }}
                                </span>
                            </td>
                        </tr>

                        <tr id="detail-{{ $customer->detailId }}" x-show="mo['{{ $customer->detailId }}']" style="display:none;">
                            <td colspan="7" class="tw:p-0">
                                <div class="tw:border-t tw:[border-top-style:solid] tw:border-t-[#e6edf5] tw:pt-[14px] tw:px-[18px] tw:pb-[18px] tw:[background:radial-gradient(600px_200px_at_0%_0%,rgba(37,99,235,.05),transparent_40%),linear-gradient(180deg,#fcfdff,#f6faff)]">
                                    <x-ui.table-wrap>
                                        <x-ui.table class="tw:[&>thead>tr>th]:font-black tw:[&>thead>tr>th]:uppercase tw:[&>thead>tr>th]:tracking-[.08em] tw:[&>thead>tr>th]:bg-transparent tw:[&>thead>tr>th]:static tw:[&>thead>tr>th]:border-b tw:[&>thead>tr>th]:[border-bottom-style:solid] tw:[&>thead>tr>th]:border-b-[#e8eef5] tw:[&>thead>tr>th]:text-[12px] tw:[&>thead>tr>th]:text-[#64748b] tw:[&>thead>tr>th]:p-3 tw:[&>tbody>tr>td]:p-3 tw:[&>tbody>tr>td]:border-b tw:[&>tbody>tr>td]:[border-bottom-style:solid] tw:[&>tbody>tr>td]:border-b-[#edf2f7] tw:align-middle tw:mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Mã đơn</th>
                                                    <th class="tw:text-right">Tổng tiền</th>
                                                    <th class="tw:text-right">Đã thanh toán</th>
                                                    <th class="tw:text-right">Còn nợ</th>
                                                    <th>Trạng thái</th>
                                                    <th>Ngày tạo</th>
                                                    <th class="tw:text-right" style="width:110px;">Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($customer->orders as $order)
                                                    <tr>
                                                        <td>
                                                            <a href="{{ route('orders.show', $order->id) }}"
                                                               class="tw:text-[#2563eb] tw:font-black tw:no-underline tw:hover:text-[#1d4ed8] tw:hover:underline">
                                                                {{ $order->orderCode }}
                                                            </a>
                                                        </td>

                                                        <td class="tw:text-right tw:font-black tw:whitespace-nowrap tw:text-[15px] tw:text-[#0f172a]">
                                                            {{ $order->totalText }}
                                                        </td>

                                                        <td class="tw:text-right tw:font-black tw:whitespace-nowrap tw:text-[15px] tw:text-[#059669]">
                                                            {{ $order->paidText }}
                                                        </td>

                                                        <td class="tw:text-right tw:font-black tw:whitespace-nowrap tw:text-[15px] tw:text-[#ef4444]">
                                                            {{ $order->debtText }}
                                                        </td>

                                                        <td>
                                                            <span class="{{ $order->statusClass }}">
                                                                <span class="tw:w-2 tw:h-2 tw:rounded-[999px] tw:inline-block"></span>
                                                                {{ $order->statusText }}
                                                            </span>
                                                        </td>

                                                        <td>{{ $order->createdAtText }}</td>
                                                        <td class="tw:text-right">
                                                            <form method="POST"
                                                                  action="{{ route('finance.customer-debts.destroy', $order->id) }}"
                                                                  class="tw:inline"
                                                                  x-on:submit="window.confirm(@js($order->deleteConfirmText)) || $event.preventDefault()">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="tw:inline-flex tw:items-center tw:gap-[6px] tw:border tw:border-solid tw:border-[#fecaca] tw:bg-[#fff1f2] tw:text-[#be123c] tw:rounded-[10px] tw:py-[7px] tw:px-[10px] tw:text-[12px] tw:font-extrabold tw:cursor-pointer tw:whitespace-nowrap tw:hover:bg-[#ffe4e6] tw:hover:border-[#fda4af] tw:hover:text-[#9f1239]" title="Xóa khỏi công nợ">
                                                                    <i class="bi bi-trash3"></i> Xóa
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </x-ui.table>
                                    </x-ui.table-wrap>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! tw:py-12">Không có dữ liệu phù hợp bộ lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        </x-ui.table-wrap>

        <div class="tw:pt-[18px] tw:px-[22px] tw:pb-[22px] tw:border-t tw:[border-top-style:solid] tw:border-t-[#eef3f8] tw:bg-[#ffffff]">
            {{ $customers->links() }}
        </div>
    </div>
</div>
@endsection