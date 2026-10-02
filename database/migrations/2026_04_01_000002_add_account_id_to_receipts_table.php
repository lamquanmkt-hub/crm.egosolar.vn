<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('receipts', 'account_id')) {
                    $table->foreignId('account_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('accounts')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('receipts')) {
            Schema::table('receipts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('account_id');
            });
        }
    }
};
