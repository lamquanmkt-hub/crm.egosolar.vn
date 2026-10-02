<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| Bù 55 cột production có mà migration chưa dựng ra
|------------------------------------------------------------------------------
|
| Sau khi chuỗi migration chạy lại được từ số 0 (289 bảng) và DDL lúc chạy đã bị
| gỡ khỏi controller, schema dựng bằng migration vẫn thiếu 55 cột so với
| production — dấu vết của những lần ALTER TABLE làm tay trên server.
|
| Migration này bù nốt, dùng ĐÚNG định nghĩa của production trích từ
| database/schema/mysql-schema.sql (kiểu, NULL/NOT NULL, DEFAULT), nên không có
| chuyện đoán sai kiểu dữ liệu.
|
| Sau migration này, DB dựng thuần bằng migration có schema tương đương production
| và chạy được cả bộ test — tức migration trở thành nguồn sự thật, không còn phụ
| thuộc file dump nữa.
|
| KHÔNG dùng AFTER: thứ tự cột không ảnh hưởng chức năng, mà AFTER lại dễ chết khi
| cột mốc chưa tồn tại. Vì vậy thứ tự cột có thể khác production.
|
| An toàn với production: mọi cột đều kiểm `Schema::hasColumn` trước -> no-op.
*/
return new class extends Migration
{
    /** @var array<string, array<string, string>> bảng => [cột => định nghĩa SQL của production] */
    private const COLUMNS = [
        'attendance_settings' => [
            'late_penalty_per_time' => 'int(10) unsigned NOT NULL DEFAULT 0',
            'saturday_custom_dates' => 'longtext DEFAULT NULL',
            'saturday_mode' => 'varchar(30) NOT NULL DEFAULT \'odd\'',
            'workday_friday' => 'tinyint(1) NOT NULL DEFAULT 1',
            'workday_monday' => 'tinyint(1) NOT NULL DEFAULT 1',
            'workday_sunday' => 'tinyint(1) NOT NULL DEFAULT 0',
            'workday_thursday' => 'tinyint(1) NOT NULL DEFAULT 1',
            'workday_tuesday' => 'tinyint(1) NOT NULL DEFAULT 1',
            'workday_wednesday' => 'tinyint(1) NOT NULL DEFAULT 1',
        ],
        'content_calendars' => [
            'assignee' => 'varchar(255) DEFAULT NULL',
            'assignees' => 'text DEFAULT NULL',
        ],
        'crm_orders' => [
            'invoice_file' => 'varchar(255) DEFAULT NULL',
            'is_shipped' => 'tinyint(1) NOT NULL DEFAULT 0',
            'shipped_at' => 'timestamp NULL DEFAULT NULL',
            'shipping_fee_station_to_customer' => 'decimal(15,2) NOT NULL DEFAULT 0.00',
            'shipping_fee_warehouse_to_station' => 'decimal(15,2) NOT NULL DEFAULT 0.00',
        ],
        'crm_product_catalog' => [
            'cost_vat_percent' => 'decimal(8,2) DEFAULT 0.00',
            'note' => 'text DEFAULT NULL',
            'vat_percent' => 'decimal(5,2) NOT NULL DEFAULT 0.00',
        ],
        'crm_product_prices' => [
            'price_after_vat' => 'decimal(15,2) DEFAULT 0.00',
            'vat_percent' => 'decimal(8,2) DEFAULT 0.00',
        ],
        'crm_product_stock' => [
            'serials_json' => 'longtext DEFAULT NULL',
        ],
        'crm_serial_unit_states' => [
            'note' => 'text DEFAULT NULL',
        ],
        'crm_serial_units' => [
            'warehouse_id' => 'bigint(20) unsigned DEFAULT NULL',
        ],
        'crm_serial_warranties' => [
            'site_id' => 'bigint(20) unsigned DEFAULT NULL',
        ],
        'crm_stock_movements' => [
            'qty_after' => 'int(11) DEFAULT NULL',
            'qty_before' => 'int(11) DEFAULT NULL',
        ],
        'material_request_items' => [
            'warehouse_id' => 'bigint(20) unsigned DEFAULT NULL',
        ],
        'mkt_seo_phase_kpis' => [
            'anchor_brand_pct' => 'decimal(5,2) NOT NULL DEFAULT 0.00',
            'anchor_exact_pct' => 'decimal(5,2) NOT NULL DEFAULT 0.00',
            'anchor_partial_pct' => 'decimal(5,2) NOT NULL DEFAULT 0.00',
            'anchor_url_pct' => 'decimal(5,2) NOT NULL DEFAULT 0.00',
            'budget' => 'double NOT NULL DEFAULT 0',
            'content_new' => 'int(11) NOT NULL DEFAULT 0',
            'content_update' => 'int(11) NOT NULL DEFAULT 0',
            'internal_link_category' => 'int(11) NOT NULL DEFAULT 0',
            'internal_link_content' => 'int(11) NOT NULL DEFAULT 0',
            'internal_link_landing' => 'int(11) NOT NULL DEFAULT 0',
            'keyword_top10' => 'int(11) NOT NULL DEFAULT 0',
            'keyword_top20' => 'int(11) NOT NULL DEFAULT 0',
            'month_range' => 'varchar(255) DEFAULT NULL',
            'note' => 'text DEFAULT NULL',
            'onpage_urls' => 'int(11) NOT NULL DEFAULT 0',
            'phase_title' => 'varchar(255) DEFAULT NULL',
        ],
        'payment_requests' => [
            'company' => 'varchar(255) DEFAULT NULL',
            'payment_content' => 'text DEFAULT NULL',
        ],
        'payments' => [
            'account_id' => 'bigint(20) unsigned DEFAULT NULL',
        ],
        'sites' => [
            'battery_kwh' => 'decimal(10,2) DEFAULT NULL',
            'completed_at' => 'date DEFAULT NULL',
            'deployment_started_at' => 'date DEFAULT NULL',
            'solar_panel_qty' => 'int(11) DEFAULT NULL',
            'solar_panel_wp' => 'decimal(10,2) DEFAULT NULL',
            'warranty_reminder_1_at' => 'date DEFAULT NULL',
            'warranty_reminder_2_at' => 'date DEFAULT NULL',
            'warranty_reminder_3_at' => 'date DEFAULT NULL',
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $definition) {
                if (Schema::hasColumn($table, $column)) {
                    continue;
                }

                DB::statement(sprintf(
                    'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                    $table,
                    $column,
                    $definition,
                ));
            }
        }
    }

    public function down(): void
    {
        // KHÔNG tự xoá: đây là cột đang mang dữ liệu thật trên production.
    }
};
