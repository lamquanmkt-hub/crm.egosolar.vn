<?php

/*
|---------------------------------------------------------------------------
| Route: Sản phẩm, kho, serial, bảng giá
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

use App\Http\Controllers\CRM\CustomerController;
use App\Http\Controllers\Inventory\BrandController;
use App\Http\Controllers\Inventory\PriceTierController;
use App\Http\Controllers\Inventory\ProductCategoryController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\Solar\SolarCalculatorController;
use App\Http\Controllers\Solar\SolarSettingController;
use App\Http\Controllers\System\CompanyController;
use App\Http\Controllers\System\MediaController;
use App\Http\Controllers\System\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Main Resources
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // Solar Settings
    Route::get('/solar/settings', [SolarSettingController::class, 'index'])
        ->name('solar.settings');

    Route::post('/solar/settings', [SolarSettingController::class, 'update'])
        ->name('solar.settings.update');

    Route::get('/solar/calculator', [SolarCalculatorController::class, 'index'])
        ->name('solar.calculator');

    Route::post('/solar/calculator/calculate', [SolarCalculatorController::class, 'calculate'])
        ->name('solar.calculator.calculate');
    // Users
    Route::resource('users', UserController::class)->except(['show']);

    // Companies - thông tin pháp nhân dùng để in PDF đơn hàng
    Route::resource('companies', CompanyController::class)->only(['index', 'edit', 'update']);

    // Customers
    Route::get('/customers/popup/form/{id?}', [CustomerController::class, 'ajaxForm'])
        ->name('customers.popup-form');
    Route::get('/customers/{customer}/invoice-info', [CustomerController::class, 'invoiceInfo'])
        ->name('customers.invoice-info');

    Route::post('/customers/{customer}/billing-info', [CustomerController::class, 'updateBillingInfo'])
        ->name('customers.billing-info.update');
    /* EGO_CUSTOMER_PROMAX_V3_ROUTES_START */
    Route::get(
        '/customers/duplicate-check',
        [CustomerController::class, 'duplicateCheck']
    )->name('customers.duplicate-check');

    Route::post(
        '/customers/{customer}/interactions',
        [CustomerController::class, 'storeInteraction']
    )
        ->whereNumber('customer')
        ->name('customers.interactions.store');
    /* EGO_CUSTOMER_PROMAX_V3_ROUTES_END */

    /* EGO_CUSTOMER_HANDOVER_V32_START */
    Route::post(
        '/customers/{customer}/handover',
        [CustomerController::class, 'handover']
    )
        ->whereNumber('customer')
        ->name('customers.handover');
    /* EGO_CUSTOMER_HANDOVER_V32_END */

    Route::resource('customers', CustomerController::class);

    /* EGO_CUSTOMER_PROFILES_ROUTES_START */
    Route::middleware(['auth'])
        ->prefix('customer-profiles')
        ->name('customer-profiles.')
        ->controller(\App\Http\Controllers\CRM\CustomerProfileController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/sync-customers', 'syncCustomers')->name('sync-customers');
            Route::get('/exports/csv', 'export')->name('export');
            Route::get('/shipping', 'shippingIndex')->name('shipping.index');
            Route::post('/shipping', 'storeShipping')->name('shipping.store');
            Route::put('/shipping/{shipping}', 'updateShipping')->whereNumber('shipping')->name('shipping.update');
            Route::delete('/shipping/{shipping}', 'destroyShipping')->whereNumber('shipping')->name('shipping.destroy');
            Route::post('/shipping', 'storeShipping')->name('shipping.store');
            Route::put('/shipping/{shipping}', 'updateShipping')->whereNumber('shipping')->name('shipping.update');
            Route::delete('/shipping/{shipping}', 'destroyShipping')->whereNumber('shipping')->name('shipping.destroy');
            Route::get('/{customerProfile}', 'show')->whereNumber('customerProfile')->name('show');
            Route::get('/{customerProfile}/edit', 'edit')->whereNumber('customerProfile')->name('edit');
            Route::put('/{customerProfile}', 'update')->whereNumber('customerProfile')->name('update');
            Route::delete('/{customerProfile}', 'destroy')->whereNumber('customerProfile')->name('destroy');
            Route::post('/{customerProfile}/documents', 'storeDocument')->whereNumber('customerProfile')->name('documents.store');
            Route::get('/{customerProfile}/documents/{document}', 'downloadDocument')->whereNumber('customerProfile')->whereNumber('document')->name('documents.download');
            Route::delete('/{customerProfile}/documents/{document}', 'destroyDocument')->whereNumber('customerProfile')->whereNumber('document')->name('documents.destroy');
        });
    /* EGO_CUSTOMER_PROFILES_ROUTES_END */

    // Product Categories, Brands, Price Tiers
    Route::resource('categories', ProductCategoryController::class);
    // `show` bị loại: hai controller này chỉ có index/create/store/edit/update/destroy,
    // `Route::resource` đầy đủ sẽ sinh ra `show` trỏ vào method không tồn tại (500).
    Route::resource('brands', BrandController::class)->except(['show']);
    Route::resource('price-tiers', PriceTierController::class)->except(['show']);

    // Warehouses
    Route::resource('warehouses', WarehouseController::class)->except(['show']);
    Route::get('warehouses/{warehouse}/inventory', [WarehouseController::class, 'inventory'])
        ->middleware(['can:warehouse.manage', 'can:warehouse.stock_check'])
        ->name('warehouses.inventory');
    // ✅ Products Input/Output (PHẢI đặt trước Route::resource('products', ...))
    Route::get('/products/input', [ProductController::class, 'input'])->name('products.input');
    Route::get('/products/input/exports/excel', [ProductController::class, 'exportInputExcel'])->name('products.input.export.excel');
    Route::get('/products/output', [ProductController::class, 'output'])->name('products.output');
    Route::get('/products/history', [ProductController::class, 'history'])->name('products.history');

    // Products

    /* EGO_PRODUCT_SERIAL_MANAGEMENT_ROUTES_START */
    Route::middleware(['auth'])
        ->prefix('products/serials')
        ->name('products.serials.')
        ->controller(\App\Http\Controllers\Inventory\ProductSerialManagementController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/exports', 'export')->name('export');
        });
    /* EGO_PRODUCT_SERIAL_MANAGEMENT_ROUTES_END */

    /* EGO_PRODUCT_GOODS_RECEIPTS_ROUTES_START */
    Route::middleware(['auth', 'role:admin|warehouse|accounting'])
        ->prefix('products/goods-receipts')
        ->name('product-goods-receipts.')
        ->controller(\App\Http\Controllers\Inventory\ProductGoodsReceiptController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/{id}/nhap-kho', 'post')->whereNumber('id')->name('post');
            Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        });
    /* EGO_PRODUCT_GOODS_RECEIPTS_ROUTES_END */

    // ProductController không có method show — loại khỏi resource để /products/{id} trả 404 thay vì 500.
    Route::resource('products', ProductController::class)->except(['show']);

    // Media
    Route::controller(MediaController::class)->group(function () {
        Route::get('/media/list', 'list')->name('media.list');
        Route::post('/media/upload', 'upload')->name('media.upload');
        Route::delete('/media/{media}', 'destroy')
            ->name('media.destroy')
            ->middleware('permission:products.manage');
    });
});

