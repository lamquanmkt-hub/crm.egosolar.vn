<?php

declare(strict_types=1);

namespace Tests\Feature\TechnicalKpi;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Giữ hành vi dashboard từ server: kỹ sư chưa chấm chỉ là dòng UI, không phải bảng lương giả. */
final class TechnicalKpiDashboardTest extends TestCase
{
    use DatabaseTransactions;

    /** Placeholder không lọt vào thống kê, bộ lọc trạng thái hoặc liên kết hồ sơ chưa tồn tại. */
    public function test_ky_su_chua_cham_hien_thi_nhung_khong_tao_bang_luong(): void
    {
        $admin = $this->userWithRole(Role::Admin->value, ['id' => 994101]);
        $scored = $this->userWithRole(Role::Technical->value, ['id' => 994102, 'name' => 'Kỹ sư đã chấm', 'is_active' => 1]);
        $unscored = $this->userWithRole(Role::Technical->value, ['id' => 994103, 'name' => 'Kỹ sư chưa chấm', 'is_active' => 1]);
        $this->userWithRole(Role::Technical->value, ['id' => 994104, 'name' => 'Kỹ sư ngừng hoạt động', 'is_active' => 0]);
        DB::table('technical_kpi_payrolls')->insert([
            'id' => 995101, 'user_id' => $scored->id, 'employee_name' => $scored->name,
            'payroll_month' => '2099-01', 'status' => 'approved', 'total_kpi_percent' => 0.95,
        ]);
        $uri = '/ky-thuat/kpis?month=2099-01';
        $response = $this->actingAs($admin)->get($uri)->assertOk()
            ->assertSee('Kỹ sư chưa chấm')->assertSee('Chưa có hồ sơ KPI tháng này')
            ->assertDontSee('Kỹ sư ngừng hoạt động');
        $rows = $response->viewData('kpiRows');
        $this->assertCount(2, $rows);
        $this->assertNull($rows->firstWhere('user_id', $unscored->id)->id);
        $this->assertSame('not_scored', $rows->firstWhere('user_id', $unscored->id)->status);
        $this->assertSame(1, $response->viewData('summary')['total_records']);
        $this->assertEqualsWithDelta(0.95, $response->viewData('summary')['avg_kpi'], 0.0001);

        $filtered = $this->get($uri.'&user_id='.$unscored->id)->assertOk()
            ->assertSee('Chưa có hồ sơ KPI tháng này');
        $this->assertCount(1, $filtered->viewData('kpiRows'));
        $this->assertSame(0, $filtered->viewData('summary')['total_records']);
        $this->assertStringNotContainsString('class="tkpi-view"', $filtered->getContent());
        $approved = $this->get($uri.'&status=approved')->assertOk();
        $this->assertCount(1, $approved->viewData('kpiRows'));
        $this->assertSame('approved', $approved->viewData('kpiRows')->first()->status);
        $draft = $this->get($uri.'&status=draft')->assertOk()->assertDontSee('Chưa có hồ sơ KPI tháng này');
        $this->assertCount(0, $draft->viewData('kpiRows'));
        $this->assertSame(0, DB::table('technical_kpi_payrolls')->where('user_id', $unscored->id)->count());
    }
}
