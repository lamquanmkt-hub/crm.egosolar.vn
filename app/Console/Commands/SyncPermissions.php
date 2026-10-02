<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RolePermission\ActionPermissionRegistry;
use App\Services\RolePermission\BusinessPermissionCatalog;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Đồng bộ toàn bộ quyền của hệ thống từ config vào DB.
 *
 * ## Vì sao có lệnh này
 * Trước đó KHÔNG có cơ chế nào chạy được để tạo quyền từ config:
 * - `PermissionSeeder` đọc `App\Enums\Permission` (49 quyền, đã lạc hậu so với
 *   102 quyền thật) và không được đăng ký trong `DatabaseSeeder`;
 * - `RolePermissionSeeder` được đăng ký nhưng toàn bộ thân hàm bị comment.
 * Kết quả: quyền mới phải tạo tay trong DB, đúng/thiếu tuỳ người nhớ.
 *
 * ## Ba nguồn quyền (đều là config, không hard-code)
 * 1. `role_permissions.page_permissions` → quyền trang `page.*`
 * 2. `role_permissions.menu_permissions` → quyền menu `menu.*`
 * 3. Quyền thao tác:
 *    - CRUD chuẩn cho từng trang do {@see ActionPermissionRegistry} sinh
 *    - Quyền nghiệp vụ chuyên biệt khai báo ở `config/permissions.php`
 *
 * ## An toàn
 * Chỉ THÊM quyền còn thiếu, không bao giờ xoá — role đang gán quyền cũ. Quyền
 * có trong DB nhưng không khai báo ở đâu sẽ được BÁO CÁO để người xử lý.
 *
 *   php artisan permissions:sync
 *   php artisan permissions:sync --dry-run
 *   php artisan permissions:sync --assign-roles   # gán quyền mặc định theo config/permissions.php
 */
final class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
        {--dry-run : Chỉ liệt kê, không ghi DB}
        {--assign-roles : Gán thêm quyền mặc định cho role theo config/permissions.php (chỉ thêm, không gỡ)}';

    protected $description = 'Đồng bộ quyền trang/menu/thao tác từ config vào DB (chỉ thêm quyền thiếu)';

    public function handle(
        ActionPermissionRegistry $registry,
        BusinessPermissionCatalog $catalog,
    ): int {
        $guard = (string) config('auth.defaults.guard', 'web');

        $expected = $this->expectedPermissions($registry, $catalog);
        $existing = Permission::query()->where('guard_name', $guard)->pluck('name')->all();
        $missing = array_values(array_diff($expected, $existing));

        $this->components->info(sprintf(
            'Khai báo %d quyền (trang %d, menu %d, CRUD %d, nghiệp vụ %d) — DB đang có %d, thiếu %d.',
            count($expected),
            count((array) config('role_permissions.page_permissions', [])),
            count((array) config('role_permissions.menu_permissions', [])),
            count($registry->permissionNames()),
            count($catalog->names()),
            count($existing),
            count($missing),
        ));

        foreach ($missing as $name) {
            $this->line('  + '.$name);
        }

        if ($this->option('dry-run')) {
            $this->components->warn('--dry-run: chưa ghi gì vào DB.');
            $this->reportUndeclared($catalog, $registry, $existing);

            return self::SUCCESS;
        }

        // Ghi theo lô thay vì `findOrCreate` từng quyền: mỗi findOrCreate là 2 truy vấn cộng
        // một lần xoá cache của Spatie, ~150 quyền mất ~1 s (8 test gọi lệnh này = 8 s).
        // `$missing` đã đối chiếu với DB nên không thể trùng; cache xoá MỘT lần ở dưới.
        $now = now();
        foreach (array_chunk($missing, 200) as $chunk) {
            Permission::query()->insert(array_map(
                static fn (string $name): array => [
                    'name' => $name, 'guard_name' => $guard, 'created_at' => $now, 'updated_at' => $now,
                ],
                $chunk,
            ));
        }

        if ($missing !== []) {
            $this->components->info('Đã tạo '.count($missing).' quyền.');
        }

        if ($this->option('assign-roles')) {
            $this->assignRoleDefaults($catalog, $guard);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->reportUndeclared($catalog, $registry, array_merge($existing, $missing));

        return self::SUCCESS;
    }

    /**
     * Toàn bộ tên quyền được khai báo trong config.
     *
     * @return list<string>
     */
    private function expectedPermissions(
        ActionPermissionRegistry $registry,
        BusinessPermissionCatalog $catalog,
    ): array {
        return array_values(array_unique(array_merge(
            array_keys((array) config('role_permissions.page_permissions', [])),
            array_keys((array) config('role_permissions.menu_permissions', [])),
            $registry->permissionNames(),
            $catalog->names(),
        )));
    }

    /**
     * Gán quyền mặc định cho role theo `config/permissions.php` — CHỈ THÊM.
     *
     * Không gỡ quyền nào: role trên production đã được tinh chỉnh tay, gỡ theo
     * config sẽ xoá mất công sức đó.
     */
    private function assignRoleDefaults(BusinessPermissionCatalog $catalog, string $guard): void
    {
        foreach ($catalog->defaultsByRole() as $roleName => $permissions) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();

            if ($role === null) {
                $this->components->warn("Bỏ qua role '{$roleName}' — chưa tồn tại trong DB.");

                continue;
            }

            $current = $role->permissions->pluck('name')->all();
            $added = array_values(array_diff(array_keys($permissions), $current));

            if ($added === []) {
                continue;
            }

            $role->givePermissionTo($added);
            $this->components->info(sprintf('Role %s: thêm %d quyền mặc định.', $roleName, count($added)));
        }
    }

    /**
     * Báo cáo quyền có trong DB nhưng không khai báo ở config nào.
     *
     * @param  list<string>  $namesInDatabase
     */
    private function reportUndeclared(
        BusinessPermissionCatalog $catalog,
        ActionPermissionRegistry $registry,
        array $namesInDatabase,
    ): void {
        $undeclared = $catalog->undeclared($namesInDatabase, $registry->permissionNames());

        if ($undeclared === []) {
            return;
        }

        $this->newLine();
        $this->components->warn(sprintf(
            '%d quyền có trong DB nhưng KHÔNG khai báo ở config nào (không tự xoá — role có thể đang dùng):',
            count($undeclared),
        ));

        foreach ($undeclared as $name) {
            $this->line('  ? '.$name);
        }
    }
}
