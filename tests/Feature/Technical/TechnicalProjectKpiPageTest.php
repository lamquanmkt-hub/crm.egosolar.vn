<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\DTOs\Technical\ProjectKpiEngineerRow;
use App\DTOs\Technical\ProjectKpiHeader;
use App\View\Presenters\Technical\TechnicalProjectKpiPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard trang `kythuat/kpi_project` sau đợt 2026-10-01.
 */
final class TechnicalProjectKpiPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/kythuat/kpi_project.blade.php';

    private const CONTROLLER_KEYS = ['site', 'month', 'users', 'evidence', 'signals', 'canManage'];

    private const LOOP_AND_BLADE_VARIABLES = ['row', 'loop', 'errors', 'slot', 'attributes', 'component'];

    public function test_view_chi_in_khong_tu_tinh(): void
    {
        // Bỏ chú thích Blade trước khi kiểm chuỗi cấm (chú thích không ra HTML).
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view, 'view còn khối @php');
        $this->assertStringNotContainsString('Carbon', $view, 'view còn tự parse ngày');
        $this->assertStringNotContainsString('str_pad', $view, 'view còn tự ghép mã dự phòng');
        $this->assertStringNotContainsString('<style', $view, 'view dùng lại khối style nội tuyến');
        $this->assertStringNotContainsString('<script', $view);
        $this->assertStringNotContainsString('class="pkpi', $view, 'view dùng lại hệ class pkpi-* cũ');
        $this->assertStringNotContainsString('me-1', $view, 'view dùng lại lớp Bootstrap');
        $this->assertKhongLamDungImportant($view, 'kythuat/kpi_project');

        // Hai ngưỡng của khối @media cũ (1000px, 600px) phải viết N+1.
        $this->assertStringContainsString('tw:max-[1001px]', $view);
        $this->assertStringContainsString('tw:max-[601px]', $view);
        $this->assertStringNotContainsString('tw:max-[1000px]', $view);
        $this->assertStringNotContainsString('tw:max-[600px]', $view);

        $data = (new TechnicalProjectKpiPresenter)->viewData(
            site: (object) ['id' => 1], users: [], evidence: new Collection, signals: [], engineerCount: 0,
        );

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $view, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        foreach (['row' => ProjectKpiEngineerRow::class, 'kpiHeader' => ProjectKpiHeader::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $view, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien ({$dto})");
        }
    }

    public function test_trang_that_in_dung_va_giu_trang_thai_bieu_mau(): void
    {
        Carbon::setTestNow('2026-10-01 09:15:00');

        $ks = $this->userWithRole('technical', ['id' => 740002, 'name' => 'Nguyễn Kỹ Sư', 'email' => 'kpi-ks@example.test']);
        $admin = $this->userWithRole('admin', ['id' => 740001, 'name' => 'QT Guard']);

        DB::table('sites')->insert([[
            'id' => 740010, 'project_code' => 'CT-KPI-G', 'name' => 'Công trình Guard',
            'lead_engineer_id' => 740002, 'progress_percent' => 70,
            'target_completion_at' => '2026-10-20', 'completed_at' => null,
            'created_at' => '2026-09-01 08:00:00', 'updated_at' => '2026-09-01 08:00:00',
        ]]);

        DB::table('technical_kpi_project_evidence')->insert([[
            'id' => 740020, 'site_id' => 740010, 'user_id' => 740002, 'payroll_month' => '2026-10',
            'timeline_excluded' => 1, 'timeline_exclusion_reason' => 'Chờ EVN',
            'quality_first_pass' => 0, 'material_waste_percent' => 3.25,
            'hse_pass' => 1, 'evn_app_required' => 1, 'evn_app_completed' => 0,
            'penalty_points' => 10, 'note' => 'Ghi chú minh chứng',
            'recorded_by' => 740001, 'created_at' => now(), 'updated_at' => now(),
        ]]);

        $html = (string) $this->actingAs($admin)->get('/ky-thuat/kpis/cong-trinh/740010')->assertOk()->getContent();

        $this->assertStringContainsString('CT-KPI-G', $html);
        $this->assertStringContainsString('70%', $html);
        $this->assertStringContainsString('20/10/2026', $html);
        $this->assertStringContainsString('Nguyễn Kỹ Sư', $html);
        $this->assertStringContainsString('Kỹ sư #740002', $html);
        $this->assertStringContainsString('Lưu KPI công trình', $html, 'admin phải thấy nút lưu');

        // Trạng thái biểu mẫu phải giữ: checkbox đã tick, các select đã chọn, giá trị đã điền.
        $this->assertStringContainsString('checked', $html);
        $this->assertStringContainsString('value="Chờ EVN"', $html);
        $this->assertStringContainsString('value="3.25"', $html);
        $this->assertStringContainsString('Ghi chú minh chứng', $html);
        $this->assertSame(5, substr_count($html, 'selected'), 'đúng 5 ô select đang có lựa chọn');

        // Kỹ sư thường chỉ xem.
        $htmlKs = (string) $this->actingAs($ks)->get('/ky-thuat/kpis/cong-trinh/740010')->assertOk()->getContent();
        $this->assertStringContainsString('Chỉ Trưởng kỹ thuật', $htmlKs);
        $this->assertStringNotContainsString('Lưu KPI công trình', $htmlKs);
        $this->assertStringContainsString('disabled', $htmlKs, 'các ô phải bị khoá với người không có quyền');
    }

    public function test_cong_trinh_chua_co_ky_su(): void
    {
        $admin = $this->userWithRole('admin', ['id' => 740003, 'name' => 'QT Guard 2']);

        DB::table('sites')->insert([[
            'id' => 740011, 'project_code' => null, 'name' => '',
            'lead_engineer_id' => null, 'progress_percent' => 0,
            'target_completion_at' => null, 'completed_at' => null,
            'created_at' => '2026-09-02 08:00:00', 'updated_at' => '2026-09-02 08:00:00',
        ]]);

        $html = (string) $this->actingAs($admin)->get('/ky-thuat/kpis/cong-trinh/740011')->assertOk()->getContent();

        $this->assertStringContainsString('Chưa có kỹ sư được phân công cho công trình này.', $html);
        $this->assertStringContainsString('DA-SITE-740011', $html, 'không có mã thì ghép DA-SITE-<id>');
        $this->assertStringContainsString('Công trình #740011', $html);
    }
}
