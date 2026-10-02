<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\DTOs\Marketing\BudgetRow;
use App\DTOs\Marketing\CampaignCombinedRow;
use App\DTOs\Marketing\MetricRow;
use App\Models\Marketing\MarketingBudget;
use App\Models\Marketing\MarketingCampaign;
use App\Models\Marketing\MarketingMetric;
use App\View\Presenters\Marketing\MarketingBudgetPagePresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Trang `marketing/budget` sau khi dời 4 khối `@php` sang MarketingBudgetPagePresenter (2026-09-28).
 *
 * Guard hai chiều: view chỉ in (không `@php`, không Carbon, không `auth()`), mọi biến do
 * controller/presenter cấp, `$row->x` là thuộc tính DTO thật; và trang thật in đúng giá trị.
 */
final class MarketingBudgetPageTest extends TestCase
{
    use DatabaseTransactions;

    /** Khoá view() do controller cấp (không qua presenter). */
    private const CONTROLLER_KEYS = [
        'from', 'to', 'month', 'platform', 'campaign_id',
        'totalBudget', 'totalSpent',
        'sumSpend', 'sumLeads', 'sumReach', 'sumOrders', 'sumRevenue',
        'cpl', 'cpo', 'roas', 'campaigns', 'canManage',
    ];

    /** Biến vòng lặp `@forelse`/`@foreach` và biến Blade tự cấp. */
    private const LOOP_AND_BLADE_VARIABLES = [
        's', 'r', 'm', 'c', 'cc', 'p', 'loop', 'errors', 'slot', 'attributes', 'component',
    ];

    /**
     * Magic của Alpine — là JS trong thuộc tính `x-*`, KHÔNG phải biến Blade.
     *
     * Chúng cũng bắt đầu bằng `$` nên cùng một regex quét biến sẽ vớt phải; liệt kê tường minh
     * thay vì nới regex, để guard vẫn bắt được biến PHP thật sự bị thiếu.
     */
    private const ALPINE_MAGICS = [
        'dispatch', 'event', 'el', 'refs', 'store', 'watch', 'nextTick', 'root', 'data', 'id',
    ];

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(resource_path('views/marketing/budget.blade.php'));

        $this->assertStringNotContainsString('@php', $source, 'view còn khối @php');
        $this->assertStringNotContainsString('Carbon::', $source, 'view còn tự parse ngày');
        $this->assertStringNotContainsString('auth()', $source, 'view còn tự hỏi quyền');
        $this->assertStringNotContainsString('request()', $source, 'view còn tự đọc request');

        // Trang này đã chuyển XONG: không còn lớp Bootstrap nào và không còn phụ thuộc
        // bootstrap-compat.js. Hai khẳng định dưới chặn việc vô tình đưa chúng trở lại.
        foreach (['class="table', 'table-responsive', 'table-hover', 'table-light', 'nav-tabs'] as $lop) {
            $this->assertStringNotContainsString($lop, $source, "view dùng lại lớp Bootstrap: {$lop}");
        }
        $this->assertStringNotContainsString('data-bs-', $source, 'view dùng lại Bootstrap JS');

