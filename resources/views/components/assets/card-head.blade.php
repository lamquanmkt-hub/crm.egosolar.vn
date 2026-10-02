{{-- Đầu thẻ của trang tài sản (`.ap-card-head` + `h2` + `.ap-sub`, 4 chỗ dùng).

     `sub` là chuỗi mô tả nhỏ dưới tiêu đề; slot dành cho phần bên phải (nút). --}}
@props(['title', 'sub' => ''])

<div {{ $attributes->class([
    'tw:flex tw:items-center tw:justify-between tw:gap-3 tw:px-[18px] tw:py-4',
    'tw:[border-bottom:1px_solid_#eef2f7] tw:bg-[linear-gradient(90deg,#f0fdfa,#f8fbff)]',
]) }}>
    <div>
        <h2 class="tw:text-[17px] tw:m-0 tw:[font-weight:950]">{{ $title }}</h2>
        @if($sub !== '')<div class="tw:text-[#64748b] tw:text-[12px] tw:font-bold">{{ $sub }}</div>@endif
    </div>
    {{ $slot }}
</div>
