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
        // Thêm cột giá đại lý, giá bán lẻ, quantity vào sản phẩm
        if (Schema::hasTable('crm_product_catalog')) {
            Schema::table('crm_product_catalog', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_product_catalog', 'price_agent')) {
                    $table->decimal('price_agent', 15, 2)->default(0)->after('price');
                }
                if (! Schema::hasColumn('crm_product_catalog', 'price_retail')) {
                    $table->decimal('price_retail', 15, 2)->default(0)->after('price_agent');
                }
                if (! Schema::hasColumn('crm_product_catalog', 'quantity')) {
                    $table->integer('quantity')->default(0)->after('price_retail');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('crm_product_catalog')) {
            Schema::table('crm_product_catalog', function (Blueprint $table) {
                $table->dropColumn(['price_agent', 'price_retail', 'quantity']);
            });
        }
    }
};
