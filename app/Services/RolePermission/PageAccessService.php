<?php

namespace App\Services\RolePermission;

use App\Contracts\Services\PageAccessServiceInterface;
use App\Enums\Role as RoleEnum;
use App\Models\User;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Service kiểm soát quyền truy cập trang theo role/permission (page.*) từ cấu hình role_permissions.
 */
class PageAccessService implements PageAccessServiceInterface
{
    private ?bool $rolesHavePageFlag = null;

    public function __construct(
        private readonly BusinessPermissionCatalog $businessPermissions = new BusinessPermissionCatalog,
    ) {}

    /**
     * Lấy định nghĩa các quyền trang từ config role_permissions.page_permissions.
     */
    public function definitions(): array
    {
        return config('role_permissions.page_permissions', []);
    }

    /**
     * Lấy định nghĩa quyền hiển thị menu từ config role_permissions.menu_permissions.
     */
    public function menuDefinitions(): array
    {
        return config('role_permissions.menu_permissions', []);
    }

    /**
     * Kiểm tra một mục menu có được hiển thị cho người dùng hay không.
     * Admin luôn thấy tất cả. Menu chỉ hiện khi có cả menu.* và page.* liên quan.
     */
    public function canSeeMenu(User $user, string $menuPermission): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $definition = $this->menuDefinitions()[$menuPermission] ?? null;

        if ($definition === null || ! $user->can($menuPermission)) {
            return false;
        }

        $pagePermission = $definition['page_permission'] ?? null;

