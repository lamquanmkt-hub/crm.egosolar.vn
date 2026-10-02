@extends('layouts.app')

@section('title', 'Tạo phiếu thu')

@section('content')
<div class="container tw:py-6" style="max-width: 900px">
    <x-ui.card class="shadow-sm border-0 rounded-4">
        <x-ui.card-body class="tw:p-6">

            <h4 class="tw:font-bold tw:mb-4">💰 Tạo phiếu thu</h4>
            <div class="tw:text-[rgba(33,37,41,0.75)] tw:mb-6">
                Nhập thông tin để tạo phiếu thu mới
            </div>

            @if ($errors->any())
                <x-ui.alert variant="danger" class="tw:rounded-[.5rem]">
                    <ul class="tw:mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-ui.alert>
            @endif

            <form method="POST" action="{{ route('finance.receipts.store') }}">
                @csrf

                <div class="tw:row tw:g-3">

                    <div class="tw:md:col12-4">
                        <x-ui.label class="tw:font-semibold">Ngày thu</x-ui.label>
                        <x-ui.input type="date" name="receipt_date" class="rounded-pill"
                               value="{{ old('receipt_date', now()->format('Y-m-d')) }}" required />
                    </div>

                    <div class="tw:md:col12-8">
                        <x-ui.label class="tw:font-semibold">Người nộp</x-ui.label>
                        <x-ui.input type="text" name="payer_name" class="rounded-pill"
                               placeholder="VD: Nguyễn Văn A" value="{{ old('payer_name') }}" required />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label class="tw:font-semibold">SĐT</x-ui.label>
                        <x-ui.input type="text" name="payer_phone" class="rounded-pill"
                               value="{{ old('payer_phone') }}" />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label class="tw:font-semibold">Loại thu</x-ui.label>
                        <x-ui.select name="category" class="rounded-pill">
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label class="tw:font-semibold">Phương thức</x-ui.label>
                        <x-ui.select name="payment_method" class="rounded-pill">
                            @foreach($paymentMethods as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label class="tw:font-semibold">Số tiền</x-ui.label>
                        <x-ui.input type="number" name="amount" class="rounded-pill"
                               placeholder="VD: 5000000" required />
                    </div>

                    <div class="tw:md:col12-12">
                        <x-ui.label class="tw:font-semibold">Ghi chú</x-ui.label>
                        <x-ui.input type="text" name="note" class="rounded-pill" />
                    </div>

                    <div class="tw:col12-12 tw:flex tw:justify-end tw:gap-2 tw:mt-4">
                        <x-ui.button href="{{ route('finance.receipts.index') }}" variant="light" class="rounded-pill tw:px-6">
                            Quay lại
                        </x-ui.button>
                        <x-ui.button variant="primary" type="submit" class="rounded-pill tw:px-6">
                            Lưu phiếu
                        </x-ui.button>
                    </div>

                </div>
            </form>

        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection