<?php

namespace App\Services\Ai\Tools;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Models\Tasks\Task;
use App\Models\User;
use App\Support\SchemaCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TaskTool
{
    private const STATUS_LABELS = [
        'new' => 'Mới giao',
        'in_progress' => 'Đang làm',
        'submitted' => 'Đã nộp',
        'revision' => 'Cần sửa / bổ sung',
        'rejected' => 'Bị từ chối',
        'approved' => 'Đã duyệt',
    ];

    private const PRIORITY_LABELS = [
        'low' => 'Thấp',
        'medium' => 'Bình thường',
        'high' => 'Cao',
    ];

    public function __construct(private readonly PageAccessServiceInterface $pageAccess) {}

    public function supports(string $name): bool
    {
        return in_array($name, ['search_tasks', 'summarize_tasks'], true);
    }

    public function execute(string $name, array $arguments, User $user): array
    {
        if (! SchemaCache::hasTable('tasks')) {
            return [
                'ok' => false,
                'error' => 'table_missing',
                'message' => 'Hệ thống chưa có bảng Công việc.',
                'result_count' => 0,
            ];
        }

        if (! $this->pageAccess->canAccess($user, 'page.tasks')) {
            return [
                'ok' => false,
                'error' => 'forbidden',
                'message' => 'Tài khoản không có quyền truy cập module Công việc.',
                'result_count' => 0,
            ];
        }

        return match ($name) {
            'search_tasks' => $this->search($arguments, $user),
            'summarize_tasks' => $this->summarize($arguments, $user),
            default => ['ok' => false, 'error' => 'unknown_tool', 'result_count' => 0],
        };
    }

    private function search(array $arguments, User $user): array
    {
        $limit = max(1, min(
            (int) ($arguments['limit'] ?? 10),
            (int) config('ai_assistant.max_search_results', 20)
        ));

        $items = $this->baseQuery($arguments, $user)
            ->with(['requester:id,name', 'assignee:id,name'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $results = $items->map(function (Task $item) {
            return [
                'id' => (int) $item->id,
                'title' => (string) $item->title,
                'description' => Str::limit(trim(strip_tags((string) $item->description)), 180),
                'status' => (string) $item->status,
                'status_label' => self::STATUS_LABELS[$item->status] ?? (string) $item->status,
                'priority' => (string) $item->priority,
                'priority_label' => self::PRIORITY_LABELS[$item->priority] ?? (string) $item->priority,
                'requester_name' => (string) optional($item->requester)->name,
                'assignee_name' => (string) optional($item->assignee)->name,
                'due_at' => optional($item->due_at)->format('Y-m-d H:i'),
                'is_overdue' => $item->due_at
                    ? Carbon::parse($item->due_at)->isPast() && $item->status !== 'approved'
                    : false,
                'created_at' => optional($item->created_at)->format('Y-m-d H:i'),
                'url' => route('tasks.show', $item->id),
            ];
        })->values()->all();

        return [
            'ok' => true,
            'result_count' => count($results),
            'results' => $results,
            'actions' => collect($results)->map(fn (array $item) => [
                'label' => 'Mở công việc #'.$item['id'],
                'url' => $item['url'],
            ])->all(),
        ];
    }

    private function summarize(array $arguments, User $user): array
    {
        $query = $this->baseQuery($arguments, $user);

        $statusRows = (clone $query)
            ->selectRaw('status, COUNT(*) as total_count')
            ->groupBy('status')
            ->get();

        $breakdown = $statusRows->map(fn ($row) => [
            'status' => (string) $row->status,
            'label' => self::STATUS_LABELS[$row->status] ?? (string) $row->status,
            'count' => (int) $row->total_count,
        ])->values()->all();

        $totalCount = array_sum(array_column($breakdown, 'count'));
        $openStatuses = ['new', 'in_progress', 'submitted', 'revision', 'rejected'];
        $openCount = (int) collect($breakdown)->whereIn('status', $openStatuses)->sum('count');
        $actionRequiredCount = (int) collect($breakdown)->whereIn('status', ['new', 'in_progress', 'revision', 'rejected'])->sum('count');
        $submittedRow = collect($breakdown)->firstWhere('status', 'submitted');
        $approvedRow = collect($breakdown)->firstWhere('status', 'approved');
        $submittedCount = (int) ($submittedRow['count'] ?? 0);
        $approvedCount = (int) ($approvedRow['count'] ?? 0);

        $overdueArguments = array_merge($arguments, [
            'overdue_only' => true,
            'status' => null,
            'status_group' => null,
        ]);
        $overdueCount = $this->baseQuery($overdueArguments, $user)->count();

        return [
            'ok' => true,
            'result_count' => $totalCount,
            'summary' => [
                'total_count' => $totalCount,
                'open_count' => $openCount,
                'action_required_count' => $actionRequiredCount,
                'submitted_count' => $submittedCount,
                'approved_count' => $approvedCount,
                'overdue_count' => $overdueCount,
                'status_breakdown' => $breakdown,
            ],
        ];
    }

    private function baseQuery(array $arguments, User $user): Builder
    {
        $query = Task::query();

        $requestedScope = (string) ($arguments['assignee_scope'] ?? 'me');
        $scope = ($requestedScope === 'all' && $this->canViewAll($user)) ? 'all' : 'me';

        if ($scope === 'me') {
            $query->where('assignee_id', $user->id);
        }

        $keyword = trim((string) ($arguments['keyword'] ?? ''));
        if ($keyword !== '') {
            $escaped = addcslashes($keyword, '%_\\');
            $query->where(function (Builder $sub) use ($escaped) {
                $like = '%'.$escaped.'%';
                $sub->where('title', 'like', $like);

                if (SchemaCache::hasColumn('tasks', 'description')) {
                    $sub->orWhere('description', 'like', $like);
                }
                if (SchemaCache::hasColumn('tasks', 'result_note')) {
                    $sub->orWhere('result_note', 'like', $like);
                }

                $sub->orWhereHas('requester', fn (Builder $person) => $person->where('name', 'like', $like))
                    ->orWhereHas('assignee', fn (Builder $person) => $person->where('name', 'like', $like));
            });
        }

        $status = trim((string) ($arguments['status'] ?? ''));
        if (array_key_exists($status, self::STATUS_LABELS)) {
            $query->where('status', $status);
        } else {
            $group = trim((string) ($arguments['status_group'] ?? ''));
            if ($group === 'action_required') {
                $query->whereIn('status', ['new', 'in_progress', 'revision', 'rejected']);
            } elseif ($group === 'unfinished') {
                $query->whereNotIn('status', ['approved']);
            }
        }

        $dateFrom = $this->validDate($arguments['date_from'] ?? null);
        $dateTo = $this->validDate($arguments['date_to'] ?? null);

        if ($dateFrom) {
            $query->whereDate('due_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('due_at', '<=', $dateTo);
        }

        if ((bool) ($arguments['overdue_only'] ?? false)) {
            $query->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->whereNotIn('status', ['approved']);
        }

        return $query;
    }

    private function canViewAll(User $user): bool
    {
        $roles = [
            'admin', 'manager', 'sales_manager', 'marketing_manager', 'accounting',
            'management', 'director', 'general_director', 'ban_giam_doc', 'giam_doc',
        ];

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        $role = mb_strtolower((string) ($user->role ?? ''));

        return in_array($role, $roles, true);
    }

    private function validDate(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