        return ! $pagePermission || $this->canAccess($user, $pagePermission);
    }

    /**
     * Danh sách menu bị từ chối, dùng để ẩn sidebar ngay từ server render.
     */
    public function deniedMenuPermissions(User $user): array
    {
        return collect(array_keys($this->menuDefinitions()))
            ->reject(fn (string $permission): bool => $this->canSeeMenu($user, $permission))
            ->values()
            ->all();
    }

    /**
     * Xác định quyền trang tương ứng với request theo tên route, path chính xác hoặc prefix.
     */
    public function permissionForRequest(Request $request): ?string
    {
        $routeName = optional($request->route())->getName();
        $path = '/'.ltrim($request->path(), '/');
        $path = $path === '//' ? '/' : $path;

        foreach ($this->definitions() as $permission => $definition) {
            /*
             * EGO_FIX_EXCLUDE_PAGE_ACCESS_V2
             * Cho phép khai báo các URL cá nhân không bị khóa bởi quyền module.
             */
            $excluded = false;

            foreach ($definition['exclude_prefixes'] ?? [] as $rawPrefix) {
                $excludePrefix = '/'.trim($rawPrefix, '/');

                if ($path === $excludePrefix || str_starts_with($path, $excludePrefix.'/')) {
                    $excluded = true;
                    break;
                }
            }

            if ($excluded) {
                continue;
            }

            foreach ($definition['routes'] ?? [] as $pattern) {
                if ($routeName && Str::is($pattern, $routeName)) {
                    return $permission;
                }
            }

            if (in_array($path, $definition['exact_paths'] ?? [], true)) {
                return $permission;
            }

            foreach ($definition['path_prefixes'] ?? [] as $prefix) {
                $prefix = '/'.trim($prefix, '/');
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return $permission;
                }
            }
        }

        return null;
    }

    /**
     * Kiểm tra người dùng có thuộc nhóm role admin theo cấu hình.
     */
    public function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(config('role_permissions.admin_roles', ['admin']));
    }

    /**
     * Kiểm tra có bật kiểm soát quyền trang cho người dùng (admin luôn miễn kiểm soát).
     */
    public function pageControlEnabled(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return false;
        }

        $user->loadMissing('roles');

        /*
        | User KHÔNG có role nào -> luôn kiểm soát chặt.
        |
        | Trước đây nhánh này trả false ("chưa bật kiểm soát") nên canAccess()
        | cho qua mọi trang: trên production có 20 tài khoản không role và họ
        | vào được cả /finance, /nhan-su lẫn /cai-dat — chỉ /orders bị chặn nhờ
        | nằm trong always_enforce_permissions. Không role thì không có quyền
        | nào cả, nên đúng ra phải chặn hết.
        */
        if ($user->roles->isEmpty()) {
            return (bool) config('role_permissions.enforce_users_without_role', true);
        }

        if ($this->rolesHavePageFlag()) {
            return $user->roles->contains(
                fn ($role) => (bool) ($role->page_access_enabled ?? false)
            );
        }

        if (! config('role_permissions.legacy_compatibility', true)) {
            return true;
        }

        return $user->getAllPermissions()
            ->contains(fn ($permission) => str_starts_with($permission->name, 'page.'));
    }

    /**
     * Kiểm tra người dùng được truy cập trang: admin hoặc chưa bật kiểm soát thì luôn cho phép.
     */
    public function canAccess(User $user, string $pagePermission): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        $alwaysEnforce = config('role_permissions.always_enforce_permissions', []);

        if (in_array($pagePermission, $alwaysEnforce, true)) {
            return $user->can($pagePermission);
        }

        if (! $this->pageControlEnabled($user)) {
            return true;
        }

        return $user->can($pagePermission);
    }

    /**
     * Cho phép vượt qua middleware role cũ nếu người dùng có quyền trang tương ứng (legacy fallback).
     */
    public function canSatisfyLegacyRole(User $user, Request $request, string $roleExpression): bool
    {
        if (! $this->pageControlEnabled($user)) {
            return false;
        }

        $allowedExpressions = collect(config('role_permissions.legacy_role_fallbacks', []))
            ->map(fn ($item) => $this->normalizeRoleExpression((string) $item));

        if (! $allowedExpressions->contains($this->normalizeRoleExpression($roleExpression))) {
            return false;
        }

        $permission = $this->permissionForRequest($request);

        return $permission !== null && $user->can($permission);
    }

    /**
     * Lấy danh sách rule các trang người dùng KHÔNG được truy cập (dùng ẩn menu điều hướng).
     */
    public function deniedNavigationRules(User $user): array
    {
        if (! $this->pageControlEnabled($user)) {
            return [];
        }

        $rules = [];

        foreach ($this->definitions() as $permission => $definition) {
            if ($user->can($permission)) {
                continue;
            }

            $rules[] = [
                'permission' => $permission,
                'exact' => array_values($definition['exact_paths'] ?? []),
                'prefixes' => array_values($definition['path_prefixes'] ?? []),
                'exclude_prefixes' => array_values($definition['exclude_prefixes'] ?? []),
            ];
        }

        return $rules;
    }

    /**
     * Gom danh sách permission thành các nhóm hiển thị (nhóm quyền trang đưa lên đầu).
     */
    public function permissionGroups(Collection $permissions): array
    {
        $groups = [];
        $pageDefinitions = $this->definitions();

        foreach ($permissions as $permission) {
            $name = $permission->name;
            $isPage = isset($pageDefinitions[$name]);
            $meta = $isPage
                ? $pageDefinitions[$name]
                : $this->metaForBusinessPermission($name);

            $groupKey = $isPage ? '__page_access' : ($meta['group_key'] ?? 'other');

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'label' => $isPage ? 'Quyền truy cập trang' : ($meta['group_label'] ?? 'Quyền khác'),
                    'icon' => $isPage ? 'bi-grid-1x2' : ($meta['group_icon'] ?? 'bi-shield-check'),
                    'is_page_group' => $isPage,
                    'permissions' => [],
                ];
            }

            $groups[$groupKey]['permissions'][] = [
                'permission' => $permission,
                'label' => $meta['label'] ?? $this->humanize($name),
                'description' => $meta['description'] ?? $name,
                'icon' => $meta['icon'] ?? 'bi-check2-circle',
                'is_page' => $isPage,
            ];
        }

        if (isset($groups['__page_access'])) {
            $pageGroup = $groups['__page_access'];
            unset($groups['__page_access']);
            $groups = ['__page_access' => $pageGroup] + $groups;
        }

        return array_values($groups);
    }

    /**
     * Lấy tên hiển thị tiếng Việt của role (ưu tiên display_name trong DB).
     *
     * Tên tiếng Việt lấy từ {@see \App\Enums\Role} chứ không chép tay ở đây nữa.
     * Bảng `match` cũ đã bỏ sót vai trò mới thêm và làm giao diện hiện tên sinh
     * tự động kiểu "Cskh" lạc lõng giữa các tên tiếng Việt khác.
     */
    public function displayRoleName($role): string
    {
        if (! empty($role->display_name)) {
            return $role->display_name;
        }

        $known = RoleEnum::tryFromName($role->name ?? null);

        if ($known !== null) {
            return $known->label();
        }

        return Str::headline(str_replace(['-', '.'], '_', (string) $role->name));
    }

    /**
     * Xây metadata hiển thị (nhóm, nhãn, icon) cho permission nghiệp vụ theo cấu hình.
     */
    private function metaForBusinessPermission(string $name): array
    {
        $parts = explode('.', $name);
        $prefix = $parts[0] ?? 'other';
        $action = implode('.', array_slice($parts, 1));
        $group = config("role_permissions.group_labels.{$prefix}", [
            'label' => Str::headline(str_replace(['-', '_'], ' ', $prefix)),
            'icon' => 'bi-shield-check',
        ]);

        $actionKey = str_replace('.', '_', $action);
        $actionLabel = config("role_permissions.action_labels.{$actionKey}");

        if (! $actionLabel) {
            $last = end($parts) ?: $name;
            $actionLabel = config('role_permissions.action_labels.'.str_replace('-', '_', $last));
        }

        // Nhãn viết sẵn trong config/permissions.php mô tả rõ nghiệp vụ hơn
        // (vd "Duyệt đơn – Sales Manager (Bước 1)") nên được ưu tiên.
        $declaredLabel = $this->businessPermissions->label($name);

        return [
            'group_key' => $prefix,
            'group_label' => $group['label'],
            'group_icon' => $group['icon'],
            'label' => $declaredLabel ?: ($actionLabel ?: $this->humanize($name)),
            'description' => $declaredLabel ?: $name,
            'icon' => 'bi-check2-circle',
        ];
    }

    /**
     * Chuyển chuỗi kỹ thuật (dấu chấm, gạch) thành dạng tiêu đề dễ đọc.
     */
    private function humanize(string $value): string
    {
        return Str::headline(str_replace(['.', '-', '_'], ' ', $value));
    }

    /**
     * Kiểm tra bảng roles có cột page_access_enabled hay không (cache trong request).
     */
    private function rolesHavePageFlag(): bool
    {
        if ($this->rolesHavePageFlag === null) {
            $this->rolesHavePageFlag = SchemaCache::hasColumn(
                config('permission.table_names.roles', 'roles'),
                'page_access_enabled'
            );
        }

        return $this->rolesHavePageFlag;
    }

    /**
     * Chuẩn hoá biểu thức role (a|b|c): trim, bỏ trùng, sắp xếp để so sánh ổn định.
     */
    private function normalizeRoleExpression(string $expression): string
    {
        $roles = array_values(array_unique(array_filter(array_map(
            fn ($role) => trim($role),
            explode('|', $expression)
        ))));

        sort($roles);

        return implode('|', $roles);
    }
}
