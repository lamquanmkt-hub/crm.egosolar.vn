<?php

declare(strict_types=1);

/*
| Diện mạo `sites/index` sau khi bỏ khối <style> 222 dòng (47 rule) sang Tailwind.
|
| Chốt lại những chỗ hỏng IM LẶNG — không lỗi, không test đỏ, chỉ là trang trông khác:
|  1. Viền thẻ đi qua BIẾN `--border`. `layouts/app` khai
|     `.card,[data-ego-card]{border:1px solid var(--border) !important}`; thay vì chọi `!important`,
|     `<x-sites.card>` đặt lại biến ngay trên thẻ. Ai gỡ cơ chế đó ra thì viền về màu chủ đề.
|  2. Breakpoint 992px (`.ego-sites` cũ bỏ đệm ngang dưới 992px) nay là `tw:max-[992px]:px-0`
|     KHÔNG có `!`. Nó thắng `tw:px-6` nhờ đứng sau trong tệp CSS — mà thứ tự đó do Tailwind
|     sinh, không phải thứ mình viết. Đổi cấu hình/thứ tự là nó thua im lặng.
|  3. Viền TRÊN của ô thân bảng (`.ego-table-modern tbody td`) đi qua biến thể
|     `tw:[&>tbody>tr>td]:[border-top:…]`, sinh lúc build.
|
| Số đo lấy từ khối <style> cũ của chính trang.
*/

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::table('sites')->insert([
        'name' => 'Công trình Look', 'status' => 'installing',
        'contract_amount' => 100_000_000, 'labor_cost' => 0, 'transport_cost' => 0, 'other_cost' => 0,
        'system_kwp' => '10.50', 'installed_at' => '2026-03-01',
        'company_id' => (int) config('ego.default_company_id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->actingAs($this->userWithRole('admin', ['name' => 'QTV Look', 'email' => 'site-look@example.test']));
});

it('sites index giu dung dien mao sau khi bo khoi style', function () {
    $page = visit('/cong-trinh');
    $page->assertNoJavaScriptErrors();

    $css = fn (string $sel, string $prop): string => (string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($sel).')).getPropertyValue('.json_encode($prop).')'
    );

    // 1) Viền thẻ: rgba(15,118,110,.07) của `.ego-card` cũ, đạt được bằng --border chứ không phải `!`.
    expect($css('[data-ego-card]', 'border-top-color'))->toBe('rgba(15, 118, 110, 0.07)',
        'viền thẻ về màu chủ đề — <x-sites.card> còn đặt --border không?');

    // 2) Viền thẻ KPI có màu riêng, cũng qua cùng cơ chế.
    expect($css('[data-ego-card][style*="11,201,170"]', 'border-top-color'))->toBe('rgba(11, 201, 170, 0.14)');

    // 3) Ô thân bảng: viền TRÊN rgba(0,0,0,.04), viền DƯỚI vẫn #dee2e6 của `.table`.
    $td = '[data-ego-table] > tbody > tr > td';
    expect($css($td, 'border-top-width'))->toBe('1px')
        ->and($css($td, 'border-top-color'))->toBe('rgba(0, 0, 0, 0.04)')
        ->and($css($td, 'border-bottom-color'))->toBe('rgb(222, 226, 230)')
        ->and($css($td, 'padding-top'))->toBe('16px', 'ego-table-modern đặt đệm dọc 1rem');

    // 4) Đầu bảng: nền #f8fafc, chữ #334155, cỡ .82rem.
    $th = '[data-ego-table-head] > tr > th';
    expect($css($th, 'background-color'))->toBe('rgb(248, 250, 252)')
        ->and($css($th, 'color'))->toBe('rgb(51, 65, 85)')
        ->and($css($th, 'font-size'))->toBe('13.12px');

    // 5) Viên nhãn: bo tròn 999px (KHÔNG phải rounded-full = calc(infinity*1px)), cao 12px chữ.
    $pill = 'td span[class*="rounded-[999px]"]';
    expect($css($pill, 'border-top-left-radius'))->toBe('999px')
        ->and($css($pill, 'font-size'))->toBe('12px');
});

it('duoi 992px thi bo dem ngang, khong can important', function () {
    $dem = function (int $w): string {
        $page = visit('/cong-trinh');
        $page->resize($w, 900);

        return (string) $page->script(
            "getComputedStyle(document.querySelector('[class*=\"rounded-[22px]\"]')).getPropertyValue('padding-left')"
        );
    };

    expect($dem(1400))->toBe('24px', 'khổ rộng giữ đệm tw:px-6');
    // `tw:max-[992px]:px-0` thắng `tw:px-6` nhờ ĐỨNG SAU trong tệp CSS, không nhờ `!`.
    expect($dem(900))->toBe('0px', 'dưới 992px phải bỏ đệm — nếu ra 24px thì biến thể đang thua');
});
