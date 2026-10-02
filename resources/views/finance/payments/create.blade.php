@extends('layouts.app')

@section('content')
<div class="container tw:py-6">
    @if(session('success'))
        <x-ui.alert variant="success" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[50rem] tw:px-6">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    @if(session('error'))
        <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[50rem] tw:px-6">
            {{ session('error') }}
        </x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)] tw:rounded-[1rem]">
            <div class="tw:font-semibold tw:mb-2">Vui lòng kiểm tra lại dữ liệu:</div>
            <ul class="tw:mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card class="border-0 shadow-lg rounded-4 overflow-hidden">
        <x-ui.card-body class="tw:p-0">
            <div class="tw:p-6 tw:text-[#ffffff]" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                <div class="tw:flex tw:justify-between tw:items-center flex-wrap tw:gap-4">
                    <div>
                        <h3 class="tw:mb-1 tw:font-bold">Tạo phiếu chi</h3>
                        <div class="opacity-75">Ghi nhận khoản chi và tự động trừ số dư quỹ / tài khoản</div>
                    </div>
                    <x-ui.button href="{{ route('finance.payments.index') }}" variant="light" class="rounded-pill tw:px-6 tw:font-semibold">
                        Quay lại danh sách
                    </x-ui.button>
                </div>
            </div>

            <div class="tw:p-6">
                <form action="{{ route('finance.payments.store') }}" method="POST">
                    @csrf

                    <div class="tw:row tw:g-3">
                        <div class="tw:md:col12-6">
                            <x-ui.label>Quỹ / Tài khoản</x-ui.label>
                            <x-ui.select name="account_id" class="rounded-pill" required>
                                <option value="">-- Chọn tài khoản --</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>
                                        {{ $account->name }} - {{ number_format($account->current_balance, 0, ',', '.') }}đ
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Ngày chi</x-ui.label>
                            <x-ui.input type="date" name="payment_date" class="rounded-pill"
                                   value="{{ old('payment_date', now()->format('Y-m-d')) }}" required />
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Người nhận</x-ui.label>
                            <x-ui.input type="text" name="payee_name" class="rounded-pill"
                                   value="{{ old('payee_name') }}" placeholder="Nhập tên người nhận" />
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Số điện thoại</x-ui.label>
                            <x-ui.input type="text" name="payee_phone" class="rounded-pill"
                                   value="{{ old('payee_phone') }}" placeholder="Nhập số điện thoại" />
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Loại chi</x-ui.label>
                            <x-ui.select name="category" class="rounded-pill" required>
                                <option value="">-- Chọn loại chi --</option>
                                @foreach($categories as $value => $label)
                                    <option value="{{ $value }}" @selected(old('category') == $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Phương thức</x-ui.label>
                            <x-ui.select name="payment_method" class="rounded-pill" required>
                                <option value="">-- Chọn phương thức --</option>
                                @foreach($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method') == $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Số tiền</x-ui.label>
                            <x-ui.input type="number" step="0.01" min="0.01" name="amount"
                                   class="rounded-pill"
                                   value="{{ old('amount') }}"
                                   placeholder="Nhập số tiền" required />
                        </div>

                        <div class="tw:col12-12">
                            <x-ui.label>Ghi chú</x-ui.label>
                            <x-ui.input as="textarea" name="note" rows="4" class="rounded-4" placeholder="Nội dung chi...">{{ old('note') }}</x-ui.input>
                        </div>

                        <div class="tw:col12-12 tw:flex tw:gap-2 flex-wrap">
                            <x-ui.button variant="danger" type="submit" class="rounded-pill tw:px-6 tw:font-semibold">
                                Lưu phiếu chi
                            </x-ui.button>
                            <x-ui.button href="{{ route('finance.payments.index') }}" variant="light" class="rounded-pill tw:px-6">
                                Hủy
                            </x-ui.button>
                        </div>
                    </div>
                </form>
            </div>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection