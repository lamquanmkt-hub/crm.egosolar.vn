<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Services\TechnicalKpi\ProjectKpiLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `signalsForProjectUsers()` phải trả về ĐÚNG thứ vòng lặp cũ trả về.
 *
 * Vòng lặp cũ (trong `TechnicalProjectKpiController::show()` tới 2026-10-01):
 *     $signals[$uid] = $svc->projectsForUserMonth($uid, $month)->firstWhere('site_id', $siteId);
 * Bản mới lọc theo `site_id` ngay trong SQL nên số truy vấn không đổi theo số kỹ sư. Test này
 * chạy CẢ HAI trên cùng dữ liệu rồi so từng khoá — bằng chứng "refactor không đổi hành vi",
 * không dừng ở `assertOk()`.
 *
 * ⚠️ Điều học được khi gieo dữ liệu: `wf_step_site_code_uq (site_id, step_code)` nên MỘT công
 * trình chỉ có một bước mỗi mã — mọi kỹ sư của công trình đó dùng CHUNG mốc hạn/hoàn thành, tức
 * `on_time` không thể khác nhau giữa các kỹ sư trên cùng công trình. Muốn phủ nhánh trễ hạn thì
 * phải có công trình thứ hai. Bản đầu của test này đoán sai và đỏ ở đúng chỗ đó.
 */
final class ProjectKpiSignalsEquivalenceTest extends TestCase
{
    use DatabaseTransactions;

    private const SITE_DUNG_HAN = 770100;

    private const SITE_TRE_HAN = 770110;

    private const MONTH = '2026-10';

    private const KS = [
        'workflow' => 770201,       // có workflow, KHÔNG có minh chứng
        'coMinhChung' => 770202,    // có workflow + dòng minh chứng xấu đủ 4 vấn đề
        'lead' => 770203,           // KHÔNG workflow, là lead_engineer_id -> nhánh dữ liệu cũ
        'khongLienQuan' => 770204,  // không workflow, không lead -> null
        'treHan' => 770205,         // workflow ở công trình trễ hạn
    ];

    public function test_ban_moi_tra_ve_y_nhu_vong_lap_cu(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->gieo();

        $ids = array_values(self::KS);

        foreach ([self::SITE_DUNG_HAN, self::SITE_TRE_HAN] as $siteId) {
            $moi = app(ProjectKpiLinkService::class)->signalsForProjectUsers($siteId, $ids, self::MONTH);

            $cu = [];
            foreach ($ids as $uid) {
                // Service MỚI mỗi lượt để memo minh chứng không bắc cầu giữa hai bên.
                $cu[$uid] = app(ProjectKpiLinkService::class)
                    ->projectsForUserMonth($uid, self::MONTH)
                    ->firstWhere('site_id', $siteId);
            }

            $this->assertSame(array_keys($cu), array_keys($moi), "công trình $siteId: thiếu/thừa khoá kỹ sư");

            foreach ($ids as $uid) {
                if ($cu[$uid] === null) {
                    $this->assertNull($moi[$uid], "CT $siteId / KS $uid: cũ null mà mới có dữ liệu");

                    continue;
                }

                $this->assertIsArray($moi[$uid], "CT $siteId / KS $uid: mới null mà cũ có dữ liệu");
                $this->assertSame(array_keys($cu[$uid]), array_keys($moi[$uid]), "CT $siteId / KS $uid: bộ khoá khác");
                $this->assertSame($cu[$uid], $moi[$uid], "CT $siteId / KS $uid: giá trị khác");
            }
        }
    }

    /** Dữ liệu gieo phải THẬT SỰ phủ các nhánh, không thì phép so trên là so hai mảng rỗng. */
    public function test_du_lieu_gieo_phu_du_nam_nhanh(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->gieo();

        $svc = app(ProjectKpiLinkService::class);
        $a = $svc->signalsForProjectUsers(self::SITE_DUNG_HAN, array_values(self::KS), self::MONTH);
        $b = app(ProjectKpiLinkService::class)->signalsForProjectUsers(self::SITE_TRE_HAN, array_values(self::KS), self::MONTH);

        $this->assertTrue($a[self::KS['workflow']]['on_time'], 'nhánh đúng hạn');
        $this->assertSame('missing', $a[self::KS['workflow']]['evidence_status']);
        $this->assertSame(['construction', 'acceptance'], $a[self::KS['workflow']]['steps']);

        $this->assertSame('configured', $a[self::KS['coMinhChung']]['evidence_status']);
        foreach (['Không đạt nghiệm thu lần đầu', 'HSE chưa đạt', 'Hao hụt vật tư cao', 'Có điểm phạt'] as $issue) {
            $this->assertContains($issue, $a[self::KS['coMinhChung']]['issues']);
        }

        $this->assertSame('lead', $a[self::KS['lead']]['role'], 'nhánh dữ liệu cũ');
        $this->assertSame([], $a[self::KS['lead']]['steps'], 'nhánh dữ liệu cũ không có bước workflow');

        $this->assertNull($a[self::KS['khongLienQuan']], 'kỹ sư không liên quan phải ra null');
        $this->assertNull($a[self::KS['treHan']], 'kỹ sư của công trình khác không lọt vào đây');

        $this->assertFalse($b[self::KS['treHan']]['on_time'], 'nhánh trễ hạn');
        $this->assertContains('Trễ tiến độ', $b[self::KS['treHan']]['issues']);
    }

