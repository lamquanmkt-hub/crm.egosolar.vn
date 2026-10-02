<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Projects\Site;
use App\View\Presenters\Projects\SiteEditFormPresenter;
use Tests\TestCase;

/** {@see SiteEditFormPresenter} thay 6 khối `@php` của sites/edit (2026-09-07). Site dựng trong bộ nhớ. */
final class SiteEditFormPresenterTest extends TestCase
{
    private const META = ['label' => 'dự án Nhà xưởng', 'index_url' => '/du-an/nha-xuong'];

    private SiteEditFormPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new SiteEditFormPresenter;
    }

    public function test_khong_co_du_lieu_thi_dong_mac_dinh(): void
    {
        $data = $this->presenter->viewData(new Site, [], [], [], [], 'residential', self::META, 'phien-42');

        $this->assertSame([['type' => 'inverter', 'brand' => '', 'model' => '', 'serial' => '', 'power_kw' => '', 'capacity_kwh' => '', 'qty' => 1, 'warranty_to' => '']], $data['devicesRows']);
        $this->assertSame([['name' => '', 'unit' => '', 'qty' => 0]], $data['plannedRows']);
        $this->assertCount(3, $data['paymentTermRows']);
        $this->assertSame('Đợt 2 - Giao vật tư / triển khai', $data['paymentTermRows'][1]['name']);
        $this->assertSame(['', '', '', 0, '', '', []], [$data['installedAt'], $data['warrantyTo'], $data['contractSignedAt'], $data['contractAmount'], $data['financeNote'], $data['techRaw'], $data['techList']]);
        $this->assertSame(['', '', '', ''], [$data['st'], $data['stype'], $data['ph'], $data['stage']]);
        $this->assertSame('phien-42', $data['egoOldSiteCompanyId'], 'công trình chưa có công ty → công ty của phiên');
        $this->assertSame(['residential', 'dự án Nhà xưởng', '/du-an/nha-xuong'], [$data['projectType'], $data['projectLabel'], $data['projectIndexUrl']]);
    }

    public function test_du_lieu_da_luu_dien_san_va_dinh_dang_ngay(): void
    {
        $site = (new Site)->forceFill([
            'company_id' => 5, 'status' => 'installing', 'system_type' => 'hybrid', 'phase' => '3_phase', 'stage' => 'installation',
            'installed_at' => '2026-08-15', 'warranty_to' => '2031-08-15 00:00:00', 'contract_signed_at' => null, 'contract_amount' => 500000000, 'finance_note' => 'Ghi chú',
            'technician_name' => ' Kỹ A, Kỹ B; ;Kỹ C ',
        ]);
        $devices = [['brand' => 'Huawei', 'qty' => 1], ['type' => 'pv', 'brand' => 'Jinko', 'qty' => 20]];

        $data = $this->presenter->viewData($site, [], $devices, [['name' => 'Dây', 'unit' => 'm', 'qty' => '120']], [['id' => 1, 'name' => 'Đợt 1']], 'factory', self::META, '');

        $this->assertSame([['type' => 'inverter', 'brand' => 'Huawei', 'qty' => 1], ['type' => 'pv', 'brand' => 'Jinko', 'qty' => 20]], $data['devicesRows'], 'thiếu type → inverter');
        $this->assertSame([['name' => 'Dây', 'unit' => 'm', 'qty' => '120']], $data['plannedRows']);
        $this->assertSame([['id' => 1, 'name' => 'Đợt 1']], $data['paymentTermRows']);
        $this->assertSame(['2026-08-15', '2031-08-15', ''], [$data['installedAt'], $data['warrantyTo'], $data['contractSignedAt']]);
        $this->assertSame(['500000000.00', 'Ghi chú'], [$data['contractAmount'], $data['financeNote']], 'contract_amount cast decimal:2 → chuỗi, giữ như cũ');
        $this->assertSame(['Kỹ A', 'Kỹ B', 'Kỹ C'], $data['techList']);
        $this->assertSame(['installing', 'hybrid', '3_phase', 'installation'], [$data['st'], $data['stype'], $data['ph'], $data['stage']]);
        $this->assertSame(5, $data['egoOldSiteCompanyId']);
    }

    public function test_old_input_thang_du_lieu_da_luu_va_giu_chi_so(): void
    {
        $site = (new Site)->forceFill(['company_id' => 5, 'status' => 'installing', 'installed_at' => '2026-08-15', 'technician_name' => 'Kỹ A']);
        $old = [
            'company_id' => '7', 'status' => 'done', 'installed_at' => '2026-09-05', 'warranty_to' => '', 'technician_name' => ' Kỹ X ;; Kỹ Y ,',
            'devices' => [0 => ['type' => 'battery', 'brand' => 'BYD'], 2 => ['brand' => 'Không type']],
            'planned' => [1 => ['name' => 'Ống']],
            'payment_terms' => [],
        ];

        $data = $this->presenter->viewData($site, $old, [['brand' => 'Huawei']], [['name' => 'Dây']], [['name' => 'Đợt 1']], 'factory', self::META, '');

        $this->assertSame([0 => ['type' => 'battery', 'brand' => 'BYD'], 2 => ['type' => 'inverter', 'brand' => 'Không type']], $data['devicesRows'], 'giữ khoá 0 và 2 của old input');
        $this->assertSame([1 => ['name' => 'Ống']], $data['plannedRows']);
        $this->assertSame([['name' => 'Đợt 1']], $data['paymentTermRows'], 'old rỗng → dữ liệu đã lưu');
        $this->assertSame(['7', 'done', '2026-09-05', ''], [$data['egoOldSiteCompanyId'], $data['st'], $data['installedAt'], $data['warrantyTo']], 'old rỗng chuỗi vẫn thắng (Arr::get, không phải ??)');
        $this->assertSame(['Kỹ X', 'Kỹ Y'], $data['techList']);
    }
}
