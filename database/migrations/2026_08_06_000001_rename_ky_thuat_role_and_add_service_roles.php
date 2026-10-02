<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Đổi tên role `ky_thuat` -> `technical`, và tạo hai role còn thiếu: `cskh`, `assistant`.
 *
 * ## Vì sao ĐỔI TÊN chứ không xoá rồi tạo lại
 * Đổi tên giữ nguyên `roles.id`, nhờ vậy toàn bộ `role_has_permissions` (37 quyền)
 * và `model_has_roles` (người đã được gán) còn nguyên. Xoá rồi tạo lại sẽ mất sạch
 * cả hai, và không có cách nào khôi phục ngoài bản sao lưu.
 *
 * ## Vì sao đổi `ky_thuat` thành `technical`
 * Trong middleware, `ky_thuat` gần như luôn đứng kèm bốn tên tiếng Anh
 * `technical|technician|technical_staff|technical_leader` — cả bốn đều KHÔNG tồn
 * tại trong DB, chỉ là nhiễu. Đặt tên thật là `technical` thì bốn tên kia bỏ đi
 * được, và nhất quán với `technical_manager` vốn đã là tiếng Anh.
 *
 * ## Hai role mới tạo RỖNG quyền
 * Cố ý: cấp quyền cho ai là quyết định nghiệp vụ, làm trên giao diện
 * `/cai-dat/roles/{id}/quyen-trang`. Migration chỉ tạo chỗ đứng cho họ — trước đó
 * `cskh` được nhắc ở 35 route và `assistant` ở 20 route mà không hề có role thật.
 *
 * ⚠️ Chưa gán role mới cho ai, nên việc tạo rỗng không khoá ai cả.
 */
return new class extends Migration
{
    private const OLD_TECHNICAL_NAME = 'ky_thuat';

    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->renameTechnicalRole();

        foreach ([Role::CustomerService, Role::Assistant] as $role) {
            $this->createRoleIfMissing($role);
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        // Trả tên cũ, chỉ khi tên cũ chưa bị ai dùng lại.
        $technical = DB::table('roles')->where('name', Role::Technical->value)->first();
        $oldTaken = DB::table('roles')->where('name', self::OLD_TECHNICAL_NAME)->exists();

        if ($technical !== null && ! $oldTaken) {
            DB::table('roles')->where('id', $technical->id)->update([
                'name' => self::OLD_TECHNICAL_NAME,
                'updated_at' => now(),
            ]);
        }

        /*
        | KHÔNG xoá `cskh` và `assistant` khi rollback: nếu đã có người được gán
        | hoặc đã được cấp quyền thì xoá là mất dữ liệu thật. Role rỗng không gán
        | cho ai thì cũng vô hại nếu còn lại.
        */
        $this->forgetPermissionCache();
    }

    /**
     * Đổi tên role kỹ thuật, xử lý cả trường hợp đã chạy rồi hoặc trùng tên.
     */
    private function renameTechnicalRole(): void
    {
        $old = DB::table('roles')->where('name', self::OLD_TECHNICAL_NAME)->first();

        if ($old === null) {
            return; // đã đổi rồi, hoặc bản triển khai này chưa từng có role đó
        }

        $existingNew = DB::table('roles')
            ->where('name', Role::Technical->value)
            ->where('guard_name', $old->guard_name)
            ->first();

        if ($existingNew !== null) {
            /*
            | Đã tồn tại sẵn role `technical` khác — không tự ý gộp, vì gộp quyền
            | và người dùng của hai role là quyết định nghiệp vụ. Dừng ở đây và
            | báo rõ để xử lý tay.
            */
            throw new RuntimeException(sprintf(
                'Đã có sẵn role "%s" (id=%d) nên không thể đổi tên "%s" (id=%d). '.
                'Cần quyết định gộp hai role này bằng tay trước khi chạy migration.',
                Role::Technical->value,
                $existingNew->id,
                self::OLD_TECHNICAL_NAME,
                $old->id,
            ));
        }

        DB::table('roles')->where('id', $old->id)->update([
            'name' => Role::Technical->value,
            'display_name' => $old->display_name ?: Role::Technical->label(),
            'updated_at' => now(),
        ]);
    }

    private function createRoleIfMissing(Role $role): void
    {
        $exists = DB::table('roles')
            ->where('name', $role->value)
            ->where('guard_name', 'web')
            ->exists();

        if ($exists) {
            return;
        }

        $columns = [
            'name' => $role->value,
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Các cột mở rộng chỉ có ở bản triển khai đã chạy migration cài đặt phân quyền.
        foreach ([
            'display_name' => $role->label(),
            'description' => $role->description(),
            'is_system' => 0,
            'page_access_enabled' => 1,
        ] as $column => $value) {
            if (Schema::hasColumn('roles', $column)) {
                $columns[$column] = $value;
            }
        }

        DB::table('roles')->insert($columns);
    }

    /**
     * Spatie giữ bảng phân quyền trong cache; không xoá thì tên cũ còn sống tiếp.
     */
    private function forgetPermissionCache(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
