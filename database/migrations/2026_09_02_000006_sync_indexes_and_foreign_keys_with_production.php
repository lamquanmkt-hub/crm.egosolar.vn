<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| Đồng bộ INDEX và KHOÁ NGOẠI với production
|------------------------------------------------------------------------------
|
| Sau khi cột đã khớp tuyệt đối (288 bảng / 3.640 cột, 0 lệch), đo tiếp index và
| khoá ngoại — hai thứ TRƯỚC ĐÓ CHƯA HỀ KIỂM — thì vẫn còn lệch:
|
|   index        production 1.198 | migration 1.187 | thiếu 14, thừa 3
|   khoá ngoại   production   196 | migration   193 | thiếu 4, thừa 1, lệch quy tắc 1
|
| Thiếu unique nghĩa là ràng buộc dữ liệu YẾU HƠN production — đó mới là phần
| đáng lo, không phải hiệu năng.
|
| THỨ TỰ CÓ CHỦ ĐÍCH:
|   1. Thêm khoá ngoại trước — MySQL tự tạo index kèm theo, nên bước 2 đỡ việc.
|   2. Thêm index còn thiếu.
|   3. Bỏ index thừa SAU CÙNG — bỏ trước có thể vướng khoá ngoại đang dùng (lỗi 1553).
|   4. Sửa quy tắc ON DELETE của khoá ngoại lệch.
|
| An toàn với production: mọi thao tác đều kiểm tồn tại trước => no-op ở đó.
| Với unique: kiểm trùng dữ liệu trước và báo lỗi rõ ràng thay vì để SQL nổ.
*/
return new class extends Migration
{
    /** @var list<array{0:string,1:string,2:string,3:string,4:string,5:string,6:string}> */
    private const FK_ADD = [
        ['material_requests', 'fk_material_requests_accounting_approved_by', 'accounting_approved_by', 'users', 'id', 'SET NULL', 'RESTRICT'],
        ['payments', 'payments_account_id_foreign', 'account_id', 'accounts', 'id', 'SET NULL', 'RESTRICT'],
        ['receipts', 'receipts_account_id_foreign', 'account_id', 'accounts', 'id', 'SET NULL', 'RESTRICT'],
        ['sessions', 'sessions_user_id_foreign', 'user_id', 'users', 'id', 'SET NULL', 'RESTRICT'],
    ];

    /** @var list<array{0:string,1:string}> */
    private const FK_DROP = [
        ['order_returns', 'order_returns_order_id_foreign'],
    ];

    /** @var list<array{0:string,1:string,2:string,3:string,4:string,5:string,6:string}> */
    private const FK_FIX = [
        ['crm_order_item_serial_units', 'crm_order_item_serial_units_serial_unit_id_foreign', 'serial_unit_id', 'crm_serial_units', 'id', 'RESTRICT', 'RESTRICT'],
    ];

    /** @var list<array{0:string,1:string,2:string,3:string}> [bảng, tên index, non_unique, cột] */
    private const IDX_ADD = [
        ['crm_customers', 'crm_customers_phone_unique', '0', 'phone'],
        ['crm_order_items', 'crm_order_items_order_id_foreign', '1', 'order_id'],
        ['crm_payment_methods', 'crm_payment_methods_code_unique', '0', 'code'],
        ['crm_product_stock', 'crm_product_stock_company_product_warehouse_unique', '0', 'company_id,product_id,warehouse_id'],
        ['crm_product_stock', 'crm_product_stock_product_id_warehouse_id_idx', '1', 'product_id,warehouse_id'],
        ['crm_serial_units', 'idx_serial_units_warehouse', '1', 'warehouse_id'],
        ['material_request_items', 'idx_mri_product_warehouse', '1', 'product_id,warehouse_id'],
        ['material_requests', 'fk_material_requests_accounting_approved_by', '1', 'accounting_approved_by'],
        ['material_requests', 'material_requests_warehouse_id_foreign', '1', 'warehouse_id'],
        ['mkt_actual_kpi_daily', 'mkt_actual_kpi_daily_channel_index', '1', 'channel'],
        ['mkt_plans', 'mkt_plans_month_unique', '0', 'month'],
        ['payments', 'payments_account_id_foreign', '1', 'account_id'],
        ['receipts', 'receipts_account_id_foreign', '1', 'account_id'],
        ['sites', 'sites_created_by_index', '1', 'created_by'],
    ];

    /** @var list<array{0:string,1:string}> */
    private const IDX_DROP = [
        ['crm_product_stock', 'crm_product_stock_company_id_index'],
        ['material_requests', 'material_requests_warehouse_id_index'],
        ['receipts', 'receipts_account_id_index'],
    ];

    public function up(): void
    {
        foreach (self::FK_ADD as [$table, $name, $col, $refTable, $refCol, $onDelete, $onUpdate]) {
            $this->addForeignKey($table, $name, $col, $refTable, $refCol, $onDelete, $onUpdate);
        }

        foreach (self::IDX_ADD as [$table, $name, $nonUnique, $cols]) {
            $this->addIndex($table, $name, $nonUnique === '1', explode(',', $cols));
        }

        foreach (self::IDX_DROP as [$table, $name]) {
            if (Schema::hasTable($table) && Schema::hasIndex($table, $name)) {
                DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $name));
            }
        }

        foreach (self::FK_DROP as [$table, $name]) {
            if ($this->foreignKeyExists($table, $name)) {
                DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $name));
            }
        }

        foreach (self::FK_FIX as [$table, $name, $col, $refTable, $refCol, $onDelete, $onUpdate]) {
            // Chỉ dựng lại khi quy tắc HIỆN TẠI thật sự khác. Bản đầu luôn drop+add,
            // nghĩa là chạy DDL trên bảng production dù không đổi gì — phát hiện khi
            // preflight, và DDL trên bảng đang chạy thì khoá bảng, tránh được thì tránh.
            if (! $this->foreignKeyExists($table, $name)) {
                $this->addForeignKey($table, $name, $col, $refTable, $refCol, $onDelete, $onUpdate);

                continue;
            }

            $current = DB::selectOne(
                'SELECT delete_rule, update_rule FROM information_schema.referential_constraints
                 WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
                [$table, $name],
            );

            if ($current !== null
                && $current->delete_rule === $onDelete
                && $current->update_rule === $onUpdate) {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $name));
            $this->addForeignKey($table, $name, $col, $refTable, $refCol, $onDelete, $onUpdate);
        }
    }

    public function down(): void
    {
        // KHÔNG hoàn tác: đây là đưa schema về đúng production.
    }

    private function addForeignKey(string $table, string $name, string $col, string $refTable, string $refCol, string $onDelete, string $onUpdate): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($refTable)) {
            return;
        }

        if (! Schema::hasColumn($table, $col) || $this->foreignKeyExists($table, $name)) {
            return;
        }

        // Còn dòng mồ côi thì bỏ qua có kiểm soát — đúng quy tắc migration additive
        // của repo: không làm gãy deploy vì dữ liệu cũ.
        $orphans = DB::table($table)
            ->whereNotNull($col)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from($refTable)
                ->whereColumn($refTable.'.'.$refCol, $table.'.'.$col))
            ->count();

        if ($orphans > 0) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE %s',
            $table, $name, $col, $refTable, $refCol, $onDelete, $onUpdate,
        ));
    }

    /** @param list<string> $columns */
    private function addIndex(string $table, string $name, bool $nonUnique, array $columns): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
            return;
        }

        foreach ($columns as $c) {
            if (! Schema::hasColumn($table, $c)) {
                return;
            }
        }

        $cols = '`'.implode('`, `', $columns).'`';

        if (! $nonUnique) {
            // Có dữ liệu trùng thì báo rõ thay vì để MariaDB nổ lỗi khó đọc.
            $dupes = DB::table($table)
                ->select($columns)
                ->groupBy($columns)
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($dupes > 0) {
                throw new RuntimeException(sprintf(
                    'Không tạo được UNIQUE `%s` trên `%s` (%s): có %d nhóm giá trị trùng. '.
                    'Dọn dữ liệu trùng rồi chạy lại migration.',
                    $name, $table, implode(', ', $columns), $dupes,
                ));
            }
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD %s `%s` (%s)',
            $table, $nonUnique ? 'INDEX' : 'UNIQUE', $name, $cols,
        ));
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE() AND table_name = ?
               AND constraint_name = ? AND constraint_type = ?',
            [$table, $name, 'FOREIGN KEY'],
        ) !== null;
    }
};
