<?php

declare(strict_types=1);

/*
| Ngưỡng 720px của `technical/maintenance/index` — hai vế phải BẬT CÙNG LÚC.
|
| 🚨 `tw:max-[Npx]` KHÔNG bằng `@media (max-width:Npx)`.
| Tailwind v4 sinh ra `@media not all and (min-width:Npx)`, tức `width < N`, còn CSS gốc
| `@media (max-width:Npx)` là `width <= N`. Tại ĐÚNG ngưỡng, hai bên khác nhau.
| Kiểm bằng bản build: `grep -o '@media[^{]*{\.tw\\:max-\\\[721px\\\]' public/build/assets/*.css`
|
| Trang này ghép hai hệ:
|  - `public/css/technical-maintenance-v4.css:22` — `@media(max-width:720px)` đặt
|    `.tm4-hero-actions{width:100%}` VÀ `.tm4-tabs a span{display:none}`  → bật khi <= 720
|  - view — 3 nút trong `.tm4-hero-actions` mang `tw:max-[721px]:flex-auto`  → bật khi < 721
|
| Hai vế đó nay khớp nhau. Trước 2026-09-30 view viết `tw:max-[720px]`, nên TẠI ĐÚNG 720px
| khung nút đã giãn hết chiều ngang mà 3 nút bên trong KHÔNG giãn theo — hụt đúng 1px bề
| rộng màn hình, không báo lỗi gì. Test này đo cả hai vế tại cùng một khổ để chốt lại.
*/

use App\Enums\Role;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $now = '2026-09-01 08:00:00';
    $admin = $this->userWithRole(Role::Admin->value, [
        'name' => 'QTV Bao Tri', 'email' => 'om-breakpoint@example.test',
    ]);
    DB::table('companies')->insert([
        'id' => 998511, 'code' => 'EGOT9985', 'name' => 'EGO Test BP',
        'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('sites')->insert([
        'id' => 998611, 'name' => 'Cong trinh breakpoint', 'company_id' => 998511,
        'contract_amount' => 0, 'system_kwp' => 10.5, 'labor_cost' => 0,
        'transport_cost' => 0, 'other_cost' => 0, 'created_by' => $admin->id,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->actingAs($admin);
});

it('nguong 720px bat dong thoi ca CSS lan utility Tailwind', function () {
    $page = visit('/du-an/bao-tri-bao-hanh');
    $page->assertNoJavaScriptErrors();

    // Vế CSS (file .css, `max-width:720px`): nhãn chữ trong tab bị ẩn.
    $veCss = fn (): string => (string) $page->script(
        'getComputedStyle(document.querySelector(".tm4-tabs a span")).display'
    );

    // Vế Tailwind (`tw:max-[721px]:flex-auto`): nút trong khung thao tác giãn ra.
    $veTailwind = fn (): string => (string) $page->script(
        'getComputedStyle(document.querySelector(".tm4-hero-actions > *")).flexGrow'
    );

    // Chốt rằng có đúng thứ để đo, không thì mọi khẳng định dưới là vô nghĩa.
    expect((int) $page->script('document.querySelectorAll(".tm4-hero-actions > *").length'))
        ->toBeGreaterThan(0, 'không có nút nào trong .tm4-hero-actions')
        ->and((int) $page->script('document.querySelectorAll(".tm4-tabs a span").length'))
        ->toBeGreaterThan(0, 'không có nhãn tab nào');

    // 719px — dưới ngưỡng, cả hai vế đều phải bật.
    $page->resize(719, 900);
    expect($veCss())->toBe('none', '719px: CSS phải ẩn nhãn tab')
        ->and($veTailwind())->toBe('1', '719px: nút phải giãn');

    // 720px — ĐÚNG ngưỡng. Đây là chỗ `max-[720px]` từng trượt.
    $page->resize(720, 900);
    expect($veCss())->toBe('none', '720px: CSS (max-width:720px) vẫn phải bật')
        ->and($veTailwind())->toBe('1', '720px: utility phải bật CÙNG với CSS — dùng max-[721px], không phải max-[720px]');

    // 721px — trên ngưỡng, cả hai vế đều phải tắt.
    // `block` chứ không phải `inline`: `.tm4-tabs a{display:flex}` nên `<span>` là flex item
    // và bị blockify. Giá trị này LẤY TỪ SỐ ĐO, đừng suy từ tên thẻ.
    $page->resize(721, 900);
    expect($veCss())->toBe('block', '721px: CSS phải thôi ẩn nhãn tab')
        ->and($veTailwind())->toBe('0', '721px: nút phải thôi giãn');
});
