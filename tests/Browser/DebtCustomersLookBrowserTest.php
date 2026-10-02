<?php

declare(strict_types=1);

/*
| Diện mạo `finance/debt-customers` sau khi bỏ khối <style> 561 dòng (82 rule) sang Tailwind.
|
| Chốt ba chỗ hỏng IM LẶNG — không lỗi, không test đỏ, chỉ là trang trông khác. Cả ba đều là
| XUNG ĐỘT CÙNG THUỘC TÍNH giữa hai lớp tiện ích: độ đặc hiệu bằng nhau nên THỨ TỰ TRONG TỆP CSS
| quyết định, mà thứ tự đó do Tailwind sinh chứ không phải thứ mình viết trong `class=`.
| Cả ba đã đo sai một lần trong lúc chuyển và chỉ lộ ra nhờ so computed style:
|
|  1. Viền huy hiệu trạng thái. Phần dùng chung của hằng trong presenter từng mang
|     `tw:border-transparent`; biến thể `tw:border-[#fca5a5]` viết SAU trong chuỗi nhưng vẫn THUA.
|     Cách chữa: bỏ hẳn màu khỏi phần chung, mỗi biến thể tự khai.
|  2. Viền pill tổng quan — y hệt cơ chế: `tw:border-[#e6edf5]` ở lớp nền nuốt màu của pill
|     xanh/đỏ. Nay chỉ pill TRƠN mới khai màu nền.
|  3. Chữ đầu bảng CHI TIẾT. Bản cũ dùng bộ chọn HẬU DUỆ `.finance-table thead th`, nên nó với
|     tới cả `th` của bảng LỒNG BÊN TRONG; `.finance-detail-table thead th` chỉ đè nền/viền/cỡ/đệm
|     nên ba thuộc tính chữ vẫn chảy từ bảng ngoài xuống. <x-ui.table> dùng biến thể CON TRỰC TIẾP
|     nên KHÔNG với tới — phải khai lại font-weight/text-transform/letter-spacing cho bảng trong.
|
| Hàng chi tiết trỏ qua `tr[id^="detail-"]`: cái `id` đó là móc THẬT — nút mở/đóng trỏ vào nó
| bằng `aria-controls`, không phải một lớp chỉ tồn tại cho test.
|
| Số đo lấy từ khối <style> cũ của chính trang.
*/

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $now = '2026-09-01 08:30:00';

    $cid = (int) DB::table('crm_customers')->insertGetId([
        'name' => 'Khách Diện Mạo', 'phone' => '0912000333', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $lid = (int) DB::table('crm_leads')->insertGetId([
        'customer_id' => $cid, 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('crm_orders')->insert([
        'lead_id' => $lid, 'order_code' => 'ORD-LOOK-01',
        'current_department' => 'completed', 'total_amount' => 10_000_000,
        'payment_recorded' => 0, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $this->actingAs($this->userWithRole('accounting', [
        'name' => 'KT Diện Mạo', 'email' => 'debt-look@example.test',
    ], ['page.finance']));
});

it('debt-customers giu dung dien mao sau khi bo khoi style', function () {
    $page = visit('/finance/customer-debts');
    $page->assertNoJavaScriptErrors();

    $css = fn (string $sel, string $prop): string => (string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($sel).')).getPropertyValue('.json_encode($prop).')'
    );

    // 1) Huy hiệu "Công nợ": viền #fca5a5, KHÔNG được trong suốt.
    $huyHieu = '[class*="tw:text-[#b91c1c]"]';
    expect($css($huyHieu, 'border-top-color'))->toBe('rgb(252, 165, 165)',
        'viền huy hiệu bị lớp nền nuốt — phần dùng chung lại khai màu viền?')
        ->and($css($huyHieu, 'border-top-width'))->toBe('1px')
        ->and($css($huyHieu, 'color'))->toBe('rgb(185, 28, 28)');

    // Chấm tròn trong huy hiệu đi qua biến thể `[&>span]` (bản cũ là luật hậu duệ).
    expect($css($huyHieu.' > span', 'background-color'))->toBe('rgb(239, 68, 68)');

    // 2) Pill tổng quan: pill TRƠN #e6edf5, pill XANH #a7f3d0, pill ĐỎ #fca5a5.
    expect($css('[class*="tw:border-[#e6edf5]"][class*="tw:rounded-[18px]"]', 'border-top-color'))
        ->toBe('rgb(230, 237, 245)')
        ->and($css('[class*="tw:border-[#a7f3d0]"]', 'border-top-color'))->toBe('rgb(167, 243, 208)')
        ->and($css('[class*="tw:border-[#fecaca]"]', 'border-top-color'))->toBe('rgb(254, 202, 202)');

    // 3) Đầu bảng CHI TIẾT: ba thuộc tính chữ cũ chảy từ bảng ngoài xuống, nay phải khai lại.
    //    Hàng chi tiết đang display:none nhưng ba thuộc tính này không phụ thuộc bố cục.
    $thTrong = 'tr[id^="detail-"] table[data-ego-table] > thead > tr > th';
    expect($css($thTrong, 'font-weight'))->toBe('900')
        ->and($css($thTrong, 'text-transform'))->toBe('uppercase')
        ->and($css($thTrong, 'letter-spacing'))->toBe('0.96px')
        // Còn đây là phần bảng chi tiết TỰ đè: nền trong suốt, cỡ 12px, không dính (sticky).
        ->and($css($thTrong, 'background-color'))->toBe('rgba(0, 0, 0, 0)')
        ->and($css($thTrong, 'font-size'))->toBe('12px')
        ->and($css($thTrong, 'position'))->toBe('static');

    // Đầu bảng NGOÀI vẫn dính — chứng tỏ hai bảng nay tách bạch chứ không còn chung một luật.
    expect($css('table[data-ego-table] > thead > tr > th', 'position'))->toBe('sticky');
});

it('nut mo chi tiet van chay that sau khi sang Alpine', function () {
    // Hành vi này từng là <script> JS thuần bắt qua lớp `.finance-toggle-btn`; lúc chuyển <style>
    // sang Tailwind, bộ thay lớp đã NUỐT MẤT cái móc đó — nút còn nguyên diện mạo, computed style
    // khớp 100%, mà bấm không ra gì. Đo CSS không bao giờ thấy kiểu hỏng này, nên phải BẤM THẬT.
    // Nay không còn móc theo tên lớp nữa: Alpine khai `x-on:click` ngay tại thẻ.
    $page = visit('/finance/customer-debts');
    $page->assertNoJavaScriptErrors();

    $docHien = fn (): string => (string) $page->script(
        'getComputedStyle(document.querySelector(\'tr[id^="detail-"]\')).display'
    );

    // Alpine cập nhật DOM ở microtask sau sự kiện, nên đọc NGAY sau click có thể còn giá trị cũ.
    // Chờ có giới hạn thay vì `sleep` cố định: đỏ nhanh khi hỏng thật, không rung khi máy chậm.
    $hien = function (?string $mong = null) use ($docHien): string {
        for ($i = 0; $i < 40; $i++) {
            $v = $docHien();
            if ($mong === null || $v === $mong) {
                return $v;
            }
            usleep(50_000);
        }

        return $docHien();
    };

    expect($hien())->toBe('none', 'hàng chi tiết phải đóng lúc mới vào');

    // Đếm TRƯỚC khi bấm: thiếu nút thì `click()` sẽ chờ hết giờ (treo ~2 phút) thay vì đỏ ngay.
    $nut = 'button[aria-controls^="detail-"]';
    expect($page->script('document.querySelectorAll('.json_encode($nut).').length'))->toBe(1,
        'không tìm thấy nút mở chi tiết');

    $bieuTuong = fn (): string => (string) $page->script(
        'document.querySelector('.json_encode($nut).' + " i").className'
    );
    $moRong = fn (): string => (string) $page->script(
        'document.querySelector('.json_encode($nut).').getAttribute("aria-expanded")'
    );

    expect($bieuTuong())->toContain('bi-plus')->and($moRong())->toBe('false');

    $page->click($nut);
    expect($hien('table-row'))->toBe('table-row', 'bấm nút không mở hàng chi tiết')
        ->and($bieuTuong())->toContain('bi-dash')
        ->and($bieuTuong())->not->toContain('bi-plus')
        ->and($moRong())->toBe('true');

    $page->click($nut);
    expect($hien('none'))->toBe('none', 'bấm lần hai không đóng lại')
        ->and($bieuTuong())->toContain('bi-plus')
        ->and($moRong())->toBe('false');
});
