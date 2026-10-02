{{-- Nút của trang tài sản (`.ap-btn` + 3 biến thể cũ, 9 chỗ dùng).

     Số đo lấy từ khối style cũ của chính trang: cao 42px, bo 14px, chữ 900, đệm ngang 16px.
     KHÔNG dùng `<x-ui.button>`: component đó tái hiện diện mạo Bootstrap (cao 38px, bo 6px,
     chữ thường) nên sẽ đổi hẳn dáng nút của trang này.

     ⚠️ Viền KHÔNG khai ở phần dùng chung. `.ap-btn` cũ có `border:0` còn `.ap-btn-light` khai đè
     `border:1px solid`; quy đổi thẳng thành hai utility cùng thuộc tính thì THỨ TỰ TỆP CSS quyết
     định ai thắng, chứ không phải thứ tự viết trong `class=`. Nên mỗi biến thể tự khai viền. --}}
@props(['tone' => 'light', 'href' => null])

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([
        'tw:h-[42px] tw:rounded-[14px] tw:px-4 tw:py-0 tw:font-black tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:no-underline tw:cursor-pointer tw:whitespace-nowrap',
        'tw:[border:0] tw:bg-[#2563eb] tw:text-white tw:shadow-[0_14px_30px_rgba(37,99,235,0.22)]' => $tone === 'primary',
        'tw:[border:0] tw:bg-[#059669] tw:text-white tw:shadow-[0_14px_30px_rgba(5,150,105,0.18)]' => $tone === 'green',
        'tw:border tw:border-solid tw:border-[#e5edf7] tw:bg-white tw:text-[#0f172a]' => $tone === 'light',
    ]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class([
        'tw:h-[42px] tw:rounded-[14px] tw:px-4 tw:py-0 tw:font-black tw:inline-flex tw:items-center tw:justify-center tw:gap-2 tw:no-underline tw:cursor-pointer tw:whitespace-nowrap',
        'tw:[border:0] tw:bg-[#2563eb] tw:text-white tw:shadow-[0_14px_30px_rgba(37,99,235,0.22)]' => $tone === 'primary',
        'tw:[border:0] tw:bg-[#059669] tw:text-white tw:shadow-[0_14px_30px_rgba(5,150,105,0.18)]' => $tone === 'green',
        'tw:border tw:border-solid tw:border-[#e5edf7] tw:bg-white tw:text-[#0f172a]' => $tone === 'light',
    ]) }}>{{ $slot }}</button>
@endif
