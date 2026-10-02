<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solar_provinces')) {
            Schema::table('solar_provinces', function (Blueprint $table) {
                if (! Schema::hasColumn('solar_provinces', 'region_profile_id')) {
                    $table->unsignedBigInteger('region_profile_id')->nullable()->after('region');
                }
                if (! Schema::hasColumn('solar_provinces', 'irradiation_override')) {
                    $table->decimal('irradiation_override', 5, 2)->nullable()->after('sun_hours');
                }

                $table->foreign('region_profile_id')
                    ->references('id')
                    ->on('solar_region_profiles')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solar_provinces')) {
            Schema::table('solar_provinces', function (Blueprint $table) {
                $table->dropForeign(['region_profile_id']);
                $table->dropColumn(['region_profile_id', 'irradiation_override']);
            });
        }
    }
};
