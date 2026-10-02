<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cấp quyền cho hai vai trò mới `cskh` và `assistant`.
 *
 * ## Quyền được suy ra TỪ CHÍNH CÁC ROUTE mà hai tên đó được gắn
 * Trước 2026-08-06 `cskh` và `assistant` chỉ là tên viết trong middleware, không
 * có role thật. Nhưng những route đó là bản ghi cụ thể về việc hai vai này được
 * cho phép làm gì, nên dùng làm căn cứ:
 *
 *   assistant / tro_ly  -> 20 route, TOÀN BỘ là module Tài liệu công ty
 *                          (/company-documents), gắn quyền trang `page.company`
 *   cskh                -> 35 route, TOÀN BỘ là module Bảo trì bảo hành
 *                          (/du-an/bao-tri-bao-hanh, /ky-thuat/bao-tri-bao-hanh),
 *                          gắn `page.projects` (32) + `page.technical` (3)
 *
 * ## Vì sao KHÔNG cấp trọn mọi thứ mà route cho đụng tới
 * Danh sách trong `role:a|b|c` là quyền VÀO MODULE, còn LÀM ĐƯỢC GÌ do lớp
 * permission quyết. Bằng chứng ngay trên production: `sales` cũng đứng trên đúng
 * 35 route bảo trì nhưng chỉ có 2 quyền (`maintenance.view`, `files.view`), còn
 * `technical_manager` có 15. Suy quyền chi tiết từ danh sách role sẽ cấp thừa.
 *
 * Vì vậy:
 *  - `assistant` nhận trọn CRUD của module tài liệu công ty — `company.*` chỉ
 *    ảnh hưởng đúng module đó, và 20 route kia đã gồm cả tạo/sửa/xoá.
 *  - `cskh` nhận `maintenance.*` (phạm vi hẹp, đúng module bảo trì) chứ KHÔNG
 *    nhận `projects.*`/`technical.*` (ảnh hưởng toàn bộ dự án và kỹ thuật).
 *    Cũng KHÔNG nhận nhóm phê duyệt (approve/reject/reopen/request_revision/
 *    assign/settings) — đó là thẩm quyền quản lý, không phải việc chăm sóc khách
 *    hàng. Muốn cấp thêm chỉ cần tick trên /cai-dat/roles/{id}/quyen-thao-tac.
 *
 * Migration chỉ THÊM quyền, không gỡ quyền nào — chạy lại nhiều lần vô hại.
 */
return new class extends Migration
{
    /**
     * Quyền cấp cho từng vai trò.
     *
     * @return array<string, list<string>>
     */
    private function grants(): array
    {
        return [
            Role::Assistant->value => [
                // Vào được trang + thấy menu
                'page.company',
                'menu.company',
                // 20 route gồm xem / tạo thư mục & tệp / sửa / xoá
                'company.view',
                'company.create',
                'company.update',
                'company.delete',
            ],

            Role::CustomerService->value => [
                // Vào được hai trang chứa module bảo trì
                'page.projects',
                'page.technical',
                'menu.technical',
                // Thao tác trong module bảo trì — KHÔNG gồm nhóm phê duyệt
                'maintenance.view',
                'maintenance.create',
                'maintenance.update',
                'maintenance.submit',
                'maintenance.files.view',
                'maintenance.files.upload',
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        foreach ($this->grants() as $roleName => $permissionNames) {
            $roleId = DB::table('roles')
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if ($roleId === null) {
                continue; // migration đổi tên/tạo role chưa chạy trên bản này
            }

            foreach ($permissionNames as $permissionName) {
                $permissionId = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->value('id');

                if ($permissionId === null) {
                    /*
                    | Quyền chưa tồn tại trên bản triển khai này. KHÔNG tự tạo:
                    | danh mục quyền do `permissions:sync` quản, tạo lén ở đây sẽ
                    | sinh quyền mồ côi không ai biết từ đâu ra.
                    */
                    continue;
                }

                $already = DB::table('role_has_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionId)
                    ->exists();

                if (! $already) {
                    DB::table('role_has_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }

            // Có quyền trang thì phải bật kiểm soát trang, nếu không quyền vô nghĩa.
            if (Schema::hasColumn('roles', 'page_access_enabled')) {
                DB::table('roles')->where('id', $roleId)->update([
                    'page_access_enabled' => 1,
                    'updated_at' => now(),
                ]);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        foreach ($this->grants() as $roleName => $permissionNames) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if ($roleId === null) {
                continue;
            }

            $permissionIds = DB::table('permissions')->whereIn('name', $permissionNames)->pluck('id');

            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
