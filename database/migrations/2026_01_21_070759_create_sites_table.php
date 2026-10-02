<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Bảng `sites` trước đây được tạo NGOÀI migration (DDL lúc chạy trong
         * controller), nên file này — dù tên là create_sites_table — chỉ đi ALTER.
         * Hệ quả: migrate trên DB rỗng chết ở đây với "Table 'sites' doesn't exist".
         *
         * Tạo phần khung tối thiểu khi bảng chưa có; các cột còn lại vẫn do chính
         * khối ALTER bên dưới và các migration sau bổ sung, nên DB đã chạy rồi
         * không bị ảnh hưởng.
         */
        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('created_by')->nullable();
                $table->string('name');
                $table->string('address')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('contact_phone')->nullable();
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table) {
                // Trạng thái / giai đoạn
                if (! Schema::hasColumn('sites', 'status')) {
                    $table->string('status', 50)->nullable()->after('name');
                }
                if (! Schema::hasColumn('sites', 'stage')) {
                    $table->string('stage', 50)->nullable()->after('note');
                }

                // Hệ thống
                if (! Schema::hasColumn('sites', 'system_kwp')) {
                    $table->decimal('system_kwp', 10, 2)->nullable()->after('note');
                }
                if (! Schema::hasColumn('sites', 'system_kw_ac')) {
                    $table->decimal('system_kw_ac', 10, 2)->nullable()->after('system_kwp');
                }
                if (! Schema::hasColumn('sites', 'system_type')) {
                    $table->string('system_type', 50)->nullable()->after('system_kw_ac');
                }
                if (! Schema::hasColumn('sites', 'phase')) {
                    $table->string('phase', 50)->nullable()->after('system_type');
                }

                // Ngày tháng
                if (! Schema::hasColumn('sites', 'installed_at')) {
                    $table->date('installed_at')->nullable()->after('phase');
                }
                if (! Schema::hasColumn('sites', 'warranty_to')) {
                    $table->date('warranty_to')->nullable()->after('installed_at');
                }

                // Monitoring / kỹ thuật
                if (! Schema::hasColumn('sites', 'technician_name')) {
                    $table->string('technician_name', 255)->nullable()->after('warranty_to');
                }
                if (! Schema::hasColumn('sites', 'monitoring_link')) {
                    $table->string('monitoring_link', 255)->nullable()->after('technician_name');
                }
                if (! Schema::hasColumn('sites', 'monitoring_account')) {
                    $table->string('monitoring_account', 255)->nullable()->after('monitoring_link');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table) {
                $cols = [
                    'status',
                    'stage',
                    'system_kwp',
                    'system_kw_ac',
                    'system_type',
                    'phase',
                    'installed_at',
                    'warranty_to',
                    'technician_name',
                    'monitoring_link',
                    'monitoring_account',
                ];

                foreach ($cols as $c) {
                    if (Schema::hasColumn('sites', $c)) {
                        $table->dropColumn($c);
                    }
                }
            });
        }
    }
};
