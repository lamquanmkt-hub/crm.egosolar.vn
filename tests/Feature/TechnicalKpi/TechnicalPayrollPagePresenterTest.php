<?php

declare(strict_types=1);

namespace Tests\Feature\TechnicalKpi;

use App\DTOs\Technical\TechnicalKpiCriteriaInput;
use App\DTOs\Technical\TechnicalPayrollListRow;
use App\View\Presenters\Technical\TechnicalPayrollPagePresenter;
use Tests\TestCase;

/**
 * TechnicalPayrollPagePresenter thay 3 khối `@php` của trang lương KPI kỹ thuật (2026-09-25).
 *
 * Khối 1 đọc 5 hệ số + khai closure chọn icon; khối 2 nằm trong `@foreach($kpis)` suy ra cờ kiểu
 * tính; khối 3 là một dòng trong `@forelse($payrolls)`.
 */
final class TechnicalPayrollPagePresenterTest extends TestCase
{
    private const VIEW = 'resources/views/kythuat/luong.blade.php';

    private const CONTROLLER_KEYS = ['settings', 'employees', 'payrolls', 'summary', 'kpis', 'kpiConfigWeight', 'kpiConfigValid'];

    private const LOOP_AND_BLADE_VARIABLES = ['i', 'kpi', 'row', 'employee', 'loop', 'errors', 'slot', 'attributes', 'component'];

    private function presenter(): TechnicalPayrollPagePresenter
    {
        return new TechnicalPayrollPagePresenter;
    }

