<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (! Schema::hasColumn('messages', 'attachment_path')) {
                    $table->string('attachment_path')->nullable()->after('body');
                }
                if (! Schema::hasColumn('messages', 'attachment_name')) {
                    $table->string('attachment_name')->nullable()->after('attachment_path');
                }
                if (! Schema::hasColumn('messages', 'attachment_mime')) {
                    $table->string('attachment_mime', 150)->nullable()->after('attachment_name');
                }
                if (! Schema::hasColumn('messages', 'attachment_size')) {
                    $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size']);
            });
        }
    }
};
