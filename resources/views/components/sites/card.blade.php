{{-- Thẻ của trang công trình: `.ego-card` + `.shadow-ego` cũ, dùng 7 chỗ.

     Viền: KHÔNG chọi `!important`. `layouts/app` khai
     `.card, [data-ego-card] { border: 1px solid var(--border) !important }`, nên cách sạch là ĐẶT LẠI
     BIẾN `--border` ngay trên thẻ — luật của layout tự cho ra màu mình muốn, không cần `!` nào.
     (Lớp `.ego-card` cũ phải dùng `!important` vì nó viết màu thẳng thay vì qua biến.)
     Bo góc thì để yên: `border-radius: var(--radius) !important` của layout vẫn quyết, và bán kính
     do chủ đề chạy động đặt — giành lại là phá cấu hình chủ đề.

     `class`/`style` là PROP tường minh, không dùng `{{ $attributes }}`: đặt `{{ }}` ở vị trí thuộc
     tính của thẻ `<x-...>` làm vỡ PHP sinh ra (SiteEditButtonsTest canh cả cây view). Nơi gọi hiện
     chỉ truyền đúng ba thứ này. --}}
@props(['class' => '', 'style' => '', 'border' => 'rgba(15,118,110,0.07)'])

<x-ui.card
    :class="trim('tw:bg-[rgba(255,255,255,0.95)] tw:[backdrop-filter:blur(8px)]
        tw:shadow-[0_14px_38px_rgba(2,44,34,0.08)] '.$class)"
    :style="'--border: '.$border.';'.$style">
    {{ $slot }}
</x-ui.card>
