{{-- Trong view của component KHÔNG có $this; phương thức public được truyền vào dưới dạng closure. --}}
<div x-data="{ open: false }" x-on:click.outside="open = false"
     x-on:keydown.escape="open = false" class="{{ $wrapClasses() }}" {{ $attributes }}>

    {{-- Nút mở: nơi gọi truyền qua slot `trigger` để giữ nguyên <x-ui.button> của trang.
         Vỏ này chỉ để gắn sự kiện, nên `display:contents` — nó biến mất khỏi hộp bố cục, không
         chèn thêm một tầng flex/block nào. Thiếu nó thì nút bị bọc trong một <div> block và
         bố cục ô "Thao tác" đổi. --}}
    <div x-on:click="open = ! open" :aria-expanded="open ? 'true' : 'false'"
         class="tw:[display:contents]">{{ $trigger }}</div>

    <ul x-show="open" x-cloak class="{{ $menuClasses() }}">{{ $slot }}</ul>
</div>
