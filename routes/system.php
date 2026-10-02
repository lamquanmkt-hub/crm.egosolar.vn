<?php

/*
|---------------------------------------------------------------------------
| Route: Hệ thống: người dùng, thông báo, chat, cài đặt
|---------------------------------------------------------------------------
|
| Tách từ routes/web.php (2.654 dòng) ngày 2026-08-05. CHỈ DI CHUYỂN nguyên
| văn, KHÔNG đổi URL, tên route hay middleware — đã đối chiếu bảng route
| trước/sau bằng snapshot 1.053 dòng.
|
| Thứ tự trong file giữ đúng thứ tự khai báo cũ: có 2 cặp route trùng URI
| (PUT/DELETE payment-requests/{id}) mà Laravel chọn cái đăng ký TRƯỚC.
|
*/

use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\System\DebugController;
use App\Http\Controllers\System\NotificationController;
use App\Http\Controllers\System\PushSubscriptionController;
use App\Http\Controllers\System\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/* EGO_FIX_BAO_GIA_ROUTE_TOP_END */

/*
|--------------------------------------------------------------------------
| Controller Imports
|--------------------------------------------------------------------------
*/

// Auth
// Core
// Orders & Payments
// Products
// Tasks & Chat
// Construction Sites & Material Requests (Refactored)
// Debug & Utils (Refactored)
// Marketing
// Finance
// Solar

/* EGO_COMPANY_CONTEXT_ROUTES_START */
Route::middleware(['auth'])->group(function () {
    Route::get('/chon-cong-ty', \App\Http\Controllers\System\AutoCompanyContextController::class)
        ->name('company-context.select');
    Route::post('/chon-cong-ty', [\App\Http\Controllers\System\AutoCompanyContextController::class, 'store'])
        ->name('company-context.store');
    Route::post('/doi-cong-ty', [\App\Http\Controllers\System\EgoCompanyContextController::class, 'reset'])->name('company-context.reset');
});

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

/* EGO_COMPANY_MANAGEMENT_START */
Route::middleware(['auth'])->prefix('company-management')->name('company-management.')->group(function () {
    Route::get('/', [\App\Http\Controllers\System\CompanyManagementController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\System\CompanyManagementController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\System\CompanyManagementController::class, 'store'])->name('store');
    Route::get('/{company}/edit', [\App\Http\Controllers\System\CompanyManagementController::class, 'edit'])->name('edit');
    Route::put('/{company}', [\App\Http\Controllers\System\CompanyManagementController::class, 'update'])->name('update');
    Route::delete('/{company}', [\App\Http\Controllers\System\CompanyManagementController::class, 'destroy'])->name('destroy');
});

/* EGO_COMPANY_MANAGEMENT_END */

Route::get('/', [\App\Http\Controllers\System\RoleHomeController::class, 'index'])
    ->middleware('auth')
    ->name('dashboard');

/*
|--------------------------------------------------------------------------
| Debug Routes (Development Only)
|--------------------------------------------------------------------------
*/

// Debug routes: chỉ admin mới được truy cập (lộ thông tin schema nếu mở rộng hơn)
Route::middleware(['auth', 'role:admin'])
    ->prefix('debug')
    ->name('debug.')
    ->controller(DebugController::class)
    ->group(function () {
        // Đặt tên cả nhóm: route không tên thì không refactor được vì không
        // biết nơi nào đang gọi.
        Route::get('/ping-route', 'ping')->name('ping');
        Route::get('/product-tables', 'productTables')->name('product-tables');
        Route::get('/products-candidates', 'productsCandidates')->name('products-candidates');
        Route::get('/inventory', 'inventoryTables')->name('inventory');
        Route::get('/me', 'currentUser')->name('me');
    });

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('notifications')
    ->name('notifications.')
    ->controller(NotificationController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/{id}/read', 'markAsRead')->name('mark-read');
        Route::post('/read-all', 'markAllAsRead')->name('mark-all-read');
        Route::get('/unread-count', 'getUnreadCount')->name('unread-count');
        Route::get('/json', 'json')->name('json');
    });

