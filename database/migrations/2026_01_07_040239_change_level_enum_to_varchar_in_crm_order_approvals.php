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
        if (Schema::hasTable('crm_order_approvals')) {
            Schema::table('crm_order_approvals', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_order_approvals', 'level')) {
                    $table->string('level', 50)->change();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('crm_order_approvals')) {
            Schema::table('crm_order_approvals', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_order_approvals', 'level')) {
                    $table->enum('level', [
                        'sales',
                        'ketoan',
                        'duyet1',
                        'duyet2',
                        'kho',
                    ])->change();
                }
            });
        }
    }
};
