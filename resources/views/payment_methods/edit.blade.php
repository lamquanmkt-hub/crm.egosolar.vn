@extends('layouts.app')
@section('title', 'Chỉnh sửa phương thức thanh toán')
@section('content')
    <div class="container-fluid tw:px-6 tw:mt-6">
        <div class="tw:row tw:justify-center">
            <div class="tw:md:col12-8">
                <x-ui.card class="shadow-sm">
                    <x-ui.card-header class="bg-warning tw:text-[#212529]">
                        <h5 class="tw:mb-0"><i class="bi bi-pencil-square"></i> Chỉnh sửa phương thức</h5>
                    </x-ui.card-header>
                    <x-ui.card-body>
                        <form action="{{ route('payment-methods.update', $method->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            {{-- Include Form Partial --}}
                            @include('payment_methods._form', ['method' => $method])

                            <div class="tw:flex tw:justify-end tw:gap-2 tw:mt-6">
                                <x-ui.button href="{{ route('payment-methods.index') }}" variant="secondary">Quay lại</x-ui.button>
                                <x-ui.button variant="primary" type="submit"><i class="bi bi-save"></i> Cập nhật</x-ui.button>
                            </div>
                        </form>
                    </x-ui.card-body>
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection