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
                if (! Schema::hasColumn('crm_orders', 'invoice_company_name')) {
                    $table->string('invoice_company_name')->nullable();
                }
                if (! Schema::hasColumn('crm_orders', 'invoice_tax_code')) {
                    $table->string('invoice_tax_code', 50)->nullable();
                }
                if (! Schema::hasColumn('crm_orders', 'invoice_address')) {
                    $table->string('invoice_address', 500)->nullable();
                }
                if (! Schema::hasColumn('crm_orders', 'invoice_email')) {
                    $table->string('invoice_email')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_orders')) {
            Schema::table('crm_orders', function (Blueprint $table) {
                $table->dropColumn([
                    'invoice_company_name',
                    'invoice_tax_code',
                    'invoice_address',
                    'invoice_email',
                ]);
            });
        }
    }
};
