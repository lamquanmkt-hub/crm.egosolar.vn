<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|------------------------------------------------------------------------------
| Dọn 1 bảng chết và 3 cột chết mà migration tạo ra nhưng production không có
|------------------------------------------------------------------------------
|
| Sau khi bù đủ cột, DB dựng bằng migration còn THỪA so với production 10 thứ:
|
|   content_feedback (7 cột, cả bảng)  - tạo bởi 2026_02_27_222043; production
|       dùng `content_feedbacks` (SỐ NHIỀU, 10 dòng dữ liệu thật). Model
|       App\Models\ContentFeedback khai `protected $table = 'content_feedbacks'`,
|       nên bảng số ít không code nào đụng tới.
|   mkt_seo_phase_kpis.kpi_key / kpi_text / kpi_value - tạo bởi
|       2026_03_02_000001; production đã bỏ.
|
| Đã xác minh trước khi viết migration này:
|   - production KHÔNG có `content_feedback` số ít, KHÔNG có 3 cột kpi_*
|     => trên production đây là no-op, không có gì để mất
|   - không dòng code nào ngoài migration tham chiếu tới chúng
|   - `content_feedbacks` số nhiều (bảng thật, 10 dòng) KHÔNG bị đụng tới
|
| Chốt an toàn: nếu ở môi trường nào đó bảng `content_feedback` LẠI CÓ DỮ LIỆU
| thì migration DỪNG chứ không xoá — thà lệch schema còn hơn mất dữ liệu.
*/
return new class extends Migration
{
    private const DEAD_TABLE = 'content_feedback';   // SỐ ÍT — không phải content_feedbacks

    private const DEAD_COLUMNS = ['kpi_key', 'kpi_text', 'kpi_value'];

    public function up(): void
    {
        $this->dropDeadTable();
        $this->dropDeadColumns();
    }

    public function down(): void
    {
        // KHÔNG dựng lại: đây là bảng/cột chết, không code nào dùng.
    }

    private function dropDeadTable(): void
    {
        if (! Schema::hasTable(self::DEAD_TABLE)) {
            return;
        }

        $rows = (int) DB::table(self::DEAD_TABLE)->count();

        if ($rows > 0) {
            throw new RuntimeException(sprintf(
                'Bảng `%s` đang có %d dòng — dừng lại, KHÔNG xoá. '.
                'Bảng này lẽ ra là bảng chết; có dữ liệu nghĩa là giả định sai, phải kiểm tra tay.',
                self::DEAD_TABLE,
                $rows,
            ));
        }

        Schema::drop(self::DEAD_TABLE);
    }

    private function dropDeadColumns(): void
    {
        if (! Schema::hasTable('mkt_seo_phase_kpis')) {
            return;
        }

        $present = array_values(array_filter(
            self::DEAD_COLUMNS,
            static fn (string $c): bool => Schema::hasColumn('mkt_seo_phase_kpis', $c),
        ));

        if ($present === []) {
            return;
        }

        // Chỉ xoá khi cả ba cột đều rỗng — có dữ liệu thì giả định "cột chết" đã sai.
        foreach ($present as $column) {
            $used = (int) DB::table('mkt_seo_phase_kpis')->whereNotNull($column)->count();

            if ($used > 0) {
                throw new RuntimeException(sprintf(
                    'Cột `mkt_seo_phase_kpis`.`%s` có %d dòng khác NULL — dừng lại, KHÔNG xoá.',
                    $column,
                    $used,
                ));
            }
        }

        /*
         * Phải gỡ index tham chiếu tới cột trước, nếu không MariaDB báo lỗi 1072
         * "Key column 'kpi_key' doesn't exist in table".
         *
         * Bản dựng bằng migration có `uniq_plan_phase_kpi (plan_id, phase_no, kpi_key)`
         * và `idx_plan_phase (plan_id, phase_no)`; production KHÔNG có hai cái đó mà
         * có `mkt_seo_phase_kpis_plan_id_phase_no_unique (plan_id, phase_no)`.
         * Nên vừa gỡ vừa dựng lại cho khớp production.
         */
        foreach (['uniq_plan_phase_kpi', 'idx_plan_phase'] as $index) {
            if (Schema::hasIndex('mkt_seo_phase_kpis', $index)) {
                Schema::table('mkt_seo_phase_kpis', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        Schema::table('mkt_seo_phase_kpis', function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });

        if (! Schema::hasIndex('mkt_seo_phase_kpis', 'mkt_seo_phase_kpis_plan_id_phase_no_unique')) {
            Schema::table('mkt_seo_phase_kpis', function (Blueprint $table) {
                $table->unique(['plan_id', 'phase_no'], 'mkt_seo_phase_kpis_plan_id_phase_no_unique');
            });
        }
    }
};