        $data = (new MarketingBudgetPagePresenter)->viewData(
            rows: new LengthAwarePaginator([], 0, 20),
            summary: [],
            metricRows: [],
            campaignCombined: [],
            rawFilters: [],
        );

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, self::ALPINE_MAGICS, array_keys($data));

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');

        // `$r->x`, `$m->x`, `$c->x` phải là thuộc tính DTO thật.
        foreach (['r' => BudgetRow::class, 'm' => MetricRow::class, 'cc' => CampaignCombinedRow::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z]+)/', $source, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien ({$dto})");
        }
    }

    public function test_trang_that_in_dung_so_va_cot_thao_tac_theo_quyen(): void
    {
        $thangNay = Carbon::now()->startOfMonth();
        $campaign = MarketingCampaign::create(['name' => 'Goodwe 5kw', 'platform' => 'Facebook']);

        MarketingBudget::create([
            'platform' => 'Facebook', 'campaign_id' => $campaign->id,
            'month' => $thangNay->toDateString(), 'budget' => 10_000_000, 'actual_spent' => 2_500_000,
        ]);

        MarketingMetric::create([
            'platform' => 'Facebook', 'campaign_id' => $campaign->id,
            'date_from' => $thangNay->copy()->addDays(2)->toDateString(),
            'date_to' => $thangNay->copy()->addDays(9)->toDateString(),
            'spend' => 1_200_000, 'reach' => 45_000, 'leads' => 30,
            'gender_breakdown' => ['nam' => 18, 'nu' => 12],
            'age_breakdown' => [],
            'region_breakdown' => ['HCM' => 0],
        ]);

        $admin = $this->userWithRole('admin');
        $html = (string) $this->actingAs($admin)->get('/marketing/budget')->assertOk()->getContent();

        $this->assertStringContainsString('10,000,000', $html, 'ngân sách');
        $this->assertStringContainsString('2,500,000', $html, 'đã chi');
        $this->assertStringContainsString('25%', $html, '% tiêu = 2,5tr / 10tr');
        $this->assertStringContainsString('Goodwe 5kw', $html, 'tên chiến dịch theo quan hệ');
        $this->assertStringContainsString('nam:18, nu:12', $html, 'breakdown có giá trị');
        $this->assertStringContainsString($thangNay->format('m/Y'), $html, 'tháng d/m/Y');
        $this->assertStringContainsString('/marketing/budget/', $html, 'admin có link sửa ngân sách');

        // Mảng rỗng và mảng toàn 0 đều phải ra '-', không phải chuỗi rỗng hay '0'.
        $this->assertStringContainsString('<td>-</td>', $html, 'breakdown rỗng/toàn 0 ra dấu -');

        $marketing = $this->userWithRole('marketing');
        $htmlNv = (string) $this->actingAs($marketing)->get('/marketing/budget')->assertOk()->getContent();

        // ⚠️ Không assert theo chữ "Thao tác": cột đó ở bảng CHỈ SỐ hiện cho mọi vai
        // (header nằm ngoài khối kiểm quyền, chỉ các nút bên trong mới theo quyền).
        // Bản đầu của test này assert như vậy và đỏ — test sai, không phải code sai.
        $this->assertStringNotContainsString('/marketing/budget/', $htmlNv,
            'nhân viên marketing không có link sửa/xoá ngân sách');
        $this->assertStringContainsString('10,000,000', $htmlNv, 'nhưng vẫn thấy số liệu');
    }

    public function test_khoi_tong_hop_chi_mo_san_khi_co_bo_loc(): void
    {
        $admin = $this->userWithRole('admin');

        $khongLoc = (string) $this->actingAs($admin)->get('/marketing/budget')->assertOk()->getContent();
        $coLoc = (string) $this->actingAs($admin)->get('/marketing/budget?platform=Facebook')->assertOk()->getContent();

        // Khối này nay là <x-ui.disclosure> chạy bằng Alpine: trạng thái ban đầu nằm trong x-data,
        // không còn là class `show` của Bootstrap.
        $this->assertStringContainsString('x-data="{ open: false }"', $khongLoc,
            'không lọc thì khối tổng hợp phải thu lại');
        $this->assertStringContainsString('x-data="{ open: true }"', $coLoc,
            'có lọc thì khối tổng hợp phải mở sẵn');

        // Và nút bấm phải gọi đúng TÊN mà khối đang nghe — sai tên thì bấm không ra gì, im lặng.
        $this->assertStringContainsString("\$dispatch('toggle-disclosure', 'budgetSummary')", $khongLoc);
        $this->assertStringContainsString(
            "x-on:toggle-disclosure.window=\"\$event.detail === 'budgetSummary'", $khongLoc);
    }
}
