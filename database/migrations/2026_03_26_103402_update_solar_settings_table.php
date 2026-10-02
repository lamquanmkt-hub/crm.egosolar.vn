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
        if (Schema::hasTable('solar_settings')) {
            Schema::table('solar_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('solar_settings', 'key')) {
                    $table->string('key')->unique()->after('id');
                }
                if (! Schema::hasColumn('solar_settings', 'label')) {
                    $table->string('label')->after('key');
                }
                if (! Schema::hasColumn('solar_settings', 'group')) {
                    $table->string('group')->nullable()->after('label');
                }
                if (! Schema::hasColumn('solar_settings', 'value')) {
                    $table->string('value')->nullable()->after('group');
                }
                if (! Schema::hasColumn('solar_settings', 'type')) {
                    $table->string('type')->default('text')->after('value');
                }
                if (! Schema::hasColumn('solar_settings', 'description')) {
                    $table->text('description')->nullable()->after('type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
