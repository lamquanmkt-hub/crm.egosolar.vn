<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chuẩn hoá 1NF cho `content_calendars.assignees`.
 *
 * Cột cũ nhồi cả danh sách người phụ trách vào MỘT ô, lại lưu **TÊN** chứ không
 * phải id — nên vừa không join/index được, vừa mất toàn vẹn tham chiếu (đổi tên
 * nhân viên là mất phân công). Xem DB_NORMALIZATION_AUDIT.md.
 *
 * Bảng mới: mỗi người phụ trách một dòng.
 * - `assignee_name` giữ nguyên chuỗi gốc ⇒ backfill KHÔNG mất dữ liệu kể cả khi
 *   tên không khớp user nào (nhân viên đã nghỉ, gõ sai chính tả).
 * - `user_id` được điền khi khớp được tên với `users.name` ⇒ có khoá ngoại thật
 *   để join và ràng buộc.
 *
 * Additive: KHÔNG xoá cột `assignees` cũ (code cũ vẫn đọc). Idempotent.
 */
return new class extends Migration
{
    private const TABLE = 'content_calendar_assignees';

    public function up(): void
    {
        if (! Schema::hasTable('content_calendars')) {
            return;
        }

        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, static function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('content_calendar_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('assignee_name', 255);
                $table->timestamps();

                $table->unique(['content_calendar_id', 'assignee_name'], 'uq_cca_calendar_name');
                $table->index('user_id', 'idx_cca_user');
            });
        }

        $this->addForeignKeysWhenSafe();
        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    /**
     * Thêm FK chỉ khi chắc chắn không sinh lỗi (bảng đích tồn tại, chưa có FK).
     */
    private function addForeignKeysWhenSafe(): void
    {
        if ($this->foreignKeyExists('fk_cca_calendar')) {
            return;
        }

        Schema::table(self::TABLE, static function (Blueprint $table): void {
            $table->foreign('content_calendar_id', 'fk_cca_calendar')
                ->references('id')->on('content_calendars')
                ->cascadeOnDelete();
        });

        if (Schema::hasTable('users') && ! $this->foreignKeyExists('fk_cca_user')) {
            Schema::table(self::TABLE, static function (Blueprint $table): void {
                $table->foreign('user_id', 'fk_cca_user')
                    ->references('id')->on('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Đổ dữ liệu từ cột danh sách cũ sang bảng con, khớp tên với users.name.
     */
    private function backfill(): void
    {
        if (! Schema::hasColumn('content_calendars', 'assignees')) {
            return;
        }

        $usersByName = DB::table('users')
            ->select('id', 'name')
            ->get()
            ->keyBy(static fn ($user): string => mb_strtolower(trim((string) $user->name)));

        DB::table('content_calendars')
            ->select('id', 'assignees')
            ->whereNotNull('assignees')
            ->where('assignees', '<>', '')
            ->orderBy('id')
            ->chunk(500, function ($calendars) use ($usersByName): void {
                $rows = [];

                foreach ($calendars as $calendar) {
                    foreach ($this->parseNames($calendar->assignees) as $name) {
                        $rows[] = [
                            'content_calendar_id' => (int) $calendar->id,
                            'user_id' => $usersByName[mb_strtolower($name)]->id ?? null,
                            'assignee_name' => mb_substr($name, 0, 255),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if ($rows !== []) {
                    // Bỏ qua dòng đã có (unique calendar+name) ⇒ chạy lại vô hại.
                    DB::table(self::TABLE)->insertOrIgnore($rows);
                }
            });
    }

    /**
     * Tách danh sách tên từ chuỗi JSON hoặc CSV.
     *
     * @return list<string>
     */
    private function parseNames(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '' || $raw === '[]') {
            return [];
        }

        $decoded = json_decode($raw, true);
        $values = is_array($decoded) ? $decoded : (preg_split('/[,;|]/', $raw) ?: []);

        $names = [];

        foreach ($values as $value) {
            $name = trim((string) $value);

            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Khoá ngoại đã tồn tại chưa.
     */
    private function foreignKeyExists(string $name): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.table_constraints
             WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?',
            [self::TABLE, $name]
        );

        return (int) ($row->c ?? 0) > 0;
    }
};
