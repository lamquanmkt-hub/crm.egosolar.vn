<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\DTOs\Technical\MaintenanceScheduleRow;
use App\Models\Projects\Site;
use App\Models\SolarMaintenanceAssignee;
use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\View\Presenters\Technical\MaintenanceDashboardPresenter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** {@see MaintenanceDashboardPresenter} thay 6 khối `@php` của technical/maintenance/index (2026-09-07). Model trong bộ nhớ, đồng hồ cố định. */
final class MaintenanceDashboardPresenterTest extends TestCase
{
    private MaintenanceDashboardPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-07 09:00:00');
        $this->presenter = new MaintenanceDashboardPresenter;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dong_lich_nhan_thoi_gian_truong_nhom_va_chu_tat(): void
    {
        $leader = (new User)->forceFill(['name' => 'Trần Kỹ Thuật']);
        $rows = [
            $this->row('2026-09-01', 'scheduled', leaderUser: $leader),
            $this->row('2026-09-07', 'assigned', assignedName: 'Kỹ A ngoài'),
            $this->row('2026-09-09', 'in_progress'),
            $this->row('2026-09-25', 'pending_approval', round: 2, total: 4),
            $this->row('2026-08-20', 'completed'),
            $this->row(null, 'scheduled'),
        ];

        $this->assertSame(['Quá hạn 6 ngày', 'overdue', true, 'Trần Kỹ Thuật', 'T', false], $this->summarize($rows[0]));
        $this->assertSame(['Hôm nay', 'today', false, 'Kỹ A ngoài', 'K', false], $this->summarize($rows[1]), 'không có assignee → tên đã gán');
        $this->assertSame(['Còn 2 ngày', 'soon', false, 'Chưa phân công', '!', true], $this->summarize($rows[2]));
        $this->assertSame(['Còn 18 ngày', 'safe', false, 'Chưa phân công', '!', true], $this->summarize($rows[3]));
        $this->assertSame([2, 4], [$rows[3]->roundNo, $rows[3]->totalRounds]);
        $this->assertSame(['Đã hoàn thành', 'done', false, 'Chưa phân công', '!', true], $this->summarize($rows[4]));
        $this->assertSame(['Chưa xác định', 'muted', false, 'Chưa phân công', '!', true], $this->summarize($rows[5]), 'không có ngày');
        $this->assertSame([1, 1], [$rows[5]->roundNo, $rows[5]->totalRounds], 'round_no/total_rounds trống → 1');
        $this->assertSame(10.5, $rows[0]->capacity, 'công suất lấy từ site, thiếu thì từ lịch');
    }

    public function test_vong_tron_kpi_trang_thai_thu_cong_va_cau_hinh_js(): void
    {
        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag(['name' => ['Thiếu tên'], 'date' => ['Thiếu tên', 'Sai ngày']]));
        $errors->put('warrantyStock', new MessageBag(['qty' => ['Thiếu SL']]));

        $data = $this->presenter->viewData(
            ['total' => 8, 'scheduled_bucket' => 2, 'processing_bucket' => 3, 'approval_bucket' => 1, 'completed' => 2, 'pending_approval' => 1, 'completed_this_month' => 2, 'today' => 1, 'upcoming' => 2, 'overdue' => 1, 'unassigned' => 2],
            ['pending_approval' => 2, 'completed_this_month' => 1, 'total' => 5, 'open' => 3, 'waiting_stock' => 1, 'completed' => 2],
            ['draft' => 'Nháp', 'scheduled' => 'Đã lên lịch', 'pending_approval' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'completed' => 'Xong'],
            ['create' => true, 'claim_create' => false, 'stock_manage' => 1, 'approve' => 0, 'manager' => true],
            [],
            new LengthAwarePaginator([$this->schedule('2026-09-09', 'scheduled')], 1, 30),
            [['user' => (new User)->forceFill(['name' => ' lê văn b']), 'active' => 2]],
            [
                ['round' => 1, 'total' => 3, 'start_date' => Carbon::parse('2026-09-01'), 'end_date' => Carbon::parse('2026-09-30'), 'days_to_start' => -6, 'progress' => 50],
                ['round' => 2, 'total' => 0, 'start_date' => null, 'end_date' => null, 'days_to_start' => null, 'progress' => 0],
                ['round' => 5, 'total' => 2, 'start_date' => null, 'end_date' => null, 'days_to_start' => 3, 'progress' => 0],
                ['round' => 6, 'total' => 2, 'start_date' => null, 'end_date' => null, 'days_to_start' => 0, 'progress' => 0],
            ],
            $errors,
        );

