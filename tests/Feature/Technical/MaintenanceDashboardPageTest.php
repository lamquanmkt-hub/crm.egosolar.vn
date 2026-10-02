<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\DTOs\Technical\MaintenanceScheduleRow;
use App\Enums\Role;
use App\Models\SolarMaintenanceSchedule;
use App\View\Presenters\Technical\MaintenanceDashboardPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** Dashboard O&M sau khi dời 6 khối `@php` sang MaintenanceDashboardPresenter (2026-09-07). */
final class MaintenanceDashboardPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/technical/maintenance/index.blade.php';

    private const URL = '/du-an/bao-tri-bao-hanh';

    /** Biến vòng lặp, Blade và những thứ controller truyền thẳng (không qua presenter). */
    private const VIEW_VARIABLES_NOT_FROM_PRESENTER = [
        'row', 'workload', 'round', 'kpi', 'claimKpi', 'key', 'tab', 'i', 'step', 'activitySchedule', 'claim', 'movement', 'site', 'user', 'v', 'l', 'warehouse', 'error', 'errors', 'loop', 'slot', 'attributes', 'component',
        'summary', 'warrantySummary', 'permissions', 'activeView', 'filters', 'schedules', 'overviewSchedules', 'claims', 'stockMovements', 'openClaims', 'warehouses', 'documentSites',
        'sites', 'users', 'types', 'statuses', 'priorities', 'claimTypes', 'claimStatuses', 'claimPriorities', 'stockTypes', 'stockStatuses',
    ];

    public function test_trang_in_gia_tri_da_tinh(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $tech = $this->userWithRole('technical', ['name' => 'Trần Kỹ Thuật']);
        $now = '2026-09-01 08:00:00';
        DB::table('companies')->insert(['id' => 998501, 'code' => 'EGOT998', 'name' => 'EGO Test', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sites')->insert(['id' => 998601, 'name' => 'Công trình canh test', 'company_id' => 998501, 'contract_amount' => 0, 'system_kwp' => 10.5, 'labor_cost' => 0, 'transport_cost' => 0, 'other_cost' => 0, 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now]);
        $type = array_key_first(SolarMaintenanceSchedule::TYPES);
        DB::table('solar_maintenance_schedules')->insert([
            ['id' => 998701, 'schedule_code' => 'BT-1', 'site_id' => 998601, 'company_id' => 998501, 'type' => $type, 'status' => 'scheduled', 'priority' => 'high', 'round_no' => 1, 'total_rounds' => 3, 'scheduled_date' => now()->subDays(6)->toDateString(), 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998702, 'schedule_code' => 'BT-2', 'site_id' => 998601, 'company_id' => 998501, 'type' => $type, 'status' => 'in_progress', 'priority' => 'urgent', 'round_no' => 2, 'total_rounds' => 2, 'scheduled_date' => now()->addDays(2)->toDateString(), 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('solar_maintenance_assignees')->insert(['maintenance_schedule_id' => 998701, 'user_id' => $tech->id, 'role' => 'leader', 'is_leader' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $html = $this->actingAs($admin)->get(self::URL)->assertOk()->getContent();
        $this->assertStringContainsString('Quá hạn 6 ngày', $html);
        $this->assertStringContainsString('Còn 2 ngày', $html);
        $this->assertStringContainsString('<span class="tm4-avatar">T</span>', $html);
        $this->assertStringContainsString('<span class="tm4-avatar">!</span>', $html, 'lịch chưa phân công');
        $this->assertStringContainsString('<small>2/2 chu kỳ</small>', $html);
        $this->assertStringContainsString('class="tm4-badge danger">', $html, 'ưu tiên urgent → danger');
        $this->assertMatchesRegularExpression('/--scheduled-deg:\d+(\.\d+)?deg/', $html);

        $this->actingAs($admin)->from(self::URL.'?view=maintenance')->post(self::URL, [])->assertRedirect();
        $html = $this->actingAs($admin)->get(self::URL.'?view=maintenance')->assertOk()->getContent();
        $this->assertStringContainsString('Chưa thể lưu dữ liệu', $html);
        $this->assertStringContainsString('"scheduleHasErrors":true', $html);
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $used = array_values(array_unique(array_diff($m[1], self::VIEW_VARIABLES_NOT_FROM_PRESENTER)));
        $provided = array_keys(app(MaintenanceDashboardPresenter::class)->viewData([], [], [], [], [], [], [], [], new ViewErrorBag));
        $this->assertSame([], array_values(array_diff($used, $provided)), 'biến view dùng mà presenter không trả');

        preg_match_all('/\$row->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(MaintenanceScheduleRow::class))->getProperties());
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), 'view đọc thuộc tính dòng lịch không có');
    }
}
