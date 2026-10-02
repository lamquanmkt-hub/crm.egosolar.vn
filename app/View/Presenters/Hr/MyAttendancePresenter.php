<?php

declare(strict_types=1);

namespace App\View\Presenters\Hr;

use App\DTOs\Hr\AttendanceRecordRow;

/**
 * Chuẩn bị trang chấm công của chính nhân viên (`hr/attendance/my`).
 *
 * Thay hai khối `@php`: khối đầu duyệt `$records` tới SÁU lần để đếm các con số tổng hợp và khai
 * một closure đổi trạng thái thành lớp CSS; khối sau nằm trong `@forelse`, chạy lại cho từng dòng.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 */
final class MyAttendancePresenter
{
    /** Trạng thái chấm công → hậu tố lớp CSS, giữ đúng `match()` cũ của view. */
    private const STATUS_CLASSES = [
        'completed' => 'success',
        'checked_in' => 'primary',
        'late' => 'warning',
        'early_leave' => 'warning',
        'incomplete' => 'danger',
    ];

    private const STATUS_CLASS_DEFAULT = 'secondary';

    /**
     * @param  iterable<object>  $records  các dòng `attendance_records` của tháng đang xem,
     *                                     đã eager load `correctionRequests`
     * @return array{validDays: int, lateDays: int, completedDays: int, totalHours: float, onTimeDays: int, onTimeRate: float, recordRows: list<AttendanceRecordRow>}
     */
    public function viewData(iterable $records): array
    {
        $validDays = 0;
        $lateDays = 0;
        $completedDays = 0;
        $onTimeDays = 0;
        $totalMinutes = 0;
        $rows = [];

        // Một lượt duyệt thay cho sáu lượt của bản cũ.
        foreach ($records as $record) {
            $hasCheckIn = filled($record->check_in_at ?? null);
            $lateMinutes = (int) ($record->late_minutes ?? 0);

            if ($hasCheckIn) {
                $validDays++;
                if ($lateMinutes === 0) {
                    $onTimeDays++;
                }
            }
            if ($lateMinutes > 0) {
                $lateDays++;
            }
            if (($record->status ?? null) === 'completed') {
                $completedDays++;
            }
            $totalMinutes += (int) ($record->work_minutes ?? 0);

            $rows[] = new AttendanceRecordRow(
                record: $record,
                statusClass: self::STATUS_CLASSES[(string) ($record->status ?? '')] ?? self::STATUS_CLASS_DEFAULT,
                pendingCorrection: $record->correctionRequests?->firstWhere('status', 'pending'),
                // `work_date` đã cast thành Carbon ở model.
                isActiveToday: (bool) ($record->work_date?->isToday() && blank($record->check_out_at ?? null)),
            );
        }

        return [
            'validDays' => $validDays,
            'lateDays' => $lateDays,
            'completedDays' => $completedDays,
            'totalHours' => round($totalMinutes / 60, 1),
            'onTimeDays' => $onTimeDays,
            // Không có ngày công hợp lệ thì để 0 thay vì chia cho 0.
            'onTimeRate' => $validDays > 0 ? round(($onTimeDays / $validDays) * 100) : 0,
            'recordRows' => $rows,
        ];
    }
}
