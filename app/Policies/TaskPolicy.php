<?php

namespace App\Policies;

use App\Models\Tasks\Task;
use App\Models\User;

/**
 * Policy phân quyền module Công việc.
 */
class TaskPolicy
{
    private function hasAnyRole(User $user, array $roles): bool
    {
        if (method_exists($user, 'hasAnyRole')) {
            try {
                return $user->hasAnyRole($roles);
            } catch (\Throwable $e) {
            }
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                try {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                }
            }
        }

        $legacyRole = strtolower((string) ($user->role ?? ''));

        return in_array($legacyRole, $roles, true);
    }

    private function isGlobalManager(User $user): bool
    {
        try {
            if (method_exists($user, 'can') && $user->can('tasks.manage.all')) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        return $this->hasAnyRole($user, [
            'admin', 'super_admin', 'management', 'director', 'general_director',
            'ban_giam_doc', 'giam_doc',
        ]);
    }

    private function isDepartmentManager(User $user): bool
    {
        if ($this->isGlobalManager($user)) {
            return false;
        }

        try {
            if (method_exists($user, 'can') && $user->can('tasks.assign.department')) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        $roles = collect();
        if (method_exists($user, 'getRoleNames')) {
            try {
                $roles = $user->getRoleNames()->map(static fn ($role) => strtolower((string) $role));
            } catch (\Throwable $e) {
            }
        }
        if (! empty($user->role)) {
            $roles->push(strtolower((string) $user->role));
        }

        foreach ($roles->filter()->unique() as $role) {
            if (
                str_ends_with($role, '_manager') ||
                str_ends_with($role, '_leader') ||
                str_starts_with($role, 'truong_phong') ||
                str_contains($role, 'department_manager')
            ) {
                return true;
            }
        }

        try {
            $position = method_exists($user, 'position') ? $user->position()->first() : null;
            $text = strtolower(trim(implode(' ', array_filter([
                $position->name ?? null,
                $position->title ?? null,
                $position->display_name ?? null,
            ]))));

            return $text !== '' && preg_match('/trưởng\s*phòng|truong\s*phong|department\s*manager|head\s*of|\bmanager\b/u', $text) === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function canAssign(User $user): bool
    {
        return $this->isGlobalManager($user) || $this->isDepartmentManager($user);
    }

    private function managesTask(User $user, Task $task): bool
    {
        if ((int) $task->requester_id === (int) $user->id || $this->isGlobalManager($user)) {
            return true;
        }

        if (! $this->isDepartmentManager($user) || empty($user->department_id)) {
            return false;
        }

        $task->loadMissing('assignee');

        return (int) optional($task->assignee)->department_id === (int) $user->department_id;
    }

    public function viewAny(User $user): bool
    {
        return $this->canAssign($user);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->managesTask($user, $task)
            || (int) $task->assignee_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $this->canAssign($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->managesTask($user, $task)
            || (int) $task->assignee_id === (int) $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->managesTask($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->managesTask($user, $task);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $this->managesTask($user, $task);
    }
}
