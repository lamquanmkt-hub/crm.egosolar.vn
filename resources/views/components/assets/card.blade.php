{{-- Thẻ của trang tài sản (`.ap-card` cũ, 5 chỗ dùng).

     ⚠️ KHÔNG khai lề dưới ở đây. Bản cũ có `.ap-card{margin-bottom:16px}` và hai thẻ trong hàng
     chi tiết đè lại bằng `style="margin:0"`. Quy đổi thành `tw:mb-4` ở component + `tw:mb-0` ở nơi
     gọi thì hai utility cùng thuộc tính tranh nhau theo THỨ TỰ TỆP CSS — `tw:mb-0` thua và hai thẻ
     lồng bị đẩy thêm 16px (đo được). Nên lề để nơi gọi tự khai. --}}
<div {{ $attributes->class([
    'tw:bg-white tw:border tw:border-solid tw:border-[#e5edf7] tw:rounded-[22px]',
    'tw:shadow-[0_18px_45px_rgba(15,23,42,0.06)] tw:overflow-hidden',
]) }}>{{ $slot }}</div>
