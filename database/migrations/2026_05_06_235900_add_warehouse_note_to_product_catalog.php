<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_product_catalog')) {
            return;
        }

        Schema::table('crm_product_catalog', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_product_catalog', 'warehouse_note')) {
                // Bỏ ->after('note'): trên DB dựng lại từ số 0, cột `note` có thể
                // chưa tồn tại và MariaDB sẽ báo "Unknown column 'note'".
                // Thứ tự cột không ảnh hưởng chức năng.
                $table->text('warehouse_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_product_catalog')) {
            return;
        }

        Schema::table('crm_product_catalog', function (Blueprint $table) {
            if (Schema::hasColumn('crm_product_catalog', 'warehouse_note')) {
                $table->dropColumn('warehouse_note');
            }
        });
    }
};
