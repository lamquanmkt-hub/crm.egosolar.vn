<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table) {
                if (! Schema::hasColumn('conversations', 'name')) {
                    $table->string('name')->nullable()->after('type');
                }
                if (! Schema::hasColumn('conversations', 'is_internal')) {
                    $table->boolean('is_internal')->default(false)->after('name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropColumn(['name', 'is_internal']);
            });
        }
    }
};
