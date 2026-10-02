<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Repositories\SupplierDebtRepository;

/**
 * Tính lại số tiền đã trả và trạng thái của công nợ nhà cung cấp.
 *
 * Luật (giữ nguyên từ code cũ trong routes/finance.php):
 * - Đợt thanh toán CÓ gắn ĐNTT: chỉ tính là đã trả khi ĐNTT đó ở trạng thái đã
 *   duyệt/đã chi; số tiền lấy `min(tiền đợt, tiền phiếu)` khi phiếu có số tiền,
 *   để một phiếu nhỏ không "trả thay" cho cả đợt lớn.
 * - Đợt KHÔNG gắn ĐNTT: tính theo trạng thái của chính đợt đó.
 */
final class SupplierDebtBalanceCalculator
{
    /**
     * Trạng thái ĐNTT được coi là tiền đã ra khỏi công ty.
     *
     * @var list<string>
     */
    private const SETTLED_REQUEST_STATUSES = [
        'accounting_approved',
        'paid',
        'completed',
        'complete',
        'done',
        'closed',
    ];

    /**
     * Trạng thái đợt thanh toán độc lập được coi là đã trả.
     *
     * @var list<string>
     */
    private const SETTLED_ROUND_STATUSES = ['paid', 'accounting_approved'];

    public function __construct(
        private readonly SupplierDebtRepository $debts,
        private readonly \App\Contracts\Repositories\PaymentRequestRepositoryInterface $paymentRequests,
    ) {}

    /**
     * Tính lại và ghi lại số dư cho nhiều công nợ.
     *
     * @param  list<int>  $debtIds
     */
    public function recalculate(array $debtIds): void
    {
        foreach ($debtIds as $debtId) {
            $debt = $this->debts->findDebt($debtId);

            if ($debt === null) {
                continue;
            }

            $paid = $this->paidAmountOf($debtId);
            $total = (float) ($debt->total_amount ?? 0);

            $this->debts->updateBalance($debtId, $paid, $this->statusFor($total, $paid));
        }
    }

    /**
     * Tổng tiền thực sự đã trả của một công nợ.
     */
    private function paidAmountOf(int $debtId): float
    {
        $paid = 0.0;

        foreach ($this->debts->roundsOf($debtId) as $round) {
            $roundAmount = (float) ($round->amount ?? 0);

            if (! empty($round->payment_request_id)) {
                $paid += $this->paidViaPaymentRequest((int) $round->payment_request_id, $roundAmount);

                continue;
            }

            if ($this->isSettled((string) ($round->status ?? ''), self::SETTLED_ROUND_STATUSES)) {
                $paid += $roundAmount;
            }
        }

        return $paid;
    }

    /**
     * Phần tiền của một đợt được coi là đã trả nhờ ĐNTT gắn kèm.
     */
    private function paidViaPaymentRequest(int $paymentRequestId, float $roundAmount): float
    {
        $request = $this->paymentRequests->find($paymentRequestId);

        if ($request === null) {
            return 0.0;
        }

        if (! $this->isSettled((string) ($request->status ?? ''), self::SETTLED_REQUEST_STATUSES)) {
            return 0.0;
        }

        $requestAmount = (float) ($request->amount ?? 0);

        return $requestAmount > 0 ? min($roundAmount, $requestAmount) : $roundAmount;
    }

    /**
     * @param  list<string>  $settled
     */
    private function isSettled(string $status, array $settled): bool
    {
        return in_array(strtolower($status), $settled, true);
    }

    private function statusFor(float $total, float $paid): string
    {
        if ($total > 0 && $paid >= $total) {
            return 'paid';
        }

        return $paid > 0 ? 'partial' : 'unpaid';
    }
}
