<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Characterization test cho trang chi tiết đơn hàng (`orders.show`).
 *
 * Chốt bằng test các giá trị view data mà Blade đang dùng — số lượng còn
 * được phép trả hàng, serial theo từng dòng hàng, serial toàn đơn — TRƯỚC khi
 * gom logic tải dữ liệu ra service và gộp N+1 query thành truy vấn theo lô.
 */
final class OrderDetailPageCharacterizationTest extends TestCase
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

    /** Đơn chưa trả hàng: được phép trả đúng bằng số lượng đã đặt. */
    public function test_returnable_quantity_equals_ordered_quantity_when_no_returns(): void
    {
        $this->seedShippableOrder();

        $response = $this->actingAs($this->user)
            ->get("/orders/{$this->seed['orderId']}")
            ->assertOk();

        $returnAvailable = $response->viewData('returnAvailable');

        $this->assertSame([$this->seed['orderItemId'] => 1], $returnAvailable);
    }

    /**
     * Phiếu trả đã duyệt trừ theo accepted_quantity, phiếu đang xử lý trừ theo
     * requested_quantity; phiếu bị từ chối/huỷ KHÔNG trừ.
     */
    public function test_returnable_quantity_subtracts_accepted_and_pending_returns(): void
    {
        $this->seedShippableOrder();
        $this->setOrderItemQuantity(10);

        $this->seedReturn(status: 'completed', accepted: 3, requested: 3);
        $this->seedReturn(status: 'submitted', accepted: 0, requested: 2);
        $this->seedReturn(status: 'rejected', accepted: 5, requested: 5);
        $this->seedReturn(status: 'cancelled', accepted: 0, requested: 4);

        $response = $this->actingAs($this->user)
            ->get("/orders/{$this->seed['orderId']}")
            ->assertOk();

        // 10 đặt - 3 đã nhận (completed) - 2 đang chờ (submitted) = 5.
        // Phiếu rejected/cancelled bị loại khỏi cả hai vế.
        $this->assertSame(
            [$this->seed['orderItemId'] => 5],
            $response->viewData('returnAvailable'),
        );
    }

    /** Không bao giờ trả số âm dù dữ liệu trả hàng vượt số đặt. */
    public function test_returnable_quantity_never_goes_negative(): void
    {
        $this->seedShippableOrder();
        $this->seedReturn(status: 'completed', accepted: 99, requested: 99);

        $response = $this->actingAs($this->user)
            ->get("/orders/{$this->seed['orderId']}")
            ->assertOk();

        $this->assertSame(
            [$this->seed['orderItemId'] => 0],
            $response->viewData('returnAvailable'),
        );
    }

    /** Serial đã gắn vào dòng hàng hiện ra ở cả map theo dòng và danh sách toàn đơn. */
    public function test_serials_are_exposed_per_item_and_for_whole_order(): void
    {
        $this->seedShippableOrder();
        $this->linkSerialToOrderItem();

        $response = $this->actingAs($this->user)
            ->get("/orders/{$this->seed['orderId']}")
            ->assertOk();

        $perItem = $response->viewData('returnSerialsByItem');
        $this->assertArrayHasKey($this->seed['orderItemId'], $perItem);

        $serials = collect($perItem[$this->seed['orderItemId']]);
        $this->assertCount(1, $serials);
        $this->assertSame($this->seed['serialUnitId'], (int) $serials->first()->id);
        $this->assertSame('SN-TEST-0001', $serials->first()->code);
        $this->assertSame('in_stock', $serials->first()->state);

        $orderSerials = collect($response->viewData('orderSerials'));
        $this->assertCount(1, $orderSerials);
        $this->assertSame('SN-TEST-0001', $orderSerials->first()->serial_code);
        $this->assertSame($this->seed['orderItemId'], (int) $orderSerials->first()->order_item_id);
    }

    /** Dòng hàng chưa gắn serial vẫn có khoá trong map, giá trị rỗng. */
    public function test_items_without_serials_still_have_empty_entry(): void
    {
        $this->seedShippableOrder();

        $response = $this->actingAs($this->user)
            ->get("/orders/{$this->seed['orderId']}")
            ->assertOk();

        $perItem = $response->viewData('returnSerialsByItem');

        $this->assertArrayHasKey($this->seed['orderItemId'], $perItem);
        $this->assertCount(0, collect($perItem[$this->seed['orderItemId']]));
    }

    /** Kho nhận hàng trả chỉ gồm kho cùng công ty với đơn. */
    public function test_return_warehouses_are_scoped_to_order_company(): void
    {
        $this->seedShippableOrder();

        $otherCompanyId = (int) DB::table('companies')->insertGetId([
            'code' => 'OTHER-'.uniqid(),
            'name' => 'Công ty Khác',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('crm_warehouses')->insert([
            'company_id' => $otherCompanyId,
            'name' => 'Kho Công Ty Khác',
            'location' => 'HN',
            'manager_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/orders/{$this->seed['orderId']}")
            ->assertOk();

        $names = collect($response->viewData('returnWarehouses'))->pluck('name')->all();

        $this->assertContains('Kho Test', $names);
        $this->assertNotContains('Kho Công Ty Khác', $names);
    }

    /**
     * Chống tái phát N+1: số truy vấn tra trả hàng/serial KHÔNG được tăng
     * theo số dòng hàng của đơn.
     *
     * Bản cũ bắn 3 truy vấn cho MỖI dòng hàng; bản gộp chỉ còn 1 truy vấn
     * tổng hợp trả hàng + 2 truy vấn serial (map theo dòng và bảng tổng đơn).
     */
    public function test_detail_page_does_not_scale_queries_with_item_count(): void
    {
        $this->seedShippableOrder();

        // Lần render đầu còn phải dò schema (App\Support\SchemaCache nạp lần
        // đầu); đo từ lần thứ hai để chỉ còn truy vấn nghiệp vụ.
        $this->actingAs($this->user)->get("/orders/{$this->seed['orderId']}")->assertOk();

        $withOneItem = $this->captureQueries(function (): void {
            $this->actingAs($this->user)
                ->get("/orders/{$this->seed['orderId']}")
                ->assertOk();
        });

        foreach (range(1, 9) as $index) {
            $this->addOrderItem($index);
        }

        $withTenItems = $this->captureQueries(function (): void {
            $this->actingAs($this->user)
                ->get("/orders/{$this->seed['orderId']}")
                ->assertOk();
        });

        $this->assertSame(
            count($withOneItem),
            count($withTenItems),
            sprintf(
                'Số truy vấn tăng theo số dòng hàng (1 dòng: %d, 10 dòng: %d) — N+1 đã tái phát.',
                count($withOneItem),
                count($withTenItems),
            ),
        );
    }

    /**
     * Thu thập SQL đã chạy trong lúc thực thi $callback.
     *
     * @return list<string>
     */
    private function captureQueries(callable $callback): array
    {
        $queries = [];

        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $callback();

        return $queries;
    }

    /**
     * Đếm số câu lệnh có nhắc tới một bảng.
     *
     * @param  list<string>  $queries
     */
    private function countQueriesTouching(array $queries, string $table): int
    {
        return count(array_filter(
            $queries,
            static fn (string $sql): bool => str_contains($sql, $table),
        ));
    }

    /**
     * Thêm 1 dòng hàng nữa vào đơn fixture.
     *
     * Mỗi dòng phải là sản phẩm khác nhau: bảng crm_order_items có UNIQUE
     * (order_id, warehouse_id, product_id).
     */
    private function addOrderItem(int $index): int
    {
        $productId = (int) DB::table('crm_product_catalog')->insertGetId([
            'name' => "Sản phẩm phụ {$index}",
            'sku' => "PV-EXTRA-{$index}-".uniqid(),
            'unit' => 'cái',
            'price' => 500_000,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('crm_order_items')->insertGetId([
            'order_id' => $this->seed['orderId'],
            'warehouse_id' => $this->seed['warehouseId'],
            'product_id' => $productId,
            'product_name' => "Dòng hàng {$index}",
            'quantity' => 1,
            'unit_price' => 500_000,
        ]);
    }

    /** Đổi số lượng dòng hàng của đơn fixture. */
    private function setOrderItemQuantity(int $quantity): void
    {
        DB::table('crm_order_items')
            ->where('id', $this->seed['orderItemId'])
            ->update(['quantity' => $quantity]);
    }

    /** Tạo 1 phiếu trả hàng gắn vào dòng hàng fixture. */
    private function seedReturn(string $status, int $accepted, int $requested): void
    {
        $returnId = (int) DB::table('order_returns')->insertGetId([
            'order_id' => $this->seed['orderId'],
            'company_id' => $this->seed['companyId'],
            'customer_id' => $this->seed['customerId'],
            'type' => 'return',
            'status' => $status,
            'return_code' => 'RT-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_return_items')->insert([
            'order_return_id' => $returnId,
            'order_item_id' => $this->seed['orderItemId'],
            'product_id' => $this->seed['productId'],
            'warehouse_id' => $this->seed['warehouseId'],
            'ordered_quantity' => 10,
            'requested_quantity' => $requested,
            'accepted_quantity' => $accepted,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Gắn serial fixture vào dòng hàng của đơn. */
    private function linkSerialToOrderItem(): void
    {
        DB::table('crm_order_item_serial_units')->insert([
            'order_item_id' => $this->seed['orderItemId'],
            'serial_unit_id' => $this->seed['serialUnitId'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
