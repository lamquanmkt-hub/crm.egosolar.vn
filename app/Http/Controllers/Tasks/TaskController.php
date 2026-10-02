<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Projects\Site;
use App\Models\Tasks\Task;
use App\Models\User;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * Controller quản lý giao việc và theo dõi công việc nội bộ.
 */
class TaskController extends Controller
{
    /**
     * Kiểm tra người dùng có quyền giao việc/quản lý công việc.
     * Ban giám đốc quản lý toàn công ty; trưởng phòng quản lý trong phòng ban của mình.
     */
    private function canAssign($user): bool
    {
        return $this->isGlobalTaskManager($user) || $this->isDepartmentTaskManager($user);
    }

    /**
     * Kiểm tra role an toàn cho cả Spatie Permission và hệ thống role cũ.
     */
    private function hasAnyRoleSafe($user, array $roles): bool
    {
        if (! $user) {
            return false;
        }

        $roles = array_values(array_unique(array_map(
            static fn ($role) => strtolower(trim((string) $role)),
            $roles
        )));

        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole($roles)) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($user, 'hasRole')) {
            try {
                foreach ($roles as $role) {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($user, 'getRoleNames')) {
            try {
                $roleNames = $user->getRoleNames()
                    ->map(static fn ($role) => strtolower((string) $role))
                    ->all();

                if (count(array_intersect($roles, $roleNames)) > 0) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        $legacyRole = strtolower((string) ($user->role ?? $user->type ?? ''));

        return in_array($legacyRole, $roles, true);
    }

    /**
     * Nhóm quản lý toàn hệ thống được xem và giao việc giữa các phòng ban.
     */
    private function isGlobalTaskManager($user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            if (method_exists($user, 'can') && $user->can('tasks.manage.all')) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        return $this->hasAnyRoleSafe($user, [
            'admin',
            'super_admin',
            'management',
            'director',
            'general_director',
            'ban_giam_doc',
            'giam_doc',
        ]);
    }

    /**
     * Mọi trưởng phòng được giao và quản lý công việc trong đúng phòng ban của mình.
     * Hỗ trợ cả permission, role *_manager và chức danh Trưởng phòng/Manager.
     */
    private function isDepartmentTaskManager($user): bool
    {
        if (! $user || $this->isGlobalTaskManager($user)) {
            return false;
        }

        try {
            if (method_exists($user, 'can') && $user->can('tasks.assign.department')) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        $roleNames = collect();

        if (method_exists($user, 'getRoleNames')) {
            try {
                $roleNames = $user->getRoleNames()->map(
                    static fn ($role) => strtolower(trim((string) $role))
                );
            } catch (\Throwable $e) {
            }
        }

        foreach (['role', 'type'] as $field) {
            if (! empty($user->{$field})) {
                $roleNames->push(strtolower(trim((string) $user->{$field})));
            }
        }

        $roleNames = $roleNames->filter()->unique();

        foreach ($roleNames as $roleName) {
            if (
                str_ends_with($roleName, '_manager') ||
                str_ends_with($roleName, '_leader') ||
                str_starts_with($roleName, 'truong_phong') ||
                str_contains($roleName, 'department_manager') ||
                in_array($roleName, [
                    'sales_manager', 'marketing_manager', 'technical_manager',
                    'technical_leader', 'ky_thuat_manager', 'truong_phong_ky_thuat',
                    'hr_manager', 'human_resources_manager', 'accounting_manager',
                    'chief_accountant', 'ke_toan_truong', 'warehouse_manager',
                    'kho_manager', 'operations_manager', 'assistant_manager',
                ], true)
            ) {
                return true;
            }
        }

        try {
            $position = $user->relationLoaded('position')
                ? $user->position
                : (method_exists($user, 'position') ? $user->position()->first() : null);

            $positionText = strtolower(trim(implode(' ', array_filter([
                $position->name ?? null,
                $position->title ?? null,
                $position->display_name ?? null,
            ]))));

            if ($positionText !== '' && preg_match('/trưởng\s*phòng|truong\s*phong|department\s*manager|head\s*of|\bmanager\b/u', $positionText)) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    /**
     * Phòng ban mà trưởng phòng được phép quản lý.
     */
    private function managedDepartmentId($user): ?int
    {
        if (! $user) {
            return null;
        }

        if (! empty($user->department_id)) {
            return (int) $user->department_id;
        }

        if (! SchemaCache::hasTable('departments')) {
            return null;
        }

        $roles = collect();
        if (method_exists($user, 'getRoleNames')) {
            try {
                $roles = $user->getRoleNames()->map(static fn ($role) => strtolower((string) $role));
            } catch (\Throwable $e) {
            }
        }

        $roleText = ' '.$roles->implode(' ').' '.strtolower((string) ($user->role ?? '')).' ';
        $query = Department::query();

        if (preg_match('/sales_manager|marketing_manager/', $roleText)) {
            $query->where(function ($q) {
                $q->where('code', 'sales')->orWhereRaw('LOWER(name) LIKE ?', ['%marketing%']);
            });
        } elseif (preg_match('/technical|technical|truong_phong_ky_thuat/', $roleText)) {
            $query->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%kỹ thuật%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%ky thuat%'])
                    ->orWhereIn('code', ['technical', 'engineering']);
            });
        } elseif (preg_match('/accounting|ke_toan|chief_accountant|warehouse|kho_manager/', $roleText)) {
            $query->where(function ($q) {
                $q->where('code', 'warehouse')
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%kế toán%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%kho%']);
            });
        } elseif (preg_match('/hr_manager|human_resources/', $roleText)) {
            $query->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%nhân sự%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%hanh chinh%']);
            });
        } else {
            return null;
        }

        $departmentId = $query->value('id');

        return $departmentId ? (int) $departmentId : null;
    }

    /**
     * Giới hạn dữ liệu công việc theo phạm vi của người quản lý.
     */
    private function scopeVisibleTasks($query, $user)
    {
        if ($this->isGlobalTaskManager($user)) {
            return $query;
        }

        if ($this->isDepartmentTaskManager($user)) {
            $departmentId = $this->managedDepartmentId($user);

            if (! $departmentId) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('assignee', function ($assigneeQuery) use ($departmentId) {
                $assigneeQuery->where('department_id', $departmentId);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Danh sách nhân viên được phép giao việc.
     */
    private function assignableUsers($user)
    {
        $query = User::query()
            ->with('department')
            ->where(function ($activeQuery) {
                $activeQuery->whereNull('is_active')->orWhere('is_active', true);
            })
            ->orderBy('name');

        if ($this->isGlobalTaskManager($user)) {
            return $query->get();
        }

        if ($this->isDepartmentTaskManager($user)) {
            $departmentId = $this->managedDepartmentId($user);

            if (! $departmentId) {
                return collect();
            }

            return $query->where('department_id', $departmentId)->get();
        }

        return collect();
    }

    /**
     * Kiểm tra quyền quản lý một công việc cụ thể.
     */
    private function canManageTask($user, Task $task): bool
    {
        if (! $user) {
            return false;
        }

        if ((int) ($task->requester_id ?? 0) === (int) $user->id) {
            return true;
        }

        if ($this->isGlobalTaskManager($user)) {
            return true;
        }

        if (! $this->isDepartmentTaskManager($user)) {
            return false;
        }

        $departmentId = $this->managedDepartmentId($user);
        $assigneeDepartmentId = (int) optional($task->assignee)->department_id;

        if (! $assigneeDepartmentId && ! empty($task->assignee_id)) {
            $assigneeDepartmentId = (int) User::query()
                ->whereKey($task->assignee_id)
                ->value('department_id');
        }

        return $departmentId && $assigneeDepartmentId === (int) $departmentId;
    }

    /**
     * Chặn giao việc ra ngoài phạm vi phòng ban được quản lý.
     */
    private function validateAssignableUserIds($user, $assigneeIds): void
    {
        $requestedIds = collect($assigneeIds)
            ->map(static fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $allowedIds = $this->assignableUsers($user)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id);

        if ($requestedIds->diff($allowedIds)->isNotEmpty()) {
            abort(403, 'Bạn chỉ được giao việc cho nhân viên thuộc phạm vi phòng ban mình quản lý.');
        }
    }

    /**
     * Kiểm tra bảng có tồn tại trong database.
     */
    private function tableExists(string $table): bool
    {
        return SchemaCache::hasTable($table);
    }

    /**
     * Lọc dữ liệu chỉ giữ các cột có trong bảng.
     */
    private function filterColumns(string $table, array $data): array
    {
        if (! SchemaCache::hasTable($table)) {
            return $data;
        }

        return collect($data)
            ->only(SchemaCache::columns($table))
            ->toArray();
    }

    /**
     * Lấy link chi tiết công việc dùng trong thông báo.
     */
    private function taskNotificationLink(Task $task): string
    {
        try {
            if (Route::has('tasks.show')) {
                return route('tasks.show', $task);
            }
        } catch (\Throwable $e) {
        }

        return url('/chat/tasks/'.$task->id);
    }

    /**
     * Ghi thông báo giao việc cho người được giao.
     */
    private function notifyTaskAssigned(Task $task): void
    {
        try {
            if (empty($task->assignee_id)) {
                return;
            }

            $assigner = optional(auth()->user())->name ?: 'Hệ thống';
            $dueText = '';

            if (! empty($task->due_at)) {
                try {
                    $dueText = ' - Hạn: '.Carbon::parse($task->due_at)->format('d/m/Y H:i');
                } catch (\Throwable $e) {
                    $dueText = ' - Hạn: '.(string) $task->due_at;
                }
            }

            DB::table('task_notifications')->insert($this->filterColumns('task_notifications', [
                'task_id' => $task->id,
                'user_id' => (int) $task->assignee_id,
                'created_by' => auth()->id(),
                'type' => 'assigned',
                'title' => '📌 Công việc mới được giao',
                'message' => $assigner.' đã giao cho bạn công việc: '.$task->title.$dueText,
                'link' => $this->taskNotificationLink($task),
                'is_read' => 0,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (\Throwable $e) {
            report($e);
        }
    }
    /* EGO_TASK_NOTIFY_END */

    /**
     * Danh sách mức độ ưu tiên công việc.
     */
    private function priorities(): array
    {
        return [
            'low' => 'Thấp',
            'medium' => 'Bình thường',
            'high' => 'Cao',
        ];
    }

    /**
     * Danh sách trạng thái công việc.
     */
    private function statuses(): array
    {
        return [
            'new' => 'Mới giao',
            'in_progress' => 'Đang thực hiện',
            'submitted' => 'Chờ duyệt',
            'revision' => 'Cần bổ sung',
            'rejected' => 'Đã từ chối',
            'approved' => 'Hoàn thành',
        ];
    }

    /**
     * Lưu file đính kèm của công việc theo loại (giao việc/kết quả/trả hồ sơ).
     */
    private function storeAttachments(Request $request, Task $task, string $inputName, string $type): void
    {
        if (! $request->hasFile($inputName)) {
            return;
        }

        foreach ($request->file($inputName) as $file) {
            if (! $file) {
                continue;
            }

            $path = $file->store('task_attachments/'.$task->id, 'public');

            if ($this->tableExists('task_attachments')) {
                DB::table('task_attachments')->insert([
                    'task_id' => $task->id,
                    'uploaded_by' => auth()->id(),
                    'type' => $type,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_mime' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Lấy danh sách file đính kèm của công việc.
     */
    private function attachmentsFor(Task $task)
    {
        if (! $this->tableExists('task_attachments')) {
            return collect();
        }

        return DB::table('task_attachments')
            ->where('task_id', $task->id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Ghi một mốc lịch sử báo cáo/duyệt của công việc.
     */
    private function recordWorkReport(Task $task, array $data): void
    {
        if (! $this->tableExists('task_work_reports')) {
            return;
        }

        $payload = array_merge([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'report_date' => now()->toDateString(),
            'progress_percent' => (int) ($task->progress_percent ?? 0),
            'result_note' => $task->result_note,
            'employee_note' => null,
            'status' => (string) ($task->status ?? 'submitted'),
            'submitted_at' => $task->submitted_at ?? now(),
            'approved_at' => $task->approved_at,
            'approved_by' => $task->approved_by,
            'manager_feedback' => $task->manager_feedback,
            'revision_reason' => $task->revision_reason,
            'created_at' => now(),
            'updated_at' => now(),
        ], $data);

        DB::table('task_work_reports')->insert(
            $this->filterColumns('task_work_reports', $payload)
        );
    }

    /**
     * Lịch sử báo cáo dùng trong trang chi tiết.
     */
    private function workReportsFor(Task $task)
    {
        if (! $this->tableExists('task_work_reports')) {
            return collect();
        }

        $query = DB::table('task_work_reports as reports')
            ->leftJoin('users as report_users', 'report_users.id', '=', 'reports.user_id')
            ->leftJoin('users as approver_users', 'approver_users.id', '=', 'reports.approved_by')
            ->where('reports.task_id', $task->id)
            ->select([
                'reports.*',
                'report_users.name as user_name',
                'approver_users.name as approver_name',
            ])
            ->orderByDesc('reports.id');

        return $query->get();
    }

    /**
     * Hiển thị toàn bộ công việc gom theo phòng ban (chỉ quản lý).
     */
    public function index()
    {
        $user = auth()->user();

        // Nhân viên thường (HR, kỹ thuật, sales...) vẫn được vào mục Công việc.
        // Nếu không có quyền giao/quản lý việc thì chuyển về danh sách việc của chính họ
        // thay vì trả 403 khi truy cập /chat/tasks.
        if (! $this->canAssign($user)) {
            return redirect()->route('tasks.my');
        }

        $allTasks = $this->scopeVisibleTasks(
            Task::query()->with(['assignee.department', 'requester']),
            $user
        )
            ->latest()
            ->get();

        $now = now();
        $unfinishedStatuses = ['new', 'in_progress', 'submitted', 'revision', 'rejected'];

        $summary = [
            'total' => $allTasks->count(),
            'new' => $allTasks->where('status', 'new')->count(),
            'in_progress' => $allTasks->where('status', 'in_progress')->count(),
            'submitted' => $allTasks->where('status', 'submitted')->count(),
            'revision' => $allTasks->whereIn('status', ['revision', 'rejected'])->count(),
            'approved' => $allTasks->where('status', 'approved')->count(),
            'overdue' => $allTasks->filter(function ($task) use ($now, $unfinishedStatuses) {
                return ! empty($task->due_at)
                    && in_array((string) $task->status, $unfinishedStatuses, true)
                    && Carbon::parse($task->due_at)->lt($now);
            })->count(),
        ];

        if ($this->isGlobalTaskManager($user)) {
            $departments = SchemaCache::hasTable('departments')
                ? Department::orderBy('name')->get()
                : collect();
        } else {
            $departmentId = $this->managedDepartmentId($user);
            $departments = $departmentId
                ? Department::whereKey($departmentId)->get()
                : collect();
        }

        $taskGroups = collect();

        foreach ($departments as $department) {
            $departmentTasks = $allTasks->filter(function ($task) use ($department) {
                return (int) optional($task->assignee)->department_id === (int) $department->id;
            })->values();

            $taskGroups->push([
                'id' => $department->id,
                'name' => $department->name,
                'tasks' => $departmentTasks,
                'total' => $departmentTasks->count(),
                'new' => $departmentTasks->where('status', 'new')->count(),
                'in_progress' => $departmentTasks->where('status', 'in_progress')->count(),
                'submitted' => $departmentTasks->where('status', 'submitted')->count(),
                'revision' => $departmentTasks->whereIn('status', ['revision', 'rejected'])->count(),
                'approved' => $departmentTasks->where('status', 'approved')->count(),
                'overdue' => $departmentTasks->filter(function ($task) use ($now, $unfinishedStatuses) {
                    return ! empty($task->due_at)
                        && in_array((string) $task->status, $unfinishedStatuses, true)
                        && Carbon::parse($task->due_at)->lt($now);
                })->count(),
            ]);
        }

        $noDepartmentTasks = $allTasks->filter(function ($task) {
            return empty(optional($task->assignee)->department_id);
        })->values();

        if ($this->isGlobalTaskManager($user) && ($noDepartmentTasks->isNotEmpty() || $departments->isEmpty())) {
            $taskGroups->push([
                'id' => 0,
                'name' => 'Chưa có phòng ban',
                'tasks' => $noDepartmentTasks,
                'total' => $noDepartmentTasks->count(),
                'new' => $noDepartmentTasks->where('status', 'new')->count(),
                'in_progress' => $noDepartmentTasks->where('status', 'in_progress')->count(),
                'submitted' => $noDepartmentTasks->where('status', 'submitted')->count(),
                'revision' => $noDepartmentTasks->whereIn('status', ['revision', 'rejected'])->count(),
                'approved' => $noDepartmentTasks->where('status', 'approved')->count(),
                'overdue' => $noDepartmentTasks->filter(function ($task) use ($now, $unfinishedStatuses) {
                    return ! empty($task->due_at)
                        && in_array((string) $task->status, $unfinishedStatuses, true)
                        && Carbon::parse($task->due_at)->lt($now);
                })->count(),
            ]);
        }

        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $assignableUsers = $this->assignableUsers($user);
        $isDepartmentScoped = ! $this->isGlobalTaskManager($user);
        $managedDepartment = $isDepartmentScoped && $this->managedDepartmentId($user)
            ? Department::find($this->managedDepartmentId($user))
            : null;

        return view('tasks.index', compact(
            'allTasks',
            'taskGroups',
            'summary',
            'priorities',
            'statuses',
            'assignableUsers',
            'isDepartmentScoped',
            'managedDepartment'
        ));
    }

    /**
     * Hiển thị danh sách công việc được giao cho người dùng hiện tại.
     */
    public function my(Request $request)
    {
        $userId = (int) auth()->id();
        $unfinishedStatuses = ['new', 'in_progress', 'submitted', 'revision', 'rejected'];

        $baseQuery = Task::query()->where('assignee_id', $userId);
        $now = now();

        $summaryTasks = (clone $baseQuery)->get(['id', 'status', 'due_at']);
        $summary = [
            'total' => $summaryTasks->count(),
            'new' => $summaryTasks->where('status', 'new')->count(),
            'in_progress' => $summaryTasks->where('status', 'in_progress')->count(),
            'submitted' => $summaryTasks->where('status', 'submitted')->count(),
            'revision' => $summaryTasks->whereIn('status', ['revision', 'rejected'])->count(),
            'approved' => $summaryTasks->where('status', 'approved')->count(),
            'overdue' => $summaryTasks->filter(function ($task) use ($now, $unfinishedStatuses) {
                return ! empty($task->due_at)
                    && in_array((string) $task->status, $unfinishedStatuses, true)
                    && Carbon::parse($task->due_at)->lt($now);
            })->count(),
        ];

        $query = Task::with(['requester'])
            ->where('assignee_id', $userId);

        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $priorityFilter = trim((string) $request->query('priority', ''));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        if ($search !== '') {
            $query->where(function ($taskQuery) use ($search) {
                $taskQuery->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('result_note', 'like', '%'.$search.'%')
                    ->orWhereHas('requester', function ($requesterQuery) use ($search) {
                        $requesterQuery->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($statusFilter === 'overdue') {
            $query->whereIn('status', $unfinishedStatuses)
                ->whereNotNull('due_at')
                ->where('due_at', '<', $now);
        } elseif (in_array($statusFilter, array_keys($this->statuses()), true)) {
            $query->where('status', $statusFilter);
        }

        if (in_array($priorityFilter, array_keys($this->priorities()), true)) {
            $query->where('priority', $priorityFilter);
        }

        if ($dateFrom !== '') {
            try {
                $query->whereDate('created_at', '>=', Carbon::parse($dateFrom)->toDateString());
            } catch (\Throwable $e) {
            }
        }

        if ($dateTo !== '') {
            try {
                $query->whereDate('created_at', '<=', Carbon::parse($dateTo)->toDateString());
            } catch (\Throwable $e) {
            }
        }

        $tasks = $query
            ->orderByRaw("CASE status WHEN 'revision' THEN 1 WHEN 'rejected' THEN 2 WHEN 'submitted' THEN 3 WHEN 'in_progress' THEN 4 WHEN 'new' THEN 5 WHEN 'approved' THEN 6 ELSE 7 END")
            ->orderByDesc('updated_at')
            ->paginate(25)
            ->withQueryString();

        $taskIds = collect($tasks->items())->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $latestReports = collect();
        $resultAttachmentCounts = collect();

        if ($taskIds && $this->tableExists('task_work_reports')) {
            $latestReports = DB::table('task_work_reports')
                ->whereIn('task_id', $taskIds)
                ->orderByDesc('id')
                ->get()
                ->unique('task_id')
                ->keyBy('task_id');
        }

        if ($taskIds && $this->tableExists('task_attachments')) {
            $resultAttachmentCounts = DB::table('task_attachments')
                ->whereIn('task_id', $taskIds)
                ->where('type', 'result')
                ->select('task_id', DB::raw('COUNT(*) as total'))
                ->groupBy('task_id')
                ->pluck('total', 'task_id');
        }

        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $canBossEdit = $this->canAssign(auth()->user());
        $filters = compact('search', 'statusFilter', 'priorityFilter', 'dateFrom', 'dateTo');

        return view('tasks.my', compact(
            'tasks',
            'summary',
            'priorities',
            'statuses',
            'canBossEdit',
            'latestReports',
            'resultAttachmentCounts',
            'filters'
        ));
    }

    /**
     * Hiển thị form giao việc.
     *
     * @param  Request  $request  Có thể kèm ?site_id= để gắn sẵn công trình vào việc mới.
     */
    public function create(Request $request)
    {
        if (! $this->canAssign(auth()->user())) {
            abort(403);
        }

        $users = $this->assignableUsers(auth()->user());
        $isDepartmentScoped = ! $this->isGlobalTaskManager(auth()->user());
        $managedDepartment = $isDepartmentScoped && $this->managedDepartmentId(auth()->user())
            ? Department::find($this->managedDepartmentId(auth()->user()))
            : null;
        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $projectSite = null;
        $requestedSiteId = (int) $request->query('site_id', 0);
        if ($requestedSiteId > 0 && SchemaCache::hasTable('sites')) {
            $projectSite = Site::query()->find($requestedSiteId);
        }

        return view('tasks.create', compact('users', 'priorities', 'statuses', 'isDepartmentScoped', 'managedDepartment', 'projectSite'));
    }

    /**
     * Tạo công việc cho từng người được giao và gửi thông báo.
     */
    public function store(Request $request)
    {
        if (! $this->canAssign(auth()->user())) {
            abort(403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignee_ids' => 'required|array|min:1',
            'assignee_ids.*' => 'required|integer|exists:users,id',
            'priority' => 'required|in:low,medium,high',
            'due_at' => 'nullable|date',
            'link_url' => 'nullable|url|max:2048',
            'site_id' => 'nullable|integer|exists:sites,id',
            'attachments.*' => 'nullable|file|max:51200',
        ]);

        $assigneeIds = collect($data['assignee_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $this->validateAssignableUserIds(auth()->user(), $assigneeIds);

        $createdTasks = collect();

        foreach ($assigneeIds as $assigneeId) {
            $taskData = $data;
            unset($taskData['assignee_ids']);

            $taskData['assignee_id'] = $assigneeId;
            $taskData['requester_id'] = auth()->id();
            $taskData['status'] = 'new';
            $taskData['progress_percent'] = 0;

            $task = Task::create($this->filterColumns('tasks', $taskData));
            $createdTasks->push($task);

            $this->storeAttachments($request, $task, 'attachments', 'task');
            $this->notifyTaskAssigned($task);
        }

        if ($createdTasks->count() === 1) {
            return redirect()
                ->route('tasks.show', $createdTasks->first())
                ->with('success', 'Đã giao việc thành công.');
        }

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Đã giao việc cho '.$createdTasks->count().' người.');
    }

    /**
     * Hiển thị chi tiết công việc kèm các task cùng nhóm giao nhiều người.
     */
    public function show(Task $task)
    {
        $isOwner = (int) $task->assignee_id === (int) auth()->id();
        $isRequester = (int) $task->requester_id === (int) auth()->id();

        if (! $isOwner && ! $isRequester && ! $this->canManageTask(auth()->user(), $task)) {
            abort(403);
        }

        $task->load(['assignee', 'requester', 'approver']);

        /*
         * Khi giao cho nhiều người, hệ thống tạo nhiều task riêng.
         * Trang chi tiết sẽ gom các task cùng nhóm để hiển thị đầy đủ người nhận.
         * Ưu tiên gom theo task_group_id nếu có cột này.
         * Nếu chưa có task_group_id thì gom theo tiêu đề + người giao + hạn + mô tả.
         */
        $relatedTasksQuery = Task::with('assignee');

        if (
            SchemaCache::hasColumn('tasks', 'task_group_id')
            && ! empty($task->task_group_id)
        ) {
            $relatedTasksQuery->where('task_group_id', $task->task_group_id);
        } else {
            $relatedTasksQuery
                ->where('title', $task->title)
                ->where('requester_id', $task->requester_id)
                ->where(function ($q) use ($task) {
                    if (empty($task->due_at)) {
                        $q->whereNull('due_at');
                    } else {
                        $q->where('due_at', $task->due_at);
                    }
                })
                ->where(function ($q) use ($task) {
                    if (empty($task->description)) {
                        $q->whereNull('description')->orWhere('description', '');
                    } else {
                        $q->where('description', $task->description);
                    }
                });
        }

        $relatedTasks = $relatedTasksQuery
            ->orderBy('id')
            ->get();

        if ($relatedTasks->isEmpty()) {
            $relatedTasks = collect([$task]);
        }

        $attachments = $this->attachmentsFor($task);
        $workReports = $this->workReportsFor($task);
        $priorities = $this->priorities();
        $statuses = $this->statuses();
        $canApprove = $this->canManageTask(auth()->user(), $task) || $isRequester;
        $canUpdate = $isOwner;

        return view('tasks.show', compact(
            'task',
            'relatedTasks',
            'attachments',
            'priorities',
            'statuses',
            'canApprove',
            'canUpdate',
            'workReports'
        ));
    }

    /**
     * Hiển thị form sửa công việc.
     */
    public function edit(Task $task)
    {
        if (! $this->canManageTask(auth()->user(), $task)) {
            abort(403);
        }

        $users = $this->assignableUsers(auth()->user());
        $priorities = $this->priorities();
        $statuses = $this->statuses();

        return view('tasks.edit', compact('task', 'users', 'priorities', 'statuses'));
    }

    /**
     * Cập nhật công việc và giao thêm cho người nhận mới nếu có.
     */
    public function update(Request $request, Task $task)
    {
        if (! $this->canManageTask(auth()->user(), $task)) {
            abort(403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignee_ids' => 'required|array|min:1',
            'assignee_ids.*' => 'required|integer|exists:users,id',
            'priority' => 'required|in:low,medium,high',
            'status' => 'required|in:new,in_progress,submitted,revision,rejected,approved',
            'due_at' => 'nullable|date',
            'link_url' => 'nullable|url|max:2048',
            'site_id' => 'nullable|integer|exists:sites,id',
            'attachments.*' => 'nullable|file|max:51200',
        ]);

        $assigneeIds = collect($data['assignee_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $this->validateAssignableUserIds(auth()->user(), $assigneeIds);

        $mainAssigneeId = (int) $assigneeIds->first();
        $oldAssigneeId = (int) ($task->assignee_id ?? 0);

        $updateData = $data;
        unset($updateData['assignee_ids']);
        $updateData['assignee_id'] = $mainAssigneeId;

        $task->update($this->filterColumns('tasks', $updateData));
        $this->storeAttachments($request, $task, 'attachments', 'task');

        if ($oldAssigneeId !== $mainAssigneeId) {
            $this->notifyTaskAssigned($task->fresh());
        }

        $createdCount = 0;

        foreach ($assigneeIds->skip(1) as $assigneeId) {
            $taskData = $updateData;
            $taskData['assignee_id'] = $assigneeId;
            $taskData['requester_id'] = $task->requester_id ?: auth()->id();
            $taskData['status'] = $data['status'] ?? 'new';
            $taskData['progress_percent'] = 0;

            $newTask = Task::create($this->filterColumns('tasks', $taskData));
            $this->storeAttachments($request, $newTask, 'attachments', 'task');
            $this->notifyTaskAssigned($newTask);
            $createdCount++;
        }

        if ($createdCount > 0) {
            return redirect()
                ->route('tasks.index')
                ->with('success', 'Đã cập nhật công việc và giao thêm cho '.$createdCount.' người.');
        }

        return redirect()
            ->route('tasks.show', $task)
            ->with('success', 'Đã cập nhật công việc.');
    }

    /**
     * Người nhận việc cập nhật trạng thái và tiến độ.
     */
    public function updateStatus(Request $request, Task $task)
    {
        if ((int) $task->assignee_id !== (int) auth()->id()) {
            abort(403);
        }

        $data = $request->validate([
            'status' => 'required|in:new,in_progress',
            'progress_percent' => 'nullable|integer|min:0|max:100',
        ]);

        $task->status = $data['status'];
        $task->progress_percent = (int) ($data['progress_percent'] ?? $task->progress_percent);
        $task->save();

        return back()->with('success', 'Đã cập nhật tiến độ công việc.');
    }

    /* EGO_BOSS_TASK_PERMISSION_START */
    /**
     * Kiểm tra sếp/quản lý có quyền chỉnh sửa công việc.
     */
    private function canBossEditTask(Task $task): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ((int) ($task->requester_id ?? 0) === (int) $user->id) {
            return true;
        }

        if ($this->canManageTask($user, $task)) {
            return true;
        }

        $bossRoles = [
            'admin',
            'manager',
            'management',
            'director',
            'general_director',
            'ban_giam_doc',
            'giam_doc',
            'ceo',
            'assistant',
            'tro_ly',
            'sales_manager',
            'marketing_manager',
            'accounting',
            'ke_toan',
        ];

        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole($bossRoles)) {
                    return true;
                }
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($user, 'hasRole')) {
            try {
                foreach ($bossRoles as $role) {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($user, 'getRoleNames')) {
            try {
                $roleNames = $user->getRoleNames()->map(fn ($r) => strtolower((string) $r))->toArray();

                foreach ($bossRoles as $role) {
                    if (in_array($role, $roleNames, true)) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $roleValue = strtolower((string) ($user->role ?? $user->type ?? $user->position ?? ''));
        $textValue = strtolower(
            (string) ($user->email ?? '').' '.
            (string) ($user->name ?? '').' '.
            $roleValue
        );

        foreach ($bossRoles as $role) {
            if ($roleValue === $role || str_contains($textValue, $role)) {
                return true;
            }
        }

        if (
            str_contains($textValue, 'giám đốc') ||
            str_contains($textValue, 'giam doc') ||
            str_contains($textValue, 'ban giám đốc') ||
            str_contains($textValue, 'ban giam doc')
        ) {
            return true;
        }

        return false;
    }
    /* EGO_BOSS_TASK_PERMISSION_END */

    /**
     * Nộp kết quả công việc để chờ duyệt (hỗ trợ nộp lại sau khi bị trả).
     */
    public function submitResult(Request $request, Task $task)
    {
        if ($request->isMethod('get')) {
            return redirect()
                ->route('tasks.show', $task)
                ->with('error', 'Vui lòng nộp kết quả bằng form trong trang chi tiết công việc.');
        }

        $authId = (int) auth()->id();
        $isAssignee = (int) ($task->assignee_id ?? 0) === $authId;
        $isRequester = (int) ($task->requester_id ?? 0) === $authId;
        $canManage = $isRequester || $this->canManageTask(auth()->user(), $task);

        abort_unless($isAssignee || $canManage, 403);

        $oldStatus = (string) ($task->status ?? '');
        $managerEditingApproved = $oldStatus === 'approved' && $canManage && ! $isAssignee;

        $data = $request->validate([
            'report_date' => 'nullable|date',
            'result_note' => 'nullable|string|max:20000',
            'employee_note' => 'nullable|string|max:10000',
            'progress_percent' => 'nullable|integer|min:0|max:100',
            'result_attachments.*' => 'nullable|file|max:51200',
            'clear_result_attachments' => 'nullable|boolean',
        ], [
            'result_attachments.*.max' => 'File kết quả tối đa 50MB/file.',
        ]);

        $task->result_note = $data['result_note'] ?? null;
        $task->progress_percent = (int) ($data['progress_percent'] ?? 100);

        if ($managerEditingApproved) {
            $task->status = 'approved';
            $task->completed_at = $task->completed_at ?: now();
        } else {
            $task->status = 'submitted';
            $task->submitted_at = now();
            $task->approved_at = null;
            $task->approved_by = null;
            $task->completed_at = null;
            $task->manager_feedback = null;
        }

        if (in_array($oldStatus, ['revision', 'rejected'], true)) {
            $task->resubmitted_at = now();
        }

        $task->save();

        if ($request->boolean('clear_result_attachments') && $this->tableExists('task_attachments')) {
            $oldFiles = DB::table('task_attachments')
                ->where('task_id', $task->id)
                ->where('type', 'result')
                ->get();

            foreach ($oldFiles as $oldFile) {
                if (! empty($oldFile->file_path)) {
                    Storage::disk('public')->delete($oldFile->file_path);
                }
            }

            DB::table('task_attachments')
                ->where('task_id', $task->id)
                ->where('type', 'result')
                ->delete();
        }

        $this->storeAttachments($request, $task, 'result_attachments', 'result');

        $historyStatus = $managerEditingApproved
            ? 'manager_updated'
            : (in_array($oldStatus, ['revision', 'rejected'], true) ? 'resubmitted' : 'submitted');

        $this->recordWorkReport($task, [
            'user_id' => $authId,
            'report_date' => $data['report_date'] ?? now()->toDateString(),
            'progress_percent' => (int) $task->progress_percent,
            'result_note' => $task->result_note,
            'employee_note' => $data['employee_note'] ?? null,
            'status' => $historyStatus,
            'submitted_at' => now(),
            'approved_at' => $managerEditingApproved ? ($task->approved_at ?? now()) : null,
            'approved_by' => $managerEditingApproved ? $authId : null,
            'manager_feedback' => $managerEditingApproved ? ($task->manager_feedback ?? null) : null,
            'revision_reason' => null,
        ]);

        if ($managerEditingApproved) {
            return back()->with('success', 'Đã cập nhật kết quả của công việc đã duyệt.');
        }

        return back()->with('success', in_array($oldStatus, ['revision', 'rejected'], true)
            ? 'Đã nộp lại báo cáo, chờ quản lý duyệt lại.'
            : 'Đã nộp báo cáo, chờ quản lý duyệt.');
    }

    /* EGO_TASK_DELETE_ATTACHMENT_START */

    /**
     * Thay file đính kèm cũ bằng file mới.
     */
    public function replaceAttachment(Request $request, Task $task, $attachment)
    {
        if (! $this->tableExists('task_attachments')) {
            abort(404);
        }

        $file = DB::table('task_attachments')
            ->where('id', (int) $attachment)
            ->where('task_id', $task->id)
            ->first();

        abort_unless($file, 404);

        $isRequester = (int) ($task->requester_id ?? 0) === (int) auth()->id();
        $isUploader = (int) ($file->uploaded_by ?? 0) === (int) auth()->id();
        $canManage = $isRequester || $this->canManageTask(auth()->user(), $task);

        abort_unless($canManage || $isUploader, 403);

        $data = $request->validate([
            'file' => 'required|file|max:51200',
        ], [
            'file.required' => 'Vui lòng chọn file mới.',
            'file.file' => 'File không hợp lệ.',
            'file.max' => 'File tối đa 50MB.',
        ]);

        $uploadedFile = $request->file('file');

        if (! empty($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        $path = $uploadedFile->store('task_attachments/'.$task->id, 'public');

        DB::table('task_attachments')
            ->where('id', (int) $attachment)
            ->where('task_id', $task->id)
            ->update($this->filterColumns('task_attachments', [
                'uploaded_by' => auth()->id(),
                'file_name' => $uploadedFile->getClientOriginalName(),
                'file_path' => $path,
                'file_mime' => $uploadedFile->getClientMimeType(),
                'file_size' => $uploadedFile->getSize(),
                'updated_at' => now(),
            ]));

        return back()->with('success', 'Đã thay file thành công.');
    }

    /**
     * Xóa file đính kèm theo quyền của người dùng.
     */
    public function destroyAttachment(Task $task, $attachment)
    {
        if (! $this->tableExists('task_attachments')) {
            abort(404);
        }

        $file = DB::table('task_attachments')
            ->where('id', (int) $attachment)
            ->where('task_id', $task->id)
            ->first();

        abort_unless($file, 404);

        $isOwner = (int) $task->assignee_id === (int) auth()->id();
        $isRequester = (int) $task->requester_id === (int) auth()->id();
        $canManage = $isRequester || $this->canManageTask(auth()->user(), $task);

        $fileType = (string) ($file->type ?? 'task');
        $isResultFile = $fileType === 'result';

        /*
         * Người giao việc / Admin / Quản lý: được xóa mọi file của công việc.
         * Người nhận việc: chỉ được xóa file kết quả của mình khi công việc chưa duyệt.
         */
        if (! $canManage) {
            abort_unless($isOwner && $isResultFile && $task->status !== 'approved', 403);
        }

        if (! $canManage && $isOwner && $task->status === 'approved') {
            return back()->with('error', 'Công việc đã được duyệt nên không thể xóa file kết quả.');
        }

        if (! empty($file->file_path)) {
            Storage::disk('public')->delete($file->file_path);
        }

        DB::table('task_attachments')
            ->where('id', (int) $attachment)
            ->where('task_id', $task->id)
            ->delete();

        return back()->with('success', $isResultFile ? 'Đã xóa file kết quả.' : 'Đã xóa file giao việc.');
    }
    /* EGO_TASK_DELETE_ATTACHMENT_END */

    /**
     * Trả hồ sơ yêu cầu nhân viên sửa đổi/bổ sung và nộp lại.
     */
    public function returnRevision(Request $request, Task $task)
    {
        if (! $this->canManageTask(auth()->user(), $task)) {
            abort(403);
        }

        $data = $request->validate([
            'revision_reason' => 'required|string|max:5000',
            'revision_attachments.*' => 'nullable|file|max:51200',
        ], [
            'revision_reason.required' => 'Vui lòng nhập lý do từ chối / yêu cầu sửa đổi bổ sung.',
            'revision_attachments.*.file' => 'File đính kèm không hợp lệ.',
            'revision_attachments.*.max' => 'File đính kèm tối đa 50MB/file.',
        ]);

        $reason = trim((string) $data['revision_reason']);

        $task->status = 'revision';
        $task->completed_at = null;
        $task->approved_at = null;
        $task->approved_by = null;
        $task->manager_feedback = $reason;

        if ((int) ($task->progress_percent ?? 0) >= 100) {
            $task->progress_percent = 90;
        }

        if (SchemaCache::hasColumn('tasks', 'revision_reason')) {
            $task->revision_reason = $reason;
        }

        if (SchemaCache::hasColumn('tasks', 'revision_requested_by')) {
            $task->revision_requested_by = auth()->id();
        }

        if (SchemaCache::hasColumn('tasks', 'revision_requested_at')) {
            $task->revision_requested_at = now();
        }

        if (SchemaCache::hasColumn('tasks', 'rejection_reason')) {
            $task->rejection_reason = $reason;
        }

        if (SchemaCache::hasColumn('tasks', 'rejected_by')) {
            $task->rejected_by = auth()->id();
        }

        if (SchemaCache::hasColumn('tasks', 'rejected_at')) {
            $task->rejected_at = now();
        }

        $task->save();

        // File Admin/Sếp gắn khi trả hồ sơ
        if ($request->hasFile('revision_attachments')) {
            $this->storeAttachments($request, $task, 'revision_attachments', 'revision');
        }

        $this->recordWorkReport($task, [
            'user_id' => auth()->id(),
            'report_date' => now()->toDateString(),
            'status' => 'revision',
            'submitted_at' => $task->submitted_at,
            'approved_at' => null,
            'approved_by' => null,
            'manager_feedback' => $reason,
            'revision_reason' => $reason,
        ]);

        return back()->with('success', 'Đã trả hồ sơ để nhân viên sửa đổi / bổ sung và nộp lại.');
    }

    /**
     * Duyệt hoàn thành công việc đã nộp.
     */
    public function approve(Request $request, Task $task)
    {
        if (! $this->canManageTask(auth()->user(), $task)) {
            abort(403);
        }

        if ($task->status !== 'submitted') {
            return back()->with('error', 'Công việc chưa được nộp báo cáo.');
        }

        $data = $request->validate([
            'manager_feedback' => 'nullable|string|max:10000',
        ]);

        $task->status = 'approved';
        $task->progress_percent = 100;
        $task->approved_at = now();
        $task->approved_by = auth()->id();
        $task->completed_at = now();
        $task->manager_feedback = $data['manager_feedback'] ?? null;
        $task->save();

        $this->recordWorkReport($task, [
            'user_id' => (int) ($task->assignee_id ?? auth()->id()),
            'report_date' => optional($task->submitted_at)->toDateString() ?: now()->toDateString(),
            'progress_percent' => 100,
            'result_note' => $task->result_note,
            'status' => 'approved',
            'submitted_at' => $task->submitted_at,
            'approved_at' => $task->approved_at,
            'approved_by' => auth()->id(),
            'manager_feedback' => $task->manager_feedback,
            'revision_reason' => null,
        ]);

        return back()->with('success', 'Đã duyệt hoàn thành báo cáo công việc.');
    }

    /**
     * Xóa công việc và toàn bộ file đính kèm.
     */
    public function destroy(Task $task)
    {
        if (! $this->canManageTask(auth()->user(), $task)) {
            abort(403);
        }

        if ($this->tableExists('task_attachments')) {
            $attachments = DB::table('task_attachments')
                ->where('task_id', $task->id)
                ->get();

            foreach ($attachments as $file) {
                Storage::disk('public')->delete($file->file_path);
            }

            DB::table('task_attachments')
                ->where('task_id', $task->id)
                ->delete();
        }

        if (! empty($task->attachment_path)) {
            Storage::disk('public')->delete($task->attachment_path);
        }

        if (! empty($task->result_attachment_path)) {
            Storage::disk('public')->delete($task->result_attachment_path);
        }

        if ($this->tableExists('task_work_reports')) {
            DB::table('task_work_reports')->where('task_id', $task->id)->delete();
        }

        $task->delete();

        return redirect()
            ->route('tasks.index')
            ->with('success', 'Đã xóa công việc.');
    }
}
