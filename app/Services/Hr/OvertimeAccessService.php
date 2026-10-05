<?php

namespace App\Services\Hr;

use App\Models\OvertimeRequest;
use App\Models\User;
use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quy tắc xem / duyệt đơn tăng ca: HR-Admin-Kế toán, người được chọn duyệt,
 * quản lý trực tiếp hoặc quản lý cùng phòng ban với nhân viên.
 */
class OvertimeAccessService
{
    private const HR_ROLES = ['admin', 'accounting', 'hr'];

    public function __construct(
        private readonly LeaveApprovalAccessService $leaveAccess
    ) {}

    public function canManageHr(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::HR_ROLES)) {
            return true;
        }

        return in_array(strtolower((string) ($user->role ?? '')), self::HR_ROLES, true);
    }

    /**
     * Đơn của mình, được chọn làm người duyệt, hoặc của nhóm mình quản lý.
     */
    public function scopeVisible(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
        $this->orWhereReviewable($query, $user);
    }

    /**
     * Đơn của người khác mà user có thể duyệt.
     */
    public function scopeReviewable(Builder $query, User $user): Builder
    {
        if ($this->canManageHr($user)) {
            return $query->where('user_id', '!=', $user->id);
        }

        return $query->where('user_id', '!=', $user->id)
            ->where(fn (Builder $scope) => $this->orWhereReviewable($scope, $user, true));
    }

    public function canApprove(?User $user, OvertimeRequest $overtime): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->canManageHr($user) || (int) $overtime->approver_id === (int) $user->id) {
            return true;
        }

        $overtime->loadMissing('user');
        $employee = $overtime->user;

        if (! $employee || (int) $employee->id === (int) $user->id) {
            return false;
        }

        if (
            SchemaCache::hasColumn('users', 'manager_id')
            && (int) ($employee->manager_id ?? 0) === (int) $user->id
        ) {
            return true;
        }

        return $this->leaveAccess->isDepartmentManager($user)
            && (int) ($user->department_id ?? 0) > 0
            && (int) ($employee->department_id ?? 0) === (int) $user->department_id;
    }

    private function orWhereReviewable(Builder $query, User $user, bool $first = false): void
    {
        $method = $first ? 'where' : 'orWhere';
        $query->{$method}('approver_id', $user->id);

        if (SchemaCache::hasColumn('users', 'manager_id')) {
            $query->orWhereHas('user', fn (Builder $e) => $e->where('manager_id', $user->id));
        }

        if ((int) ($user->department_id ?? 0) > 0 && $this->leaveAccess->isDepartmentManager($user)) {
            $query->orWhereHas('user', fn (Builder $e) => $e->where('department_id', $user->department_id));
        }
    }
}
