<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\CustomerDebtOrderRow;
use App\DTOs\Finance\CustomerDebtRow;
use App\View\Presenters\Finance\CustomerDebtListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trang `finance/debt-customers` sau khi dời 3 khối `@php` sang CustomerDebtListPresenter và
 * bỏ khối <style> 561 dòng sang Tailwind (2026-09-29).
 *
 * Guard hai chiều: view chỉ in, mọi biến do controller/presenter cấp, `$customer->x` và
 * `$order->x` là thuộc tính DTO thật; và trang thật in đúng số.
 *
 * Diện mạo thì ở DebtCustomersLookBrowserTest — ở đây chỉ chốt phần đọc được từ NGUỒN.
 */
final class CustomerDebtPageTest extends TestCase
{
    use DatabaseTransactions;

    /** Khoá view() do controller cấp (không qua presenter). */
    private const CONTROLLER_KEYS = [
        'fullSummary', 'debtContext',
        'filterQuery', 'filterKeyword', 'filterFromDate', 'filterToDate', 'filterPaymentStatus',
    ];

    private const LOOP_AND_BLADE_VARIABLES = ['customer', 'order', 'loop', 'errors', 'slot', 'attributes', 'component'];

    /** Biến ma thuật của Alpine trong `x-on:` — không phải biến Blade. */
    private const ALPINE_MAGICS = ['event'];

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(resource_path('views/finance/debt-customers.blade.php'));

        $this->assertStringNotContainsString('@php', $source, 'view còn khối @php');
        $this->assertStringNotContainsString('auth()', $source, 'view còn tự hỏi quyền');
        $this->assertStringNotContainsString('request(', $source, 'view còn tự đọc request');
        $this->assertStringNotContainsString('md5(', $source, 'view còn tự băm khoá nhóm');
        $this->assertStringNotContainsString('number_format(', $source, 'view còn tự định dạng tiền');

