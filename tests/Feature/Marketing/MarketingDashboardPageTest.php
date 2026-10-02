<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\DTOs\Marketing\MarketingDashboardInput;
use App\Enums\Role;
use App\View\Presenters\Marketing\MarketingDashboardPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trang marketing/dashboard sau khi dời 12 khối `@php` sang {@see MarketingDashboardPresenter}
 * (2026-09-07). Số trên trang phải là số tính từ dữ liệu thật, và view không được tự tính lại.
 */
final class MarketingDashboardPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/marketing/dashboard.blade.php';

    /** Biến trong view không do presenter cấp: biến vòng lặp, Blade, và hằng số nhúng JS. */
    private const VIEW_VARIABLES_NOT_FROM_PRESENTER = ['r', 'it', 'loop', 'errors', 'slot', 'attributes', 'component'];

    public function test_tab_ads_hien_kpi_tinh_tu_ngan_sach_va_chi_so(): void
    {
        $user = $this->seedActorAndData();

        $html = $this->actingAs($user)
            ->get('/marketing/dashboard?from=2026-09-01&to=2026-09-30&platform=facebook')
            ->assertOk()
            ->getContent();

        // spend = max(ngân sách 2.000.000, chỉ số 1.500.000 + 700.000) ; leads 30 + 7 ; CPL = 2.200.000 / 37
        $this->assertStringContainsString('2.200.000 đ', $html);
        $this->assertStringContainsString('59.459 đ', $html);
        $this->assertStringContainsString('23.000', $html, 'reach 20.000 + 3.000');
        // Bảng theo tháng: ngân sách tháng 9 (2.000.000) thắng chỉ số (1.500.000), 30 leads → CPL 66.667
        $this->assertStringContainsString('66.667 đ', $html);
        $this->assertStringContainsString('Chien dich FB Lead', $html);
        // Kỳ trước chưa có nguồn → huy hiệu trung tính, không chia cho 0
        $this->assertStringContainsString('<span class="ego-delta neutral">—</span>', $html);
    }

    public function test_cac_tab_seo_content_email_van_render(): void
    {
        $user = $this->seedActorAndData();

        $html = $this->actingAs($user)->get('/marketing/dashboard?tab=seo&seo_tab=plan')->assertOk()->getContent();
        $this->assertStringContainsString('Wireframe: danh sách task SEO', $html);

        $html = $this->actingAs($user)->get('/marketing/dashboard?platform=seo')->assertOk()->getContent();
        $this->assertStringContainsString('Chưa có dữ liệu query (wireframe)', $html);

        $this->actingAs($user)->get('/marketing/dashboard?tab=content')->assertOk();
        $this->actingAs($user)->get('/marketing/dashboard?tab=email')->assertOk();
    }

    /** View chỉ in giá trị: không còn `@php`, và mọi biến nó đọc đều do presenter cấp. */
    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $matches);
        $used = array_values(array_unique(array_diff($matches[1], self::VIEW_VARIABLES_NOT_FROM_PRESENTER)));
        $provided = array_keys(app(MarketingDashboardPresenter::class)->viewData(new MarketingDashboardInput));

        $this->assertSame([], array_values(array_diff($used, $provided)), 'biến view dùng mà presenter không trả');
    }

    private function seedActorAndData(): \App\Models\User
    {
        $user = $this->userWithRole(Role::Admin->value, [
            'id' => 999800, 'name' => 'Nguoi Dung Canh', 'email' => 'nguoi.dung@example.test',
        ]);
        $now = '2026-09-01 08:00:00';
        DB::table('marketing_campaigns')->insert([
            'id' => 970001, 'name' => 'Chien dich FB Lead', 'platform' => 'Facebook', 'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('marketing_budgets')->insert([
            ['id' => 970011, 'platform' => 'Facebook', 'campaign_id' => 970001, 'month' => '2026-09-01', 'budget' => 10000000, 'actual_spent' => 2000000, 'created_by' => $user->id, 'created_at' => '2026-09-03 09:00:00', 'updated_at' => $now],
            ['id' => 970013, 'platform' => 'Facebook', 'campaign_id' => 970001, 'month' => '2026-08-01', 'budget' => 5000000, 'actual_spent' => 1000000, 'created_by' => $user->id, 'created_at' => '2026-08-20 09:00:00', 'updated_at' => $now],
        ]);
        DB::table('marketing_metrics')->insert([
            ['id' => 970021, 'platform' => 'Facebook', 'campaign_id' => 970001, 'date_from' => '2026-09-01', 'date_to' => '2026-09-05', 'spend' => 1500000, 'leads' => 30, 'reach' => 20000, 'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 970023, 'platform' => 'Facebook', 'campaign_id' => null, 'date_from' => '2026-08-28', 'date_to' => '2026-09-02', 'spend' => 700000, 'leads' => 7, 'reach' => 3000, 'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 970022, 'platform' => 'Google', 'campaign_id' => null, 'date_from' => '2026-09-02', 'date_to' => '2026-09-07', 'spend' => 3000000, 'leads' => 12, 'reach' => 5000, 'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now],
        ]);

        return $user;
    }
}
