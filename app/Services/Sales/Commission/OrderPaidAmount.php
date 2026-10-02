<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use Illuminate\Support\Facades\DB;

/**
 * Số tiền đã thu của một đơn.
 *
 * Nguồn đúng nhất là bảng phiếu thu (cộng dồn từng lần thu). Cột `paid_amount`
 * trên đơn chỉ là bản tóm tắt, có thể lỗi thời, nên chỉ dùng khi không tổng hợp
 * được từ phiếu thu.
 */
final class OrderPaidAmount
{
    /** Phiếu thu ở các trạng thái này không phải tiền thật đã vào. */
    private const VOIDED_PAYMENT_STATUSES = ['cancel', 'cancelled', 'canceled', 'void', 'failed', 'deleted'];

    public function __construct(private readonly OrderColumnMap $cols) {}

    public function forOrder(object $order): float
    {
        $orderId = $order->id ?? null;

        if ($this->cols->paymentTable && $this->cols->paymentOrderCol && $this->cols->paymentAmountCol && $orderId) {
            $sum = $this->sumOfPayments($orderId);

            if ($sum > 0) {
                return $sum;
            }
        }

        $paidCol = $this->cols->paidCol;

        if ($paidCol && isset($order->{$paidCol})) {
            return (float) ($order->{$paidCol} ?? 0);
        }

        return 0.0;
    }

    /** Lỗi truy vấn được coi như "chưa thu", để còn rơi xuống cột tóm tắt trên đơn. */
    private function sumOfPayments(mixed $orderId): float
    {
        try {
            $query = DB::table((string) $this->cols->paymentTable)
                ->where((string) $this->cols->paymentOrderCol, $orderId);

            if ($this->cols->paymentStatusCol) {
                $query->whereNotIn($this->cols->paymentStatusCol, self::VOIDED_PAYMENT_STATUSES);
            }

            return (float) $query->sum((string) $this->cols->paymentAmountCol);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}
