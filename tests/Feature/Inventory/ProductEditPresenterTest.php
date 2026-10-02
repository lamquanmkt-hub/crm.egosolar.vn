<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\DTOs\Inventory\ProductSerialGroup;
use App\DTOs\Inventory\ProductSerialLine;
use App\DTOs\Inventory\StockMovementLogRow;
use App\DTOs\Inventory\TierPriceInputRow;
use App\Services\Inventory\ProductEdit\NumberInputFormatter;
use App\Services\Inventory\ProductEdit\ProductEditPageData;
use App\View\Presenters\Inventory\ProductEditPresenter;
use Tests\TestCase;

/**
 * {@see ProductEditPresenter} thay 4 khối `@php` của view `products.edit` (2026-09-08): nhóm serial,
 * phân loại nguồn lịch sử xuất nhập, ô nhập giá theo bảng giá. Dữ liệu dựng trong bộ nhớ, không cần DB.
 */
final class ProductEditPresenterTest extends TestCase
{
    private const VIEW = 'resources/views/products/edit.blade.php';

    /** Khoá view() của ProductController@edit (không do presenter cấp). */
    private const CONTROLLER_KEYS = ['product', 'companies', 'categories', 'brands', 'companyWarehouses', 'priceTiers', 'formData', 'productStockLots'];

    /** Khoá do {@see ProductEditPageData::build()} cấp. */
    private const PAGE_DATA_KEYS = [
        'companyOptions', 'warehouseOptions', 'groupRows', 'initialLines', 'currentStockQty', 'stockHistoryRows', 'serialRowsByProduct',
        'egoSerialRowsByProduct', 'egoSerialProductIds', 'egoSerialProducts', 'egoSerialWarehouses', 'egoStateLabels', 'savedTierPrices', 'fmtInput',
    ];

    private const LOOP_AND_BLADE_VARIABLES = ['group', 'line', 'row', 'tierRow', 'wh', 'cat', 'brand', 'errors', 'error', 'e', 'loop', 'slot', 'attributes', 'component'];

    public function test_nhom_serial_theo_san_pham_va_khoa_serial_da_ban(): void
    {
        $data = $this->presenter()->viewData(
            (object) ['id' => 1],
            collect([7, 8]),
            collect([7 => (object) ['id' => 7, 'name' => 'Pin', 'sku' => 'PV-7']]),
            collect([7 => collect([(object) ['id' => 1, 'code' => 'SN1', 'state' => 'in_stock'], (object) ['id' => 2, 'code' => 'SN2', 'state' => 'sold'], (object) ['id' => 3, 'code' => 'SN3', 'state' => null]])]),
            [], [], collect(), [],
        );
        $groups = $data['serialGroups'];

        $this->assertContainsOnlyInstancesOf(ProductSerialGroup::class, $groups);
        $this->assertSame([7, 'Pin', 3], [$groups[0]->productId, $groups[0]->product->name, count($groups[0]->lines)]);
        $this->assertContainsOnlyInstancesOf(ProductSerialLine::class, $groups[0]->lines);
        $this->assertSame([['in_stock', false], ['sold', true], ['unknown', false]], array_map(fn (ProductSerialLine $l) => [$l->state, $l->locked], $groups[0]->lines), 'đã bán → khoá; không trạng thái → unknown');
        $this->assertSame([8, null, []], [$groups[1]->productId, $groups[1]->product, $groups[1]->lines], 'sản phẩm không còn trong catalog và chưa có serial');
        $this->assertSame([[], []], [$data['stockLogRows'], $data['tierPriceRows']]);
    }

