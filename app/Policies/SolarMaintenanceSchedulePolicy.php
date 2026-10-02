<?php

namespace App\Policies;

use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\Support\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;

/**
 * Policy phân quyền các thao tác trên lịch bảo trì điện mặt trời.
 */
class SolarMaintenanceSchedulePolicy
{
    /**
     * Cho phép xem danh sách lịch bảo trì điện mặt trời hay không.
     */
    public function viewAny(User $user): bool
    {
        return SolarMaintenanceAccess::canViewAny($user);
    }

    /**
     * Cho phép xem chi tiết lịch (kỹ thuật viên chỉ xem lịch được phân công).
     */
    public function view(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (! SolarMaintenanceAccess::canViewAny($user) || ! $this->sameCompany($user, $schedule)) {
            return false;
        }

        if (! SolarMaintenanceAccess::isTechnicianOnly($user)) {
            return true;
        }

        return $this->isAssigned($user, $schedule);
    }

    /**
     * Cho phép tạo mới lịch bảo trì điện mặt trời hay không.
     */
    public function create(User $user): bool
    {
        return SolarMaintenanceAccess::canCreate($user);
    }

    /**
     * Manager/người có quyền hoặc kỹ thuật viên được phân công được cập nhật lịch.
     */
    public function update(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (! $this->sameCompany($user, $schedule)) {
            return false;
        }

        // Ban quản lý được phép hiệu chỉnh dữ liệu của các đợt trước để hoàn thiện hồ sơ.
        // Kỹ thuật viên vẫn bị khóa khi đợt đã gửi duyệt, được duyệt hoặc hoàn thành.
        if (SolarMaintenanceAccess::isManager($user)
            || SolarMaintenanceAccess::hasPermission($user, 'maintenance.reopen')) {
            return true;
        }

        if (in_array($schedule->status, ['pending_approval', 'approved', 'completed'], true)) {
            return false;
        }

        if (SolarMaintenanceAccess::isTechnicianOnly($user)) {
            return $this->canPerformAssignedWork($user, $schedule);
        }

        return SolarMaintenanceAccess::hasPermission($user, 'maintenance.update')
            || (SolarMaintenanceAccess::isTechnician($user) && $this->isAssigned($user, $schedule));
    }

    /**
     * Cho phép đổi trạng thái lịch (cùng điều kiện với quyền cập nhật).
     */
    public function changeStatus(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    /**
     * Cho phép tải tệp đính kèm lên lịch bảo trì hay không.
     */
    public function uploadAttachment(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (! $this->sameCompany($user, $schedule)) {
            return false;
        }

        if (SolarMaintenanceAccess::isManager($user)) {
            return true;
        }

        if (! SolarMaintenanceAccess::isTechnicianOnly($user)
            && SolarMaintenanceAccess::hasPermission($user, 'maintenance.files.upload')) {
            return true;
        }

        // Khi đã gửi duyệt/được duyệt/hoàn thành, kỹ thuật viên không được bổ sung âm thầm.
        // Quản lý vẫn có thể hoàn thiện hoặc quản trị hồ sơ sau khi đóng công việc.
        if (in_array($schedule->status, ['pending_approval', 'approved', 'completed'], true)) {
            return false;
        }

        return SolarMaintenanceAccess::isTechnician($user)
            && $this->canPerformAssignedWork($user, $schedule);
    }

    /**
     * Cho phép gửi lịch bảo trì đi duyệt hay không.
     */
    public function submitForApproval(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (! $this->sameCompany($user, $schedule)) {
            return false;
        }

        return SolarMaintenanceAccess::isManager($user)
            || SolarMaintenanceAccess::hasPermission($user, 'maintenance.submit')
            || $this->isAssigned($user, $schedule);
    }

    /**
     * Người duyệt phải cùng công ty và không phải người được phân công.
     */
    public function approve(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->sameCompany($user, $schedule)
            && SolarMaintenanceAccess::canApprove($user)
            && ! $this->isAssigned($user, $schedule);
    }

    /**
     * Cho phép yêu cầu chỉnh sửa lại (cùng điều kiện với quyền duyệt).
     */
    public function requestRevision(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->approve($user, $schedule);
    }

    /**
     * Cho phép từ chối lịch (cùng điều kiện với quyền duyệt).
     */
    public function reject(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->approve($user, $schedule);
    }

    /**
     * Manager/người có quyền được mở lại lịch.
     */
    public function reopen(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->sameCompany($user, $schedule)
            && (SolarMaintenanceAccess::isManager($user)
                || SolarMaintenanceAccess::hasPermission($user, 'maintenance.reopen'));
    }

    /**
     * Chỉ admin cùng công ty được xoá lịch.
     */
    public function delete(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return SolarMaintenanceAccess::isAdmin($user) && $this->sameCompany($user, $schedule);
    }

    /**
     * Chỉ admin cùng công ty được khôi phục lịch.
     */
    public function restore(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return SolarMaintenanceAccess::isAdmin($user) && $this->sameCompany($user, $schedule);
    }

    /**
     * Kiểm tra user và lịch có cùng công ty hay không (admin luôn đạt).
     */
    private function canPerformAssignedWork(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (! $this->isAssigned($user, $schedule)) {
            return false;
        }

        $approval = $schedule->approvals()
            ->where('approval_level', 'assignment')->latest('id')->first();

        if (! $approval) {
            return true;
        }

        return $approval->status === 'approved'
            && $schedule->assignees()->where('user_id', $user->id)
                ->whereNotNull('accepted_at')->exists();
    }

    private function sameCompany(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (SolarMaintenanceAccess::isAdmin($user)) {
            return true;
        }

        $currentCompanyId = EgoCompanyScope::currentId();
        if ($currentCompanyId <= 0) {
            return true;
        }

        $resolvedCompanyId = $this->resolvedCompanyId($schedule);

        // Dữ liệu cũ chưa xác định công ty chỉ Trưởng phòng kỹ thuật / quản lý được xem để xử lý.
        if ($resolvedCompanyId <= 0) {
            return SolarMaintenanceAccess::isManager($user);
        }

        return $resolvedCompanyId === $currentCompanyId;
    }

    /**
     * Xác định company_id thực của lịch (ưu tiên lịch, sau đó công trình).
     */
    private function resolvedCompanyId(SolarMaintenanceSchedule $schedule): int
    {
        $scheduleCompanyId = (int) ($schedule->company_id ?? 0);
        if ($scheduleCompanyId > 0) {
            return $scheduleCompanyId;
        }

        if ($schedule->relationLoaded('site')) {
            return (int) ($schedule->site?->company_id ?? 0);
        }

        if (! $schedule->site_id) {
            return 0;
        }

        return (int) ($schedule->site()->withoutGlobalScopes()->value('company_id') ?? 0);
    }

    /**
     * Kiểm tra user có được phân công vào lịch hay không.
     */
    private function isAssigned(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if ((int) $schedule->assigned_to === (int) $user->id) {
            return true;
        }

        if ($schedule->relationLoaded('assignees')) {
            return $schedule->assignees->contains(
                fn ($item) => (int) $item->user_id === (int) $user->id
            );
        }

        return $schedule->assignees()->where('user_id', $user->id)->exists();
    }
}
