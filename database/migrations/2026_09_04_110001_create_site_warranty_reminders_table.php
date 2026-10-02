<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bỏ nhóm lặp `warranty_reminder_1_at` / `_2_at` / `_3_at` khỏi `sites`.
 *
 * ## Vì sao đây là lỗi 1NF
 * Ba cột cùng nghĩa, chỉ khác số thứ tự — dấu hiệu kinh điển của nhóm lặp. Hệ
 * quả thực tế: muốn thêm mốc nhắc thứ tư phải ĐỔI LƯỢC ĐỒ, và mọi câu truy vấn
 * "công trình nào cần nhắc trong tháng này" phải viết `OR` ba lần thay vì một
 * `WHERE remind_at BETWEEN …`.
 *
 * ## Đã đo trước khi làm (production, 2026-09-04)
 * 19 công trình: mốc 1 có 3 bản ghi, mốc 2 có 3, mốc 3 có 2 — tổng 8 dòng sẽ
 * được chép sang.
 *
 * ## Đây là bước EXPAND, chưa phải CONTRACT
 * KHÔNG đụng ba cột cũ. Xoá chúng là việc của một migration RIÊNG đợt sau, sau
 * khi code đã ghi song song qua ít nhất một lần deploy.
 */
return new class extends Migration
{
    /** Cột nguồn => số thứ tự mốc nhắc. */
    private const SOURCE_COLUMNS = [
        'warranty_reminder_1_at' => 1,
        'warranty_reminder_2_at' => 2,
        'warranty_reminder_3_at' => 3,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('site_warranty_reminders')) {
            Schema::create('site_warranty_reminders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
                $table->unsignedTinyInteger('sequence');
                $table->date('remind_at');
                $table->timestamps();

                $table->unique(['site_id', 'sequence']);

                // Câu hỏi hay gặp nhất: "sắp tới cần nhắc những công trình nào".
                $table->index('remind_at');
            });
        }

        $this->backfill();
    }

    /** Idempotent: bỏ qua cặp (site_id, sequence) đã có. */
    private function backfill(): void
    {
        $columns = array_filter(
            array_keys(self::SOURCE_COLUMNS),
            static fn (string $column): bool => Schema::hasColumn('sites', $column),
        );

        if ($columns === []) {
            return;
        }

        $existing = DB::table('site_warranty_reminders')
            ->get(['site_id', 'sequence'])
            ->map(static fn ($row): string => $row->site_id.':'.$row->sequence)
            ->flip();

        $now = now();

        DB::table('sites')
            ->select(array_merge(['id'], $columns))
            ->orderBy('id')
            ->chunkById(200, function ($sites) use ($columns, $existing, $now): void {
                $rows = [];

                foreach ($sites as $site) {
                    foreach ($columns as $column) {
                        $sequence = self::SOURCE_COLUMNS[$column];
                        $value = $site->{$column} ?? null;

                        if ($value === null || $value === '' || $existing->has($site->id.':'.$sequence)) {
                            continue;
                        }

                        $rows[] = [
                            'site_id' => $site->id,
                            'sequence' => $sequence,
                            'remind_at' => $value,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('site_warranty_reminders')->insert($rows);
                }
            });
    }

    /** Chỉ gỡ bảng `up()` tạo ra. Ba cột cũ trong `sites` vẫn nguyên vẹn. */
    public function down(): void
    {
        Schema::dropIfExists('site_warranty_reminders');
    }
};
