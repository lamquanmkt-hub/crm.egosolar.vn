<?php

/**
 * Web Routes - Refactored
 *
 * Áp dụng:
 * - Clean Code: Không có inline controller logic
 * - Single Responsibility: Routes chỉ định nghĩa URL mapping
 * - DRY: Sử dụng route groups để tránh lặp middleware
 * - RESTful: Sử dụng resource routes khi có thể
 *
 * @author Your Name
 *
 * @version 2.0
 */

/* EGO_FIX_BAO_GIA_ROUTE_TOP_START */

/*
|---------------------------------------------------------------------------
| Bảng định tuyến — đã tách theo domain
|---------------------------------------------------------------------------
|
| File này trước đây dài 2.654 dòng. Nay chỉ nạp các file theo domain.
|
| ⚠️ THỨ TỰ NẠP CÓ Ý NGHĨA: Laravel khớp route ĐẦU TIÊN trùng method+URI.
| Thứ tự dưới đây giữ đúng thứ tự khai báo của file gốc — đừng sắp xếp lại
| theo bảng chữ cái.
|
*/

require __DIR__.'/sales.php';
require __DIR__.'/system.php';
require __DIR__.'/auth.php';
require __DIR__.'/tasks.php';
require __DIR__.'/projects.php';
require __DIR__.'/inventory.php';
require __DIR__.'/orders.php';
require __DIR__.'/finance.php';
require __DIR__.'/technical.php';
require __DIR__.'/marketing.php';
require __DIR__.'/customers.php';

/* EGO_SITE_ASSEMBLY_ROUTES_END */

require __DIR__.'/hr.php';
/* EGO_VPP_DETAIL_POPUP_ROUTE_END */

/* EGO_BOOKING_ROOM_ROUTES_START */
require __DIR__.'/booking_room.php';
/* EGO_AI_ASSISTANT_ROUTES_START */
require __DIR__.'/ai_assistant.php';
/* EGO_AI_ASSISTANT_ROUTES_END */

/* EGO_ROLE_PERMISSION_SETTINGS_ROUTES */
require __DIR__.'/role_permissions.php';
/* EGO_PROJECT_TEST_NEW_ROUTES_START */
require __DIR__.'/project_unified.php';
require __DIR__.'/project_test.php';

/*
| {EGO_PROJECT_WORKFLOW_DOCUMENT_SETTINGS_ROUTES}
|
| ⚠️ PHẢI dùng `require`, KHÔNG được `require_once`: trong tiến trình chạy dài
| (queue worker, Octane, `artisan test`) app boot nhiều lần, `require_once` chỉ
| nạp file ở lần boot ĐẦU nên từ lần thứ hai trở đi 2 route trong file này BIẾN
| MẤT. Trên production có route:cache nên lỗi bị che, nhưng khi cache bị xoá
| hoặc chạy worker thì lộ ra.
*/
require __DIR__.'/project_workflow_document_settings.php';

/* EGO_DEPARTMENT_WORKSPACE_V1_REQUIRE */
require __DIR__.'/workspace.php';

/* EGO_WAREHOUSE_SUPPLIER_DEBT_READONLY_ROUTES */

/*
 * EGO_WAREHOUSE_SUPPLIER_DEBT_FULL_ROUTES
 * Phải đặt CUỐI để route này thắng route Finance cũ.
 */
require __DIR__.'/warehouse_supplier_debts.php';
