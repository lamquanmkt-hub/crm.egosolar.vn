<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Enums\Role;
use App\View\Presenters\Projects\SiteEditFormPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Form sửa công trình sau khi dời 6 khối `@php` sang SiteEditFormPresenter (2026-09-07). */
final class SiteEditPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/sites/edit.blade.php';

    /** Composer cấp `egoSiteCompanyOptions`; còn lại là biến vòng lặp/Blade. */
    private const VIEW_VARIABLES_NOT_FROM_PRESENTER = ['egoSiteCompanyOptions', 'company', 'i', 'd', 'p', 'term', 't', 'errors', 'e', 'loop', 'slot', 'attributes', 'component'];

    public function test_mo_lai_sau_khi_gui_sai_thi_old_input_thang_du_lieu_da_luu(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $now = '2026-09-01 08:00:00';
        DB::table('companies')->insert(['id' => 998501, 'code' => 'EGOT998', 'name' => 'EGO Test', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sites')->insert(['id' => 998601, 'name' => 'Công trình canh test', 'company_id' => 998501, 'status' => 'installing', 'contract_amount' => 500000000,
            'labor_cost' => 0, 'transport_cost' => 0, 'other_cost' => 0, 'installed_at' => '2026-08-15', 'technician_name' => 'Kỹ A, Kỹ B', 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('site_devices')->insert(['site_id' => 998601, 'type' => 'pv', 'brand' => 'Jinko', 'model' => 'Tiger', 'qty' => 20, 'created_at' => $now, 'updated_at' => $now]);

        $html = $this->actingAs($admin)->get('/cong-trinh/998601/edit')->assertOk()->getContent();
        $this->assertStringContainsString('value="solar_panel"     selected', $html, 'type pv cũ chọn mục solar_panel');
        $this->assertStringContainsString('data-val="Kỹ B"', $html);
        $this->assertStringContainsString('value="2026-08-15"', $html);

        $this->actingAs($admin)->from('/cong-trinh/998601/edit')->post('/cong-trinh/998601/cap-nhat', [
            'name' => '', 'status' => 'done', 'installed_at' => '2026-09-05', 'technician_name' => 'Kỹ X ; Kỹ Y',
            'devices' => [0 => ['type' => 'battery', 'brand' => 'BYD', 'qty' => 2], 2 => ['brand' => 'Không type', 'qty' => 1]],
        ])->assertRedirect('/cong-trinh/998601/edit');
        $html = $this->actingAs($admin)->get('/cong-trinh/998601/edit')->assertOk()->getContent();

        $this->assertStringContainsString('value="battery"         selected', $html);
        $this->assertStringContainsString('name="devices[2][brand]" value="Không type"', $html, 'giữ chỉ số 2 của old input');
        $this->assertStringNotContainsString('value="Jinko"', $html, 'old input thay dữ liệu đã lưu (Jinko chỉ còn trong placeholder)');
        $this->assertStringContainsString('data-val="Kỹ Y"', $html);
        $this->assertStringContainsString('value="2026-09-05"', $html);
        $this->assertStringContainsString('value="done"       selected', $html);
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $used = array_values(array_unique(array_diff($m[1], self::VIEW_VARIABLES_NOT_FROM_PRESENTER)));
        $provided = array_keys(app(SiteEditFormPresenter::class)->viewData(new \App\Models\Projects\Site, [], [], [], [], 'residential', ['label' => '', 'index_url' => ''], ''));
        $this->assertSame([], array_values(array_diff($used, $provided)), 'biến view dùng mà presenter không trả');
    }
}
