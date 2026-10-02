<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Enums\Role;
use App\Models\SolarMaintenanceSchedule;
use App\View\Presenters\Technical\MaintenanceSitePresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Hồ sơ bảo trì công trình sau khi dời 5 khối `@php` sang MaintenanceSitePresenter (2026-09-07). */
final class MaintenanceSitePageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/technical/maintenance/site-show.blade.php';

    private const VIEW_VARIABLES_NOT_FROM_PRESENTER = ['cycle', 'row', 'document', 'serial', 'event', 'category', 'label', 'errors', 'loop', 'slot', 'attributes', 'component',
        'site', 'schedules', 'cycles', 'documents', 'serials', 'activity', 'statuses', 'types', 'approvalStatuses', 'permissions'];

    public function test_trang_in_so_dem_va_co_dot(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $now = '2026-09-01 08:00:00';
        DB::table('companies')->insert(['id' => 998501, 'code' => 'EGOT998', 'name' => 'EGO Test', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sites')->insert(['id' => 998601, 'name' => 'Công trình canh test', 'company_id' => 998501, 'contract_amount' => 0, 'address' => '12 Nguyễn Huệ', 'labor_cost' => 0, 'transport_cost' => 0, 'other_cost' => 0, 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now]);
        $type = array_key_first(SolarMaintenanceSchedule::TYPES);
        foreach ([[998701, 1, '2026-03-20', 'completed'], [998702, 2, now()->subDays(6)->toDateString(), 'scheduled'], [998703, 3, now()->addMonths(3)->toDateString(), 'assigned']] as [$id, $round, $date, $status]) {
            DB::table('solar_maintenance_schedules')->insert(['id' => $id, 'schedule_code' => 'BT-'.$id, 'site_id' => 998601, 'company_id' => 998501, 'type' => $type, 'status' => $status, 'priority' => 'normal', 'round_group' => 'grp-1', 'round_no' => $round, 'total_rounds' => 3, 'scheduled_date' => $date, 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        $html = $this->actingAs($admin)->get('/du-an/bao-tri-bao-hanh/cong-trinh/998601')->assertOk()->getContent();

        $this->assertStringContainsString('tong: 3,', $html);
        $this->assertStringContainsString('soDot: { done: 1, open: 2, overdue: 1 }', $html);
        $this->assertStringContainsString('class="ms7-round done"', $html);
        $this->assertStringContainsString('class="ms7-round overdue"', $html);
        $this->assertStringContainsString('hienChuKy(true, true, true)', $html);
        $this->assertStringContainsString('google.com/maps/search/?api=1&amp;query=12%20Nguy', $html);
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $used = array_values(array_unique(array_diff($m[1], self::VIEW_VARIABLES_NOT_FROM_PRESENTER)));
        $provided = array_keys(app(MaintenanceSitePresenter::class)->viewData(null, collect(), collect()));
        $this->assertSame([], array_values(array_diff($used, $provided)), 'biến view dùng mà presenter không trả');
    }
}
