<?php

/*
|---------------------------------------------------------------------------
| Route: Khách hàng và lead
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

/* EGO_ORDER_DOCUMENTS_PROFILE_ROUTES_END */

/* EGO_CUSTOMER_PROFILE_DOCUMENT_PREVIEW_START */
Route::middleware(['auth'])->get(
    '/customer-profiles/{customerProfile}/documents/{document}/preview-ego',
    \App\Http\Controllers\CRM\EgoCustomerProfileDocumentPreviewController::class
)->whereNumber('customerProfile')->whereNumber('document')->name('customer-profiles.documents.preview-ego');
