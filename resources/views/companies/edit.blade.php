@extends('layouts.app')

@section('content')
<div class="container-fluid tw:py-6">
    <div class="tw:flex tw:items-center tw:justify-between tw:mb-4">
        <div>
            <h4 class="tw:mb-1">Sửa thông tin công ty</h4>
            <div class="tw:text-[rgba(33,37,41,0.75)]">{{ $company->name }}</div>
        </div>

        <x-ui.button href="{{ route('companies.index') }}" variant="light">
            Quay lại
        </x-ui.button>
    </div>

    @if($errors->any())
        <x-ui.alert variant="danger">
            <div class="tw:font-semibold tw:mb-1">Có lỗi xảy ra:</div>
            <ul class="tw:mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('companies.update', $company) }}">
        @csrf
        @method('PUT')

        <x-ui.card class="shadow-sm border-0">
            <x-ui.card-header class="bg-white tw:font-semibold">
                Thông tin pháp lý
            </x-ui.card-header>

            <x-ui.card-body>
                <div class="tw:row tw:g-3">
                    <div class="tw:md:col12-8">
                        <x-ui.label>Tên công ty</x-ui.label>
                        <x-ui.input type="text" name="name" value="{{ old('name', $company->name) }}" required />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Mã công ty</x-ui.label>
                        <x-ui.input type="text" name="code" value="{{ old('code', $company->code) }}" required />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Mã số thuế</x-ui.label>
                        <x-ui.input type="text" name="tax_code" value="{{ old('tax_code', $company->tax_code) }}" />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Email</x-ui.label>
                        <x-ui.input type="email" name="email" value="{{ old('email', $company->email) }}" />
                    </div>

                    <div class="tw:md:col12-4">
                        <x-ui.label>Số điện thoại</x-ui.label>
                        <x-ui.input type="text" name="phone" value="{{ old('phone', $company->phone) }}" />
                    </div>

                    <div class="tw:col12-12">
                        <x-ui.label>Địa chỉ</x-ui.label>
                        <x-ui.input as="textarea" name="address" rows="3">{{ old('address', $company->address) }}</x-ui.input>
                    </div>
                </div>
            </x-ui.card-body>
        </x-ui.card>

        <x-ui.card class="shadow-sm border-0 tw:mt-4">
            <x-ui.card-header class="bg-white tw:font-semibold">
                Thông tin thanh toán in trên PDF
            </x-ui.card-header>

            <x-ui.card-body>
                @php
                    $bankRows = old('bank_accounts', $company->bank_accounts ?? []);

                    if (is_string($bankRows)) {
                        $decodedBankRows = json_decode($bankRows, true);
                        $bankRows = is_array($decodedBankRows) ? $decodedBankRows : [];
                    }

                    if (!is_array($bankRows)) {
                        $bankRows = [];
                    }

                    $bankRows = array_values(array_slice($bankRows, 0, 2));

                    if (count($bankRows) === 0) {
                        $bankRows = [
                            [
                                'bank_account' => old('bank_account', $company->bank_account),
                                'bank_name' => old('bank_name', $company->bank_name),
                                'bank_holder' => old('bank_holder', $company->bank_holder),
                                'is_default' => 1,
                            ],
                            [
                                'bank_account' => '',
                                'bank_name' => '',
                                'bank_holder' => old('bank_holder', $company->bank_holder),
                                'is_default' => 0,
                            ],
                        ];
                    }

                    if (count($bankRows) === 1) {
                        $bankRows[] = [
                            'bank_account' => '',
                            'bank_name' => '',
                            'bank_holder' => $bankRows[0]['bank_holder'] ?? old('bank_holder', $company->bank_holder),
                            'is_default' => 0,
                        ];
                    }

                    $defaultIndex = 0;

                    foreach ($bankRows as $i => $row) {
                        if (!empty($row['is_default'])) {
                            $defaultIndex = $i;
                            break;
                        }
                    }

                    $defaultBank = $bankRows[$defaultIndex] ?? $bankRows[0];
                @endphp

                <input type="hidden" name="bank_account" id="bank_account_default" value="{{ $defaultBank['bank_account'] ?? '' }}">
                <input type="hidden" name="bank_name" id="bank_name_default" value="{{ $defaultBank['bank_name'] ?? '' }}">
                <input type="hidden" name="bank_holder" id="bank_holder_default" value="{{ $defaultBank['bank_holder'] ?? '' }}">

                <x-ui.alert variant="info" class="tw:py-2 tw:text-[0.875em]">
                    Nhập <b>2 tài khoản ngân hàng</b>. PDF đơn hàng sẽ hiện cả 2 tài khoản có dữ liệu.
                    Chỉ chọn được <b>1 tài khoản mặc định</b>.
                </x-ui.alert>

                <div class="table-responsive">
                    <table class="table table-sm align-middle table-bordered tw:mb-2">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 26%;">Số tài khoản</th>
                                <th style="width: 32%;">Ngân hàng</th>
                                <th style="width: 30%;">Tên tài khoản</th>
                                <th class="tw:text-center" style="width: 12%;">Mặc định</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bankRows as $index => $bank)
                                <tr class="bank-account-row">
                                    <td>
                                        <x-ui.input size="sm" type="text"
                                               name="bank_accounts[{{ $index }}][bank_account]"
                                               class="js-bank-account"
                                               value="{{ $bank['bank_account'] ?? '' }}" />
                                    </td>
                                    <td>
                                        <x-ui.input size="sm" type="text"
                                               name="bank_accounts[{{ $index }}][bank_name]"
                                               class="js-bank-name"
                                               value="{{ $bank['bank_name'] ?? '' }}" />
                                    </td>
                                    <td>
                                        <x-ui.input size="sm" type="text"
                                               name="bank_accounts[{{ $index }}][bank_holder]"
                                               class="js-bank-holder"
                                               value="{{ $bank['bank_holder'] ?? '' }}" />
                                    </td>
                                    <td class="tw:text-center">
                                        <input type="radio"
                                               name="bank_default_index"
                                               value="{{ $index }}"
                                               class="form-check-input js-bank-default"
                                               {{ $defaultIndex === $index ? 'checked' : '' }}>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tw:col12-12 tw:mt-4">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input"
                               {{ old('is_active', $company->exists ? $company->is_active : true) ? 'checked' : '' }}>
                        <span class="form-check-label">Đang hoạt động</span>
                    </label>
                </div>

                <script>
                    function syncDefaultBank() {
                        const checked = document.querySelector('.js-bank-default:checked');
                        const row = checked ? checked.closest('tr') : document.querySelector('.bank-account-row');

                        if (!row) return;

                        document.getElementById('bank_account_default').value = row.querySelector('.js-bank-account').value || '';
                        document.getElementById('bank_name_default').value = row.querySelector('.js-bank-name').value || '';
                        document.getElementById('bank_holder_default').value = row.querySelector('.js-bank-holder').value || '';
                    }

                    document.querySelectorAll('.js-bank-account,.js-bank-name,.js-bank-holder,.js-bank-default').forEach(function (input) {
                        input.addEventListener('input', syncDefaultBank);
                        input.addEventListener('change', syncDefaultBank);
                    });

                    document.querySelector('form')?.addEventListener('submit', syncDefaultBank);

                    syncDefaultBank();
                </script>
            </x-ui.card-body>


        <x-ui.card-footer class="bg-white tw:flex tw:justify-end tw:gap-2">
                <x-ui.button href="{{ route('companies.index') }}" variant="light">Hủy</x-ui.button>
                <x-ui.button variant="primary" type="submit">Lưu thông tin</x-ui.button>
            </x-ui.card-footer>
        </x-ui.card>
    </form>
</div>
@endsection