<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Support\SchemaCache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Quy ước phạm vi dữ liệu hoa hồng sales dùng chung giữa controller và exporter.
 */
final class SalesCommissionScope
{
    /**
     * Nhân viên bị loại khỏi mọi báo cáo hoa hồng/KPI theo yêu cầu nghiệp vụ.
     */
    public const EXCLUDED_SALES_NAME = 'Ngọc Trân';

    /**
     * Phòng ban được coi là bộ phận kinh doanh.
     *
     * So khớp theo TÊN chứ không theo id: id có thể khác giữa các môi trường.
     */
    public const SALES_DEPARTMENT_NAMES = ['Marketing & Sales'];

    /**
     * Chức danh được coi là nhân sự kinh doanh — khớp theo chuỗi con.
     *
     * Dữ liệu thật: "Nhân viên Sales", "Thử Việc Sales", "Học Việc Sales".
     */
    public const SALES_POSITION_KEYWORD = 'Sales';

    /**
     * Vai trò Spatie tương ứng — GIỮ LẠI làm tín hiệu bổ sung.
     *
     * Trên production hiện chỉ 1 user mang vai trò này và người đó đã nghỉ, nên
     * một mình nó không đủ (xem PHPDoc của {@see self::constrainToSalesStaff}).
     * Nhưng nếu sau này quản trị gán vai trò cho đúng người thì vẫn có tác dụng.
     */
    public const SALES_ROLE_NAMES = ['sales', 'sales_manager'];

    /**
     * Giới hạn một truy vấn trên bảng `users` về đúng nhân sự kinh doanh.
     *
     * ## Vì sao KHÔNG chỉ dựa vào vai trò
     * Đo trên production 2026-09-05, trong 235 đơn đã thu đủ tiền:
     *
     * | cách nhận diện | số đơn khớp |
     * |---|---|
     * | vai trò Spatie `sales`/`sales_manager` | **0** |
     * | phòng ban `Marketing & Sales` | **161** |
     * | chức danh chứa "Sales" | 144 |
     *
     * Công ty theo dõi nhân sự kinh doanh bằng PHÒNG BAN và CHỨC DANH; bảng
     * `model_has_roles` chỉ có đúng một user mang vai trò `sales` (#60, đã nghỉ,
     * tạo 0 đơn). Vì thế toàn bộ module hoa hồng trả về rỗng — xem P1n trong
     * REFACTOR_ROADMAP.md.
     *
     * Ba tín hiệu nối bằng HOẶC, để cách nào đúng cũng nhận ra được.
     *
     * ## Chênh lệch 161 so với 144 là 2 người "Trưởng nhóm"
     * #2 Nguyễn Lâm Quân (12 đơn) và #18 Thái Chi (5 đơn) thuộc phòng
     * `Marketing & Sales` nhưng chức danh là "Trưởng nhóm". Lấy theo phòng ban
     * thì họ được tính. Nếu nghiệp vụ muốn loại trưởng nhóm marketing ra thì sửa
     * hằng số ở lớp này, đừng sửa rải rác trong truy vấn.
     */
    public static function constrainToSalesStaff(Builder $query, string $alias = 'u'): Builder
    {
        return $query->where(function (Builder $outer) use ($alias): void {
            self::matchByDepartment($outer, $alias);
            self::matchByPosition($outer, $alias);
            self::matchByRole($outer, $alias);
        });
    }

    private static function matchByDepartment(Builder $query, string $alias): void
    {
        if (! SchemaCache::hasColumn('users', 'department_id') || ! SchemaCache::hasTable('departments')) {
            return;
        }

        $query->orWhereExists(function ($sub) use ($alias): void {
            $sub->select(DB::raw(1))
                ->from('departments as d')
                ->whereColumn('d.id', $alias.'.department_id')
                ->whereIn('d.name', self::SALES_DEPARTMENT_NAMES);
        });
    }

    private static function matchByPosition(Builder $query, string $alias): void
    {
        if (! SchemaCache::hasColumn('users', 'position_id') || ! SchemaCache::hasTable('positions')) {
            return;
        }

        $query->orWhereExists(function ($sub) use ($alias): void {
            $sub->select(DB::raw(1))
                ->from('positions as p')
                ->whereColumn('p.id', $alias.'.position_id')
                ->where('p.name', 'like', '%'.self::SALES_POSITION_KEYWORD.'%');
        });
    }

    private static function matchByRole(Builder $query, string $alias): void
    {
        if (! SchemaCache::hasTable('model_has_roles') || ! SchemaCache::hasTable('roles')) {
            return;
        }

        $query->orWhereExists(function ($sub) use ($alias): void {
            $sub->select(DB::raw(1))
                ->from('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->whereColumn('mhr.model_id', $alias.'.id')
                ->where('mhr.model_type', \App\Models\User::class)
                ->whereIn('r.name', self::SALES_ROLE_NAMES);
        });
    }
}
