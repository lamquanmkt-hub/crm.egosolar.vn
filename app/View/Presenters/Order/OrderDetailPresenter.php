<?php

declare(strict_types=1);

namespace App\View\Presenters\Order;

use App\DTOs\Order\OrderFlowStepView;
use App\DTOs\Order\OrderProductLineView;
use App\DTOs\Order\OrderVatSummary;
use App\Enums\OrderDepartment;
use App\Models\CRM\Orders\Order;
use App\Models\CRM\Orders\OrderItem;
use App\Models\User;
use App\Services\Order\OrderItemCalculator;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị giá trị cho view `orders.show` (trang chi tiết đơn một trang).
 *
 * Trước 2026-09-07 view tự tính trong 5 khối `@php` (225 dòng): trạng thái và luồng duyệt, quyền
 * hậu mãi theo permission/vai trò, vùng khách, thanh toán, cảnh báo, tab đang mở; lớp CSS từng bước
 * duyệt; và bảng sản phẩm tách VAT (100 dòng). Thành tiền sau chiết khấu của dòng hàng nay đi qua
 * OrderItemCalculator::calcLineTotal — một luật chiết khấu duy nhất với lúc lưu đơn (khác duy nhất:
 * làm tròn 2 số lẻ). Tên bộ phận lấy từ {@see OrderDepartment} (một nguồn, chốt 2026-09-07); riêng
 * bước "Vận chuyển" trên dải luồng chỉ là bước trình bày, không phải bộ phận nghiệp vụ.
 * Tên khoá trả về giữ tên biến cũ của view; kiểm bằng so HTML 9 trang/tab.
 */
final class OrderDetailPresenter
{
    public const TABS = ['approval', 'payments', 'inventory', 'shipping', 'returns', 'invoice', 'documents', 'history'];

    /** Bước trình bày duy nhất không có trong OrderDepartment. */
    private const SHIPPING_STEP = 'shipping';

    private const FLOW = ['sales', 'sales_manager', 'accounting', 'management', 'warehouse', self::SHIPPING_STEP, 'completed'];

    private const ACTIVE_RETURN_EXCLUDED = ['completed', 'rejected', 'cancelled'];

    public function __construct(private readonly OrderItemCalculator $calculator) {}

