<?php

/*
|---------------------------------------------------------------------------
| Route: Đơn hàng, trả hàng, hoàn tiền
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

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Orders
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('orders')
    ->name('orders.')
    ->controller(\App\Http\Controllers\CRM\OrderController::class)
    ->group(function () {

        // ✅ NEW: Search khách hàng cho dropdown (Tom Select)
        Route::get('/customers/search', 'searchCustomers')->name('customers.search');

        // Đã bỏ 2026-08-05: `send-completed-mail` trỏ tới sendCompletedMail()
        // chưa từng được viết (500) và không nơi nào gọi tới.

        Route::post('/{id}/warehouse-issue', 'approveWarehouseIssue')
            ->whereNumber('id')
            ->name('warehouse.issue');
        Route::post('/{order}/invoice', 'updateInvoice')
            ->whereNumber('order')
            ->name('updateInvoice');
        Route::get('/{order}/returns/create', [\App\Http\Controllers\CRM\OrderReturnController::class, 'create'])
            ->whereNumber('order')
            ->name('returns.create');

        Route::post('/{order}/returns', [\App\Http\Controllers\CRM\OrderReturnController::class, 'store'])
            ->whereNumber('order')
            ->name('returns.store');

        // API endpoints
        Route::get('/product-catalog', 'getProductCatalog')->name('product-catalog');
        Route::get('/product-warehouses/{productId}', 'getProductWarehouses')
            ->whereNumber('productId')
            ->name('product-warehouses');
        Route::get('/products-by-warehouse', 'getProductsByWarehouse')->name('products-by-warehouse');
        Route::get('/warehouses-by-company', 'getWarehousesByCompany')->name('warehouses-by-company');
        Route::get('/customer-info/{customerId}', 'getCustomerInfo')->name('customer-info');
        Route::get('/product-price/{productId}', 'getProductPrice')->name('product-price');

        // PDF
        Route::get('/{order}/pdf', 'pdf')->whereNumber('order')->name('pdf');
        Route::get('/{order}/pdf-preview', 'pdfPreview')->whereNumber('order')->name('pdf.preview');
        // CRUD
        Route::get('/', 'index')->name('index');
        Route::get('/exports/excel', 'exportExcel')->name('export.excel');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->name('update');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');

        // Workflow
        Route::post('/{id}/submit', 'submitForApproval')->name('submit');
        Route::get('/{id}/approval', 'approvalForm')->name('approval-form');
        Route::post('/{id}/approval', 'processApproval')->name('process-approval');
        Route::post('/{id}/cancel', 'cancelOrder')->name('cancel');
        // Đã bỏ 2026-08-05: `/{id}/ship` -> shipOrder() không tồn tại (500),
        // không ai gọi. Việc giao hàng chạy qua `markShipped`/`updateShippingInfo`.
        Route::post('/{id}/payment', 'recordPayment')->name('record-payment');
        // Đã bỏ 2026-08-05: `payments/{id}/edit` -> editPayment() không tồn tại (500),
        // không ai gọi. Sửa thanh toán chạy thẳng qua PUT payments/{id}.
        Route::put('/payments/{paymentId}', 'updatePayment')->name('payments.update');
        Route::delete('/payments/{paymentId}', 'destroyPayment')->name('payments.destroy');

        // Khôi phục 2026-09-02: `/my/dashboard` -> myOrders(). Trước đó bị gỡ ngày
        // 2026-08-05 vì method không tồn tại (500) — nhưng method đó không phải chưa
        // từng có, nó bị đánh rơi trong lần khôi phục web.php ngày 2026-05-06.
        Route::get('/my/dashboard', 'myOrders')->name('my-orders');
    });

/* EGO_ORDER_AFTER_SALES_ROUTES_START */
Route::middleware(['auth'])->group(function () {
    Route::get('/order-returns', [\App\Http\Controllers\CRM\OrderReturnController::class, 'dashboard'])->name('order-returns.dashboard');
    Route::get('/orders/{order}/returns', [\App\Http\Controllers\CRM\OrderReturnController::class, 'index'])->name('orders.returns.index');
    Route::post('/order-returns/{orderReturn}/submit', [\App\Http\Controllers\CRM\OrderReturnController::class, 'submit'])->name('order-returns.submit');
    Route::post('/order-returns/{orderReturn}/approve', [\App\Http\Controllers\CRM\OrderReturnController::class, 'approve'])->name('order-returns.approve');
    Route::post('/order-returns/{orderReturn}/reject', [\App\Http\Controllers\CRM\OrderReturnController::class, 'reject'])->name('order-returns.reject');
    Route::post('/order-returns/{orderReturn}/revision', [\App\Http\Controllers\CRM\OrderReturnController::class, 'requestRevision'])->name('order-returns.revision');
    Route::post('/order-returns/{orderReturn}/in-transit', [\App\Http\Controllers\CRM\OrderReturnController::class, 'markInTransit'])->name('order-returns.in-transit');
    Route::post('/order-returns/{orderReturn}/receive', [\App\Http\Controllers\CRM\OrderReturnController::class, 'receive'])->name('order-returns.receive');
    Route::post('/order-returns/{orderReturn}/inspect', [\App\Http\Controllers\CRM\OrderReturnController::class, 'inspect'])->name('order-returns.inspect');
    Route::post('/order-returns/{orderReturn}/stock-in', [\App\Http\Controllers\CRM\OrderReturnController::class, 'stockIn'])->name('order-returns.stock-in');
    Route::post('/order-returns/{orderReturn}/attachments', [\App\Http\Controllers\CRM\OrderReturnController::class, 'upload'])->name('order-returns.upload');
    Route::get('/order-return-attachments/{attachment}/download', [\App\Http\Controllers\CRM\OrderReturnController::class, 'download'])->name('order-returns.attachments.download');
    Route::post('/order-returns/{orderReturn}/refunds', [\App\Http\Controllers\CRM\OrderReturnController::class, 'createRefund'])->name('order-returns.refunds.store');
    Route::post('/order-refunds/{refund}/approve', [\App\Http\Controllers\CRM\OrderReturnController::class, 'approveRefund'])->name('order-refunds.approve');
    Route::post('/order-refunds/{refund}/process', [\App\Http\Controllers\CRM\OrderReturnController::class, 'processRefund'])->name('order-refunds.process');
    Route::get('/order-returns/{orderReturn}', [\App\Http\Controllers\CRM\OrderReturnController::class, 'show'])->name('order-returns.show');
});

