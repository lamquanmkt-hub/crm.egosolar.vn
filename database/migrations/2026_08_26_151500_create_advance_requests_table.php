<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('advance_requests')) {
            Schema::create('advance_requests', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('company')->nullable();
                $table->string('recipient_name');
                $table->decimal('amount', 18, 2)->default(0);
                $table->text('reason');
                $table->date('needed_date')->nullable();
                $table->date('settlement_due_date')->nullable();
                $table->string('bank_name')->nullable();
                $table->string('bank_account')->nullable();
                $table->string('bank_account_name')->nullable();
                $table->string('status', 40)->default('draft')->index();
                $table->text('note')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('admin_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('admin_approved_at')->nullable();
                $table->foreignId('accounting_approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('accounting_approved_at')->nullable();
                $table->timestamps();
                $table->index(['created_by', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_requests');
    }
};
