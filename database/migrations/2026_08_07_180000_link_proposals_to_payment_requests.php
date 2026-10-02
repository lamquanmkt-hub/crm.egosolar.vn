<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_requests') && ! Schema::hasColumn('payment_requests', 'proposal_id')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_requests', 'proposal_id')) {
                    $table->unsignedBigInteger('proposal_id')->nullable()->unique()->after('id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_requests') && Schema::hasColumn('payment_requests', 'proposal_id')) {
            Schema::table('payment_requests', function (Blueprint $table) {
                $table->dropUnique(['proposal_id']);
                $table->dropColumn('proposal_id');
            });
        }
    }
};
