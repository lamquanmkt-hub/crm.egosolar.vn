<?php

declare(strict_types=1);

/*
| Trang `finance/assets` sau đợt 2026-09-30 (bỏ `@php`, bỏ <style>, bỏ JS thuần).
|
| Hai thứ chỉ hỏng IM LẶNG nếu không đo thật:
|  1. Hành vi mở/đóng nay do Alpine lo. Quên `pnpm run build`, đổi tên biến trong `x-data`, hay
|     bỏ `[x-cloak]` đều làm nút bấm không ra gì mà KHÔNG có lỗi nào.
|  2. Hai ngưỡng responsive dùng `tw:max-[1201px]` / `tw:max-[641px]`. Tailwind sinh
|     `@media not all and (min-width:N)`, tức `< N`, còn CSS gốc là `<= N` — nên phải viết N+1.
|     Test đo TẠI ĐÚNG ngưỡng 1200 và 640 để chốt tính bao gồm; viết nhầm 1200/640 là đỏ.
|
| ⚠️ Alpine gom hiệu ứng vào scheduler riêng: gán biến xong đọc `display` ngay (kể cả ở lần gọi
| script sau) vẫn có thể ra giá trị CŨ. Phải phát sự kiện rồi chờ HAI `requestAnimationFrame`
| trong CÙNG một lần gọi và trả Promise — mẫu `doiTrangThai()` của UiAlpineBehaviourBrowserTest.
*/

use Illuminate\Support\Facades\DB;

/** Bấm (hoặc chạy JS) rồi chờ Alpine flush xong mới đọc `display` của phần tử. */
function displaySauKhi(object $page, string $js, string $selector): string
{
    return (string) $page->script(<<<JS
        new Promise(resolve => {
            {$js};
            requestAnimationFrame(() => requestAnimationFrame(() => {
                const el = document.querySelector({$selector});
                resolve(el ? getComputedStyle(el).display : '<không thấy>');
            }));
        })
    JS);
}

beforeEach(function () {
    $now = '2026-09-30 09:15:00';

    DB::table('finance_asset_categories')->insert([
        'id' => 770001, 'code' => 'BRW_A', 'name' => 'Nhóm Browser',
        'useful_life_months' => 60, 'color' => '#2563eb', 'created_at' => $now, 'updated_at' => $now,
    ]);

    $base = [
        'category_id' => 770001, 'company_id' => null, 'assigned_to' => null, 'department' => null,
        'serial_no' => null, 'purchase_date' => null, 'start_use_date' => '2025-04-01',
        'warranty_until' => null, 'next_maintenance_date' => null, 'vendor' => null,
        'invoice_no' => null, 'original_cost' => 90000000, 'salvage_value' => 0,
        'useful_life_months' => 60, 'depreciation_method' => 'straight_line', 'location' => null,
        'status' => 'active', 'condition' => 'good', 'note' => null, 'created_by' => null,
        'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
    ];

    DB::table('finance_assets')->insert([
        array_merge($base, ['id' => 770001, 'code' => 'TS-BRW-1', 'name' => 'Máy hàn Browser']),
        array_merge($base, ['id' => 770002, 'code' => 'TS-BRW-2', 'name' => 'Xe nâng Browser']),
    ]);

    $this->actingAs($this->userWithRole('accounting', [
        'name' => 'KT Browser', 'email' => 'asset-browser@example.test',
    ], ['page.finance']));
});

it('the them tai san mo va dong bang Alpine', function () {
    $page = visit('/finance/assets');
    $page->assertNoJavaScriptErrors();

    $the = "'#assetCreateCard'";

    expect(displaySauKhi($page, '0', $the))
        ->toBe('none', 'vào trang thì thẻ thêm mới phải đóng (nhờ x-cloak, trước cả khi Alpine chạy)');

    expect(displaySauKhi($page, "document.querySelector('[aria-controls=\"assetCreateCard\"]').click()", $the))
        ->toBe('block', 'bấm "+ Thêm tài sản" phải mở thẻ');

    expect((string) $page->script("document.querySelector('[aria-controls=\"assetCreateCard\"]').getAttribute('aria-expanded')"))
        ->toBe('true', 'aria-expanded phải theo trạng thái thật');

    // Nút "Đóng" nằm trong đầu thẻ.
    expect(displaySauKhi($page, "[...document.querySelectorAll('#assetCreateCard button')].find(b => b.textContent.trim() === 'Đóng').click()", $the))
        ->toBe('none', 'bấm "Đóng" phải đóng thẻ lại');
});

