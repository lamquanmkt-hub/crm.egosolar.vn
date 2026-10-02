@extends('layouts.app')

@section('title', 'Quản lý phương thức thanh toán')

@section('content')
    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    {{-- `.container-fluid` = width 100% + đệm calc(1.5rem*.5)=12px + margin auto; trang này đã có
         `tw:px-6` (24px) nên đệm 12px của container vốn đã bị đè — giữ nguyên thứ tự để không đổi. --}}
    <div class="tw:w-full tw:mx-auto tw:px-6 tw:py-4">
        <div class="tw:flex tw:justify-between tw:items-center tw:mb-6 tw:mt-6">
            <h2 class="tw:font-bold tw:text-[#6c757d]">PHƯƠNG THỨC THANH TOÁN</h2>
            <x-ui.button href="{{ route('payment-methods.create') }}" variant="primary">
                <i class="bi bi-plus-lg"></i> Thêm mới
            </x-ui.button>
        </div>

        {{-- Thông báo thành công --}}
        @if(session('success'))
            <x-ui.alert variant="success" :dismissible="true">
                {{ session('success') }}
            </x-ui.alert>
        @endif

        <x-ui.card class="tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)]">
            <x-ui.card-header class="tw:bg-white tw:py-4">
                <form action="{{ route('payment-methods.index') }}" method="GET" class="tw:row tw:g-3">
                    <div class="tw:md:col12-4">
                        {{-- `.input-group` GIỮ NGUYÊN: nó là MÓC của chính CSS repo — `resources/css/app.css:152`
                             khai `.input-group > input:not(.form-control){position:relative;flex:1 1 auto;width:1%;
                             min-width:0}` để ô `<x-ui.input>` (không còn `.form-control`) vẫn co giãn đúng; cộng
                             luật con của Bootstrap bỏ bo góc giữa hai phần tử. Thay bằng utility làm ô nở 363→405px
                             và nút rớt xuống dòng (đo được 116 ô lệch). Cùng nhóm "cấm đụng" với `.table-responsive`. --}}
                        <div class="input-group">
                            <x-ui.input type="text" name="keyword" placeholder="Tìm kiếm tên..." value="{{ request('keyword') }}" />
                            {{-- Nút này là con của `.input-group`: Bootstrap cho nó position/z-index qua
                             `.input-group > .btn`. Bỏ `.btn` là mất, viền chồng bị đè. --}}
                        <x-ui.button variant="outline-secondary" type="submit" class="tw:relative tw:z-[2]"><i class="bi bi-search"></i></x-ui.button>
                        </div>
                    </div>
                </form>
            </x-ui.card-header>
            <x-ui.card-body class="tw:p-0">
                <x-ui.table-wrap>
                    <x-ui.table hover class="tw:align-middle tw:mb-0">
                        <x-ui.table-head>
                        <tr>
                            <th class="tw:pl-6!">Tên phương thức</th>
                            <th>Mã Code</th>
                            <th>Mô tả</th>
                            <th>Trạng thái</th>
                            <th class="tw:text-right tw:pr-6!">Hành động</th>
                        </tr>
                        </x-ui.table-head>
                        <tbody>
                        @forelse($methodRows as $row)
                            <tr>
                                <td class="tw:pl-6! tw:font-bold">{{ $row->methodName }}</td>
                                <td><span class="tw:inline-block tw:px-[0.65em] tw:py-[0.35em] tw:text-[0.75em] tw:font-bold tw:leading-none tw:text-center tw:whitespace-nowrap tw:align-baseline tw:rounded-[0.375rem] tw:bg-[rgb(108,117,125)] tw:text-white tw:[font-family:SFMono-Regular,Menlo,Monaco,Consolas,'Liberation_Mono','Courier_New',monospace]">{{ $row->code }}</span></td>
                                <td class="tw:text-[rgba(33,37,41,0.75)] tw:text-[0.875em]">{{ $row->descriptionText }}</td>
                                <td>
                                    @if($row->isActive)
                                        <x-finance.pill tone="success" class="tw:px-[0.65em] tw:text-[#198754] tw:[border:1px_solid_rgb(25,135,84)] tw:rounded-[0.375rem]">Hoạt động</x-finance.pill>
                                    @else
                                        <x-finance.pill tone="danger" class="tw:px-[0.65em] tw:text-[#dc3545] tw:[border:1px_solid_rgb(220,53,69)] tw:rounded-[0.375rem]">Tạm khóa</x-finance.pill>
                                    @endif
                                </td>
                                <td class="tw:text-right tw:pr-6!">
                                    <x-ui.button href="{{ route('payment-methods.edit', $row->id) }}" variant="outline-primary" size="sm" class="tw:mr-1">
                                        <i class="bi bi-pencil"></i>
                                    </x-ui.button>
                                    <form action="{{ route('payment-methods.destroy', $row->id) }}" method="POST" class="tw:inline-block" onsubmit="return confirm('Bạn có chắc chắn muốn xóa phương thức này?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="outline-danger" size="sm" type="submit">
                                            <i class="bi bi-trash"></i>
                                        </x-ui.button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="tw:text-center tw:py-12 tw:text-[rgba(33,37,41,0.75)]">
                                    <i class="bi bi-inbox tw:text-[calc(1.375rem+1.5vw)] tw:min-[75rem]:text-[2.5rem] tw:block tw:mb-2"></i>
                                    Chưa có phương thức thanh toán nào.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </x-ui.table>
                </x-ui.table-wrap>
            </x-ui.card-body>
            <x-ui.card-footer class="tw:bg-white">
                {{ $methods->withQueryString()->links() }}
            </x-ui.card-footer>
        </x-ui.card>
    </div>
@endsection