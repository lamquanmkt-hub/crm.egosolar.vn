<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settlement_requests')) {
            Schema::create('settlement_requests', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->unsignedBigInteger('advance_request_id')->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('company')->nullable();
                $table->string('recipient_name');
                $table->decimal('advance_amount', 18, 2)->default(0);
                $table->decimal('actual_amount', 18, 2)->default(0);
                $table->decimal('difference_amount', 18, 2)->default(0);
                $table->string('settlement_type', 40)->default('balanced');
                $table->text('reason');
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
        Schema::dropIfExists('settlement_requests');
    }
};