    public function test_so_truy_van_khong_doi_theo_so_ky_su(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->gieo();

        // Làm nóng SchemaCache: lượt đầu của tiến trình còn phải đọc lược đồ.
        app(ProjectKpiLinkService::class)->signalsForProjectUsers(self::SITE_DUNG_HAN, [self::KS['workflow']], self::MONTH);

        $dem = function (array $ids): int {
            $svc = app(ProjectKpiLinkService::class);
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $svc->signalsForProjectUsers(self::SITE_DUNG_HAN, $ids, self::MONTH);
            DB::getEventDispatcher()->forget(\Illuminate\Database\Events\QueryExecuted::class);

            return $n;
        };

        // 1 kỹ sư và 5 kỹ sư phải TỐN BẰNG NHAU: minh chứng hàng loạt + workflow + công trình = 3.
        $this->assertSame(3, $dem([self::KS['workflow']]));
        $this->assertSame(3, $dem(array_values(self::KS)));
    }

    private function gieo(): void
    {
        foreach (self::KS as $uid) {
            DB::table('users')->insert([
                'id' => $uid, 'name' => 'KS '.$uid, 'email' => "ks{$uid}@kpi.test",
                'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('sites')->insert([
            [
                'id' => self::SITE_DUNG_HAN, 'project_code' => 'CT-EQ-OK', 'name' => 'CT đúng hạn',
                'lead_engineer_id' => self::KS['lead'], 'progress_percent' => 80,
                'target_completion_at' => '2026-10-25', 'completed_at' => '2026-10-18',
                'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-18 08:00:00',
            ],
            [
                'id' => self::SITE_TRE_HAN, 'project_code' => 'CT-EQ-LATE', 'name' => 'CT trễ hạn',
                'lead_engineer_id' => null, 'progress_percent' => 60,
                'target_completion_at' => '2026-10-10', 'completed_at' => '2026-10-19',
                'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-19 08:00:00',
            ],
        ]);

        // CT đúng hạn: nghiệm thu 18/10 <= hạn 20/10.
        DB::table('project_workflow_steps')->insert([
            [
                'id' => 770301, 'site_id' => self::SITE_DUNG_HAN, 'step_code' => 'construction', 'sequence' => 1,
                'status' => 'approved', 'due_at' => '2026-10-15 00:00:00',
                'submitted_at' => '2026-10-12 00:00:00', 'approved_at' => '2026-10-13 00:00:00',
                'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-13 08:00:00',
            ],
            [
                'id' => 770302, 'site_id' => self::SITE_DUNG_HAN, 'step_code' => 'acceptance', 'sequence' => 2,
                'status' => 'approved', 'due_at' => '2026-10-20 00:00:00',
                'submitted_at' => '2026-10-17 00:00:00', 'approved_at' => '2026-10-18 00:00:00',
                'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-18 08:00:00',
            ],
            // CT trễ hạn: nghiệm thu 19/10 > hạn 12/10.
            [
                'id' => 770311, 'site_id' => self::SITE_TRE_HAN, 'step_code' => 'acceptance', 'sequence' => 2,
                'status' => 'approved', 'due_at' => '2026-10-12 00:00:00',
                'submitted_at' => '2026-10-18 00:00:00', 'approved_at' => '2026-10-19 00:00:00',
                'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-19 08:00:00',
            ],
        ]);

        foreach ([[self::KS['workflow'], 'lead'], [self::KS['coMinhChung'], 'collaborator']] as [$uid, $role]) {
            foreach ([770301, 770302] as $stepId) {
                DB::table('project_workflow_assignments')->insert([
                    'workflow_step_id' => $stepId, 'user_id' => $uid,
                    'assignment_role' => $role, 'status' => 'done', 'is_active' => 1,
                    'submitted_at' => '2026-10-12 00:00:00',
                    'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-12 08:00:00',
                ]);
            }
        }

        DB::table('project_workflow_assignments')->insert([
            'workflow_step_id' => 770311, 'user_id' => self::KS['treHan'],
            'assignment_role' => 'lead', 'status' => 'done', 'is_active' => 1,
            'submitted_at' => '2026-10-18 00:00:00',
            'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-19 08:00:00',
        ]);

        DB::table('technical_kpi_project_evidence')->insert([
            'site_id' => self::SITE_DUNG_HAN, 'user_id' => self::KS['coMinhChung'], 'payroll_month' => self::MONTH,
            'timeline_excluded' => 0, 'quality_first_pass' => 0, 'material_waste_percent' => 5.5,
            'hse_pass' => 0, 'evn_app_required' => 1, 'evn_app_completed' => 0, 'penalty_points' => 10,
            'note' => 'xấu đủ 4 vấn đề', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