        $data = (new CustomerDebtListPresenter)->viewData(new LengthAwarePaginator([], 0, 20, 1), []);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, self::ALPINE_MAGICS, array_keys($data));

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');

        foreach (['customer' => CustomerDebtRow::class, 'order' => CustomerDebtOrderRow::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z]+)/', $source, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien ({$dto})");
        }
    }

    /**
     * Nguồn view sau khi bỏ khối <style>: không CSS nhúng, không `!important`, không Bootstrap,
     * và hai móc JS còn nguyên.
     *
     * Hành vi nay là Alpine: không còn khối <script>, không còn `addEventListener`, và không còn
     * lớp `finance-*` nào làm móc JS. Ba tên móc cũ (`finance-toggle-btn`, `finance-delete-debt-form`,
     * `finance-detail-row`) phải biến mất SẠCH — còn sót một cái là dấu hiệu chuyển dở dang.
     */
    public function test_view_khong_con_css_nhung_va_hanh_vi_da_sang_alpine(): void
    {
        $source = (string) file_get_contents(resource_path('views/finance/debt-customers.blade.php'));

        $this->assertStringNotContainsString('<style', $source, 'view còn khối <style>');
        $this->assertStringNotContainsString('!important', $source, 'view còn !important');
        $this->assertStringNotContainsString('data-bs-', $source, 'view còn Bootstrap JS');

        $this->assertStringNotContainsString('<script', $source, 'view còn khối <script>');
        $this->assertStringNotContainsString('addEventListener', $source, 'view còn bắt sự kiện kiểu cũ');
        $this->assertStringNotContainsString('finance-', $source, 'view còn lớp móc JS cũ');

        // Hai hành vi của trang, nay khai bằng Alpine ngay tại thẻ.
        $this->assertStringContainsString('x-data="{ mo: {} }"', $source, 'mất phạm vi Alpine của <tbody>');
        $this->assertSame(1, substr_count($source, 'x-on:click='), 'mất nút mở/đóng chi tiết');
        $this->assertSame(1, substr_count($source, 'x-show='), 'mất hàng chi tiết ẩn/hiện');
        $this->assertSame(1, substr_count($source, 'x-on:submit='), 'mất hỏi xác nhận trước khi xoá');

        // Câu hỏi xác nhận do presenter dựng, view chỉ in qua @js (escape an toàn cho thuộc tính).
        $this->assertStringContainsString('@js($order->deleteConfirmText)', $source);
        $this->assertStringNotContainsString('Xóa khoản công nợ', $source, 'view tự ghép câu hỏi');

        // Sau khi sang Alpine, mọi lớp trong `class=` chỉ còn `tw:*` hoặc icon `bi*`.
        // `(?<![-:\w])` để không vơ luôn `x-bind:class="{ 'bi-dash': … }"` — đó là biểu thức JS.
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

    public function test_hai_bang_deu_dung_component_dung_chung(): void
    {
        $source = (string) file_get_contents(resource_path('views/finance/debt-customers.blade.php'));

        $this->assertSame(0, substr_count($source, '<table'), 'còn thẻ <table> viết tay');
        $this->assertSame(2, substr_count($source, '<x-ui.table '), 'phải đúng 2 bảng qua <x-ui.table>');
        $this->assertSame(2, substr_count($source, '<x-ui.table-wrap>'), 'mỗi bảng một <x-ui.table-wrap>');
    }

    public function test_trang_that_in_dung_so_va_trang_thai(): void
    {
        $kt = $this->userWithRole('accounting', [], ['page.finance']);
        $now = '2026-09-01 08:30:00';

        // Khách CÒN nợ: 1 đơn 10tr chưa ghi nhận thanh toán.
        $cid = (int) DB::table('crm_customers')->insertGetId([
            'name' => 'Khách Guard Nợ', 'phone' => '0912000111', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $lid = (int) DB::table('crm_leads')->insertGetId([
            'customer_id' => $cid, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('crm_orders')->insert([
            'lead_id' => $lid, 'order_code' => 'ORD-GUARD-NO',
            'current_department' => 'completed', 'total_amount' => 10_000_000,
            'payment_recorded' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $html = (string) $this->actingAs($kt)->get('/finance/customer-debts')->assertOk()->getContent();

        $this->assertStringContainsString('Khách Guard Nợ', $html);
        $this->assertStringContainsString('ORD-GUARD-NO', $html);
        $this->assertStringContainsString('10.000.000 đ', $html, 'tiền kiểu VN, CÓ dấu cách trước đ');
        // Huy hiệu nay là chuỗi lớp Tailwind đầy đủ, không còn tên biến thể BEM.
        $this->assertStringContainsString('tw:text-[#b91c1c]', $html, 'còn nợ -> huy hiệu đỏ');
        $this->assertStringContainsString('Công nợ', $html);
        $this->assertStringContainsString('01/09/2026 08:30', $html, 'ngày d/m/Y H:i');
        // Chữ cái đầu tên khách phải nguyên vẹn UTF-8 (mb_substr, không phải substr).
        $this->assertStringContainsString('>K<', str_replace(["\n", ' '], '', $html));
    }

    public function test_don_da_ghi_nhan_thanh_toan_thi_bao_da_hoan_thanh(): void
    {
        $kt = $this->userWithRole('accounting', [], ['page.finance']);
        $now = '2026-09-01 08:30:00';

        $cid = (int) DB::table('crm_customers')->insertGetId([
            'name' => 'Khách Trả Hết', 'phone' => '0912000222', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $lid = (int) DB::table('crm_leads')->insertGetId([
            'customer_id' => $cid, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('crm_orders')->insert([
            'lead_id' => $lid, 'order_code' => 'ORD-GUARD-XONG',
            'current_department' => 'completed', 'total_amount' => 5_000_000,
            'payment_recorded' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $html = (string) $this->actingAs($kt)->get('/finance/customer-debts')->assertOk()->getContent();

        $this->assertStringContainsString('Khách Trả Hết', $html);
        $this->assertStringContainsString('tw:text-[#047857]', $html, 'đã xong -> huy hiệu xanh');
        $this->assertStringContainsString('Đã hoàn thành', $html);
    }
}
