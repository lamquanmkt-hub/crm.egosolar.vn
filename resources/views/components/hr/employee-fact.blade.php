{{-- Ô thông tin nhãn-giá trị của trang nhân viên; dùng 21 chỗ nên tách ra để khỏi lặp chuỗi utility. --}}
@props(['label', 'value'])

<div class="tw:rounded-[14px] tw:border tw:border-solid tw:border-[#edf2f7] tw:bg-[#fbfdff] tw:px-3 tw:py-[11px]">
    <div class="tw:mb-[5px] tw:text-[10px] tw:font-extrabold tw:uppercase tw:text-[#64748b]">{{ $label }}</div>
    <div class="tw:text-[13px] tw:font-extrabold tw:break-words tw:text-[#0f172a]">{{ $value }}</div>
</div>
