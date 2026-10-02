<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Characterization test cho luồng GHI dòng hàng khi cập nhật đơn
 * (`PUT /orders/{id}` với mảng `items[]`).
 *
 * Đây là phần nghiệp vụ nhạy cảm nhất của đơn hàng: parse tiền kiểu Việt Nam,
 * tự tra VAT, quy đổi giá TRƯỚC VAT sang SAU VAT theo bảng giá đại lý, tính
 * chiết khấu và tổng tiền. Chốt hành vi hiện tại TRƯỚC khi tách khỏi controller.
 */
final class OrderItemSyncCharacterizationTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->userWithRole('admin', permissions: ['page.orders']);
    }

    /** Id user thao tác trong fixture. */
    protected function seedActorId(): int
    {
        return (int) $this->user->id;
    }

    /** Cập nhật dòng hàng sẵn có: ghi lại giá, số lượng và tổng tiền đơn. */
    public function test_updates_existing_item_and_order_total(): void
    {
        $this->seedShippableOrder();

        $this->submitItems([
            [
                'id' => $this->seed['orderItemId'],
                'product_id' => $this->seed['productId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'quantity' => 3,
                'unit_price' => '2.000.000',
                'vat_percent' => 0,
            ],
        ]);

        $item = $this->orderItem($this->seed['orderItemId']);

        $this->assertSame(3, (int) $item->quantity);
        $this->assertEqualsWithDelta(2_000_000, (float) $item->unit_price, 0.01);
        $this->assertEqualsWithDelta(6_000_000, $this->orderTotal(), 0.01);
    }

    /** Tiền định dạng Việt Nam ("1.234.567 đ") được hiểu đúng. */
    public function test_parses_vietnamese_money_format(): void
    {
        $this->seedShippableOrder();

        $this->submitItems([
            [
                'id' => $this->seed['orderItemId'],
                'product_id' => $this->seed['productId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'quantity' => 1,
                'unit_price' => '1.234.567 đ',
                'vat_percent' => 0,
            ],
        ]);

        $this->assertEqualsWithDelta(
            1_234_567,
            (float) $this->orderItem($this->seed['orderItemId'])->unit_price,
            0.01,
        );
    }

    /** Chiết khấu % và số tiền cộng dồn, không vượt quá giá trị dòng. */
    public function test_applies_percent_and_amount_discounts(): void
    {
        $this->seedShippableOrder();

        $this->submitItems([
            [
                'id' => $this->seed['orderItemId'],
                'product_id' => $this->seed['productId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'quantity' => 2,
                'unit_price' => 1_000_000,
                'discount_percent' => 10,
                'discount_amount' => 100_000,
                'vat_percent' => 0,
            ],
        ]);

        // 2 x 1.000.000 = 2.000.000; giảm 10% (200.000) + 100.000 = 300.000.
        $this->assertEqualsWithDelta(1_700_000, $this->orderTotal(), 0.01);
        $this->assertEqualsWithDelta(300_000, $this->orderDiscount(), 0.01);
    }

    /**
     * Chiết khấu không bao giờ vượt quá giá trị dòng hàng: giảm 5 triệu trên
     * dòng 1 triệu thì thành tiền là 0, KHÔNG âm.
     */
    public function test_discount_is_capped_at_line_subtotal(): void
    {
        $this->seedShippableOrder();

        $this->submitItems([
            [
                'id' => $this->seed['orderItemId'],
                'product_id' => $this->seed['productId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'quantity' => 1,
                'unit_price' => 1_000_000,
                'discount_amount' => 5_000_000,
                'vat_percent' => 0,
            ],
        ]);

        // OrderRequest đã ghi total_amount = 0; sync thấy tổng <= 0 nên dừng,
        // không ghi đè thêm. Kết quả cuối cùng: đơn 0 đồng (không âm).
        $this->assertEqualsWithDelta(0, $this->orderTotal(), 0.01);
    }

    /** Tiền thuế của đơn được tách ra từ giá đã gồm VAT. */
    public function test_extracts_tax_amount_from_vat_inclusive_price(): void
    {
        $this->seedShippableOrder();

        $this->submitItems([
            [
                'id' => $this->seed['orderItemId'],
                'product_id' => $this->seed['productId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'quantity' => 1,
                'unit_price' => 1_100_000,
                'vat_percent' => 10,
            ],
        ]);

        // 1.100.000 đã gồm 10% VAT -> thuế = 1.100.000 - 1.000.000 = 100.000.
        $this->assertEqualsWithDelta(100_000, $this->orderTax(), 1);
    }

    /**
     * Giá TRƯỚC VAT của bảng giá đại lý được quy đổi sang giá SAU VAT khi lưu.
     *
     * Quy ước nghiệp vụ: `crm_order_items.unit_price` là giá ĐÃ gồm VAT.
     */
    public function test_converts_tier_price_before_vat_to_after_vat(): void
    {
        $this->seedShippableOrder();

        $tierId = $this->seedTierPrice(priceBeforeVat: 18_000_000, vatPercent: 8);

        $this->submitItems([
            [
                'id' => $this->seed['orderItemId'],
                'product_id' => $this->seed['productId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'price_tier_id' => $tierId,
                'quantity' => 1,
                'unit_price' => 18_000_000,
            ],
        ]);

        $item = $this->orderItem($this->seed['orderItemId']);

        $this->assertEqualsWithDelta(19_440_000, (float) $item->unit_price, 1);
        $this->assertEqualsWithDelta(8, (float) $item->vat_percent, 0.01);
    }

    /** Giá không khớp bảng giá thì giữ nguyên, không tự nhân thêm VAT. */
    public function test_keeps_custom_price_untouched(): void
    {
        $this->seedShippableOrder();

        $tierId = $this->seedTierPrice(priceBeforeVat: 18_000_000, vatPercent: 8);

        $this->submitItems([
            [
                'id' => $this->seed['orderItemId'],
                'product_id' => $this->seed['productId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'price_tier_id' => $tierId,
                'quantity' => 1,
                'unit_price' => 15_000_000,
            ],
        ]);

        $this->assertEqualsWithDelta(
            15_000_000,
            (float) $this->orderItem($this->seed['orderItemId'])->unit_price,
            1,
        );
    }

    /** Dòng hàng không có id nhưng trùng sản phẩm + kho thì cập nhật, không tạo mới. */
    public function test_matches_existing_row_by_product_and_warehouse(): void
    {
        $this->seedShippableOrder();

        $this->submitItems([
            [
                'product_id' => $this->seed['productId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'quantity' => 7,
                'unit_price' => 500_000,
                'vat_percent' => 0,
            ],
        ]);

        $items = DB::table('crm_order_items')->where('order_id', $this->seed['orderId'])->get();

        $this->assertCount(1, $items, 'Không được tạo thêm dòng trùng sản phẩm/kho.');
        $this->assertSame(7, (int) $items[0]->quantity);
    }

    /**
     * Dòng hàng đơn giá 0 bị bỏ qua, KHÔNG tạo thêm dòng rác trong đơn.
     *
     * (Dòng thiếu hẳn sản phẩm/kho đã bị FormRequest chặn từ vòng validate,
     * không xuống được tới đây.)
     */
    public function test_skips_rows_without_price(): void
    {
        $this->seedShippableOrder();

        $this->submitItems([
            [
                'product_id' => $this->seed['productId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'quantity' => 1,
                'unit_price' => 0,
                'vat_percent' => 0,
            ],
        ]);

        $items = DB::table('crm_order_items')->where('order_id', $this->seed['orderId'])->get();

        $this->assertCount(1, $items);
        $this->assertEqualsWithDelta(1_000_000, (float) $items[0]->unit_price, 0.01);
    }

    /** Không gửi items[] thì bị chặn ở validate, dòng hàng giữ nguyên. */
    public function test_request_without_items_is_rejected(): void
    {
        $this->seedShippableOrder();

        $this->actingAs($this->user)
            ->put("/orders/{$this->seed['orderId']}", [
                'customer_id' => $this->seed['customerId'],
                'company_id' => $this->seed['companyId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'order_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame(
            1,
            DB::table('crm_order_items')->where('order_id', $this->seed['orderId'])->count(),
        );
    }

    /**
     * Chống tái phát N+1: bảng giá và danh sách cột được nạp một lần cho cả
     * request, không phải mỗi dòng hàng một lần.
     */
    public function test_sync_does_not_scale_price_queries_with_item_count(): void
    {
        $this->seedShippableOrder();
        $tierId = $this->seedTierPrice(priceBeforeVat: 1_000_000, vatPercent: 10);

        $rows = [$this->itemPayload($this->seed['productId'], $tierId)];

        $oneItem = $this->countPriceQueries(fn () => $this->submitItems($rows));

        foreach (range(1, 9) as $index) {
            $rows[] = $this->itemPayload($this->seedExtraProduct($index), $tierId);
        }

        $tenItems = $this->countPriceQueries(fn () => $this->submitItems($rows));

        $this->assertSame(
            $oneItem,
            $tenItems,
            sprintf(
                'Số truy vấn bảng giá tăng theo số dòng hàng (1 dòng: %d, 10 dòng: %d).',
                $oneItem,
                $tenItems,
            ),
        );
    }

    /** Đếm truy vấn chạm bảng giá sản phẩm trong lúc thực thi callback. */
    private function countPriceQueries(callable $callback): int
    {
        $count = 0;

        DB::listen(static function ($query) use (&$count): void {
            if (str_contains($query->sql, 'crm_product_prices')) {
                $count++;
            }
        });

        $callback();

        return $count;
    }

    /** Một dòng hàng hợp lệ cho sản phẩm chỉ định. */
    private function itemPayload(int $productId, int $tierId): array
    {
        return [
            'product_id' => $productId,
            'warehouse_id' => $this->seed['warehouseId'],
            'price_tier_id' => $tierId,
            'quantity' => 1,
            'unit_price' => 1_000_000,
        ];
    }

    /** Tạo thêm sản phẩm phụ (mỗi dòng phải là sản phẩm khác nhau). */
    private function seedExtraProduct(int $index): int
    {
        return (int) DB::table('crm_product_catalog')->insertGetId([
            'name' => "Sản phẩm phụ {$index}",
            'sku' => "SKU-SYNC-{$index}-".uniqid(),
            'unit' => 'cái',
            'price' => 1_000_000,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Gửi mảng items[] tới endpoint cập nhật đơn. */
    private function submitItems(array $items): void
    {
        $this->putOrder(['items' => $items]);
    }

    /** PUT /orders/{id} với payload tối thiểu hợp lệ. */
    private function putOrder(array $payload): void
    {
        $response = $this->actingAs($this->user)->put(
            "/orders/{$this->seed['orderId']}",
            array_merge([
                'customer_id' => $this->seed['customerId'],
                'company_id' => $this->seed['companyId'],
                'warehouse_id' => $this->seed['warehouseId'],
                'order_date' => now()->toDateString(),
            ], $payload),
        );

        $response->assertRedirect("/orders/{$this->seed['orderId']}");
        $response->assertSessionMissing('error');
    }

    /** Thêm bảng giá theo tier cho sản phẩm fixture. */
    private function seedTierPrice(float $priceBeforeVat, float $vatPercent): int
    {
        $tierId = (int) DB::table('crm_price_tiers')->insertGetId([
            'code' => 'tier_'.uniqid(),
            'name' => 'Đại lý 3',
            'priority' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crm_product_prices')->insert([
            'product_id' => $this->seed['productId'],
            'price_tier_id' => $tierId,
            'price' => $priceBeforeVat,
            'vat_percent' => $vatPercent,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $tierId;
    }

    private function orderItem(int $itemId): object
    {
        return DB::table('crm_order_items')->where('id', $itemId)->first();
    }

    private function orderTotal(): float
    {
        return (float) DB::table('crm_orders')->where('id', $this->seed['orderId'])->value('total_amount');
    }

    private function orderDiscount(): float
    {
        return (float) DB::table('crm_orders')->where('id', $this->seed['orderId'])->value('discount_amount');
    }

    private function orderTax(): float
    {
        return (float) DB::table('crm_orders')->where('id', $this->seed['orderId'])->value('tax_amount');
    }
}
