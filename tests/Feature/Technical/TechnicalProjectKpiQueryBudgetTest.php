<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ngân sách truy vấn của trang KPI công trình — chặn N+1 quay lại.
 *
 * Vì sao có tệp này: tới 2026-10-01, `show()` gọi `projectsForUserMonth()` trong vòng lặp kỹ sư
 * rồi `firstWhere('site_id', …)`. Đo trên trang thật, số truy vấn khớp CHÍNH XÁC công thức
 * `13 + 3·E + E·K` ở cả 9 tổ hợp đã thử (E = số kỹ sư của công trình, K = số công trình KHÁC mỗi
 * kỹ sư cũng tham gia trong kỳ): E=2,K=0 → 19 · E=8,K=0 → 37 · E=8,K=4 → 69 · E=8,K=8 → 101.
 * Tức Θ(E·K), và gần hết dữ liệu lấy về bị bỏ ngay sau `firstWhere`.
 *
 * Service không nhanh thì vô nghĩa nếu controller thôi gọi nó, nên guard này đo TRANG THẬT,
 * không đo service. Nó so số truy vấn giữa hai quy mô: bằng nhau = không có N+1.
 */
final class TechnicalProjectKpiQueryBudgetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_so_truy_van_khong_tang_theo_so_ky_su_va_so_cong_trinh(): void
    {
        Carbon::setTestNow('2026-10-15 09:00:00');
        $admin = $this->userWithRole('admin', ['id' => 771000, 'name' => 'QT ngân sách']);

        // Làm nóng: lượt đầu của tiến trình còn nạp SchemaCache nên tốn thêm truy vấn.
        $this->actingAs($admin)->get('/ky-thuat/kpis/cong-trinh/'.$this->gieo(771100, 1, 0))->assertOk();

        $nho = $this->demTruyVan($admin, $this->gieo(772000, 2, 0));
        $to = $this->demTruyVan($admin, $this->gieo(773000, 8, 8));

        $this->assertSame($nho, $to, sprintf(
            "Số truy vấn TĂNG theo quy mô: 2 kỹ sư → %d, 8 kỹ sư × 8 công trình khác → %d.\n".
            'Có N+1 trong trang KPI công trình; dùng ProjectKpiLinkService::signalsForProjectUsers().',
            $nho, $to
        ));

        // Chốt luôn trần tuyệt đối để lần sau thêm truy vấn mới cũng phải cố ý hạ/nâng con số này.
        $this->assertLessThanOrEqual(20, $to, "trang tốn $to truy vấn — vượt trần 20");
    }

    private function demTruyVan(object $admin, int $siteId): int
    {
        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });

        $this->actingAs($admin)->get("/ky-thuat/kpis/cong-trinh/{$siteId}")->assertOk();

        DB::getEventDispatcher()->forget(\Illuminate\Database\Events\QueryExecuted::class);

        return $n;
    }

    /** Gieo 1 công trình + E kỹ sư; mỗi kỹ sư còn tham gia K công trình KHÁC cùng kỳ. */
    private function gieo(int $base, int $soKySu, int $soCongTrinhKhac): int
    {
        $this->congTrinh($base, 'CT-NS-'.$base);

        $users = [];
        for ($i = 0; $i < $soKySu; $i++) {
            $uid = $base + 100 + $i;
            DB::table('users')->insert([
                'id' => $uid, 'name' => 'KS '.$uid, 'email' => "ks{$uid}@ns.test",
                'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $users[] = $uid;
        }

        $this->workflow($base, $base + 500, $users);

        $dem = 0;
        foreach ($users as $uid) {
            for ($j = 0; $j < $soCongTrinhKhac; $j++) {
                $khac = $base + 700 + ($dem++);
                $this->congTrinh($khac, 'CT-KHAC-'.$khac);
                $this->workflow($khac, $base + 2000 + $dem * 10, [$uid]);
            }
        }

        return $base;
    }

    private function congTrinh(int $id, string $code): void
    {
        DB::table('sites')->insert([
            'id' => $id, 'project_code' => $code, 'name' => 'CT '.$id,
            'progress_percent' => 50, 'target_completion_at' => '2026-10-28',
            'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-10 08:00:00',
        ]);
    }

    private function workflow(int $siteId, int $stepBase, array $userIds): void
    {
        foreach ([['construction', 1], ['acceptance', 2]] as $i => [$code, $seq]) {
            $stepId = $stepBase + $i;
            DB::table('project_workflow_steps')->insert([
                'id' => $stepId, 'site_id' => $siteId, 'step_code' => $code, 'sequence' => $seq,
                'status' => 'approved', 'due_at' => '2026-10-20 00:00:00',
                'submitted_at' => '2026-10-12 00:00:00', 'approved_at' => '2026-10-13 00:00:00',
                'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-13 08:00:00',
            ]);
            foreach ($userIds as $n => $uid) {
                DB::table('project_workflow_assignments')->insert([
                    'workflow_step_id' => $stepId, 'user_id' => $uid,
                    'assignment_role' => $n === 0 ? 'lead' : 'collaborator', 'status' => 'done',
                    'is_active' => 1, 'submitted_at' => '2026-10-12 00:00:00',
                    'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-12 08:00:00',
                ]);
            }
        }
    }
}
