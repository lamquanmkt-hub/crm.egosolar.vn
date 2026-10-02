<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('material_request_activity_histories')) {
            return;
        }

        Schema::create('material_request_activity_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('material_request_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('user_role')->nullable();
            $table->string('action', 80)->index();
            $table->string('status_before', 80)->nullable();
            $table->string('status_after', 80)->nullable();
            $table->json('details')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['material_request_id', 'created_at'], 'mr_activity_request_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_request_activity_histories');
    }
};
