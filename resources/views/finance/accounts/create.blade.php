@extends('layouts.app')

@section('content')
<div class="container tw:py-6">
    <x-ui.card class="border-0 shadow-lg rounded-4 overflow-hidden">
        <x-ui.card-body class="tw:p-0">
            <div class="tw:p-6 tw:text-[#ffffff]" style="background: linear-gradient(135deg, #0f172a, #1d4ed8);">
                <h3 class="tw:mb-1 tw:font-bold">{{ isset($account) ? 'Cập nhật tài khoản' : 'Tạo quỹ / tài khoản' }}</h3>
                <div class="opacity-75">Thiết lập quỹ tiền mặt, ngân hàng hoặc ví điện tử để quản lý số dư thật</div>
            </div>

            <div class="tw:p-6">
                <form action="{{ isset($account) ? route('finance.accounts.update', $account) : route('finance.accounts.store') }}" method="POST">
                    @csrf
                    @if(isset($account))
                        @method('PUT')
                    @endif

                    <div class="tw:row tw:g-3">
                        <div class="tw:md:col12-6">
                            <x-ui.label>Tên tài khoản</x-ui.label>
                            <x-ui.input type="text" name="name" class="rounded-pill" value="{{ old('name', $account->name ?? '') }}" required />
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Mã tài khoản</x-ui.label>
                            <x-ui.input type="text" name="code" class="rounded-pill" value="{{ old('code', $account->code ?? '') }}" />
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Loại</x-ui.label>
                            <x-ui.select name="type" class="rounded-pill" required>
                                <option value="cash" @selected(old('type', $account->type ?? '') == 'cash')>Tiền mặt</option>
                                <option value="bank" @selected(old('type', $account->type ?? '') == 'bank')>Ngân hàng</option>
                                <option value="ewallet" @selected(old('type', $account->type ?? '') == 'ewallet')>Ví điện tử</option>
                            </x-ui.select>
                        </div>

                        <div class="tw:md:col12-6">
                            <x-ui.label>Số dư ban đầu</x-ui.label>
                            <x-ui.input type="number" step="0.01" min="0" name="opening_balance" class="rounded-pill"
                                   value="{{ old('opening_balance', $account->opening_balance ?? 0) }}"
                                   :disabled="isset($account)" />
                            @if(isset($account))
                                <small class="tw:text-[rgba(33,37,41,0.75)]">Số dư đầu chỉ thiết lập khi tạo mới.</small>
                            @endif
                        </div>

                        <div class="tw:col12-12">
                            <x-ui.label>Ghi chú</x-ui.label>
                            <x-ui.input as="textarea" name="note" rows="4" class="rounded-4">{{ old('note', $account->note ?? '') }}</x-ui.input>
                        </div>

                        <div class="tw:col12-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                       @checked(old('is_active', $account->is_active ?? true))>
                                <label class="form-check-label" for="is_active">
                                    Kích hoạt tài khoản
                                </label>
                            </div>
                        </div>

                        <div class="tw:col12-12 tw:flex tw:gap-2">
                            <x-ui.button variant="primary" type="submit" class="rounded-pill tw:px-6">
                                {{ isset($account) ? 'Cập nhật' : 'Tạo tài khoản' }}
                            </x-ui.button>
                            <x-ui.button href="{{ route('finance.accounts.index') }}" variant="light" class="rounded-pill tw:px-6">
                                Quay lại
                            </x-ui.button>
                        </div>
                    </div>
                </form>

                @if($errors->any())
                    <x-ui.alert variant="danger" class="tw:mt-6 tw:rounded-[1rem]">
                        <ul class="tw:mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-ui.alert>
                @endif
            </div>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection