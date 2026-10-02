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
        if (Schema::hasTable('content_feedbacks')) {
            Schema::table('content_feedbacks', function (Blueprint $table) {
                if (! Schema::hasColumn('content_feedbacks', 'parent_id')) {
                    $table->unsignedBigInteger('parent_id')->nullable()->after('id');
                }
                $table->index('parent_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('content_feedbacks')) {
            Schema::table('content_feedbacks', function (Blueprint $table) {
                $table->dropColumn('parent_id');
            });
        }
    }
};
