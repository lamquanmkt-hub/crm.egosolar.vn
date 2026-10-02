<?php

/*
|---------------------------------------------------------------------------
| Route: Tài chính: đề nghị thanh toán, công nợ, tài sản
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

use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\AdvanceRequestController;
use App\Http\Controllers\Finance\BudgetController;
use App\Http\Controllers\Finance\CustomerDebtController;
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\Finance\FinanceLedgerController;
use App\Http\Controllers\Finance\FinanceReportController;
use App\Http\Controllers\Finance\PaymentAttachmentController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\PaymentMethodController;
use App\Http\Controllers\Finance\PaymentRequestApprovalController;
use App\Http\Controllers\Finance\PaymentRequestController;
use App\Http\Controllers\Finance\PaymentRequestDuplicateController;
use App\Http\Controllers\Finance\PrivilegedPaymentRequestController;
use App\Http\Controllers\Finance\ProjectReceivableController;
use App\Http\Controllers\Finance\ReceiptController;
use App\Http\Controllers\Finance\SettlementRequestController;
use App\Http\Controllers\Finance\SupplierDebtController;
use Illuminate\Support\Facades\Route;

/* EGO_ORDER_AFTER_SALES_ROUTES_END */

/*
|--------------------------------------------------------------------------
| Payment Methods
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('payment-methods')
    ->name('payment-methods.')
    ->controller(PaymentMethodController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
    });

/* EGO_THAO_FULL_PAYMENT_REQUESTS_START */
/*
| Xoá ĐNTT ở MỌI trạng thái — đặc quyền tài chính.
|
| ## Lịch sử: từng có một BẢN THỨ HAI ở đây, và nó chưa bao giờ chạy
| Trước 2026-08-06 khối này còn đăng ký `PATCH|PUT /payment-requests/{id}` và
| `POST|DELETE /payment-requests/{id}` để cho người có đặc quyền sửa/xoá phiếu ở
| mọi trạng thái. Chúng KHÔNG BAO GIỜ chạy: phía dưới file này đăng ký PUT và
| DELETE trên cùng URI trỏ vào PaymentRequestController, mà với method+URI trùng
| nhau Laravel để bản đăng ký SAU ghi đè bản trước.
|
| Điều quan trọng: tính năng đó KHÔNG mất — `PaymentRequestController::update()`
| và `destroy()` đã tự kiểm `canEditCompletedFinanceRecord()` và cho người có
| đặc quyền sửa/xoá phiếu đã duyệt. Bản thứ hai chỉ là bản THỪA, lại còn ghi
| thẳng mọi cột từ request mà không kiểm tra dữ liệu.
|
| Đã xoá 2026-08-06 theo quyết định của chủ hệ thống. Đừng thêm lại.
*/
Route::middleware(['auth'])
    ->controller(PrivilegedPaymentRequestController::class)
    ->group(function () {
        /*
        | Endpoint xoá đặc quyền riêng. Đã rà Blade/JS ngày 2026-08-05: KHÔNG
        | nơi nào gọi tới.
        |
        | ✅ CHỦ HỆ THỐNG ĐÃ QUYẾT (2026-08-06): GIỮ LẠI. Đừng đề xuất xoá nữa.
        | Đây là lối thoát hiểm khi cần xoá tay một phiếu mà giao diện không cho
        | — không có nút nào gọi là ĐÚNG chủ ý, không phải sót.
        |
        | Hệ quả: URL còn động từ nên bảng chấm REST đứng ở 740/741 (99,9%) —
        | đó là ngoại lệ được chấp nhận, không phải việc chưa làm xong.
        */
        Route::delete('/payment-requests/{id}/xoa-full-thao', 'eraseCompletely')
            ->whereNumber('id')
            ->name('payment_requests.thao_full_delete');
    });

/* EGO_THAO_FULL_PAYMENT_REQUESTS_END */