/*
|--------------------------------------------------------------------------
| Profile & Push Subscription
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // Warehouses
    Route::resource('warehouses', WarehouseController::class)->except(['show']);

    Route::get('warehouses/{warehouse}/inventory', [WarehouseController::class, 'inventory'])
        ->middleware(['can:warehouse.manage', 'can:warehouse.stock_check'])
        ->name('warehouses.inventory');

    // Đã bỏ 2026-08-05: `warehouses/bulk` trỏ tới WarehouseController::bulk()
    // vốn chưa từng được viết (bấm vào là 500) và không nơi nào gọi tới.
    // Profile
    Route::middleware(['auth'])->controller(UserController::class)->group(function () {
        Route::get('/profile', 'profile')
            ->name('users.profile');

        Route::get('/profile/edit', 'editProfile')
            ->name('users.profile-edit');

        Route::put('/profile', 'updateProfile')
            ->name('users.profile-update');

        Route::post('/profile/avatar', 'updateAvatar')
            ->name('users.profile.avatar');
    });

    // Push Notifications
    Route::controller(PushSubscriptionController::class)->group(function () {
        Route::post('/push/subscribe', 'subscribe')->name('push.subscribe');
        Route::post('/push/unsubscribe', 'unsubscribe')->name('push.unsubscribe');
    });
});

Route::middleware('auth')->get('/sidebar/status', function () {
    return response()->json(
        \App\Support\EgoSidebarStatus::data(request()->boolean('debug'))
    );
})->name('sidebar.status');

/* EGO_HR_ANNOUNCEMENTS_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('hr/announcements')
    ->name('hr.announcements.')
    ->controller(\App\Http\Controllers\Hr\AnnouncementController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/json/unread-count', 'unreadCount')->name('unread-count');
        Route::get('/json/list', 'jsonList')->name('json');
        Route::post('/read-all', 'markAllRead')->name('read-all');
        Route::get('/{announcement}', 'show')->name('show');
        Route::put('/{announcement}', 'update')->name('update');
        Route::delete('/{announcement}', 'destroy')->name('destroy');
        Route::post('/{announcement}/read', 'markRead')->name('read');
    });

/* EGO_SERIAL_WARRANTY_ROUTES_END */

/* EGO_COMPANY_DOCUMENTS_ROUTES_START */
Route::middleware(['auth', 'role:admin|sales|sales_manager|marketing|marketing_manager|technical|technical_manager|accounting|assistant|management|warehouse'])
    ->prefix('company-documents')
    ->name('company-documents.')
    ->controller(\App\Http\Controllers\System\CompanyDocumentController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');

        Route::post('/folders', 'storeFolder')->name('folders.store');
        Route::patch('/folders/{folder}', 'updateFolder')->whereNumber('folder')->name('folders.update');
        Route::delete('/folders/{folder}', 'destroyFolder')->whereNumber('folder')->name('folders.destroy');
        Route::post('/folders/{folder}/restore', 'restoreFolder')->whereNumber('folder')->name('folders.restore');

        Route::post('/files', 'upload')->name('files.upload');
        Route::get('/files/{file}/details', 'showFile')->whereNumber('file')->name('files.show');
        Route::patch('/files/{file}', 'updateFile')->whereNumber('file')->name('files.update');
        Route::post('/files/{file}/versions', 'uploadVersion')->whereNumber('file')->name('files.versions.store');
        Route::post('/files/{file}/submit', 'submitApproval')->whereNumber('file')->name('files.submit');
        Route::post('/files/{file}/approve', 'approveFile')->whereNumber('file')->name('files.approve');
        Route::post('/files/{file}/revision', 'requestRevision')->whereNumber('file')->name('files.revision');
        Route::get('/files/{file}/preview', 'preview')->whereNumber('file')->name('files.preview');
        Route::get('/files/{file}/download', 'download')->whereNumber('file')->name('files.download');
        Route::delete('/files/{file}', 'destroyFile')->whereNumber('file')->name('files.destroy');
        Route::post('/files/{file}/restore', 'restoreFile')->whereNumber('file')->name('files.restore');

        Route::get('/versions/{version}/download', 'downloadVersion')->whereNumber('version')->name('versions.download');

        Route::post('/clipboard', 'setClipboard')->name('clipboard.set');
        Route::post('/clipboard/clear', 'clearClipboard')->name('clipboard.clear');
        Route::post('/paste', 'paste')->name('paste');
    });

