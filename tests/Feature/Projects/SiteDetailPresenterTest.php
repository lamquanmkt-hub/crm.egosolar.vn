<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\DTOs\Projects\SiteMaterialRow;
use App\Enums\Role;
use App\Models\Projects\Site;
use App\View\Presenters\Projects\SiteDetailFormat;
use App\View\Presenters\Projects\SiteDetailPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * {@see SiteDetailPresenter} thay 7 khối `@php` (290 dòng) của sites/show (2026-09-07).
 * Site và dòng vật tư dựng trong bộ nhớ; chỉ user cần DB (role/permission Spatie).
 */
final class SiteDetailPresenterTest extends TestCase
{
    use DatabaseTransactions;

    private SiteDetailPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new SiteDetailPresenter(new SiteDetailFormat);
    }

    public function test_quyen_xem_gia_von_va_tao_don_vat_tu_theo_role(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $technical = $this->userWithRole('technical');
        $accounting = $this->userWithRole('accounting');
        $financeViewer = $this->userWithPermissions(['finance.view']);

        $this->assertSame([true, true], $this->access($admin));
        $this->assertSame([false, true], $this->access($technical));
        $this->assertSame([true, false], $this->access($accounting));
        $this->assertSame([true, false], $this->access($financeViewer));
        $this->assertSame([false, false], $this->access(null));
    }

    public function test_phan_nhom_va_tach_ghi_chu_vat_tu(): void
    {
        $rows = collect([
            $this->material(998701, 998801, '[Thiết bị chính - Trong kho] Serial ABC', qty: 2, unit: '', productUnit: 'bộ', lineTotal: 44000000),
            $this->material(998701, null, '[Vật tư phụ - Ngoài kho] Ống nhựa | ĐVT: m | 20m loại tốt | thêm', qty: 20.5, unit: 'm', lineTotal: 1107000),
            $this->material(998702, null, 'Tấm pin ngoài | ĐVT: tấm', qty: 3),
            $this->material(998702, 998801, '', qty: 1, productUnit: 'bộ'),
            $this->material(998702, null, '[Thiết bị chính - Ngoài kho] Inverter ngoài'),
            $this->material(998702, 998801, '[Vật tư phụ - Trong kho] phụ kiện', qty: 4, unit: 'cái', lineTotal: 400000),
        ]);

        $data = $this->presenter->viewData(new Site, [], [], [], [], $rows, null);

        $main = $data['mainMaterials'];
        $sub = $data['subMaterials'];
        $this->assertContainsOnlyInstancesOf(SiteMaterialRow::class, $main);
        $this->assertSame(['Thiết bị chính - Trong kho', 'Thiết bị chính - Trong kho', 'Thiết bị chính - Ngoài kho'], $main->pluck('group')->all());
        $this->assertSame(['Vật tư phụ - Ngoài kho', 'Vật tư phụ - Ngoài kho', 'Vật tư phụ - Trong kho'], $sub->pluck('group')->all());
        $this->assertSame(['group-main-stock', 'group-main-stock', 'group-main-external'], $main->pluck('groupClass')->all());

        $this->assertSame(['Inverter 10kW', 'bộ', 'Serial ABC', 'INV-10'], [$main[0]->name, $main[0]->unit, $main[0]->note, $main[0]->sku]);
        $this->assertSame(['Inverter 10kW', 'bộ', '—'], [$main[1]->name, $main[1]->unit, $main[1]->note], 'ghi chú rỗng → —');
        $this->assertSame(['Inverter ngoài', '—', '—', false], [$main[2]->name, $main[2]->unit, $main[2]->note, $main[2]->inCatalog]);
        $this->assertSame(['Ống nhựa', 'm', '20m loại tốt | thêm'], [$sub[0]->name, $sub[0]->unit, $sub[0]->note], 'bỏ đoạn tên và ĐVT');
        $this->assertSame(['Tấm pin ngoài', 'tấm', '—'], [$sub[1]->name, $sub[1]->unit, $sub[1]->note], 'không ngoặc → ngoài kho, ĐVT từ ghi chú');
        $this->assertSame(['Inverter 10kW', 'cái', 'phụ kiện'], [$sub[2]->name, $sub[2]->unit, $sub[2]->note]);

        $this->assertSame(['rows' => 3, 'qty' => 3.0, 'cost' => 44000000.0, 'stock' => 2, 'external' => 1], $data['mainSummary']);
        $this->assertSame(['rows' => 3, 'qty' => 27.5, 'cost' => 1507000.0, 'stock' => 1, 'external' => 2], $data['subSummary']);
        $this->assertSame(45507000.0, $data['materialCost'], 'finance không cấp material_cost → tổng vật tư thực tế');
    }

    public function test_tai_chinh_tien_do_trang_thai_va_dot_thu(): void
    {
        $site = (new Site)->forceFill(['id' => 998601, 'status' => 'installing', 'contract_amount' => 500000000, 'labor_cost' => 20000000, 'transport_cost' => 5000000, 'other_cost' => 1000000, 'system_kwp' => 10.5, 'system_kw_ac' => 10]);
        $terms = [
            ['id' => 1, 'name' => 'Đợt 1', 'amount' => '150000000.00', 'paid_amount' => '150000000.00', 'computed_status' => 'paid'],
            ['id' => 2, 'name' => 'Đợt 2', 'amount' => '200000000.00', 'paid_amount' => '150000000.00', 'remaining_amount' => '50000000.00', 'computed_status' => 'partial'],
            ['id' => 3, 'name' => 'Đợt 3', 'amount' => '150000000.00', 'paid_amount' => 0, 'computed_status' => 'pending'],
            ['name' => 'Không id', 'amount' => 0],
        ];
        $finance = ['contract_amount' => 500000000, 'received_amount' => 300000000, 'material_cost' => 100000000];
        $receipts = [['id' => 11, 'site_payment_term_id' => 1, 'amount' => '150000000.00', 'payment_method' => 'bank_transfer', 'paid_at' => '2026-08-01', 'note' => null], ['amount' => 5]];

        $data = $this->presenter->viewData($site, $terms, $finance, $receipts, ['battery_kwh' => 5, 'device_count' => 3], collect(), null);

        $this->assertSame([500000000.0, 300000000.0, 200000000.0], [$data['contractAmount'], $data['receivedAmount'], $data['remainingReceivable']]);
        $this->assertSame(126000000.0, $data['totalCost']);
        $this->assertSame(374000000.0, $data['grossProfit']);
        $this->assertSame(60.0, $data['paidPercent']);
        $this->assertSame(25.2, $data['costPercent']);
        $this->assertSame(['Đang lắp đặt', 'status-warning', 'bi-tools'], array_values($data['statusInfo']));
        $this->assertSame(['Còn công nợ', 'debt-danger'], [$data['debtLabel'], $data['debtClass']]);
        $this->assertSame([10.5, 10.5, 10.0, 5.0, 3], [$data['systemKwp'], $data['pvKwp'], $data['inverterKw'], $data['batteryKwh'], $data['deviceCount']]);

        $this->assertSame(500000000.0, $data['totalTermAmount']);
        $this->assertSame(150000000.0, $data['paymentTerms'][0]['amount'], 'chuỗi thô của DB phải thành số');
        $this->assertSame([0.0, 'Đã thu đủ', 'bg-success'], [$data['paymentTerms'][0]['remaining_amount'], $data['paymentTerms'][0]['status_label'], $data['paymentTerms'][0]['status_class']]);
        $this->assertSame([50000000.0, 'Thu một phần', 'bg-warning text-dark'], [$data['paymentTerms'][1]['remaining_amount'], $data['paymentTerms'][1]['status_label'], $data['paymentTerms'][1]['status_class']]);
        $this->assertSame([150000000.0, 'Chưa thu', 'bg-secondary'], [$data['paymentTerms'][2]['remaining_amount'], $data['paymentTerms'][2]['status_label'], $data['paymentTerms'][2]['status_class']]);

        $this->assertSame([['id' => 1, 'name' => 'Đợt 1', 'amount' => 150000000.0], ['id' => 2, 'name' => 'Đợt 2', 'amount' => 200000000.0], ['id' => 3, 'name' => 'Đợt 3', 'amount' => 150000000.0]], $data['paymentEditorTerms']->all(), 'bỏ đợt không id');
        $this->assertSame([['id' => 11, 'term_id' => 1, 'amount' => 150000000.0, 'payment_method' => 'bank_transfer', 'receipt_date' => null, 'note' => null]], $data['paymentEditorRows']->all());

        $data = $this->presenter->viewData(new Site, [], [], [], [], collect(), null);
        $this->assertSame([0, 0, 'Đã thu đủ', 'debt-ok'], [$data['paidPercent'], $data['costPercent'], $data['debtLabel'], $data['debtClass']]);
        $this->assertSame(['—', 'status-muted', 'bi-dot'], array_values($data['statusInfo']));
    }

    /** @return array{0: bool, 1: bool} [canSeeCost, canCreateMaterialRequest] */
    private function access(?\App\Models\User $user): array
    {
        $data = $this->presenter->viewData(new Site, [], [], [], [], collect(), $user);

        return [$data['canSeeCost'], $data['canCreateMaterialRequest']];
    }

    private function material(int $requestId, ?int $productId, string $note, float $qty = 0, string $unit = '', ?string $productUnit = null, float $lineTotal = 0): object
    {
        return (object) [
            'material_request_id' => $requestId, 'request_created_at' => '2026-08-10 09:00:00', 'product_id' => $productId,
            'product_name' => $productId ? 'Inverter 10kW' : '', 'product_sku' => $productId ? 'INV-10' : '', 'product_unit' => $productUnit ?? '',
            'qty' => $qty, 'unit' => $unit, 'unit_cost' => 0, 'vat_percent' => 0, 'line_total' => $lineTotal, 'note' => $note,
        ];
    }
}
