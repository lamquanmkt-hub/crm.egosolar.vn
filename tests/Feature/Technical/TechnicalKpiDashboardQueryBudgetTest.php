<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ngân sách truy vấn của `/ky-thuat/kpis` — chặn N+1 quay lại.
 *
 * Tới 2026-10-01, `dashboardForPayrolls()` gọi `metricsForUserMonth()` cho MỖI bảng lương. Đo trên
 * trang thật, số truy vấn khớp chính xác `32 + 3·U + U·S` ở cả 7 tổ hợp (U = bảng lương trong kỳ,
 * S = công trình mỗi kỹ sư): U=1,S=1 → 36 · U=2 → 40 · U=4 → 48 · U=8 → 64 · U=4,S=2 → 52 ·
 * U=4,S=4 → 60 · U=8,S=4 → 88. Controller lấy `limit(200)` bảng lương nên trần xấu nhất rất cao.
 *
 * Guard đo TRANG THẬT, không đo service: service nhanh mà controller thôi gọi thì vô nghĩa.
 */
final class TechnicalKpiDashboardQueryBudgetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_so_truy_van_khong_tang_theo_so_bang_luong_va_cong_trinh(): void
    {
        Carbon::setTestNow('2026-10-15 09:00:00');
        $admin = $this->userWithRole('admin', ['id' => 795000, 'name' => 'QT ngân sách KPI']);

        // Làm nóng SchemaCache trước khi đếm.
        $this->gieo(795100, 1, 1);
        $this->actingAs($admin)->get('/ky-thuat/kpis?month=2026-10')->assertOk();

        $nho = $this->demTruyVan($admin);

        DB::table('technical_kpi_payrolls')->where('payroll_month', '2026-10')->delete();
        $this->gieo(796000, 8, 4);
        $to = $this->demTruyVan($admin);

        $this->assertSame($nho, $to, sprintf(
            "Số truy vấn TĂNG theo quy mô: 1 bảng lương × 1 công trình → %d, 8 × 4 → %d.\n".
            'Có N+1; dùng ProjectKpiLinkService::projectsForUsersMonth() thay vì gọi mỗi người.',
            $nho, $to
        ));

        $this->assertLessThanOrEqual(40, $to, "trang tốn $to truy vấn — vượt trần 40");
    }

    private function demTruyVan(object $admin): int
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });

        $this->actingAs($admin)->get('/ky-thuat/kpis?month=2026-10')->assertOk();

        DB::getEventDispatcher()->forget(\Illuminate\Database\Events\QueryExecuted::class);

        return $n;
    }

    /** U bảng lương trong kỳ, mỗi kỹ sư có S công trình với workflow trong kỳ. */
    private function gieo(int $base, int $soBangLuong, int $soCongTrinh): void
    {
        for ($i = 0; $i < $soBangLuong; $i++) {
            $uid = $base + 100 + $i;
            DB::table('users')->insert([
                'id' => $uid, 'name' => 'KS '.$uid, 'email' => "ks{$uid}@ns-kpi.test",
                'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('technical_kpi_payrolls')->insert([
                'user_id' => $uid, 'employee_name' => 'KS '.$uid, 'payroll_month' => '2026-10',
                'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
            ]);

            for ($j = 0; $j < $soCongTrinh; $j++) {
                $siteId = $base + 1000 + $i * 50 + $j;
                DB::table('sites')->insert([
                    'id' => $siteId, 'project_code' => 'CT-'.$siteId, 'name' => 'CT '.$siteId,
                    'progress_percent' => 50, 'target_completion_at' => '2026-10-28',
                    'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-10 08:00:00',
                ]);

                foreach ([['construction', 1], ['acceptance', 2]] as $k => [$code, $seq]) {
                    $stepId = $siteId * 10 + $k;
                    DB::table('project_workflow_steps')->insert([
                        'id' => $stepId, 'site_id' => $siteId, 'step_code' => $code, 'sequence' => $seq,
                        'status' => 'approved', 'due_at' => '2026-10-20 00:00:00',
                        'submitted_at' => '2026-10-12 00:00:00', 'approved_at' => '2026-10-13 00:00:00',
                        'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-13 08:00:00',
                    ]);
                    DB::table('project_workflow_assignments')->insert([
                        'workflow_step_id' => $stepId, 'user_id' => $uid,
                        'assignment_role' => 'lead', 'status' => 'done', 'is_active' => 1,
                        'submitted_at' => '2026-10-12 00:00:00',
                        'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-12 08:00:00',
                    ]);
                }
            }
        }
    }
}
