<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\View\Presenters\Technical\TechnicalProjectKpiPresenter;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * `TechnicalProjectKpiPresenter` — thay 2 khối `@php` của `kythuat/kpi_project` (2026-10-01).
 */
final class TechnicalProjectKpiPresenterTest extends TestCase
{
    /** @param array<string, mixed> $ghiDe */
    private function congTrinh(array $ghiDe = []): object
    {
        return (object) array_merge([
            'id' => 42, 'project_code' => 'CT-A1', 'name' => 'Công trình A',
            'progress_percent' => 70, 'target_completion_at' => '2026-10-20',
            'completed_at' => '2026-10-15',
        ], $ghiDe);
    }

    /**
     * @param  array<string, mixed>  $site
     * @param  array<int, object|null>  $evidence
     * @param  array<int, mixed>  $signals
     * @return array<string, mixed>
     */
    private function chay(array $site = [], array $evidence = [], array $signals = [], int $soKySu = 1): array
    {
        return (new TechnicalProjectKpiPresenter)->viewData(
            site: $this->congTrinh($site),
            users: [(object) ['id' => 9, 'name' => 'Nguyễn Kỹ Sư', 'email' => 'ks@example.test']],
            evidence: new Collection($evidence),
            signals: $signals,
            engineerCount: $soKySu,
        );
    }

    public function test_dau_trang_ghep_ma_va_ten_du_phong(): void
    {
        $h = $this->chay()['kpiHeader'];
        $this->assertSame('CT-A1', $h->projectCode);
        $this->assertSame('Công trình A', $h->projectName);
        $this->assertSame('70%', $h->progressText);
        $this->assertSame('20/10/2026', $h->targetCompletionText);
        $this->assertSame('15/10/2026', $h->completedText);

        // Không có mã → `DA-SITE-` + id đệm 5 chữ số; không có tên → `Công trình #<id>`.
        $trong = $this->chay([
            'project_code' => null, 'name' => '', 'progress_percent' => null,
            'target_completion_at' => null, 'completed_at' => null,
        ])['kpiHeader'];
        $this->assertSame('DA-SITE-00042', $trong->projectCode);
        $this->assertSame('Công trình #42', $trong->projectName);
        $this->assertSame('0%', $trong->progressText);
        $this->assertSame('—', $trong->targetCompletionText, 'ngày trống ra gạch DÀI');
        $this->assertSame('—', $trong->completedText);
    }

    public function test_tin_hieu_workflow_ba_nhanh(): void
    {
        $khong = $this->chay()['engineerRows'][0];
        $this->assertFalse($khong->hasSignal, 'view in "Chưa có hoạt động workflow trong kỳ."');

        $dungHan = $this->chay(signals: [9 => ['deadline' => '20/10', 'completed' => '15/10', 'on_time' => true]])['engineerRows'][0];
        $this->assertTrue($dungHan->hasSignal);
        $this->assertSame('20/10', $dungHan->deadlineText);
        $this->assertSame('15/10', $dungHan->completedText);
        $this->assertSame('ok', $dungHan->onTime);

        $treHan = $this->chay(signals: [9 => ['deadline' => '20/10', 'completed' => '25/10', 'on_time' => false]])['engineerRows'][0];
        $this->assertSame('late', $treHan->onTime);

        // Bản cũ phân biệt `=== true` / `=== false` / còn lại → "Chưa đủ dữ liệu".
        $chuaDu = $this->chay(signals: [9 => ['deadline' => null, 'completed' => null, 'on_time' => null]])['engineerRows'][0];
        $this->assertNull($chuaDu->onTime);
        $this->assertSame('—', $chuaDu->deadlineText);
        $this->assertSame('—', $chuaDu->completedText);
    }

    public function test_gia_tri_bieu_mau_giu_dung_kieu_de_selected_con_khop(): void
    {
        $row = $this->chay(evidence: [9 => (object) [
            'timeline_excluded' => 1, 'timeline_exclusion_reason' => 'Chờ EVN',
            'quality_first_pass' => 0, 'material_waste_percent' => '3.25',
            'hse_pass' => 1, 'evn_app_required' => 1, 'evn_app_completed' => 0,
            'penalty_points' => '10.00', 'note' => 'Ghi chú',
        ]])['engineerRows'][0];

        $this->assertTrue($row->timelineExcluded);
        $this->assertSame('Chờ EVN', $row->exclusionReason);
        // ⚠️ Phải là int 0, không phải chuỗi/null: view so `@selected($row->qualityFirstPass === 0)`.
        $this->assertSame(0, $row->qualityFirstPass);
        $this->assertSame(1, $row->hsePass);
        $this->assertSame(1, $row->evnAppRequired);
        $this->assertSame(0, $row->evnAppCompleted);
        // Và phải là float: view so `=== 0.0 | 10.0 | 20.0`.
        $this->assertSame(10.0, $row->penaltyPoints);
        $this->assertSame('3.25', $row->materialWastePercent);
        $this->assertSame('Ghi chú', $row->note);
    }

    public function test_chua_co_minh_chung_thi_moi_o_de_trong(): void
    {
        $row = $this->chay()['engineerRows'][0];

        $this->assertFalse($row->timelineExcluded);
        $this->assertSame('', $row->exclusionReason);
        $this->assertNull($row->qualityFirstPass, 'null để không ô nào được chọn sẵn');
        $this->assertNull($row->hsePass);
        $this->assertNull($row->evnAppRequired);
        $this->assertNull($row->evnAppCompleted);
        $this->assertSame(0.0, $row->penaltyPoints, 'mặc định 0 điểm');
        $this->assertSame('', $row->materialWastePercent);
        $this->assertSame('', $row->note);
        $this->assertSame('Nguyễn Kỹ Sư', $row->name);
        $this->assertSame(9, $row->userId);
    }

    public function test_so_ky_su_dung_dau_phan_cach_tieng_viet(): void
    {
        $this->assertSame('1.234', $this->chay(soKySu: 1234)['kpiHeader']->engineerCountText);
    }
}
