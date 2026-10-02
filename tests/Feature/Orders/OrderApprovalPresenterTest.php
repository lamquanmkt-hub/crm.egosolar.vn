<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\DTOs\Order\OrderApprovalItemView;
use App\Models\Core\Warehouse;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderApproval;
use App\Models\CRM\Orders\OrderItem;
use App\Models\Inventory\Catalog\Product;
use App\Models\Payments\Payment;
use App\Models\User;
use App\View\Presenters\Order\OrderApprovalPresenter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** {@see OrderApprovalPresenter} thay 6 khối `@php` của orders/approval-form (2026-09-07). Model dựng trong bộ nhớ. */
final class OrderApprovalPresenterTest extends TestCase
{
    private OrderApprovalPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new OrderApprovalPresenter;
    }

    public function test_cap_duyet_nhan_va_co_ke_toan(): void
    {
        $data = $this->presenter->viewData($this->order(), 'Accounting');
        $this->assertSame(['accounting', true, 'Kế toán'], [$data['dept'], $data['isAccounting'], $data['levelLabel']]);

        $data = $this->presenter->viewData($this->order(['current_department' => 'ketoan']), null);
        $this->assertSame(['ketoan', true, 'Kế toán'], [$data['dept'], $data['isAccounting'], $data['levelLabel']], 'thiếu currentLevel → lấy bộ phận của đơn; mã cũ ketoan vẫn được');

        $data = $this->presenter->viewData($this->order(), 'warehouse');
        $this->assertSame([false, 'Kho'], [$data['isAccounting'], $data['levelLabel']], 'nhãn theo OrderDepartment, khớp Role::Warehouse');
        $this->assertSame('Custom', $this->presenter->viewData($this->order(), 'custom')['levelLabel'], 'cấp lạ → viết hoa chữ đầu');
    }

    public function test_dong_hang_tong_va_kho_xuat(): void
    {
        $order = $this->order(['total_amount' => 2350000], items: [
            $this->item('Pin 450W', 2, 1000000, 10, 0, 1800000, 'Kho A'),
            $this->item('Inverter', 1, 600000, 150, 50000, 550000, 'Kho B'),
            $this->item(null, 3, 100000, 0, 0, 300000, 'Kho A'),
        ]);

        $data = $this->presenter->viewData($order, 'management');

        $this->assertSame(2650000.0, $data['computedTotal']);
        $this->assertSame('Kho A, Kho B', $data['warehouseText']);
        $this->assertContainsOnlyInstancesOf(OrderApprovalItemView::class, $data['itemViews']);
        $this->assertSame(['Pin 450W', 2, 1000000.0, 10.0, 200000.0, 1800000.0], array_values((array) $data['itemViews'][0]), 'không lưu giảm tiền → suy từ %');
        $this->assertSame(['Inverter', 1, 600000.0, 100.0, 50000.0, 550000.0], array_values((array) $data['itemViews'][1]), '% kẹp về 100; giảm tiền DB giữ nguyên');
        $this->assertSame('-', $data['itemViews'][2]->productName);

        $order = $this->order(items: [$this->item('X', 1, 1, 0, 0, 1, null)]);
        $order->setRelation('warehouse', (new Warehouse)->forceFill(['name' => 'Kho đơn']));
        $this->assertSame('Kho đơn', $this->presenter->viewData($order, 'sales')['warehouseText'], 'dòng hàng không có kho → kho của đơn');
        $this->assertSame('N/A', $this->presenter->viewData($this->order(), 'sales')['warehouseText']);
    }

    public function test_cong_no_va_lich_su_duyet(): void
    {
        $manager = (new User)->forceFill(['id' => 7, 'name' => 'Quản Lý Sales']);
        $order = $this->order(['total_amount' => 2350000], payments: [300000, 200000], approvals: [
            $this->approval('sales', 'pending', null, null),
            $this->approval('Sales_Manager', 'rejected', $manager, '2026-09-02 10:15:00'),
        ]);

        $data = $this->presenter->viewData($order, 'accounting');

        $this->assertSame(1850000.0, $data['remainingDebt']);
        $this->assertSame(
            [['sales', 'Sales', '-', '-', 'bg-info', 'Gửi duyệt'], ['sales_manager', 'Sales Manager', 'Quản Lý Sales', '02/09/2026 10:15', 'bg-danger', 'Từ chối'], ['accounting', 'Kế toán', '-', '-', 'bg-warning', 'Chờ xử lý']],
            array_map('array_values', $data['approvalRows']),
        );

        $order = $this->order(['total_amount' => 100], payments: [500], approvals: [$this->approval('accounting', 'approved', $manager, null, '2026-09-03 08:00:00')]);
        $data = $this->presenter->viewData($order, 'management');
        $this->assertSame(0.0, $data['remainingDebt'], 'trả dư → 0, không âm');
        $this->assertSame(['accounting', 'Kế toán', 'Quản Lý Sales', '03/09/2026 08:00', 'bg-success', 'Đã duyệt'], array_values($data['approvalRows'][2]), 'không có approved_at → lấy created_at');
    }

    /**
     * @param  list<OrderItem>  $items
     * @param  list<float>  $payments
     * @param  list<OrderApproval>  $approvals
     */
    private function order(array $attributes = [], array $items = [], array $payments = [], array $approvals = []): Order
    {
        $order = (new Order)->forceFill($attributes + ['id' => 1, 'order_code' => 'T-1', 'current_department' => 'sales', 'total_amount' => 0]);
        $order->setRelation('items', new EloquentCollection($items));
        $order->setRelation('payments', new EloquentCollection(array_map(fn (float $amount) => (new Payment)->forceFill(['amount' => $amount]), $payments)));
        $order->setRelation('approvals', new EloquentCollection($approvals));
        $order->setRelation('warehouse', null);

        return $order;
    }

    private function item(?string $productName, int $qty, float $price, float $percent, float $amount, float $lineTotal, ?string $warehouseName): OrderItem
    {
        $item = (new OrderItem)->forceFill(['quantity' => $qty, 'unit_price' => $price, 'discount_percent' => $percent, 'discount_amount' => $amount, 'line_total' => $lineTotal]);
        $item->setRelation('product', $productName === null ? null : (new Product)->forceFill(['name' => $productName]));
        $item->setRelation('warehouse', $warehouseName === null ? null : (new Warehouse)->forceFill(['name' => $warehouseName]));

        return $item;
    }

    private function approval(string $level, string $status, ?User $approver, ?string $approvedAt, ?string $createdAt = null): OrderApproval
    {
        $approval = (new OrderApproval)->forceFill(['level' => $level, 'status' => $status, 'approved_at' => $approvedAt ? Carbon::parse($approvedAt) : null, 'created_at' => $createdAt ? Carbon::parse($createdAt) : null]);
        $approval->setRelation('approver', $approver);

        return $approval;
    }
}