it('moi hang co trang thai chi tiet rieng, nut sua chi MO chu khong bat tat', function () {
    $page = visit('/finance/assets');
    $page->assertNoJavaScriptErrors();

    $hang1 = "'#asset-detail-770001'";
    $hang2 = "'#asset-detail-770002'";
    $xem1 = "document.querySelector('[aria-controls=\"asset-detail-770001\"]').click()";
    $sua1 = "document.querySelectorAll('[aria-controls=\"asset-detail-770001\"]')[1].click()";

    expect(displaySauKhi($page, '0', $hang1))->toBe('none')
        ->and(displaySauKhi($page, '0', $hang2))->toBe('none');

    expect(displaySauKhi($page, $xem1, $hang1))->toBe('table-row', 'bấm 👁 phải mở hàng chi tiết');
    expect(displaySauKhi($page, '0', $hang2))
        ->toBe('none', 'mở hàng 1 KHÔNG được mở hàng 2 — mỗi id một khoá riêng');

    expect(displaySauKhi($page, $xem1, $hang1))->toBe('none', 'bấm 👁 lần nữa phải đóng');

    // Nút ✎ dùng `= true` chứ không `= ! …`: bấm hai lần liên tiếp vẫn phải MỞ.
    expect(displaySauKhi($page, $sua1, $hang1))->toBe('table-row', 'bấm ✎ phải mở');
    expect(displaySauKhi($page, $sua1, $hang1))->toBe('table-row', 'bấm ✎ lần nữa vẫn phải mở, không được đóng');
});

it('luoi co theo dung hai nguong 1200px va 640px', function () {
    $page = visit('/finance/assets');
    $page->assertNoJavaScriptErrors();

    // Lưới KPI là div duy nhất khai `repeat(5,minmax(0,1fr))` trong chuỗi lớp — bám vào đó
    // thay vì vị trí DOM để test không vỡ khi bố cục đổi.
    $soCot = function (int $w) use ($page): int {
        $page->resize($w, 900);

        $cols = (string) $page->script(<<<'JS'
            new Promise(resolve => {
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    const el = [...document.querySelectorAll('div')]
                        .find(d => typeof d.className === 'string' && d.className.includes('repeat(5,minmax(0,1fr))'));
                    resolve(el ? getComputedStyle(el).gridTemplateColumns : '');
                }));
            })
        JS);

        return $cols === '' ? 0 : count(preg_split('/\s+/', trim($cols)) ?: []);
    };

    expect($soCot(1400))->toBe(5, 'màn rộng: 5 ô chỉ số một hàng');
    expect($soCot(1201))->toBe(5, 'ngay TRÊN ngưỡng vẫn 5 cột');
    expect($soCot(1200))->toBe(2, 'TẠI ĐÚNG 1200px phải co còn 2 cột — đây là chỗ tw:max-[1200px] sẽ sai');
    expect($soCot(641))->toBe(2, 'ngay trên ngưỡng nhỏ vẫn 2 cột');
    expect($soCot(640))->toBe(1, 'TẠI ĐÚNG 640px phải còn 1 cột');
});

it('o tien chi nhan chu so va dau phan cach', function () {
    $page = visit('/finance/assets');
    $page->assertNoJavaScriptErrors();

    $ketQua = (string) $page->script(<<<'JS'
        new Promise(resolve => {
            document.querySelector('[aria-controls="assetCreateCard"]').click();
            requestAnimationFrame(() => requestAnimationFrame(() => {
                const o = document.querySelector('#assetCreateCard input[data-money]');
                o.value = 'abc12.345,6xyz-';
                o.dispatchEvent(new Event('input'));
                requestAnimationFrame(() => requestAnimationFrame(() => resolve(o.value)));
            }));
        })
    JS);

    expect($ketQua)->toBe('12.345,6-', 'chỉ giữ chữ số, dấu chấm, dấu phẩy và dấu trừ — đúng regex của bản cũ');
});
