<?php

declare(strict_types=1);

namespace Tests\Feature\TechnicalKpi;

use App\DTOs\Technical\KpiCriteriaRow;
use App\DTOs\Technical\KpiSettingRow;
use App\View\Presenters\Technical\TechnicalPayrollSettingsPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard cho `kythuat/luong_settings.blade.php` sau khi dời 2 khối `@php` (2026-09-25).
 *
 * Khối cũ tự chạy truy vấn `technical_payroll_kpi_items`, tự gọi `auth()->user()->hasRole()`
 * và một service ngay trong Blade; khối thứ hai nằm TRONG vòng lặp chỉ để phân biệt dòng DB
 * (object) với tiêu chí mặc định (mảng). Guard này chặn cả ba thứ đó quay lại.
 */
final class KpiSettingsPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/kythuat/luong_settings.blade.php';

    /** Khoá view() do controller cấp ngoài presenter. */
    private const CONTROLLER_KEYS = ['settings'];

    private const LOOP_AND_BLADE_VARIABLES = ['row', 'legacy', 'sourceValue', 'sourceLabel', 'loop', 'errors', 'slot', 'attributes', 'component'];

    public function test_view_khong_tu_truy_van_khong_doc_auth_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);
        $this->assertStringNotContainsString('DB::', $source, 'truy vấn phải nằm ở controller');
        $this->assertStringNotContainsString('auth()', $source, 'quyền phải do controller quyết định');
        $this->assertStringNotContainsString('SchemaCache', $source);

        $data = (new TechnicalPayrollSettingsPresenter)->viewData([], [], false, []);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));
        $this->assertSame(
            [],
            array_values(array_diff(array_unique($m[1]), $provided)),
            'biến view dùng mà presenter/controller không cấp'
        );
    }

    public function test_view_chi_doc_thuoc_tinh_that_cua_dto(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $criteria = array_map(
            fn (\ReflectionProperty $p) => $p->getName(),
            (new \ReflectionClass(KpiCriteriaRow::class))->getProperties()
        );
        $setting = array_map(
            fn (\ReflectionProperty $p) => $p->getName(),
            (new \ReflectionClass(KpiSettingRow::class))->getProperties()
        );

        foreach (['row' => $criteria, 'legacy' => $criteria] as $var => $properties) {
            preg_match_all('/\$'.$var.'->([a-zA-Z]+)/', $source, $m);
            $this->assertNotSame([], $m[1], "không thấy chỗ nào đọc \$$var-> — guard mất tác dụng");
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), $var);
        }

        foreach (['baseSetting', 'kpiSalarySetting', 'maxSetting'] as $var) {
            preg_match_all('/\$'.$var.'->([a-zA-Z]+)/', $source, $m);
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $setting)), $var);
        }
    }

    public function test_trang_that_dung_5_tieu_chi_mac_dinh_khi_bang_chua_co_dong_nao(): void
    {
        $admin = $this->userWithRole('admin');
        DB::table('technical_payroll_kpi_items')->delete();

        $response = $this->actingAs($admin)->get('/ky-thuat/luong/settings');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('Tiến độ hoàn thành lắp đặt hệ thống', $html);
        $this->assertStringContainsString('Hỗ trợ thủ tục EVN &amp; cài đặt App', $html);
        $this->assertStringContainsString('value="30.00"', $html);   // trọng số 0.30 -> 30.00
        $this->assertStringContainsString('>5</strong>', $html);      // ô "Số tiêu chí"
        $this->assertStringContainsString('bi-stopwatch', $html);     // icon tiêu chí 1
    }

    public function test_trang_that_uu_tien_dong_trong_db_va_giu_dong_da_tat(): void
    {
        $admin = $this->userWithRole('admin');
        $now = '2024-01-01 00:00:00';
        DB::table('technical_payroll_kpi_items')->delete();
        DB::table('technical_payroll_kpi_items')->insert([
            ['name' => 'Tiêu chí DB 1', 'unit' => 'Công trình', 'plan_value' => 2, 'actual_value' => 1, 'weight' => 0.4, 'calc_type' => 'actual_div_plan', 'source_code' => 'manual', 'note' => 'Ghi chú 1', 'sort_order' => 1, 'is_enabled' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Tiêu chí cũ đã tắt', 'unit' => 'Công trình', 'plan_value' => 1, 'actual_value' => 1, 'weight' => 0.1, 'calc_type' => 'actual_div_plan', 'source_code' => 'manual', 'note' => null, 'sort_order' => 9, 'is_enabled' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $legacyId = (int) DB::table('technical_payroll_kpi_items')->where('is_enabled', 0)->value('id');

        $response = $this->actingAs($admin)->get('/ky-thuat/luong/settings');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('Tiêu chí DB 1', $html);
        $this->assertStringNotContainsString('Tiến độ hoàn thành lắp đặt hệ thống', $html, 'có dòng DB thì không dùng tiêu chí mặc định');
        $this->assertStringContainsString('value="40.00"', $html);
        $this->assertStringContainsString('>1</strong>', $html);
        // Dòng đã tắt vẫn được ghi lại bằng input hidden để lần lưu sau không mất
        $this->assertStringContainsString('kpi_items[legacy_'.$legacyId.'][is_enabled]', $html);
        $this->assertStringContainsString('Tiêu chí cũ đã tắt', $html);
    }

    public function test_khong_phai_admin_thi_moi_o_nhap_deu_bi_khoa(): void
    {
        $tech = $this->userWithRole('technical');

        $response = $this->actingAs($tech)->get('/ky-thuat/luong/settings');

        $response->assertOk();
        $html = (string) $response->getContent();

        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('Xem', $html);
        // Ba thứ này thật sự nằm trong @if($canManageKpi); `js-delete-row` thì KHÔNG dùng được
        // để kiểm vì nó còn xuất hiện trong <script> và template JS của trang, luôn có mặt.
        $this->assertStringContainsString('Chỉ Admin được sửa', $html);
        $this->assertStringNotContainsString('Lưu tỷ lệ lương', $html, 'không admin thì không có nút lưu');
        $this->assertStringContainsString('const canManage = false', $html);
    }
}
