<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_calendar_weekly_metrics')) {
            Schema::table('content_calendar_weekly_metrics', function (Blueprint $table) {
                if (! Schema::hasColumn('content_calendar_weekly_metrics', 'feedback')) {
                    $table->text('feedback')->nullable()->after('note');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('content_calendar_weekly_metrics')) {
            Schema::table('content_calendar_weekly_metrics', function (Blueprint $table) {
                $table->dropColumn('feedback');
            });
        }
    }
};
