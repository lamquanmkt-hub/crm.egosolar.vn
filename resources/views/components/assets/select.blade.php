{{-- Ô chọn của trang tài sản (`.ap-select` cũ) — cùng số đo với `<x-assets.input>`. --}}
<select {{ $attributes->class([
    'tw:w-full tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[14px] tw:bg-white',
    'tw:text-[#0f172a] tw:font-bold tw:outline-none tw:h-[44px] tw:px-[13px] tw:py-0',
]) }}>{{ $slot }}</select>
