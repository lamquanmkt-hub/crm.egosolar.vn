<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'last_seen_at')) {
            if (Schema::hasTable('users')) {
                Schema::table('users', function (Blueprint $table) {
                    if (! Schema::hasColumn('users', 'last_seen_at')) {
                        $table->timestamp('last_seen_at')->nullable()->index()->after('remember_token');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_seen_at')) {
            if (Schema::hasTable('users')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropColumn('last_seen_at');
                });
            }
        }
    }
};
