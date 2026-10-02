<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Characterization test cho luồng GHI của ProductController::store()
 * (255 dòng, hai nhánh: nhập một SKU và nhập nhiều dòng "v2_lines").
 *
 * Chốt hành vi hiện tại TRƯỚC khi tách: quy tắc SKU là khoá phân biệt, không
 * ghi đè giá vốn cũ, tính giá sau VAT, tạo lô nhập ban đầu, tạo serial, và các
 * chốt chặn kho phải thuộc công ty vận hành.
 */
final class ProductStoreCharacterizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->userWithRole('admin', permissions: ['page.products']);
        $this->seedShippableOrder();
    }

    /** Id user thao tác trong fixture. */
    protected function seedActorId(): int
    {
        return (int) $this->user->id;
    }

    /** Tạo sản phẩm mới: lưu thuộc tính và tính giá sau VAT từ giá trước VAT. */
    public function test_creates_product_with_vat_derived_prices(): void
    {
        $sku = 'SKU-NEW-'.uniqid();

        $this->postProduct([
            'name' => 'Inverter 5kW',
            'sku' => $sku,
            'price_agent' => 10_000_000,
            'cost_vat_percent' => 10,
            'price_retail' => 12_000_000,
            'vat_percent' => 8,
        ]);

        $product = DB::table('crm_product_catalog')->where('sku', $sku)->first();

        $this->assertNotNull($product);
        $this->assertSame('Inverter 5kW', $product->name);
        $this->assertEqualsWithDelta(10_000_000, (float) $product->price_agent, 0.01);
        $this->assertEqualsWithDelta(11_000_000, (float) $product->price_agent_vat, 0.01);
        $this->assertEqualsWithDelta(12_000_000, (float) $product->price_retail, 0.01);
        $this->assertEqualsWithDelta(12_960_000, (float) $product->price_retail_vat, 0.01);
        $this->assertSame(1, (int) $product->is_active);
    }

    /**
     * SKU là khoá phân biệt: nhập lại cùng SKU KHÔNG tạo sản phẩm trùng và
     * KHÔNG ghi đè giá vốn cũ.
     */
    public function test_reposting_same_sku_does_not_duplicate_or_overwrite_cost(): void
    {
        $sku = 'SKU-DUP-'.uniqid();

        $this->postProduct([
            'name' => 'Pin 450W',
            'sku' => $sku,
            'price_agent' => 1_000_000,
            'cost_vat_percent' => 10,
        ]);

        $this->postProduct([
            'name' => 'Pin 450W (giá khác)',
            'sku' => $sku,
            'price_agent' => 9_999_999,
            'cost_vat_percent' => 10,
        ]);

        $products = DB::table('crm_product_catalog')->where('sku', $sku)->get();

        $this->assertCount(1, $products, 'Cùng SKU không được tạo sản phẩm thứ hai.');
        $this->assertEqualsWithDelta(
            1_000_000,
            (float) $products[0]->price_agent,
            0.01,
            'Lần nhập sau KHÔNG được ghi đè giá vốn cũ.',
        );
    }

    /** Lô nhập ban đầu tạo dòng lô với giá vốn trước/sau VAT. */
    public function test_creates_initial_stock_lot(): void
    {
        $sku = 'SKU-LOT-'.uniqid();

        $this->postProduct([
            'name' => 'Ắc quy',
            'sku' => $sku,
            'initial_lots' => [[
                'company_id' => $this->seed['companyId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'qty_in' => 7,
                'cost_before_vat' => 2_000_000,
                'cost_vat_percent' => 10,
                'received_at' => now()->toDateString(),
            ]],
        ]);

        $productId = DB::table('crm_product_catalog')->where('sku', $sku)->value('id');
        $lot = DB::table('crm_product_stock_lots')->where('product_id', $productId)->first();

        $this->assertNotNull($lot);
        $this->assertSame(7, (int) $lot->qty_in);
        $this->assertSame(7, (int) $lot->qty_remaining);
        $this->assertEqualsWithDelta(2_000_000, (float) $lot->cost_before_vat, 0.01);
        $this->assertEqualsWithDelta(2_200_000, (float) $lot->cost_after_vat, 0.01);
    }

    /** Kho không thuộc công ty vận hành bị chặn, không tạo lô. */
    public function test_rejects_lot_in_warehouse_of_other_company(): void
    {
        $otherCompanyId = (int) DB::table('companies')->insertGetId([
            'code' => 'OTHER-'.uniqid(),
            'name' => 'Công ty Khác',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherWarehouseId = (int) DB::table('crm_warehouses')->insertGetId([
            'company_id' => $otherCompanyId,
            'name' => 'Kho Ngoài',
            'location' => 'HN',
            'manager_id' => null,
        ]);

        $sku = 'SKU-REJECT-'.uniqid();

        $this->actingAs($this->user)
            ->post('/products', [
                'name' => 'Hàng lạ',
                'sku' => $sku,
                'initial_lots' => [[
                    'company_id' => $otherCompanyId,
                    'warehouse_id' => $otherWarehouseId,
                    'qty_in' => 3,
                    'cost_before_vat' => 100,
                ]],
            ])
            ->assertSessionHas('error');

        $productId = DB::table('crm_product_catalog')->where('sku', $sku)->value('id');

        $this->assertNull(
            $productId,
            'Giao dịch phải rollback trọn vẹn khi kho không hợp lệ.',
        );
    }

    /** Nhập serial: đánh dấu sản phẩm serial hoá và tạo đủ đơn vị serial. */
    public function test_creates_serial_units_from_serial_codes(): void
    {
        $sku = 'SKU-SERIAL-'.uniqid();
        $prefix = 'SN-'.strtoupper(substr(uniqid(), -6));

        $this->postProduct([
            'name' => 'Inverter serial',
            'sku' => $sku,
            'warehouse_id' => $this->seed['warehouseId'],
            'company_id' => $this->seed['companyId'],
            'serials' => "{$prefix}-1\n{$prefix}-2\n{$prefix}-3",
        ]);

        $product = DB::table('crm_product_catalog')->where('sku', $sku)->first();

        $this->assertNotNull($product);
        $this->assertSame(1, (int) $product->is_serialized);
        $this->assertSame(
            3,
            DB::table('crm_serial_units')->where('product_id', $product->id)->count(),
        );
    }

    /** Nhánh v2_lines: mỗi dòng tạo một sản phẩm riêng theo SKU. */
    public function test_v2_lines_create_one_product_per_line(): void
    {
        $skuA = 'SKU-V2A-'.uniqid();
        $skuB = 'SKU-V2B-'.uniqid();

        $this->actingAs($this->user)
            ->post('/products', [
                'name' => 'Lô hàng V2',
                'sku' => $skuA,
                'v2_lines' => [
                    [
                        'sku' => $skuA,
                        'company_id' => $this->seed['companyId'],
                        'warehouse_id' => $this->seed['warehouseId'],
                        'qty_in' => 4,
                        'cost_before_vat' => 500_000,
                        'cost_vat_percent' => 10,
                    ],
                    [
                        'sku' => $skuB,
                        'company_id' => $this->seed['companyId'],
                        'warehouse_id' => $this->seed['warehouseId'],
                        'qty_in' => 6,
                        'cost_before_vat' => 700_000,
                        'cost_vat_percent' => 10,
                    ],
                ],
            ])
            ->assertSessionMissing('error');

        foreach ([[$skuA, 4, 500_000], [$skuB, 6, 700_000]] as [$sku, $qty, $cost]) {
            $product = DB::table('crm_product_catalog')->where('sku', $sku)->first();
            $this->assertNotNull($product, "Thiếu sản phẩm cho {$sku}");
            $this->assertEqualsWithDelta($cost, (float) $product->price_agent, 0.01);

            $lot = DB::table('crm_product_stock_lots')->where('product_id', $product->id)->first();
            $this->assertNotNull($lot, "Thiếu lô cho {$sku}");
            $this->assertSame($qty, (int) $lot->qty_in);
        }
    }

    /** Thiếu tên hoặc SKU thì bị chặn ở validate. */
    public function test_requires_name_and_sku(): void
    {
        $this->actingAs($this->user)
            ->post('/products', ['name' => '', 'sku' => ''])
            ->assertSessionHas('error');
    }

    /**
     * Gửi form tạo sản phẩm với payload tối thiểu hợp lệ.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postProduct(array $payload): void
    {
        $this->actingAs($this->user)
            ->post('/products', $payload)
            ->assertSessionMissing('error');
    }
}
