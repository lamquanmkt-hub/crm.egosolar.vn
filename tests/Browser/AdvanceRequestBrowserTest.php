<?php

declare(strict_types=1);

/*
| Trang `advance_requests/index` sau đợt 2026-09-30 (bỏ `@php`, bỏ <style>, bỏ JS nội tuyến).
|
| Ba thứ hỏng IM LẶNG nếu không đo thật:
|  1. Hộp thoại nay là `<x-ui.modal>` chạy bằng Alpine — sai tên sự kiện thì bấm không ra gì.
|  2. `.ego-pr-reveal` của tệp CSS dùng chung đặt `opacity:0`; nếu `x-init` không gắn được
|     `ego-pr-ui-ready` thì CẢ TRANG vô hình mà không có lỗi nào.
|  3. Hai cột `overflow-wrap` phải khác nhau: bản đầu của đợt này đặt nhầm ở biến thể chung nên
|     mọi ô đều `anywhere` — chữ trong cột mã và cột tiền bị ngắt giữa từ.
|
| ⚠️ Alpine gom hiệu ứng vào scheduler riêng: phải chờ HAI `requestAnimationFrame` trong CÙNG
| một lần gọi script rồi mới đọc `display` (mẫu `doiTrangThai()` của UiAlpineBehaviourBrowserTest).
*/

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ⚠️ `x-show` của `<x-ui.modal>` nằm trên LỚP PHỦ, không trên `[role="dialog"]`. Thẻ dialog bên
 * trong luôn `display:flex`, nên đo nó thì hộp thoại lúc nào cũng "đang mở" — test xanh giả.
 * Vì vậy `$bieuThuc` phải trả về đúng phần tử mang `x-show`.
 */
function displaySauKhiAdv(object $page, string $js, string $bieuThuc): string
{
    return (string) $page->script(<<<JS
        new Promise(resolve => {
            {$js};
            requestAnimationFrame(() => requestAnimationFrame(() => {
                const el = {$bieuThuc};
                resolve(el ? getComputedStyle(el).display : '<không thấy>');
            }));
        })
    JS);
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-30 09:15:00');

    $this->actingAs($this->userWithRole('accounting', [
        'id' => 860001, 'name' => 'KT Browser', 'email' => 'adv-browser@example.test',
    ], ['page.finance']));

    DB::table('advance_requests')->insert([[
        'id' => 860001, 'code' => 'AR-BRW-1', 'company' => 'EGO', 'recipient_name' => 'Nguyễn Nhận',
        'amount' => 12500000, 'reason' => 'Chi phí công tác lắp đặt', 'settlement_due_date' => '2026-09-25',
        'status' => 'accounting_approved', 'created_by' => 860001,
        'created_at' => '2026-09-10 08:00:00', 'updated_at' => '2026-09-10 08:00:00',
    ]]);

});

it('hop thoai tao phieu mo va dong bang Alpine', function () {
    $page = visit('/advance-requests');
    $page->assertNoJavaScriptErrors();

    $hop = 'document.querySelector(\'[role="dialog"][aria-label="Tạo đề nghị tạm ứng"]\').parentElement';

    expect(displaySauKhiAdv($page, '0', $hop))
        ->toBe('none', 'vào trang thì hộp thoại phải đóng — x-cloak lo việc này trước cả khi Alpine chạy');

    expect(displaySauKhiAdv($page, "[...document.querySelectorAll('button')].find(b => b.textContent.includes('Tạo phiếu mới')).click()", $hop))
        ->toBe('flex', 'bấm "Tạo phiếu mới" phải mở hộp thoại');

    expect(displaySauKhiAdv($page, "[...document.querySelectorAll('button')].find(b => b.textContent.trim() === 'Đóng').click()", $hop))
        ->toBe('none', 'bấm "Đóng" phải đóng lại');

    // Phát SAI tên thì không được mở — chứng minh bộ lọc theo tên có tác dụng.
    expect(displaySauKhiAdv($page, "window.dispatchEvent(new CustomEvent('open-modal', { detail: 'saiTen' }))", $hop))
        ->toBe('none', 'tên khác thì hộp thoại phải đứng yên');
});

it('noi dung trang hien ra that su, khong bi ket o opacity 0', function () {
    $page = visit('/advance-requests');
    $page->assertNoJavaScriptErrors();

    // `.ego-pr-reveal` của tệp CSS dùng chung khởi tạo opacity:0; `x-init` phải gỡ nó.
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
    expect((string) $page->script("document.querySelector('.ego-pr-page').classList.contains('ego-pr-ui-ready') ? '1' : '0'"))
        ->toBe('1');
});

it('cot ma va cot tien khong bi ngat giua tu', function () {
    $page = visit('/advance-requests');
    $page->assertNoJavaScriptErrors();

    $wrap = fn (int $cot): string => (string) $page->script(
        "getComputedStyle(document.querySelectorAll('table.ego-pr-table tbody tr td')[{$cot}]).overflowWrap"
    );

    // Cột 0 = mã phiếu, 3 = số tiền, 4 = hạn hoàn ứng → `normal`; cột 1 = người nhận → `anywhere`.
    expect($wrap(0))->toBe('normal', 'cột mã phiếu không được ngắt giữa từ')
        ->and($wrap(3))->toBe('normal', 'cột số tiền không được ngắt giữa từ')
        ->and($wrap(4))->toBe('normal', 'cột hạn hoàn ứng không được ngắt giữa từ')
        ->and($wrap(1))->toBe('anywhere', 'cột người nhận vẫn phải ngắt được');

    // Đệm ngang 12px phải thắng luật `.ego-pr-table tbody td{padding:10px}` của tệp CSS dùng chung
    // — bản cũ cần `!important`, bản mới dùng biến thể con trực tiếp.
    expect((string) $page->script("getComputedStyle(document.querySelector('table.ego-pr-table tbody tr td')).paddingLeft"))
        ->toBe('12px');
});
