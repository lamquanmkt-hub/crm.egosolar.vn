<div class="tw:row">
    {{-- Tên phương thức --}}
    <div class="tw:md:col12-6 tw:mb-4">
        <x-ui.label for="method_name" class="tw:font-bold">Tên phương thức <span class="tw:text-[#dc3545]">*</span></x-ui.label>
        <x-ui.input type="text"
               class="@error('method_name') is-invalid @enderror"
               id="method_name"
               name="method_name"
               value="{{ old('method_name', $method->method_name ?? '') }}"
               placeholder="VD: Chuyển khoản ngân hàng" />
        @error('method_name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Mã code --}}
    <div class="tw:md:col12-6 tw:mb-4">
        <x-ui.label for="code" class="tw:font-bold">Mã (Code) <span class="tw:text-[#dc3545]">*</span></x-ui.label>
        <x-ui.input type="text"
               class="tw:uppercase @error('code') is-invalid @enderror"
               id="code"
               name="code"
               value="{{ old('code', $method->code ?? '') }}"
               placeholder="VD: BANKING" />
        <div class="form-text">Mã nên viết liền không dấu (VD: COD, CASH).</div>
        @error('code')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Mô tả --}}
    <div class="tw:col12-12 tw:mb-4">
        <x-ui.label for="description" class="tw:font-bold">Mô tả chi tiết</x-ui.label>
        <x-ui.input as="textarea" class="@error('description') is-invalid @enderror"
                  id="description"
                  name="description"
                  rows="3">{{ old('description', $method->description ?? '') }}</x-ui.input>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Trạng thái hoạt động --}}
    <div class="tw:col12-12 tw:mb-4">
        <div class="form-check form-switch">
            {{-- Trick: input hidden để gửi value 0 khi checkbox không được check --}}
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input"
                   type="checkbox"
                   id="is_active"
                   name="is_active"
                   value="1"
                    {{ old('is_active', $method->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Kích hoạt sử dụng</label>
        </div>
    </div>
</div>