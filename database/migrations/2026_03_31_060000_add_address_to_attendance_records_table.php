<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                if (! Schema::hasColumn('attendance_records', 'check_in_address')) {
                    $table->string('check_in_address')->nullable()->after('check_in_lng');
                }
                if (! Schema::hasColumn('attendance_records', 'check_out_address')) {
                    $table->string('check_out_address')->nullable()->after('check_out_lng');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_records')) {
            Schema::table('attendance_records', function (Blueprint $table) {
                $table->dropColumn([
                    'check_in_address',
                    'check_out_address',
                ]);
            });
        }
    }
};
