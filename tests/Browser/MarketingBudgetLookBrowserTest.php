<?php

declare(strict_types=1);

use App\Models\Marketing\MarketingBudget;
use App\Models\Marketing\MarketingCampaign;
use Illuminate\Support\Carbon;

/*
| Diện mạo `marketing/budget` sau đợt quy đổi Bootstrap -> `tw:` (2026-09-28).
|
| Chốt lại đúng những chỗ có thể hỏng IM LẶNG — không lỗi, không test đỏ, chỉ là trang
| trông khác đi:
|
|  1. `tw:shadow-[...]`, `tw:text-[0.875em]`… là utility SINH LÚC BUILD. Quên `pnpm run build`,
|     đổi tiền tố, hay đổi cấu hình Tailwind thì lớp biến mất và bóng/cỡ chữ về mặc định.
|  2. Đầu thẻ dùng `tw:bg-white` (không `!important` như bản Bootstrap `bg-white`), nên nó chỉ
|     thắng nền xám của `<x-ui.card-header>` nhờ `LopTienIch` nhận `tw:bg-*` là `background-color`
|     rồi BỎ lớp nền của component. Gỡ ánh xạ đó đi là hai lớp đứng cạnh nhau và ai thắng lại
|     phụ thuộc thứ tự tệp CSS — xem Tests\Unit\Support\LopTienIchTest.
|
| Số đo lấy từ bootstrap@5.3.3 đang chạy trên chính trang này trước khi đổi.
*/

it('marketing budget giu dung dien mao sau khi doi sang tailwind', function () {
    $thangNay = Carbon::now()->startOfMonth();
    $cp = MarketingCampaign::create(['name' => 'Goodwe 5kw', 'platform' => 'Facebook']);
    MarketingBudget::create([
        'platform' => 'Facebook', 'campaign_id' => $cp->id,
        'month' => $thangNay->toDateString(), 'budget' => 10_000_000, 'actual_spent' => 2_500_000,
    ]);

    $this->actingAs($this->userWithRole('admin', ['name' => 'QTV Look', 'email' => 'look@example.test']));

    $page = visit('/marketing/budget');
    $page->assertNoJavaScriptErrors();

    $css = fn (string $sel, string $prop): string => (string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($sel).')).getPropertyValue('.json_encode($prop).')'
    );

    // 1) Bóng thẻ: `.shadow-sm` của Bootstrap = rgba(0,0,0,.075) 0 2px 4px.
    //    Tailwind v4 xếp 5 lớp, 4 lớp đầu trong suốt nên chỉ kiểm lớp CUỐI.
    $bong = $css('[data-ego-card]', 'box-shadow');
    expect($bong)->toEndWith('rgba(0, 0, 0, 0.075) 0px 2px 4px 0px',
        'bóng thẻ mất — utility tw:shadow-[...] chưa được sinh? (chạy pnpm run build)');

    // 2) Nền đầu thẻ phải TRẮNG, không phải nền xám mặc định rgba(33,37,41,0.03) của component.
    expect($css('[data-ego-card-header]', 'background-color'))->toBe('rgb(255, 255, 255)',
        'đầu thẻ về nền xám — LopTienIch không còn nhận tw:bg-* là background-color?');

    // 3) Cỡ chữ: `.fs-5` = 20px; `.small` = .875em (THEO EM, nên phải là 14px trên nền 16px).
    expect($css('[data-ego-card] .tw\\:text-\\[20px\\]', 'font-size'))->toBe('20px');
    expect($css('[data-ego-card] .tw\\:text-\\[0\\.875em\\]', 'font-size'))->toBe('14px');
});
