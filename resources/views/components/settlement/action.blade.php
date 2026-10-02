{{-- Nút thao tác trong cột "Thao tác" của bảng hoàn ứng (`.ego-hu-btn-*` cũ, 8 chỗ dùng).

     ⚠️ Nền và màu chữ KHÔNG khai ở phần dùng chung: bốn tông đều đặt đúng hai thuộc tính đó, mà hai
     utility cùng độ đặc hiệu thì THỨ TỰ TỆP CSS quyết định chứ không phải thứ tự viết trong `class=`.

     `muted` dùng cho cả `<a>` (nút "Xem") lẫn `<button>` (nút "Xóa"); bản cũ cho thẻ `<a>` thêm
     `display:inline-flex;align-items:center;gap:5px` bằng style nội tuyến, còn `<button>` thì không. --}}
@props(['tone' => 'muted', 'href' => null])

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([
        'tw:rounded-lg tw:px-[9px] tw:py-[7px] tw:text-[12px] tw:font-bold tw:no-underline',
        'tw:inline-flex tw:items-center tw:gap-[5px]',
        'tw:bg-[#f1f5f9] tw:text-[#334155]' => $tone === 'muted',
        'tw:bg-[#0ea5b7] tw:text-white' => $tone === 'primary',
        'tw:bg-[#10b981] tw:text-white' => $tone === 'ok',
        'tw:bg-[#ef4444] tw:text-white' => $tone === 'danger',
    ]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->class([
        'tw:[border:0] tw:rounded-lg tw:px-[9px] tw:py-[7px] tw:text-[12px] tw:font-bold',
        'tw:bg-[#f1f5f9] tw:text-[#334155]' => $tone === 'muted',
        'tw:bg-[#0ea5b7] tw:text-white' => $tone === 'primary',
        'tw:bg-[#10b981] tw:text-white' => $tone === 'ok',
        'tw:bg-[#ef4444] tw:text-white' => $tone === 'danger',
    ]) }}>{{ $slot }}</button>
@endif
