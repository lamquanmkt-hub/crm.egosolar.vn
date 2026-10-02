<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Truy cập công nợ nhà cung cấp và các đợt thanh toán của nó.
 *
 * Tách khỏi {@see PaymentRequestRepository} vì đây là hai bảng khác nhau với
 * vòng đời khác nhau; gộp chung sẽ tạo một lớp biết quá nhiều thứ.
 */
final class SupplierDebtRepository
{
    private const DEBTS = 'finance_supplier_debts';

    private const ROUNDS = 'finance_supplier_debt_payments';

    /**
     * Bảng đợt thanh toán có tồn tại và có liên kết về ĐNTT không.
     *
     * Một số bản triển khai chưa có phân hệ công nợ; mọi thao tác dưới đây phải
     * lặng lẽ bỏ qua thay vì làm hỏng việc xoá phiếu.
     */
    public function roundsLinkable(): bool
    {
        return SchemaCache::hasTable(self::ROUNDS)
            && SchemaCache::hasColumn(self::ROUNDS, 'payment_request_id');
    }

    /**
     * Id các công nợ đang có đợt thanh toán trỏ tới phiếu ĐNTT này.
     *
     * @return list<int>
     */
    public function debtIdsLinkedTo(int $paymentRequestId): array
    {
        if (! $this->roundsLinkable()) {
            return [];
        }

        return DB::table(self::ROUNDS)
            ->where('payment_request_id', $paymentRequestId)
            ->pluck('supplier_debt_id')
            ->filter()
            ->unique()
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Gỡ liên kết các đợt thanh toán khỏi phiếu và trả chúng về trạng thái
     * `planned` — tiền chưa chi thì không được tính là đã chi.
     */
    public function unlinkPaymentRequest(int $paymentRequestId): void
    {
        if (! $this->roundsLinkable()) {
            return;
        }

        DB::table(self::ROUNDS)
            ->where('payment_request_id', $paymentRequestId)
            ->update([
                'payment_request_id' => null,
                'status' => 'planned',
                'updated_at' => now(),
            ]);
    }

    public function findDebt(int $debtId): ?object
    {
        if (! SchemaCache::hasTable(self::DEBTS)) {
            return null;
        }

        return DB::table(self::DEBTS)->where('id', $debtId)->first();
    }

    /**
     * Các đợt thanh toán của một công nợ.
     *
     * @return list<object>
     */
    public function roundsOf(int $debtId): array
    {
        if (! SchemaCache::hasTable(self::ROUNDS)) {
            return [];
        }

        return DB::table(self::ROUNDS)
            ->where('supplier_debt_id', $debtId)
            ->get()
            ->all();
    }

    public function updateBalance(int $debtId, float $paid, string $status): void
    {
        DB::table(self::DEBTS)
            ->where('id', $debtId)
            ->update([
                'paid_amount' => $paid,
                'status' => $status,
                'updated_at' => now(),
            ]);
    }
}
