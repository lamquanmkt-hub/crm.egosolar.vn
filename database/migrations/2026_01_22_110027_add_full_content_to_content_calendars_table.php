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
        if (Schema::hasTable('content_calendars')) {
            Schema::table('content_calendars', function (Blueprint $table) {
                if (! Schema::hasColumn('content_calendars', 'full_content')) {
                    $table->longText('full_content')->nullable();
                }
                if (! Schema::hasColumn('content_calendars', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable();
                }
                if (! Schema::hasColumn('content_calendars', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if (! Schema::hasColumn('content_calendars', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('content_calendars')) {
            Schema::table('content_calendars', function (Blueprint $table) {
                //
            });
        }
    }
};
