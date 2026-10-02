<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Index cho các cột đang bị quét toàn bảng trên luồng nóng.
 *
 * Mỗi index dưới đây gắn với một truy vấn CÓ THẬT trong code, không thêm theo
 * cảm tính — index thừa làm chậm INSERT/UPDATE và tốn đĩa.
 *
 * An toàn tuyệt đối (additive, safe-online): chỉ thêm index, không đổi dữ liệu
 * hay kiểu cột. InnoDB tạo index online nên không khoá ghi lâu. Idempotent:
 * bỏ qua khi bảng/cột không tồn tại hoặc index đã có.
 */
return new class extends Migration
{
    /**
     * Danh sách index: [bảng, tên index, các cột, lý do].
     *
     * @var list<array{0: string, 1: string, 2: list<string>, 3: string}>
     */
    private array $indexes = [
        ['crm_orders', 'idx_orders_company', ['company_id'],
            'Mọi truy vấn đơn đều lọc theo công ty vận hành (EgoCompanyContext).'],

        ['crm_stock_movements', 'idx_stock_movements_reference', ['reference_id'],
            'OrderDetailViewService::stockMovements lọc theo reference_id của đơn.'],

        ['order_return_items', 'idx_ori_order_item', ['order_item_id'],
            'Truy vấn gộp số lượng trả hàng dùng whereIn(order_item_id); index sẵn có bắt đầu bằng order_return_id nên không dùng được.'],

        ['tasks', 'idx_tasks_assignee_status', ['assignee_id', 'status'],
            'Badge "việc chưa xong" của sidebar chạy ở MỌI trang.'],

        ['tasks', 'idx_tasks_requester', ['requester_id'],
            'Danh sách việc đã giao của trưởng phòng.'],

        ['crm_serial_units', 'idx_serial_units_warehouse', ['warehouse_id'],
            'Tra serial tồn theo kho (trang serial, xuất kho).'],

        ['crm_order_approvals', 'idx_order_approvals_status_order', ['status', 'order_id'],
            'Badge "đơn chờ duyệt" đếm distinct order_id theo status — index phủ trọn truy vấn.'],

        ['material_requests', 'idx_material_requests_status', ['status'],
            'Badge "đơn vật tư chờ duyệt" của sidebar.'],

        ['payment_requests', 'idx_payment_requests_status', ['status'],
            'Badge "đề nghị thanh toán chờ duyệt" của sidebar.'],

        ['sales_commissions', 'idx_sales_commissions_order', ['order_id'],
            'Bảng hoa hồng trước đây chỉ có PRIMARY — mọi tra cứu theo đơn đều quét toàn bảng.'],

        ['sales_commissions', 'idx_sales_commissions_sales_user', ['sales_user_id'],
            'Báo cáo hoa hồng theo nhân viên sales.'],

        ['sales_commissions', 'idx_sales_commissions_customer', ['customer_id'],
            'Tra hoa hồng theo khách hàng.'],

        ['material_request_items', 'idx_mri_request', ['material_request_id'],
            'Dòng vật tư của một phiếu — trước đây chỉ có PRIMARY.'],

        ['material_request_items', 'idx_mri_product_warehouse', ['product_id', 'warehouse_id'],
            'Đối chiếu vật tư theo sản phẩm/kho.'],

        ['order_returns', 'idx_order_returns_customer', ['customer_id'],
            'Lịch sử trả hàng theo khách.'],

        ['order_returns', 'idx_order_returns_receiving_warehouse', ['receiving_warehouse_id'],
            'Danh sách phiếu trả theo kho nhận.'],

        ['users', 'idx_users_position', ['position_id'],
            'SalesManagerDirectory và các báo cáo nhân sự lọc/nạp theo chức danh.'],

        ['content_files', 'idx_content_files_calendar', ['content_calendar_id'],
            'File đính kèm của một mục lịch nội dung.'],

        ['content_calendars', 'idx_content_calendars_campaign', ['campaign_id'],
            'Lịch nội dung theo chiến dịch.'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as [$table, $name, $columns, $_reason]) {
            if (! $this->isApplicable($table, $name, $columns)) {
                continue;
            }

            Schema::table($table, static function (Blueprint $blueprint) use ($name, $columns): void {
                $blueprint->index($columns, $name);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $name, $_columns, $_reason]) {
            if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
                continue;
            }

            Schema::table($table, static function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        }
    }

    /**
     * Có nên tạo index này không: bảng tồn tại, đủ cột, và chưa có index cùng tên.
     *
     * @param  list<string>  $columns
     */
    private function isApplicable(string $table, string $name, array $columns): bool
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Index đã tồn tại trên bảng hay chưa.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $indexName]
        );

        return (int) ($result->c ?? 0) > 0;
    }
};
