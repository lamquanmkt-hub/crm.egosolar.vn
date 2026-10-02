<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PermissionAction;
use App\Models\User;
use App\Services\RolePermission\ActionPermissionRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Quyền thao tác CRUD đầy đủ cho mọi trang + màn hình phân quyền hiển thị đúng.
 */
final class ActionPermissionMatrixTest extends TestCase
{
    use DatabaseTransactions;

    private ActionPermissionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ActionPermissionRegistry;
    }

    /** Mọi trang trong config đều có mặt trong ma trận quyền thao tác. */
    public function test_every_page_permission_has_an_action_module(): void
    {
        $pages = array_keys(config('role_permissions.page_permissions'));
        $modules = $this->registry->modules();

        $this->assertSame(count($pages), count($modules));

        foreach ($pages as $page) {
            $key = str_replace('page.', '', $page);
            $this->assertArrayHasKey($key, $modules, "Thiếu module thao tác cho {$page}");
            $this->assertSame($page, $modules[$key]['page_permission']);
        }
    }

    /** Trang thông thường có đủ 4 thao tác Xem/Thêm/Sửa/Xoá. */
    public function test_standard_module_has_full_crud(): void
    {
        $matrix = collect($this->registry->matrix())->keyBy('key');

        foreach (['orders', 'customers', 'products', 'finance', 'hr'] as $key) {
            $actions = collect($matrix->get($key)['actions'])->pluck('action')->all();

            $this->assertSame(
                ['view', 'create', 'update', 'delete'],
                $actions,
                "Module {$key} phải có đủ 4 thao tác theo đúng thứ tự.",
            );
        }
    }

    /** Trang chỉ để xem thì không sinh quyền thêm/sửa/xoá. */
    public function test_read_only_module_has_only_view(): void
    {
        $matrix = collect($this->registry->matrix())->keyBy('key');

        $this->assertSame(
            ['view'],
            collect($matrix->get('dashboard')['actions'])->pluck('action')->all(),
        );
    }

    /** Tên quyền theo đúng quy ước `<module>.<action>`. */
    public function test_permission_naming_convention(): void
    {
        $this->assertSame('orders.delete', $this->registry->permissionName('orders', PermissionAction::Delete));
        $this->assertTrue($this->registry->isManaged('orders.delete'));
        $this->assertFalse($this->registry->isManaged('order.approve_level1'));
    }

    /** Lệnh đồng bộ tạo đủ quyền còn thiếu và chạy lại được. */
    public function test_sync_command_creates_missing_permissions_and_is_idempotent(): void
    {
        $expected = $this->registry->permissionNames();

        Permission::query()->whereIn('name', $expected)->delete();

        $this->artisan('permissions:sync')->assertExitCode(0);

        $existing = Permission::query()->whereIn('name', $expected)->pluck('name')->all();
        $this->assertCount(count($expected), $existing);

        $this->artisan('permissions:sync')->assertExitCode(0);

        $this->assertSame(
            count($expected),
            Permission::query()->whereIn('name', $expected)->count(),
            'Chạy lần hai không được nhân bản quyền.',
        );
    }

    /** --dry-run không ghi gì vào DB. */
    public function test_sync_command_dry_run_writes_nothing(): void
    {
        $expected = $this->registry->permissionNames();
        Permission::query()->whereIn('name', $expected)->delete();

        $this->artisan('permissions:sync --dry-run')->assertExitCode(0);

        $this->assertSame(0, Permission::query()->whereIn('name', $expected)->count());
    }

    /** Lệnh đồng bộ KHÔNG được xoá quyền nghiệp vụ cũ. */
    public function test_sync_command_never_deletes_legacy_permissions(): void
    {
        $legacy = Permission::findOrCreate('order.approve_level1', 'web');

        $this->artisan('permissions:sync')->assertExitCode(0);

        $this->assertDatabaseHas('permissions', ['id' => $legacy->id, 'name' => 'order.approve_level1']);
    }

    /** Màn "Quyền thao tác" render ma trận với đủ ô CRUD. */
    public function test_actions_page_renders_crud_matrix(): void
    {
        $this->artisan('permissions:sync');
        Permission::findOrCreate('order.approve_level1', 'web');

        $admin = $this->userWithRole('admin');
        $role = Role::findOrCreate('ke_toan_test', 'web');

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.actions', ['role' => $role->id]))
            ->assertOk();

        $response->assertSee('Trang / Module', escape: false);
        $response->assertSee('orders.delete', escape: false);
        $response->assertSee('customers.create', escape: false);
        $response->assertSee('Quyền nghiệp vụ chuyên biệt', escape: false);

        $matrix = $response->viewData('actionMatrix');
        $this->assertCount(count($this->registry->modules()), $matrix);
    }

    /** Lưu quyền thao tác cho một role ghi đúng vào DB. */
    public function test_saving_action_permissions_persists_selection(): void
    {
        $this->artisan('permissions:sync');

        $admin = $this->userWithRole('admin');
        $role = Role::findOrCreate('kho_test', 'web');

        $this->actingAs($admin)
            ->put(route('admin.settings.roles.actions', ['role' => $role->id]), [
                'permissions' => ['products.view', 'products.create', 'products.update'],
            ])
            ->assertRedirect();

        $granted = $role->fresh()->permissions->pluck('name')->all();

        sort($granted);
        $this->assertSame(['products.create', 'products.update', 'products.view'], $granted);
        $this->assertNotContains('products.delete', $granted);
    }

    /** User được cấp quyền xoá thì `can()` trả true, không thì false. */
    public function test_granted_delete_permission_is_effective(): void
    {
        $this->artisan('permissions:sync');

        $role = Role::findOrCreate('sales_test', 'web');
        $role->givePermissionTo('orders.view');

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertTrue($user->can('orders.view'));
        $this->assertFalse($user->can('orders.delete'));
    }

    /** Không tạo quyền trùng tên với quyền trang/menu. */
    public function test_action_permissions_do_not_collide_with_page_or_menu(): void
    {
        $actions = $this->registry->permissionNames();
        $pages = array_keys(config('role_permissions.page_permissions'));
        $menus = array_keys(config('role_permissions.menu_permissions'));

        $this->assertSame([], array_intersect($actions, $pages));
        $this->assertSame([], array_intersect($actions, $menus));
    }

    /** Quyền thao tác chuẩn không lọt xuống nhóm "chuyên biệt". */
    public function test_managed_actions_are_excluded_from_specialised_groups(): void
    {
        $this->artisan('permissions:sync');

        $admin = $this->userWithRole('admin');
        $role = Role::findOrCreate('marketing_test', 'web');

        $response = $this->actingAs($admin)
            ->get(route('admin.settings.actions', ['role' => $role->id]))
            ->assertOk();

        $specialised = collect($response->viewData('businessGroups'))
            ->flatMap(fn (array $group) => collect($group['permissions'])->pluck('permission.name'))
            ->all();

        $this->assertSame(
            [],
            array_intersect($specialised, $this->registry->permissionNames()),
            'Quyền CRUD chuẩn phải nằm ở ma trận, không lặp lại ở nhóm chuyên biệt.',
        );
    }

    protected function tearDown(): void
    {
        DB::table('permissions')->whereIn('name', $this->registry->permissionNames())->delete();
        parent::tearDown();
    }
}
