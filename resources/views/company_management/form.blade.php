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

<form method="POST" action="{{ $action }}">
    @csrf

    @if($method !== 'POST')
        @method($method)
    @endif

    <x-ui.card class="shadow-sm border-0">
        <x-ui.card-header class="bg-white tw:font-semibold">
            Thông tin pháp lý
        </x-ui.card-header>

        <x-ui.card-body>
            <div class="tw:row tw:g-3">
                <div class="tw:md:col12-8">
                    <x-ui.label>Tên công ty <span class="tw:text-[#dc3545]">*</span></x-ui.label>
                    <x-ui.input type="text" name="name" value="{{ old('name', $company->name) }}" required />
                </div>

                <div class="tw:md:col12-4">
                    <x-ui.label>Mã công ty <span class="tw:text-[#dc3545]">*</span></x-ui.label>
                    <x-ui.input type="text" name="code" value="{{ old('code', $company->code) }}" placeholder="VD: EGO_VN" required />
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
            <div class="tw:row tw:g-3">
                <div class="tw:md:col12-4">
                    <x-ui.label>Số tài khoản</x-ui.label>
                    <x-ui.input type="text" name="bank_account" value="{{ old('bank_account', $company->bank_account) }}" />
                </div>

                <div class="tw:md:col12-4">
                    <x-ui.label>Ngân hàng</x-ui.label>
                    <x-ui.input type="text" name="bank_name" value="{{ old('bank_name', $company->bank_name) }}" />
                </div>

                <div class="tw:md:col12-4">
                    <x-ui.label>Tên tài khoản</x-ui.label>
                    <x-ui.input type="text" name="bank_holder" value="{{ old('bank_holder', $company->bank_holder) }}" />
                </div>

                <div class="tw:col12-12">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input"
                               {{ old('is_active', $company->exists ? $company->is_active : true) ? 'checked' : '' }}>
                        <span class="form-check-label">Đang hoạt động</span>
                    </label>
                </div>
            </div>
        </x-ui.card-body>

        <x-ui.card-footer class="bg-white tw:flex tw:justify-end tw:gap-2">
            <x-ui.button href="{{ route('company-management.index') }}" variant="light">Hủy</x-ui.button>
            <x-ui.button variant="primary" type="submit">{{ $buttonText }}</x-ui.button>
        </x-ui.card-footer>
    </x-ui.card>
</form>