<?php

declare(strict_types=1);

namespace App\Services\RolePermission;

use App\Enums\PermissionAction;

/**
 * Nguồn duy nhất định nghĩa quyền thao tác (CRUD) của TỪNG trang.
 *
 * ## Vấn đề đang giải
 * Quyền thao tác trước đây đặt tên tuỳ hứng theo từng đợt phát triển:
 * `products.manage` gộp cả 4 việc, `payment` chỉ có create/view, `brands`,
 * `categories`, `price-tiers` mỗi cái đúng 1 quyền `.manage`, trong khi
 * `maintenance` lại tách thành 17 quyền. Màn "Quyền thao tác" vì thế là một
 * danh sách phẳng 102 dòng, không đối chiếu được và không trả lời nổi câu hỏi
 * cơ bản: "role này có được xoá đơn hàng không?".
 *
 * ## Cách làm
 * Lấy đúng danh sách trang từ `role_permissions.page_permissions` (nguồn đã
 * dùng cho màn "Phân quyền trang") rồi sinh ra 4 quyền cho mỗi trang:
 * `<module>.view|create|update|delete`. Nhờ vậy ba màn hình phân quyền
 * (trang / menu / thao tác) luôn nói về CÙNG một danh sách module.
 *
 * Quyền cũ KHÔNG bị xoá: role đang dùng chúng, và nhiều quyền cũ diễn tả
 * nghiệp vụ riêng mà CRUD không phủ được (duyệt đơn, chốt công nợ, phân
 * công...). Chúng được gom vào nhóm "Quyền nghiệp vụ chuyên biệt" tách riêng.
 */
final class ActionPermissionRegistry
{
    /**
     * Trang không cần đủ 4 thao tác — chỉ xem.
     *
     * @var array<string, list<string>> module => danh sách action áp dụng
     */
    private const ACTION_OVERRIDES = [
        'dashboard' => ['view'],
        'chat' => ['view', 'create', 'delete'],
        'solar' => ['view', 'create'],
    ];

    /**
     * Toàn bộ module lấy từ định nghĩa quyền trang.
     *
     * @return array<string, array{key: string, label: string, icon: string, page_permission: string}>
     */
    public function modules(): array
    {
        $modules = [];

        foreach ((array) config('role_permissions.page_permissions', []) as $pagePermission => $definition) {
            $key = $this->moduleKey((string) $pagePermission);

            $modules[$key] = [
                'key' => $key,
                'label' => (string) ($definition['label'] ?? $key),
                'icon' => (string) ($definition['icon'] ?? 'bi-grid'),
                'page_permission' => (string) $pagePermission,
            ];
        }

        return $modules;
    }

    /**
     * Ma trận quyền thao tác: mỗi module kèm 4 (hoặc ít hơn) quyền CRUD.
     *
     * @return list<array{
     *     key: string,
     *     label: string,
     *     icon: string,
     *     page_permission: string,
     *     actions: list<array{name: string, action: string, label: string, description: string, icon: string, destructive: bool}>
     * }>
     */
    public function matrix(): array
    {
        $matrix = [];

        foreach ($this->modules() as $module) {
            $actions = [];

            foreach ($this->actionsFor($module['key']) as $action) {
                $actions[] = [
                    'name' => $this->permissionName($module['key'], $action),
                    'action' => $action->value,
                    'label' => $action->label(),
                    'description' => $action->description(mb_strtolower($module['label'])),
                    'icon' => $action->icon(),
                    'destructive' => $action->isDestructive(),
                ];
            }

            $matrix[] = $module + ['actions' => $actions];
        }

        return $matrix;
    }

    /**
     * Toàn bộ tên quyền thao tác chuẩn (dùng để đồng bộ vào DB).
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        $names = [];

        foreach ($this->matrix() as $module) {
            foreach ($module['actions'] as $action) {
                $names[] = $action['name'];
            }
        }

        return $names;
    }

    /**
     * Tên quyền của một thao tác trên một module.
     */
    public function permissionName(string $moduleKey, PermissionAction $action): string
    {
        return $moduleKey.'.'.$action->value;
    }

    /**
     * Quyền này có phải quyền CRUD chuẩn do registry sinh ra không.
     */
    public function isManaged(string $permissionName): bool
    {
        return in_array($permissionName, $this->permissionNames(), true);
    }

    /**
     * Các thao tác áp dụng cho một module (mặc định đủ 4).
     *
     * @return list<PermissionAction>
     */
    private function actionsFor(string $moduleKey): array
    {
        $allowed = self::ACTION_OVERRIDES[$moduleKey] ?? null;

        if ($allowed === null) {
            return PermissionAction::ordered();
        }

        return array_values(array_filter(
            PermissionAction::ordered(),
            static fn (PermissionAction $action): bool => in_array($action->value, $allowed, true),
        ));
    }

    /**
     * `page.orders` -> `orders`.
     */
    private function moduleKey(string $pagePermission): string
    {
        return str_starts_with($pagePermission, 'page.')
            ? substr($pagePermission, 5)
            : $pagePermission;
    }
}