        $this->assertSame([25.0, 38.0, 13.0, 25.0], [$data['scheduledPct'], $data['processingPct'], $data['approvalPct'], $data['completedPct']]);
        $this->assertSame([90.0, 226.8, 273.6, 360.0], [$data['scheduledDeg'], $data['processingEndDeg'], $data['approvalEndDeg'], $data['completedEndDeg']]);
        $this->assertSame([3, 3], [$data['pendingApprovalTotal'], $data['completedThisMonth']]);
        $this->assertSame(['draft' => 'Nháp', 'scheduled' => 'Đã lên lịch'], $data['manualStatuses']);
        $this->assertSame(['view' => 'maintenance', 'month' => '', 'overdue' => 1], $data['maintenanceKpis'][3]['params']);
        $this->assertSame(['view' => 'maintenance', 'month' => '', 'status' => 'unassigned'], $data['maintenanceKpis'][4]['params']);
        $this->assertSame(['view' => 'maintenance', 'month' => ''], $data['maintenanceKpis'][0]['params']);
        $this->assertSame(['Tổng phiếu', 5, 'blue'], [$data['claimKpis'][0]['label'], $data['claimKpis'][0]['value'], $data['claimKpis'][0]['tone']]);

        $this->assertContainsOnlyInstancesOf(MaintenanceScheduleRow::class, $data['scheduleRows']);
        $this->assertCount(1, $data['scheduleRows'], 'paginator → lấy items(), không phải mảng meta');
        $this->assertSame('L', $data['technicianWorkload'][0]['initial']);
        $this->assertSame(['green', '01/09 - 30/09/2026', 'Đã bắt đầu 6 ngày'], [$data['maintenanceRounds'][0]['tone'], $data['maintenanceRounds'][0]['dateLabel'], $data['maintenanceRounds'][0]['timeLabel']]);
        $this->assertSame(['amber', 'Chưa thiết lập thời gian', 'Chưa có kế hoạch'], [$data['maintenanceRounds'][1]['tone'], $data['maintenanceRounds'][1]['dateLabel'], $data['maintenanceRounds'][1]['timeLabel']]);
        $this->assertSame(['green', 'Còn 3 ngày'], [$data['maintenanceRounds'][2]['tone'], $data['maintenanceRounds'][2]['timeLabel']], 'đợt 5 quay lại màu đầu');
        $this->assertSame('Bắt đầu hôm nay', $data['maintenanceRounds'][3]['timeLabel']);

        $this->assertSame(['Thiếu tên', 'Sai ngày', 'Thiếu SL'], $data['allFormErrors']->all(), 'gộp mọi bag, bỏ trùng');
        $this->assertSame([true, false, true, true, false, true, false, true], array_values(array_slice($data['tmConfigForJs'], 0, 8)));
        $this->assertSame(['pending' => ['approved', 'completed', 'cancelled'], 'approved' => ['completed', 'cancelled'], 'completed' => [], 'cancelled' => ['pending']], $data['tmConfigForJs']['stockTransitions']);
        $this->assertSame('Lịch bảo trì', $data['tabs']['maintenance']['label']);
        $this->assertSame('info', $data['statusTone']['scheduled']);
    }

    /** @return array{0: string, 1: string, 2: bool, 3: ?string, 4: string, 5: bool} */
    private function summarize(MaintenanceScheduleRow $row): array
    {
        return [$row->timeLabel, $row->timeTone, $row->overdue, $row->leaderName, $row->avatarInitial, $row->isUnassigned];
    }

    private function row(?string $date, string $status, ?User $leaderUser = null, ?string $assignedName = null, ?int $round = null, ?int $total = null): MaintenanceScheduleRow
    {
        return $this->presenter->row($this->schedule($date, $status, $leaderUser, $assignedName, $round, $total));
    }

    private function schedule(?string $date, string $status, ?User $leaderUser = null, ?string $assignedName = null, ?int $round = null, ?int $total = null): SolarMaintenanceSchedule
    {
        $schedule = (new SolarMaintenanceSchedule)->forceFill(['id' => 1, 'status' => $status, 'scheduled_date' => $date, 'round_no' => $round, 'total_rounds' => $total, 'assigned_name' => $assignedName, 'system_kwp' => 8]);
        $schedule->setRelation('site', (new Site)->forceFill(['system_kwp' => 10.5]));
        $assignees = [];
        if ($leaderUser) {
            $assignee = (new SolarMaintenanceAssignee)->forceFill(['is_leader' => 1, 'role' => 'leader']);
            $assignee->setRelation('user', $leaderUser);
            $assignees[] = $assignee;
        }
        $schedule->setRelation('assignees', new EloquentCollection($assignees));

        return $schedule;
    }
}
