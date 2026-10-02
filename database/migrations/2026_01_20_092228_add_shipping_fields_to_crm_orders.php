<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Thêm nhóm cột giao hàng cho crm_orders.
 *
 * Bọc hasColumn vì `shipping_address` đã được
 * 2025_12_30_083712_update_crm_tables_add_missing_columns thêm trước (có điều
 * kiện). Không bọc thì migrate trên DB rỗng chết ở đây với
 * "Duplicate column name 'shipping_address'" — xem README mục CI.
 */
return new class extends Migration
{
    /** @var array<string, string> tên cột => kiểu */
    private const COLUMNS = [
        'shipping_carrier' => 'string:100',
        'tracking_number' => 'string:100',
        'receiver_name' => 'string:120',
        'receiver_phone' => 'string:30',
        'shipping_address' => 'text',
        'shipping_note' => 'text',
    ];

    public function up(): void
    {
        $missing = array_filter(
            self::COLUMNS,
            static fn (string $spec, string $column): bool => ! Schema::hasColumn('crm_orders', $column),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($missing === []) {
            return;
        }

        if (Schema::hasTable('crm_orders')) {
            Schema::table('crm_orders', function (Blueprint $table) use ($missing) {
                foreach ($missing as $column => $spec) {
                    if (str_starts_with($spec, 'string:')) {
                        $table->string($column, (int) substr($spec, 7))->nullable();
                    } else {
                        $table->text($column)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        $existing = array_keys(array_filter(
            self::COLUMNS,
            static fn (string $spec, string $column): bool => Schema::hasColumn('crm_orders', $column),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($existing === []) {
            return;
        }

        if (Schema::hasTable('crm_orders')) {
            Schema::table('crm_orders', function (Blueprint $table) use ($existing) {
                $table->dropColumn($existing);
            });
        }
    }
};
