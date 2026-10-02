{{-- Trong view của component KHÔNG có $this; phương thức public được truyền vào dưới dạng closure. --}}
<div
    x-data="{ open: false }"
    x-on:open-modal.window="$event.detail === @js($name) && (open = true)"
    x-on:close-modal.window="$event.detail === @js($name) && (open = false)"
    {{-- Escape chỉ đóng khi hộp này đang mở, nếu không mọi hộp trên trang cùng nuốt phím. --}}
    x-on:keydown.escape.window="open && (open = false)"
    x-effect="document.body.style.overflow = open ? 'hidden' : ''"
    x-show="open"
    x-cloak
    class="{{ $backdropClasses() }}"
>
    {{-- Nền mờ: thẻ riêng để bấm ra ngoài thì đóng, mà bấm trong hộp thì không. --}}
    <div class="tw:absolute tw:inset-0 tw:bg-[rgba(0,0,0,0.5)]" x-on:click="open = false"></div>

    <div role="dialog" aria-modal="true" @if ($title !== '') aria-label="{{ $title }}" @endif
         class="{{ $dialogClasses() }}" {{ $attributes }}>

        @if ($title !== '' || $subtitle !== '')
            <div class="{{ $headerClasses() }}">
                <div>
                    @if ($title !== '')
                        <h5 class="tw:font-bold tw:mb-0 tw:text-[1.25rem]">{{ $title }}</h5>
                    @endif
                    @if ($subtitle !== '')
                        <small class="tw:text-[rgba(33,37,41,0.75)]">{{ $subtitle }}</small>
                    @endif
                </div>
                <x-ui.close-button in="modal" type="button" x-on:click="open = false" aria-label="Đóng" />
            </div>
        @endif

        <div class="{{ $bodyClasses() }}">{{ $slot }}</div>
    </div>
</div>
