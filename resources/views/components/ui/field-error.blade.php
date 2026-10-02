{{-- Thông báo lỗi dưới ô nhập — thay `.invalid-feedback` của Bootstrap.

     Số đo lấy từ bootstrap@5.3.3: `.invalid-feedback{display:none;width:100%;margin-top:.25rem;
     font-size:.875em;color:var(--bs-form-invalid-color)}` với `--bs-form-invalid-color:#dc3545`,
     và `.is-invalid~.invalid-feedback{display:block}`.

     🚨 Vì sao KHÔNG tái hiện `display:none` + luật anh em: ở repo này ô nhập là `<x-ui.input>`
     (Tailwind), không còn lớp `.form-control`, nên cơ chế anh em của Bootstrap là thứ duy nhất còn
     sống và nó phụ thuộc vào việc lớp `is-invalid` có mặt. Blade chỉ render thẻ này bên trong
     `@error(...)`, tức CHỈ khi thật sự có lỗi — nên hiện thẳng là đúng hành vi, và không còn phụ
     thuộc Bootstrap nữa. --}}

<div {{ $attributes->class('tw:block tw:w-full tw:mt-1 tw:text-[0.875em] tw:text-[#dc3545]') }}>{{ $slot }}</div>
