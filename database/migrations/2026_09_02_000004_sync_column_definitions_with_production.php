<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| Đồng bộ 21 cột có định nghĩa lệch so với production
|------------------------------------------------------------------------------
|
| Sau khi bù đủ cột thiếu, DB dựng bằng migration vẫn còn 21 cột mà KIỂU hoặc
| NULL/NOT NULL khác production — dấu vết của những lần ALTER làm tay trên server
| mà không migration nào ghi lại. Ví dụ nặng nhất:
|
|   payment_requests.reason   migration: varchar(255) NOT NULL
|                             production: text NULL
|
| Cột đó làm 10 test đỏ vì insert không truyền `reason` thì MariaDB từ chối.
|
| An toàn với production: mỗi cột được ĐỌC định nghĩa hiện tại từ information_schema
| trước; chỉ MODIFY khi thực sự khác. Trên production mọi cột đã đúng nên migration
| này là no-op — quan trọng, vì MODIFY sẽ dựng lại bảng, rất đắt với bảng lớn.
*/
return new class extends Migration
{
    /** @var list<array{0:string,1:string,2:string,3:string,4:string}> [bảng, cột, kiểu đích, nullable đích, mệnh đề MODIFY] */
    private const COLUMNS = [
        ['crm_customers', 'customer_status', 'enum(\'lead\',\'member\',\'retail\')', 'NO', 'enum(\'lead\',\'member\',\'retail\') NOT NULL DEFAULT \'lead\''],
        ['crm_order_approvals', 'level', 'varchar(50)', 'NO', 'varchar(50) NOT NULL'],
        ['crm_orders', 'shipping_fee_payer', 'varchar(20)', 'NO', 'varchar(20) NOT NULL DEFAULT \'company\''],
        ['crm_payment_methods', 'code', 'varchar(50)', 'NO', 'varchar(50) NOT NULL'],
        ['crm_product_stock', 'company_id', 'bigint(20) unsigned', 'YES', 'bigint(20) unsigned NULL DEFAULT NULL'],
        ['crm_serial_warranty_claims', 'serial_unit_id', 'bigint(20) unsigned', 'YES', 'bigint(20) unsigned NULL DEFAULT NULL'],
        ['crm_stock_movements', 'reason', 'text', 'YES', 'text NULL DEFAULT NULL'],
        ['material_requests', 'accounting_approved_at', 'datetime', 'YES', 'datetime NULL DEFAULT NULL'],
        ['material_requests', 'warehouse_id', 'bigint(20) unsigned', 'NO', 'bigint(20) unsigned NOT NULL'],
        ['messages', 'body', 'text', 'YES', 'text NULL DEFAULT NULL'],
        ['mkt_actual_kpi_daily', 'channel', 'varchar(50)', 'YES', 'varchar(50) NULL DEFAULT NULL'],
        ['mkt_actual_kpi_daily', 'source_type', 'varchar(100)', 'YES', 'varchar(100) NULL DEFAULT NULL'],
        ['mkt_seo_contents', 'website', 'varchar(191)', 'YES', 'varchar(191) NULL DEFAULT NULL'],
        ['mkt_seo_phase_kpis', 'phase_no', 'tinyint(3) unsigned', 'NO', 'tinyint(3) unsigned NOT NULL'],
        ['payment_requests', 'reason', 'text', 'YES', 'text NULL DEFAULT NULL'],
        ['sites', 'company_id', 'bigint(20) unsigned', 'NO', 'bigint(20) unsigned NOT NULL DEFAULT 1'],
        ['sites', 'labor_cost', 'decimal(15,2)', 'NO', 'decimal(15,2) NOT NULL DEFAULT 0.00'],
        ['sites', 'other_cost', 'decimal(15,2)', 'NO', 'decimal(15,2) NOT NULL DEFAULT 0.00'],
        ['sites', 'transport_cost', 'decimal(15,2)', 'NO', 'decimal(15,2) NOT NULL DEFAULT 0.00'],
        ['tasks', 'link_url', 'text', 'YES', 'text NULL DEFAULT NULL'],
        ['tasks', 'progress_percent', 'int(11)', 'NO', 'int(11) NOT NULL DEFAULT 0'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column, $type, $nullable, $definition]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $current = DB::selectOne(
                'SELECT column_type, is_nullable FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $column],
            );

            if ($current === null) {
                continue;
            }

            // Đã khớp thì KHÔNG đụng vào: MODIFY dựng lại bảng, đắt và không cần thiết.
            if ($current->column_type === $type && $current->is_nullable === $nullable) {
                continue;
            }

            DB::statement(sprintf('ALTER TABLE `%s` MODIFY `%s` %s', $table, $column, $definition));
        }
    }

    public function down(): void
    {
        // KHÔNG hoàn tác: định nghĩa cũ hẹp hơn, quay lại có thể cắt cụt dữ liệu.
    }
};
