<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\DTOs\Order\OrderFlowStepView;
use App\DTOs\Order\OrderProductLineView;
use App\DTOs\Order\OrderVatSummary;
use App\Enums\Role;
use App\Models\CRM\Orders\Order;
use App\View\Presenters\Order\OrderDetailPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/** Trang chi tiết đơn sau khi dời 5 khối `@php` sang OrderDetailPresenter (2026-09-07). */
final class OrderDetailPageTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    private const VIEW = 'resources/views/orders/show.blade.php';

    /** Biến view không do presenter cấp: controller, vòng lặp, Blade. */
    private const VIEW_VARIABLES_NOT_FROM_PRESENTER = [
        'order', 'timeline', 'notifications', 'paymentMethods', 'editHistories', 'currentApprovalLevel', 'returnAvailable',
        'returnSerialsByItem', 'returnWarehouses', 'stockAllocations', 'orderSerials', 'stockMovements', 'orderDocuments', 'documentTypes',
        'return', 'returnItem', 'payment', 'serial', 'approval', 'refund', 'allocation', 'movement', 'method', 'warehouse', 'event',
        'history', 'tabKey', 'tabLabel', 'index', 'line', 'step', 'displayVatRate', 'displayVatValue', 'item',
        'errors', 'error', 'e', 'loop', 'slot', 'attributes', 'component',
    ];

    private int $actor = 0;

    protected function seedActorId(): int
    {
        return $this->actor;
    }

    public function test_trang_in_bang_san_pham_tach_vat_va_dai_luong_duyet(): void
    {
        $user = $this->userWithRole(Role::Admin->value);
        $this->actor = (int) $user->id;
        $this->seedShippableOrder();
        $orderId = (int) $this->seed['orderId'];

        $html = $this->actingAs($user)->get('/orders/'.$orderId)->assertOk()->getContent();
        $this->assertStringContainsString('Tổng cộng trước VAT', $html);
        $this->assertStringContainsString('Tổng cộng sau thuế', $html);
        $this->assertStringContainsString('op-approval-step active', $html, 'đúng một bước đang xử lý');
        $this->assertStringContainsString('op-approval-step done', $html, 'các bước trước đã xong');
        $this->actingAs($user)->get('/orders/'.$orderId.'?tab=payments')->assertOk();
        $this->actingAs($user)->get('/orders/'.$orderId.'?tab=xyz')->assertOk();
    }

    /** View chỉ in: không `@php`; mọi biến nó đọc do presenter/controller cấp; thuộc tính DTO là thật. */
    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $used = array_values(array_unique(array_diff($m[1], self::VIEW_VARIABLES_NOT_FROM_PRESENTER)));
        $order = (new Order)->forceFill(['total_amount' => 0, 'current_department' => 'sales']);
        foreach (['items', 'payments', 'returns'] as $relation) {
            $order->setRelation($relation, collect());
        }
        $order->setRelation('lead', null);
        $order->setRelation('currentStatusType', null);
        $provided = array_keys(app(OrderDetailPresenter::class)->viewData($order, null, [], null, null));
        $this->assertSame([], array_values(array_diff($used, $provided)), 'biến view dùng mà presenter không trả');

        foreach (['line' => OrderProductLineView::class, 'step' => OrderFlowStepView::class, 'vatSummary' => OrderVatSummary::class] as $variable => $dto) {
            preg_match_all('/\$'.$variable.'->([a-zA-Z]+)/', $source, $m);
            $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass($dto))->getProperties());
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), "view đọc thuộc tính không có của \${$variable}");
        }
    }
}