    public function test_view_khong_con_php_va_moi_bien_do_presenter_hoac_controller_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);

        $data = $this->presenter()->viewData([], [], []);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)));
    }

    public function test_view_chi_doc_thuoc_tinh_that_cua_hai_dto(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        foreach ([
            'kpi' => TechnicalKpiCriteriaInput::class,
            'row' => TechnicalPayrollListRow::class,
        ] as $var => $class) {
            preg_match_all('/\$'.$var.'->([a-zA-Z]+)/', $source, $m);
            $properties = array_map(
                fn (\ReflectionProperty $p) => $p->getName(),
                (new \ReflectionClass($class))->getProperties()
            );
            $this->assertNotSame([], $m[1], $var);
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), $var);
        }
    }

    public function test_he_so_thieu_thi_ve_mac_dinh_con_co_thi_doc_tu_settings(): void
    {
        $mac = $this->presenter()->viewData([], [], []);
        $this->assertSame(0.70, $mac['baseRate']);
        $this->assertSame(0.30, $mac['kpiRate']);
        $this->assertSame(1.30, $mac['kpiMaxRate']);
        $this->assertSame(0.05, $mac['qualityPenalty']);
        $this->assertSame(0.05, $mac['safetyPenalty']);

        $co = $this->presenter()->viewData(['base_salary_rate' => '0.6500', 'kpi_salary_rate' => 0.35], [], []);
        $this->assertSame(0.65, $co['baseRate']);
        $this->assertSame(0.35, $co['kpiRate']);
        $this->assertSame(1.30, $co['kpiMaxRate'], 'hệ số không có trong settings vẫn về mặc định');
    }

    public function test_icon_rieng_cho_ba_kieu_tinh_va_icon_theo_v_i_tr_i_cho_phan_con_lai(): void
    {
        // Khoá mảng là `cfg_<id>`/`default_N`, KHÔNG phải số tuần tự — icon mặc định phải xoay theo
        // VỊ TRÍ chứ không theo khoá. Bản cũ dùng $loop->index nên đây là điểm dễ làm sai.
        $kpis = [
            'cfg_91' => ['type' => 'actual_div_plan', 'name' => 'A', 'weight' => 0.2],
            'cfg_92' => ['type' => 'material_waste', 'name' => 'B', 'weight' => 0.2],
            'cfg_93' => ['type' => 'minus_safety', 'name' => 'C', 'weight' => 0.2],
            'cfg_94' => ['type' => 'minus_quality', 'name' => 'D', 'weight' => 0.2],
            'cfg_95' => ['type' => 'actual_div_plan', 'name' => 'E', 'weight' => 0.2],
        ];

        $data = $this->presenter()->viewData([], $kpis, []);

        $this->assertSame(['cfg_91', 'cfg_92', 'cfg_93', 'cfg_94', 'cfg_95'], array_keys($data['criteriaInputs']), 'khoá mảng phải giữ nguyên');
        $this->assertSame('bi-stopwatch', $data['criteriaInputs']['cfg_91']->icon, 'vị trí 0');
        $this->assertSame('bi-box-seam', $data['criteriaInputs']['cfg_92']->icon);
        $this->assertSame('bi-shield-check', $data['criteriaInputs']['cfg_93']->icon);
        $this->assertSame('bi-patch-check', $data['criteriaInputs']['cfg_94']->icon);
        $this->assertSame('bi-phone', $data['criteriaInputs']['cfg_95']->icon, 'vị trí 4 -> icon thứ 5');
        $this->assertSame(5, $data['criteriaCount']);
    }

    public function test_icon_du_phong_xoay_vong_sau_bay_tieu_chi(): void
    {
        $kpis = [];
        for ($i = 0; $i < 8; $i++) {
            $kpis['cfg_'.$i] = ['type' => 'actual_div_plan', 'name' => 'TC'.$i, 'weight' => 0.1];
        }

        $data = $this->presenter()->viewData([], $kpis, []);

        $this->assertSame('bi-graph-up-arrow', $data['criteriaInputs']['cfg_6']->icon, 'vị trí 6 = icon cuối');
        $this->assertSame('bi-stopwatch', $data['criteriaInputs']['cfg_7']->icon, 'vị trí 7 quay lại icon đầu (7 % 7 = 0)');
    }

    public function test_co_kieu_tinh_va_gia_tri_mac_dinh_cua_tieu_chi(): void
    {
        $data = $this->presenter()->viewData([], [
            'a' => ['type' => 'material_waste', 'name' => 'Hao hụt', 'weight' => 0.3, 'default_actual' => 1.5],
            'b' => ['type' => 'minus_quality', 'name' => 'Lỗi', 'weight' => 0.2, 'default_plan' => 3],
            'c' => ['type' => 'actual_div_plan', 'name' => 'Tiến độ', 'weight' => 0.5, 'subject' => 'Đúng hạn', 'unit' => 'CT', 'rule' => 'TH/KH', 'source' => 'Tay', 'definition_id' => 12],
        ], []);

        $a = $data['criteriaInputs']['a'];
        $this->assertTrue($a->isMaterial);
        $this->assertFalse($a->isErrorBased);
        $this->assertSame(1.5, $a->defaultActual);
        $this->assertSame(1, $a->defaultPlan, 'thiếu default_plan -> 1');

        $b = $data['criteriaInputs']['b'];
        $this->assertFalse($b->isMaterial);
        $this->assertTrue($b->isErrorBased);
        $this->assertSame(3, $b->defaultPlan);

        $c = $data['criteriaInputs']['c'];
        $this->assertFalse($c->isMaterial);
        $this->assertFalse($c->isErrorBased);
        $this->assertSame('Đúng hạn', $c->subject);
        $this->assertSame('50', $c->weightPercentText);
        $this->assertSame(0.5, $c->weight, 'trọng số THÔ giữ nguyên cho value= và data-weight=');
        $this->assertSame('12', $c->definitionId);

        // subject rỗng thì lấy name, như `$kpi['subject'] ?? $kpi['name']` của bản cũ
        $this->assertSame('Hao hụt', $a->subject);
    }

    public function test_dong_bang_luong_dinh_dang_va_co_da_duyet(): void
    {
        $data = $this->presenter()->viewData([], [], [
            (object) ['id' => 5, 'employee_name' => 'KS Một', 'position_name' => 'Kỹ sư trưởng', 'payroll_month' => '2026-01', 'month_label' => 'Tháng 01/2026', 'gross_salary' => '20000000.00', 'total_kpi_percent' => '0.9250', 'total_income' => '18500000.00', 'status' => 'approved'],
            // Thiếu month_label -> rơi về payroll_month; thiếu position_name -> 'Kỹ thuật'
            (object) ['id' => 6, 'employee_name' => 'KS Hai', 'position_name' => null, 'payroll_month' => '2026-02', 'month_label' => null, 'gross_salary' => 0, 'total_kpi_percent' => 0, 'total_income' => 0, 'status' => 'draft'],
        ]);

        $this->assertSame(2, $data['payrollCount']);
        [$first, $second] = $data['payrollRows'];

        $this->assertSame(5, $first->id);
        $this->assertSame('Tháng 01/2026', $first->monthText);
        $this->assertSame('92,5%', $first->kpiPercentText, 'KPI lưu dạng tỷ lệ nên nhân 100');
        $this->assertSame('20.000.000 đ', $first->grossText);
        $this->assertSame('18.500.000 đ', $first->incomeText);
        $this->assertTrue($first->approved);

        $this->assertSame('2026-02', $second->monthText);
        $this->assertSame('Kỹ thuật', $second->positionName);
        $this->assertSame('0,0%', $second->kpiPercentText);
        $this->assertFalse($second->approved);
    }

    public function test_thieu_status_thi_coi_la_chua_duyet(): void
    {
        $data = $this->presenter()->viewData([], [], [(object) ['id' => 9]]);

        $this->assertFalse($data['payrollRows'][0]->approved);
        $this->assertSame('—', $data['payrollRows'][0]->monthText);
        $this->assertSame('Kỹ thuật', $data['payrollRows'][0]->positionName);
    }
}
