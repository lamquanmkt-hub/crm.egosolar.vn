<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_orders')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_orders', 'shipping_fee_payer')) {
                    $table->enum('shipping_fee_payer', ['seller', 'buyer'])
                        ->default('seller')
                        ->after('shipping_fee');
                }

                if (! Schema::hasColumn('crm_orders', 'invoice_status')) {
                    $table->enum('invoice_status', ['no_invoice', 'pending', 'issued'])
                        ->default('no_invoice')
                        ->after('shipping_fee_payer');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_orders')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->dropColumn(['shipping_fee_payer', 'invoice_status']);
            });
        }
    }
};
