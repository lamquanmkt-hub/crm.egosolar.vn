<?php

declare(strict_types=1);

/*
| Diện mạo + hành vi `finance/supplier-debts` sau khi bỏ khối <style> 639 dòng (105 rule).
|
| Chốt những chỗ hỏng IM LẶNG đã xảy ra THẬT trong lúc chuyển:
|  1. Hàng chi tiết bật/tắt bằng Alpine (`x-show` + `x-cloak`). Trước 2026-09-29 là JS thuần
|     qua lớp `.show` + biến thể `tw:[&.show]:table-row`; nay `mo[<id>]` của `hangChiTiet()`
|     quyết định, nên phải chờ Alpine flush trước khi đọc `display` — xem test scope bên dưới.
|  2. Tên biến thể `green/amber/red` DÙNG CHUNG cho `.sd-kpi` (tông icon) và `.sd-money`
|     (màu chữ) — bộ thay lớp một-khoá gán nhầm, phải quyết theo lớp đi kèm.
|  3. Bốn lớp nút `sd-btn*` do view CHA khai nhưng partial `_debt_files_manager` cũng dùng:
|     xoá <style> ở cha là nút trong partial mất sạch định dạng.
|  4. ⚠️ `tw:max-[Npx]` của Tailwind là `width < N`, còn `@media (max-width:Npx)` là
|     `width <= N`. Muốn khớp phải dùng `max-[(N+1)px]`.
*/

use Illuminate\Support\Facades\DB;

/** Id dùng chung cho cả công nợ và đợt thanh toán — xem ghi chú trong beforeEach. */
const ID_CHUNG = 424242;

