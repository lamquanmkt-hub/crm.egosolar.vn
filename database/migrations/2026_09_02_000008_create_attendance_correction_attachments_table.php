<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_correction_attachments')) {
            return;
        }

        Schema::create('attendance_correction_attachments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('attendance_correction_request_id');
            $table->foreign(
                'attendance_correction_request_id',
                'attendance_correction_attachment_request_fk'
            )->references('id')->on('attendance_correction_requests')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 40)->default('local');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 160)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamps();

            $table->index(
                ['attendance_correction_request_id', 'created_at'],
                'attendance_correction_attachment_request_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_correction_attachments');
    }
};
