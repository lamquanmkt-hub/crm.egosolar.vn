<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Models\User;
use App\Services\System\SidebarStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Đặc tả số liệu sidebar sau khi tách khỏi Blade.
 *
 * Quan trọng: các bộ đếm bỏ `LOWER(status)` để MariaDB dùng được index —
 * test dưới đây chứng minh việc bỏ đó KHÔNG đổi kết quả, vì cột status dùng
 * collation không phân biệt hoa thường.
 */
final class SidebarStatusServiceTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private SidebarStatusService $service;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
        $this->service = new SidebarStatusService;
    }

    /** Id user thao tác trong fixture đơn hàng. */
    protected function seedActorId(): int
    {
        return (int) $this->actor->id;
    }

    /** Đếm đúng người đang online trong 5 phút gần nhất. */
    public function test_counts_only_users_seen_in_last_five_minutes(): void
    {
        $this->truncateTables('users');

        User::factory()->create(['last_seen_at' => now()->subMinutes(1), 'is_active' => 1]);
        User::factory()->create(['last_seen_at' => now()->subMinutes(30), 'is_active' => 1]);
        User::factory()->create(['last_seen_at' => null, 'is_active' => 1]);

        $this->assertSame(1, $this->service->viewData()['egoOnlineCount']);
    }

    /** Nhân viên hoạt động chỉ tính is_active = 1. */
    public function test_counts_active_employees_only(): void
    {
        $this->truncateTables('users');

        User::factory()->count(3)->create(['is_active' => 1]);
        User::factory()->count(2)->create(['is_active' => 0]);

        $this->assertSame(3, $this->service->viewData()['egoActiveEmployees']);
    }

    /** Badge đơn chờ duyệt đếm theo ĐƠN, không theo số phiếu duyệt. */
    public function test_pending_order_badge_counts_distinct_orders(): void
    {
        $this->truncateTables('crm_order_approvals');
        $this->seedShippableOrder();

        $pendingOrderId = $this->seed['orderId'];
        $approvedOrderId = $this->cloneOrder('TEST-APPROVED');

        DB::table('crm_order_approvals')->insert([
            ['order_id' => $pendingOrderId, 'status' => 'pending', 'level' => 1],
            ['order_id' => $pendingOrderId, 'status' => 'pending', 'level' => 2],
            ['order_id' => $approvedOrderId, 'status' => 'approved', 'level' => 1],
        ]);

        $this->assertSame(1, $this->service->viewData()['egoPendingOrdersCount']);
    }

    /** So khớp status KHÔNG phân biệt hoa thường (nhờ collation của cột). */
    public function test_pending_status_matching_is_case_insensitive(): void
    {
        $this->truncateTables('crm_order_approvals');
        $this->seedShippableOrder();

        DB::table('crm_order_approvals')->insert([
            ['order_id' => $this->seed['orderId'], 'status' => 'PENDING', 'level' => 1],
            ['order_id' => $this->cloneOrder('TEST-UPPER'), 'status' => 'Pending', 'level' => 1],
        ]);

        $this->assertSame(
            2,
            $this->service->viewData()['egoPendingOrdersCount'],
            'Bỏ LOWER() không được làm sót bản ghi viết hoa.',
        );
    }

    /** Việc chưa hoàn thành của chính người đăng nhập. */
    public function test_counts_my_unfinished_tasks_only(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        DB::table('tasks')->insert([
            ['title' => 'Việc của tôi', 'assignee_id' => $me->id, 'status' => 'new', 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Việc đã xong', 'assignee_id' => $me->id, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Việc người khác', 'assignee_id' => $other->id, 'status' => 'new', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($me);

        $this->assertSame(1, $this->service->viewData()['egoMyUnfinishedTasksCount']);
    }

    /** Tạo thêm một đơn dùng chung lead/công ty của fixture. */
    private function cloneOrder(string $orderCode): int
    {
        return (int) DB::table('crm_orders')->insertGetId([
            'company_id' => $this->seed['companyId'],
            'lead_id' => $this->seed['leadId'],
            'order_code' => $orderCode.'-'.uniqid(),
            'order_date' => now()->toDateString(),
            'total_amount' => 0,
            'created_by' => $this->actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Ba ô trạng thái luôn đủ khoá và đúng thứ tự hiển thị. */
    public function test_status_items_shape(): void
    {
        $items = $this->service->viewData()['egoStatusItems'];

        $this->assertCount(3, $items);
        $this->assertSame(['online', 'working', 'employees'], array_column($items, 'key'));

        foreach ($items as $item) {
            $this->assertSame(['key', 'label', 'value', 'icon', 'tone'], array_keys($item));
            $this->assertIsInt($item['value']);
        }
    }
}
