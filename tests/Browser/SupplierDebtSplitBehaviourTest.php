<?php

declare(strict_types=1);

/*
| Hành vi "chia đợt theo %" của `finance/supplier-debts` — đây là logic TIỀN, sai một phép làm
| tròn là sai số tiền đề nghị thanh toán. Test này chốt hành vi của bản JS thuần TRƯỚC khi
| chuyển sang Alpine, để bản mới phải cho ra đúng từng con số.
|
| Ba phép tính phải giữ nguyên:
|   percent -> amount : Math.round((total * percent / 100) * 100) / 100
|   chia đều n dòng   : n-1 dòng đầu Math.floor((100/n)*10000)/10000, dòng CUỐI ăn phần dư
|   tổng kết          : phần trăm toFixed(2) bỏ đuôi `.00`, tiền theo Intl vi-VN + " đ"
*/

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $now = '2026-09-01 08:30:00';
    $this->debtId = (int) DB::table('finance_supplier_debts')->insertGetId([
        'supplier_name' => 'NCC Split', 'document_no' => 'HD-SPLIT', 'document_date' => '2026-09-01',
        'debt_month' => '2026-09-01', 'due_date' => '2026-10-01',
        'total_amount' => 100_000_000, 'paid_amount' => 0, 'status' => 'unpaid',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->actingAs($this->userWithRole('admin', [
        'name' => 'QTV Split', 'email' => 'sd-split@example.test',
    ]));
});

it('nhap phan tram thi tu nhay so tien', function () {
    $page = visit('/finance/supplier-debts');
    $page->assertNoJavaScriptErrors();

    // Mở thẻ chia đợt (nút "+ Thêm dòng theo %") rồi thêm một dòng.
    $page->script('document.querySelector("[data-split-open]").click(); "ok"');
    usleep(400_000);

    $doc = fn (string $js): string => (string) $page->script($js);

    expect((int) $doc('document.querySelectorAll("[data-split-row]").length'))->toBe(1,
        'mở thẻ phải tạo sẵn 1 dòng');

    // 12,5% của 100.000.000 = 12.500.000
    $page->script(<<<'JS'
        (function(){
            var i = document.querySelector('[data-split-percent]');
            i.value = '12,5';
            i.dispatchEvent(new Event('input', {bubbles:true}));
            return 'ok';
        })()
    JS);
    usleep(400_000);

    expect($doc('document.querySelector("[data-split-amount]").value'))->toBe('12500000');
});

it('chia deu dung va dong cuoi an phan du', function () {
    $page = visit('/finance/supplier-debts');
    $page->script('document.querySelector("[data-split-open]").click(); "ok"');
    usleep(400_000);

    // Thêm cho đủ 3 dòng rồi chia đều: 100/3 = 33,3333% (làm TRÒN XUỐNG) × 2, dòng cuối ăn dư.
    $page->script('document.querySelector("[data-split-refresh]").click(); document.querySelector("[data-split-refresh]").click(); "ok"');
    usleep(400_000);
    expect((int) $page->script('document.querySelectorAll("[data-split-row]").length'))->toBe(3);

    $page->script('document.querySelector("[data-split-equal]").click(); "ok"');
    usleep(400_000);

    $phanTram = json_decode((string) $page->script(
        'JSON.stringify(Array.from(document.querySelectorAll("[data-split-percent]")).map(function(e){return e.value}))'
    ), true);
    $soTien = json_decode((string) $page->script(
        'JSON.stringify(Array.from(document.querySelectorAll("[data-split-amount]")).map(function(e){return e.value}))'
    ), true);

    expect($phanTram)->toBe(['33.3333', '33.3333', '33.3334'], 'dòng cuối ăn phần dư phần trăm');
    expect($soTien)->toBe(['33333300', '33333300', '33333400'], 'dòng cuối ăn phần dư tiền');

    // Tổng kết: tiền theo vi-VN, phần trăm bỏ đuôi `.00`.
    $tong = (string) $page->script('document.querySelector("[data-split-summary]").textContent');
    expect($tong)->toContain('100.000.000 đ')
        ->and($tong)->toContain('100%')
        ->and($tong)->not->toContain('VƯỢT');
});

it('canh bao khi vuot tong cong no', function () {
    $page = visit('/finance/supplier-debts');
    $page->script('document.querySelector("[data-split-open]").click(); "ok"');
    usleep(400_000);

    $page->script(<<<'JS'
        (function(){
            var i = document.querySelector('[data-split-amount]');
            i.value = '200000000';
            i.dispatchEvent(new Event('input', {bubbles:true}));
            return 'ok';
        })()
    JS);
    usleep(400_000);

    expect((string) $page->script('document.querySelector("[data-split-summary]").textContent'))
        ->toContain('VƯỢT tổng công nợ!');
});

it('xoa dong thi tong tinh lai', function () {
    $page = visit('/finance/supplier-debts');
    $page->script('document.querySelector("[data-split-open]").click(); "ok"');
    usleep(400_000);
    $page->script('document.querySelector("[data-split-equal]").click(); "ok"');
    usleep(400_000);

    expect((string) $page->script('document.querySelector("[data-split-summary]").textContent'))
        ->toContain('100.000.000 đ');

    $page->script('document.querySelector("[data-split-row] button[title=\'Xóa dòng\']").click(); "ok"');
    usleep(400_000);

    expect((int) $page->script('document.querySelectorAll("[data-split-row]").length'))->toBe(0)
        ->and((string) $page->script('document.querySelector("[data-split-summary]").textContent'))
        ->toContain('Tổng dòng mới: 0 đ');
});
