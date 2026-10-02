<?php

/*
|---------------------------------------------------------------------------
| Route: Marketing và lịch nội dung
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

use App\Http\Controllers\Marketing\MarketingPlanFilePreviewController;
use Illuminate\Support\Facades\Route;

/* EGO_SALES_WORK_REPORTS_END */

/* EGO_FIX_MARKETING_PLAN_FILE_ROUTE_START */
Route::middleware(['auth', 'role:marketing|marketing_manager|admin|accounting'])
    ->get('/marketing/plan/file/{file}', [\App\Http\Controllers\Marketing\MarketingPlanController::class, 'file'])
    ->whereNumber('file')
    ->name('marketing.plan.file');

/* EGO_FIX_MARKETING_PLAN_FILE_ROUTE_END */

/* EGO_MARKETING_PLAN_FILE_PREVIEW_START */
/*
| Xem nhanh tệp đính kèm kế hoạch marketing.
|
| 344 dòng logic từng nằm thẳng ở đây (dò bảng, phân giải đường dẫn, đọc Excel,
| nối chuỗi HTML) nay chia ra: MarketingPlanFilePreviewController +
| PlanFileLocator + LocalFilePathResolver + FilePreviewRenderer + view
| marketing.plan.file-preview.
*/
Route::middleware(['auth', 'role:marketing|marketing_manager|admin|accounting'])
    ->get('/marketing/plan/file-preview/{file}', MarketingPlanFilePreviewController::class)
    ->whereNumber('file')
    ->name('marketing.plan.file.preview');
