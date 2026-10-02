<?php

use App\Http\Controllers\Finance\SupplierDebtController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Công nợ nhà cung cấp - Admin / Kế toán / Kho
|--------------------------------------------------------------------------
|
| Kho được sử dụng toàn bộ nghiệp vụ Công nợ NCC.
| Không mở các phần Finance khác.
|
*/

Route::middleware([
    'auth',
    'role:admin|accounting|warehouse',
])
    ->prefix('finance/supplier-debts')
    ->name('finance.supplier-debts.')
    ->group(function () {

        // Danh sách
        Route::get(
            '/',
            [SupplierDebtController::class, 'index']
        )->name('index');

        // Thêm công nợ
        Route::post(
            '/',
            [SupplierDebtController::class, 'store']
        )->name('store');

        // File công nợ
        Route::post(
            '/{id}/files',
            [SupplierDebtController::class, 'storeDebtFileOnly']
        )
            ->whereNumber('id')
            ->name('files.store');

        Route::get(
            '/files/{fileId}/download',
            [SupplierDebtController::class, 'downloadDebtFile']
        )
            ->whereNumber('fileId')
            ->name('files.download');

        Route::delete(
            '/files/{fileId}',
            [SupplierDebtController::class, 'destroyDebtFile']
        )
            ->whereNumber('fileId')
            ->name('files.destroy');

        // Sửa / xóa công nợ
        Route::put(
            '/{id}',
            [SupplierDebtController::class, 'update']
        )
            ->whereNumber('id')
            ->name('update');

        Route::delete(
            '/{id}',
            [SupplierDebtController::class, 'destroy']
        )
            ->whereNumber('id')
            ->name('destroy');

        // Đợt thanh toán
        Route::post(
            '/{id}/payment-rounds',
            [SupplierDebtController::class, 'storePaymentRound']
        )
            ->whereNumber('id')
            ->name('payment-rounds.store');

        Route::put(
            '/payment-rounds/{paymentRoundId}',
            [SupplierDebtController::class, 'updatePaymentRound']
        )
            ->whereNumber('paymentRoundId')
            ->name('payment-rounds.update');

        Route::delete(
            '/payment-rounds/{paymentRoundId}',
            [SupplierDebtController::class, 'destroyPaymentRound']
        )
            ->whereNumber('paymentRoundId')
            ->name('payment-rounds.destroy');

        // Tạo ĐNTT từ đợt thanh toán
        Route::post(
            '/payment-rounds/{paymentRoundId}/payment-request',
            [SupplierDebtController::class, 'createPaymentRequestFromRound']
        )
            ->whereNumber('paymentRoundId')
            ->name('payment-rounds.create-payment-request');

        // Đợt còn lại
        Route::post(
            '/payment-rounds/{paymentRoundId}/create-remaining-round',
            [SupplierDebtController::class, 'createRemainingRoundFromLinkedPayment']
        )
            ->whereNumber('paymentRoundId')
            ->name('payment-rounds.create-remaining-round');
    });
