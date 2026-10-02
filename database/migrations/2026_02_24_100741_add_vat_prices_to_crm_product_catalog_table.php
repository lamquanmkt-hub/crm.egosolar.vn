<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (Schema::hasTable('crm_product_catalog')) {
            Schema::table('crm_product_catalog', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_product_catalog', 'price_agent_vat')) {
                    $table->decimal('price_agent_vat', 15, 2)->nullable()->after('price_agent');
                }
                if (! Schema::hasColumn('crm_product_catalog', 'price_retail_vat')) {
                    $table->decimal('price_retail_vat', 15, 2)->nullable()->after('price_retail');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (Schema::hasTable('crm_product_catalog')) {
            Schema::table('crm_product_catalog', function (Blueprint $table) {
                $table->dropColumn(['price_agent_vat', 'price_retail_vat']);
            });
        }
    }
};
