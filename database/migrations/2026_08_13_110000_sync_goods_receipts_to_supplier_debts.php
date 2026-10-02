<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_supplier_debts')) {
            Schema::table('finance_supplier_debts', function (Blueprint $table) {
                if (! Schema::hasColumn('finance_supplier_debts', 'source_type')) {
                    $table->string('source_type', 50)->nullable()->after('id');
                }
                if (! Schema::hasColumn('finance_supplier_debts', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                }
                if (! Schema::hasColumn('finance_supplier_debts', 'source_code')) {
                    $table->string('source_code', 120)->nullable()->after('source_id');
                }
                if (! Schema::hasColumn('finance_supplier_debts', 'supplier_scope')) {
                    $table->string('supplier_scope', 30)->default('general')->after('source_code');
                }
                if (! Schema::hasColumn('finance_supplier_debts', 'due_date')) {
                    $table->date('due_date')->nullable()->after('debt_month');
                }
            });
        }

        if (Schema::hasTable('finance_supplier_debt_payments')) {
            Schema::table('finance_supplier_debt_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('finance_supplier_debt_payments', 'source_type')) {
                    $table->string('source_type', 50)->nullable()->after('supplier_debt_id');
                }
                if (! Schema::hasColumn('finance_supplier_debt_payments', 'source_id')) {
                    $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
                }
            });
        }

        $this->backfillDomesticGoodsReceipts();
    }

    private function backfillDomesticGoodsReceipts(): void
    {
        if (! Schema::hasTable('product_goods_receipts') || ! Schema::hasTable('finance_supplier_debts')) {
            return;
        }

        $receipts = DB::table('product_goods_receipts')
            ->where('status', 'posted')
            ->orderBy('id')
            ->get();

        foreach ($receipts as $receipt) {
            $debt = DB::table('finance_supplier_debts')
                ->where('source_type', 'product_goods_receipt')
                ->where('source_id', (int) $receipt->id)
                ->first();

            if (! $debt) {
                $match = DB::table('finance_supplier_debts')
                    ->where('supplier_name', (string) $receipt->supplier_name)
                    ->whereBetween('total_amount', [max(0, (float) $receipt->total_amount - 0.5), (float) $receipt->total_amount + 0.5]);

                if (! empty($receipt->invoice_no)) {
                    $match->where('document_no', (string) $receipt->invoice_no);
                }

                $debt = $match->orderByDesc('id')->first();
            }

            $companyName = null;
            if (Schema::hasTable('companies') && ! empty($receipt->company_id)) {
                $companyName = DB::table('companies')->where('id', (int) $receipt->company_id)->value('name');
            }

            $documentDate = $receipt->invoice_date ?: (empty($receipt->posted_at) ? now()->toDateString() : substr((string) $receipt->posted_at, 0, 10));
            $debtMonth = substr((string) $documentDate, 0, 7).'-01';
            $paidAmount = min(max(0, (float) ($receipt->paid_amount ?? 0)), max(0, (float) ($receipt->total_amount ?? 0)));
            $status = $paidAmount >= (float) $receipt->total_amount && (float) $receipt->total_amount > 0
                ? 'paid'
                : ($paidAmount > 0 ? 'partial' : 'unpaid');

            if ($debt) {
                DB::table('finance_supplier_debts')->where('id', $debt->id)->update([
                    'source_type' => 'product_goods_receipt',
                    'source_id' => (int) $receipt->id,
                    'source_code' => (string) $receipt->code,
                    'supplier_scope' => 'domestic',
                    'due_date' => $receipt->payment_due_date ?: null,
                    'updated_at' => now(),
                ]);
                $debtId = (int) $debt->id;
                $hadRounds = Schema::hasTable('finance_supplier_debt_payments')
                    ? DB::table('finance_supplier_debt_payments')->where('supplier_debt_id', $debtId)->exists()
                    : false;
            } else {
                $debtId = (int) DB::table('finance_supplier_debts')->insertGetId([
                    'source_type' => 'product_goods_receipt',
                    'source_id' => (int) $receipt->id,
                    'source_code' => (string) $receipt->code,
                    'supplier_scope' => 'domestic',
                    'supplier_name' => (string) $receipt->supplier_name,
                    'company_name' => $companyName,
                    'document_no' => $receipt->invoice_no ?: $receipt->code,
                    'document_date' => $documentDate,
                    'debt_month' => $debtMonth,
                    'due_date' => $receipt->payment_due_date ?: null,
                    'total_amount' => (float) $receipt->total_amount,
                    'paid_amount' => $paidAmount,
                    'note' => 'Tự động đồng bộ từ phiếu nhập kho '.$receipt->code,
                    'bank_info' => null,
                    'status' => $status,
                    'created_by' => $receipt->posted_by ?: $receipt->created_by,
                    'created_at' => $receipt->posted_at ?: now(),
                    'updated_at' => now(),
                    'company_id' => $receipt->company_id ?: null,
                ]);
                $hadRounds = false;
            }

            if ($paidAmount > 0 && ! $hadRounds && Schema::hasTable('finance_supplier_debt_payments')) {
                DB::table('finance_supplier_debt_payments')->insert([
                    'supplier_debt_id' => $debtId,
                    'source_type' => 'product_goods_receipt_paid',
                    'source_id' => (int) $receipt->id,
                    'payment_request_id' => null,
                    'payment_round' => 1,
                    'amount' => $paidAmount,
                    'payment_date' => $documentDate,
                    'status' => 'paid',
                    'note' => 'Tự động ghi nhận số đã thanh toán từ phiếu nhập '.$receipt->code,
                    'created_by' => $receipt->posted_by ?: $receipt->created_by,
                    'created_at' => $receipt->posted_at ?: now(),
                    'updated_at' => now(),
                ]);

                DB::table('finance_supplier_debts')->where('id', $debtId)->update([
                    'paid_amount' => $paidAmount,
                    'status' => $status,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Không xóa dữ liệu công nợ đã đồng bộ khi rollback để tránh mất lịch sử tài chính.
    }
};
