<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\RolePermission\PageAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Hai lỗ phân quyền có thật trên production, đã vá:
 *
 * 1. Kiểm tra `is_active` lúc đăng nhập bị comment ⇒ 20 tài khoản đã vô hiệu
 *    hoá vẫn đăng nhập được.
 * 2. `pageControlEnabled()` trả false cho user KHÔNG có role ⇒ họ vào được gần
 *    như mọi trang, kể cả /cai-dat, vì `canAccess()` hiểu là "chưa bật kiểm
 *    soát quyền trang".
 *
 * Cộng lại: tài khoản đã khoá vẫn vào được trang Cài đặt hệ thống.
 */
final class AccessControlHardeningTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Tài khoản bị vô hiệu hoá không đăng nhập được — KHI đã bật cờ.
     *
     * Cờ mặc định TẮT vì dữ liệu is_active trên production chưa đáng tin
     * (có tài khoản admin is_active=0 nhưng vẫn đang dùng hệ thống).
     */
    public function test_deactivated_account_cannot_log_in_when_enforced(): void
    {
        config(['role_permissions.enforce_active_account_on_login' => true]);

        $user = User::factory()->create([
            'password' => Hash::make('mat-khau-dung'),
            'is_active' => 0,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'mat-khau-dung'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    /** Mặc định (cờ tắt): tài khoản is_active=0 vẫn đăng nhập được như trước. */
    public function test_deactivated_account_can_log_in_while_flag_is_off(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('mat-khau-dung'),
            'is_active' => 0,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'mat-khau-dung'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    /** Tài khoản đang hoạt động vẫn đăng nhập bình thường. */
    public function test_active_account_can_still_log_in(): void
    {
        config(['role_permissions.enforce_active_account_on_login' => true]);

        $user = User::factory()->create([
            'password' => Hash::make('mat-khau-dung'),
            'is_active' => 1,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'mat-khau-dung'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    /** Sai mật khẩu vẫn báo lỗi như cũ (không lộ tài khoản có tồn tại hay không). */
    public function test_wrong_password_is_still_rejected(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('mat-khau-dung'),
            'is_active' => 1,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'sai-mat-khau'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    /** User không có role nào thì BỊ kiểm soát quyền trang. */
    public function test_user_without_any_role_is_page_controlled(): void
    {
        $user = User::factory()->create(['is_active' => 1]);
        $service = app(PageAccessService::class);

        $this->assertTrue($service->pageControlEnabled($user));

        foreach (['page.finance', 'page.hr', 'page.customers', 'page.settings'] as $permission) {
            $this->assertFalse(
                $service->canAccess($user, $permission),
                "User không role KHÔNG được phép truy cập {$permission}.",
            );
        }
    }

    /** Vào thẳng URL bằng tài khoản không role bị chặn 403. */
    public function test_user_without_role_is_blocked_from_settings_page(): void
    {
        $user = User::factory()->create(['is_active' => 1]);

        $this->actingAs($user)->get('/nhan-su')->assertForbidden();
    }

    /**
     * Có role kèm quyền trang thì vào được đúng trang được cấp.
     *
     * Lưu ý: `roles.page_access_enabled` là công tắc bật kiểm soát quyền trang
     * cho từng role. Trên production cả 11 role đều đang bật.
     */
    public function test_user_with_role_and_permission_still_has_access(): void
    {
        $role = Role::findOrCreate('nhan_su_test', 'web');
        $role->forceFill(['page_access_enabled' => 1])->save();
        Permission::findOrCreate('page.hr', 'web');
        $role->givePermissionTo('page.hr');

        $user = User::factory()->create(['is_active' => 1]);
        $user->assignRole($role);

        $service = app(PageAccessService::class);

        $this->assertTrue($service->canAccess($user, 'page.hr'));
        $this->assertFalse($service->canAccess($user, 'page.finance'));
    }

    /** Admin vẫn đi qua mọi cửa. */
    public function test_admin_is_unaffected(): void
    {
        $admin = $this->userWithRole('admin', ['is_active' => 1]);
        $service = app(PageAccessService::class);

        $this->assertFalse($service->pageControlEnabled($admin));
        $this->assertTrue($service->canAccess($admin, 'page.settings'));
    }

    /** Tắt cờ cấu hình thì quay lại hành vi cũ (đường lui khi cần gấp). */
    public function test_config_flag_can_restore_legacy_behaviour(): void
    {
        config(['role_permissions.enforce_users_without_role' => false]);

        $user = User::factory()->create(['is_active' => 1]);

        $this->assertFalse(app(PageAccessService::class)->pageControlEnabled($user));
    }
}
