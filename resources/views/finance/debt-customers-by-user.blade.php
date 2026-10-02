@extends('layouts.app')

@section('title', $debtContext['title'] ?? 'Công nợ khách hàng')

@section('content')
<div class="container-fluid tw:py-6">
    <div class="tw:flex tw:justify-between tw:items-center tw:mb-6 flex-wrap tw:gap-2">
        <div>
            <h2 class="tw:mb-1 tw:font-bold">{{ $debtContext['title'] ?? 'Công nợ theo khách hàng' }} · Tổng hợp</h2>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Tổng hợp doanh số, thanh toán và dư nợ theo từng khách hàng</div>
        </div>

        <div class="tw:flex tw:gap-2 flex-wrap">
            <x-ui.button href="{{ route('finance.customer-debts.index', ['debt_type' => $debtContext['type'] ?? 'construction']) }}" variant="outline-secondary" class="rounded-pill">
                Danh sách công nợ
            </x-ui.button>
            <x-ui.button href="{{ route('finance.customer-debts.payment-history', ['debt_type' => $debtContext['type'] ?? 'construction']) }}" variant="outline-primary" class="rounded-pill">
                Lịch sử thanh toán
            </x-ui.button>
        </div>
    </div>

    <x-ui.card class="border-0 shadow-sm rounded-4">
        <x-ui.card-body>
            <div class="table-responsive">
                <table class="table align-middle tw:mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Khách hàng</th>
                            <th class="tw:text-right">Số đơn</th>
                            <th class="tw:text-right">Tổng tiền</th>
                            <th class="tw:text-right">Đã thanh toán</th>
                            <th class="tw:text-right">Còn nợ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($debts as $item)
                            <tr>
                                <td>{{ $debts->firstItem() + $loop->index }}</td>
                                <td class="tw:font-semibold">{{ $item->customer_name }}</td>
                                <td class="tw:text-right">{{ number_format($item->total_orders) }}</td>
                                <td class="tw:text-right tw:font-semibold">{{ number_format($item->total_amount, 0, ',', '.') }} đ</td>
                                <td class="tw:text-right tw:text-[#198754] tw:font-semibold">{{ number_format($item->paid_amount, 0, ',', '.') }} đ</td>
                                <td class="tw:text-right tw:text-[#dc3545] tw:font-semibold">{{ number_format($item->debt_amount, 0, ',', '.') }} đ</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-6">Chưa có dữ liệu</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="tw:mt-4">
                {{ $debts->links() }}
            </div>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection