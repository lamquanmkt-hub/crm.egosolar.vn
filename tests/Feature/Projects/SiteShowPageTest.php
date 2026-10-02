<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\DTOs\Projects\SiteMaterialRow;
use App\Enums\Role;
use App\View\Presenters\Projects\SiteDetailPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Trang chi tiết công trình sau khi dời 7 khối `@php` sang SiteDetailPresenter (2026-09-07). */
final class SiteShowPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/sites/show.blade.php';

    /** Biến view không do presenter cấp: composer SitePaymentEditorService, vòng lặp, Blade. */
    private const VIEW_VARIABLES_NOT_FROM_PRESENTER = ['egoFixPayments', 'egoFixTermOptions', 'egoFixSiteId', 'i', 'term', 'receipt', 'item', 'errors', 'error', 'e', 'loop', 'slot', 'attributes', 'component'];

    public function test_trang_in_gia_tri_da_tinh_theo_vai_tro(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $technical = $this->userWithRole('technical');
        $this->seedSite($admin->id);

        $html = $this->actingAs($admin)->get('/cong-trinh/998601')->assertOk()->getContent();
        $this->assertStringContainsString('don-vat-tu/create?site_id=998601', $html, 'admin tạo được đơn vật tư');
        $this->assertStringContainsString('Tổng giá vốn', $html, 'admin thấy giá vốn');
        $this->assertStringContainsString('group-sub-external">Vật tư phụ - Ngoài kho', $html);
        $this->assertStringContainsString('<div class="actual-name">Ống nhựa</div>', $html);
        $this->assertStringContainsString('20m loại tốt | thêm', $html);
        $this->assertStringContainsString('20,50', $html, 'số lượng lẻ hai chữ số');
        $this->assertStringContainsString('Thu một phần', $html);
        $this->assertStringContainsString('Chuyển khoản', $html);
        $this->assertStringContainsString('Còn công nợ', $html);
        $this->assertStringContainsString('Đang lắp đặt', $html);
        $this->assertMatchesRegularExpression('/data-amount="150000000"/', $html, 'số tiền đợt thu là số, không phải chuỗi "150000000.00" của DB');

        $html = $this->actingAs($technical)->get('/cong-trinh/998601')->assertOk()->getContent();
        $this->assertStringContainsString('don-vat-tu/create?site_id=998601', $html);
        $this->assertStringNotContainsString('Tổng giá vốn', $html, 'kỹ thuật không thấy giá vốn');
    }

    /** View chỉ in: không `@php`; mọi biến nó đọc do presenter/composer cấp; `$item->x` là thuộc tính thật. */
    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $used = array_values(array_unique(array_diff($m[1], self::VIEW_VARIABLES_NOT_FROM_PRESENTER)));
        $provided = array_keys(app(SiteDetailPresenter::class)->viewData(new \App\Models\Projects\Site, [], [], [], [], collect(), null));
        $this->assertSame([], array_values(array_diff($used, $provided)), 'biến view dùng mà presenter không trả');

        preg_match_all('/\$item->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(SiteMaterialRow::class))->getProperties());
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), 'view đọc thuộc tính vật tư không có');
    }

    private function seedSite(int $creatorId): void
    {
        $now = '2026-09-01 08:00:00';
        DB::table('companies')->insert(['id' => 998501, 'code' => 'EGOT998', 'name' => 'EGO Test', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('crm_warehouses')->insert(['id' => 998401, 'company_id' => 998501, 'name' => 'Kho canh test']);
        DB::table('crm_product_catalog')->insert(['id' => 998801, 'name' => 'Inverter 10kW', 'sku' => 'INV-10', 'unit' => 'bộ', 'company_id' => 998501, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sites')->insert([
            'id' => 998601, 'name' => 'Công trình canh test', 'company_id' => 998501, 'status' => 'installing', 'contract_amount' => 500000000,
            'labor_cost' => 20000000, 'transport_cost' => 5000000, 'other_cost' => 1000000, 'created_by' => $creatorId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('site_payment_terms')->insert([
            ['id' => 998611, 'site_id' => 998601, 'name' => 'Đợt 1 - Đặt cọc', 'percent' => 30, 'amount' => 150000000, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998612, 'site_id' => 998601, 'name' => 'Đợt 2 - Triển khai', 'percent' => 40, 'amount' => 200000000, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('receipts')->insert([
            ['code' => 'PT-998601-0', 'receipt_date' => '2026-08-01', 'payer_name' => 'Khách', 'site_id' => 998601, 'site_payment_term_id' => 998611, 'amount' => 150000000, 'payment_method' => 'bank_transfer', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PT-998601-1', 'receipt_date' => '2026-08-20', 'payer_name' => 'Khách', 'site_id' => 998601, 'site_payment_term_id' => 998612, 'amount' => 100000000, 'payment_method' => 'cash', 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('material_requests')->insert(['id' => 998701, 'site_id' => 998601, 'warehouse_id' => 998401, 'created_by' => $creatorId, 'status' => 'EXPORTED', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('material_request_items')->insert([
            ['id' => 998711, 'material_request_id' => 998701, 'product_id' => 998801, 'warehouse_id' => 998401, 'qty' => 2, 'unit' => '', 'unit_cost' => 20000000, 'vat_percent' => 10, 'line_total' => 44000000, 'note' => '[Thiết bị chính - Trong kho] Serial ABC', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998712, 'material_request_id' => 998701, 'product_id' => null, 'warehouse_id' => 998401, 'qty' => 20.5, 'unit' => 'm', 'unit_cost' => 50000, 'vat_percent' => 8, 'line_total' => 1107000, 'note' => '[Vật tư phụ - Ngoài kho] Ống nhựa | ĐVT: m | 20m loại tốt | thêm', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
