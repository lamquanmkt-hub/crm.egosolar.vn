<?php

declare(strict_types=1);

use App\Enums\Role;

/*
|--------------------------------------------------------------------------
| Browser test đầu tiên — thay cho bộ đo CDP viết tay của đợt chuyển Bootstrap→Tailwind
|--------------------------------------------------------------------------
|
| Hai phép đo dưới đây chính là thứ đã làm bằng script Node + Chrome DevTools trong
| các Bước 3.15/3.16 của skill, nay chốt lại thành test chạy được mãi. Số đo lấy từ
| bootstrap@5.3.3 và đã đối chiếu 4 trạng thái bằng sự kiện chuột thật trước khi
| chuyển component.
|
| Quy ước đo (xem references/browser-testing.md):
|  - So COMPUTED STYLE qua script(), không so ảnh chụp: render ảnh lệch theo máy/hệ điều
|    hành, còn giá trị tính toán thì tất định.
|  - Modal/offcanvas đóng thì mở bằng script trước khi đo (không có backdrop, không animation).
|  - Nút đóng dùng :focus (không phải :focus-visible): el.focus() là đủ để kiểm.
*/

it('x-ui.close-button trong modal-header giữ đúng 3 trạng thái của .btn-close', function () {
    $this->actingAs($this->userWithRole(Role::Admin->value));

    $page = visit('/finance/budget');
    $page->assertNoJavaScriptErrors();

    // Mở modal đầu tiên có nút đóng, không qua JS của app (không backdrop, không animation).
    $page->script(<<<'JS'
        const btn = document.querySelector('.modal-header [data-ego-close]');
        const modal = btn.closest('.modal');
        modal.classList.add('show'); modal.style.display = 'block'; modal.style.position = 'static';
        btn.scrollIntoView({ block: 'center', behavior: 'instant' });
    JS);

    $sel = '.modal-header [data-ego-close]';
    $css = fn (string $prop): string => (string) $page->script(
        "getComputedStyle(document.querySelector('{$sel}')).getPropertyValue('{$prop}')"
    );

    // Mặc định: hộp 1em + đệm ngữ cảnh 8px, lề kéo ra -8px, ảnh SVG nền, mờ 50%.
    expect($css('width'))->toBe('16px')
        ->and($css('height'))->toBe('16px')
        ->and($css('padding-left'))->toBe('8px')
        ->and($css('margin-right'))->toBe('-8px')
        ->and($css('background-image'))->toStartWith('url("data:image/svg+xml')
        ->and($css('opacity'))->toBe('0.5')
        ->and($css('border-top-style'))->toBe('none', 'border:0 của Bootstrap đặt cả style; tw:border-0 chỉ đặt width');

    // Hover bằng chuột thật của Playwright: mờ 75%.
    $page->hover($sel);
    expect($css('opacity'))->toBe('0.75');

    // Focus (Bootstrap dùng :focus, không phải :focus-visible): sáng hẳn + vòng xanh 4px.
    $page->script("document.querySelector('{$sel}').focus()");
    expect($css('opacity'))->toBe('1')
        ->and($css('box-shadow'))->toContain('rgba(13, 110, 253, 0.25) 0px 0px 0px 4px');
});

it('x-ui.button outline-secondary đổi màu khi hover đúng như .btn-outline-secondary', function () {
    $this->actingAs($this->userWithRole(Role::Admin->value));

    $page = visit('/cong-trinh/create');
    $page->assertNoJavaScriptErrors();

    // Nút "quay lại" đầu trang: <x-ui.button variant="outline-secondary" href=...>.
    // Lớp Tailwind có `:`/`[`/`#` nên KHÔNG dùng `.tw\:…` (thoát ký tự lồng PHP→JS→CSS
    // hỏng ngay); chọn theo token bằng [class~="…"] thì không phải thoát gì.
    $sel = 'a[class~="tw:border-[#6c757d]"][href]';
    $css = fn (string $prop): string => (string) $page->script(
        "getComputedStyle(document.querySelector('{$sel}')).getPropertyValue('{$prop}')"
    );

    expect($page->script("document.querySelectorAll('{$sel}').length"))->toBe(1)
        ->and($css('color'))->toBe('rgb(108, 117, 125)')
        ->and($css('background-color'))->toBe('rgba(0, 0, 0, 0)')
        ->and($css('border-top-color'))->toBe('rgb(108, 117, 125)');

    // Component khai transition color/background .15s: hover() của Playwright trả về ngay
    // khi chuột tới, đọc computed style lúc đó là đọc GIỮA chừng chuyển động → chờ hết.
    $page->hover($sel)->wait(0.3);

    expect($css('color'))->toBe('rgb(255, 255, 255)')
        ->and($css('background-color'))->toBe('rgb(108, 117, 125)');
});
