<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\User;
use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Ghi danh sách người phụ trách của một mục lịch nội dung sang bảng quan hệ
 * `content_calendar_assignees`.
 *
 * Cột `content_calendars.assignees` (JSON chứa TÊN người) vẫn được ghi song song
 * để bản deploy cũ không vỡ, nhưng bảng quan hệ mới là nơi truy vấn: có
 * `user_id` thật nên join/index/ràng buộc được — xem DB_NORMALIZATION_AUDIT.md.
 *
 * Tên không khớp user nào (nhân viên đã nghỉ, gõ sai) vẫn được lưu với
 * `user_id = null` để không mất dữ liệu.
 */
final class ContentCalendarAssigneeSync
{
    private const TABLE = 'content_calendar_assignees';

    /**
     * Đặt lại toàn bộ người phụ trách của một mục lịch.
     *
     * @param  list<string>  $names  Tên người phụ trách (đã trim, bỏ trùng)
     */
    public function sync(int $contentCalendarId, array $names): void
    {
        if ($contentCalendarId <= 0 || ! SchemaCache::hasTable(self::TABLE)) {
            return;
        }

        $names = $this->clean($names);

        DB::transaction(function () use ($contentCalendarId, $names): void {
            DB::table(self::TABLE)->where('content_calendar_id', $contentCalendarId)->delete();

            if ($names === []) {
                return;
            }

            $userIdByName = $this->resolveUserIds($names);
            $now = now();

            DB::table(self::TABLE)->insert(array_map(
                static fn (string $name): array => [
                    'content_calendar_id' => $contentCalendarId,
                    'user_id' => $userIdByName[mb_strtolower($name)] ?? null,
                    'assignee_name' => mb_substr($name, 0, 255),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $names,
            ));
        });
    }

    /**
     * Chuẩn hoá danh sách tên: trim, bỏ rỗng, bỏ trùng (không phân biệt hoa thường).
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function clean(array $names): array
    {
        $cleaned = [];
        $seen = [];

        foreach ($names as $name) {
            $name = trim((string) $name);
            $key = mb_strtolower($name);

            if ($name === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $cleaned[] = $name;
        }

        return $cleaned;
    }

    /**
     * Map tên → user_id bằng MỘT truy vấn (không lặp User::where trong vòng lặp).
     *
     * @param  list<string>  $names
     * @return array<string, int> tên viết thường => user id
     */
    private function resolveUserIds(array $names): array
    {
        $users = User::query()
            ->select(['id', 'name'])
            ->whereIn('name', $names)
            ->get();

        $map = [];

        foreach ($users as $user) {
            $map[mb_strtolower(trim((string) $user->name))] ??= (int) $user->id;
        }

        return $map;
    }
}
