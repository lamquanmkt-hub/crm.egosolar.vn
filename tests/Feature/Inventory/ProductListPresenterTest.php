<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\DTOs\Inventory\ProductListRow;
use App\Models\Inventory\Catalog\Product;
use App\Models\Media\MediaFile;
use App\Models\Media\MediaRelation;
use App\View\Presenters\Inventory\ProductListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

/**
 * {@see ProductListPresenter} thay 12 khối `@php` của ba view danh sách sản phẩm (2026-09-08).
 * Dòng dựng trong bộ nhớ; chỉ phần quyền cần user thật trong DB.
 */
final class ProductListPresenterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_trang_tong_gia_theo_bang_gia_gia_von_lo_va_so_luong_theo_kho(): void
    {
        $product = (new Product)->forceFill([
            'id' => 7, 'name' => 'Pin', 'price' => 100, 'price_retail' => 120, 'vat_percent' => 8, 'quantity' => 9,
            'stocks_sum_qty' => '4', 'warehouse_qty' => '2', 'lot_cost_before_vat' => 90, 'price_agent' => 95,
            'stock_lot_id' => 3, 'stock_lot_code' => 'LOT-1', 'stock_lot_name' => null, 'note' => null, 'notes' => null, 'description' => 'Mô tả',
        ])->setRelation('prices', collect([(object) ['price_tier_id' => 5, 'price' => '150']]));
        $product->setRelation('mainImage', (new MediaRelation)->setRelation('media', (new MediaFile)->forceFill(['file_path' => 'products/x.jpg'])));
        $presenter = new ProductListPresenter;

        $data = $presenter->index(null, $this->paginate([$product]), ['price_tier_id' => '5', 'warehouse_id' => '1']);
        $row = $data['rows'][0];

        $this->assertInstanceOf(ProductListRow::class, $row);
        $this->assertSame([150.0, 162.0, 8.0, 90.0, 2, 'LOT-1', 'Mô tả'], [$row->sellBefore, round($row->sellAfter, 6), $row->vatPercent, $row->costBefore, $row->displayQty, $row->lotTitle, $row->note], 'giá bảng giá, VAT, giá vốn lô, tồn kho đang chọn, mã lô khi không có tên, mô tả khi không có ghi chú');
        $this->assertStringEndsWith('/storage/products/x.jpg', (string) $row->imageUrl);
        $this->assertSame([false, false, 10, '5', '1', null, '1'], [$data['canManageProducts'], $data['canViewCost'], $data['colspan'], $data['selectedPriceTier'], $data['selectedWarehouse'], $data['selectedCategory'], $data['currentCompanyId']], 'không user → không quyền; colspan 9 + hành động');

        $row = $presenter->index(null, $this->paginate([$product]), [])['rows'][0];
        $this->assertSame([120.0, 129.6, 4], [$row->sellBefore, round($row->sellAfter, 6), $row->displayQty], 'không chọn bảng giá → giá lẻ; không chọn kho → tổng tồn');

        $row = $presenter->index(null, $this->paginate([$product]), ['price_tier_id' => '99'])['rows'][0];
        $this->assertSame(0.0, $row->sellBefore, 'bảng giá không có dòng → 0');

        $this->assertSame([], $presenter->index(null, collect([$product]), [])['rows'], 'không phải paginator → không có dòng');
    }

    public function test_trang_dau_vao_gia_von_thanh_tien_va_ton_theo_kho(): void
    {
        $product = (new Product)->forceFill(['id' => 8, 'price_agent' => '1000', 'price_agent_vat' => null, 'vat_percent' => 10, 'stocks_sum_qty' => 3, 'note' => 'Ghi chú'])
            ->setRelation('stocks', collect([
                (object) ['warehouse_id' => 1, 'qty' => 1, 'warehouse' => (object) ['name' => 'Kho A']],
                (object) ['warehouse_id' => 2, 'qty' => 0, 'warehouse' => (object) ['name' => 'Kho rỗng']],
                (object) ['warehouse_id' => 3, 'qty' => 2, 'warehouse' => null],
                (object) ['warehouse_id' => 1, 'qty' => 1.5, 'warehouse' => (object) ['name' => 'Kho A']],
            ]));

        $data = (new ProductListPresenter)->input(null, $this->paginate([$product]), [], '12', '3400.5');
        $row = $data['rows'][0];

        $this->assertSame([1000.0, 1100.0, 3, 3300.0, 'Ghi chú', null], [$row->costBefore, round($row->costAfter, 6), $row->displayQty, round($row->rowAmount, 6), $row->note, $row->imageUrl], 'giá vốn sau VAT tự tính khi không lưu sẵn');
        $this->assertSame([['name' => 'Kho A', 'qty' => 2.5], ['name' => 'Kho không tên', 'qty' => 2.0]], $row->warehouseStocks, 'gộp theo kho, bỏ kho tồn 0, giảm dần theo số lượng');
        $this->assertSame([12, 3400.5, 13], [$data['totalQtyAll'], $data['totalAmountAll'], $data['colspan']]);

        $row = (new ProductListPresenter)->input(null, $this->paginate([(new Product)->forceFill(['price_agent' => 1000, 'price_agent_vat' => 1080, 'vat_percent' => 10])]), [], null, null)['rows'][0];
        $this->assertSame([1080.0, 0, 0.0], [$row->costAfter, $row->displayQty, $row->rowAmount], 'giá vốn sau VAT lưu sẵn thắng công thức');
    }

    public function test_trang_dau_ra_gia_ban_theo_dong_bang_gia(): void
    {
        $product = (new Product)->forceFill(['price' => 100, 'price_retail' => 200, 'vat_percent' => 10, 'stocks_sum_qty' => '6', 'stock_lot_name' => 'Lô A'])
            ->setRelation('prices', collect([(object) ['price_tier_id' => 5, 'price' => 150, 'vat_percent' => 8, 'price_after_vat' => 170]]));
        $presenter = new ProductListPresenter;

        $row = $presenter->output(null, $this->paginate([$product]), ['price_tier_id' => 5])['rows'][0];
        $this->assertSame([150.0, 8.0, 170.0, 6, 'Lô A'], [$row->sellBefore, $row->vatPercent, $row->sellAfter, $row->displayQty, $row->lotTitle], 'dòng bảng giá: giá sau VAT lưu sẵn thắng');

        $row = $presenter->output(null, $this->paginate([$product]), [])['rows'][0];
        $this->assertSame([200.0, 10.0, 220.0], [$row->sellBefore, $row->vatPercent, round($row->sellAfter, 6)], 'không chọn bảng giá → giá lẻ + VAT sản phẩm');

        $unloaded = (new Product)->forceFill(['price_retail' => 200, 'vat_percent' => 10]);
        $row = $presenter->output(null, $this->paginate([$unloaded]), ['price_tier_id' => 5])['rows'][0];
        $this->assertSame(200.0, $row->sellBefore, 'chưa gắn quan hệ prices → rơi về giá lẻ');
        $this->assertSame(11, $presenter->output(null, null, [])['colspan']);
    }

    public function test_quyen_theo_policy_san_pham(): void
    {
        $presenter = new ProductListPresenter;
        $admin = $this->userWithRole('admin');
        $sales = $this->userWithRole('sales');

        $data = $presenter->index($admin, null, []);
        $this->assertSame([true, true, 11], [$data['canManageProducts'], $data['canViewCost'], $data['colspan']]);
        $this->assertSame(12, $presenter->output($admin, null, [])['colspan'], 'đầu ra thêm cột hành động theo quyền');

        $data = $presenter->index($sales, null, []);
        $this->assertSame([false, false, 10], [$data['canManageProducts'], $data['canViewCost'], $data['colspan']]);
    }

    /** @param  list<object>  $items */
    private function paginate(array $items): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, count($items), 20);
    }
}
