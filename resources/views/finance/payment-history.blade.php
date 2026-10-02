@extends('layouts.app')

@section('title', $debtContext['title'] ?? 'Công nợ khách hàng')

@section('content')
<div class="container-fluid tw:py-6">
    <div class="tw:flex tw:justify-between tw:items-center tw:mb-6 flex-wrap tw:gap-2">
        <div>
            <h2 class="tw:mb-1 tw:font-bold">Lịch sử thanh toán · {{ $debtContext['title'] ?? 'Công nợ khách hàng' }}</h2>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Theo dõi trạng thái ghi nhận thanh toán của đơn hàng</div>
        </div>

        <div class="tw:flex tw:gap-2 flex-wrap">
            <x-ui.button href="{{ route('finance.customer-debts.index', ['debt_type' => $debtContext['type'] ?? 'construction']) }}" variant="outline-secondary" class="rounded-pill">
                Danh sách công nợ
            </x-ui.button>
            <x-ui.button href="{{ route('finance.customer-debts.by-customer', ['debt_type' => $debtContext['type'] ?? 'construction']) }}" variant="outline-primary" class="rounded-pill">
                Theo khách hàng
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
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th class="tw:text-right">Tổng tiền</th>
                            <th class="tw:text-right">Đã thanh toán</th>
                            <th class="tw:text-right">Còn nợ</th>
                            <th>Trạng thái</th>
                            <th>Ngày đơn</th>
                            <th>Cập nhật</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>{{ $orders->firstItem() + $loop->index }}</td>
                                <td class="tw:font-semibold">{{ $order->order_code ?? ('#' . $order->id) }}</td>
                                <td>{{ $order->finance_customer_name }}</td>
                                <td class="tw:text-right tw:font-semibold">{{ number_format($order->finance_total_amount, 0, ',', '.') }} đ</td>
                                <td class="tw:text-right tw:text-[#198754] tw:font-semibold">{{ number_format($order->finance_paid_amount, 0, ',', '.') }} đ</td>
                                <td class="tw:text-right tw:text-[#dc3545] tw:font-semibold">{{ number_format($order->finance_debt_amount, 0, ',', '.') }} đ</td>
                                <td>
                                    @if(($order->payment_recorded ?? 0) == 1)
                                        <span class="badge bg-success-subtle tw:text-[#198754] rounded-pill tw:px-4 tw:py-2">Đã ghi nhận</span>
                                    @else
                                        <span class="badge bg-warning-subtle tw:text-[#ffc107] rounded-pill tw:px-4 tw:py-2">Chưa ghi nhận</span>
                                    @endif
                                </td>
                                <td>{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') : optional($order->created_at)->format('d/m/Y') }}</td>
                                <td>{{ optional($order->updated_at)->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-6">Chưa có dữ liệu</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="tw:mt-4">
                {{ $orders->links() }}
            </div>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection