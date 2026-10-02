<?php

declare(strict_types=1);

/*
| Trang `settlement_requests/index` sau đợt 2026-09-30.
|
| Ba thứ hỏng IM LẶNG nếu không đo thật:
|  1. Bộ đối soát trong hộp thoại nay là Alpine — chọn phiếu rồi nhập số tiền phải ra đúng ba nhánh
|     (hoàn lại / trả thêm / khớp đủ). Sai biểu thức thì không có lỗi nào, chỉ là con số không đổi.
|  2. `.ego-pr-reveal` của tệp CSS dùng chung đặt `opacity:0`; thiếu lớp `ego-pr-ui-ready` thì CẢ
|     TRANG vô hình.
|  3. 🚨 Thẻ bảng: bản cũ dùng bốn lớp KHÔNG có CSS ở đâu cả nên mất hẳn vỏ — đo được nền trong
|     suốt, viền 0px, bo góc 0px, bóng none. Test chốt các giá trị THẬT của tệp CSS dùng chung.
*/

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function textSauKhiStl(object $page, string $js, string $selector): string
{
    return (string) $page->script(<<<JS
        new Promise(resolve => {
            {$js};
            requestAnimationFrame(() => requestAnimationFrame(() => {
                const el = document.querySelector({$selector});
                resolve(el ? el.textContent.replace(/\\s+/g, ' ').trim() : '<không thấy>');
            }));
        })
    JS);
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-30 09:15:00');

    $this->actingAs($this->userWithRole('accounting', [
        'id' => 840001, 'name' => 'KT Browser', 'email' => 'stl-browser@example.test',
    ], ['page.finance']));

    DB::table('advance_requests')->insert([[
        'id' => 840010, 'code' => 'AR-BRW', 'company' => 'EGO', 'recipient_name' => 'Nguyễn Nhận',
        'amount' => 10000000, 'reason' => 'Chi phí', 'settlement_due_date' => '2026-10-15',
        'status' => 'accounting_approved', 'created_by' => 840001,
        'accounting_approved_at' => '2026-09-15 08:00:00',
        'created_at' => '2026-09-10 08:00:00', 'updated_at' => '2026-09-10 08:00:00',
    ]]);

    DB::table('settlement_requests')->insert([[
        'id' => 840020, 'code' => 'ST-BRW', 'advance_request_id' => null, 'created_by' => 840001,
        'company' => 'EGO', 'recipient_name' => 'Nguyễn Nhận', 'advance_amount' => 10000000,
        'actual_amount' => 9000000, 'refund_amount' => 1000000, 'difference_amount' => -1000000,
        'settlement_type' => 'refund', 'reason' => 'Quyết toán', 'status' => 'draft',
        'attachments' => json_encode(['settlements/x.pdf']),
        'created_at' => '2026-09-20 08:00:00', 'updated_at' => '2026-09-20 08:00:00',
    ]]);
});

it('bo doi soat tinh dung ba nhanh', function () {
    $page = visit('/settlement-requests');
    $page->assertNoJavaScriptErrors();

    $oKetQua = "'[x-bind\\\\:class]'";
    $moHop = "[...document.querySelectorAll('button')].find(b => b.textContent.includes('Tạo phiếu mới')).click()";
    $chonPhieu = "(() => { const s = document.getElementById('settlementAdvanceRequest'); s.value = '840010'; s.dispatchEvent(new Event('change')); s.dispatchEvent(new Event('input')); })()";
    $nhap = fn (string $v): string => "(() => { const i = document.getElementById('settlementActualAmount'); i.value = '{$v}'; i.dispatchEvent(new Event('input')); })()";

    expect(textSauKhiStl($page, $moHop, $oKetQua))
        // chưa chọn gì thì hiện lời nhắc
        ->toContain('Chọn phiếu tạm ứng và nhập số tiền đã chi');

    expect(textSauKhiStl($page, $chonPhieu, $oKetQua))
        // đã chọn phiếu nhưng chưa nhập tiền
        ->toContain('Đã chọn AR-BRW');

    // Tạm ứng 10.000.000 — chi 9.000.000 → nhân sự hoàn lại 1.000.000.
    expect(textSauKhiStl($page, $nhap('9000000'), $oKetQua))
        ->toContain('Nhân sự cần hoàn lại công ty: 1.000.000 đ');

    // Chi 12.000.000 → công ty trả thêm 2.000.000.
    expect(textSauKhiStl($page, $nhap('12000000'), $oKetQua))
        ->toContain('công ty cần thanh toán thêm: 2.000.000 đ');

    // Chi đúng bằng tạm ứng → khớp đủ.
    expect(textSauKhiStl($page, $nhap('10000000'), $oKetQua))
        ->toContain('Khớp đủ.');

    // Ô tóm tắt phải hiện đúng số liệu của phiếu đã chọn.
    expect((string) $page->script("document.getElementById('settlementAdvanceAmountReadonly').value"))
        ->toBe('10.000.000 đ');
});

it('noi dung trang hien ra that su', function () {
    $page = visit('/settlement-requests');
    $page->assertNoJavaScriptErrors();

    // ⚠️ `.ego-pr-reveal` có `transition: opacity .32s ease`. Hai `requestAnimationFrame` chỉ là
    // ~32ms nên đọc được giá trị ĐANG CHẠY (đo thật: 0.97 và 0.70) — test chập chờn, đã đỏ một lần.
    // Tắt transition trước khi đo: nếu lớp `ego-pr-ui-ready` KHÔNG được gắn thì luật
    // `.ego-pr-reveal{opacity:0}` vẫn áp và opacity ra 0, nên tắt transition không che được lỗi.
    $opacity = (string) $page->script(<<<'JS'
        new Promise(resolve => {
            const st = document.createElement('style');
            st.textContent = '*,*::before,*::after{transition:none!important;animation:none!important}';
            document.head.appendChild(st);
            requestAnimationFrame(() => requestAnimationFrame(() => {
                resolve(getComputedStyle(document.querySelector('.ego-pr-page-header')).opacity);
            }));
        })
    JS);

    expect($opacity)->toBe('1', 'thiếu lớp ego-pr-ui-ready thì cả trang vô hình mà không báo lỗi');
});

it('the bang co vo dung nhu hai trang anh em cung module', function () {
    $page = visit('/settlement-requests');
    $page->assertNoJavaScriptErrors();

    $css = fn (string $prop): string => (string) $page->script(
        "getComputedStyle(document.querySelector('.ego-pr-data-card')).getPropertyValue('{$prop}')"
    );

    // Giá trị lấy từ `.ego-pr-data-card` trong tệp CSS dùng chung. Bản cũ dùng tên lớp không tồn tại
    // nên bốn thuộc tính này lần lượt là: rgba(0,0,0,0) · 0px · 0px · none.
    expect($css('background-color'))->toBe('rgb(255, 255, 255)', 'thẻ bảng mất nền trắng')
        ->and($css('border-top-width'))->toBe('1px', 'thẻ bảng mất viền')
        ->and($css('border-top-left-radius'))->toBe('17px', 'thẻ bảng mất bo góc')
        ->and($css('box-shadow'))->not->toBe('none', 'thẻ bảng mất đổ bóng');
});