    public function test_phan_loai_nguon_lich_su_xuat_nhap(): void
    {
        // Bản ghi có đúng các cột controller select (order_code luôn có, có thể null)
        $log = fn (array $v) => (object) ($v + ['order_code' => null, 'site_name' => null, 'reference_id' => null, 'qty_before_safe' => null, 'qty_after_safe' => null, 'created_at' => null, 'note_safe' => null, 'reference_type_safe' => null]);
        $logs = [
            $log(['change_qty' => -1, 'reason' => 'Xuất kho', 'order_code' => 'DH-1', 'qty_before_safe' => 5, 'qty_after_safe' => 4, 'created_at' => '2026-09-01 10:05:00', 'note_safe' => 'Giao khách']),
            $log(['change_qty' => -2, 'reason' => 'x', 'reference_type_safe' => 'Order', 'reference_id' => 9]),
            $log(['change_qty' => -3, 'reason' => 'Xuất vật tư', 'site_name' => 'CT A', 'created_at' => '']),
            $log(['change_qty' => -1, 'reason' => 'Đơn vật tư', 'reference_id' => 777]),
            $log(['change_qty' => 3, 'reason' => 'NHẬP TAY']),
            $log(['change_qty' => -2, 'reason' => 'adjust', 'note_safe' => 'nhập tay sai']),
            $log(['change_qty' => 1, 'reason' => 'Hàng trả']),
            $log(['change_qty' => -1, 'reason' => '']),
            $log(['change_qty' => null, 'reason' => null]),
        ];

        $rows = $this->presenter()->viewData((object) [], collect(), collect(), collect(), $logs, [], collect(), [])['stockLogRows'];

        $this->assertContainsOnlyInstancesOf(StockMovementLogRow::class, $rows);
        $this->assertSame(
            [['Đơn hàng DH-1', 'is-order', 'bi-receipt-cutoff'], ['Đơn hàng #9', 'is-order', 'bi-receipt-cutoff'], ['Công trình: CT A', 'is-site', 'bi-kanban'], ['Đơn vật tư #777', 'is-site', 'bi-kanban'],
                ['Nhập kho / nhập tay', 'is-other', 'bi-pencil-square'], ['Điều chỉnh tay', 'is-other', 'bi-pencil-square'], ['Nhập kho', 'is-other', 'bi-box-arrow-in-down'], ['Xuất kho / Điều chỉnh', 'is-other', 'bi-arrow-left-right'], ['Nhập kho', 'is-other', 'bi-box-arrow-in-down']],
            array_map(fn (StockMovementLogRow $r) => [$r->sourceLabel, $r->sourceClass, $r->sourceIcon], $rows),
            'đơn hàng (mã hoặc reference_type) → công trình/vật tư → nhập tay (kể cả ghi chú) → khác theo dấu',
        );
        $this->assertSame(['-1', '5', '4', '01/09/2026 10:05', 'Giao khách'], [$rows[0]->changeText, $rows[0]->beforeText, $rows[0]->afterText, $rows[0]->createdAtText, $rows[0]->noteText]);
        $this->assertSame(['—', '—', '—', 'x'], [$rows[1]->beforeText, $rows[1]->afterText, $rows[1]->createdAtText, $rows[1]->noteText], 'trống → gạch; không ghi chú → lý do');
        $this->assertSame(['+3', '—', 0, '0'], [$rows[4]->changeText, $rows[7]->noteText, $rows[8]->changeQty, $rows[8]->changeText], 'dấu + chỉ khi nhập dương; bản ghi rỗng → 0 không dấu');
    }

    public function test_o_nhap_gia_theo_bang_gia_old_input_thang_gia_da_luu(): void
    {
        $tiers = [(object) ['id' => 1, 'name' => 'Đại lý'], (object) ['id' => 2, 'name' => 'Công trình'], (object) ['id' => 3, 'name' => 'Mới']];
        $saved = collect([1 => ['before_vat' => '1200000.50', 'vat_percent' => 8], 2 => ['price' => 1100000, 'vat_percent' => null]]);
        $product = (object) ['vat_percent' => 10];

        $rows = $this->presenter()->viewData($product, collect(), collect(), collect(), [], $tiers, $saved, [])['tierPriceRows'];
        $this->assertContainsOnlyInstancesOf(TierPriceInputRow::class, $rows);
        $this->assertSame([['1200000.5', '8'], ['1100000', ''], ['', '10']], array_map(fn (TierPriceInputRow $r) => [$r->beforeVat, $r->vatPercent], $rows), 'before_vat rồi price; VAT null → rỗng; chưa lưu → VAT sản phẩm');

        $rows = $this->presenter()->viewData($product, collect(), collect(), collect(), [], $tiers, $saved, ['prices' => [1 => ['before_vat' => '999', 'vat_percent' => '3'], 3 => ['before_vat' => '']]])['tierPriceRows'];
        $this->assertSame([['999', '3'], ['1100000', ''], ['', '10']], array_map(fn (TierPriceInputRow $r) => [$r->beforeVat, $r->vatPercent], $rows), 'old input thắng từng ô');
        $this->assertSame('Công trình', $rows[1]->tier->name);
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::PAGE_DATA_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys(
            $this->presenter()->viewData((object) [], collect(), collect(), collect(), [], [], collect(), []),
        ));
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)), 'biến view dùng mà presenter/controller không cấp');

        foreach (['group' => ProductSerialGroup::class, 'line' => ProductSerialLine::class, 'row' => StockMovementLogRow::class, 'tierRow' => TierPriceInputRow::class] as $variable => $dto) {
            preg_match_all('/\$'.$variable.'->([a-zA-Z]+)/', $source, $m);
            $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass($dto))->getProperties());
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), "view đọc thuộc tính không có của \${$variable}");
        }
    }

    private function presenter(): ProductEditPresenter
    {
        return new ProductEditPresenter(new NumberInputFormatter);
    }
}
