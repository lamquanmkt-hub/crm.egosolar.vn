<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proposals')) {
            Schema::table('proposals', function (Blueprint $table) {
                if (! Schema::hasColumn('proposals', 'payment_receiver')) {
                    $table->string('payment_receiver')->nullable()->after('amount');
                }
                if (! Schema::hasColumn('proposals', 'bank_name')) {
                    $table->string('bank_name')->nullable()->after('payment_receiver');
                }
                if (! Schema::hasColumn('proposals', 'bank_account')) {
                    $table->string('bank_account', 100)->nullable()->after('bank_name');
                }
                if (! Schema::hasColumn('proposals', 'bank_account_name')) {
                    $table->string('bank_account_name')->nullable()->after('bank_account');
                }
            });
        }

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
        if (Schema::hasTable('proposals')) {
            foreach (['bank_account_name', 'bank_account', 'bank_name', 'payment_receiver'] as $column) {
                if (Schema::hasColumn('proposals', $column)) {
                    Schema::table('proposals', fn (Blueprint $table) => $table->dropColumn($column));
                }
            }
        }
    }
};
