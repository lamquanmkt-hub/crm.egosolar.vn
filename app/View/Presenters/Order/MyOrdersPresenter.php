<?php

declare(strict_types=1);

namespace App\View\Presenters\Order;

use App\DTOs\Order\MyOrderRow;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị trang `orders/my-orders` (bảng điều khiển đơn hàng của sales đang đăng nhập).
 *
 * Thay hai khối `@php`:
 *  1. khối trong `@forelse` dựng lại bản đồ 6 bộ phận → tông badge cho TỪNG dòng;
 *  2. khối tính `% hoàn thành` / `% đang xử lý` cho thanh tiến độ.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 *
 * ⚠️ `departmentBadge` trả về vẫn là TÊN LỚP BOOTSTRAP (`secondary`, `info`…) — CỐ Ý giữ y bản cũ
 * để HTML không đổi một byte nào. Quy đổi sang `tw:*` là đợt đổi giao diện riêng: đổi lớp thì
 * không so byte được nữa, phải đo computed style. Cùng cách `WeeklyTaskListPresenter` đã làm.
 */
final class MyOrdersPresenter
{
    /**
     * Bộ phận → tông badge. Chép đúng mảng cũ, kể cả chỗ dùng mã cũ (`ketoan`, `duyet1`, `duyet2`,
     * `kho`) chứ không phải giá trị của `OrderDepartment` hiện tại — đổi là đổi màu đang hiển thị.
     */
    private const DEPARTMENT_BADGES = [
        'sales' => 'tw:bg-[#6c757d]',
        'ketoan' => 'tw:bg-[#0dcaf0]',
        'duyet1' => 'tw:bg-[#ffc107]',
        'duyet2' => 'tw:bg-[#0d6efd]',
        'kho' => 'tw:bg-[#212529]',
        'completed' => 'tw:bg-[#198754]',
    ];

    private const BADGE_DEFAULT = 'tw:bg-[#6c757d]';

    /** Màu nền badge trạng thái khi đơn chưa gắn trạng thái nào. */
    private const STATUS_COLOR_DEFAULT = '#6c757d';

    /**
     * @param  array<string, mixed>  $statistics  bốn con số từ `OrderService::getSalesStatistics()`
     * @param  iterable<object>  $recentOrders  đã eager load `lead.customer` và `currentStatusType`
     * @return array{orderRows: list<MyOrderRow>, completedPercent: float, pendingPercent: float}
     */
    public function viewData(array $statistics, iterable $recentOrders): array
    {
        // Guard chia-cho-0 của bản cũ: `?: 1` nên người chưa có đơn nào vẫn ra 0%, không phải NaN.
        $total = ((int) ($statistics['total_orders'] ?? 0)) ?: 1;

        $rows = [];
        foreach ($recentOrders as $order) {
            $customer = $order->lead->customer ?? null;
            $status = $order->currentStatusType ?? null;
            $department = (string) ($order->current_department ?? '');

            $rows[] = new MyOrderRow(
                id: (int) ($order->id ?? 0),
                orderCode: (string) ($order->order_code ?? ''),
                customerName: (string) ($customer->name ?? ''),
                customerPhone: (string) ($customer->phone ?? ''),
                // Bản cũ: `number_format($x, 0, ',', '.')` — trùng đúng `DisplayFormat::number()`.
                // KHÔNG dùng `::money()`: hàm đó thêm ' đ' CÓ khoảng trắng, còn view in liền `đ`.
                totalText: DisplayFormat::number($order->total_amount ?? 0),
                statusName: (string) ($status->name ?? 'N/A'),
                statusColor: (string) ($status->color ?? self::STATUS_COLOR_DEFAULT),
                departmentLabel: ucfirst($department),
                departmentBadge: self::DEPARTMENT_BADGES[$department] ?? self::BADGE_DEFAULT,
            );
        }

        return [
            'orderRows' => $rows,
            'completedPercent' => round(((int) ($statistics['completed'] ?? 0)) / $total * 100),
            'pendingPercent' => round(((int) ($statistics['pending'] ?? 0)) / $total * 100),
        ];
    }
}
