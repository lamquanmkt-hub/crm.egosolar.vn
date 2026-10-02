<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Ba màn phân quyền phải hiển thị ĐÚNG vai trò ghi trong đường dẫn.
 *
 * ## Lỗi đã xảy ra thật, và nó nguy hiểm
 * Khi chuyển sang REST (2026-08-05), URL đổi từ `?role=22` sang
 * `/cai-dat/roles/22/quyen-thao-tac`, nhưng controller vẫn lấy vai trò bằng
 * `$request->integer('role')` — hàm này KHÔNG đọc tham số đường dẫn. Mọi URL
 * dạng mới đều rơi về vai trò `admin`.
 *
 * Người dùng thấy triệu chứng nhẹ ("không tô sáng vai trò đang chọn"), nhưng
 * hậu quả thật nặng hơn: các ô tick hiện ra là quyền của ADMIN, mà nút Lưu lại
 * nhắm đúng vai trò trong đường dẫn — mở rồi bấm Lưu là cấp trọn quyền admin
 * cho vai trò đó.
 *
 * Nhóm test này khoá cả hai chiều: đúng vai trò theo đường dẫn, và vẫn tương
 * thích URL cũ dùng query string.
 */
final class RolePermissionScreenTest extends TestCase
{
    use DatabaseTransactions;

    /** Ba màn phân quyền, dạng có vai trò trong đường dẫn. */
    public static function screens(): array
    {
        return [
            'quyền trang' => ['quyen-trang'],
            'quyền menu' => ['quyen-menu'],
            'quyền thao tác' => ['quyen-thao-tac'],
        ];
    }

    #[DataProvider('screens')]
    public function test_screen_selects_the_role_from_the_url_path(string $screen): void
    {
        $admin = $this->userWithRole('admin');
        $target = Role::findOrCreate('vai_tro_kiem_thu', 'web');

        $response = $this->actingAs($admin)->get('/cai-dat/roles/'.$target->id.'/'.$screen);

        $response->assertOk();

        $selected = $response->original->getData()['selectedRole'] ?? null;

        $this->assertNotNull($selected, 'Màn hình phải xác định được vai trò đang xem.');
        $this->assertSame(
            $target->id,
            $selected->id,
            'Màn hình đang hiện vai trò KHÁC vai trò ghi trong đường dẫn — mở rồi bấm Lưu sẽ ghi nhầm quyền.',
        );
    }

    /** URL cũ dùng query string vẫn phải chọn đúng vai trò. */
    public function test_screen_still_supports_the_query_string_form(): void
    {
        $admin = $this->userWithRole('admin');
        $target = Role::findOrCreate('vai_tro_kiem_thu_qs', 'web');

        $response = $this->actingAs($admin)->get('/cai-dat/quyen-trang?role='.$target->id);

        $response->assertOk();

        $this->assertSame($target->id, $response->original->getData()['selectedRole']->id);
    }

    /**
     * Ô tick hiển thị phải là quyền của CHÍNH vai trò đó.
     *
     * Đây là phép đo trực tiếp cái đã gây hại: trước khi sửa, danh sách quyền
     * đánh dấu sẵn là của admin.
     */
    public function test_screen_shows_the_permissions_of_that_role_only(): void
    {
        $admin = $this->userWithRole('admin');

        $adminRole = Role::findOrCreate('admin', 'web');
        $adminOnly = Permission::findOrCreate('page.chi_admin_moi_co', 'web');
        $adminRole->givePermissionTo($adminOnly);

        $target = Role::findOrCreate('vai_tro_khong_quyen', 'web');
        $target->syncPermissions([]);

        $response = $this->actingAs($admin)->get('/cai-dat/roles/'.$target->id.'/quyen-trang');

        $response->assertOk();

        /** @var list<string> $selectedNames */
        $selectedNames = $response->original->getData()['selectedPermissionNames'] ?? [];

        $this->assertNotContains(
            $adminOnly->name,
            $selectedNames,
            'Đang hiện quyền của admin trên màn của vai trò khác — bấm Lưu là cấp nhầm quyền.',
        );
        $this->assertSame([], $selectedNames, 'Vai trò chưa có quyền nào thì không được tick sẵn ô nào.');
    }

    /** Vào màn không kèm vai trò thì mặc định về admin, giữ như trước. */
    public function test_screen_without_a_role_falls_back_to_admin(): void
    {
        $admin = $this->userWithRole('admin');
        Role::findOrCreate('admin', 'web');

        $response = $this->actingAs($admin)->get('/cai-dat/quyen-trang');

        $response->assertOk();
        $this->assertSame('admin', $response->original->getData()['selectedRole']->name);
    }
}
