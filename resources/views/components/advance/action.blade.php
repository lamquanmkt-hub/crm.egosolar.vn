{{-- Nút thao tác nhỏ trong cột "Thao tác" của bảng tạm ứng (`.ego-fin-action` cũ, 8 chỗ dùng).

     ⚠️ Màu nền/chữ/viền KHÔNG khai ở phần dùng chung: bốn tông đều đặt cùng ba thuộc tính đó, mà
     hai utility cùng độ đặc hiệu thì THỨ TỰ TỆP CSS quyết định chứ không phải thứ tự viết trong
     `class=`. Nên tông mặc định cũng tự khai màu của mình. --}}
@props(['tone' => 'default', 'href' => null])

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([
        'tw:border tw:border-solid tw:rounded-[9px] tw:px-2 tw:py-[6px] tw:text-[11px] tw:[font-weight:750] tw:no-underline tw:whitespace-nowrap',
        'tw:border-[#dce8ee] tw:text-[#48606d] tw:bg-white' => $tone === 'default',
        'tw:border-[#c7eadf] tw:text-[#08795e] tw:bg-[#f1fbf7]' => $tone === 'ok',
        'tw:border-[#bfe8ed] tw:text-[#087f98] tw:bg-[#eefbfc]' => $tone === 'primary',
        'tw:border-[#f1d0d0] tw:text-[#b33a3a] tw:bg-[#fff7f7]' => $tone === 'danger',
    ]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class([
        'tw:border tw:border-solid tw:rounded-[9px] tw:px-2 tw:py-[6px] tw:text-[11px] tw:[font-weight:750] tw:no-underline tw:whitespace-nowrap',
        'tw:border-[#dce8ee] tw:text-[#48606d] tw:bg-white' => $tone === 'default',
        'tw:border-[#c7eadf] tw:text-[#08795e] tw:bg-[#f1fbf7]' => $tone === 'ok',
        'tw:border-[#bfe8ed] tw:text-[#087f98] tw:bg-[#eefbfc]' => $tone === 'primary',
        'tw:border-[#f1d0d0] tw:text-[#b33a3a] tw:bg-[#fff7f7]' => $tone === 'danger',
    ]) }}>{{ $slot }}</button>
@endif
