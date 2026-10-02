<?php

/*
|---------------------------------------------------------------------------
| Route: Công trình, đơn vật tư, dự án
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

use App\Http\Controllers\Projects\MaterialRequestController;
use App\Http\Controllers\Projects\MaterialRequestDeletionController;
use App\Http\Controllers\Projects\SiteController;
use Illuminate\Support\Facades\Route;

/* EGO_SITE_WORKSPACE_V2_ROUTES_END */

/* EGO_PROJECT_CATEGORY_ROUTES_START */
Route::middleware(['auth'])->group(function () {
    Route::get('/du-an/nha-xuong', function () {
        return redirect()->route('projects-unified.index', ['type' => 'factory']);
    })->name('projects.factory.index');

    Route::get('/du-an/nha-xuong/create', function () {
        return redirect()->route('projects-unified.create', ['type' => 'factory']);
    })->name('projects.factory.create');

    Route::post('/du-an/nha-xuong', [SiteController::class, 'store'])
        ->defaults('project_type', 'factory')
        ->name('projects.factory.store');

    Route::get('/du-an/dan-dung', function () {
        return redirect()->route('projects-unified.index', ['type' => 'large_residential']);
    })->name('projects.residential.index');

    Route::get('/du-an/dan-dung/create', function () {
        return redirect()->route('projects-unified.create', ['type' => 'large_residential']);
    })->name('projects.residential.create');

    Route::post('/du-an/dan-dung', [SiteController::class, 'store'])
        ->defaults('project_type', 'residential')
        ->name('projects.residential.store');
});

/* EGO_PROJECT_CATEGORY_ROUTES_END */
/*
|--------------------------------------------------------------------------
| Công trình (Sites) - Refactored
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:technical|accounting|admin|warehouse|sales'])
    ->prefix('cong-trinh')
    ->name('sites.')
    ->controller(SiteController::class)
    ->group(function () {

        Route::get('/', 'index')->name('index');

        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');

        Route::get('/{id}/edit', 'edit')->name('edit')->whereNumber('id');

        Route::post('/{id}/cap-nhat', 'update')->name('update')->whereNumber('id');

        Route::post('/{id}/ghi-nhan-thanh-toan', 'recordPayment')
            ->name('record-payment')
            ->whereNumber('id');

        Route::put('/{id}/thanh-toan/{receipt}/cap-nhat', 'updatePayment')
            ->name('payments.update')
            ->whereNumber('id')
            ->whereNumber('receipt');

        Route::delete('/{id}/thanh-toan/{receipt}', 'destroyPayment')
            ->name('payments.destroy')
            ->whereNumber('id')
            ->whereNumber('receipt');

        Route::delete('/{id}', 'destroy')->name('destroy')->whereNumber('id');

        // Đã bỏ 2026-08-05: `/file/{file}` trỏ tới SiteController::file()
        // chưa từng được viết (500) và không nơi nào gọi tới.

        Route::get('/{id}', 'show')->name('show')->whereNumber('id');
    });

/* EGO_MR_ADMIN_DELETE_COMPLETED_ROUTE_START */
// 80 dòng logic cũ ở đây nay ở MaterialRequestDeletionController.
Route::delete('/don-vat-tu/{materialRequest}', MaterialRequestDeletionController::class)
    ->middleware(['auth', 'role:technical|admin'])
    ->whereNumber('materialRequest')
    ->name('material-requests.destroy');

/* EGO_MR_ADMIN_DELETE_COMPLETED_ROUTE_END */

