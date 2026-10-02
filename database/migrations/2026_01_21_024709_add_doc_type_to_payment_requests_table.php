<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_requests')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_requests', 'doc_type')) {
                    $table->string('doc_type')->default('payment_request')->after('code');
                }
                // payment_request | payment_voucher | advance
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_requests')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                $table->dropColumn('doc_type');
            });
        }
    }
};
