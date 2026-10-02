<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\FinanceOrderProfitRow;
use App\View\Presenters\Finance\FinanceDashboardPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trang `finance/index` sau khi dời khối `@php` sang FinanceDashboardPresenter (2026-09-29).
 *
 * Guard hai chiều: view chỉ in, mọi biến do controller/presenter cấp, `$row->x` là thuộc tính DTO
 * thật; và trang thật in đúng số.
 */
final class FinanceDashboardPageTest extends TestCase
{
    use DatabaseTransactions;

    /** Khoá view() do controller cấp trực tiếp (không qua presenter). */
    private const CONTROLLER_KEYS = ['filters', 'orderStatuses'];

    private const LOOP_AND_BLADE_VARIABLES = ['row', 'status', 'loop', 'errors', 'slot', 'attributes', 'component'];

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(resource_path('views/finance/index.blade.php'));

        $this->assertStringNotContainsString('@php', $source, 'view còn khối @php');
        $this->assertStringNotContainsString('number_format(', $source, 'view còn tự định dạng số');
        $this->assertStringNotContainsString('Carbon', $source, 'view còn tự parse ngày');
        $this->assertStringNotContainsString('auth()', $source, 'view còn tự hỏi quyền');
        $this->assertStringNotContainsString('request(', $source, 'view còn tự đọc request');

        $data = (new FinanceDashboardPresenter)->viewData([], []);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');

