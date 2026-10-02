<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\DTOs\Order\OrderListRow;
use App\Models\CRM\Orders\Order;
use App\View\Presenters\Order\OrderListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * {@see OrderListPresenter} thay 4 khối `@php` của view `orders.index` (2026-09-08): tổng trang/tóm tắt,
 * trạng thái + tông màu, nhãn bộ phận một nguồn (OrderDepartment), hậu mãi, quyền xoá mềm.
 */
final class OrderListPresenterTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/orders/index.blade.php';

    /** Khoá view() của OrderController@index (không do presenter cấp). */
    private const CONTROLLER_KEYS = ['orders', 'orderSummary', 'companies', 'creators'];

    private const LOOP_AND_BLADE_VARIABLES = ['row', 'c', 'u', 'k', 'v', 'errors', 'error', 'e', 'loop', 'slot', 'attributes', 'component'];

    public function test_trang_thai_tong_mau_nhan_bo_phan_va_hau_mai(): void
    {
        $make = function (array $attrs, array $payments = [], array $returns = [], ?string $statusTypeName = null): Order {
            $order = (new Order)->forceFill($attrs + ['total_amount' => 1000, 'inventory_issued' => 0]);
            $order->setRelation('payments', collect(array_map(fn ($amount) => (object) ['amount' => $amount], $payments)));
            $order->setRelation('returns', collect(array_map(fn ($r) => (object) $r, $returns)));
            $order->setRelation('currentStatusType', $statusTypeName === null ? null : (object) ['name' => $statusTypeName]);
            $order->setRelation('lead', null);

            return $order;
        };
        $orders = [
            $make(['current_department' => 'accounting'], [300, '200'], [], 'Chờ kế toán'),
            $make(['current_department' => 'ketoan'], []),
            $make(['current_department' => 'completed', 'inventory_issued' => 1], [1000], [['id' => 1, 'status' => 'completed', 'return_code' => 'R1'], ['id' => 2, 'status' => 'pending_accounting', 'return_code' => 'R2']]),
            $make(['current_department' => 'completed', 'inventory_issued' => '1'], [400], [['id' => 3, 'status' => 'rejected']]),
            $make(['current_department' => 'Da_Huy'], [1200]),
            $make(['current_department' => null, 'total_amount' => 0]),
        ];
        $data = (new OrderListPresenter)->viewData($this->paginate($orders), [], [], null);
        $rows = $data['rows'];

        $this->assertContainsOnlyInstancesOf(OrderListRow::class, $rows);
        $this->assertSame(['Chờ kế toán', 'blue', 'Kế toán', 500.0, 500.0, 50], [$rows[0]->statusText, $rows[0]->statusClass, $rows[0]->departmentLabel, $rows[0]->paid, $rows[0]->remain, $rows[0]->payPct], 'chưa xuất kho: tên loại trạng thái, tiền đã thu cộng cả chuỗi số');
        $this->assertSame(['Kế toán', 'Kế toán', 0], [$rows[1]->statusText, $rows[1]->departmentLabel, $rows[1]->payPct], 'không loại trạng thái → nhãn bộ phận; alias ketoan');
        $this->assertSame(['Hoàn tất', 'green', 'Hoàn thành', 1, 2, 100], [$rows[2]->statusText, $rows[2]->statusClass, $rows[2]->departmentLabel, $rows[2]->activeReturnCount, $rows[2]->latestReturn->id, $rows[2]->payPct], 'đã xuất kho trả đủ; hậu mãi đang xử lý 1, mới nhất theo id');
        $this->assertSame(['Còn công nợ', 'amber', 0, 3, 40], [$rows[3]->statusText, $rows[3]->statusClass, $rows[3]->activeReturnCount, $rows[3]->latestReturn->id, $rows[3]->payPct]);
        $this->assertSame(['Đã hủy', 'red', true, 'Đã hủy', 0.0], [$rows[4]->statusText, $rows[4]->statusClass, $rows[4]->isCancelled, $rows[4]->departmentLabel, $rows[4]->remain], 'alias huỷ không phân biệt hoa thường; không nợ âm');
        $this->assertSame(['Chưa xác định', 'Chưa xác định', 0, null], [$rows[5]->statusText, $rows[5]->departmentLabel, $rows[5]->payPct, $rows[5]->latestReturn], 'không bộ phận');
        $this->assertSame([5000.0, 3100.0, 1900.0, 0.0, 1, 2, false, false], [$data['totalAmount'], $data['totalPaid'], $data['totalDebt'], $data['debtOver30'], $data['pageReturns'], $data['pageIssued'], $data['hasAdvanced'], $data['canSoftDeleteOrder']], 'không tóm tắt → cộng theo trang; không user → không xoá mềm');
    }

    public function test_tom_tat_thang_tong_trang_bo_loc_nang_cao_va_quyen_xoa_mem(): void
    {
        $presenter = new OrderListPresenter;
        $data = $presenter->viewData(null, ['total_amount' => '10', 'total_paid' => 4, 'total_debt' => 6, 'debt_over_30' => 2.5], ['created_by' => ''], null);
        $this->assertSame([10.0, 4.0, 6.0, 2.5, true, []], [$data['totalAmount'], $data['totalPaid'], $data['totalDebt'], $data['debtOver30'], $data['hasAdvanced'], $data['rows']], 'tham số nâng cao có mặt (dù rỗng) → mở khung lọc; không paginator → không dòng');
        $this->assertFalse($presenter->viewData(null, [], ['status' => 'x'], null)['hasAdvanced']);

        $this->assertTrue($presenter->viewData(null, [], [], $this->userWithRole('admin'))['canSoftDeleteOrder'], 'admin theo vai');
        $this->assertFalse($presenter->viewData(null, [], [], $this->userWithRole('sales'))['canSoftDeleteOrder']);
        $this->assertTrue($presenter->viewData(null, [], [], $this->userWithRole('sales', [], ['orders.delete']))['canSoftDeleteOrder'], 'sales có quyền orders.delete');
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys((new OrderListPresenter)->viewData(null, [], [], null)));
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)), 'biến view dùng mà presenter/controller không cấp');

        preg_match_all('/\$row->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(OrderListRow::class))->getProperties());
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), 'view đọc thuộc tính không có của $row');
    }

    /** @param  list<object>  $items */
    private function paginate(array $items): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, count($items), 20);
    }
}
