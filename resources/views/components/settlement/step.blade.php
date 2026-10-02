{{-- Một chặng trong dải quy trình hoàn ứng (`.ego-hu-step` ở trang danh sách và
     `.ego-hu-detail-step` ở trang chi tiết — 24 chỗ dùng).

     ⚠️ Cỡ chữ và đệm KHÔNG khai ở phần dùng chung: hai trang dùng hai cỡ khác nhau (12px/10-7 và
     11px/9-6), mà hai utility cùng thuộc tính thì THỨ TỰ TỆP CSS quyết định chứ không phải thứ tự
     viết trong `class=` — truyền lớp từ nơi gọi sẽ THUA lớp nền. Đo được: 11px bị kéo về 12px. --}}
@props(['size' => 'md'])

<span {{ $attributes->class([
    'tw:inline-flex tw:items-center tw:gap-[6px] tw:rounded-[999px]',
    'tw:bg-[#eff9fb] tw:text-[#0e7490] tw:font-extrabold',
    'tw:text-[12px] tw:px-[10px] tw:py-[7px]' => $size === 'md',
    'tw:text-[11px] tw:px-[9px] tw:py-[6px]' => $size === 'sm',
]) }}>{{ $slot }}</span>
