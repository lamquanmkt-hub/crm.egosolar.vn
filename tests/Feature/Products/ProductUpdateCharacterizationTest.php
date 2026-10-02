<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Characterization test cho ProductController::update() — method dài nhất của
 * controller (476 dòng, 3 nhánh).
 *
 * Điểm quan trọng phải giữ: nhánh sửa thường có ngữ nghĩa **cập nhật một phần**
 * (trường nào form không gửi thì giữ giá trị cũ), khác hẳn nhánh tạo mới. Đây
 * chính là lý do KHÔNG gộp chung khối gán thuộc tính của hai nhánh.
 */
final class ProductUpdateCharacterizationTest extends TestCase
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

    /** Sửa thông tin cơ bản và tính lại giá sau VAT. */
    public function test_updates_basic_fields_and_recomputes_vat_prices(): void
    {
        $productId = $this->seedProduct(['price_agent' => 1_000_000, 'cost_vat_percent' => 10]);

        $this->putProduct($productId, [
            'name' => 'Tên Đã Sửa',
            'price_agent' => 2_000_000,
            'cost_vat_percent' => 10,
            'price_retail' => 3_000_000,
            'vat_percent' => 8,
        ]);

        $product = $this->product($productId);

        $this->assertSame('Tên Đã Sửa', $product->name);
        $this->assertEqualsWithDelta(2_000_000, (float) $product->price_agent, 0.01);
        $this->assertEqualsWithDelta(2_200_000, (float) $product->price_agent_vat, 0.01);
        $this->assertEqualsWithDelta(3_240_000, (float) $product->price_retail_vat, 0.01);
    }

    /**
     * Cập nhật MỘT PHẦN: trường không gửi lên phải giữ nguyên giá trị cũ.
     *
     * Đây là điểm khác biệt then chốt so với luồng tạo mới (tạo mới coi trường
     * thiếu là 0).
     */
    public function test_keeps_existing_values_for_fields_not_submitted(): void
    {
        $productId = $this->seedProduct([
            'price_agent' => 5_000_000,
            'cost_vat_percent' => 10,
            'price_retail' => 7_000_000,
            'vat_percent' => 8,
        ]);

        $this->putProduct($productId, ['name' => 'Chỉ Đổi Tên']);

        $product = $this->product($productId);

        $this->assertSame('Chỉ Đổi Tên', $product->name);
        $this->assertEqualsWithDelta(5_000_000, (float) $product->price_agent, 0.01);
        $this->assertEqualsWithDelta(7_000_000, (float) $product->price_retail, 0.01);
        $this->assertEqualsWithDelta(8, (float) $product->vat_percent, 0.01);
    }

    /** Nhánh sửa nhiều dòng tồn: cập nhật thuộc tính sản phẩm của từng dòng. */
    public function test_group_edit_updates_line_product_attributes(): void
    {
        $productId = $this->seedProduct(['price_agent' => 100, 'cost_vat_percent' => 0]);
        $sku = DB::table('crm_product_catalog')->where('id', $productId)->value('sku');

        $this->actingAs($this->user)
            ->put("/products/{$productId}", [
                'name' => 'Hàng Sửa Nhóm',
                'sku' => $sku,
                'group_edit_mode' => 1,
                'price_retail' => 1_000_000,
                'vat_percent' => 10,
                'v2_lines' => [[
                    'product_id' => $productId,
                    'sku' => $sku,
                    'company_id' => $this->seed['companyId'],
                    'warehouse_id' => $this->seed['warehouseId'],
                    'qty_in' => 5,
                    'cost_before_vat' => 800_000,
                    'cost_vat_percent' => 10,
                ]],
            ])
            ->assertSessionMissing('error');

        $product = $this->product($productId);

        $this->assertEqualsWithDelta(800_000, (float) $product->price_agent, 0.01);
        $this->assertEqualsWithDelta(880_000, (float) $product->price_agent_vat, 0.01);
    }

    /** Kho không thuộc công ty vận hành bị chặn ở nhánh sửa nhóm. */
    public function test_group_edit_rejects_warehouse_of_other_company(): void
    {
        $productId = $this->seedProduct(['price_agent' => 100]);
        $sku = DB::table('crm_product_catalog')->where('id', $productId)->value('sku');

        $otherCompanyId = (int) DB::table('companies')->insertGetId([
            'code' => 'OTH-'.uniqid(),
            'name' => 'Công ty Ngoài',
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

        $this->actingAs($this->user)
            ->put("/products/{$productId}", [
                'name' => 'Không được đổi',
                'sku' => $sku,
                'group_edit_mode' => 1,
                'v2_lines' => [[
                    'product_id' => $productId,
                    'sku' => $sku,
                    'company_id' => $otherCompanyId,
                    'warehouse_id' => $otherWarehouseId,
                    'qty_in' => 5,
                    'cost_before_vat' => 800_000,
                ]],
            ])
            ->assertSessionHas('error');

        $this->assertEqualsWithDelta(
            100,
            (float) $this->product($productId)->price_agent,
            0.01,
            'Giao dịch phải rollback, giá vốn giữ nguyên.',
        );
    }

    /** Gửi form sửa với payload tối thiểu. */
    private function putProduct(int $productId, array $payload): void
    {
        $sku = DB::table('crm_product_catalog')->where('id', $productId)->value('sku');

        $this->actingAs($this->user)
            ->put("/products/{$productId}", array_merge(['sku' => $sku], $payload))
            ->assertSessionMissing('error');
    }

    private function product(int $productId): object
    {
        return DB::table('crm_product_catalog')->where('id', $productId)->first();
    }

    /**
     * Tạo sản phẩm nền để sửa.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function seedProduct(array $attributes = []): int
    {
        return (int) DB::table('crm_product_catalog')->insertGetId(array_merge([
            'name' => 'Sản phẩm nền',
            'sku' => 'SKU-UPD-'.uniqid(),
            'unit' => 'cái',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }
}
