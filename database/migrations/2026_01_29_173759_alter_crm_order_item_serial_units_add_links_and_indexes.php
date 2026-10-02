<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('crm_order_item_serial_units')) {
            Schema::table('crm_order_item_serial_units', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_order_item_serial_units', 'order_item_id')) {
                    $table->foreignId('order_item_id')
                        ->after('id')
                        ->constrained('crm_order_items')
                        ->cascadeOnDelete();
                }

                if (! Schema::hasColumn('crm_order_item_serial_units', 'serial_unit_id')) {
                    $table->foreignId('serial_unit_id')
                        ->after('order_item_id')
                        ->constrained('crm_serial_units')
                        ->restrictOnDelete();
                    $table->softDeletes();
                }

                /*
                 * Bọc hasIndex: bảng có thể đã mang sẵn các index này từ migration
                 * tạo bảng, nên chạy lại từ DB rỗng sẽ chết với "Duplicate key name".
                 */
                if (! Schema::hasIndex('crm_order_item_serial_units', 'crm_oisu_item_unit_uk')) {
                    $table->unique(['order_item_id', 'serial_unit_id'], 'crm_oisu_item_unit_uk');
                }

                if (! Schema::hasIndex('crm_order_item_serial_units', 'crm_oisu_unit_uk')) {
                    $table->unique(['serial_unit_id'], 'crm_oisu_unit_uk'); // chống bán trùng
                }

                if (! Schema::hasIndex('crm_order_item_serial_units', 'crm_oisu_item_idx')) {
                    $table->index(['order_item_id'], 'crm_oisu_item_idx');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('crm_order_item_serial_units')) {
            Schema::table('crm_order_item_serial_units', function (Blueprint $table) {
                // Drop index/unique trước
                if (Schema::hasIndex('crm_order_item_serial_units', 'crm_oisu_item_idx')) {
                    $table->dropIndex('crm_oisu_item_idx');
                }
                if (Schema::hasIndex('crm_order_item_serial_units', 'crm_oisu_unit_uk')) {
                    $table->dropUnique('crm_oisu_unit_uk');
                }
                if (Schema::hasIndex('crm_order_item_serial_units', 'crm_oisu_item_unit_uk')) {
                    $table->dropUnique('crm_oisu_item_unit_uk');
                }

                // Drop FK + cột (Laravel sẽ tự tạo tên constraint theo convention,
                // nhưng dropConstrainedForeignId sẽ handle tốt)
                if (Schema::hasColumn('crm_order_item_serial_units', 'serial_unit_id')) {
                    $table->dropConstrainedForeignId('serial_unit_id');
                }

                if (Schema::hasColumn('crm_order_item_serial_units', 'order_item_id')) {
                    $table->dropConstrainedForeignId('order_item_id');
                }
            });
        }
    }
};
