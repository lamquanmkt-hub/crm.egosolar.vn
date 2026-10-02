<?php

namespace App\Services\Hr;

use App\Models\User;

class AttendanceCorrectionAccessService
{
    /**
     * Chỉ HR, admin hoặc người được cấp quyền riêng mới xử lý sửa công.
     */
    public function canReview(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'hr'])) {
            return true;
        }

        if (method_exists($user, 'hasPermissionTo')) {
            try {
                return $user->hasPermissionTo('hr.attendance_correction.manage');
            } catch (\Throwable $e) {
                // Permission có thể chưa được đồng bộ trong lần deploy đầu tiên.
            }
        }

        return false;
    }
}
