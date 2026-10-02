<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Support\SchemaCache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Số liệu hiển thị trên sidebar: 3 ô trạng thái nhân sự và các badge "chờ duyệt".
 *
 * ## Vì sao tách khỏi Blade
 * Toàn bộ phần này trước đây nằm trong `partials/sidebar.blade.php` dưới dạng
 * ~230 dòng `@php` — view tự truy vấn DB, tự bắt exception, không test được và
 * lặp `Schema::hasColumn` cho cùng một bảng nhiều lần (mỗi lần là 1 truy vấn
 * `information_schema`). Sidebar render ở MỌI trang nên chi phí đó nhân với
 * toàn bộ lưu lượng.
 *
 * Hành vi giữ nguyên: mỗi khối số liệu tự phòng thủ, lỗi/thiếu bảng thì trả 0
 * chứ không làm vỡ trang.
 */
final class SidebarStatusService
{
    /** Khoảng thời gian coi là "đang online" (phút). */
    private const ONLINE_WINDOW_MINUTES = 5;

    /** Trạng thái chấm công KHÔNG tính là đang làm việc. */
    private const NON_WORKING_ATTENDANCE_STATUSES = [
        'absent', 'leave', 'off', 'rejected', 'cancelled', 'canceled',
    ];

    /** Trạng thái công việc coi như đã xong. */
    private const FINISHED_TASK_STATUSES = [
        'approved', 'done', 'completed', 'complete', 'closed', 'cancelled', 'canceled',
    ];

    /**
     * Toàn bộ biến sidebar cần, gom một lần để view chỉ việc dùng.
     *
     * @return array{
     *     egoOnlineCount: int,
     *     egoWorkingToday: int,
     *     egoActiveEmployees: int,
     *     egoStatusItems: list<array{key: string, label: string, value: int, icon: string, tone: string}>,
     *     egoPendingOrdersCount: int,
     *     egoPendingMaterialRequestsCount: int,
     *     egoPendingPaymentRequestsCount: int,
     *     egoPendingProposalsCount: int,
     *     egoMyUnfinishedTasksCount: int
     * }
     */
    public function viewData(): array
    {
        $online = $this->onlineCount();
        $working = $this->workingTodayCount();
        $employees = $this->activeEmployeeCount();

        return [
            'egoOnlineCount' => $online,
            'egoWorkingToday' => $working,
            'egoActiveEmployees' => $employees,
            'egoStatusItems' => [
                ['key' => 'online', 'label' => 'Đang online', 'value' => $online, 'icon' => 'bi-wifi', 'tone' => 'online'],
                ['key' => 'working', 'label' => 'Đang làm việc', 'value' => $working, 'icon' => 'bi-person-check', 'tone' => 'work'],
                ['key' => 'employees', 'label' => 'Nhân viên hoạt động', 'value' => $employees, 'icon' => 'bi-people', 'tone' => 'people'],
            ],
            'egoPendingOrdersCount' => $this->pendingOrderApprovalCount(),
            'egoPendingMaterialRequestsCount' => $this->pendingCount('material_requests', ['pending', 'submitted', 'admin_approved']),
            'egoPendingPaymentRequestsCount' => $this->pendingCount('payment_requests', ['pending', 'submitted', 'admin_pending', 'admin_approved', 'accounting_pending']),
            'egoPendingProposalsCount' => $this->pendingCount('proposals', ['pending', 'submitted']),
            'egoMyUnfinishedTasksCount' => $this->myUnfinishedTaskCount(),
        ];
    }

    /** Số user có hoạt động trong 5 phút gần nhất. */
    private function onlineCount(): int
    {
        $fallback = Auth::check() ? 1 : 0;

        if (! SchemaCache::hasColumn('users', 'last_seen_at')) {
            return $fallback;
        }

        return $this->guard(function (): int {
            $query = DB::table('users')
                ->whereNotNull('last_seen_at')
                ->where('last_seen_at', '>=', now()->subMinutes(self::ONLINE_WINDOW_MINUTES));

            $this->applyActiveUserFilters($query);

            return (int) $query->count();
        }, $fallback);
    }

