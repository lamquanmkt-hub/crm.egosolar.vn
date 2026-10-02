<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Đổi khoá ngoại product_id từ CASCADE sang RESTRICT.
 *
 * Bọc điều kiện vì trên DB dựng lại từ số 0 khoá ngoại cũ có thể chưa tồn tại —
 * dropForeign khi không có sẽ chết.
 */
return new class extends Migration
{
    private const FK = 'crm_serial_units_product_id_foreign';

    public function up(): void
    {
        $this->rebuild(fn (Blueprint $table) => $table->foreign('product_id', self::FK)
            ->references('id')->on('crm_product_catalog')->restrictOnDelete());
    }

    public function down(): void
    {
        $this->rebuild(fn (Blueprint $table) => $table->foreign('product_id', self::FK)
            ->references('id')->on('crm_product_catalog')->cascadeOnDelete());
    }

    private function rebuild(callable $addForeign): void
    {
        if (! Schema::hasTable('crm_serial_units') || ! Schema::hasTable('crm_product_catalog')) {
            return;
        }

        if ($this->foreignKeyExists()) {
            Schema::table('crm_serial_units', function (Blueprint $table) {
                $table->dropForeign(self::FK);
            });
        }

        Schema::table('crm_serial_units', $addForeign);
    }

    private function foreignKeyExists(): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE()
               AND table_name = ? AND constraint_name = ? AND constraint_type = ?',
            ['crm_serial_units', self::FK, 'FOREIGN KEY'],
        ) !== null;
    }
};
