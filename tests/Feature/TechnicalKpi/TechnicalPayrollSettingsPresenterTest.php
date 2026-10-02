<?php

declare(strict_types=1);

namespace Tests\Feature\TechnicalKpi;

use App\View\Presenters\Technical\TechnicalPayrollSettingsPresenter;
use Tests\TestCase;

/**
 * TechnicalPayrollSettingsPresenter thay 2 khối `@php` của trang cài đặt hệ số KPI kỹ thuật.
 *
 * Khẳng định giá trị thật, gồm các định dạng phải giữ y bản cũ: phần trăm ô input dùng dấu chấm
 * và không phân cách nghìn, còn `plan_value`/`actual_value` giữ NGUYÊN chuỗi từ cột decimal.
 */
final class TechnicalPayrollSettingsPresenterTest extends TestCase
{
    private function presenter(): TechnicalPayrollSettingsPresenter
    {
        return new TechnicalPayrollSettingsPresenter;
    }

    public function test_thieu_dong_cai_dat_thi_dung_mac_dinh_cua_tung_he_so(): void
    {
        $data = $this->presenter()->viewData([], [], false, []);

        $this->assertSame('70', $data['baseSetting']->percentSummary);
        $this->assertSame('70.00', $data['baseSetting']->percentInput);
        $this->assertSame('Tỷ lệ lương cố định', $data['baseSetting']->label);
        $this->assertSame('Phần lương cố định trên Gross.', $data['baseSetting']->note);

        $this->assertSame('30', $data['kpiSalarySetting']->percentSummary);
        $this->assertSame('30.00', $data['kpiSalarySetting']->percentInput);

        // Trần KPI mặc định 1.30 -> 130%, và ô tóm tắt của nó không hiển thị trên trang.
        $this->assertSame('130.00', $data['maxSetting']->percentInput);
        $this->assertSame('Trần KPI tổng', $data['maxSetting']->label);
    }

    public function test_dong_cai_dat_trong_db_ghi_de_mac_dinh(): void
    {
        $settings = [
            (object) ['setting_key' => 'base_salary_rate', 'setting_value' => '0.6500', 'setting_label' => 'Lương cứng', 'note' => 'Ghi chú riêng'],
        ];

        $data = $this->presenter()->viewData($settings, [], true, []);

        $this->assertSame('65', $data['baseSetting']->percentSummary);
        // Dấu CHẤM thập phân, KHÔNG phân cách nghìn — <input type=number> không nhận dấu phẩy.
        $this->assertSame('65.00', $data['baseSetting']->percentInput);
        $this->assertSame('Lương cứng', $data['baseSetting']->label);
        $this->assertSame('Ghi chú riêng', $data['baseSetting']->note);
        // Hệ số không có dòng vẫn về mặc định
        $this->assertSame('30.00', $data['kpiSalarySetting']->percentInput);
        $this->assertTrue($data['canManageKpi']);
    }

    public function test_khong_co_dong_bat_thi_dung_5_tieu_chi_mac_dinh(): void
    {
        $data = $this->presenter()->viewData([], [], false, []);

        $this->assertCount(5, $data['criteriaRows']);
        $this->assertSame(5, $data['criteriaCount']);
        $this->assertSame([], $data['legacyRows']);

        $first = $data['criteriaRows'][0];
        $this->assertNull($first->id);
        $this->assertSame('solar_0', $first->key);
        $this->assertSame('Tiến độ hoàn thành lắp đặt hệ thống', $first->name);
        $this->assertSame(30.0, $first->weightPercent);
        $this->assertSame('manual', $first->sourceCode);
        $this->assertSame('bi-stopwatch', $first->icon);
        $this->assertSame(1, $first->order);

        // Tổng trọng số 5 tiêu chí mặc định phải đúng 100%
        $tong = array_sum(array_map(fn ($r) => $r->weightPercent, $data['criteriaRows']));
        $this->assertSame(100.0, round($tong, 6));
    }

    public function test_dong_bat_trong_db_thang_tieu_chi_mac_dinh_va_dong_tat_thanh_legacy(): void
    {
        $items = [
            (object) ['id' => 11, 'name' => 'Tiêu chí DB 1', 'unit' => 'Công trình', 'plan_value' => '2.00', 'actual_value' => '1.00', 'weight' => '0.4000', 'calc_type' => 'actual_div_plan', 'source_code' => 'timeline', 'note' => 'Ghi chú 1', 'sort_order' => 1, 'is_enabled' => 1],
            (object) ['id' => 12, 'name' => 'Tiêu chí cũ', 'unit' => 'Công trình', 'plan_value' => '1.00', 'actual_value' => '1.00', 'weight' => '0.1000', 'calc_type' => 'actual_div_plan', 'source_code' => null, 'note' => null, 'sort_order' => 9, 'is_enabled' => 0],
        ];

        $data = $this->presenter()->viewData([], $items, true, ['manual' => 'Nhập tay']);

        $this->assertCount(1, $data['criteriaRows']);
        $this->assertSame(1, $data['criteriaCount']);
        $row = $data['criteriaRows'][0];
        $this->assertSame(11, $row->id);
        $this->assertSame('solar_0', $row->key);
        $this->assertSame(40.0, $row->weightPercent);
        $this->assertSame('timeline', $row->sourceCode);
        // Cột decimal của MariaDB trả CHUỖI '2.00' — giữ nguyên, ép kiểu sẽ đổi HTML in ra.
        $this->assertSame('2.00', $row->plan);
        $this->assertSame('1.00', $row->actual);

        $this->assertCount(1, $data['legacyRows']);
        $legacy = $data['legacyRows'][0];
        $this->assertSame(12, $legacy->id);
        $this->assertSame('legacy_12', $legacy->key);
        $this->assertSame(10.0, $legacy->weightPercent);
        // source_code null -> 'manual', note null -> chuỗi rỗng, như bản cũ
        $this->assertSame('manual', $legacy->sourceCode);
        $this->assertSame('', $legacy->note);
        $this->assertSame(9, $legacy->order);
        $this->assertSame('', $legacy->icon);
    }

    public function test_tieu_chi_thu_sau_tro_len_dung_icon_du_phong(): void
    {
        $items = [];
        for ($i = 1; $i <= 6; $i++) {
            $items[] = (object) ['id' => $i, 'name' => 'TC '.$i, 'unit' => 'x', 'plan_value' => '1.00', 'actual_value' => '1.00', 'weight' => '0.1000', 'calc_type' => 'actual_div_plan', 'source_code' => 'manual', 'note' => '', 'sort_order' => $i, 'is_enabled' => 1];
        }

        $data = $this->presenter()->viewData([], $items, true, []);

        $this->assertSame('bi-phone', $data['criteriaRows'][4]->icon);
        $this->assertSame('bi-bar-chart', $data['criteriaRows'][5]->icon, 'tiêu chí thứ 6 không có icon riêng');
    }
}
