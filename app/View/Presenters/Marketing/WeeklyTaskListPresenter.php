<?php

declare(strict_types=1);

namespace App\View\Presenters\Marketing;

use App\DTOs\Marketing\WeeklyTaskRow;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị bảng công việc tuần (`marketing/reports/weekly_tasks`).
 *
 * Thay hai khối `@php` của view. Khối đầu ngoài phần định dạng còn chạy hai truy vấn CHẾT
 * (`DB::connection()->getDatabaseName()` và `DB::table('weekly_tasks')->count()`) — hai biến đó
 * không được dùng ở đâu trong view, tức mỗi lần mở trang lại tốn một `COUNT(*)` để bỏ đi.
 * Khối thứ hai nằm trong `@forelse`, chạy lại cho từng dòng.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 *
 * ⚠️ Lớp badge trả về vẫn là lớp Bootstrap (`bg-danger`, `bg-warning text-dark`…) — CỐ Ý giữ y
 * bản cũ để HTML không đổi. Việc quy đổi sang `tw:*` thuộc đợt chuyển giao diện riêng, vì đổi
 * lớp là đổi HTML nên không so byte được.
 */
final class WeeklyTaskListPresenter
{
    /** Ưu tiên → lớp nền badge, giữ đúng `match()` cũ của view. */
    private const PRIORITY_BADGES = [
        'high' => 'bg-danger',
        'medium' => 'bg-warning text-dark',
        'low' => 'bg-info text-dark',
    ];

    /**
     * Trạng thái → lớp nền badge.
     *
     * Cột `status` là enum(pending,doing,done,overdue) nhưng bản cũ KHÔNG có nhánh cho
     * `overdue`; nó rơi vào mặc định `bg-secondary`. Giữ nguyên chỗ đó.
     */
    private const STATUS_BADGES = [
        'done' => 'bg-success',
        'doing' => 'bg-primary',
        'pending' => 'bg-secondary',
    ];

    private const BADGE_DEFAULT = 'bg-secondary';

    /**
     * @param  iterable<object>  $tasks  các dòng `weekly_tasks` controller đã lọc và sắp xếp
     * @return array{taskRows: list<WeeklyTaskRow>, taskCount: int}
     */
    public function viewData(iterable $tasks): array
    {
        $rows = [];
        foreach ($tasks as $task) {
            $priority = strtolower((string) ($task->priority ?? ''));
            $status = strtolower((string) ($task->status ?? ''));

            $rows[] = new WeeklyTaskRow(
                id: (int) ($task->id ?? 0),
                title: (string) ($task->title ?? '-'),
                priorityBadge: self::PRIORITY_BADGES[$priority] ?? self::BADGE_DEFAULT,
                priorityText: strtoupper($priority !== '' ? $priority : 'N/A'),
                category: (string) ($task->category ?? '-'),
                assignee: (string) ($task->assignee ?? '-'),
                startDateText: $this->dateText($task->start_date ?? null),
                dueDateText: $this->dateText($task->due_date ?? null),
                statusBadge: self::STATUS_BADGES[$status] ?? self::BADGE_DEFAULT,
                statusText: strtoupper($status !== '' ? $status : 'N/A'),
            );
        }

        return ['taskRows' => $rows, 'taskCount' => count($rows)];
    }

    /**
     * Ngày `Y-m-d`, trống thì `-`.
     *
     * Không gọi thẳng `DisplayFormat::date()` cho cả nhánh trống: hàm đó trả gạch DÀI `—` còn
     * bản cũ của trang này trả gạch NGANG `-`. Giữ guard riêng rồi mới uỷ quyền phần parse,
     * để không đổi một ký tự nào trong HTML.
     */
    private function dateText(mixed $value): string
    {
        return empty($value) ? '-' : DisplayFormat::date($value, 'Y-m-d');
    }
}
