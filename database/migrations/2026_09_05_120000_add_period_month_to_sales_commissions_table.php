<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột kỳ cho `sales_commissions`.
 *
 * ## Vì sao cần
 * Bảng này ghi hoa hồng ĐÃ CHỐT, nhưng không có cột nào cho biết dòng thuộc kỳ
 * nào. {@see \App\Services\Sales\Commission\RecordedCommissionQuery} lọc kỳ bằng
 * cột ngày đầu tiên tìm được — với lược đồ hiện tại đó là `created_at`, tức NGÀY
 * CHẠY BACKFILL chứ không phải kỳ của đơn. Bật phần đọc bảng này lên mà thiếu
 * cột kỳ thì báo cáo tháng 3 sẽ đọc nhầm dòng ghi ngày tháng 9.
 *
 * Chỉ THÊM cột, không đụng dữ liệu cũ (quy tắc expand-only). Cột để `null` cho
 * 120 dòng cũ; lệnh `commission:backfill` sẽ điền khi tính lại.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_commissions')) {
            return;
        }

        if (! Schema::hasColumn('sales_commissions', 'period_month')) {
            Schema::table('sales_commissions', function (Blueprint $table): void {
                $table->string('period_month', 7)->nullable()->after('status')
                    ->comment('Kỳ tính hoa hồng dạng YYYY-MM, lấy theo ngày thanh toán cuối của đơn');
                $table->index(['period_month', 'sales_user_id'], 'idx_sales_commissions_period_sales');
            });
        }
    }

    /**
     * Chỉ bỏ đúng thứ migration này thêm vào.
     *
     * Không dùng `dropColumn` trên cột có sẵn — quy tắc không mất dữ liệu.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sales_commissions') || ! Schema::hasColumn('sales_commissions', 'period_month')) {
            return;
        }

        Schema::table('sales_commissions', function (Blueprint $table): void {
            $table->dropIndex('idx_sales_commissions_period_sales');
            $table->dropColumn('period_month');
        });
    }
};
