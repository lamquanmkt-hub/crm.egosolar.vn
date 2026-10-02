<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_ledger_entries')) {
            Schema::create('finance_ledger_entries', function (Blueprint $table) {
                $table->id();
                $table->string('direction', 20)->index();
                $table->string('category', 50)->index();
                $table->string('counterparty_name');
                $table->string('company_name')->nullable();
                $table->string('reference_no', 120)->nullable();
                $table->date('reference_date')->nullable();
                $table->date('due_date')->nullable()->index();
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->decimal('paid_amount', 18, 2)->default(0);
                $table->text('bank_info')->nullable();
                $table->text('note')->nullable();
                $table->string('status', 30)->default('unpaid')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['direction', 'category', 'status'], 'finance_ledger_scope_status_idx');
            });
        }

        if (! Schema::hasTable('finance_ledger_transactions')) {
            Schema::create('finance_ledger_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('finance_ledger_entry_id')->index();
                $table->decimal('amount', 18, 2)->default(0);
                $table->date('transaction_date')->index();
                $table->string('method', 30)->default('other');
                $table->unsignedBigInteger('account_id')->nullable()->index();
                $table->string('reference_no', 120)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->foreign('finance_ledger_entry_id', 'finance_ledger_tx_entry_fk')
                    ->references('id')
                    ->on('finance_ledger_entries')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_ledger_transactions');
        Schema::dropIfExists('finance_ledger_entries');
    }
};
