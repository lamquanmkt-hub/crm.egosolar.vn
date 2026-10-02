<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\DTOs\Inventory\ProductListRow;
use App\View\Presenters\Inventory\ProductListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Ba view danh sách sản phẩm sau khi dời 12 khối `@php` sang ProductListPresenter (2026-09-08):
 * view chỉ in, mọi biến do presenter/controller cấp, thuộc tính DTO là thật; và trang thật in đúng số.
 */
final class ProductListPagesTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    /** Khoá view() của ProductController index/input/output (không do presenter cấp). */
    private const CONTROLLER_KEYS = ['products', 'companies', 'categories', 'warehouses', 'brands', 'priceTiers', 'companyMode', 'companyId', 'keyword', 'warehouseId', 'categoryId', 'brandId', 'priceTierId', 'totalQtyAll', 'totalAmountAll'];

    private const LOOP_AND_BLADE_VARIABLES = ['row', 'c', 'w', 'b', 't', 'ws', 'loop', 'errors', 'error', 'e', 'slot', 'attributes', 'component'];

    private int $actorId = 0;

    protected function seedActorId(): int
    {
        return $this->actorId;
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $presenter = new ProductListPresenter;
        $views = [
            'index' => $presenter->index(null, null, []),
            'index_input' => $presenter->input(null, null, [], null, null),
            'index_output' => $presenter->output(null, null, []),
        ];
        foreach ($views as $view => $data) {
            $source = (string) file_get_contents(base_path("resources/views/products/{$view}.blade.php"));
            $this->assertStringNotContainsString('@php', $source, $view);
            preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
            $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys($data));
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)), "{$view}: biến view dùng mà presenter/controller không cấp");

            preg_match_all('/\$row->([a-zA-Z]+)/', $source, $m);
            $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(ProductListRow::class))->getProperties());
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), "{$view}: view đọc thuộc tính không có của \$row");
        }
    }

    public function test_trang_that_in_gia_theo_quyen_va_bang_gia(): void
    {
        $admin = $this->userWithRole('admin');
        $sales = $this->userWithRole('sales');
        $this->actorId = (int) $admin->id;
        $this->seedShippableOrder();
        $now = now()->format('Y-m-d H:i:s');
        // Tổng tồn trang đầu vào chỉ cộng các kho gắn với công ty qua pivot
        DB::table('company_warehouse')->insert(['company_id' => config('ego.default_company_id'), 'warehouse_id' => $this->seed['warehouseId']]);
        $tierId = (int) DB::table('crm_price_tiers')->insertGetId(['code' => 'test_tier', 'name' => 'Bảng giá test', 'priority' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $productId = (int) DB::table('crm_product_catalog')->insertGetId([
            'name' => 'Inverter Test 5kW', 'sku' => 'INV-TEST-5', 'unit' => 'cái', 'price' => 9_000_000, 'price_retail' => 9_500_000,
            'price_agent' => 7_000_000, 'price_agent_vat' => 7_700_000, 'vat_percent' => 10, 'note' => 'Ghi chú test', 'quantity' => 0, 'is_active' => 1, 'company_id' => config('ego.default_company_id'),
        ]);
        DB::table('crm_product_prices')->insert(['product_id' => $productId, 'price_tier_id' => $tierId, 'price' => 8_000_000, 'vat_percent' => 10, 'price_after_vat' => 8_900_000, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('crm_product_stock')->insert(['product_id' => $productId, 'warehouse_id' => $this->seed['warehouseId'], 'company_id' => config('ego.default_company_id'), 'qty' => 2, 'last_updated' => $now]);
        // Trang tổng/đầu ra liệt kê theo lô nên sản phẩm cần một lô
        DB::table('crm_product_stock_lots')->insert([
            'product_id' => $productId, 'company_id' => config('ego.default_company_id'), 'warehouse_id' => $this->seed['warehouseId'], 'lot_code' => 'LOT-INV-1', 'lot_name' => 'Lô inverter', 'received_at' => $now,
            'qty_in' => 2, 'qty_remaining' => 2, 'cost_before_vat' => 7_000_000, 'cost_vat_percent' => 10, 'cost_after_vat' => 7_700_000, 'created_at' => $now, 'updated_at' => $now,
        ]);

        $html = $this->actingAs($admin)->get("/products/output?price_tier_id={$tierId}")->assertOk()->getContent();
        $this->assertStringContainsString('8,000,000', $html, 'giá bảng giá trước VAT');
        $this->assertStringContainsString('8,900,000', $html, 'giá sau VAT lưu sẵn, không tính lại 8,800,000');
        $this->assertStringNotContainsString('8,800,000', $html);
        $this->assertStringContainsString('Ghi chú test', $html);
        $this->assertStringContainsString('Lô inverter', $html, 'tên lô');

        $html = $this->actingAs($admin)->get('/products/input')->assertOk()->getContent();
        $this->assertStringContainsString('7,700,000', $html, 'admin thấy giá vốn sau VAT');
        $this->assertStringContainsString('15,400,000 đ', $html, 'thành tiền = giá vốn sau VAT × tồn 2');
        $this->assertStringContainsString('<span class="warehouse">Kho Test</span>', $html, 'tồn tách theo kho');
        $this->assertStringContainsString(route('products.create'), $html, 'admin có nút thêm sản phẩm');

        $html = $this->actingAs($sales)->get('/products/input')->assertOk()->getContent();
        $this->assertStringNotContainsString('7,700,000', $html, 'sales không thấy giá vốn');
        $this->assertStringNotContainsString('15,400,000', $html);
        $this->assertStringContainsString('INV-TEST-5', $html, 'nhưng vẫn thấy sản phẩm');
        $this->assertStringNotContainsString(route('products.create'), $html, 'sales không có nút thêm');
    }
}
