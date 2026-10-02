<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * `shipping_note` đã được 2026_01_20_092228_add_shipping_fields_to_crm_orders
 * thêm trước, nên migration này chỉ còn tác dụng với những DB đã chạy qua bản cũ.
 * Bọc hasColumn để chạy lại từ DB rỗng không chết.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('crm_orders', 'shipping_note')) {
            return;
        }

        if (Schema::hasTable('crm_orders')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_orders', 'shipping_note')) {
                    $table->text('shipping_note')->nullable()->after('shipping_address');
                }
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('crm_orders', 'shipping_note')) {
            return;
        }

        if (Schema::hasTable('crm_orders')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->dropColumn('shipping_note');
            });
        }
    }
};
