<?php

declare(strict_types=1);

namespace App\View\Presenters\Order;

use App\DTOs\Order\OrderListRow;
use App\Enums\OrderDepartment;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Chuẩn bị giá trị cho view `orders.index` (danh sách đơn hàng). Trước 2026-09-08 view tự tính trong
 * 4 khối `@php`: tổng doanh thu/đã thu/công nợ (ưu tiên tóm tắt toàn phạm vi lọc, rơi về tổng trang),
 * số đơn đã xuất kho và hậu mãi đang xử lý trên trang, bộ lọc nâng cao có mở không (đọc `request()`),
 * bảng nhãn bộ phận riêng; mỗi dòng (bảng + thẻ di động lặp lại): khách, đã thu/còn nợ/%, trạng thái
 * và tông màu, nhãn bộ phận, hậu mãi; và quyền xoá mềm tính lại trong từng dòng (đọc `auth()`).
 * Nhãn bộ phận nay lấy từ {@see OrderDepartment} (một nguồn): `completed` là "Hoàn thành".
 */
final class OrderListPresenter
{
    /** Tham số bộ lọc nâng cao — có mặt trên URL thì mở sẵn khung lọc. */
    private const ADVANCED_FILTER_KEYS = ['created_by', 'payment_filter', 'from_date', 'to_date'];

    /** Hậu mãi đã xong/bị từ chối/huỷ không còn "đang xử lý". */
    private const CLOSED_RETURN_STATUSES = ['completed', 'rejected', 'cancelled'];

    private const CANCELLED_DEPARTMENTS = ['cancelled', 'canceled', 'huy', 'da_huy'];

    /** Vai được xoá mềm đơn ngoài quyền `orders.delete`. */
    private const SOFT_DELETE_ROLES = ['admin', 'super_admin', 'management', 'director'];

    private const UNKNOWN_DEPARTMENT = 'Chưa xác định';

    /**
     * @param  mixed  $orders  paginator của OrderService
     * @param  array<string, mixed>  $orderSummary  tổng theo toàn phạm vi lọc (thiếu khoá nào thì cộng theo trang)
     * @param  array<string, mixed>  $filters  `$request->all()`
     * @return array<string, mixed>
     */
    public function viewData(mixed $orders, array $orderSummary, array $filters, ?User $user): array
    {
        $items = is_object($orders) && method_exists($orders, 'items') ? array_values($orders->items()) : [];
        $rows = array_map(fn (object $order) => $this->row($order), $items);
        $pageTotal = array_sum(array_map(fn (OrderListRow $row) => $row->total, $rows));
        $pagePaid = array_sum(array_map(fn (OrderListRow $row) => $row->paid, $rows));
        $totalAmount = (float) ($orderSummary['total_amount'] ?? $pageTotal);
        $totalPaid = (float) ($orderSummary['total_paid'] ?? $pagePaid);

        return [
            'totalAmount' => $totalAmount,
            'totalPaid' => $totalPaid,
            'totalDebt' => (float) ($orderSummary['total_debt'] ?? max(0, $totalAmount - $totalPaid)),
            'debtOver30' => (float) ($orderSummary['debt_over_30'] ?? 0),
            'pageReturns' => array_sum(array_map(fn (OrderListRow $row) => $row->activeReturnCount, $rows)),
            'pageIssued' => count(array_filter($rows, fn (OrderListRow $row) => (bool) $row->order->inventory_issued)),
            'hasAdvanced' => Arr::hasAny($filters, self::ADVANCED_FILTER_KEYS),
            'canSoftDeleteOrder' => $this->canSoftDelete($user),
            'rows' => $rows,
        ];
    }

    private function row(object $order): OrderListRow
    {
        $paid = (float) collect($order->payments ?? [])->sum('amount');
        $total = (float) ($order->total_amount ?? 0);
        $remain = max(0, $total - $paid);
        $dept = strtolower((string) ($order->current_department ?? ''));
        $isCancelled = in_array($dept, self::CANCELLED_DEPARTMENTS, true);
        $departmentLabel = $dept === '' ? self::UNKNOWN_DEPARTMENT : OrderDepartment::labelFor($dept);
        $returns = collect($order->returns ?? []);

        if ($isCancelled) {
            [$statusText, $statusClass] = ['Đã hủy', 'red'];
        } elseif ($order->inventory_issued) {
            [$statusText, $statusClass] = $remain > 0 ? ['Còn công nợ', 'amber'] : ['Hoàn tất', 'green'];
        } else {
            [$statusText, $statusClass] = [(string) ($order->currentStatusType->name ?? $departmentLabel), 'blue'];
        }

        return new OrderListRow(
            order: $order,
            customer: $order->lead?->customer,
            total: $total,
            paid: $paid,
            remain: (float) $remain,
            payPct: $total > 0 ? (int) min(100, round($paid / $total * 100)) : 0,
            isCancelled: $isCancelled,
            statusText: $statusText,
            statusClass: $statusClass,
            departmentLabel: $departmentLabel,
            activeReturnCount: $returns->whereNotIn('status', self::CLOSED_RETURN_STATUSES)->count(),
            latestReturn: $returns->sortByDesc('id')->first(),
        );
    }

    private function canSoftDelete(?User $user): bool
    {
        return (bool) $user?->can('orders.delete')
            || (bool) $user?->hasAnyRole(self::SOFT_DELETE_ROLES)
            || in_array((string) ($user->role ?? ''), self::SOFT_DELETE_ROLES, true);
    }
}
