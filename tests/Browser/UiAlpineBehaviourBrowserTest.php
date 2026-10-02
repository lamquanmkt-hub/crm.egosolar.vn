<?php

declare(strict_types=1);

/*
| Hành vi của 3 component Alpine thay Bootstrap JS: <x-ui.disclosure>, <x-ui.tabs>, <x-ui.modal>.
|
| Bắt buộc phải là browser test. Cả ba ẩn/hiện bằng `x-show`, tức chỉ chạy khi Alpine khởi động
| được VÀ quy tắc `[x-cloak]{display:none!important}` có trong bản build. Thiếu vế nào thì trang
| vẫn trả 200, HTML vẫn đủ chữ, test Feature vẫn xanh — chỉ là bấm không ra gì, hoặc tệ hơn: mọi
| panel hiện cùng lúc. Đúng kiểu hỏng im lặng.
|
| ⚠️ PHẢI CHỜ ALPINE FLUSH TRƯỚC KHI ĐỌC `display`.
| Alpine không sửa DOM ngay lúc gán biến; nó gom hiệu ứng vào scheduler riêng. Đọc computed style
| ở lần gọi script kế tiếp vẫn có thể thấy giá trị CŨ — và kết quả đổi theo từng lần chạy, tức
| test sẽ đỏ lúc được lúc không. Đo thật khi dựng tệp này: gán xong đọc ngay ra `none`, chờ 2 khung
| hình thì ra `flex`. Nên mọi thao tác đi qua `doiTrangThai()` bên dưới: phát sự kiện rồi chờ
| `requestAnimationFrame` hai lần trong CÙNG một lần gọi (Playwright có await Promise trả về).
*/

use App\Models\Marketing\MarketingCampaign;

beforeEach(function () {
    MarketingCampaign::create(['name' => 'Goodwe 5kw', 'platform' => 'Facebook']);
    $this->actingAs($this->userWithRole('admin', ['name' => 'QTV Alpine', 'email' => 'alpine@example.test']));
});

/** Chạy một đoạn JS rồi chờ Alpine vẽ xong, trả về `display` của phần tử cần đo. */
function doiTrangThai(object $page, string $js, string $selector): string
{
    return (string) $page->script(<<<JS
        new Promise(resolve => {
            {$js};
            requestAnimationFrame(() => requestAnimationFrame(() => {
                resolve(getComputedStyle(document.querySelector({$selector})).display);
            }));
        })
    JS);
}

it('disclosure thu lai khi khong loc, mo va dong bang su kien dung ten', function () {
    $page = visit('/marketing/budget');
    $page->assertNoJavaScriptErrors();

    $sel = "'[x-on\\\\:toggle-disclosure\\\\.window]'";
    $bat = fn (string $ten): string => doiTrangThai(
        $page,
        "window.dispatchEvent(new CustomEvent('toggle-disclosure', { detail: '{$ten}' }))",
        $sel
    );

    expect(doiTrangThai($page, '0', $sel))->toBe('none', 'không lọc thì khối tổng hợp phải đóng');
    expect($bat('budgetSummary'))->toBe('block', 'phát đúng tên phải mở khối ra');
    expect($bat('budgetSummary'))->toBe('none', 'phát lần nữa phải đóng lại');
    expect($bat('ten-khong-ton-tai'))->toBe('none', 'tên khác không được đụng vào khối này');
});

it('disclosure mo san khi URL co bo loc', function () {
    $page = visit('/marketing/budget?platform=Facebook');
    $page->assertNoJavaScriptErrors();

    expect(doiTrangThai($page, '0', "'[x-on\\\\:toggle-disclosure\\\\.window]'"))
        ->toBe('block', 'có lọc thì khối tổng hợp phải mở sẵn');
});

it('modal dong san, mo bang su kien, dong bang Escape, va khoa cuon nen', function () {
    $page = visit('/marketing/budget');
    $page->assertNoJavaScriptErrors();

    $sel = "'[x-on\\\\:open-modal\\\\.window]'";

    expect(doiTrangThai($page, '0', $sel))->toBe('none', 'hộp thoại phải đóng khi mới vào trang');

    expect(doiTrangThai($page, "window.dispatchEvent(new CustomEvent('open-modal', { detail: 'adsModal' }))", $sel))
        ->toBe('flex');
    expect((string) $page->script('document.body.style.overflow'))
        ->toBe('hidden', 'mở hộp thì khoá cuộn nền');

    expect(doiTrangThai($page, "window.dispatchEvent(new CustomEvent('close-modal', { detail: 'adsModal' }))", $sel))
        ->toBe('none');
    expect((string) $page->script('document.body.style.overflow'))
        ->toBe('', 'đóng hộp thì trả lại cuộn nền');

    // Escape
    expect(doiTrangThai($page, "window.dispatchEvent(new CustomEvent('open-modal', { detail: 'adsModal' }))", $sel))
        ->toBe('flex');
    expect(doiTrangThai($page, "window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))", $sel))
        ->toBe('none', 'Escape phải đóng hộp');
});

it('tab chi hien dung mot panel va doi duoc khi bam', function () {
    $page = visit('/marketing/budget');
    $page->assertNoJavaScriptErrors();

    $dem = fn (string $js): string => (string) $page->script(<<<JS
        new Promise(resolve => {
            {$js};
            requestAnimationFrame(() => requestAnimationFrame(() => {
                resolve([...document.querySelectorAll('[role="tabpanel"]')]
                    .map(el => getComputedStyle(el).display).join(','));
            }));
        })
    JS);

    $moModal = "window.dispatchEvent(new CustomEvent('open-modal', { detail: 'adsModal' }))";

    expect($dem($moModal))->toBe('block,none,none', 'vào là panel đầu mở, hai panel kia đóng');
    expect($dem("document.querySelectorAll('[role=\"tab\"]')[2].click()"))
        ->toBe('none,none,block', 'bấm tab 3 thì chỉ panel 3 mở');
    expect($dem("document.querySelectorAll('[role=\"tab\"]')[1].click()"))
        ->toBe('none,block,none', 'bấm tab 2 thì chỉ panel 2 mở');

    expect((string) $page->script(
        "[...document.querySelectorAll('[role=\"tab\"]')].map(b => b.getAttribute('aria-selected')).join(',')"
    ))->toBe('false,true,false', 'aria-selected phải đi theo tab đang chọn');
});
