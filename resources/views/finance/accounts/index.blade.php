@extends('layouts.app')

@section('content')
{{-- `.container-fluid` quy đổi theo GIÁ TRỊ: width 100% + đệm calc(1.5rem*.5)=12px + margin auto. --}}
<div class="tw:w-full tw:px-3 tw:mx-auto tw:py-6">
    @if(session('success'))
        <x-ui.alert variant="success" class="tw:[border:0] tw:[box-shadow:0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[50rem] tw:px-6">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" class="tw:[border:0] tw:[box-shadow:0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[50rem] tw:px-6">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    <x-ui.card class="tw:[border:0] tw:[box-shadow:0_1rem_3rem_rgba(0,0,0,0.175)] tw:rounded-[1rem] tw:overflow-hidden tw:mb-6">
        <x-ui.card-body class="tw:p-6 tw:text-[#ffffff] tw:[background:linear-gradient(135deg,#0ea5e9,#2563eb)]">
            <div class="tw:flex tw:flex-wrap tw:justify-between tw:items-center tw:gap-4">
                <div>
                    <h3 class="tw:mb-1 tw:font-bold">Quỹ & Tài Khoản</h3>
                    <div class="tw:opacity-75">Quản lý tiền mặt, ngân hàng, ví điện tử và số dư thực tế</div>
                </div>
                <x-ui.button href="{{ route('finance.accounts.create') }}" variant="light" class="tw:rounded-[50rem]! tw:px-6 tw:font-semibold">
                    + Tạo tài khoản
                </x-ui.button>
            </div>
        </x-ui.card-body>
    </x-ui.card>

    <div class="tw:row tw:g-3 tw:mb-6">
        <div class="tw:md:col12-3">
            <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem] tw:h-full">
                <x-ui.card-body>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Tổng tài khoản</div>
                    <div class="tw:text-[calc(1.275rem+0.3vw)] tw:min-[75rem]:text-[1.5rem] tw:font-bold">{{ $statsCards->totalAccountsText }}</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>
        <div class="tw:md:col12-3">
            <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem] tw:h-full">
                <x-ui.card-body>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Đang hoạt động</div>
                    <div class="tw:text-[calc(1.275rem+0.3vw)] tw:min-[75rem]:text-[1.5rem] tw:font-bold tw:text-[#198754]">{{ $statsCards->activeAccountsText }}</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>
        <div class="tw:md:col12-3">
            <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem] tw:h-full">
                <x-ui.card-body>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Tổng số dư</div>
                    <div class="tw:text-[calc(1.275rem+0.3vw)] tw:min-[75rem]:text-[1.5rem] tw:font-bold tw:text-[#0d6efd]">{{ $statsCards->totalBalanceText }}</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>
        <div class="tw:md:col12-3">
            <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem] tw:h-full">
                <x-ui.card-body>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">Tiền mặt</div>
                    <div class="tw:text-[calc(1.275rem+0.3vw)] tw:min-[75rem]:text-[1.5rem] tw:font-bold tw:text-[#0dcaf0]">{{ $statsCards->cashBalanceText }}</div>
                </x-ui.card-body>
            </x-ui.card>
        </div>
    </div>

    <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem] tw:mb-6">
        <x-ui.card-body>
            <form method="GET" class="tw:row tw:g-3">
                <div class="tw:md:col12-5">
                    <x-ui.input type="text" name="keyword" value="{{ request('keyword') }}" class="tw:rounded-[50rem]!" placeholder="Tìm theo tên, mã, ghi chú..." />
                </div>
                <div class="tw:md:col12-3">
                    <x-ui.select name="type" class="tw:rounded-[50rem]!">
                        <option value="">-- Loại tài khoản --</option>
                        <option value="cash" @selected(request('type') == 'cash')>Tiền mặt</option>
                        <option value="bank" @selected(request('type') == 'bank')>Ngân hàng</option>
                        <option value="ewallet" @selected(request('type') == 'ewallet')>Ví điện tử</option>
                    </x-ui.select>
                </div>
                <div class="tw:md:col12-2">
                    <x-ui.select name="status" class="tw:rounded-[50rem]!">
                        <option value="">-- Trạng thái --</option>
                        <option value="active" @selected(request('status') == 'active')>Hoạt động</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>Ngưng</option>
                    </x-ui.select>
                </div>
                <div class="tw:md:col12-2 tw:grid">
                    <x-ui.button variant="primary" type="submit" class="tw:rounded-[50rem]!">Lọc</x-ui.button>
                </div>
            </form>
        </x-ui.card-body>
    </x-ui.card>

    <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)] tw:rounded-[1rem]">
        <x-ui.card-body class="tw:p-0">
            @if($accounts->count())
                <x-ui.table-wrap>
                    <x-ui.table class="tw:align-middle tw:mb-0">
                        {{-- Dùng `<thead>` trơn, KHÔNG dùng `<x-ui.table-head>`: component đó tái hiện `.table-light`
                             (gán lại BIẾN nền/viền/màu chữ của Ô), còn bản cũ chỉ đặt `bg-light` trên chính thẻ
                             `<thead>` — mà nền ô của `.table` phủ lên nên ô vẫn TRẮNG. Đo được: đổi sang
                             component làm 228 ô lệch (nền ô, màu viền, màu chữ). Giữ y bản cũ. --}}
                        <thead class="tw:bg-[rgb(248,249,250)]">
                            <tr>
                                <th class="tw:px-6 tw:py-4">Tên tài khoản</th>
                                <th class="tw:py-4">Mã</th>
                                <th class="tw:py-4">Loại</th>
                                <th class="tw:py-4">Số dư đầu</th>
                                <th class="tw:py-4">Số dư hiện tại</th>
                                <th class="tw:py-4">Trạng thái</th>
                                <th class="tw:text-right tw:px-6 tw:py-4">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accountRows as $row)
                                <tr>
                                    <td class="tw:px-6">
                                        <div class="tw:font-semibold">{{ $row->name }}</div>
                                        @if($row->note)
                                            <div class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">{{ $row->note }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $row->codeText }}</td>
                                    <td>{{ $row->typeLabel }}</td>
                                    <td>{{ $row->openingText }}</td>
                                    <td class="tw:font-bold tw:text-[#0d6efd]">{{ $row->currentText }}</td>
                                    <td>
                                        @if($row->isActive)
                                            <x-finance.pill tone="success" class="tw:text-[#198754] tw:px-4 tw:rounded-[50rem]">Hoạt động</x-finance.pill>
                                        @else
                                            <x-finance.pill class="tw:text-[#6c757d] tw:px-4 tw:rounded-[50rem]">Ngưng</x-finance.pill>
                                        @endif
                                    </td>
                                    <td class="tw:text-right tw:px-6">
                                        

                                        <form action="{{ route('finance.accounts.destroy', $row->id) }}" method="POST" class="tw:inline-block" onsubmit="return confirm('Xóa tài khoản này?')">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button variant="danger" size="sm" type="submit" class="tw:rounded-[50rem]! tw:px-4">Xóa</x-ui.button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-ui.table>
                </x-ui.table-wrap>

                <div class="tw:p-4">
                    {{ $accounts->links() }}
                </div>
            @else
                <div class="tw:text-center tw:py-12">
                    <div class="tw:mb-2 tw:font-semibold">Chưa có quỹ / tài khoản nào</div>
                    <div class="tw:text-[rgba(33,37,41,0.75)] tw:mb-4">Bắt đầu bằng cách tạo quỹ tiền mặt hoặc tài khoản ngân hàng đầu tiên.</div>
                    <x-ui.button href="{{ route('finance.accounts.create') }}" variant="primary" class="tw:rounded-[50rem]! tw:px-6">
                        + Tạo ngay
                    </x-ui.button>
                </div>
            @endif
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection