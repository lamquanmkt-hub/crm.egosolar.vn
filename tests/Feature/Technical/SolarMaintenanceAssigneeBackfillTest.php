<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kiểm chứng migration backfill đưa danh sách id trong một cột về bảng quan hệ.
 *
 * Chạy trực tiếp lớp migration (không qua artisan) để test nằm trong
 * transaction và tự rollback.
 */
final class SolarMaintenanceAssigneeBackfillTest extends TestCase
{
    use DatabaseTransactions;

    /** JSON và CSV đều được tách đúng; người đầu danh sách là trưởng nhóm. */
    public function test_backfills_assignees_from_json_and_csv(): void
    {
        $leader = User::factory()->create();
        $member = User::factory()->create();

        $jsonScheduleId = $this->seedSchedule(json_encode([$leader->id, $member->id]));
        $csvScheduleId = $this->seedSchedule($leader->id.', '.$member->id);

        $this->runBackfill();

        foreach ([$jsonScheduleId, $csvScheduleId] as $scheduleId) {
            $rows = DB::table('solar_maintenance_assignees')
                ->where('maintenance_schedule_id', $scheduleId)
                ->orderBy('id')
                ->get();

            $this->assertCount(2, $rows, "Lịch {$scheduleId} phải có đủ 2 người.");
            $this->assertSame((int) $leader->id, (int) $rows[0]->user_id);
            $this->assertSame(1, (int) $rows[0]->is_leader);
            $this->assertSame('leader', $rows[0]->role);
            $this->assertSame((int) $member->id, (int) $rows[1]->user_id);
            $this->assertSame(0, (int) $rows[1]->is_leader);
        }
    }

    /** Chạy lại migration KHÔNG được nhân bản dòng. */
    public function test_backfill_is_idempotent(): void
    {
        $user = User::factory()->create();
        $scheduleId = $this->seedSchedule(json_encode([$user->id]));

        $this->runBackfill();
        $this->runBackfill();

        $this->assertSame(
            1,
            DB::table('solar_maintenance_assignees')->where('maintenance_schedule_id', $scheduleId)->count(),
        );
    }

    /** Id trỏ tới user đã bị xoá thì bỏ qua, không làm gãy migration. */
    public function test_skips_unknown_users(): void
    {
        $user = User::factory()->create();
        $missingId = ((int) User::query()->max('id')) + 5000;

        $scheduleId = $this->seedSchedule(json_encode([$user->id, $missingId]));

        $this->runBackfill();

        $userIds = DB::table('solar_maintenance_assignees')
            ->where('maintenance_schedule_id', $scheduleId)
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([(int) $user->id], $userIds);
    }

    /** Lịch không có dữ liệu phân công thì không sinh dòng nào. */
    public function test_ignores_empty_lists(): void
    {
        $scheduleId = $this->seedSchedule('[]');

        $this->runBackfill();

        $this->assertSame(
            0,
            DB::table('solar_maintenance_assignees')->where('maintenance_schedule_id', $scheduleId)->count(),
        );
    }

    /** Nạp và chạy phần up() của migration backfill. */
    private function runBackfill(): void
    {
        $migration = require database_path(
            'migrations/2026_08_05_000002_backfill_solar_maintenance_assignees_from_id_list.php'
        );

        $migration->up();
    }

    /** Tạo một lịch bảo trì với cột danh sách id cho trước. */
    private function seedSchedule(string $assignedUserIds): int
    {
        return (int) DB::table('solar_maintenance_schedules')->insertGetId([
            'assigned_user_ids' => $assignedUserIds,
            'scheduled_date' => now()->toDateString(),
            'type' => 'maintenance',
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
