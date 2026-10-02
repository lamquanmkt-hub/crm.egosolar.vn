<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Quyền của hai vai trò mới `cskh` và `assistant`.
 *
 * ## Quyền lấy từ đâu
 * Từ chính các route mà hai tên đó vốn được gắn trong middleware — đó là bản ghi
 * cụ thể về việc hai vai này được cho phép làm gì:
 *
 *   assistant -> 20 route, toàn bộ là /company-documents      (page.company)
 *   cskh      -> 35 route, toàn bộ là bảo trì bảo hành        (page.projects + page.technical)
 *
 * ## Điều nhóm test này canh chặt nhất: KHÔNG cấp thừa
 * Danh sách trong `role:a|b|c` là quyền VÀO MODULE, còn làm được gì do permission
 * quyết. Trên production `sales` đứng trên đúng 35 route bảo trì nhưng chỉ có 2
 * quyền, còn `technical_manager` có 15. Nếu suy quyền chi tiết từ danh sách role
 * thì sẽ cấp cho CSKH cả quyền phê duyệt — thẩm quyền quản lý, không phải việc
 * chăm sóc khách hàng.
 */
final class ServiceRolePermissionsTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = 'database/migrations/2026_08_06_000002_grant_permissions_to_service_roles.php';

    /**
     * Quyền phê duyệt/quản trị mà `cskh` KHÔNG được nhận.
     *
     * @var list<string>
     */
    private const APPROVAL_PERMISSIONS = [
        'maintenance.approve',
        'maintenance.reject',
        'maintenance.reopen',
        'maintenance.request_revision',
        'maintenance.assign',
        'maintenance.settings.manage',
        'maintenance.delete',
    ];

    private function runMigration(): void
    {
        (require base_path(self::MIGRATION))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Tạo sẵn các quyền cần thiết — DB test chỉ có schema, không có danh mục quyền.
     */
    private function seedPermissions(): void
    {
        foreach ([
            'page.company', 'menu.company',
            'company.view', 'company.create', 'company.update', 'company.delete',
            'page.projects', 'page.technical', 'menu.technical',
            'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.submit',
            'maintenance.files.view', 'maintenance.files.upload',
            ...self::APPROVAL_PERMISSIONS,
        ] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        SpatieRole::findOrCreate(Role::Assistant->value, 'web');
        SpatieRole::findOrCreate(Role::CustomerService->value, 'web');
    }

    /** Trợ lý nhận trọn quyền module tài liệu công ty. */
    public function test_assistant_gets_the_company_document_module(): void
    {
        $this->seedPermissions();
        $this->runMigration();

        $role = SpatieRole::where('name', Role::Assistant->value)->first();

        foreach (['page.company', 'menu.company', 'company.view', 'company.create', 'company.update', 'company.delete'] as $permission) {
            $this->assertTrue(
                $role->hasPermissionTo($permission),
                "assistant phải có {$permission} — 20 route của nó gồm cả tạo/sửa/xoá tài liệu công ty.",
            );
        }
    }

    /** CSKH nhận quyền thao tác trong module bảo trì. */
    public function test_customer_service_gets_the_maintenance_module(): void
    {
        $this->seedPermissions();
        $this->runMigration();

        $role = SpatieRole::where('name', Role::CustomerService->value)->first();

        foreach ([
            'page.projects', 'page.technical', 'menu.technical',
            'maintenance.view', 'maintenance.create', 'maintenance.update',
            'maintenance.submit', 'maintenance.files.view', 'maintenance.files.upload',
        ] as $permission) {
            $this->assertTrue($role->hasPermissionTo($permission), "cskh thiếu {$permission}.");
        }
    }

    /** CSKH KHÔNG được nhận quyền phê duyệt hay xoá lịch bảo trì. */
    public function test_customer_service_does_not_get_approval_powers(): void
    {
        $this->seedPermissions();
        $this->runMigration();

        $role = SpatieRole::where('name', Role::CustomerService->value)->first();
        $granted = [];

        foreach (self::APPROVAL_PERMISSIONS as $permission) {
            if ($role->hasPermissionTo($permission)) {
                $granted[] = $permission;
            }
        }

        $this->assertSame(
            [],
            $granted,
            "Đã cấp thừa quyền quản lý cho CSKH:\n  ".implode("\n  ", $granted).
            "\nDanh sách role trong middleware chỉ là quyền VÀO module, không phải quyền phê duyệt.",
        );
    }

    /** CSKH không được lan sang quyền toàn bộ dự án / kỹ thuật. */
    public function test_customer_service_does_not_get_broad_project_powers(): void
    {
        $this->seedPermissions();

        foreach (['projects.delete', 'projects.update', 'technical.delete', 'technical.update'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $this->runMigration();

        $role = SpatieRole::where('name', Role::CustomerService->value)->first();

        foreach (['projects.delete', 'projects.update', 'technical.delete', 'technical.update'] as $permission) {
            $this->assertFalse(
                $role->hasPermissionTo($permission),
                "cskh không được có {$permission} — quyền đó ảnh hưởng TOÀN BỘ dự án/kỹ thuật, ".
                'trong khi route của nó chỉ thuộc module bảo trì.',
            );
        }
    }

    /** Chạy lại migration không nhân đôi bản ghi phân quyền. */
    public function test_migration_is_safe_to_run_twice(): void
    {
        $this->seedPermissions();
        $this->runMigration();
        $this->runMigration();

        foreach ([Role::Assistant, Role::CustomerService] as $role) {
            $roleId = DB::table('roles')->where('name', $role->value)->value('id');

            $duplicates = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->selectRaw('permission_id, COUNT(*) as total')
                ->groupBy('permission_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            $this->assertSame(0, $duplicates, $role->value.' có bản ghi phân quyền bị nhân đôi.');
        }
    }

    /** Có quyền trang thì phải bật kiểm soát trang, nếu không quyền vô nghĩa. */
    public function test_service_roles_have_page_control_enabled(): void
    {
        $this->seedPermissions();
        $this->runMigration();

        foreach ([Role::Assistant, Role::CustomerService] as $role) {
            $this->assertSame(
                1,
                (int) DB::table('roles')->where('name', $role->value)->value('page_access_enabled'),
                $role->value.' chưa bật kiểm soát trang — quyền `page.*` sẽ không có tác dụng.',
            );
        }
    }

    /** `tro_ly` đã được gỡ khỏi mọi middleware, assistant thay chỗ. */
    public function test_tro_ly_is_gone_from_the_routes(): void
    {
        $stillThere = [];

        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || ! str_starts_with($middleware, 'role:')) {
                    continue;
                }

                $names = array_map('trim', explode('|', substr($middleware, 5)));

                if (in_array('tro_ly', $names, true)) {
                    $stillThere[] = $route->methods()[0].' /'.$route->uri();
                }

                if (in_array('tro_ly', $names, true) && ! in_array(Role::Assistant->value, $names, true)) {
                    $this->fail('Route còn tro_ly mà KHÔNG có assistant thay thế: /'.$route->uri());
                }
            }
        }

        $this->assertSame([], $stillThere, "tro_ly vẫn còn trong middleware:\n".implode("\n", $stillThere));
    }
}
