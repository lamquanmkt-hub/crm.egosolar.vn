<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Projects\Site;
use App\Models\SolarMaintenanceSchedule;
use App\View\Presenters\Technical\MaintenanceSitePresenter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** {@see MaintenanceSitePresenter} thay 5 khối `@php` của technical/maintenance/site-show (2026-09-07). Model trong bộ nhớ, đồng hồ cố định. */
final class MaintenanceSitePresenterTest extends TestCase
{
    private MaintenanceSitePresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-07 09:00:00');
        $this->presenter = new MaintenanceSitePresenter;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_so_dem_tien_do_chu_ky_va_dot_ke_tiep(): void
    {
        $done = $this->schedule(1, '2026-03-20', 'completed');
        $overdue = $this->schedule(2, '2026-09-01', 'scheduled');
        $future = $this->schedule(3, '2026-12-20', 'assigned');
        $cancelled = $this->schedule(4, '2026-09-15', 'cancelled');
        $schedules = new EloquentCollection([$done, $overdue, $future, $cancelled]);
        $cycles = collect([
            ['key' => 'grp-1', 'title' => 'Chu kỳ 3 đợt', 'items' => new EloquentCollection([$done, $overdue, $future])],
            ['key' => 'single-4', 'title' => 'Lịch đơn lẻ', 'items' => new EloquentCollection([$cancelled])],
        ]);
        $site = (new Site)->forceFill(['address' => '12 Nguyễn Huệ, Q1', 'installed_at' => '2026-03-15']);

        $data = $this->presenter->viewData($site, $schedules, $cycles);

        $this->assertSame([1, 1, 25], [$data['completedCount'], $data['overdueCount'], $data['progressPercent']]);
        $this->assertSame(2, $data['nextSchedule']->id, 'đợt mở sớm nhất');
        $this->assertSame([2, 3], $data['activeSchedules']->pluck('id')->all());
        $this->assertSame('5 tháng/lần', $data['cycleLabel'], 'khoảng cách hai đợt đầu 20/03 → 01/09 = 5 tháng lẻ ngày, làm tròn xuống');
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=12%20Nguy%E1%BB%85n%20Hu%E1%BB%87%2C%20Q1', $data['mapUrl']);
        $this->assertSame('2026-03-15', Carbon::parse($data['acceptedDate'])->format('Y-m-d'), 'không có accepted_at → installed_at (model Site legacy không cast ngày, view parse)');
        $this->assertSame([4, 1, 3, 1], [$data['tongDot'], $data['soDotXong'], $data['soDotMo'], $data['soDotQuaHan']]);

        $cycle = $data['cycles'][0];
        $this->assertSame([true, true, true], [$cycle['hasDone'], $cycle['hasOpen'], $cycle['hasOverdue']]);
        $this->assertSame([[1, true, false, false], [2, false, true, true], [3, false, false, false]], array_map(fn (array $row) => [$row['item']->id, $row['done'], $row['overdue'], $row['active']], $cycle['rows']));
        $this->assertSame([false, true, false], [$data['cycles'][1]['hasDone'], $data['cycles'][1]['hasOpen'], $data['cycles'][1]['hasOverdue']]);
        $this->assertSame('info', $data['statusTone']['scheduled']);
        $this->assertSame('Biên bản nghiệm thu', $data['documentCategories']['acceptance']);
    }

    public function test_cong_trinh_trong_va_nhan_chu_ky(): void
    {
        $data = $this->presenter->viewData(new Site, new EloquentCollection, collect());
        $this->assertSame([0, 0, 0, 'Theo yêu cầu', null, null], [$data['completedCount'], $data['progressPercent'], $data['tongDot'], $data['cycleLabel'], $data['nextSchedule'], $data['mapUrl']]);

        $two = new EloquentCollection([$this->schedule(1, '2026-09-01', 'scheduled'), $this->schedule(2, '2026-09-20', 'scheduled')]);
        $this->assertSame('1 tháng/lần', $this->presenter->viewData(null, $two, collect())['cycleLabel'], 'dưới một tháng vẫn tối thiểu 1');

        $twoNoDate = new EloquentCollection([$this->schedule(1, null, 'draft'), $this->schedule(2, null, 'draft')]);
        $this->assertSame('Theo lịch công trình', $this->presenter->viewData(null, $twoNoDate, collect())['cycleLabel']);
    }

    private function schedule(int $id, ?string $date, string $status): SolarMaintenanceSchedule
    {
        $schedule = (new SolarMaintenanceSchedule)->forceFill(['id' => $id, 'status' => $status, 'scheduled_date' => $date, 'round_no' => 1, 'total_rounds' => 1]);
        $schedule->setRelation('assignees', new EloquentCollection);

        return $schedule;
    }
}
