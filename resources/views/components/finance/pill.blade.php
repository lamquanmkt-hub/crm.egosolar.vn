{{-- Viên nhãn trạng thái tài khoản — thay `badge bg-*-subtle rounded-pill`.

     Số đo từ bootstrap@5.3.3: `.badge{display:inline-block;padding:.35em .65em;font-size:.75em;
     font-weight:700;line-height:1;text-align:center;white-space:nowrap;vertical-align:baseline}`,
     `rounded-pill` = `--bs-border-radius-pill` = 50rem, `bg-success-subtle` = #d1e7dd,
     `bg-secondary-subtle` = #e2e3e5, `bg-danger-subtle` = #f8d7da. Màu chữ do nơi gọi truyền (bản cũ dùng `tw:text-[#198754]`
     và `tw:text-[#6c757d]`), nên lớp nền ở đây KHÔNG khai `color` — tránh xung đột cùng thuộc tính. --}}
@props(['tone' => 'muted'])

<span {{ $attributes->class([
    // ⚠️ KHÔNG khai đệm NGANG ở lớp nền: hai nơi gọi đều truyền `tw:px-4`, mà hai utility cùng
    // thuộc tính thì THỨ TỰ TỆP CSS quyết định — đo được đệm rơi 16px → 7,8px khi lớp nền thắng.
    // Nơi gọi tự khai đệm ngang (bản Bootstrap cũ cũng để `tw:px-4` đè `.badge{padding:.35em .65em}`).
    'tw:inline-block tw:py-[0.35em] tw:text-[0.75em] tw:font-bold tw:leading-none',
    // ⚠️ Lớp nền KHÔNG khai `rounded` nữa: `accounts/index` cần 50rem (`rounded-pill`) còn
    // `payment_methods/index` cần 0.375rem (chỉ `badge`). Hai utility cùng thuộc tính thì thứ tự tệp
    // CSS quyết định — đo được bán kính 6px bị kéo thành 800px. Nơi gọi tự khai bán kính.
    'tw:text-center tw:whitespace-nowrap tw:align-baseline',
    'tw:bg-[#d1e7dd]' => $tone === 'success',
    'tw:bg-[#f8d7da]' => $tone === 'danger',
    'tw:bg-[#e2e3e5]' => ! in_array($tone, ['success', 'danger'], true),
]) }}>{{ $slot }}</span>