/* EGO_THAO_PR_ATTACHMENTS_START */
Route::middleware(['auth'])->group(function () {
    Route::get('/payment-requests/{paymentRequest}/attachments-thao/{attachment}/download', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentController::class, 'download'])
        ->whereNumber('paymentRequest')
        ->whereNumber('attachment')
        ->name('payment-requests.attachments-thao.download');

    Route::get('/payment-requests/{paymentRequest}/attachments-thao/{attachment}/preview', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentPreviewController::class, 'show'])
        ->whereNumber('paymentRequest')
        ->whereNumber('attachment')
        ->name('payment-requests.attachments-thao.preview');

    Route::post('/payment-requests/{paymentRequest}/attachments-thao', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentController::class, 'upload'])
        ->whereNumber('paymentRequest')
        ->name('payment-requests.attachments-thao.upload');

    Route::post('/payment-requests/{paymentRequest}/attachments-thao/{attachment}/cap-nhat', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentController::class, 'replace'])
        ->whereNumber('paymentRequest')
        ->whereNumber('attachment')
        ->name('payment-requests.attachments-thao.replace');

    Route::delete('/payment-requests/{paymentRequest}/attachments-thao/{attachment}', [\App\Http\Controllers\Finance\EgoPaymentRequestAttachmentController::class, 'destroy'])
        ->whereNumber('paymentRequest')
        ->whereNumber('attachment')
        ->name('payment-requests.attachments-thao.destroy');
});

/* EGO_THAO_PR_ATTACHMENTS_END */

/* EGO_COPY_PAYMENT_REQUEST_START */
// Luật sao chép phiếu (114 dòng cũ ở đây) nay ở PaymentRequestDuplicator.
Route::post('/payment-requests/{id}/ban-sao', PaymentRequestDuplicateController::class)
    ->middleware('auth')
    ->whereNumber('id')
    ->name('payment_requests.copy');

/* EGO_COPY_PAYMENT_REQUEST_END */

/* EGO_ADVANCE_REQUESTS_START */
Route::middleware(['auth'])
    ->prefix('advance-requests')
    ->name('advance_requests.')
    ->controller(AdvanceRequestController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{advanceRequest}', 'show')->whereNumber('advanceRequest')->name('show');
        Route::put('/{advanceRequest}', 'update')->whereNumber('advanceRequest')->name('update');
        Route::delete('/{advanceRequest}', 'destroy')->whereNumber('advanceRequest')->name('destroy');
        Route::post('/{advanceRequest}/submit', 'submit')->whereNumber('advanceRequest')->name('submit');
        Route::post('/{advanceRequest}/management-approve', 'managementApprove')->whereNumber('advanceRequest')->name('management_approve');
        Route::post('/{advanceRequest}/management-reject', 'managementReject')->whereNumber('advanceRequest')->name('management_reject');
        Route::post('/{advanceRequest}/accounting-approve', 'accountingApprove')->whereNumber('advanceRequest')->name('accounting_approve');
        Route::post('/{advanceRequest}/accounting-reject', 'accountingReject')->whereNumber('advanceRequest')->name('accounting_reject');
        Route::post('/{advanceRequest}/settlement', [SettlementRequestController::class, 'storeForAdvance'])->whereNumber('advanceRequest')->name('settlement.store');
    });
/* EGO_ADVANCE_REQUESTS_END */

/* EGO_SETTLEMENT_REQUESTS_START */
Route::middleware(['auth'])
    ->prefix('settlement-requests')
    ->name('settlement_requests.')
    ->controller(SettlementRequestController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{settlementRequest}', 'show')->whereNumber('settlementRequest')->name('show');
        Route::delete('/{settlementRequest}', 'destroy')->whereNumber('settlementRequest')->name('destroy');
        Route::post('/{settlementRequest}/submit', 'submit')->whereNumber('settlementRequest')->name('submit');
        Route::post('/{settlementRequest}/management-approve', 'managementApprove')->whereNumber('settlementRequest')->name('management_approve');
        Route::post('/{settlementRequest}/management-reject', 'managementReject')->whereNumber('settlementRequest')->name('management_reject');
        Route::post('/{settlementRequest}/accounting-approve', 'accountingApprove')->whereNumber('settlementRequest')->name('accounting_approve');
        Route::post('/{settlementRequest}/accounting-reject', 'accountingReject')->whereNumber('settlementRequest')->name('accounting_reject');
    });
/* EGO_SETTLEMENT_REQUESTS_END */

