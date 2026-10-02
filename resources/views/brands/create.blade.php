@extends('layouts.app')

@section('title', 'Thêm Brand')

@section('content')
    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    <div class="container-fluid tw:px-6 tw:py-4">

        <div class="tw:flex tw:justify-between tw:items-center tw:mb-4">
            <h1 class="tw:font-bold tw:uppercase tw:text-[#6c757d] tw:mb-0">THÊM BRAND</h1>
            <x-ui.button href="{{ route('brands.index') }}" variant="outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </x-ui.button>
        </div>

        <x-ui.card class="shadow-sm">
            <x-ui.card-body>
                <form method="POST" action="{{ route('brands.store') }}">
                    @include('brands._form', ['brand' => null, 'buttonText' => 'Tạo brand'])
                </form>
            </x-ui.card-body>
        </x-ui.card>

    </div>
@endsection
