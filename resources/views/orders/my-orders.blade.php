{{-- Hai con số badge (đơn chờ duyệt, phiếu vật tư chờ duyệt) do
     App\Services\System\SidebarStatusService cấp cho partials.sidebar qua view
     composer. Trước đây đúng chỗ này có một khối 16 dòng CHÉP QUA 9 VIEW tự chạy
     lại hai câu COUNT rồi nuốt lỗi bằng catch(Throwable). Giá trị nó tính ra bị
     composer ghi đè nên không hiển thị ở đâu — chỉ tốn 2 câu truy vấn mỗi lần
     dựng trang. --}}


@extends('layouts.app')
@section('title', 'Dashboard đơn hàng của tôi')
@section('content')
    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    <div class="tw:w-full tw:mx-auto tw:px-6 tw:py-4">
        <div class="tw:flex tw:justify-between tw:items-center tw:mb-4">
            <h1 class="tw:font-bold tw:uppercase tw:text-[#6c757d]">DASHBOARD ĐƠN HÀNG CỦA TÔI</h1>
            <x-ui.button href="{{ route('orders.create') }}" variant="primary">
                <i class="bi bi-plus-lg"></i> Tạo đơn mới
            </x-ui.button>
        </div>
        {{-- Thống kê tổng quan --}}
        <div class="tw:row tw:mb-6">
            <div class="tw:md:col12-3">
                <x-ui.card class="tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:[border-left:4px_solid_#0d6efd]">
                    <x-ui.card-body>
                        <div class="tw:flex tw:justify-between tw:items-center">
                            <div>
                                <p class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:mb-1">Tổng đơn hàng</p>
                                <h3 class="tw:mb-0 tw:font-bold">{{ $statistics['total_orders'] }}</h3>
                            </div>
                            <div class="tw:text-[#0d6efd] tw:text-[40px]/[60px]">
                                <i class="bi bi-cart-check"></i>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
            <div class="tw:md:col12-3">
                <x-ui.card class="tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:[border-left:4px_solid_#ffc107]">
                    <x-ui.card-body>
                        <div class="tw:flex tw:justify-between tw:items-center">
                            <div>
                                <p class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:mb-1">Đang xử lý</p>
                                <h3 class="tw:mb-0 tw:font-bold tw:text-[#ffc107]">{{ $statistics['pending'] }}</h3>
                            </div>
                            <div class="tw:text-[#ffc107] tw:text-[40px]/[60px]">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
            <div class="tw:md:col12-3">
                <x-ui.card class="tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:[border-left:4px_solid_#198754]">
                    <x-ui.card-body>
                        <div class="tw:flex tw:justify-between tw:items-center">
                            <div>
                                <p class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:mb-1">Hoàn thành</p>
                                <h3 class="tw:mb-0 tw:font-bold tw:text-[#198754]">{{ $statistics['completed'] }}</h3>
                            </div>
                            <div class="tw:text-[#198754] tw:text-[40px]/[60px]">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
            <div class="tw:md:col12-3">
                <x-ui.card class="tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:[border-left:4px_solid_#0dcaf0]">
                    <x-ui.card-body>
                        <div class="tw:flex tw:justify-between tw:items-center">
                            <div>
                                <p class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em] tw:mb-1">Doanh thu</p>
                                <h3 class="tw:mb-0 tw:font-bold tw:text-[#0dcaf0]">
                                    {{ number_format($statistics['total_revenue'], 0, ',', '.') }}đ
                                </h3>
                            </div>
                            <div class="tw:text-[#0dcaf0] tw:text-[40px]/[60px]">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
        </div>
        <div class="tw:row">
            {{-- Đơn hàng gần đây --}}
            <div class="tw:md:col12-8">
                <x-ui.card class="tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
                    <x-ui.card-header class="tw:bg-[#0d6efd] tw:text-[#ffffff] tw:flex tw:justify-between">
                        <h5 class="tw:mb-0"><i class="bi bi-clock-history"></i> Đơn hàng gần đây</h5>
                        <a href="{{ route('orders.index') }}" class="tw:text-[#ffffff] tw:no-underline">
                            Xem tất cả <i class="bi bi-arrow-right"></i>
                        </a>
                    </x-ui.card-header>
                    <x-ui.card-body class="tw:p-0">
                        <x-ui.table-wrap>
                            <x-ui.table hover class="tw:mb-0">
                                <x-ui.table-head>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Bộ phận</th>
                                    <th></th>
                                </tr>
                                </x-ui.table-head>
                                <tbody>
                                @forelse($orderRows as $order)
                                    <tr>
                                        <td>
                                            <a href="{{ route('orders.show', $order->id) }}" class="tw:no-underline tw:font-semibold">
                                                {{ $order->orderCode }}
                                            </a>
                                        </td>
                                        <td>
                                            <div>{{ $order->customerName }}</div>
                                            <small class="tw:text-[rgba(33,37,41,0.75)]">{{ $order->customerPhone }}</small>
                                        </td>
                                        <td class="tw:font-bold">{{ $order->totalText }}đ</td>
                                        <td>
                                        <span class="tw:inline-block tw:py-[0.35em] tw:px-[0.65em] tw:text-[0.75em] tw:font-bold tw:leading-none tw:text-center tw:align-baseline tw:whitespace-nowrap tw:rounded-[.375rem] tw:text-[#ffffff]" style="background-color: {{ $order->statusColor }}">
                                            {{ $order->statusName }}
                                        </span>
                                        </td>
                                        <td>
                                            <span class="tw:inline-block tw:py-[0.35em] tw:px-[0.65em] tw:text-[0.75em] tw:font-bold tw:leading-none tw:text-center tw:align-baseline tw:whitespace-nowrap tw:rounded-[.375rem] tw:text-[#ffffff] {{ $order->departmentBadge }}">
                                            {{ $order->departmentLabel }}
                                        </span>
                                        </td>
                                        <td>
                                            <x-ui.button href="{{ route('orders.show', $order->id) }}" variant="outline-primary" size="sm">
                                                <i class="bi bi-eye"></i>
                                            </x-ui.button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-4">
                                            Chưa có đơn hàng nào
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </x-ui.table>
                        </x-ui.table-wrap>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
            {{-- Thông báo --}}
            <div class="tw:md:col12-4">
                <x-ui.card class="tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
                    <x-ui.card-header class="tw:bg-[#ffc107] tw:flex tw:justify-between">
                        <h5 class="tw:mb-0"><i class="bi bi-bell"></i> Thông báo mới</h5>
                        @if($pendingNotifications->count() > 0)
                            <span class="tw:inline-block tw:py-[0.35em] tw:px-[0.65em] tw:text-[0.75em] tw:font-bold tw:leading-none tw:text-center tw:align-baseline tw:whitespace-nowrap tw:rounded-[.375rem] tw:text-[#ffffff] tw:bg-[#dc3545]">{{ $pendingNotifications->count() }}</span>
                        @endif
                    </x-ui.card-header>
                    <x-ui.card-body style="max-height: 500px; overflow-y: auto;">
                        @forelse($pendingNotifications as $notif)
                            {{-- `alert-dismissible` ở đây KHÔNG kèm nút đóng nào — nó chỉ còn tác dụng
                                 chừa đệm phải 3rem cho một nút không tồn tại. Giữ nguyên bằng
                                 `tw:pr-12` để giao diện không đổi; bỏ đệm thừa là quyết định riêng. --}}
                            <x-ui.alert :variant="$notif->is_read ? 'secondary' : 'info'" class="tw:pr-12 tw:mb-2">
                                <small class="tw:text-[rgba(33,37,41,0.75)] tw:block tw:mb-1">
                                    <i class="bi bi-clock"></i> {{ $notif->created_at->diffForHumans() }}
                                </small>
                                <h6 class="tw:mb-1">{{ $notif->title }}</h6>
                                <p class="tw:mb-2 tw:text-[0.875em]">{{ $notif->message }}</p>
                                <div class="tw:flex tw:gap-2">
                                    <x-ui.button href="{{ route('orders.show', $notif->order_id) }}" variant="primary" size="sm">
                                        Xem đơn
                                    </x-ui.button>
                                    @if(!$notif->is_read)
                                        <form action="{{ route('notifications.mark-read', $notif->id) }}" method="POST" class="tw:inline">
                                            @csrf
                                            <x-ui.button variant="outline-secondary" size="sm" type="submit">
                                                Đánh dấu đã đọc
                                            </x-ui.button>
                                        </form>
                                    @endif
                                </div>
                            </x-ui.alert>
                        @empty
                            <div class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-6">
                                <i class="bi bi-bell-slash tw:text-[40px]/[60px]"></i>
                                <p class="tw:mt-2">Không có thông báo mới</p>
                            </div>
                        @endforelse
                    </x-ui.card-body>
                    @if($pendingNotifications->count() > 0)
                        <x-ui.card-footer class="tw:text-center">
                            <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                                @csrf
                                <x-ui.button variant="link" size="sm" type="submit">
                                    Đánh dấu tất cả đã đọc
                                </x-ui.button>
                            </form>
                        </x-ui.card-footer>
                    @endif
                </x-ui.card>
            </div>
        </div>
        {{-- Biểu đồ tiến độ --}}
        <div class="tw:row tw:mt-6">
            <div class="tw:col12-12">
                <x-ui.card class="tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
                    <x-ui.card-header class="tw:bg-[#0dcaf0] tw:text-[#ffffff]">
                        <h5 class="tw:mb-0"><i class="bi bi-bar-chart"></i> Tiến độ xử lý đơn hàng</h5>
                    </x-ui.card-header>
                    <x-ui.card-body>
                        <div class="tw:flex tw:overflow-hidden tw:text-[12px] tw:bg-[#e9ecef] tw:rounded-[.375rem]" style="height: 30px;">
                            <div class="tw:flex tw:flex-col tw:justify-center tw:overflow-hidden tw:text-center tw:whitespace-nowrap tw:text-[#ffffff] tw:[transition:width_.6s_ease] tw:bg-[#198754]" role="progressbar"
                                 style="width: {{ $completedPercent }}%"
                                 aria-valuenow="{{ $completedPercent }}" aria-valuemin="0" aria-valuemax="100">
                                Hoàn thành: {{ $completedPercent }}%
                            </div>
                            <div class="tw:flex tw:flex-col tw:justify-center tw:overflow-hidden tw:text-center tw:whitespace-nowrap tw:text-[#ffffff] tw:[transition:width_.6s_ease] tw:bg-[#ffc107]" role="progressbar"
                                 style="width: {{ $pendingPercent }}%"
                                 aria-valuenow="{{ $pendingPercent }}" aria-valuemin="0" aria-valuemax="100">
                                Đang xử lý: {{ $pendingPercent }}%
                            </div>
                        </div>
                        <div class="tw:mt-4 tw:text-center">
                            <p class="tw:mb-0 tw:text-[rgba(33,37,41,0.75)]">
                                Tỷ lệ hoàn thành: <strong class="tw:text-[#198754]">{{ $completedPercent }}%</strong> |
                                Đang xử lý: <strong class="tw:text-[#ffc107]">{{ $pendingPercent }}%</strong>
                            </p>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection

