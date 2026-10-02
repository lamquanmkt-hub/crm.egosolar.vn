<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_correction_requests')) {
            return;
        }

        Schema::create('attendance_correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attendance_record_id')->constrained('attendance_records')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('work_date')->index();

            $table->dateTime('original_check_in_at')->nullable();
            $table->dateTime('original_check_out_at')->nullable();
            $table->dateTime('requested_check_in_at');
            $table->dateTime('requested_check_out_at')->nullable();
            $table->dateTime('approved_check_in_at')->nullable();
            $table->dateTime('approved_check_out_at')->nullable();

            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();

            // MySQL/SQLite cho phép nhiều NULL trong unique index: bảo đảm chỉ một đơn pending/bản công.
            $table->unsignedTinyInteger('pending_guard')->nullable()->default(1);
            $table->unique(['attendance_record_id', 'pending_guard'], 'attendance_correction_one_pending_unique');

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('before_apply_snapshot')->nullable();
            $table->json('applied_snapshot')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'attendance_correction_user_status_idx');
            $table->index(['status', 'created_at'], 'attendance_correction_review_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_requests');
    }
};
