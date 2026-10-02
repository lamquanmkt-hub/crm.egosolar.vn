<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settlement_requests')) {
            Schema::table('settlement_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('settlement_requests', 'refund_amount')) {
                    $table->decimal('refund_amount', 18, 2)->default(0)->after('actual_amount');
                }
                if (! Schema::hasColumn('settlement_requests', 'attachments')) {
                    $table->json('attachments')->nullable()->after('settlement_type');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('settlement_requests')) {
            Schema::table('settlement_requests', function (Blueprint $table) {
                if (Schema::hasColumn('settlement_requests', 'attachments')) {
                    $table->dropColumn('attachments');
                }
                if (Schema::hasColumn('settlement_requests', 'refund_amount')) {
                    $table->dropColumn('refund_amount');
                }
            });
        }
    }
};
