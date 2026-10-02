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
        if (Schema::hasTable('crm_order_items')) {
            Schema::table('crm_order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_order_items', 'discount_percent')) {
                    $table->decimal('discount_percent', 5, 2)
                        ->default(0)
                        ->after('unit_price');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('crm_order_items')) {
            Schema::table('crm_order_items', function (Blueprint $table) {
                $table->dropColumn('discount_percent');
            });
        }
    }
};
