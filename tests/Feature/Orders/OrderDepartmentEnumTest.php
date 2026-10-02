<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Enums\OrderDepartment;
use App\Enums\OrderStatusCode;
use App\Enums\Role;
use Tests\TestCase;

/** Tên bộ phận đơn hàng có một nguồn (chốt 2026-09-07): trùng vai trò thì lấy đúng chữ của Role. */
final class OrderDepartmentEnumTest extends TestCase
{
    public function test_nhan_bo_phan_khop_nhan_vai_tro_cung_ten(): void
    {
        $this->assertSame(Role::Accounting->label(), OrderDepartment::ACCOUNTING->label());
        $this->assertSame(Role::Warehouse->label(), OrderDepartment::WAREHOUSE->label());
        $this->assertSame(Role::Management->label(), OrderDepartment::MANAGEMENT->label());
        $this->assertSame(['Kế toán', 'Kho', 'Ban Giám đốc', 'Hoàn thành', 'Đã hủy'], [
            OrderDepartment::ACCOUNTING->label(), OrderDepartment::WAREHOUSE->label(), OrderDepartment::MANAGEMENT->label(),
            OrderDepartment::COMPLETED->label(), OrderDepartment::CANCELLED->label(),
        ]);
    }

    public function test_ma_cu_va_ma_la_deu_ra_nhan_on_dinh(): void
    {
        $this->assertSame(OrderDepartment::ACCOUNTING, OrderDepartment::fromLegacy('ketoan'));
        $this->assertSame(OrderDepartment::MANAGEMENT, OrderDepartment::fromLegacy(' DUYET2 '));
        $this->assertSame(OrderDepartment::WAREHOUSE, OrderDepartment::fromLegacy('kho'));
        $this->assertSame(OrderDepartment::CANCELLED, OrderDepartment::fromLegacy('canceled'));
        $this->assertNull(OrderDepartment::fromLegacy('shipping'), 'vận chuyển chỉ là bước trình bày, không phải bộ phận');
        $this->assertNull(OrderDepartment::fromLegacy(null));

        $this->assertSame(['Kho', 'Kế toán', 'Custom', ''], [
            OrderDepartment::labelFor('warehouse'), OrderDepartment::labelFor('ketoan'), OrderDepartment::labelFor('custom'), OrderDepartment::labelFor(null),
        ], 'mã lạ viết hoa chữ đầu như các view vẫn làm');
    }

    /** Đổi nghĩa "mã khi rời" → "mã khi đang ở" (2026-09-07): mọi bước chuyển thật vẫn ra đúng mã cũ. */
    public function test_ma_trang_thai_cua_buoc_dich_bang_ma_cu_khi_roi_buoc_hien_tai(): void
    {
        $leavingCodesBefore = [
            'sales' => OrderStatusCode::PENDING_SALES_MANAGER,
            'sales_manager' => OrderStatusCode::PENDING_ACCOUNTING,
            'accounting' => OrderStatusCode::PENDING_MANAGEMENT,
            'management' => OrderStatusCode::READY_TO_SHIP,
            'warehouse' => OrderStatusCode::COMPLETED,
        ];
        foreach ($leavingCodesBefore as $current => $codeBefore) {
            $next = OrderDepartment::from($current)->nextDepartment();
            $this->assertSame($codeBefore, $next?->arrivalStatusCode(), "rời {$current} → tới {$next?->value}");
        }
        $this->assertSame(OrderStatusCode::PENDING_APPROVAL, OrderDepartment::SALES->arrivalStatusCode(), 'khớp mã đơn mới tạo');
        $this->assertSame(OrderStatusCode::CANCELLED, OrderDepartment::CANCELLED->arrivalStatusCode());
        $this->assertNull(OrderDepartment::COMPLETED->nextDepartment());
    }
}