/* EGO_COMPANY_DOCUMENTS_ROUTES_END */

/* EGO_ORDER_DOCUMENTS_PROFILE_ROUTES_START */
Route::middleware(['auth'])->group(function () {
    Route::post('/orders/{order}/documents-ego', [\App\Http\Controllers\CRM\EgoOrderDocumentController::class, 'store'])
        ->whereNumber('order')
        ->name('orders.documents-ego.store');

    Route::get('/orders/{order}/documents-ego/{document}/preview', [\App\Http\Controllers\CRM\EgoOrderDocumentController::class, 'preview'])
        ->whereNumber('order')
        ->whereNumber('document')
        ->name('orders.documents-ego.preview');

    Route::get('/orders/{order}/documents-ego/{document}/download', [\App\Http\Controllers\CRM\EgoOrderDocumentController::class, 'download'])
        ->whereNumber('order')
        ->whereNumber('document')
        ->name('orders.documents-ego.download');

    Route::delete('/orders/{order}/documents-ego/{document}', [\App\Http\Controllers\CRM\EgoOrderDocumentController::class, 'destroy'])
        ->whereNumber('order')
        ->whereNumber('document')
        ->name('orders.documents-ego.destroy');
});

/* EGO_BOOKING_ROOM_ROUTES_END */

/*
|--------------------------------------------------------------------------
| Xóa mềm đơn hàng
|--------------------------------------------------------------------------
| Chỉ ẩn đơn khỏi danh sách. Không xóa thanh toán, tồn kho hoặc lịch sử.
*/
Route::delete(
    '/orders/{order}/soft-delete',
    [
        \App\Http\Controllers\CRM\OrderDeleteController::class,
        'destroy',
    ]
)
    ->middleware('auth')
    ->name('orders.soft-delete');
