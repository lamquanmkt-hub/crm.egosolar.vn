<?php

namespace Tests\Feature\Hr;

use App\Models\AttendanceCorrectionAttachment;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceCorrectionWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_employee_can_create_one_pending_request_for_own_attendance_record(): void
    {
        Storage::fake('local');
        $employee = $this->userWithRole('sales');
        $record = AttendanceRecord::create([
            'user_id' => $employee->id,
            'work_date' => '2026-09-01',
            'check_in_at' => '2026-09-01 09:15:00',
            'check_out_at' => '2026-09-01 17:00:00',
            'late_minutes' => 40,
            'early_leave_minutes' => 60,
            'work_minutes' => 465,
            'status' => 'early_leave',
        ]);

        $response = $this->actingAs($employee)->post(route('hr.attendance-corrections.store'), [
            'attendance_record_id' => $record->id,
            'requested_check_in_time' => '08:30',
            'requested_check_out_time' => '18:00',
            'reason' => 'Máy ghi nhận sai thời gian.',
            'attachments' => [UploadedFile::fake()->image('anh-camera.png', 800, 600)],
        ]);

        $response->assertRedirect(route('hr.attendance-corrections.index', ['tab' => 'mine']));
        $this->assertDatabaseHas('attendance_correction_requests', [
            'attendance_record_id' => $record->id,
            'user_id' => $employee->id,
            'status' => 'pending',
            'pending_guard' => 1,
        ]);
        $createdCorrection = AttendanceCorrectionRequest::query()
            ->where('attendance_record_id', $record->id)
            ->firstOrFail();
        $attachment = AttendanceCorrectionAttachment::query()
            ->where('attendance_correction_request_id', $createdCorrection->id)
            ->firstOrFail();
        $this->assertSame('anh-camera.png', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->actingAs($employee)
            ->get(route('hr.attendance-corrections.attachments.download', [
                $createdCorrection,
                $attachment,
            ]))
            ->assertOk();

        $duplicate = $this->actingAs($employee)->from(route('hr.attendance.my'))->post(route('hr.attendance-corrections.store'), [
            'attendance_record_id' => $record->id,
            'requested_check_in_time' => '08:35',
            'requested_check_out_time' => '18:00',
            'reason' => 'Gửi trùng.',
        ]);

        $duplicate->assertSessionHasErrors('attendance_record_id');
        $this->assertSame(1, AttendanceCorrectionRequest::where('attendance_record_id', $record->id)->count());
    }

    public function test_employee_cannot_create_or_approve_request_for_another_employee(): void
    {
        $owner = $this->userWithRole('sales');
        $other = $this->userWithRole('marketing');
        $record = AttendanceRecord::create([
            'user_id' => $owner->id,
            'work_date' => '2026-09-01',
            'check_in_at' => '2026-09-01 09:00:00',
            'status' => 'late',
        ]);

        $this->actingAs($other)->post(route('hr.attendance-corrections.store'), [
            'attendance_record_id' => $record->id,
            'requested_check_in_time' => '08:30',
            'reason' => 'Không phải bản công của mình.',
        ])->assertForbidden();

        $correction = AttendanceCorrectionRequest::create([
            'attendance_record_id' => $record->id,
            'user_id' => $owner->id,
            'work_date' => '2026-09-01',
            'original_check_in_at' => $record->check_in_at,
            'requested_check_in_at' => '2026-09-01 08:30:00',
            'reason' => 'Xin sửa giờ.',
            'status' => 'pending',
            'pending_guard' => 1,
        ]);
        $attachment = AttendanceCorrectionAttachment::create([
            'attendance_correction_request_id' => $correction->id,
            'uploaded_by' => $owner->id,
            'disk' => 'local',
            'original_name' => 'giai-trinh.pdf',
            'file_path' => 'private/attendance-corrections/test/giai-trinh.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
        ]);

        $this->actingAs($other)
            ->get(route('hr.attendance-corrections.attachments.download', [$correction, $attachment]))
            ->assertForbidden();

        $this->actingAs($other)->post(route('hr.attendance-corrections.approve', $correction), [
            'approved_check_in_time' => '08:30',
        ])->assertForbidden();
    }

    public function test_hr_approval_recalculates_attendance_and_keeps_before_after_audit_snapshots(): void
    {
        $employee = $this->userWithRole('sales');
        $hr = $this->userWithRole('hr');
        $setting = AttendanceSetting::query()->firstOrNew();
        $setting->fill([
            'work_start_time' => '08:30:00',
            'work_end_time' => '18:00:00',
            'late_grace_minutes' => 0,
            'min_work_minutes' => 480,
            'require_gps' => false,
        ])->save();

        $record = AttendanceRecord::create([
            'user_id' => $employee->id,
            'work_date' => '2026-09-01',
            'check_in_at' => '2026-09-01 09:15:00',
            'check_out_at' => '2026-09-01 17:00:00',
            'late_minutes' => 45,
            'early_leave_minutes' => 60,
            'work_minutes' => 465,
            'status' => 'early_leave',
        ]);
        $correction = AttendanceCorrectionRequest::create([
            'attendance_record_id' => $record->id,
            'user_id' => $employee->id,
            'work_date' => '2026-09-01',
            'original_check_in_at' => $record->check_in_at,
            'original_check_out_at' => $record->check_out_at,
            'requested_check_in_at' => '2026-09-01 08:30:00',
            'requested_check_out_at' => '2026-09-01 18:00:00',
            'reason' => 'Thiết bị ghi nhận sai.',
            'status' => 'pending',
            'pending_guard' => 1,
        ]);

        $this->actingAs($hr)->post(route('hr.attendance-corrections.approve', $correction), [
            'approved_check_in_time' => '08:30',
            'approved_check_out_time' => '18:00',
            'review_note' => 'Đã đối chiếu camera.',
        ])->assertSessionHas('success');

        $record->refresh();
        $correction->refresh();
        $this->assertSame('08:30', $record->check_in_at->format('H:i'));
        $this->assertSame('18:00', $record->check_out_at->format('H:i'));
        $this->assertSame(0, (int) $record->late_minutes);
        $this->assertSame(0, (int) $record->early_leave_minutes);
        $this->assertSame(570, (int) $record->work_minutes);
        $this->assertSame('completed', $record->status);
        $this->assertSame('approved', $correction->status);
        $this->assertNull($correction->pending_guard);
        $this->assertSame($hr->id, $correction->reviewed_by);
        $this->assertSame('09:15', substr((string) $correction->before_apply_snapshot['check_in_at'], 11, 5));
        $this->assertSame('08:30', substr((string) $correction->applied_snapshot['check_in_at'], 11, 5));
    }

    public function test_rejection_keeps_attendance_unchanged_and_employee_can_cancel_pending_request(): void
    {
        $employee = $this->userWithRole('sales');
        $hr = $this->userWithRole('hr');
        $record = AttendanceRecord::create([
            'user_id' => $employee->id,
            'work_date' => '2026-09-01',
            'check_in_at' => '2026-09-01 09:00:00',
            'late_minutes' => 30,
            'status' => 'late',
        ]);
        $correction = AttendanceCorrectionRequest::create([
            'attendance_record_id' => $record->id,
            'user_id' => $employee->id,
            'work_date' => '2026-09-01',
            'original_check_in_at' => $record->check_in_at,
            'requested_check_in_at' => '2026-09-01 08:30:00',
            'reason' => 'Xin sửa.',
            'status' => 'pending',
            'pending_guard' => 1,
        ]);

        $this->actingAs($hr)->post(route('hr.attendance-corrections.reject', $correction), [
            'review_note' => 'Chưa đủ căn cứ.',
        ])->assertSessionHas('success');

        $this->assertSame('09:00', $record->fresh()->check_in_at->format('H:i'));
        $this->assertSame('rejected', $correction->fresh()->status);

        $second = AttendanceCorrectionRequest::create([
            'attendance_record_id' => $record->id,
            'user_id' => $employee->id,
            'work_date' => '2026-09-01',
            'original_check_in_at' => $record->check_in_at,
            'requested_check_in_at' => '2026-09-01 08:45:00',
            'reason' => 'Gửi lại.',
            'status' => 'pending',
            'pending_guard' => 1,
        ]);

        $this->actingAs($employee)->post(route('hr.attendance-corrections.cancel', $second))
            ->assertSessionHas('success');
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertNull($second->fresh()->pending_guard);
    }
}