    /**
     * @param  array<int, int|float>  $returnAvailable  số lượng còn trả được theo dòng hàng (controller cấp)
     * @param  string|null  $currentApprovalLevel  cấp duyệt đang xử lý; thiếu thì lấy bộ phận của đơn
     * @param  string|null  $requestedTab  `?tab=` trên URL; không hợp lệ thì về tab duyệt
     * @return array<string, mixed>
     */
    public function viewData(Order $order, ?User $user, array $returnAvailable, ?string $currentApprovalLevel, ?string $requestedTab): array
    {
        $customer = $order->lead?->customer ?? $order->customer ?? null;
        $payments = collect($order->payments ?? []);
        $paid = (float) $payments->sum('amount');
        $total = (float) ($order->total_amount ?? 0);
        $remaining = max(0, $total - $paid);
        $returns = collect($order->returns ?? []);
        $activeReturns = $returns->whereNotIn('status', self::ACTIVE_RETURN_EXCLUDED);
        $refunds = $returns->flatMap(fn ($return) => $return->refunds ?? []);
        $department = strtolower((string) ($order->current_department ?? 'sales'));
        $resolvedDepartment = OrderDepartment::fromLegacy($department);
        $currentFlow = $resolvedDepartment?->value ?? $department;
        $currentIndex = array_search($currentFlow, self::FLOW, true);
        if ($currentIndex === false) {
            $currentIndex = 0;
        }
        $isCancelled = $resolvedDepartment === OrderDepartment::CANCELLED;
        $inventoryIssued = (bool) ($order->inventory_issued ?? false);
        $invoiceStatus = (string) ($order->invoice_status ?? 'none');
        $can = fn (string $permission, array $roles): bool => ($user?->can($permission) ?? false) || $this->hasAnyRole($user, $roles);
        $canReturnCreate = $can('orders.return.create', ['admin', 'management', 'sales_manager', 'sales']);
        $productLines = $order->items->map(fn (OrderItem $item) => $this->productLine($item))->values();
        $completedLabel = OrderDepartment::COMPLETED->label();

        return [
            'customer' => $customer,
            'paid' => $paid,
            'total' => $total,
            'remaining' => $remaining,
            'paymentPercent' => $total > 0 ? min(100, round(($paid / $total) * 100)) : 0,
            'returns' => $returns,
            'activeReturns' => $activeReturns,
            'refundPaid' => (float) $refunds->where('status', 'paid')->sum('amount'),
            'department' => $department,
            'departmentLabel' => OrderDepartment::labelFor($department),
            'currentFlow' => $currentFlow,
            'isCancelled' => $isCancelled,
            'statusName' => $isCancelled
                ? OrderDepartment::CANCELLED->label()
                : ($inventoryIssued
                    ? ($remaining > 0 ? $completedLabel.' · còn công nợ' : $completedLabel)
                    : ($order->currentStatusType->name ?? OrderDepartment::labelFor($department))),
            'invoiceStatus' => $invoiceStatus,
            'canReturnApprove' => $can('orders.return.approve', ['admin', 'management', 'accounting', 'sales_manager']),
            'canReturnReceive' => $can('orders.return.receive', ['admin', 'warehouse', 'kho']),
            'canReturnInspect' => $can('orders.return.inspect', ['admin', 'warehouse', 'kho']),
            'canReturnStockIn' => $can('orders.return.stock_in', ['admin', 'warehouse', 'kho']),
            'canRefundCreate' => $can('orders.refund.create', ['admin', 'accounting', 'sales_manager']),
            'canRefundApprove' => $can('orders.refund.approve', ['admin', 'management', 'accounting']),
            // Nút "Đã xử lý tiền" của phiếu hoàn: view cũ chỉ xét vai trò, không có permission riêng.
            'canRefundProcess' => $this->hasAnyRole($user, ['admin', 'accounting']),
            // EGO_COMPLETED_RETURN_VIEW_RULE_V1
            'canCreateCompletedReturn' => $canReturnCreate
                && $inventoryIssued
                && $department === OrderDepartment::COMPLETED->value
                && (int) collect($returnAvailable)->sum() > 0,
            'customerRegion' => $this->customerRegion($customer),
            'lastPayment' => $payments->sortByDesc(fn ($payment) => (string) ($payment->payment_date ?? $payment->created_at ?? ''))->first(),
            'nextActionText' => $isCancelled ? 'Đơn hàng đã hủy' : $this->nextAction($currentFlow, $inventoryIssued, $activeReturns->count() > 0),
            'hasWarnings' => $remaining > 0
                || (! $inventoryIssued && $currentFlow === OrderDepartment::WAREHOUSE->value)
                || ($inventoryIssued && ! $order->is_shipped)
                || $activeReturns->count() > 0
                || ($returns->count() > 0 && ! in_array($invoiceStatus, ['none', 'not_issued', ''], true)),
            'activeTab' => in_array($requestedTab, self::TABS, true) ? $requestedTab : 'approval',
            'isAccountingApproval' => OrderDepartment::fromLegacy($currentApprovalLevel ?? $department) === OrderDepartment::ACCOUNTING,
            'flowSteps' => $this->flowSteps($currentFlow, $currentIndex, $isCancelled),
            'productLines' => $productLines,
            'vatSummary' => $this->vatSummary($productLines),
        ];
    }

    /** @return list<OrderFlowStepView> */
    private function flowSteps(string $currentFlow, int $currentIndex, bool $isCancelled): array
    {
        $flowCompleted = $currentFlow === OrderDepartment::COMPLETED->value;
        $steps = [];
        foreach (self::FLOW as $index => $key) {
            $done = ! $isCancelled && ($flowCompleted ? $index <= $currentIndex : $index < $currentIndex);
            $active = ! $isCancelled && ! $flowCompleted && $index === $currentIndex;
            $steps[] = new OrderFlowStepView(
                key: $key,
                label: $key === self::SHIPPING_STEP ? 'Vận chuyển' : OrderDepartment::from($key)->label(),
                number: $index + 1,
                done: $done,
                active: $active,
                class: $done ? 'done' : ($active ? 'active' : 'pending'),
            );
        }

        return $steps;
    }

