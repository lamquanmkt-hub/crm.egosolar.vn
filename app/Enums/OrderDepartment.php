<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Enum định nghĩa các bộ phận trong luồng phê duyệt đơn hàng.
 *
 * Luồng: Sales → Sales Manager → Accounting → Management → Warehouse → Completed
 *
 * Mỗi department biết:
 * - Tên hiển thị (label)
 * - Bộ phận tiếp theo (nextDepartment)
 * - Mã trạng thái khi đơn đang ở đó (arrivalStatusCode)
 *
 * ## Nguồn duy nhất của tên bộ phận (chốt 2026-09-07)
 * Trước đó cùng một bộ phận có tới 3 tên tuỳ màn hình (warehouse = "Kho" / "Kho vận",
 * completed = "Hoàn tất" / "Hoàn thành", sales_manager = "Sales Manager" / "Quản lý kinh doanh" /
 * "Duyệt cấp 1") vì 6 chỗ tự giữ map nhãn. Nay mọi chỗ gọi `label()` / `labelFor()`; nhãn
 * trùng vai trò thì lấy đúng chữ của {@see Role} (Kế toán, Ban Giám đốc, Kho).
 *
 * Mã cũ (`ketoan`, `duyet1`, `duyet2`, `kho`, `canceled`) không còn trong dữ liệu production
 * (đo 2026-09-07: crm_orders, crm_order_approvals, crm_order_status_history đều chỉ có giá trị
 * chuẩn) — vẫn đổi được qua `fromLegacy()` để trang cũ và test cũ không gãy, rẻ.
 */
enum OrderDepartment: string
{
    case SALES = 'sales';
    case SALES_MANAGER = 'sales_manager';
    case ACCOUNTING = 'accounting';
    case MANAGEMENT = 'management';
    case WAREHOUSE = 'warehouse';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /** Mã cũ từng lưu trong DB / viết trong view => mã chuẩn. */
    private const LEGACY_ALIASES = [
        'ketoan' => 'accounting',
        'ke_toan' => 'accounting',
        'duyet1' => 'sales_manager',
        'duyet2' => 'management',
        'kho' => 'warehouse',
        'canceled' => 'cancelled',
        'huy' => 'cancelled',
        'da_huy' => 'cancelled',
    ];

    /**
     * Tên hiển thị tiếng Việt.
     */
    public function label(): string
    {
        return match ($this) {
            self::SALES => 'Sales',
            self::SALES_MANAGER => 'Sales Manager',
            self::ACCOUNTING => 'Kế toán',
            self::MANAGEMENT => 'Ban Giám đốc',
            self::WAREHOUSE => 'Kho',
            self::COMPLETED => 'Hoàn thành',
            self::CANCELLED => 'Đã hủy',
        };
    }

    /**
     * Đổi mã (kể cả mã cũ, không phân biệt hoa thường) thành enum; không nhận ra thì null.
     */
    public static function fromLegacy(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }
        $value = strtolower(trim($value));

        return self::tryFrom(self::LEGACY_ALIASES[$value] ?? $value);
    }

    /**
     * Nhãn cho một mã đọc từ DB/request: mã chuẩn hay mã cũ đều ra nhãn chuẩn; mã lạ thì
     * viết hoa chữ đầu như các view vẫn làm.
     */
    public static function labelFor(?string $value): string
    {
        return self::fromLegacy($value)?->label() ?? ucfirst((string) $value);
    }

    /**
     * Bộ phận tiếp theo trong luồng duyệt.
     *
     * @return self|null Null nếu đã ở bước cuối
     */
    public function nextDepartment(): ?self
    {
        return match ($this) {
            self::SALES => self::SALES_MANAGER,
            self::SALES_MANAGER => self::ACCOUNTING,
            self::ACCOUNTING => self::MANAGEMENT,
            self::MANAGEMENT => self::WAREHOUSE,
            self::WAREHOUSE => self::COMPLETED,
            self::COMPLETED,
            self::CANCELLED => null,
        };
    }

    /**
     * Mã trạng thái của đơn khi đơn ĐANG Ở bộ phận này.
     *
     * Trước 2026-09-07 hàm tên `statusCode()` trả mã "khi rời bộ phận" (lệch một bước:
     * SALES → PENDING_SALES_MANAGER … WAREHOUSE → COMPLETED, COMPLETED → COMPLETED), còn
     * `transitionToDepartment()` lúc không được truyền mã lại lấy mã của bộ phận ĐÍCH — hai nghĩa
     * chỉ trùng nhau ở bước cuối. Nay một nghĩa: chuyển đơn tới đâu thì lấy mã của nơi đó.
     * Đã đối chiếu từng đường đi thật: gửi duyệt, duyệt SM → Kế toán → BGĐ → Kho, xuất kho hoàn
     * thành đều ra đúng mã cũ; SALES và CANCELLED không có chỗ gọi (tạo đơn ghi thẳng
     * PENDING_APPROVAL, huỷ/từ chối truyền mã tường minh).
     */
    public function arrivalStatusCode(): OrderStatusCode
    {
        return match ($this) {
            self::SALES => OrderStatusCode::PENDING_APPROVAL,
            self::SALES_MANAGER => OrderStatusCode::PENDING_SALES_MANAGER,
            self::ACCOUNTING => OrderStatusCode::PENDING_ACCOUNTING,
            self::MANAGEMENT => OrderStatusCode::PENDING_MANAGEMENT,
            self::WAREHOUSE => OrderStatusCode::READY_TO_SHIP,
            self::COMPLETED => OrderStatusCode::COMPLETED,
            self::CANCELLED => OrderStatusCode::CANCELLED,
        };
    }

    /**
     * Màu Bootstrap cho hiển thị badge.
     */
    public function badgeColor(): string
    {
        return match ($this) {
            self::SALES => 'secondary',
            self::SALES_MANAGER => 'warning',
            self::ACCOUNTING => 'info',
            self::MANAGEMENT => 'warning',
            self::WAREHOUSE => 'primary',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Kiểm tra đơn có thể duyệt ở bước này không.
     */
    public function isApprovable(): bool
    {
        return in_array($this, [
            self::SALES_MANAGER,
            self::ACCOUNTING,
            self::MANAGEMENT,
            self::WAREHOUSE,
        ], true);
    }
}
