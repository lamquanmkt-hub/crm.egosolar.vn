<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_office_incidents')) {
            return;
        }

        Schema::create('hr_office_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_code', 40)->unique();
            $table->string('reported_by');
            $table->string('department')->nullable();
            $table->string('location');
            $table->text('description');
            $table->unsignedTinyInteger('severity')->default(2);
            $table->dateTime('reported_at');
            $table->dateTime('sla_due_at');
            $table->string('assignee')->nullable();
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->string('approval_status', 30)->default('not_required');
            $table->string('status', 30)->default('received');
            $table->text('resolution')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('completion_evidence_path')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'sla_due_at']);
            $table->index(['severity', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_office_incidents');
    }
};
