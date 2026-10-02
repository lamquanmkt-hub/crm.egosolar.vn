<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\DTOs\Order\OrderFlowStepView;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderItem;
use App\Models\Payments\Payment;
use App\Services\Order\OrderItemCalculator;
use App\View\Presenters\Order\OrderDetailPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * {@see OrderDetailPresenter} thay 5 khối `@php` (225 dòng) của orders/show (2026-09-07).
 * Đơn, dòng hàng, thanh toán dựng trong bộ nhớ; chỉ user (vai trò Spatie) cần DB.
 */
final class OrderDetailPresenterTest extends TestCase
{
    use DatabaseTransactions;

    private OrderDetailPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new OrderDetailPresenter(app(OrderItemCalculator::class));
    }

    public function test_bang_san_pham_tach_vat_va_tong_theo_muc_thue(): void
    {
        $order = $this->order('ketoan', 2350000, [
            $this->item(2, 1000000, 10, 0, 10),          // giảm 10 %, VAT ghi ở dòng
            $this->item(1, 600000, 150, 50000, null, 8), // giảm tiền/SP thắng giảm % (kẹp 100 %), VAT lấy từ sản phẩm
        ]);

        $data = $this->presenter->viewData($order, null, [], null, null);
        $lines = $data['productLines'];

        $this->assertEqualsWithDelta(909090.909, $lines[0]->unitBefore, 0.01, 'đơn giá là giá sau VAT, suy ngược trước VAT');
        $this->assertSame(1800000.0, $lines[0]->lineAfter, '2 × 1.000.000 − 10 %');
        $this->assertEqualsWithDelta(1636363.636, $lines[0]->lineBefore, 0.01);
        $this->assertSame('10', $lines[0]->vatLabel);
        $this->assertSame(550000.0, $lines[1]->lineAfter, 'giảm 50.000/SP thắng giảm 150 % (kẹp 100 %) — cùng luật OrderItemCalculator');
        $this->assertEqualsWithDelta(40740.74, $lines[1]->vatAmount, 0.01);
        $this->assertSame('8', $lines[1]->vatLabel);

        $summary = $data['vatSummary'];
        $this->assertSame(2350000.0, $summary->afterVat);
        $this->assertEqualsWithDelta(2350000 - 1636363.636 - 509259.259, $summary->vatAmount, 0.01);
        $this->assertEqualsWithDelta($summary->afterVat - $summary->vatAmount, $summary->beforeVat, 0.01);
        // Nhãn "10"/"8" thành khoá số nguyên (PHP ép), view in `{{ $rate }}%` nên vẫn ra "10%"; "8.5" mới giữ chuỗi.
        $this->assertSame([10, 8], array_keys($summary->groups), 'mức VAT theo thứ tự xuất hiện của dòng hàng');
        $this->assertEqualsWithDelta(163636.36, $summary->groups[10], 0.01);
    }

    public function test_luong_duyet_thanh_toan_trang_thai_va_tab(): void
    {
        $order = $this->order('ketoan', 2350000, [$this->item(1, 2350000, 0, 0, 0)]);
        $order->setRelation('payments', collect([$this->payment('2026-09-02', 300000), $this->payment('2026-09-03', 200000)]));

        $data = $this->presenter->viewData($order, null, [], null, 'xyz');

        $this->assertSame(['accounting', 'ketoan', 500000.0, 1850000.0, 21.0], [$data['currentFlow'], $data['department'], $data['paid'], $data['remaining'], $data['paymentPercent']]);
        $this->assertSame('2026-09-03', (string) $data['lastPayment']->payment_date);
        $this->assertSame(['done', 'done', 'active', 'pending', 'pending', 'pending', 'pending'], array_map(fn (OrderFlowStepView $step) => $step->class, $data['flowSteps']));
        $this->assertSame([3, 'Kế toán'], [$data['flowSteps'][2]->number, $data['flowSteps'][2]->label]);
        $this->assertSame('Kế toán', $data['statusName'], 'không có loại trạng thái → nhãn bộ phận');
        $this->assertSame('Kế toán kiểm tra thanh toán và công nợ', $data['nextActionText']);
        $this->assertTrue($data['hasWarnings'], 'còn công nợ');
        $this->assertSame('approval', $data['activeTab'], 'tab lạ → tab duyệt');
        $this->assertTrue($data['isAccountingApproval'], 'không có cấp duyệt → theo bộ phận (alias ketoan)');
        $this->assertSame('payments', $this->presenter->viewData($order, null, [], 'sales', 'payments')['activeTab']);
        $this->assertFalse($this->presenter->viewData($order, null, [], 'sales', null)['isAccountingApproval']);
        $this->assertSame('—', $data['customerRegion'], 'không có khách → vùng trống');
    }

    public function test_don_huy_va_don_hoan_tat(): void
    {
        $cancelled = $this->presenter->viewData($this->order('cancelled', 500000, [$this->item(1, 500000, 0, 0, 0)]), null, [], null, null);
        $this->assertTrue($cancelled['isCancelled']);
        $this->assertSame(['Đã hủy', 'Đơn hàng đã hủy'], [$cancelled['statusName'], $cancelled['nextActionText']]);
        $this->assertSame(array_fill(0, 7, 'pending'), array_map(fn (OrderFlowStepView $step) => $step->class, $cancelled['flowSteps']));

        $order = $this->order('completed', 540000, [$this->item(1, 540000, 0, 0, 8)], ['inventory_issued' => 1, 'is_shipped' => 0, 'invoice_status' => 'issued']);
        $order->setRelation('payments', collect([$this->payment('2026-09-04', 540000)]));
        $done = $this->presenter->viewData($order, null, [], null, null);
        $this->assertSame('Hoàn thành', $done['statusName'], 'nhãn theo OrderDepartment::COMPLETED');
        $this->assertSame(array_fill(0, 7, 'done'), array_map(fn (OrderFlowStepView $step) => $step->class, $done['flowSteps']));
        $this->assertTrue($done['hasWarnings'], 'đã xuất kho nhưng chưa giao');
        $this->assertSame('Đơn đã hoàn tất, sẵn sàng xử lý hậu mãi khi cần', $done['nextActionText']);

        $order->forceFill(['is_shipped' => 1]);
        $order->setRelation('payments', collect());
        $debt = $this->presenter->viewData($order, null, [], null, null);
        $this->assertSame('Hoàn thành · còn công nợ', $debt['statusName']);
    }

    public function test_quyen_hau_mai_theo_vai_tro_khi_khong_co_permission(): void
    {
        $sales = $this->userWithRole('sales');
        $accounting = $this->userWithRole('accounting');
        $order = $this->order('completed', 540000, [$this->item(1, 540000, 0, 0, 8)], ['inventory_issued' => 1]);

        $bySales = $this->presenter->viewData($order, $sales, [7 => 1], null, null);
        $this->assertFalse($bySales['canReturnApprove']);
        $this->assertFalse($bySales['canRefundProcess']);
        $this->assertTrue($bySales['canCreateCompletedReturn'], 'sales tạo được hoàn trả khi đơn hoàn tất, đã xuất kho và còn hàng trả được');
        $this->assertFalse($this->presenter->viewData($order, $sales, [7 => 0], null, null)['canCreateCompletedReturn'], 'hết hàng trả được');

        $byAccounting = $this->presenter->viewData($order, $accounting, [], null, null);
        $this->assertTrue($byAccounting['canReturnApprove']);
        $this->assertTrue($byAccounting['canRefundProcess']);
        $this->assertFalse($byAccounting['canReturnReceive'], 'nhận hàng là việc của kho');
        $this->assertFalse($this->presenter->viewData($order, null, [], null, null)['canReturnApprove'], 'khách vãng lai không có quyền');
    }

    /** @param  list<OrderItem>  $items */
    private function order(string $department, float $total, array $items, array $attributes = []): Order
    {
        $order = (new Order)->forceFill(array_merge(['total_amount' => $total, 'current_department' => $department, 'inventory_issued' => 0, 'is_shipped' => 0, 'invoice_status' => 'no_invoice'], $attributes));
        $order->setRelation('items', collect($items));
        $order->setRelation('payments', collect());
        $order->setRelation('returns', collect());
        $order->setRelation('lead', null);
        $order->setRelation('currentStatusType', null);

        return $order;
    }

    private function item(int $qty, float $price, float $pct, float $amount, ?float $vat, ?float $productVat = null): OrderItem
    {
        $item = (new OrderItem)->forceFill(['quantity' => $qty, 'unit_price' => $price, 'discount_percent' => $pct, 'discount_amount' => $amount, 'vat_percent' => $vat]);
        $item->setRelation('product', $productVat === null ? null : (object) ['vat_percent' => $productVat]);

        return $item;
    }

    private function payment(string $date, float $amount): Payment
    {
        return (new Payment)->forceFill(['payment_date' => $date, 'amount' => $amount]);
    }
}