    private function productLine(OrderItem $item): OrderProductLineView
    {
        $quantity = max(1, (int) ($item->quantity ?? 1));
        $vatPercent = max(0, (float) ($item->vat_percent ?? $item->product?->vat_percent ?? 0));
        $unitAfter = max(0, (float) ($item->unit_price ?? 0));
        $unitBefore = $vatPercent > 0 ? $unitAfter / (1 + $vatPercent / 100) : $unitAfter;
        $lineAfter = $this->calculator->calcLineTotal(
            $quantity,
            $unitAfter,
            (float) ($item->discount_percent ?? 0),
            max(0, (float) ($item->discount_amount ?? 0)),
        );
        $lineBefore = $vatPercent > 0 ? $lineAfter / (1 + $vatPercent / 100) : $lineAfter;

        return new OrderProductLineView(
            item: $item,
            quantity: $quantity,
            vatPercent: $vatPercent,
            vatLabel: rtrim(rtrim(number_format($vatPercent, 2, '.', ''), '0'), '.'),
            unitBefore: $unitBefore,
            unitAfter: $unitAfter,
            lineBefore: $lineBefore,
            lineAfter: $lineAfter,
            vatAmount: max(0, $lineAfter - $lineBefore),
        );
    }

    /** @param  Collection<int, OrderProductLineView>  $lines */
    private function vatSummary(Collection $lines): OrderVatSummary
    {
        $groups = [];
        foreach ($lines as $line) {
            $groups[$line->vatLabel] = ($groups[$line->vatLabel] ?? 0) + $line->vatAmount;
        }

        return new OrderVatSummary(
            beforeVat: (float) $lines->sum(fn (OrderProductLineView $line) => $line->lineBefore),
            vatAmount: (float) $lines->sum(fn (OrderProductLineView $line) => $line->vatAmount),
            afterVat: (float) $lines->sum(fn (OrderProductLineView $line) => $line->lineAfter),
            groups: $groups,
        );
    }

    private function nextAction(string $currentFlow, bool $inventoryIssued, bool $hasActiveReturns): string
    {
        return match ($currentFlow) {
            'sales' => 'Hoàn thiện và gửi đơn đi duyệt',
            'sales_manager' => 'Sales Manager kiểm tra và phê duyệt',
            'accounting' => 'Kế toán kiểm tra thanh toán và công nợ',
            'management' => 'Ban Giám đốc phê duyệt đơn hàng',
            'warehouse' => $inventoryIssued ? 'Bàn giao đơn cho vận chuyển' : 'Kho kiểm tra tồn, serial và xuất hàng',
            'shipping' => 'Cập nhật giao hàng và xác nhận hoàn tất',
            'completed' => $hasActiveReturns ? 'Theo dõi hồ sơ hậu mãi đang xử lý' : 'Đơn đã hoàn tất, sẵn sàng xử lý hậu mãi khi cần',
            default => 'Kiểm tra trạng thái đơn hàng',
        };
    }

    /** Vùng/khu vực khách: cột chữ, JSON có name/label/title, hay đối tượng — thiếu thì `—`. */
    private function customerRegion(mixed $customer): string
    {
        $region = $customer->region ?? $customer->area ?? null;
        if (is_string($region)) {
            $decoded = json_decode($region, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $region = $decoded['name'] ?? $decoded['label'] ?? $decoded['title'] ?? $region;
            }
        } elseif (is_object($region)) {
            $region = $region->name ?? $region->label ?? $region->title ?? '—';
        }

        return $region ?: '—';
    }

    /** @param  list<string>  $roles */
    private function hasAnyRole(?User $user, array $roles): bool
    {
        return $user !== null && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles);
    }
}
