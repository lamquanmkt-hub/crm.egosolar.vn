@extends('layouts.app')

@section('content')
{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="container tw:py-4">
    <div class="tw:flex tw:items-center tw:justify-between tw:mb-4">
        <h4 class="tw:mb-0">Tạo yêu cầu Đổi/Trả - Đơn #{{ $order->code ?? $order->id }}</h4>
        <x-ui.button href="{{ route('orders.show', $order->id) }}" variant="light">Quay lại</x-ui.button>
    </div>

    @if ($errors->any())
        <x-ui.alert variant="danger">
            <div><b>Có lỗi:</b></div>
            <ul class="tw:mb-0">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card>
        <x-ui.card-body>
            <form method="POST" action="{{ route('orders.returns.store', $order->id) }}">
                @csrf

                <div class="tw:mb-4">
                    <x-ui.label>Loại yêu cầu</x-ui.label>
                    <x-ui.select name="type" required>
                        <option value="exchange" @selected(old('type')=='exchange')>Đổi hàng</option>
                        <option value="return" @selected(old('type')=='return')>Trả hàng</option>
                    </x-ui.select>
                </div>

                <div class="tw:mb-4">
                    <x-ui.label>Lý do</x-ui.label>
                    <x-ui.input as="textarea" name="reason" rows="5" required>{{ old('reason') }}</x-ui.input>
                    <div class="form-text">Mô tả tình trạng hàng, lỗi, thiếu phụ kiện, v.v...</div>
                </div>

                <x-ui.button variant="warning" type="submit" class="tw:w-full">
                    Gửi yêu cầu Đổi/Trả
                </x-ui.button>
            </form>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection
