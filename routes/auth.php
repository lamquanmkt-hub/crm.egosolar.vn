<?php

/*
|---------------------------------------------------------------------------
| Route: Đăng nhập / đăng xuất
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

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/* EGO_COMPANY_CONTEXT_ROUTES_END */

/*
|--------------------------------------------------------------------------
| Public / Auth Routes
|--------------------------------------------------------------------------
*/
Route::controller(LoginController::class)->group(function () {
    Route::get('/login', 'showLoginForm')->name('login');
    Route::post('/login', 'login')->name('login.store');
    Route::post('/logout', 'logout')->name('logout');
});
