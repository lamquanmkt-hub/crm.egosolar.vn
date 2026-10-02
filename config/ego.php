<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Công ty vận hành mặc định
    |--------------------------------------------------------------------------
    |
    | CRM này chỉ vận hành kho/sản phẩm cho EGO Việt Nam. Trước đây id công ty
    | được viết cứng (`private const EGO_VN_COMPANY_ID = 1`) lặp lại ở 4
    | controller Inventory — mỗi lần đổi phải sửa 4 nơi và không test được.
    | Nay gom về một nguồn duy nhất, ghi đè được bằng biến môi trường
    | `EGO_DEFAULT_COMPANY_ID` (staging/test dùng id khác production).
    |
    */

    'default_company_id' => (int) env('EGO_DEFAULT_COMPANY_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Cache kiểm tra schema (giây)
    |--------------------------------------------------------------------------
    |
    | Code có hơn 1.400 lời gọi `hasTable()/hasColumn()`. App\Support\SchemaCache
    | đã gộp chúng lại trong phạm vi một request; bật thêm TTL dưới đây thì kết
    | quả còn dùng lại được GIỮA các request, gần như xoá sạch truy vấn
    | `information_schema` khỏi mọi trang.
    |
    | Cache tự huỷ khi chạy migration và khi có câu lệnh DDL lúc chạy
    | (xem AppServiceProvider). Đặt 0 để tắt — môi trường test dùng 0 để mỗi
    | test luôn nhìn schema thật.
    |
    */

    'schema_cache_ttl' => (int) env('EGO_SCHEMA_CACHE_TTL', 600),

    /*
    |--------------------------------------------------------------------------
    | Email được toàn quyền trên hồ sơ tài chính đã hoàn tất
    |--------------------------------------------------------------------------
    |
    | Hệ thống có một ngoại lệ: một người được sửa/xoá ĐNTT và công nợ KỂ CẢ khi
    | đã kế toán duyệt. Trước đây email của người đó bị viết CỨNG ở 12 chỗ trong
    | 5 file (middleware, 3 controller, routes/finance.php). Người này nghỉ việc
    | hay đổi mail là phải sửa code rồi deploy lại.
    |
    | Gom về một nguồn duy nhất, đổi được bằng biến môi trường mà không cần deploy:
    |     EGO_FINANCE_FULL_ACCESS_EMAILS="a@egosolar.vn,b@egosolar.vn"
    |
    | So khớp KHÔNG phân biệt hoa thường, giữ nguyên hành vi cũ.
    |
    | ⚠️ Đây là đặc quyền theo DANH TÍNH, không theo vai trò — về lâu dài nên
    | chuyển thành permission (`finance.edit_completed`) để quản trị được từ giao
    | diện phân quyền. Ở bước này chỉ gom mối, chưa đổi cách phân quyền.
    |
    */

    'finance_full_access_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('EGO_FINANCE_FULL_ACCESS_EMAILS', 'buibichthao@egosolar.vn')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Cột phi chuẩn hoá cần theo dõi (vi phạm 3NF loại B/C)
    |--------------------------------------------------------------------------
    |
    | Danh sách này KHÔNG phải để tự động sửa dữ liệu — nó là đầu vào cho lệnh
    | `php artisan db:check-denormalized`, chỉ ĐỌC và báo cáo chỗ đã lệch.
    | Xem DB_NORMALIZATION_AUDIT.md để biết vì sao mỗi cột nằm ở đây.
    |
    | Hai kiểu kiểm tra:
    | - `value`: cột cache phải khớp giá trị nguồn qua khoá ngoại.
    | - `mapping`: cột varchar và khoá ngoại phải ánh xạ 1-1 với nhau (dùng khi
    |   app vẫn đang tin cột varchar, so khớp chuỗi trực tiếp sẽ toàn báo giả).
    |
    */

    'denormalized_columns' => [
        [
            'check' => 'value',
            'table' => 'finance_supplier_debts',
            'column' => 'company_name',
            'foreign_key' => 'company_id',
            'references' => 'companies',
            'source_column' => 'name',
            'note' => 'Đổi tên công ty là báo cáo công nợ hiện tên cũ.',
        ],
        [
            'check' => 'value',
            'table' => 'solar_maintenance_schedules',
            'column' => 'site_name',
            'foreign_key' => 'site_id',
            'references' => 'sites',
            'source_column' => 'name',
            'note' => 'Đổi tên công trình là lịch bảo trì hiện tên cũ.',
        ],
        [
            'check' => 'value',
            'table' => 'sales_customer_followups',
            'column' => 'customer_phone',
            'foreign_key' => 'customer_id',
            'references' => 'crm_customers',
            'source_column' => 'phone',
            'note' => 'Sửa SĐT khách thì danh sách chăm sóc vẫn gọi số cũ.',
        ],
        [
            'check' => 'mapping',
            'table' => 'payment_requests',
            'column' => 'company',
            'foreign_key' => 'company_id',
            'references' => 'companies',
            'source_column' => 'name',
            'note' => 'App đang lọc/hiển thị theo cột varchar và CỐ Ý gỡ company_id khỏi request; hai cột đã mâu thuẫn trên production.',
        ],
    ],

];
