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
 * Kiểm chứng migration đổi tên `ky_thuat` -> `technical` trên production.
 *
 * ## Vì sao phải test một migration
 * Migration này ĐỘNG VÀO DỮ LIỆU THẬT của một role đang có 37 quyền và người
 * dùng đang gán. Nếu nó xoá rồi tạo lại thay vì đổi tên tại chỗ, toàn bộ
 * `role_has_permissions` và `model_has_roles` biến mất, và chỉ khôi phục được
 * bằng bản sao lưu. Chạy thử một lần trên production rồi mới biết là quá muộn.
 *
 * DB test không có sẵn role `ky_thuat` (nó chỉ có schema), nên test tự dựng lại
 * đúng tình huống production rồi gọi thẳng `up()` của migration.
 */
final class RenameTechnicalRoleMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private const MIGRATION = 'database/migrations/2026_08_06_000001_rename_ky_thuat_role_and_add_service_roles.php';

    private function migration(): object
    {
        return require base_path(self::MIGRATION);
    }

    /** Đổi tên phải GIỮ nguyên id, quyền và người đã gán. */
    public function test_rename_keeps_permissions_and_members(): void
    {
        $old = SpatieRole::findOrCreate('ky_thuat', 'web');
        $old->givePermissionTo(Permission::findOrCreate('maintenance.view', 'web'));
        $old->givePermissionTo(Permission::findOrCreate('material-requests.create', 'web'));

        $member = $this->userWithRole('ky_thuat');
        $originalId = $old->id;

        $this->migration()->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $renamed = SpatieRole::find($originalId);

        $this->assertNotNull($renamed, 'Role đã biến mất — migration xoá thay vì đổi tên.');
        $this->assertSame(Role::Technical->value, $renamed->name);
        $this->assertSame(
            $originalId,
            $renamed->id,
            'Id đổi nghĩa là role bị tạo lại, mọi liên kết cũ đã mất.',
        );

        $this->assertTrue($renamed->hasPermissionTo('maintenance.view'), 'Mất quyền sau khi đổi tên.');
        $this->assertTrue($renamed->hasPermissionTo('material-requests.create'), 'Mất quyền sau khi đổi tên.');

        $this->assertTrue(
            $member->fresh()->hasRole(Role::Technical->value),
            'Người dùng rơi khỏi vai trò sau khi đổi tên.',
        );

        $this->assertNull(
            SpatieRole::where('name', 'ky_thuat')->first(),
            'Tên cũ vẫn còn — sẽ có hai vai trò kỹ thuật song song.',
        );
    }

    /** Tạo hai vai trò còn thiếu, và tạo RỖNG quyền. */
    public function test_creates_the_two_missing_service_roles(): void
    {
        SpatieRole::where('name', Role::CustomerService->value)->delete();
        SpatieRole::where('name', Role::Assistant->value)->delete();

        $this->migration()->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([Role::CustomerService, Role::Assistant] as $role) {
            $created = SpatieRole::where('name', $role->value)->first();

            $this->assertNotNull($created, $role->value.' chưa được tạo.');
            $this->assertSame($role->label(), $created->display_name);
            $this->assertCount(
                0,
                $created->permissions,
                $role->value.' phải được tạo RỖNG quyền — cấp quyền là quyết định nghiệp vụ, làm trên giao diện.',
            );
        }
    }

    /** Chạy lại nhiều lần không được sinh vai trò trùng. */
    public function test_migration_is_safe_to_run_twice(): void
    {
        $this->migration()->up();
        $this->migration()->up();

        foreach ([Role::Technical, Role::CustomerService, Role::Assistant] as $role) {
            $this->assertLessThanOrEqual(
                1,
                DB::table('roles')->where('name', $role->value)->where('guard_name', 'web')->count(),
                $role->value.' bị tạo trùng khi chạy migration lần hai.',
            );
        }
    }

    /**
     * Nếu đã tồn tại sẵn một role tên `technical` khác thì DỪNG, không tự gộp.
     *
     * Gộp quyền và người dùng của hai vai trò là quyết định nghiệp vụ; migration
     * âm thầm gộp hộ là cách nhanh nhất để cấp nhầm quyền cho cả một nhóm.
     */
    public function test_migration_refuses_to_merge_two_technical_roles(): void
    {
        SpatieRole::findOrCreate('ky_thuat', 'web');
        SpatieRole::findOrCreate(Role::Technical->value, 'web');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/không thể đổi tên/');

        $this->migration()->up();
    }
}
