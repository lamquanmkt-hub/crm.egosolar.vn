<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Smoke test đặc tả hành vi hiện tại của các endpoint Orders
 * TRƯỚC khi refactor (characterization test).
 *
 * Chạy trên DB egosolar_test — bản sao schema production, mọi thay đổi
 * được rollback sau từng test nhờ DatabaseTransactions.
 */
final class OrderEndpointsSmokeTest extends TestCase
{
    use DatabaseTransactions;

    /** Khách chưa đăng nhập bị đưa về trang login. */
    public function test_guest_is_redirected_from_orders_index(): void
    {
        $this->get('/orders')->assertRedirect('/login');
    }

    /**
     * User có quyền `page.orders` xem được danh sách đơn hàng.
     *
     * `page.orders` nằm trong `role_permissions.always_enforce_permissions`
     * nên luôn bị kiểm tra ở backend, kể cả với role chưa bật ma trận quyền trang.
     */
    public function test_user_with_orders_page_permission_can_view_index(): void
    {
        $this->actingAs($this->userWithPermissions(['page.orders']))
            ->get('/orders')
            ->assertOk();
    }

    /** Không có `page.orders` thì bị chặn 403, không phụ thuộc role. */
    public function test_user_without_orders_page_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/orders')
            ->assertForbidden();
    }

    /** User không có role tạo đơn bị chặn 403 khỏi API catalog. */
    public function test_product_catalog_requires_order_create_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/orders/product-catalog')
            ->assertForbidden();
    }

    /** Sales (được phép tạo đơn) gọi API catalog nhận JSON có key products. */
    public function test_product_catalog_returns_products_for_sales_role(): void
    {
        $this->actingAs($this->userWithRole('sales', permissions: ['page.orders']))
            ->get('/orders/product-catalog')
            ->assertOk()
            ->assertJsonStructure(['products']);
    }
}
