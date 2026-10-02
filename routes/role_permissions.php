<?php

/*
|---------------------------------------------------------------------------
| Route: Cài đặt vai trò & phân quyền — đã chuyển sang REST (2026-08-05)
|---------------------------------------------------------------------------
|
| ## Vi phạm REST của bản cũ
| Tài nguyên được định danh bằng QUERY STRING: `/cai-dat/phan-quyen-trang?role=20`.
| Vai trò là tài nguyên, phải nằm trong đường dẫn:
| `/cai-dat/roles/{role}/quyen-trang`.
| Ngoài ra `POST /roles/{role}/clone` nhét động từ vào URL.
|
| ## Cách chuyển KHÔNG gãy gì
| 1. **Giữ nguyên TÊN route.** Blade gọi `route('admin.settings.pages', ...)`
|    nên chỉ cần đổi URI, không phải sửa view.
| 2. Mỗi màn có HAI đường vào:
|    - không kèm vai trò (`/cai-dat/quyen-trang`) cho link sidebar;
|    - kèm vai trò (`/cai-dat/roles/{role}/quyen-trang`) là dạng chuẩn.
| 3. KHÔNG làm lớp redirect cho URL cũ — đây là trang quản trị nội bộ, không có
|    bookmark ngoài (đã xác nhận với chủ hệ thống).
|
*/

use App\Http\Controllers\Admin\AppearanceSettingsController;
use App\Http\Controllers\Admin\RolePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])
    ->prefix('cai-dat')
    ->name('admin.settings.')
    ->controller(RolePermissionController::class)
    ->group(function () {
        Route::get('/', 'overview')->name('index');

        /* EGO_APPEARANCE_SETTINGS_V2_START */
        Route::get('/giao-dien-thuong-hieu', [AppearanceSettingsController::class, 'index'])
            ->name('appearance');
        Route::post('/giao-dien-thuong-hieu', [AppearanceSettingsController::class, 'update'])
            ->name('appearance.update');
        Route::delete('/giao-dien-thuong-hieu/reset', [AppearanceSettingsController::class, 'reset'])
            ->name('appearance.reset');
        /* EGO_APPEARANCE_SETTINGS_V2_END */

        Route::get('/vai-tro', 'roles')->name('roles');
        Route::get('/nhat-ky', 'audit')->name('audit');

        /*
        | Ba màn phân quyền — dạng KHÔNG kèm vai trò.
        | Giữ tên cũ (pages/menus/actions) để link sidebar không phải sửa.
        */
        Route::get('/quyen-trang', 'pages')->name('pages');
        Route::get('/quyen-menu', 'menus')->name('menus');
        Route::get('/quyen-thao-tac', 'actions')->name('actions');

        /*
        | Vai trò là tài nguyên; quyền trang/menu/thao tác là tài nguyên CON.
        | Mỗi loại quyền có cặp GET (xem) + PUT (ghi) trên cùng một URI.
        |
        | Viết tay chứ KHÔNG dùng `apiResource`: controller đặt tên hành động là
        | storeRole/updateRole/destroyRole (vì nó còn phục vụ nhiều tài nguyên
        | khác), trong khi `apiResource` luôn trỏ cứng vào store/update/destroy.
        | Đợt 2026-08-05 tôi đã dùng apiResource và làm hỏng đúng chỗ này —
        | route vẫn sinh ra, `route()` trong Blade vẫn ra URL, nhưng bấm vào thì
        | 500 vì method không tồn tại. Test RouteControllerResolutionTest nay
        | chặn lại kiểu lỗi đó.
        */
        Route::post('/roles', 'storeRole')->name('roles.store');
        Route::put('/roles/{role}', 'updateRole')->whereNumber('role')->name('roles.update');
        Route::delete('/roles/{role}', 'destroyRole')->whereNumber('role')->name('roles.destroy');

        Route::prefix('roles/{role}')->whereNumber('role')->group(function () {
            Route::get('/quyen-trang', 'pages')->name('roles.pages.show');
            Route::put('/quyen-trang', 'syncRolePagePermissions')->name('roles.pages');

            Route::get('/quyen-menu', 'menus')->name('roles.menus.show');
            Route::put('/quyen-menu', 'syncRoleMenuPermissions')->name('roles.menus');

            Route::get('/quyen-thao-tac', 'actions')->name('roles.actions.show');
            Route::put('/quyen-thao-tac', 'syncRoleBusinessPermissions')->name('roles.actions');

            // "Nhân bản vai trò" là TẠO một bản sao -> POST vào tài nguyên con,
            // thay cho động từ `clone` trong URL.
            Route::post('/ban-sao', 'cloneRole')->name('roles.clone');
        });

        Route::put('/users/{user}/roles', 'syncUserRoles')->whereNumber('user')->name('users.roles');
        Route::put('/users/{user}/permissions', 'syncUserPermissions')->whereNumber('user')->name('users.permissions');
    });

/*
|---------------------------------------------------------------------------
| URL cũ
|---------------------------------------------------------------------------
|
| CỐ Ý KHÔNG làm lớp redirect cho URL cũ: đây là trang quản trị nội bộ, không
| có bookmark ngoài, không link trong email/Zalo. Thêm redirect chỉ tạo thêm
| route phải bảo trì. Người đang mở tab cũ chỉ cần tải lại từ menu.
|
| Riêng `/cai-dat/phan-quyen` giữ lại vì nó vốn đã là redirect từ trước đợt này.
|
*/
Route::middleware(['auth', 'role:admin'])
    ->get('/cai-dat/phan-quyen', fn () => redirect()->route('admin.settings.roles', [], 301))
    ->name('admin.role-permissions.index');
