<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\User;
use App\Services\Content\ContentCalendarAssigneeSync;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Chuẩn hoá 1NF cho người phụ trách lịch nội dung.
 *
 * Cột `content_calendars.assignees` nhồi cả danh sách TÊN vào một ô; bảng
 * `content_calendar_assignees` tách mỗi người một dòng và gắn `user_id` thật.
 */
final class ContentCalendarAssigneeNormalizationTest extends TestCase
{
    use DatabaseTransactions;

    private ContentCalendarAssigneeSync $sync;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sync = new ContentCalendarAssigneeSync;
    }

    /** Mỗi người phụ trách thành một dòng, khớp được tên thì gắn user_id. */
    public function test_sync_creates_one_row_per_assignee_and_links_users(): void
    {
        $known = User::factory()->create(['name' => 'Nguyễn Văn A']);
        $calendarId = $this->seedCalendar();

        $this->sync->sync($calendarId, ['Nguyễn Văn A', 'Người Đã Nghỉ']);

        $rows = DB::table('content_calendar_assignees')
            ->where('content_calendar_id', $calendarId)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $rows);
        $this->assertSame((int) $known->id, (int) $rows[0]->user_id);
        $this->assertSame('Nguyễn Văn A', $rows[0]->assignee_name);

        // Tên không khớp user nào vẫn được giữ, KHÔNG mất dữ liệu.
        $this->assertNull($rows[1]->user_id);
        $this->assertSame('Người Đã Nghỉ', $rows[1]->assignee_name);
    }

    /** Ghi lại lần nữa thì thay thế hẳn danh sách cũ, không nhân bản. */
    public function test_sync_replaces_previous_assignees(): void
    {
        $calendarId = $this->seedCalendar();

        $this->sync->sync($calendarId, ['A', 'B']);
        $this->sync->sync($calendarId, ['C']);

        $names = DB::table('content_calendar_assignees')
            ->where('content_calendar_id', $calendarId)
            ->pluck('assignee_name')
            ->all();

        $this->assertSame(['C'], $names);
    }

    /** Tên trùng (khác hoa thường) chỉ ghi một dòng — tránh vỡ UNIQUE. */
    public function test_sync_deduplicates_names(): void
    {
        $calendarId = $this->seedCalendar();

        $this->sync->sync($calendarId, ['Trần B', 'trần b', '  Trần B  ', '']);

        $this->assertSame(
            1,
            DB::table('content_calendar_assignees')->where('content_calendar_id', $calendarId)->count(),
        );
    }

    /** Danh sách rỗng thì xoá sạch phân công. */
    public function test_sync_with_empty_list_clears_assignees(): void
    {
        $calendarId = $this->seedCalendar();

        $this->sync->sync($calendarId, ['A']);
        $this->sync->sync($calendarId, []);

        $this->assertSame(
            0,
            DB::table('content_calendar_assignees')->where('content_calendar_id', $calendarId)->count(),
        );
    }

    /** Số truy vấn không tăng theo số người phụ trách (không N+1 khi map tên). */
    public function test_sync_resolves_users_in_constant_queries(): void
    {
        $calendarId = $this->seedCalendar();

        foreach (range(1, 8) as $index) {
            User::factory()->create(['name' => "Nhân viên {$index}"]);
        }

        $one = $this->countQueries(fn () => $this->sync->sync($calendarId, ['Nhân viên 1']));
        $many = $this->countQueries(fn () => $this->sync->sync(
            $calendarId,
            array_map(static fn (int $i): string => "Nhân viên {$i}", range(1, 8)),
        ));

        $this->assertSame($one, $many, 'Map tên → user phải gộp trong một truy vấn.');
    }

    /** Đếm số truy vấn chạy trong callback. */
    private function countQueries(callable $callback): int
    {
        $count = 0;

        DB::listen(static function () use (&$count): void {
            $count++;
        });

        $callback();

        return $count;
    }

    /** Tạo một mục lịch nội dung tối thiểu. */
    private function seedCalendar(): int
    {
        return (int) DB::table('content_calendars')->insertGetId([
            'publish_date' => now()->toDateString(),
            'platform' => 'facebook',
            'content_type' => 'Bài viết',
            'title' => 'Nội dung test',
            'status' => 'draft',
            'created_by' => User::factory()->create()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