/*
|--------------------------------------------------------------------------
| Payment Requests
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    // CRUD
    Route::controller(PaymentRequestController::class)->group(function () {
        Route::get('/payment-requests', 'index')->name('payment_requests.index');
        Route::get('/payment-requests/exports/excel', 'exportExcel')->name('payment_requests.export_excel');
        Route::get('/payment-requests/exports/pdf', 'exportPdf')->name('payment_requests.export_pdf');
        // Route::get('/payment-requests/create', 'create')->name('payment_requests.create');
        // Bí danh của trang tạo mới; đặt tên để còn refactor được (trước đây không tên).
        Route::get('/payment-requests/new', 'create')->name('payment_requests.create_alias');
        Route::post(
            '/payment-requests',
            [PaymentRequestController::class, 'store']
        )->name('payment_requests.store');

        Route::get('/payment-requests/{id}', 'show')->whereNumber('id')->name('payment_requests.show');
        Route::get('/payment-requests/{id}/edit', 'edit')->whereNumber('id')->name('payment_requests.edit');
        Route::put('/payment-requests/{id}', 'update')->whereNumber('id')->name('payment_requests.update');
        Route::delete('/payment-requests/{id}', 'destroy')->whereNumber('id')->name('payment_requests.destroy');
        Route::get('/payment-requests/{id}/invoice', 'invoice')->whereNumber('id')->name('payment_requests.invoice');
        Route::get('/payment-requests/demo-create', 'demoCreate')->name('payment_requests.demo_create');
    });

    // Approval Workflow
    Route::controller(PaymentRequestApprovalController::class)->group(function () {
        // Phải đặt trước các route động {id}
        Route::post('/payment-requests/bulk-approve', 'bulkApprove')
            ->name('payment_requests.bulk_approve');

        Route::post('/payment-requests/{id}/submit', 'submit')->name('payment_requests.submit');
        Route::post('/payment-requests/{id}/admin-approve', 'adminApprove')->name('payment_requests.admin_approve');
        Route::post('/payment-requests/{id}/admin-reject', 'adminReject')->name('payment_requests.admin_reject');
        Route::post('/payment-requests/{id}/acc-approve', 'accApprove')->name('payment_requests.acc_approve');
        Route::post('/payment-requests/{id}/acc-reject', 'accReject')->name('payment_requests.acc_reject');
    });

    // Attachments
    Route::post('/payment-requests/{paymentRequest}/attachments', [PaymentAttachmentController::class, 'store'])
        ->name('payment-attachments.store');
});

/*
|--------------------------------------------------------------------------
| Finance
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])
    ->prefix('finance')
    ->name('finance.')
    ->group(function () {
        /*
         * EGO_WAREHOUSE_SUPPLIER_DEBT_ACCESS
         *
         * Toàn bộ Finance mặc định vẫn chỉ Admin/Kế toán.
         * Riêng Công nợ NCC được mở thêm cho Kho.
         */
        Route::middleware('role:admin|accounting|management')->group(function () {
            Route::get('/', [FinanceDashboardController::class, 'index'])->name('index');

            /* EGO_FINANCE_ASSETS_ROUTES_START */
            Route::prefix('assets')
                ->name('assets.')
                ->controller(\App\Http\Controllers\Finance\AssetController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/exports/csv', 'exportCsv')->name('export');
                    Route::post('/', 'store')->name('store');
                    Route::put('/{id}', 'update')->whereNumber('id')->name('update');
                    Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
                    Route::post('/categories', 'storeCategory')->name('categories.store');
                    Route::post('/{assetId}/events', 'storeEvent')->whereNumber('assetId')->name('events.store');
                    Route::delete('/events/{eventId}', 'destroyEvent')->whereNumber('eventId')->name('events.destroy');
                    Route::get('/files/{fileId}/download', 'downloadFile')->whereNumber('fileId')->name('files.download');
                });
            /* EGO_FINANCE_ASSETS_ROUTES_END */

            Route::get('/project-receivables', [ProjectReceivableController::class, 'index'])
                ->name('project-receivables.index');

        });

        /*
         * EGO_WAREHOUSE_CUSTOMER_RECEIVABLE_ACCESS_V1
         *
         * Kho được phép XEM Công nợ phải thu / công nợ khách hàng.
         * Quyền xóa vẫn chỉ dành cho Admin / Kế toán / Quản lý.
         */
        Route::middleware('role:admin|accounting|management|warehouse')
            ->prefix('customer-debts')
            ->name('customer-debts.')
            ->group(function () {
                Route::get('/', [CustomerDebtController::class, 'index'])->name('index');
                Route::get('/by-customer', [CustomerDebtController::class, 'byCustomer'])->name('by-customer');
                Route::get('/payment-history', [CustomerDebtController::class, 'paymentHistory'])->name('payment-history');

                Route::delete('/{id}', [CustomerDebtController::class, 'destroy'])
                    ->whereNumber('id')
                    ->middleware('role:admin|accounting|management')
                    ->name('destroy');
            });

        /*
         * Kho được phép truy cập riêng Công nợ nhà cung cấp.
         * Không cấp quyền sang Dashboard / Thu chi / Báo cáo Finance.
         */
        Route::middleware('role:admin|accounting|management|warehouse')
            ->prefix('supplier-debts')
            ->name('supplier-debts.')
            ->group(function () {
                Route::get('/', [SupplierDebtController::class, 'index'])->name('index');

                Route::post('/', [SupplierDebtController::class, 'store'])
                    ->name('store');

                Route::post('/{id}/files', [SupplierDebtController::class, 'storeDebtFileOnly'])
                    ->whereNumber('id')
                    ->name('files.store');

                Route::get('/files/{fileId}/download', [SupplierDebtController::class, 'downloadDebtFile'])
                    ->whereNumber('fileId')
                    ->name('files.download');

                Route::delete('/files/{fileId}', [SupplierDebtController::class, 'destroyDebtFile'])
                    ->whereNumber('fileId')
                    ->name('files.destroy');

                Route::put('/{id}', [SupplierDebtController::class, 'update'])
                    ->whereNumber('id')
                    ->name('update');

                Route::delete('/{id}', [SupplierDebtController::class, 'destroy'])
                    ->whereNumber('id')
                    ->name('destroy');

                Route::post('/{id}/payment-rounds', [SupplierDebtController::class, 'storePaymentRound'])
                    ->whereNumber('id')
                    ->name('payment-rounds.store');

                Route::put('/payment-rounds/{paymentRoundId}', [SupplierDebtController::class, 'updatePaymentRound'])
                    ->whereNumber('paymentRoundId')
                    ->name('payment-rounds.update');

                Route::delete('/payment-rounds/{paymentRoundId}', [SupplierDebtController::class, 'destroyPaymentRound'])
                    ->whereNumber('paymentRoundId')
                    ->name('payment-rounds.destroy');

                Route::post('/payment-rounds/{paymentRoundId}/payment-request', [SupplierDebtController::class, 'createPaymentRequestFromRound'])
                    ->whereNumber('paymentRoundId')
                    ->name('payment-rounds.create-payment-request');
                Route::post('/payment-rounds/{paymentRoundId}/create-remaining-round', [SupplierDebtController::class, 'createRemainingRoundFromLinkedPayment'])
                    ->whereNumber('paymentRoundId')
                    ->name('payment-rounds.create-remaining-round');

            });

        /*
         * Các chức năng Finance còn lại tiếp tục chỉ Admin/Kế toán.
         */
        Route::middleware('role:admin|accounting|management')->group(function () {
            /* EGO_FINANCE_EXTENDED_LEDGER_V1_START */
            Route::prefix('ledger/{direction}/{category}')
                ->where([
                    'direction' => 'payable',
                    'category' => 'import_goods|bank|loan|shareholder',
                ])
                ->name('ledger.')
                ->controller(FinanceLedgerController::class)
                ->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('/export', 'export')->name('export');
                    Route::post('/', 'store')->name('store');
                    Route::put('/{entry}', 'update')->whereNumber('entry')->name('update');
                    Route::delete('/{entry}', 'destroy')->whereNumber('entry')->name('destroy');
                    Route::post('/{entry}/transactions', 'storeTransaction')->whereNumber('entry')->name('transactions.store');
                    Route::delete('/{entry}/transactions/{transaction}', 'destroyTransaction')
                        ->whereNumber('entry')
                        ->whereNumber('transaction')
                        ->name('transactions.destroy');
                });
            /* EGO_FINANCE_EXTENDED_LEDGER_V1_END */

            Route::prefix('receipts')->name('receipts.')->group(function () {
                Route::get('/', [ReceiptController::class, 'index'])->name('index');
                Route::get('/create', [ReceiptController::class, 'create'])->name('create');
                Route::post('/', [ReceiptController::class, 'store'])->name('store');
                Route::delete('/{receipt}', [ReceiptController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('payments')->name('payments.')->group(function () {
                Route::get('/', [PaymentController::class, 'index'])->name('index');
                Route::get('/create', [PaymentController::class, 'create'])->name('create');
                Route::post('/', [PaymentController::class, 'store'])->name('store');
                Route::delete('/{payment}', [PaymentController::class, 'destroy'])->name('destroy');
            });

            Route::prefix('accounts')->name('accounts.')->group(function () {
                Route::get('/', [AccountController::class, 'index'])->name('index');
                Route::get('/create', [AccountController::class, 'create'])->name('create');
                Route::post('/', [AccountController::class, 'store'])->name('store');
                Route::get('/{account}/edit', [AccountController::class, 'edit'])->name('edit');
                Route::put('/{account}', [AccountController::class, 'update'])->name('update');
                Route::delete('/{account}', [AccountController::class, 'destroy'])->name('destroy');
            });

            Route::get('/payment-request', [FinanceDashboardController::class, 'paymentRequest'])->name('payment-request');
            Route::get('/salary', [FinanceDashboardController::class, 'salary'])->name('salary');
            Route::get('/salary/exports/excel', [FinanceDashboardController::class, 'exportSalaryExcel'])->name('salary.export.excel');
            Route::get('/salary/my', [FinanceDashboardController::class, 'mySalary'])->name('salary.my');

            Route::middleware('role:admin')->group(function () {
                Route::get('/salary/settings', [FinanceDashboardController::class, 'salarySlipSettings'])->name('salary.settings');
                Route::post('/salary/settings/general', [FinanceDashboardController::class, 'saveSalarySlipSettings'])->name('salary.settings.general');
                Route::post('/salary/settings/components', [FinanceDashboardController::class, 'createSalarySlipComponent'])->name('salary.settings.components.store');
                Route::put('/salary/settings/components/{component}', [FinanceDashboardController::class, 'updateSalarySlipComponent'])->whereNumber('component')->name('salary.settings.components.update');
                Route::delete('/salary/settings/components/{component}', [FinanceDashboardController::class, 'deleteSalarySlipComponent'])->whereNumber('component')->name('salary.settings.components.destroy');
            });

            Route::get('/salary/{user}', [FinanceDashboardController::class, 'salaryDetail'])->name('salary.detail');
            Route::put('/salary/{user}', [FinanceDashboardController::class, 'saveSalaryDetail'])->name('salary.detail.update');
            Route::get('/budget', [BudgetController::class, 'index'])->name('budget');

            Route::post('/budget', [BudgetController::class, 'store'])
                ->name('budget.store');

            Route::put('/budget/{id}', [BudgetController::class, 'update'])
                ->whereNumber('id')
                ->name('budget.update');

            Route::delete('/budget/{id}', [BudgetController::class, 'destroy'])
                ->whereNumber('id')
                ->name('budget.destroy');
            Route::put('/salary', [FinanceDashboardController::class, 'saveSalary'])->name('salary.save');
            Route::get('/reports/settlement', [FinanceReportController::class, 'settlement'])->name('settlement');
            Route::get('/reports/audit', [FinanceReportController::class, 'audit'])->name('audit');
            Route::get('/reports', [FinanceReportController::class, 'index'])->name('reports');
        });
    });