beforeEach(function () {
    $now = '2026-09-01 08:30:00';

    // ⚠️ Id gieo TƯỜNG MINH và BẰNG NHAU ở hai bảng. Hàng chi tiết khoá theo id công nợ còn
    // hàng sửa đợt khoá theo id đợt; hai khoá đó chỉ đụng nhau khi TRÙNG SỐ. Để auto-increment
    // tự cấp thì hai số khác nhau và guard scope Alpine KHÔNG cắn — đã đo: bỏ `x-data` của
    // `<tbody>` lồng mà test vẫn xanh. Trùng số mới tái hiện được cảnh "bấm sửa đợt thì hàng
    // chi tiết cha tự đóng".
    DB::table('finance_supplier_debts')->insert([
        'id' => ID_CHUNG,
        'supplier_name' => 'NCC Look', 'document_no' => 'HD-LOOK', 'document_date' => '2026-09-01',
        'debt_month' => '2026-09-01', 'due_date' => '2026-10-01',
        'total_amount' => 100_000_000, 'paid_amount' => 30_000_000, 'status' => 'partial',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('finance_supplier_debt_payments')->insert([
        'id' => ID_CHUNG,
        'supplier_debt_id' => ID_CHUNG,
        'payment_round' => 1, 'amount' => 30_000_000, 'payment_date' => '2026-09-15',
        'status' => 'planned', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->actingAs($this->userWithRole('accounting', [
        'name' => 'KT Look NCC', 'email' => 'sd-look@example.test',
    ], ['page.finance']));
});

it('hang chi tiet van bat tat duoc sau khi sang Alpine', function () {
    $page = visit('/finance/supplier-debts');
    $page->assertNoJavaScriptErrors();

    $hien = function (?string $mong = null) use ($page): string {
        for ($i = 0; $i < 40; $i++) {
            $v = (string) $page->script('getComputedStyle(document.querySelector(\'tr[id^="sd-detail-"]\')).display');
            if ($mong === null || $v === $mong) {
                return $v;
            }
            usleep(50_000);
        }

        return (string) $page->script('getComputedStyle(document.querySelector(\'tr[id^="sd-detail-"]\')).display');
    };

    expect($hien())->toBe('none', 'hàng chi tiết phải đóng lúc mới vào');

    $nut = 'button[data-detail-trigger]';
    expect($page->script('document.querySelectorAll('.json_encode($nut).').length'))->toBe(1);

    $page->click($nut);
    expect($hien('table-row'))->toBe('table-row', 'bấm không mở — biến thể [&.show] còn không?');

    $page->click($nut);
    expect($hien('none'))->toBe('none', 'bấm lần hai không đóng');
});

it('dien mao giu nguyen o cac moc quan trong', function () {
    $page = visit('/finance/supplier-debts');

    $css = fn (string $sel, string $prop): string => (string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($sel).')).getPropertyValue('.json_encode($prop).')'
    );

    // Tông icon KPI đi qua biến thể của thẻ cha, KHÔNG lẫn với màu chữ tiền.
    expect($css('[data-sd-kpi-icon]', 'background-color'))->toBe('rgb(234, 242, 255)')
        ->and($css('[data-sd-kpi-icon]', 'color'))->toBe('rgb(37, 99, 235)');

    // Tiền "đã trả" tông xanh — cùng tên biến thể `green` nhưng ngữ nghĩa khác hẳn.
    expect($css('[class*="tw:text-[#059669]"]', 'color'))->toBe('rgb(5, 150, 105)');

    // Nút chính: nền gradient, chữ trắng, KHÔNG viền (bản cũ dùng `border:0`).
    $nutChinh = 'button[class*="linear-gradient(135deg,#2563eb,#1d4ed8)"]';
    expect($css($nutChinh, 'border-top-width'))->toBe('0px')
        ->and($css($nutChinh, 'color'))->toBe('rgb(255, 255, 255)')
        ->and($css($nutChinh, 'white-space'))->toBe('nowrap');

    // Đầu bảng LỒNG thừa hưởng nền + nowrap từ luật hậu duệ của bảng ngoài.
    $thTrong = 'table[class*="[border-collapse:collapse]"] table thead th';
    expect($css($thTrong, 'background-color'))->toBe('rgb(251, 253, 255)')
        ->and($css($thTrong, 'padding-top'))->toBe('9px', 'bảng lồng giữ đệm riêng 9px');
});

it('breakpoint 768px bao gom dung ca moc', function () {
    $page = visit('/finance/supplier-debts');
    $luoi = 'form[class*="[grid-template-columns:160px_160px_1fr_185px_120px]"]';
    $cot = fn (): int => substr_count((string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($luoi).')).gridTemplateColumns'
    ), 'px');

    // ⚠️ `tw:max-[768px]` của Tailwind là `< 768`, KHÔNG phải `<= 768`. Bản CSS cũ dùng
    // `@media (max-width:768px)` nên TẠI ĐÚNG 768px vẫn phải là 1 cột -> dùng max-[769px].
    $page->resize(768, 900);
    expect($cot())->toBe(1, 'tại đúng 768px phải là 1 cột');

    $page->resize(1000, 900);
    expect($cot())->toBe(2, 'từ 769 đến 1300 là 2 cột');

    $page->resize(1600, 900);
    expect($cot())->toBe(5, 'trên 1300px là 5 cột');
});

/**
 * Hàng sửa đợt nằm ở `<tbody>` LỒNG bên trong hàng chi tiết, tức một scope Alpine CON.
 * `mo` của scope con che `mo` của scope cha. Bỏ `x-data` của `<tbody>` lồng thì hai hàng
 * dùng chung một `mo`, và vì id công nợ trùng id đợt (xem beforeEach) thì: mở hàng chi
 * tiết sẽ mở LUÔN form sửa đợt, còn bấm sửa đợt lại ĐÓNG hàng chi tiết cha. Cả hai đều
 * không báo lỗi gì.
 *
 * ⚠️ PHẢI CHỜ ALPINE FLUSH TRONG CÙNG LẦN GỌI SCRIPT. Alpine gom hiệu ứng vào scheduler
 * riêng, nên đọc `display` ở lần gọi kế tiếp còn ra giá trị CŨ. Bản đầu của test này dùng
 * vòng lặp "chờ tới khi thấy giá trị mong đợi" và ĐÃ XANH TRÊN CẢ BẢN HỎNG — vì giá trị
 * mong đợi trùng giá trị trước khi bấm, nên lần đọc đầu (còn cũ) đã khớp. Đo được:
 * bỏ `x-data` lồng ra thì PROBE thấy `round` = table-row ngay sau khi mở hàng chi tiết.
 */
it('hang sua dot mo duoc ma khong dong hang chi tiet cha', function () {
    $page = visit('/finance/supplier-debts');
    $page->assertNoJavaScriptErrors();

    $selChiTiet = "'tr[id^=\"sd-detail-\"]'";
    $selDot = "'tr[id^=\"sd-round-edit-\"]'";

    // Chạy `$js` rồi chờ 2 khung hình TRONG CÙNG lần gọi, sau đó mới đọc `display`.
    // `$js` = '0' nghĩa là chỉ đọc, không thao tác gì.
    $doi = function (string $js, string $sel) use ($page): string {
        return (string) $page->script(<<<JS
            new Promise(resolve => {
                {$js};
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    const e = document.querySelector({$sel});
                    resolve(e ? getComputedStyle(e).display : 'KHONG-CO');
                }));
            })
        JS);
    };

    $bam = fn (string $sel): string => 'document.querySelector('.json_encode($sel).').click()';

    expect($doi('0', $selChiTiet))->toBe('none', 'hàng chi tiết phải đóng lúc mới vào')
        ->and($doi('0', $selDot))->toBe('none', 'hàng sửa đợt phải đóng lúc mới vào');

    // Mở hàng chi tiết. Đây là chỗ guard cắn: form sửa đợt KHÔNG được mở theo.
    expect($doi($bam('button[data-detail-trigger]'), $selChiTiet))->toBe('table-row')
        ->and($doi('0', $selDot))
        ->toBe('none', 'mở hàng chi tiết mà form sửa đợt cũng mở → <tbody> lồng thiếu x-data riêng');

    // beforeEach gieo đúng MỘT đợt thanh toán; không có nút thì phần dưới vô nghĩa.
    expect((int) $page->script('document.querySelectorAll(\'tr[id^="sd-detail-"] button[data-round-trigger]\').length'))
        ->toBe(1, 'seed phải cho đúng 1 đợt thanh toán');

    expect($doi($bam('tr[id^="sd-detail-"] button[data-round-trigger]'), $selDot))
        ->toBe('table-row', 'bấm sửa đợt không mở được form')
        ->and($doi('0', $selChiTiet))
        ->toBe('table-row', 'bấm sửa đợt lại đóng hàng chi tiết cha → scope con đã che scope cha');
});

/**
 * Nút ✎ ở dòng cha phải MỞ (không bật/tắt) rồi đưa tiêu điểm vào ô đầu của lưới sửa.
 * Bản JS cũ chờ 200ms cho cuộn êm xong mới focus; Alpine phải giữ y hệt, và focus chỉ
 * ăn khi hàng đã hiện — ô nằm trong `display:none` thì `focus()` im lặng không làm gì.
 */
it('nut sua mo hang chi tiet va dua tieu diem vao o dau', function () {
    $page = visit('/finance/supplier-debts');
    $page->assertNoJavaScriptErrors();

    $page->click('button[data-edit-trigger]');

    $trongLuoi = function () use ($page): string {
        for ($i = 0; $i < 40; $i++) {
            $v = (string) $page->script(
                'document.activeElement && document.activeElement.closest("[data-edit-grid]") ? "TRONG" : "NGOAI"'
            );
            if ($v === 'TRONG') {
                return $v;
            }
            usleep(50_000);
        }

        return 'NGOAI';
    };

    $hienHang = function () use ($page): string {
        for ($i = 0; $i < 40; $i++) {
            $v = (string) $page->script('getComputedStyle(document.querySelector(\'tr[id^="sd-detail-"]\')).display');
            if ($v === 'table-row') {
                return $v;
            }
            usleep(50_000);
        }

        return 'KHONG-MO';
    };

    expect($hienHang())->toBe('table-row', 'nút sửa không mở hàng chi tiết')
        ->and($trongLuoi())->toBe('TRONG', 'tiêu điểm không vào lưới sửa — $nextTick/setTimeout 200ms hỏng?');
});
