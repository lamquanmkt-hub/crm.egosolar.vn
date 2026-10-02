<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tên file là "add_warehouse_id" nhưng bản cũ CHỈ thêm khoá ngoại, không thêm
 * cột — cột `warehouse_id` được tạo ngoài migration (DDL lúc chạy trong
 * controller). Hệ quả: migrate trên DB rỗng chết với
 * "Key column 'warehouse_id' doesn't exist in table".
 *
 * Nay thêm cột khi thiếu, rồi mới gắn khoá ngoại — và chỉ gắn khi bảng đích đã
 * có, khoá chưa tồn tại. DB đã chạy qua bản cũ không bị ảnh hưởng.
 */
return new class extends Migration
{
    private const FK = 'material_requests_warehouse_id_foreign';

    public function up(): void
    {
        if (! Schema::hasTable('material_requests')) {
            return;
        }

        if (! Schema::hasColumn('material_requests', 'warehouse_id')) {
            Schema::table('material_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('material_requests', 'warehouse_id')) {
                    $table->unsignedBigInteger('warehouse_id')->nullable()->index();
                }
            });
        }

        if (! Schema::hasTable('crm_warehouses') || $this->foreignKeyExists()) {
            return;
        }

        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreign('warehouse_id', self::FK)
                ->references('id')
                ->on('crm_warehouses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('material_requests', 'warehouse_id')) {
            return;
        }

        if (Schema::hasTable('material_requests')) {
            Schema::table('material_requests', function (Blueprint $table) {
                if ($this->foreignKeyExists()) {
                    $table->dropForeign(self::FK);
                }

                $table->dropColumn('warehouse_id');
            });
        }
    }

    /** Khoá ngoại đã tồn tại chưa — Schema::hasIndex không phân biệt được FK. */
    private function foreignKeyExists(): bool
    {
        return \Illuminate\Support\Facades\DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE()
               AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            ['material_requests', self::FK, 'FOREIGN KEY'],
        ) !== null;
    }
};
