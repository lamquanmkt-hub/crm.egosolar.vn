<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketing_metrics')) {
            Schema::table('marketing_metrics', function (Blueprint $table) {
                if (! Schema::hasColumn('marketing_metrics', 'reach')) {
                    $table->unsignedInteger('reach')->default(0)->after('leads');
                } // số người tiếp cận
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketing_metrics')) {
            Schema::table('marketing_metrics', function (Blueprint $table) {
                $table->dropColumn('reach');
            });
        }
    }
};
