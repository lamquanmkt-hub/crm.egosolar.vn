@extends('layouts.app')
@section('title', 'Thêm phương thức thanh toán')
@section('content')
    <div class="container-fluid tw:px-6 tw:mt-6">
        <div class="tw:row tw:justify-center">
            <div class="tw:md:col12-8">
                <x-ui.card class="shadow-sm">
                    <x-ui.card-header class="bg-primary tw:text-[#ffffff]">
                        <h5 class="tw:mb-0"><i class="bi bi-plus-circle"></i> Thêm phương thức mới</h5>
                    </x-ui.card-header>
                    <x-ui.card-body>
                        <form action="{{ route('payment-methods.store') }}" method="POST">
                            @csrf

                            {{-- Include Form Partial --}}
                            @include('payment_methods._form')

                            <div class="tw:flex tw:justify-end tw:gap-2 tw:mt-6">
                                <x-ui.button href="{{ route('payment-methods.index') }}" variant="secondary">Quay lại</x-ui.button>
                                <x-ui.button variant="primary" type="submit"><i class="bi bi-save"></i> Lưu lại</x-ui.button>
                            </div>
                        </form>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection