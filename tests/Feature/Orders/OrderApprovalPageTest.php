<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\DTOs\Order\OrderApprovalItemView;
use App\Enums\Role;
use App\View\Presenters\Order\OrderApprovalPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/** Form duyệt đơn sau khi dời 6 khối `@php` sang OrderApprovalPresenter (2026-09-07). */
final class OrderApprovalPageTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private const VIEW = 'resources/views/orders/approval-form.blade.php';

    private const VIEW_VARIABLES_NOT_FROM_PRESENTER = ['item', 'row', 'errors', 'error', 'message', 'loop', 'slot', 'attributes', 'component'];

    private int $actor = 0;

    protected function seedActorId(): int
    {
        return $this->actor;
    }

    public function test_trang_in_gia_tri_da_tinh(): void
    {
        $user = $this->userWithRole(Role::Admin->value);
        $this->actor = (int) $user->id;
        $this->seedShippableOrder(5, ['current_department' => 'accounting', 'total_amount' => 2000000]);
        DB::table('crm_order_items')->where('id', $this->seed['orderItemId'])->update(['quantity' => 2, 'unit_price' => 1000000, 'discount_percent' => 10]);
        DB::table('crm_payments')->insert(['order_id' => $this->seed['orderId'], 'payment_date' => '2026-09-02', 'amount' => 300000, 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('crm_order_approvals')->insert(['order_id' => $this->seed['orderId'], 'level' => 'sales_manager', 'status' => 'rejected', 'approved_by' => $user->id, 'approved_at' => '2026-09-02 10:15:00']);

        // DB tự tính lại crm_orders.total_amount từ line_total (cột generated) khi thêm dòng hàng → đọc số thật.
        $total = (float) DB::table('crm_orders')->where('id', $this->seed['orderId'])->value('total_amount');

        $html = $this->actingAs($user)->get('/orders/'.$this->seed['orderId'].'/approval')->assertOk()->getContent();

        $this->assertStringContainsString('cấp: <strong>Kế toán</strong>', $html);
        $this->assertStringContainsString('Kiểm tra công nợ', $html, 'kế toán mới thấy khối công nợ');
        $this->assertStringContainsString('<span class="fs-5">'.number_format($total - 300000, 0, ',', '.').' đ</span>', $html, 'tổng đơn − 300.000 đã trả');
        $this->assertStringContainsString('10%', $html);
        $this->assertStringContainsString('-200.000 đ', $html, 'giảm tiền suy từ 10% của 2 × 1.000.000');
        $this->assertStringContainsString('02/09/2026 10:15', $html);
        $this->assertStringContainsString('<span class="badge bg-danger">Từ chối</span>', $html);
        $this->assertStringContainsString('<span class="badge bg-info">Gửi duyệt</span>', $html);
        // Trước 2026-09-07 tiêu đề này luôn là "Accounting" vì vòng lặp lịch sử ghi đè $levelLabel.
        $this->assertStringContainsString('Trách nhiệm của Kế toán:', $html);
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $used = array_values(array_unique(array_diff($m[1], self::VIEW_VARIABLES_NOT_FROM_PRESENTER)));
        $provided = array_keys(app(OrderApprovalPresenter::class)->viewData(new \App\Models\CRM\Orders\Order, null));
        $this->assertSame([], array_values(array_diff($used, $provided)), 'biến view dùng mà presenter không trả');

        preg_match_all('/\$item->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(OrderApprovalItemView::class))->getProperties());
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), 'view đọc thuộc tính dòng hàng không có');
    }
}
