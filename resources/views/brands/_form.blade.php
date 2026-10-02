@csrf
@if ($errors->any())
    <x-ui.alert variant="danger">
        <div class="tw:font-semibold tw:mb-1">Dữ liệu chưa hợp lệ:</div>
        <ul class="tw:mb-0">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
@php
    $brandInstance = $brand ?? null;
@endphp
<div class="tw:mb-4">
    <x-ui.label>Tên brand</x-ui.label>
    <x-ui.input type="text" name="name" value="{{ old('name', $brandInstance?->name ?? '') }}"
           required />
</div>
<div class="tw:mb-4">
    <x-ui.label>Slug</x-ui.label>
    <x-ui.input type="text" name="slug" value="{{ old('slug', $brandInstance?->slug ?? '') }}"
           placeholder="Tự sinh theo tên nếu để trống" />
    <small class="tw:text-[rgba(33,37,41,0.75)]">Unique. Không dấu, viết thường, dùng dấu gạch ngang.</small>
</div>
<div class="tw:mb-4">
    <x-ui.label>Mô tả</x-ui.label>
    <x-ui.input as="textarea" name="description" rows="3"
              placeholder="Mô tả brand...">{{ old('description', $brandInstance?->description ?? '') }}</x-ui.input>
</div>
<div class="tw:mb-4">
    <div class="form-check form-switch">
        <input class="form-check-input"
               type="checkbox"
               id="is_active"
               name="is_active"
               value="1"
                {{ old('is_active', $brandInstance?->is_active ?? true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">Đang sử dụng</label>
    </div>
</div>
<div class="tw:flex tw:gap-2">
    <x-ui.button variant="primary" type="submit">
        <i class="bi bi-save"></i> {{ $buttonText ?? 'Lưu' }}
    </x-ui.button>
    <x-ui.button href="{{ route('brands.index') }}" variant="outline-secondary">Huỷ</x-ui.button>
</div>