        preg_match_all('/\$row->([a-zA-Z]+)/', $source, $hit);
        $props = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass(FinanceOrderProfitRow::class))->getProperties()
        );
        $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
            'view đọc thuộc tính không có của $row');
    }

    /**
     * Nguồn view sau khi bỏ khối <style>: không CSS nhúng, không `!important`, không Bootstrap,
     * mọi lớp là `tw:*` hoặc icon `bi*`.
     *
     * Kèm chốt chặn cho một lỗi ĐÃ XẢY RA: lớp `tw:after:content-[""]` chứa dấu NHÁY KÉP, mà
     * thuộc tính `class="…"` cũng dùng nháy kép — trình duyệt coi thuộc tính kết thúc ngay đó và
     * BỎ mọi lớp viết sau. Vòng trang trí `::after` của thẻ KPI mất sạch màu mà computed style
     * của PHẦN TỬ vẫn khớp 100%. Phải viết nháy đơn: `content-['']`.
     */
    public function test_view_khong_con_css_nhung(): void
    {
        $source = (string) file_get_contents(resource_path('views/finance/index.blade.php'));

        $this->assertStringNotContainsString('<style', $source, 'view còn khối <style>');
        $this->assertStringNotContainsString('!important', $source, 'view còn !important');
        $this->assertStringNotContainsString('data-bs-', $source, 'view còn Bootstrap JS');
        $this->assertStringNotContainsString('finance-', $source, 'view còn lớp BEM cũ');

        $this->assertStringNotContainsString('content-[""]', $source,
            'nháy KÉP trong lớp làm vỡ thuộc tính class — dùng content-[\'\']');
        $this->assertSame(8, substr_count($source, "content-['']"),
            'tám thẻ KPI phải còn vòng trang trí ::after');

        // Mọi lớp trong `class=` chỉ còn `tw:*` hoặc icon `bi*`.
        // `(?<![-:\w])` để không vơ luôn `x-bind:class` / `:class` của Alpine.
        preg_match_all('/(?<![-:\w])class="([^"]*)"/', $source, $m);
        $la = [];
        foreach ($m[1] as $danhSach) {
            foreach (preg_split('/\s+/', trim($danhSach)) ?: [] as $lop) {
                if ($lop === '' || str_starts_with($lop, 'tw:') || str_starts_with($lop, 'bi')) {
                    continue;
                }
                if (str_contains($lop, '{{') || str_contains($lop, '}}') || str_contains($lop, '$')) {
                    continue;
                }
                $la[] = $lop;
            }
        }
        $this->assertSame([], array_values(array_unique($la)), 'view còn lớp không phải tw:* hoặc icon');
    }

    /**
     * Presenter trả về CHUỖI LỚP TAILWIND, không còn tên lớp Bootstrap.
     *
     * Trang này từng ĐỊNH NGHĨA LẠI `.text-success`/`.text-danger` bằng `!important` ngay trong
     * khối <style> của nó. Bỏ khối đó mà giữ tên lớp là màu rơi về bản Bootstrap — sai im lặng.
     */
    public function test_presenter_khong_tra_ve_lop_bootstrap(): void
    {
        $data = (new FinanceDashboardPresenter)->viewData(['grossProfit' => -1, 'netCashFlow' => -1], []);

        foreach (['grossProfitClass', 'netCashFlowClass', 'netCashFlowBadgeClass'] as $khoa) {
            $this->assertStringStartsWith('tw:', $data[$khoa], "{$khoa} còn tên lớp không phải tw:*");
        }

        $source = (string) file_get_contents(
            app_path('View/Presenters/Finance/FinanceDashboardPresenter.php')
        );
        foreach (["'text-success'", "'text-danger'", 'finance-badge--', 'finance-margin--'] as $cu) {
            $this->assertStringNotContainsString($cu, $source, "presenter còn tên lớp cũ {$cu}");
        }
    }

    public function test_trang_that_in_dung_so_va_tong(): void
    {
        $now = '2026-09-01 08:30:00';
        $kt = $this->userWithRole('accounting', [
            'name' => 'KT Dashboard', 'email' => 'fin-dash@example.test',
        ], ['page.finance']);

        // `crm_warehouses` và `crm_order_items` KHÔNG có timestamps; `line_total` là cột
        // GENERATED STORED nên không được insert (đã đọc database/schema/mysql-schema.sql).
        $wid = (int) DB::table('crm_warehouses')->insertGetId(['name' => 'Kho Dashboard']);
        $pid = (int) DB::table('crm_product_catalog')->insertGetId([
            'name' => 'Tấm pin Dashboard', 'sku' => 'SKU-FIN-DASH', 'price_agent' => 6_000_000,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $cid = (int) DB::table('crm_customers')->insertGetId([
            'name' => 'Khách Dashboard', 'phone' => '0915000111',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $lid = (int) DB::table('crm_leads')->insertGetId([
            'customer_id' => $cid, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $oid = (int) DB::table('crm_orders')->insertGetId([
            'lead_id' => $lid, 'order_code' => 'FIN-DASH-1', 'current_department' => 'completed',
            'order_date' => '2026-09-01', 'total_amount' => 20_000_000, 'payment_recorded' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('crm_order_items')->insert([
            'order_id' => $oid, 'warehouse_id' => $wid, 'product_id' => $pid,
            'quantity' => 2, 'unit_price' => 10_000_000,
        ]);

        $html = (string) $this->actingAs($kt)->get('/finance')->assertOk()->getContent();

        $this->assertStringContainsString('Tổng quan tài chính', $html);
        $this->assertStringContainsString('FIN-DASH-1', $html);
        $this->assertStringContainsString('01/09/2026', $html, 'ngày d/m/Y');
        // 2 × 10tr bán, 2 × 6tr giá vốn -> lãi 8tr, biên 40%.
        $this->assertStringContainsString('20.000.000 đ', $html);
        $this->assertStringContainsString('12.000.000 đ', $html);
        $this->assertStringContainsString('8.000.000 đ', $html);
        $this->assertStringContainsString('40,00%', $html, 'phần trăm dấu VIỆT (phẩy thập phân)');
        $this->assertStringNotContainsString('40.00%', $html, 'không được còn dấu Anh');
        // Nhãn biên lợi nhuận DƯƠNG: nay là chuỗi lớp Tailwind, không còn tên biến thể BEM.
        $this->assertStringContainsString('tw:bg-[rgba(22,163,74,0.10)]', $html, 'biên dương -> nền xanh');
        $this->assertStringContainsString('tw:text-[#166534]', $html);
    }
}
