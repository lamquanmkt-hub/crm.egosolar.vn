<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\DTOs\Projects\SiteListRow;
use App\View\Presenters\Projects\SiteListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Trang `sites/index` sau khi dời 3 khối `@php` sang SiteListPresenter (2026-09-28).
 *
 * Guard hai chiều: view chỉ in, mọi biến do controller/presenter cấp, `$row->x` là thuộc tính DTO
 * thật; và trang thật in đúng số theo quyền.
 */
final class SiteListPageTest extends TestCase
{
    use DatabaseTransactions;

    /** Khoá view() do controller cấp (không qua presenter). */
    private const CONTROLLER_KEYS = [
        'egoSiteTotals', 'projectType', 'pageTitle', 'pageSubtitle', 'projectLabel',
        'projectIndexUrl', 'projectCreateUrl', 'canSeeCost',
        'q', 'status', 'companyFilter', 'installedFrom', 'installedTo', 'completedFrom', 'completedTo',
    ];

    /** Do view composer EgoDefaultCompany cấp. */
    private const COMPOSER_KEYS = ['egoSiteCompanyOptions', 'egoSiteCompanyMap'];

    private const LOOP_AND_BLADE_VARIABLES = [
        'row', 'k', 'oc', 'company', 'site', 'loop', 'errors', 'slot', 'attributes', 'component',
    ];

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(resource_path('views/sites/index.blade.php'));

        $this->assertStringNotContainsString('@php', $source, 'view còn khối @php');
        $this->assertStringNotContainsString('auth()', $source, 'view còn tự hỏi quyền');
        $this->assertStringNotContainsString('request(', $source, 'view còn tự đọc request');
        $this->assertStringNotContainsString('SchemaCache', $source, 'view còn tự tra lược đồ');
        $this->assertStringNotContainsString('Carbon::', $source, 'view còn tự parse ngày');

        // Trang đã chuyển XONG: hết <style>, hết lớp Bootstrap, hết lớp CSS riêng, hết Bootstrap JS.
        $this->assertStringNotContainsString('<style', $source, 'view có lại khối <style>');
        $this->assertStringNotContainsString('data-bs-', $source, 'view dùng lại Bootstrap JS');
        foreach (['ego-card', 'shadow-ego', 'pill-soft', 'pill-date', 'kpi-icon', 'si-input',
            'btn-ego', 'btn-action', 'ego-table-modern', 'finance-cell', 'empty-icon'] as $lop) {
            $this->assertStringNotContainsString($lop, $source, "view dùng lại lớp CSS riêng: {$lop}");
        }
        foreach (['class="row', 'col-lg-', 'col-md-', 'table-responsive', 'table-light', 'fs-5'] as $lop) {
            $this->assertStringNotContainsString($lop, $source, "view dùng lại lớp Bootstrap: {$lop}");
        }

        // `!important` chỉ còn hai chỗ CÓ SẴN từ trước (màu chữ ô rỗng phải thắng luật ô của bảng).
        // Mọi cách khác đã thay bằng cơ chế sạch hơn: biến CSS (--border), `size="none"`,
        // `flex-basis` thay vì giành `width`, và thứ tự tệp CSS cho biến thể `max-[992px]`.
        $this->assertSame(2, preg_match_all('/tw:[^"\s]+!/', $source),
            'thêm `!` mới — cân nhắc biến CSS / size="none" / đổi thuộc tính trước khi dùng `!`');

        $data = (new SiteListPresenter)->viewData(
            new LengthAwarePaginator([], 0, 20, 1),
            ['received' => [], 'materialCost' => [], 'paymentCost' => [], 'requestCost' => []],
        );

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(
            self::CONTROLLER_KEYS,
            self::COMPOSER_KEYS,
            self::LOOP_AND_BLADE_VARIABLES,
            array_keys($data),
        );

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');

        preg_match_all('/\$row->([a-zA-Z]+)/', $source, $hit);
        $props = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass(SiteListRow::class))->getProperties()
        );
        $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
            'view đọc thuộc tính không có của $row');
    }

    public function test_trang_that_in_dung_so_va_an_loi_nhuan_theo_quyen(): void
    {
        $admin = $this->userWithRole('admin');
        $sales = $this->userWithRole('sales');

        // `created_by` = người kinh doanh: SiteController lọc `created_by = auth()->id()` cho vai
        // sales-only (egoCurrentUserIsSalesOnly). Không đặt thì bảng của sales rỗng và phép so
        // "sales vẫn thấy công trình" thành vô nghĩa.
        $now = now()->format('Y-m-d H:i:s');
        DB::table('sites')->insert([
            'created_by' => $sales->id,
            'name' => 'Công trình Guard', 'status' => 'installing',
            'contract_amount' => 100_000_000, 'labor_cost' => 5_000_000,
            'transport_cost' => 0, 'other_cost' => 0,
            'system_kwp' => '10.50', 'system_kw_ac' => '8.00',
            'installed_at' => '2026-03-01', 'warranty_to' => '2031-03-01',
            'technician_name' => 'Anh Ba, Chị Năm',
            'company_id' => (int) config('ego.default_company_id'),
            'address' => 'Số 1 Đường Thử', 'created_at' => $now, 'updated_at' => $now,
        ]);

        $html = (string) $this->actingAs($admin)->get('/cong-trinh')->assertOk()->getContent();

        $this->assertStringContainsString('Công trình Guard', $html);
        $this->assertStringContainsString('100.000.000 đ', $html, 'doanh thu theo quy ước Việt Nam');
        $this->assertStringContainsString('10.5', $html, 'kWp cắt số 0 vô nghĩa');
        $this->assertStringContainsString('01/03/2026', $html, 'ngày lắp đặt d/m/Y');
        $this->assertStringContainsString('Đang lắp đặt', $html, 'nhãn trạng thái');
        $this->assertStringContainsString('Anh Ba', $html);
        $this->assertStringContainsString('Chị Năm', $html, 'tên người phụ trách đã tách');
        $this->assertStringContainsString('Lợi nhuận tạm tính', $html, 'admin thấy ô lợi nhuận');

        // 100tr hợp đồng - 5tr nhân công = 95tr lợi nhuận tạm tính.
        $this->assertStringContainsString('95.000.000 đ', $html);

        $htmlSales = (string) $this->actingAs($sales)->get('/cong-trinh')->assertOk()->getContent();

        $this->assertStringNotContainsString('Lợi nhuận tạm tính', $htmlSales,
            'nhân viên kinh doanh KHÔNG được thấy lợi nhuận');
        $this->assertStringContainsString('Công trình Guard', $htmlSales, 'nhưng vẫn thấy công trình');
    }
}
