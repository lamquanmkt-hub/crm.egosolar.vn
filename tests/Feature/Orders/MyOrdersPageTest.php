<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\DTOs\Order\MyOrderRow;
use App\View\Presenters\Order\MyOrdersPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trang `orders/my-orders` sau khi dời 2 khối `@php` sang MyOrdersPresenter (2026-09-28).
 *
 * Guard hai chiều: view chỉ in, mọi biến do controller/presenter cấp, `$order->x` là thuộc tính
 * DTO thật; và trang thật in đúng số theo dữ liệu.
 */
final class MyOrdersPageTest extends TestCase
{
    use DatabaseTransactions;

    /** Khoá view() do controller cấp (không qua presenter). */
    private const CONTROLLER_KEYS = ['statistics', 'pendingNotifications'];

    private const LOOP_AND_BLADE_VARIABLES = ['order', 'notif', 'loop', 'errors', 'slot', 'attributes', 'component'];

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(resource_path('views/orders/my-orders.blade.php'));

        $this->assertStringNotContainsString('@php', $source, 'view còn khối @php');
        $this->assertStringNotContainsString('auth()', $source, 'view còn tự hỏi quyền');
        $this->assertStringNotContainsString('request(', $source, 'view còn tự đọc request');

        // Trang đã chuyển XONG: hết lớp Bootstrap, hết Bootstrap JS, không có <style>.
        $this->assertStringNotContainsString('<style', $source);
        $this->assertStringNotContainsString('data-bs-', $source);
        foreach (['class="badge', 'shadow-sm', 'table-responsive', 'table-light', 'fs-1',
            'border-start', 'progress-bar', 'container-fluid', 'text-success', 'alert-heading'] as $lop) {
            $this->assertStringNotContainsString($lop, $source, "view dùng lại lớp Bootstrap: {$lop}");
        }

        // Không thêm `!important` mới: trang này vốn không có dấu nào.
        $this->assertSame(0, preg_match_all('/tw:[^"\s]+!/', $source),
            'thêm `!` mới — cân nhắc biến CSS / size="none" / đổi thuộc tính trước khi dùng `!`');

        $data = (new MyOrdersPresenter)->viewData(
            ['total_orders' => 0, 'pending' => 0, 'completed' => 0],
            [],
        );

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');

        preg_match_all('/\$order->([a-zA-Z]+)/', $source, $hit);
        $props = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass(MyOrderRow::class))->getProperties()
        );
        $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
            'view đọc thuộc tính không có của $order');
    }

    public function test_trang_that_in_dung_so_va_dung_tong_badge(): void
    {
        // `page.orders` phải cấp TƯỜNG MINH cho vai không phải admin (EnforcePageAccess).
        $sales = $this->userWithRole('sales', [], ['page.orders']);
        $now = now()->format('Y-m-d H:i:s');

        $customerId = (int) DB::table('crm_customers')->insertGetId([
            'name' => 'Khách Guard', 'phone' => '0911222333', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $leadId = (int) DB::table('crm_leads')->insertGetId([
            'customer_id' => $customerId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $statusId = (int) DB::table('crm_order_status_types')->insertGetId([
            'code' => 'guard_duyet', 'name' => 'Chờ duyệt', 'department' => 'sales', 'color' => '#0d6efd',
        ]);

        // 4 đơn: 1 completed + 3 đang xử lý -> 25% hoàn thành, 75% đang xử lý.
        foreach (['completed', 'kho', 'ketoan', 'sales'] as $i => $dept) {
            DB::table('crm_orders')->insert([
                'lead_id' => $leadId,
                'order_code' => 'ORD-GUARD-'.$i,
                'current_department' => $dept,
                'current_status_type_id' => $statusId,
                'total_amount' => 2_500_000,
                'created_by' => $sales->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $html = (string) $this->actingAs($sales)->get('/orders/my/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('ORD-GUARD-0', $html);
        $this->assertStringContainsString('Khách Guard', $html);
        $this->assertStringContainsString('0911222333', $html);
        $this->assertStringContainsString('2.500.000đ', $html, 'tiền kiểu VN, `đ` nối LIỀN không có dấu cách');

        // Tông badge của từng bộ phận, đúng MÀU của bản đồ cũ. Nay là chuỗi lớp Tailwind đầy đủ
        // chứ không phải tên tông: `bg-{{ $tone }}` ghép lúc chạy thì Tailwind không sinh ra lớp.
        $this->assertStringContainsString('tw:bg-[#198754]', $html, 'completed -> success #198754');
        $this->assertStringContainsString('tw:bg-[#212529]', $html, 'kho -> dark #212529');
        $this->assertStringContainsString('tw:bg-[#0dcaf0]', $html, 'ketoan -> info #0dcaf0');
        $this->assertStringContainsString('tw:bg-[#6c757d]', $html, 'sales -> secondary #6c757d');

        // 1/4 hoàn thành, 3/4 đang xử lý.
        $this->assertStringContainsString('Hoàn thành: 25%', $html);
        $this->assertStringContainsString('Đang xử lý: 75%', $html);
    }

    public function test_chua_co_don_nao_thi_khong_chia_cho_0(): void
    {
        $sales = $this->userWithRole('sales', [], ['page.orders']);

        $html = (string) $this->actingAs($sales)->get('/orders/my/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Hoàn thành: 0%', $html, 'không có đơn -> 0%, không phải NaN');
        $this->assertStringContainsString('Chưa có đơn hàng nào', $html);
    }
}