/* EGO_SALES_WORK_REPORT_DETAIL_JSON_END */

/* EGO_THAO_FORCE_DELETE_PAYMENT_REQUEST_ONLY_START */
/*
| Xoá phiếu RỒI tính lại số dư công nợ nhà cung cấp — endpoint đang dùng thật,
| gọi từ resources/views/payment-requests/_buibichthao_actions.blade.php.
| Logic tính lại số dư (98 dòng cũ ở đây) nay ở SupplierDebtBalanceCalculator.
*/
Route::middleware(['auth'])
    ->post('/payment-requests/{id}/force-delete-by-thao', [PrivilegedPaymentRequestController::class, 'forceDestroy'])
    ->whereNumber('id')
    ->name('payment_requests.force-delete-by-thao');

/* EGO_WAREHOUSE_SUPPLIER_DEBT_READONLY_START */
/*
|--------------------------------------------------------------------------
| Kho - Công nợ nhà cung cấp (READ ONLY)
|--------------------------------------------------------------------------
|
| Role warehouse chỉ được XEM danh sách công nợ NCC và tải file.
| Các POST / PUT / DELETE vẫn nằm trong Finance dành cho Admin/Kế toán.
|
*/
Route::middleware(['auth', 'role:admin|accounting|management|warehouse'])
    ->group(function () {

        Route::get(
            '/finance/supplier-debts',
            [SupplierDebtController::class, 'index']
        )->name('warehouse.supplier-debts.index');

        Route::get(
            '/finance/supplier-debts/files/{fileId}/download',
            [SupplierDebtController::class, 'downloadDebtFile']
        )
            ->whereNumber('fileId')
            ->name('warehouse.supplier-debts.files.download');
    });
/* EGO_WAREHOUSE_SUPPLIER_DEBT_READONLY_END */
