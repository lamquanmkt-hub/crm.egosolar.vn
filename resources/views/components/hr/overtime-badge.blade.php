{{-- Huy hiệu trạng thái đơn tăng ca — thay `badge bg-*` của Bootstrap.

     Số đo lấy từ bootstrap@5.3.3 đang chạy, không chép tài liệu:
     `.badge{display:inline-block;padding:.35em .65em;font-size:.75em;font-weight:700;line-height:1;
     color:#fff;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:.375rem}`.
     Luật `position:relative;top:-1px` của Bootstrap chỉ áp cho `.btn .badge` (huy hiệu TRONG nút)
     nên không tái hiện ở đây.

     Màu nền đúng `--bs-<tone>-rgb`: warning 255,193,7 · success 25,135,84 · danger 220,53,69 ·
     secondary 108,117,125.

     ⚠️ ĐỔI CÓ CHỦ Ý (a11y): tông `warning` dùng chữ ĐẬM MÀU #212529 thay vì trắng. Đo tỷ lệ tương
     phản theo WCAG 2.1: trắng trên #ffc107 chỉ **1,63:1** (ngưỡng AA là 4,5:1) — chữ gần như không
     đọc được; #212529 trên #ffc107 đạt **9,46:1**. Ba tông còn lại giữ chữ trắng vì đã đạt AA
     (success 4,53 · danger 4,53 · secondary 4,69). --}}
@props(['tone' => 'secondary'])

<span {{ $attributes->class([
    'tw:inline-block tw:px-[0.65em] tw:py-[0.35em] tw:text-[0.75em] tw:font-bold',
    'tw:leading-none tw:text-center tw:whitespace-nowrap tw:align-baseline tw:rounded-[0.375rem]',
    'tw:bg-[rgb(255,193,7)] tw:text-[#212529]' => $tone === 'warning',
    'tw:bg-[rgb(25,135,84)] tw:text-white' => $tone === 'success',
    'tw:bg-[rgb(220,53,69)] tw:text-white' => $tone === 'danger',
    'tw:bg-[rgb(108,117,125)] tw:text-white' => ! in_array($tone, ['warning', 'success', 'danger'], true),
]) }}>{{ $slot }}</span>