/*
|--------------------------------------------------------------------------
| Đơn vật tư (Material Requests) - Refactored
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:technical|accounting|admin|warehouse|sales'])
    ->prefix('don-vat-tu')
    ->name('material-requests.')
    ->group(function () {

        Route::controller(MaterialRequestController::class)->group(function () {
            Route::get('/site-info', 'siteInfo')->name('siteInfo');
            Route::get('/san-pham-kho/tim-kiem', 'searchWarehouseProducts')
                ->name('warehouse-products.search')
                ->middleware('role:warehouse|admin');

            // Static routes phải để TRƯỚC route động
            Route::get('/', 'index')->name('index');

            Route::get('/create', 'create')
                ->name('create')
                ->middleware('role:technical|admin|sales|warehouse');

            Route::post('/', 'store')
                ->name('store')
                ->middleware('role:technical|admin|sales|warehouse');

            Route::get('/{materialRequest}/edit', 'edit')
                ->name('edit')
                ->middleware('role:technical|admin|warehouse|sales');

            Route::match(['post', 'put', 'patch'], '/{materialRequest}/cap-nhat', 'update')
                ->name('update')
                ->middleware('role:technical|admin|warehouse|sales');

            Route::delete('/{materialRequest}', 'destroy')
                ->name('destroy')
                ->middleware('role:technical|admin');

            Route::post('/{materialRequest}/gui-duyet', 'submit')
                ->name('submit')
                ->middleware('role:technical|admin');

            Route::post('/{materialRequest}/admin-duyet', 'adminApprove')
                ->name('admin-approve')
                ->middleware('role:admin');

            Route::post('/{materialRequest}/kho-duyet', 'warehouseApprove')
                ->name('warehouse-approve')
                ->middleware('role:warehouse|admin');

            Route::post('/{materialRequest}/phan-bo-kho', 'saveWarehouseAllocation')
                ->name('warehouse-allocation')
                ->middleware('role:warehouse|admin');

            Route::get('/{materialRequest}/exports/excel', 'exportExcel')
                ->name('export.excel')
                ->middleware('role:warehouse|admin');

            Route::get('/{materialRequest}/phieu-xuat/pdf', 'exportDispatchPdf')
                ->name('dispatch.pdf')
                ->middleware('role:warehouse|admin');

            Route::get('/{materialRequest}/phieu-xuat/excel', 'exportDispatchExcel')
                ->name('dispatch.excel')
                ->middleware('role:warehouse|admin');

            // Route động để CUỐI
            Route::get('/{materialRequest}', 'show')->name('show');
        });
    });

// Status tracking
Route::get('/theo-doi-trang-thai', fn () => 'Theo dõi trạng thái - OK')
    ->middleware(['auth', 'role:technical|accounting|admin|warehouse|sales'])
    ->name('status-tracking.index');

/*
|--------------------------------------------------------------------------
| Kỹ thuật - Lịch bảo trì / bảo hành điện mặt trời
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'role:technical|technical_manager|accounting|admin|manager|warehouse|sales|sales_manager|cskh'])
    ->prefix('ky-thuat/bao-tri-bao-hanh')
    ->name('ky-thuat.maintenance.')
    ->group(function () {
        Route::get('/', fn () => redirect()->route('projects-unified.maintenance.index', request()->query()))->name('index');
        Route::get('/cong-trinh/{site}', fn ($site) => redirect()->route('projects-unified.maintenance.site', ['site' => $site]))
            ->whereNumber('site')->name('site');
        Route::get('/{schedule}', fn ($schedule) => redirect()->route('projects-unified.maintenance.show', ['schedule' => $schedule]))
            ->whereNumber('schedule')->name('show');
    });

/*
|--------------------------------------------------------------------------
| Đề xuất chung — đã chuẩn hoá REST 2026-08-05
|--------------------------------------------------------------------------
|
| /tao -> /create, /luu -> POST /, /{id}/xoa (POST) -> DELETE /{id},
| /{id}/duyet GIỮ NGUYÊN: `duyet` là chuyển trạng thái, cùng loại với `approve`
| — toàn app đang dùng `duyet`, đổi riêng chỗ này thành `phe-duyet` chỉ tạo ra
| hai cách gọi cho cùng một việc.
|
| Tên route GIỮ NGUYÊN nên Blade không phải sửa.
| `/{id}/tu-choi` cố ý giữ: tiếng Việt không có danh từ hoá gọn cho "từ chối",
| đổi thành chuỗi dài hơn chỉ làm URL khó đọc mà không lợi gì.
|
*/
Route::middleware(['auth'])
    ->prefix('de-xuat')
    ->name('de-xuat.')
    ->group(function () {

        Route::get('/', [\App\Http\Controllers\Projects\ProposalController::class, 'index'])
            ->name('index');

        Route::get('/create', [\App\Http\Controllers\Projects\ProposalController::class, 'create'])
            ->name('create');

        Route::post('/', [\App\Http\Controllers\Projects\ProposalController::class, 'store'])
            ->name('store');

        Route::get('/{id}/edit', [\App\Http\Controllers\Projects\ProposalController::class, 'edit'])
            ->whereNumber('id')
            ->name('edit');

        Route::put('/{id}', [\App\Http\Controllers\Projects\ProposalController::class, 'update'])
            ->whereNumber('id')
            ->name('update');

        Route::post('/{id}/create-payment-request', [\App\Http\Controllers\Projects\ProposalController::class, 'createPaymentRequest'])
            ->whereNumber('id')
            ->name('create-payment-request');

        Route::get('/{id}', [\App\Http\Controllers\Projects\ProposalController::class, 'show'])
            ->whereNumber('id')
            ->name('show');

        Route::post('/{id}/duyet', [\App\Http\Controllers\Projects\ProposalController::class, 'approve'])
            ->whereNumber('id')
            ->name('approve');

        Route::post('/{id}/tu-choi', [\App\Http\Controllers\Projects\ProposalController::class, 'reject'])
            ->whereNumber('id')
            ->name('reject');

        Route::delete('/{id}', [\App\Http\Controllers\Projects\ProposalController::class, 'destroy'])
            ->whereNumber('id')
            ->name('destroy');
    });

/* EGO_HR_ANNOUNCEMENTS_ROUTES_END */

/* EGO_SITE_ASSEMBLY_ROUTES_START */
Route::middleware(['auth', 'role:technical|accounting|admin|warehouse|sales'])
    ->prefix('cong-trinh/lap-rap-san-xuat')
    ->name('site-assemblies.')
    ->controller(\App\Http\Controllers\Projects\SiteAssemblyController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('/{id}/hoan-thanh', 'complete')->whereNumber('id')->name('complete');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });

Route::middleware(['auth'])->get('/cong-trinh-moi', function () {
    return redirect()->route('projects-unified.index');
})->name('sites-v2.index');
