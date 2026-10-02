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
    $tierInstance = $tier ?? null;
@endphp
<div class="tw:row">
    <div class="tw:md:col12-4 tw:mb-4">
        <x-ui.label>Code</x-ui.label>
        <x-ui.input type="text" name="code" value="{{ old('code', $tierInstance?->code ?? '') }}"
               placeholder="retail, agent_1..."
               required />
        <small class="tw:text-[rgba(33,37,41,0.75)]">Duy nhất, không dấu, dùng _ nếu cần.</small>
    </div>
    <div class="tw:md:col12-8 tw:mb-4">
        <x-ui.label>Tên</x-ui.label>
        <x-ui.input type="text" name="name" value="{{ old('name', $tierInstance?->name ?? '') }}"
               required />
    </div>
</div>
<div class="tw:row">
    <div class="tw:md:col12-4 tw:mb-4">
        <x-ui.label>Priority</x-ui.label>
        <x-ui.input type="number" min="0" name="priority" value="{{ old('priority', $tierInstance?->priority ?? 0) }}" />
        <small class="tw:text-[rgba(33,37,41,0.75)]">Số nhỏ hơn ưu tiên hơn (tuỳ bạn quy ước).</small>
    </div>
    <div class="tw:md:col12-8 tw:mb-4 tw:flex tw:items-end">
        <div class="form-check form-switch">
            <input class="form-check-input"
                   type="checkbox"
                   id="is_active"
                   name="is_active"
                   value="1"
                    {{ old('is_active', $tierInstance?->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Đang sử dụng</label>
        </div>
    </div>
</div>
<div class="tw:flex tw:gap-2">
    <x-ui.button variant="primary" type="submit">
        <i class="bi bi-save"></i> {{ $buttonText ?? 'Lưu' }}
    </x-ui.button>
    <x-ui.button href="{{ route('price-tiers.index') }}" variant="outline-secondary">Huỷ</x-ui.button>
</div>
