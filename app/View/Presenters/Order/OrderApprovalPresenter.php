<?php

declare(strict_types=1);

namespace App\View\Presenters\Order;

use App\DTOs\Order\OrderApprovalItemView;
use App\Enums\OrderDepartment;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderApproval;
use App\Models\CRM\Orders\OrderItem;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị giá trị cho view `orders.approval-form`.
 *
 * Trước 2026-09-07 view tự tính trong 6 khối `@php`: cấp duyệt + nhãn, tổng theo dòng hàng, tên kho
 * xuất, giảm giá từng dòng, công nợ còn lại, và bảng lịch sử duyệt. Tên khoá trả về giữ tên
 * biến cũ của view; kiểm bằng so HTML 4 đơn ở 4 cấp duyệt.
 */
final class OrderApprovalPresenter
{
    /** Ba bước ghi vào lịch sử duyệt (bước Sales là "gửi duyệt"); nhãn lấy từ {@see OrderDepartment}. */
    private const APPROVAL_STEPS = ['sales', 'sales_manager', 'accounting'];

    /**
     * @param  string|null  $currentLevel  cấp đang xử lý; thiếu thì lấy `current_department` của đơn
     * @return array<string, mixed>
     */
    public function viewData(Order $order, ?string $currentLevel): array
    {
        $dept = strtolower((string) ($currentLevel ?? ($order->current_department ?? '')));
        $items = $order->items ?? collect();
        $approvals = collect($order->approvals ?? [])->keyBy(fn (OrderApproval $approval) => strtolower((string) ($approval->level ?? '')));

        return [
            'order' => $order,
            'dept' => $dept,
            'isAccounting' => OrderDepartment::fromLegacy($dept) === OrderDepartment::ACCOUNTING,
            'levelLabel' => OrderDepartment::labelFor($dept),
            'computedTotal' => (float) $items->sum(fn (OrderItem $item) => (float) ($item->line_total ?? 0)),
            'warehouseText' => $this->warehouseText($order, $items),
            'itemViews' => $items->map(fn (OrderItem $item) => $this->itemView($item))->values(),
            'remainingDebt' => max(0.0, (float) ($order->total_amount ?? 0) - (float) $order->payments->sum('amount')),
            'approvalRows' => collect(self::APPROVAL_STEPS)
                ->map(fn (string $level) => $this->approvalRow($level, OrderDepartment::from($level)->label(), $approvals->get($level)))
                ->values()
                ->all(),
        ];
    }

    /**
     * Kho xuất: gom tên kho của các dòng hàng; không có thì kho của đơn; không có nữa thì N/A.
     *
     * @param  Collection<int, OrderItem>  $items
     */
    private function warehouseText(Order $order, Collection $items): string
    {
        $names = $items
            ->map(fn (OrderItem $item) => $item->warehouse->name ?? $item->kho->name ?? null)
            ->filter()
            ->unique()
            ->values();

        return $names->isNotEmpty() ? $names->implode(', ') : ($order->warehouse->name ?? 'N/A');
    }

    private function itemView(OrderItem $item): OrderApprovalItemView
    {
        $qty = (int) ($item->quantity ?? 0);
        $price = (float) ($item->unit_price ?? 0);
        $discountPercent = max(0, min(100, (float) ($item->discount_percent ?? 0)));
        $discountAmount = (float) ($item->discount_amount ?? 0);
        if ($discountAmount <= 0 && $discountPercent > 0) {
            $discountAmount = $qty * $price * $discountPercent / 100;
        }

        return new OrderApprovalItemView(
            productName: $item->product->name ?? '-',
            qty: $qty,
            unitPrice: $price,
            discountPercent: $discountPercent,
            discountAmount: $discountAmount,
            lineTotal: (float) ($item->line_total ?? 0),
        );
    }

    /**
     * Một dòng lịch sử duyệt: người duyệt, thời điểm (approved_at, thiếu thì created_at), nhãn +
     * màu trạng thái. Bước Sales luôn là "Gửi duyệt".
     *
     * @return array{level: string, label: string, approverName: string, displayTime: string, badgeClass: string, statusText: string}
     */
    private function approvalRow(string $level, string $label, ?OrderApproval $approval): array
    {
        $displayTime = '-';
        if ($approval) {
            $displayTime = $approval->approved_at
                ? $approval->approved_at->format('d/m/Y H:i')
                : ($approval->created_at ? $approval->created_at->format('d/m/Y H:i') : '-');
        }

        [$badgeClass, $statusText] = match (true) {
            $level === 'sales' => ['bg-info', 'Gửi duyệt'],
            $approval?->status === 'approved' => ['bg-success', 'Đã duyệt'],
            $approval?->status === 'rejected' => ['bg-danger', 'Từ chối'],
            default => ['bg-warning', 'Chờ xử lý'],
        };

        return [
            'level' => $level,
            'label' => $label,
            'approverName' => $approval->approver->name ?? '-',
            'displayTime' => $displayTime,
            'badgeClass' => $badgeClass,
            'statusText' => $statusText,
        ];
    }
}
