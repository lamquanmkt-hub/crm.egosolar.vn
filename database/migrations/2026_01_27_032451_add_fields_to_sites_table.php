<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * TRÙNG HOÀN TOÀN với khối ALTER trong 2026_01_21_070759_create_sites_table:
 * cùng 11 cột. Bản kia có bọc hasColumn, bản này thì không, nên migrate trên DB
 * rỗng chết với "Duplicate column name 'status'".
 *
 * Giữ file lại (đã chạy trên production, xoá là lệch lịch sử migration) nhưng
 * bọc điều kiện để nó thành no-op khi cột đã có.
 */
return new class extends Migration
{
    /** @var array<string, callable(Blueprint): void> */
    private function columns(): array
    {
        return [
            'status' => fn (Blueprint $t) => $t->string('status', 50)->nullable()->after('name'),
            'stage' => fn (Blueprint $t) => $t->string('stage', 50)->nullable()->after('note'),
            'system_kwp' => fn (Blueprint $t) => $t->decimal('system_kwp', 10, 2)->nullable()->after('note'),
            'system_kw_ac' => fn (Blueprint $t) => $t->decimal('system_kw_ac', 10, 2)->nullable()->after('system_kwp'),
            'system_type' => fn (Blueprint $t) => $t->string('system_type', 50)->nullable()->after('system_kw_ac'),
            'phase' => fn (Blueprint $t) => $t->string('phase', 50)->nullable()->after('system_type'),
            'installed_at' => fn (Blueprint $t) => $t->date('installed_at')->nullable()->after('phase'),
            'warranty_to' => fn (Blueprint $t) => $t->date('warranty_to')->nullable()->after('installed_at'),
            'technician_name' => fn (Blueprint $t) => $t->string('technician_name')->nullable()->after('warranty_to'),
            'monitoring_link' => fn (Blueprint $t) => $t->string('monitoring_link')->nullable()->after('technician_name'),
            'monitoring_account' => fn (Blueprint $t) => $t->string('monitoring_account')->nullable()->after('monitoring_link'),
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('sites')) {
            return;
        }

        $missing = array_filter(
            $this->columns(),
            static fn (callable $add, string $column): bool => ! Schema::hasColumn('sites', $column),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing === []) {
            return;
        }

        if (Schema::hasTable('sites')) {
            Schema::table('sites', function (Blueprint $table) use ($missing) {
                foreach ($missing as $add) {
                    $add($table);
                }
            });
        }
    }

    public function down(): void
    {
        // Không tự xoá: các cột này do migration create_sites_table sở hữu.
    }
};
