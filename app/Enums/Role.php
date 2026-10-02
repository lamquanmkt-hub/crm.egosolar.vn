<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Danh sách vai trò của hệ thống — NGUỒN SỰ THẬT DUY NHẤT cho tên role.
 *
 * ## Vì sao cần enum này
 * Trước 2026-08-06 tên role chỉ tồn tại dưới dạng chuỗi rải rác: 23 tên khác
 * nhau ở 106 chỗ trong 25 file. Hệ quả đo được:
 *
 *  - 12 trong 23 tên KHÔNG tồn tại trong DB. Middleware `role:` của Spatie không
 *    báo lỗi với tên lạ — nó chỉ lặng lẽ không khớp ai, nên gõ sai không sinh
 *    lỗi, không log, chỉ lộ ra khi có người kêu "tôi không vào được trang".
 *  - Đổi tên đúng MỘT role phải sờ tới 25 file, không có gì bảo đảm không sót.
 *
 * ## Vì sao role dùng enum còn permission thì KHÔNG
 * `app/Enums/Permission.php` từng tồn tại và đã bị xoá, vì 220 permission là DỮ
 * LIỆU: quản trị viên tự thêm/bớt trên giao diện phân quyền, đóng băng vào code
 * là sai. Còn 11 vai trò là CẤU TRÚC của tổ chức, gần như không đổi — enum đúng
 * chỗ ở đây.
 *
 * ## Quan hệ với DB
 * Enum này là danh sách chuẩn; bảng `roles` là dữ liệu chạy thật. Hai bên phải
 * khớp nhau — `RoleEnumMatchesDatabaseTest` và `php artisan authz:audit` canh
 * việc đó. Thêm role mới: thêm case ở đây RỒI viết migration tạo trong DB.
 */
enum Role: string
{
    case Admin = 'admin';
    case Management = 'management';
    case Accounting = 'accounting';
    case Hr = 'hr';
    case Warehouse = 'warehouse';
    case Sales = 'sales';
    case SalesManager = 'sales_manager';
    case Marketing = 'marketing';
    case MarketingManager = 'marketing_manager';

    /**
     * Kỹ thuật. Tên cũ là `ky_thuat`, đổi thành `technical` ngày 2026-08-06 để
     * thống nhất với `technical_manager` và bỏ được 4 tên tiếng Anh vốn không
     * tồn tại (`technical`, `technician`, `technical_staff`, `technical_leader`)
     * hay đứng kèm nó trong middleware.
     */
    case Technical = 'technical';
    case TechnicalManager = 'technical_manager';

    /** Chăm sóc khách hàng. Tạo mới 2026-08-06 — trước đó tên `cskh` được nhắc ở 35 route mà không có role thật. */
    case CustomerService = 'cskh';

    /** Trợ lý. Tạo mới 2026-08-06 — trước đó `assistant`/`tro_ly` được nhắc ở 20 route mà không có role thật. */
    case Assistant = 'assistant';

    /**
     * Tên hiển thị tiếng Việt.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị viên',
            self::Management => 'Ban Giám đốc',
            self::Accounting => 'Kế toán',
            self::Hr => 'Nhân sự',
            self::Warehouse => 'Kho',
            self::Sales => 'Nhân viên Sales',
            self::SalesManager => 'Quản lý Sales',
            self::Marketing => 'Nhân viên Marketing',
            self::MarketingManager => 'Quản lý Marketing',
            self::Technical => 'Kỹ thuật',
            self::TechnicalManager => 'Quản lý Kỹ thuật',
            self::CustomerService => 'Chăm sóc khách hàng',
            self::Assistant => 'Trợ lý',
        };
    }

    /**
     * Mô tả ngắn, dùng khi tạo role trong DB.
     */
    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Toàn quyền hệ thống.',
            self::Management => 'Ban giám đốc: xem toàn cảnh và duyệt cấp cao.',
            self::Accounting => 'Kế toán: công nợ, thanh toán, báo cáo tài chính.',
            self::Hr => 'Nhân sự: hồ sơ, chấm công, tuyển dụng.',
            self::Warehouse => 'Kho: nhập xuất, tồn kho, serial.',
            self::Sales => 'Kinh doanh: khách hàng, báo giá, đơn hàng.',
            self::SalesManager => 'Quản lý kinh doanh: duyệt và theo dõi đội sales.',
            self::Marketing => 'Marketing: kế hoạch, nội dung, khách hàng tiềm năng.',
            self::MarketingManager => 'Quản lý marketing: duyệt ngân sách và kế hoạch.',
            self::Technical => 'Kỹ thuật: thi công, vật tư, bảo trì bảo hành.',
            self::TechnicalManager => 'Quản lý kỹ thuật: phân công và duyệt kỹ thuật.',
            self::CustomerService => 'Chăm sóc khách hàng: tiếp nhận và theo dõi yêu cầu sau bán.',
            self::Assistant => 'Trợ lý: hỗ trợ điều phối, xem thông tin liên phòng ban.',
        };
    }

    /**
     * Vai trò có toàn quyền, không bị lọc bởi kiểm soát trang.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Tất cả tên role, dùng cho config và kiểm tra.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }

    /**
     * Ghép biểu thức cho middleware `role:`.
     *
     * Dùng thay cho chuỗi viết tay để lỗi chính tả không lọt được vào định tuyến:
     *
     *     ->middleware('role:'.Role::expression(Role::Technical, Role::Admin))
     */
    public static function expression(self ...$roles): string
    {
        return implode('|', array_map(static fn (self $role): string => $role->value, $roles));
    }

    /**
     * Đổi tên thành enum, trả null nếu tên không thuộc danh sách chuẩn.
     *
     * Dùng khi đọc tên role từ DB hoặc từ chuỗi middleware — nơi vẫn có thể gặp
     * tên cũ chưa dọn (xem `role_permissions.legacy_role_aliases`).
     */
    public static function tryFromName(?string $name): ?self
    {
        return $name === null ? null : self::tryFrom($name);
    }
}
