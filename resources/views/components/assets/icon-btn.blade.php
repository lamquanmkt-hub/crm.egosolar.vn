{{-- Nút biểu tượng vuông trong cột thao tác (`.ap-icon` / `.ap-icon.red`, 3 chỗ dùng).

     ⚠️ Màu viền KHÔNG khai ở phần dùng chung — hai utility `border-color` cùng độ đặc hiệu thì
     thứ tự tệp CSS quyết định, nên biến thể đỏ có thể thua mà không báo gì. Mỗi tông tự khai. --}}
@props(['tone' => 'blue'])

<button {{ $attributes->class([
    'tw:w-[38px] tw:h-[38px] tw:rounded-[12px] tw:border tw:border-solid tw:[font-weight:950] tw:cursor-pointer',
    'tw:bg-white tw:text-[#2563eb] tw:border-[#dbeafe]' => $tone === 'blue',
    'tw:bg-[#fff1f2] tw:text-[#e11d48] tw:border-[#fecdd3]' => $tone === 'red',
]) }}>{{ $slot }}</button>