/* EGO_THAO_FORCE_DELETE_PAYMENT_REQUEST_ONLY_END */

/* EGO_SERIAL_WARRANTY_ROUTES_START */
Route::middleware(['auth'])
    ->prefix('serial-warranty')
    ->name('serial-warranty.')
    ->controller(\App\Http\Controllers\Inventory\SerialWarrantyController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/lookup', 'lookup')->name('lookup');
        Route::post('/manual-add', 'manualAddSerialWarranty')->name('manual-add');
        Route::post('/receive', 'receive')->name('receive');
        Route::post('/issue', 'issue')->name('issue');
        Route::post('/transfer', 'transfer')->name('transfer');
        Route::post('/return-stock', 'returnStock')->name('return-stock');
        Route::post('/claim', 'claim')->name('claim');
        Route::post('/serial/{serialUnit}/remove-from-lookup', 'removeSerialFromLookup')->whereNumber('serialUnit')->name('serial.remove-from-lookup');
        Route::post('/serial/{serialUnit}/warranty', 'updateSerialWarranty')->whereNumber('serialUnit')->name('serial.warranty.update');
        Route::post('/product/{product}/serials', 'productAddSerials')->whereNumber('product')->name('product.serials.add');
        Route::put('/serial/{serialUnit}', 'productUpdateSerial')->whereNumber('serialUnit')->name('product.serials.update');
        Route::delete('/serial/{serialUnit}', 'productDeleteSerial')->whereNumber('serialUnit')->name('product.serials.delete');
    });
