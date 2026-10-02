<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gỡ foreign key cũ nếu có
        try {
            if (Schema::hasTable('material_request_items')) {
                Schema::table('material_request_items', function (Blueprint $table) {
                    $table->dropForeign(['product_id']);
                });
            }
        } catch (\Throwable $e) {
            // bỏ qua nếu không có foreign key
        }

        // Cho phép product_id = null
        DB::statement('ALTER TABLE material_request_items MODIFY product_id BIGINT UNSIGNED NULL');

        // Gắn lại foreign key
        try {
            if (Schema::hasTable('material_request_items')) {
                Schema::table('material_request_items', function (Blueprint $table) {
                    $table->foreign('product_id')
                        ->references('id')
                        ->on('products')
                        ->nullOnDelete();
                });
            }
        } catch (\Throwable $e) {
            // nếu bảng products không đúng tên thì tự sửa lại
        }
    }

    public function down(): void
    {
        try {
            if (Schema::hasTable('material_request_items')) {
                Schema::table('material_request_items', function (Blueprint $table) {
                    $table->dropForeign(['product_id']);
                });
            }
        } catch (\Throwable $e) {
            //
        }

        DB::statement('ALTER TABLE material_request_items MODIFY product_id BIGINT UNSIGNED NOT NULL');

        try {
            if (Schema::hasTable('material_request_items')) {
                Schema::table('material_request_items', function (Blueprint $table) {
                    $table->foreign('product_id')
                        ->references('id')
                        ->on('products')
                        ->cascadeOnDelete();
                });
            }
        } catch (\Throwable $e) {
            //
        }
    }
};
