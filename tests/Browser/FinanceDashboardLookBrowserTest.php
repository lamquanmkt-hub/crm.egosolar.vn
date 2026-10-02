<?php

declare(strict_types=1);

/*
| Diện mạo `finance/index` sau khi bỏ khối <style> 568 dòng (86 rule) sang Tailwind.
|
| Chốt bốn chỗ hỏng IM LẶNG — không lỗi, không test đỏ, chỉ là trang trông khác. Cả bốn là
| XUNG ĐỘT CÙNG THUỘC TÍNH: độ đặc hiệu bằng nhau (hoặc thẻ cha cao hơn) nên THỨ TỰ TRONG TỆP
| CSS quyết định — mà thứ tự đó Tailwind sinh, không phải thứ tự viết trong `class=`.
| Ba trong bốn đã sai thật một lần trong lúc chuyển, chỉ lộ ra nhờ so computed style:
|
|  1. Nút cỡ `--sm` THUA lớp nền. Lớp nền cũ khai `padding:11px 14px; font-size:14px`, biến thể
|     `--sm` khai `10px 13px; 13px`. Cách chữa: bỏ hẳn đệm + cỡ chữ khỏi phần dùng chung, mỗi
|     nút tự khai kích cỡ của mình.
|  2. Màu chữ của <strong> dòng tiền ròng bị biến thể HẬU DUỆ của thẻ cha đè: `[&_strong]:text-…`
|     có độ đặc hiệu (0,1,1) còn lớp tiện ích đặt THẲNG trên <strong> chỉ (0,1,0). Bản CSS cũ
|     thắng được là nhờ `!important` trên `.text-success` — nay bỏ màu khỏi thẻ cha thay vì chọi.
|  3. `.text-success`/`.text-danger` bị CHÍNH TRANG định nghĩa lại (`#166534`/`#dc2626`) với
|     `!important`. Bỏ <style> là chúng rơi về màu Bootstrap. Nay là lớp Tailwind tường minh,
|     không còn trùng tên với Bootstrap nữa.
|  4. Đệm ô "không có dữ liệu" (22px) — bản cũ phải dùng `!important` vì `.finance-table tbody td`
|     (0,1,2) đè `.finance-empty` (0,1,0). Nay đệm đặt THẲNG trên từng th/td nên không ai chọi ai
|     và trang về **0 dấu `!`**.
|
| Số đo lấy từ khối <style> cũ của chính trang.
*/

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $now = '2026-09-01 08:30:00';

    // crm_warehouses / crm_order_items KHÔNG có timestamps; line_total là cột GENERATED STORED.
    $wid = (int) DB::table('crm_warehouses')->insertGetId(['name' => 'Kho Look']);
    $pid = (int) DB::table('crm_product_catalog')->insertGetId([
        'name' => 'Tấm pin Look', 'sku' => 'SKU-FIN-LOOK', 'price_agent' => 6_000_000,
        'created_at' => $now, 'updated_at' => $now,
    ]);

    // Một đơn LỖ để chắc chắn nhánh tông ĐỎ được dựng ra.
    $cid = (int) DB::table('crm_customers')->insertGetId([
        'name' => 'Khách Look', 'phone' => '0917000111', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $lid = (int) DB::table('crm_leads')->insertGetId([
        'customer_id' => $cid, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $oid = (int) DB::table('crm_orders')->insertGetId([
        'lead_id' => $lid, 'order_code' => 'LOOK-1', 'current_department' => 'completed',
        'order_date' => '2026-09-01', 'total_amount' => 2_000_000, 'payment_recorded' => 0,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('crm_order_items')->insert([
        'order_id' => $oid, 'warehouse_id' => $wid, 'product_id' => $pid,
        'quantity' => 2, 'unit_price' => 1_000_000,
    ]);

    $kt = $this->userWithRole('accounting', [
        'name' => 'KT Look', 'email' => 'fin-look@example.test',
    ], ['page.finance']);

    // Một đề nghị thanh toán ĐÃ DUYỆT trong tháng hiện tại -> chi > thu -> dòng tiền ròng ÂM.
    // Cần thế để có <strong> mang TÔNG RIÊNG; nếu ròng = 0 thì nó cùng màu với màu mặc định và
    // guard ở dưới không phân biệt được đúng/sai (đã thử: guard không cắn).
    // `getCashOutPeriod()` lọc theo `updated_at` của THÁNG HIỆN TẠI khi không có bộ lọc ngày.
    DB::table('payment_requests')->insert([
        'code' => 'PR-LOOK-1', 'created_by' => $kt->id, 'receiver_name' => 'NCC Look',
        'amount' => 50_000_000, 'status' => 'accounting_approved',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($kt);
});

it('finance index giu dung dien mao sau khi bo khoi style', function () {
    $page = visit('/finance');
    $page->assertNoJavaScriptErrors();

    $css = fn (string $sel, string $prop): string => (string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($sel).')).getPropertyValue('.json_encode($prop).')'
    );

    // 1) Nút LỌC là cỡ sm: đệm 10/13, cỡ chữ 13px. Nút HERO là cỡ thường: 11/14, 14px.
    //    Trỏ theo LỚP chứ không theo `form button[type=submit]` — trang có 4 form submit.
    $nutLoc = 'button[class*="tw:py-[10px]"]';
    expect($css($nutLoc, 'padding-top'))->toBe('10px', 'nút --sm bị lớp nền đè mất đệm')
        ->and($css($nutLoc, 'padding-left'))->toBe('13px')
        ->and($css($nutLoc, 'font-size'))->toBe('13px');

    $nutHero = 'section a[class*="linear-gradient(135deg,#3b82f6"]';
    expect($css($nutHero, 'padding-top'))->toBe('11px')
        ->and($css($nutHero, 'padding-left'))->toBe('14px')
        ->and($css($nutHero, 'font-size'))->toBe('14px');

    // 2) <strong> của ô dòng tiền: cỡ chữ do THẺ CHA cấp, màu do lớp trên CHÍNH nó.
    //    Dòng tiền ròng ÂM (seed có phiếu chi 50tr) nên nó mang tông ĐỎ riêng — đây mới là chỗ
    //    phân biệt được: biến thể `[&_strong]` của thẻ cha (0,1,1) đè lớp trên <strong> (0,1,0),
    //    bản CSS cũ thắng nhờ `!important` trên `.text-danger`.
    $strongTong = 'strong[class*="#dc2626"]';
    expect($css($strongTong, 'color'))->toBe('rgb(220, 38, 38)',
        'tông đỏ bị biến thể [&_strong] của thẻ cha đè')
        ->and($css($strongTong, 'font-size'))->toBe('18px', 'cỡ chữ vẫn do thẻ cha cấp');

    // <strong> KHÔNG có tông riêng vẫn phải là màu mặc định.
    $strongThuong = 'strong[class="tw:text-[#0f172a]"]';
    expect($css($strongThuong, 'color'))->toBe('rgb(15, 23, 42)')
        ->and($css($strongThuong, 'font-size'))->toBe('18px');

    // 3) Tông ĐỎ lấy từ ô lợi nhuận của đơn LỖ trong bảng — đây là chỗ `.text-danger` cũ phải
    //    dùng `!important` vì chính trang định nghĩa lại lớp Bootstrap này.
    $oDo = 'td[class*="#dc2626"]';
    expect($css($oDo, 'color'))->toBe('rgb(220, 38, 38)', 'ô lỗ mất tông đỏ của trang');

    $margin = 'span[class*="rgba(220,38,38,0.10)"]';
    expect($css($margin, 'color'))->toBe('rgb(220, 38, 38)')
        ->and($css($margin, 'background-color'))->toBe('rgba(220, 38, 38, 0.1)');

    // 3b) Huy hiệu dòng tiền (ÂM -> tông đỏ): nền + chữ + viền của biến thể phải thắng lớp nền.
    //     Lớp nền KHÔNG khai màu viền để biến thể không phải chọi.
    $badge = 'span[class*="rgba(220,38,38,0.10)"][class*="rounded-[999px]"]';
    expect($css($badge, 'color'))->toBe('rgb(220, 38, 38)')
        ->and($css($badge, 'border-top-color'))->toBe('rgba(220, 38, 38, 0.16)')
        ->and($css($badge, 'border-top-width'))->toBe('1px');

    // 4) Ô thân bảng đệm 12px; đầu bảng chữ hoa + viền dưới #e7edf5.
    expect($css('table tbody td', 'padding-top'))->toBe('12px')
        ->and($css('table thead th', 'text-transform'))->toBe('uppercase')
        ->and($css('table thead th', 'font-size'))->toBe('12px')
        ->and($css('table thead th', 'border-bottom-color'))->toBe('rgb(231, 237, 245)');

    // 5) Vòng trang trí ::after của thẻ KPI.
    //
    // ⚠️ Chỗ này từng VỠ THẬT: lớp `tw:after:content-[""]` chứa dấu NHÁY KÉP, mà thuộc tính
    // `class="…"` cũng dùng nháy kép -> trình duyệt coi thuộc tính KẾT THÚC ở đó và bỏ mọi lớp
    // viết sau, kể cả `after:bg-*`. Phải dùng nháy ĐƠN: `content-['']`.
    // Đo computed style của PHẦN TỬ không bao giờ thấy lỗi này — chỉ đo ::after mới thấy.
    $kpi = 'article[class*="after:bg-[#2563eb]"]';
    expect($page->script('document.querySelectorAll('.json_encode($kpi).').length'))->toBe(1,
        'lớp after:bg bị mất — `content-[\"\"]` làm vỡ thuộc tính class?');

    $sau = fn (string $prop): string => (string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($kpi).'), "::after").getPropertyValue('.json_encode($prop).')'
    );
    expect($sau('width'))->toBe('90px', 'mất vòng trang trí ::after của thẻ KPI')
        ->and($sau('height'))->toBe('90px')
        ->and($sau('background-color'))->toBe('rgb(37, 99, 235)')
        ->and($sau('opacity'))->toBe('0.08');
});

it('finance index gap lai luoi o hai breakpoint', function () {
    $page = visit('/finance');

    $cot = fn (string $sel): string => (string) $page->script(
        'getComputedStyle(document.querySelector('.json_encode($sel).')).gridTemplateColumns'
    );
    $luoiKpi = 'div[class*="repeat(4,minmax(0,1fr))"]';

    // >1400px: 4 cột. Bản cũ: .finance-kpi-grid--4{grid-template-columns:repeat(4,...)}
    $page->resize(1600, 1000);
    expect(substr_count($cot($luoiKpi), 'px'))->toBe(4, 'trên 1400px phải còn 4 cột');

    // <=1400px: 2 cột. <=991px: 1 cột. Đây là chỗ `tw:max-[…]` phải thắng lớp nền — mà nó
    // thắng nhờ ĐỨNG SAU trong tệp CSS, thứ tự do Tailwind sinh chứ không do mình viết.
    $page->resize(1200, 1000);
    expect(substr_count($cot($luoiKpi), 'px'))->toBe(2, 'dưới 1400px phải còn 2 cột');

    $page->resize(900, 1000);
    expect(substr_count($cot($luoiKpi), 'px'))->toBe(1, 'dưới 991px phải còn 1 cột');
});
