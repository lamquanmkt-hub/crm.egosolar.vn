<?php

declare(strict_types=1);

/*
| <x-ui.dropdown> — thay .dropdown + data-bs-toggle="dropdown" của Bootstrap bằng Alpine.
|
| Phải là browser test: menu ẩn/hiện bằng `x-show`, chỉ chạy khi Alpine khởi động được VÀ
| [x-cloak] có trong bản build. Thiếu vế nào thì trang vẫn 200, HTML vẫn đủ chữ — chỉ là bấm
| không ra gì, hoặc mọi menu hiện cùng lúc.
|
| ⚠️ Chờ Alpine flush trước khi đọc `display` — xem ghi chú trong UiAlpineBehaviourBrowserTest.
*/

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $now = now()->format('Y-m-d H:i:s');
    foreach (['Công trình A', 'Công trình B'] as $i => $ten) {
        DB::table('sites')->insert([
            'name' => $ten, 'status' => 'installing', 'contract_amount' => 1_000_000,
            'labor_cost' => 0, 'transport_cost' => 0, 'other_cost' => 0,
            'company_id' => (int) config('ego.default_company_id'),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $this->actingAs($this->userWithRole('admin', ['name' => 'QTV DD', 'email' => 'dropdown@example.test']));
});

/** Chạy JS rồi chờ Alpine vẽ xong, trả `display` của menu thứ $n (0-based). */
function menuDisplay(object $page, string $js, int $n = 0): string
{
    return (string) $page->script(<<<JS
        new Promise(resolve => {
            {$js};
            requestAnimationFrame(() => requestAnimationFrame(() => {
                const uls = document.querySelectorAll('[x-data] > ul');
                resolve(getComputedStyle(uls[{$n}]).display);
            }));
        })
    JS);
}

it('dropdown dong san, mo khi bam nut, va moi dong co menu rieng', function () {
    $page = visit('/cong-trinh');
    $page->assertNoJavaScriptErrors();

    expect(menuDisplay($page, '0', 0))->toBe('none', 'menu phải đóng khi mới vào trang');
    expect(menuDisplay($page, '0', 1))->toBe('none');

    $bam = "document.querySelectorAll('[x-data] > [x-on\\\\:click]')[0].click()";
    expect(menuDisplay($page, $bam, 0))->toBe('block', 'bấm nút phải mở menu của chính dòng đó');
    expect(menuDisplay($page, '0', 1))->toBe('none', 'menu dòng khác KHÔNG được mở theo');

    expect(menuDisplay($page, $bam, 0))->toBe('none', 'bấm lần nữa thì đóng');
});

it('dropdown dong khi bam ra ngoai va khi nhan Escape', function () {
    $page = visit('/cong-trinh');
    $page->assertNoJavaScriptErrors();

    $bam = "document.querySelectorAll('[x-data] > [x-on\\\\:click]')[0].click()";

    expect(menuDisplay($page, $bam, 0))->toBe('block');
    expect(menuDisplay($page, 'document.body.click()', 0))->toBe('none', 'bấm ra ngoài phải đóng');

    expect(menuDisplay($page, $bam, 0))->toBe('block');
    $esc = "document.querySelectorAll('[x-data]')[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))";
    expect(menuDisplay($page, $esc, 0))->toBe('none', 'Escape phải đóng');
});

it('menu giu dung so do cua bootstrap', function () {
    $page = visit('/cong-trinh');
    $bam = "document.querySelectorAll('[x-data] > [x-on\\\\:click]')[0].click()";
    menuDisplay($page, $bam, 0);

    $css = fn (string $sel, string $prop): string => (string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($sel).')).getPropertyValue('.json_encode($prop).')'
    );

    $menu = '[x-data] > ul';
    expect($css($menu, 'position'))->toBe('absolute')
        ->and($css($menu, 'z-index'))->toBe('1000')
        ->and($css($menu, 'background-color'))->toBe('rgb(255, 255, 255)')
        // `.dropdown-menu` khai `padding: .5rem 0` nên cũng xoá thụt lề mặc định của <ul>.
        ->and($css($menu, 'padding-left'))->toBe('0px')
        ->and($css($menu, 'padding-top'))->toBe('8px')
        ->and($css($menu, 'list-style-type'))->toBe('none');

    $item = '[x-data] > ul > li > a';
    expect($css($item, 'display'))->toBe('block')
        ->and($css($item, 'padding-left'))->toBe('16px')
        ->and($css($item, 'padding-top'))->toBe('4px')
        ->and($css($item, 'white-space'))->toBe('nowrap')
        // Bootstrap `.dropdown-item` khai `border: 0` -> xoá cả KIỂU viền. `tw:border-0` của
        // Tailwind v4 thì không (nó đặt border-style: var(--tw-border-style) = solid).
        ->and($css($item, 'border-top-style'))->toBe('none');

    // Đường kẻ ngăn: `height:0` nhưng computed ra 1px — reboot đặt `box-sizing:border-box` nên
    // hộp không thể thấp hơn viền. Đường kẻ chính là VIỀN TRÊN, không phải chiều cao.
    $hr = '[x-data] > ul > li > hr';
    expect($css($hr, 'height'))->toBe('1px')
        ->and($css($hr, 'border-top-width'))->toBe('1px')
        ->and($css($hr, 'border-top-style'))->toBe('solid');
});
