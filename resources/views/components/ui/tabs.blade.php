{{-- Trong view của component KHÔNG có $this; phương thức public được truyền vào dưới dạng closure. --}}
<div x-data="{ tab: @js($activeTab()) }" {{ $attributes }}>
    <div role="tablist" class="{{ $stripClasses() }}">
        @foreach ($tabs as $key => $label)
            {{-- Chuỗi lớp phải TĨNH trong mã nguồn: Tailwind quét theo văn bản, ghép từ biến là
                 nó không thấy và không sinh utility (bẫy đã ghi trong Ui\Alert). --}}
            <button
                type="button"
                role="tab"
                class="{{ $tabBase() }}"
                :class="tab === @js($key) ? @js($tabActive()) : @js($tabIdle())"
                :aria-selected="tab === @js($key) ? 'true' : 'false'"
                x-on:click="tab = @js($key)"
            >{{ $label }}</button>
        @endforeach
    </div>

    {{ $slot }}
</div>
