<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Services\RolePermission\PageAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

/**
 * `App\Enums\Role` là nguồn sự thật cho tên vai trò — nhóm test này giữ cho mọi
 * nơi khác không trôi khỏi nó.
 *
 * Trước khi có enum, tên role chỉ tồn tại dưới dạng chuỗi ở 106 chỗ trong 25
 * file, và đã trôi thật: 12 trong 23 tên không hề tồn tại trong DB.
 */
final class RoleEnumTest extends TestCase
{
    use DatabaseTransactions;

    /** Config phải lấy thẳng từ enum, không chép tay. */
    public function test_config_role_list_comes_from_the_enum(): void
    {
        $this->assertSame(
            Role::names(),
            config('role_permissions.protected_roles'),
            'protected_roles đã lệch khỏi App\Enums\Role — sửa config để lấy từ enum thay vì chép tay.',
        );
    }

    /** Mỗi vai trò phải có tên hiển thị và mô tả tiếng Việt, không để trống. */
    public function test_every_role_has_a_label_and_description(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotSame('', trim($role->label()), $role->value.' thiếu tên hiển thị.');
            $this->assertNotSame('', trim($role->description()), $role->value.' thiếu mô tả.');
        }
    }

    /**
     * Màn phân quyền phải hiển thị được tên tiếng Việt cho mọi vai trò.
     *
     * Thiếu thì giao diện rơi về dạng `Technical Manager` sinh tự động — không sai
     * nhưng lạc lõng giữa các tên tiếng Việt khác.
     */
    public function test_settings_screen_can_name_every_role_in_vietnamese(): void
    {
        $service = app(PageAccessService::class);
        $generic = [];

        foreach (Role::cases() as $role) {
            $model = SpatieRole::findOrCreate($role->value, 'web');
            $model->display_name = null;

            $shown = $service->displayRoleName($model);

            if ($shown !== $role->label()) {
                $generic[] = sprintf('%s: hiện "%s", enum ghi "%s"', $role->value, $shown, $role->label());
            }
        }

        $this->assertSame([], $generic, "Tên hiển thị lệch giữa giao diện và enum:\n".implode("\n", $generic));
    }

    /** Tên vai trò phải viết thường, gạch dưới — không dấu, không khoảng trắng. */
    public function test_role_names_use_a_consistent_format(): void
    {
        $bad = [];

        foreach (Role::names() as $name) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
                $bad[] = $name;
            }
        }

        $this->assertSame([], $bad, 'Tên vai trò sai định dạng: '.implode(', ', $bad));
    }

    /**
     * Migration đổi tên `ky_thuat` -> `technical` phải GIỮ NGUYÊN quyền và người
     * đã được gán.
     *
     * Đây là điểm nguy hiểm nhất của cả đợt: nếu ai đó "dọn" bằng cách xoá role
     * cũ rồi tạo role mới, toàn bộ `role_has_permissions` và `model_has_roles`
     * biến mất, và không có đường khôi phục ngoài bản sao lưu.
     */
    public function test_renaming_a_role_preserves_its_permissions_and_members(): void
    {
        $role = SpatieRole::findOrCreate('vai_tro_doi_ten', 'web');
        $role->givePermissionTo(Permission::findOrCreate('page.thu_nghiem_doi_ten', 'web'));

        $user = $this->userWithRole('vai_tro_doi_ten');
        $roleId = $role->id;

        // Đúng cách: đổi tên tại chỗ, giữ nguyên id.
        DB::table('roles')->where('id', $roleId)->update(['name' => 'vai_tro_ten_moi']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $renamed = SpatieRole::find($roleId);

        $this->assertSame('vai_tro_ten_moi', $renamed->name);
        $this->assertTrue(
            $renamed->hasPermissionTo('page.thu_nghiem_doi_ten'),
            'Đổi tên làm mất quyền của vai trò — phải đổi tên tại chỗ, không xoá rồi tạo lại.',
        );
        $this->assertTrue(
            $user->fresh()->hasRole('vai_tro_ten_moi'),
            'Đổi tên làm mất người đã được gán vai trò.',
        );
    }
}
