<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Services\TechnicalKpi\ProjectKpiLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `dashboardForPayrolls()` bản nạp hàng loạt phải trả về ĐÚNG thứ vòng lặp cũ trả về.
 *
 * Vòng lặp cũ (tới 2026-10-01):
 *     foreach ($payrolls as $row) { $map[$uid] = $this->metricsForUserMonth($uid, $month); }
 * Bản mới nạp công trình + minh chứng của cả kỳ một lượt rồi tính trong PHP. Test so **từng
 * khoá, từng công trình, từng chỉ số** — kể cả THỨ TỰ danh sách công trình, vì `arsort()` của
 * PHP 8 là sắp ổn định nên thứ tự trước khi sắp quyết định các phần tử bằng điểm.
 */
final class ProjectKpiDashboardEquivalenceTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-10';

    /** Bốn kỹ sư: nhiều công trình · chỉ nhánh dữ liệu cũ · có minh chứng · không có gì trong kỳ. */
    private const KS = [
        'nhieuCT' => 790201,
        'chiLead' => 790202,
        'coMinhChung' => 790203,
        'trong' => 790204,
    ];

    public function test_ban_hang_loat_tra_ve_y_nhu_vong_lap_cu(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->gieo();

        $payrolls = collect(array_map(static fn (int $uid) => (object) ['user_id' => $uid], array_values(self::KS)));

        $moi = app(ProjectKpiLinkService::class)->dashboardForPayrolls($payrolls, self::MONTH);

        $cu = [];
        foreach (array_values(self::KS) as $uid) {
            // Service MỚI mỗi lượt: memo không được bắc cầu giữa hai bên của phép so.
            $cu[$uid] = app(ProjectKpiLinkService::class)->metricsForUserMonth($uid, self::MONTH);
        }

        $this->assertSame(array_keys($cu), array_keys($moi), 'thiếu/thừa khoá kỹ sư');

        foreach (array_values(self::KS) as $uid) {
            $this->assertSame(array_keys($cu[$uid]), array_keys($moi[$uid]), "KS $uid: bộ khoá khác");

            foreach ($cu[$uid] as $khoa => $giaTri) {
                if ($giaTri instanceof Collection) {
                    $this->assertInstanceOf(Collection::class, $moi[$uid][$khoa]);
                    // So cả THỨ TỰ: `->all()` giữ nguyên trình tự sau `sortByDesc`.
                    $this->assertSame($giaTri->all(), $moi[$uid][$khoa]->all(), "KS $uid / $khoa: danh sách khác");

                    continue;
                }

                $this->assertSame($giaTri, $moi[$uid][$khoa], "KS $uid / $khoa: giá trị khác");
            }
        }
    }

    /** Dữ liệu gieo phải thật sự phủ các nhánh, không thì phép so trên là so mảng rỗng. */
    public function test_du_lieu_gieo_phu_du_bon_nhanh(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->gieo();

        $payrolls = collect(array_map(static fn (int $uid) => (object) ['user_id' => $uid], array_values(self::KS)));
        $m = app(ProjectKpiLinkService::class)->dashboardForPayrolls($payrolls, self::MONTH);

        $this->assertSame(3, $m[self::KS['nhieuCT']]['project_count'], 'kỹ sư nhiều công trình');
        $this->assertSame(1, $m[self::KS['chiLead']]['project_count'], 'nhánh dữ liệu cũ');
        $this->assertSame('lead', $m[self::KS['chiLead']]['projects']->first()['role']);
        $this->assertSame(0, $m[self::KS['trong']]['project_count'], 'kỹ sư không có gì trong kỳ');

        $ks = $m[self::KS['coMinhChung']];
        $this->assertTrue($ks['project_quality']['available'], 'phải có chỉ số chất lượng từ minh chứng');
        $this->assertTrue($ks['project_hse']['available']);
        $this->assertSame(10.0, $ks['project_penalty_points'], 'điểm phạt cộng dồn từ minh chứng');
        $this->assertGreaterThan(0, $ks['issue_count']);
        $this->assertTrue($m[self::KS['nhieuCT']]['project_timeline']['available'], 'phải có chỉ số tiến độ');
    }

    public function test_so_truy_van_khong_tang_theo_so_ky_su(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->gieo();

        $mot = collect([(object) ['user_id' => self::KS['nhieuCT']]]);
        $bon = collect(array_map(static fn (int $uid) => (object) ['user_id' => $uid], array_values(self::KS)));

        // Làm nóng SchemaCache.
        app(ProjectKpiLinkService::class)->dashboardForPayrolls($mot, self::MONTH);

        $dem = function (Collection $payrolls): int {
            $svc = app(ProjectKpiLinkService::class);
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $svc->dashboardForPayrolls($payrolls, self::MONTH);
            DB::getEventDispatcher()->forget(\Illuminate\Database\Events\QueryExecuted::class);

            return $n;
        };

        $this->assertSame($dem($mot), $dem($bon), 'số truy vấn phải KHÔNG đổi theo số kỹ sư');
        $this->assertLessThanOrEqual(4, $dem($bon), 'minh chứng + workflow + công trình = 3');
    }

    private function gieo(): void
    {
        foreach (self::KS as $uid) {
            DB::table('users')->insert([
                'id' => $uid, 'name' => 'KS '.$uid, 'email' => "ks{$uid}@db.test",
                'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // 3 công trình có workflow cho kỹ sư `nhieuCT`; công trình đầu cũng có `coMinhChung`.
        foreach ([790301, 790302, 790303] as $i => $siteId) {
            DB::table('sites')->insert([
                'id' => $siteId, 'project_code' => 'CT-DB-'.$siteId, 'name' => 'CT '.$siteId,
                'progress_percent' => 70, 'target_completion_at' => '2026-10-'.(20 + $i),
                'completed_at' => '2026-10-1'.(5 + $i),
                'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-1'.(5 + $i).' 08:00:00',
            ]);

            foreach ([['construction', 1], ['acceptance', 2]] as $k => [$code, $seq]) {
                $stepId = $siteId * 10 + $k;
                DB::table('project_workflow_steps')->insert([
                    'id' => $stepId, 'site_id' => $siteId, 'step_code' => $code, 'sequence' => $seq,
                    'status' => 'approved', 'due_at' => '2026-10-'.(18 + $i).' 00:00:00',
                    'submitted_at' => '2026-10-1'.(4 + $i).' 00:00:00',
                    'approved_at' => '2026-10-1'.(5 + $i).' 00:00:00',
                    'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-1'.(5 + $i).' 08:00:00',
                ]);

                $gan = $i === 0 ? [self::KS['nhieuCT'], self::KS['coMinhChung']] : [self::KS['nhieuCT']];
                foreach ($gan as $n => $uid) {
                    DB::table('project_workflow_assignments')->insert([
                        'workflow_step_id' => $stepId, 'user_id' => $uid,
                        'assignment_role' => $n === 0 ? 'lead' : 'collaborator', 'status' => 'done',
                        'is_active' => 1, 'submitted_at' => '2026-10-1'.(4 + $i).' 00:00:00',
                        'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-1'.(5 + $i).' 08:00:00',
                    ]);
                }
            }
        }

        // Công trình chỉ có lead_engineer_id (nhánh dữ liệu cũ), không workflow.
        DB::table('sites')->insert([
            'id' => 790310, 'project_code' => 'CT-DB-LEAD', 'name' => 'CT dữ liệu cũ',
            'lead_engineer_id' => self::KS['chiLead'], 'progress_percent' => 90,
            'target_completion_at' => '2026-10-22', 'completed_at' => '2026-10-19',
            'created_at' => '2026-09-01 08:00:00', 'updated_at' => '2026-10-19 08:00:00',
        ]);

        DB::table('technical_kpi_project_evidence')->insert([
            'site_id' => 790301, 'user_id' => self::KS['coMinhChung'], 'payroll_month' => self::MONTH,
            'timeline_excluded' => 0, 'quality_first_pass' => 0, 'material_waste_percent' => 6.25,
            'hse_pass' => 0, 'evn_app_required' => 1, 'evn_app_completed' => 0, 'penalty_points' => 10,
            'note' => 'minh chứng xấu', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