    /** Số nhân viên đã chấm công hôm nay và không ở trạng thái nghỉ. */
    private function workingTodayCount(): int
    {
        if (! SchemaCache::hasColumns('attendance_records', ['work_date', 'user_id'])) {
            return 0;
        }

        return $this->guard(static function (): int {
            // `work_date` là kiểu DATE nên so sánh bằng thẳng — dùng whereDate()
            // sẽ bọc cột trong DATE(...) và MariaDB không dùng được index.
            $query = DB::table('attendance_records')
                ->where('work_date', now()->toDateString())
                ->whereNotNull('user_id');

            if (SchemaCache::hasColumn('attendance_records', 'check_in_at')) {
                $query->whereNotNull('check_in_at');
            }

            if (SchemaCache::hasColumn('attendance_records', 'status')) {
                $query->where(static function (Builder $filter): void {
                    $filter->whereNull('status')
                        ->orWhereNotIn('status', self::NON_WORKING_ATTENDANCE_STATUSES);
                });
            }

            return (int) $query->distinct()->count('user_id');
        });
    }

    /** Số nhân viên đang hoạt động. */
    private function activeEmployeeCount(): int
    {
        if (! SchemaCache::hasTable('users')) {
            return 0;
        }

        return $this->guard(function (): int {
            $query = DB::table('users');
            $this->applyActiveUserFilters($query);

            return (int) $query->count();
        });
    }

    /** Số đơn hàng còn phiếu duyệt ở trạng thái pending (đếm theo đơn, không theo phiếu). */
    private function pendingOrderApprovalCount(): int
    {
        if (! SchemaCache::hasColumns('crm_order_approvals', ['status', 'order_id'])) {
            return 0;
        }

        // Cột status dùng collation utf8mb4_unicode_ci (không phân biệt hoa
        // thường) nên KHÔNG cần LOWER() — bỏ đi để MariaDB dùng được index.
        return $this->guard(static fn (): int => (int) DB::table('crm_order_approvals')
            ->where('status', 'pending')
            ->distinct()
            ->count('order_id'));
    }

    /**
     * Đếm bản ghi đang chờ xử lý của một bảng theo danh sách trạng thái.
     *
     * @param  list<string>  $statuses
     */
    private function pendingCount(string $table, array $statuses): int
    {
        if (! SchemaCache::hasColumn($table, 'status')) {
            return 0;
        }

        return $this->guard(static fn (): int => (int) DB::table($table)
            ->whereIn('status', $statuses)
            ->count());
    }

    /** Số công việc chưa hoàn thành của chính người đang đăng nhập. */
    private function myUnfinishedTaskCount(): int
    {
        if (! Auth::check() || ! SchemaCache::hasColumn('tasks', 'assignee_id')) {
            return 0;
        }

        return $this->guard(static function (): int {
            $query = DB::table('tasks')->where('assignee_id', Auth::id());

            if (SchemaCache::hasColumn('tasks', 'status')) {
                $query->where(static function (Builder $filter): void {
                    $filter->whereNull('status')
                        ->orWhereNotIn('status', self::FINISHED_TASK_STATUSES);
                });
            }

            if (SchemaCache::hasColumn('tasks', 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            return (int) $query->count();
        });
    }

    /** Lọc user còn hiệu lực (áp dụng khi cột tương ứng tồn tại). */
    private function applyActiveUserFilters(Builder $query): void
    {
        if (SchemaCache::hasColumn('users', 'is_active')) {
            $query->where('is_active', 1);
        }

        if (SchemaCache::hasColumn('users', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
    }

    /**
     * Chạy phép đếm, lỗi thì trả giá trị mặc định — sidebar không được vỡ trang.
     *
     * @param  callable(): int  $count
     */
    private function guard(callable $count, int $default = 0): int
    {
        try {
            return $count();
        } catch (Throwable) {
            return $default;
        }
    }
}
