<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Services\RolePermission\ActionPermissionRegistry;
use App\Services\RolePermission\BusinessPermissionCatalog;
use App\Services\RolePermission\PageAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Bất biến của danh mục quyền: mọi quyền phải được KHAI BÁO và có NHÃN tiếng Việt.
 *
 * Trước đợt này, quyền sinh ra theo kiểu ai cần thì tạo tay: 53/102 quyền
 * nghiệp vụ trên production không được khai báo ở bất kỳ file config nào, và
 * màn phân quyền hiển thị tên kỹ thuật đã "humanize" (vd "Orders Return Stock
 * In") thay vì tiếng Việt. Nhóm test này chặn việc đó tái diễn.
 */
final class PermissionCatalogIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    private BusinessPermissionCatalog $catalog;

    private ActionPermissionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new BusinessPermissionCatalog;
        $this->registry = new ActionPermissionRegistry;
    }

    /** Mọi quyền trong DB đều được khai báo ở config (không có quyền mồ côi). */
    public function test_no_undeclared_permission_exists_in_database(): void
    {
        $this->artisan('permissions:sync');

        $inDatabase = Permission::query()->pluck('name')->all();
        $undeclared = $this->catalog->undeclared($inDatabase, $this->registry->permissionNames());

        $this->assertSame(
            [],
            $undeclared,
            "Có quyền trong DB không khai báo ở config nào.\n".
            "Khai báo vào config/permissions.php (quyền nghiệp vụ) hoặc\n".
            'config/role_permissions.php (page.*/menu.*), hoặc xoá nếu không dùng.',
        );
    }

    /** Mọi quyền nghiệp vụ đã khai báo đều có nhãn tiếng Việt tử tế. */
    public function test_every_declared_business_permission_has_vietnamese_label(): void
    {
        $withoutLabel = [];

        foreach ($this->catalog->labelled() as $name => $label) {
            if (trim($label) === '' || $label === $name) {
                $withoutLabel[] = $name;
            }
        }

        $this->assertSame([], $withoutLabel, 'Quyền thiếu nhãn tiếng Việt trong config/permissions.php.');
    }

    /** Màn phân quyền không được hiển thị tên đã "humanize" thay cho tiếng Việt. */
    public function test_permission_groups_never_fall_back_to_humanized_names(): void
    {
        $this->artisan('permissions:sync');

        $permissions = Permission::query()
            ->whereNotIn('name', array_merge(
                array_keys(config('role_permissions.page_permissions')),
                array_keys(config('role_permissions.menu_permissions')),
                $this->registry->permissionNames(),
            ))
            ->get();

        $groups = app(PageAccessService::class)->permissionGroups($permissions);

        $humanized = collect($groups)
            ->flatMap(fn (array $group): Collection => collect($group['permissions']))
            ->filter(function (array $item): bool {
                $name = $item['permission']->name;
                $humanizedGuess = \Illuminate\Support\Str::headline(str_replace(['.', '-', '_'], ' ', $name));

                return $item['label'] === $humanizedGuess;
            })
            ->map(fn (array $item): string => $item['permission']->name)
            ->values()
            ->all();

        $this->assertSame(
            [],
            $humanized,
            "Các quyền sau đang hiện tên máy thay vì tiếng Việt.\n".
            'Thêm nhãn vào config/permissions.php hoặc role_permissions.action_labels.',
        );
    }

    /** Mỗi trang khai báo đầy đủ nhãn, mô tả và icon. */
    public function test_every_page_permission_is_fully_described(): void
    {
        $incomplete = [];

        foreach (config('role_permissions.page_permissions') as $name => $definition) {
            foreach (['label', 'description', 'icon'] as $key) {
                if (empty($definition[$key])) {
                    $incomplete[] = "{$name}.{$key}";
                }
            }
        }

        $this->assertSame([], $incomplete);
    }

    /** Mỗi menu khai báo đủ nhãn, mô tả, icon và trang tương ứng. */
    public function test_every_menu_permission_is_fully_described(): void
    {
        $incomplete = [];
        $pages = array_keys(config('role_permissions.page_permissions'));

        foreach (config('role_permissions.menu_permissions') as $name => $definition) {
            foreach (['label', 'description', 'icon'] as $key) {
                if (empty($definition[$key])) {
                    $incomplete[] = "{$name}.{$key}";
                }
            }

            $page = $definition['page_permission'] ?? null;

            if ($page !== null && ! in_array($page, $pages, true)) {
                $incomplete[] = "{$name} trỏ tới trang không tồn tại: {$page}";
            }
        }

        $this->assertSame([], $incomplete);
    }

    /** Role khai báo trong config/permissions.php phải khớp role thật. */
    public function test_declared_roles_use_known_role_names(): void
    {
        $protected = config('role_permissions.protected_roles', []);
        $declared = array_keys($this->catalog->defaultsByRole());

        $unknown = array_values(array_diff($declared, $protected));

        $this->assertSame(
            [],
            $unknown,
            'config/permissions.php khai báo role không có trong role_permissions.protected_roles: '
            .implode(', ', $unknown),
        );
    }

    /** Lệnh gán quyền mặc định chỉ THÊM, không bao giờ gỡ quyền đang có. */
    public function test_assign_roles_never_removes_existing_permissions(): void
    {
        $this->artisan('permissions:sync');

        $role = \Spatie\Permission\Models\Role::findOrCreate('sales', 'web');
        Permission::findOrCreate('quyen.rieng.cua.role', 'web');
        $role->givePermissionTo('quyen.rieng.cua.role');

        $this->artisan('permissions:sync --assign-roles');

        $this->assertTrue(
            $role->fresh()->hasPermissionTo('quyen.rieng.cua.role'),
            'Quyền tinh chỉnh tay trên production không được bị gỡ.',
        );
    }
}