/* EGO_CUSTOMER_PROFILE_DOCUMENT_PREVIEW_END */
/* EGO_VPP_FUNCTION_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        if (! Route::has('hr.office-supply-process.index')) {
            Route::get('/', 'index')->name('index');
        }

        if (! Route::has('hr.office-supply-process.store')) {
            Route::post('/', 'store')->name('store');
        }

        if (! Route::has('hr.office-supply-process.show')) {
            Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        }

        if (! Route::has('hr.office-supply-process.update')) {
            Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        }

        if (! Route::has('hr.office-supply-process.destroy')) {
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        }

        if (! Route::has('hr.office-supply-process.hr-review')) {
            Route::post('/{id}/hr-kiem-tra', 'hrReview')->whereNumber('id')->name('hr-review');
        }

        if (! Route::has('hr.office-supply-process.approve')) {
            Route::post('/{id}/duyet', 'approve')->whereNumber('id')->name('approve');
        }

        if (! Route::has('hr.office-supply-process.reject')) {
            Route::post('/{id}/tu-choi', 'reject')->whereNumber('id')->name('reject');
        }

        if (! Route::has('hr.office-supply-process.issue')) {
            Route::post('/{id}/xuat-kho', 'issue')->whereNumber('id')->name('issue');
        }

        if (! Route::has('hr.office-supply-process.receive')) {
            Route::post('/{id}/ky-nhan', 'receive')->whereNumber('id')->name('receive');
        }

        if (! Route::has('hr.office-supply-process.complete')) {
            Route::post('/{id}/hoan-tat', 'complete')->whereNumber('id')->name('complete');
        }
    });

/* EGO_VPP_FUNCTION_ROUTES_END */

/* EGO_VPP_SAVE_ONLY_ROUTES_START */
// Route riêng cho module VPP HR.
// Không liên kết kho hàng chính, không trừ kho sản phẩm, chỉ lưu sổ VPP riêng.
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        Route::post('/san-pham', 'productStore')->name('product.store');
        Route::post('/nhap-vpp', 'importStock')->name('stock.import');
        Route::post('/cap-phat-vpp', 'allocateStock')->name('stock.allocate');
    });

/* EGO_VPP_SAVE_ONLY_ROUTES_END */

/* EGO_VPP_POPUP_EDIT_ROUTE_START */
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        Route::put('/san-pham/{id}', 'productUpdate')->whereNumber('id')->name('product.update');
    });

/* EGO_VPP_POPUP_EDIT_ROUTE_END */

/* EGO_VPP_DELETE_ROUTE_START */
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        Route::delete('/san-pham/{id}', 'productDestroy')->whereNumber('id')->name('product.destroy');
    });

/* EGO_VPP_DELETE_ROUTE_END */

/* EGO_VPP_DETAIL_POPUP_ROUTE_START */
Route::middleware(['auth'])
    ->prefix('nhan-su/quy-trinh-phan-bo-vpp')
    ->name('hr.office-supply-process.')
    ->controller(\App\Http\Controllers\Hr\OfficeSupplyProcessController::class)
    ->group(function () {
        Route::get('/popup-chi-tiet/{id}', 'detailJson')->whereNumber('id')->name('detail-json');
    });
