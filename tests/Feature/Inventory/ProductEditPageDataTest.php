<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Role;
use App\Models\Inventory\Catalog\Product;
use App\Services\Inventory\ProductEdit\NumberInputFormatter;
use App\Services\Inventory\ProductEdit\ProductEditPageData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Canh giữ phần dữ liệu của form sửa sản phẩm.
 *
 * Trước 2026-09-03 toàn bộ phần này nằm trong hai khối `@php` (275 + 81 dòng)
 * ngay trong view, kèm 9 lệnh `DB::`.
 *
 * Trong đó có một lỗi lãng phí: HAI khối cùng chạy đúng một câu truy vấn serial,
 * chỉ khác cách gom nhóm — một để đổ JSON cho JavaScript, một để render phía
 * server. Nay truy vấn một lần rồi lấy hai hình dạng từ cùng kết quả.
 */
final class ProductEditPageDataTest extends TestCase
{
    use DatabaseTransactions;

    private const PRODUCT_ID = 972001;

    /** Đúng bộ biến mà template cần — thiếu một cái là view vỡ lúc chạy. */
    public function test_tra_du_hop_dong_bien_cho_template(): void
    {
        $data = app(ProductEditPageData::class)->build($this->product(), collect(), [], []);

        foreach ([
            'companyOptions', 'warehouseOptions', 'groupRows', 'initialLines',
            'currentStockQty', 'stockHistoryRows', 'serialRowsByProduct',
            'egoSerialRowsByProduct', 'egoSerialProductIds', 'egoSerialProducts',
            'egoSerialWarehouses', 'egoStateLabels', 'savedTierPrices', 'fmtInput',
        ] as $key) {
            $this->assertArrayHasKey($key, $data, "Thiếu biến {$key}");
        }
    }

    /** Sản phẩm chưa có lô nào vẫn phải có một dòng để nhập. */
    public function test_san_pham_chua_co_lo_van_co_mot_dong_nhap(): void
    {
        $data = app(ProductEditPageData::class)->build($this->product(), collect(), [], []);

        $this->assertCount(1, $data['initialLines']);
        $this->assertSame(self::PRODUCT_ID, $data['initialLines'][0]['product_id']);
        $this->assertSame(0, $data['initialLines'][0]['stock_lot_id']);
    }

    /**
     * Truy vấn serial chỉ được chạy MỘT lần cho mỗi lần dựng dữ liệu.
     *
     * Đây là bản canh cho lỗi cũ: view chạy đúng câu này hai lần.
     */
    public function test_truy_van_serial_chi_chay_mot_lan(): void
    {
        $product = $this->product();

        DB::enableQueryLog();
        app(ProductEditPageData::class)->build($product, collect(), [], []);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $serial = array_filter($log, fn ($q) => str_contains($q['query'], 'crm_serial_units'));

        $this->assertLessThanOrEqual(1, count($serial), 'Truy vấn serial bị lặp');
    }

    /** Định dạng số bỏ phần thập phân thừa — thay cho closure từng khai trong view. */
    public function test_dinh_dang_so_cho_o_nhap(): void
    {
        $format = new NumberInputFormatter;

        $this->assertSame('', $format(null));
        $this->assertSame('', $format(''));
        $this->assertSame('1000', $format(1000));
        $this->assertSame('1000', $format(1000.0000001));
        $this->assertSame('10.5', $format(10.5));
        $this->assertSame('10.25', $format(10.25));
    }

    /** View không còn tự truy cập dữ liệu. */
    public function test_view_khong_con_lenh_db(): void
    {
        $source = (string) file_get_contents(resource_path('views/products/edit.blade.php'));

        $this->assertStringNotContainsString('DB::', $source);
        $this->assertStringNotContainsString('use Illuminate\Support\Facades\DB;', $source);
    }

    /** Trang vẫn mở được. */
    public function test_trang_render_duoc(): void
    {
        $user = $this->userWithRole(Role::Admin->value);
        foreach (['page.products', 'product.view', 'products.manage'] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }
        $this->product();

        $this->actingAs($user)->get('/products/'.self::PRODUCT_ID.'/edit')->assertOk();
    }

    private function product(): Product
    {
        DB::table('crm_product_catalog')->insertOrIgnore([
            'id' => self::PRODUCT_ID,
            'name' => 'San pham canh test',
            'sku' => 'SP-CANH',
            'unit' => 'cai',
            'price' => 1000000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Product::findOrFail(self::PRODUCT_ID);
    }
}
