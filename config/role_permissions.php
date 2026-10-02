<?php

return [
    'admin_roles' => ['admin'],

    /*
    | Khi một role chưa bật page_access_enabled, hệ thống giữ nguyên cách phân
    | quyền cũ để không khóa nhầm nhân sự đang sử dụng CRM.
    */
    'legacy_compatibility' => true,

    /*
    | Các quyền nhạy cảm luôn được kiểm tra từ backend,
    | kể cả role chưa bật toàn bộ ma trận quyền truy cập trang.
    */
    /*
    | User KHÔNG được gán role nào thì luôn bị kiểm soát quyền trang (mặc định).
    |
    | Đặt false để quay lại hành vi cũ — nhưng nhớ rằng hành vi cũ mở gần như
    | toàn bộ hệ thống cho tài khoản không role, kể cả trang Cài đặt.
    */
    'enforce_users_without_role' => env('EGO_ENFORCE_USERS_WITHOUT_ROLE', true),

    /*
    | Chặn đăng nhập với tài khoản is_active = 0.
    |
    | MẶC ĐỊNH TẮT vì dữ liệu is_active trên production chưa đáng tin: rà ngày
    | 2026-08-05 có 4 tài khoản mang role nhưng is_active = 0, trong đó một tài
    | khoản admin VẪN ĐANG dùng hệ thống. Bật lên là khoá người đang làm việc.
    | Dọn dữ liệu bằng `php artisan permissions:audit-users` rồi mới bật.
    */
    'enforce_active_account_on_login' => env('EGO_ENFORCE_ACTIVE_LOGIN', false),

    /*
    | Quyền trang LUÔN được kiểm, kể cả khi pageControlEnabled() trả false.
    |
    | Vì sao cần: canAccess() có nhánh "chưa bật kiểm soát thì cho qua" dành cho
    | role chưa được gán quyền trang nào (legacy_compatibility). Nhánh đó là cửa
    | mở: một role tạo mới mà quên gán quyền trang sẽ vào được MỌI trang.
    |
    | Đo trên production 2026-09-02:
    |  - `page.finance` chỉ được cấp cho admin, accounting, management. Các role
    |    khác hiện đã bị chặn khỏi /finance rồi, nên đưa vào đây KHÔNG đổi quyền
    |    của ai hôm nay — nó chặn trước cho role tạo sau này mà quên gán quyền.
    |  - `page.payment_requests` thì CỐ Ý KHÔNG đưa vào: cả 13 role đều đã được
    |    cấp quyền này nên nó không lọc ai cả; lớp chặn thật của module ĐNTT nằm
    |    trong controller (`canEditPaymentRequest`, lọc `created_by` ở
    |    `applyCommonFilters`). Thử đưa vào thì 6 test đặc tả của
    |    PrivilegedPaymentRequestCharacterizationTest đổi 404 -> 403 — đổi hành vi
    |    thật để đối lấy gần như không thêm an toàn nào.
    |
    | Ai đọc mục này khi thêm mới: chỉ đưa vào đây quyền của module nhạy cảm
    | (tiền, nhân sự, cài đặt), và phải ĐO trên production xem có role thật nào
    | mất quyền không, rồi chạy full test để xem có đổi hành vi ở đâu.
    */
    'always_enforce_permissions' => [
        'page.orders',
        'page.finance',
    ],

    /*
    | Vai trò hệ thống — KHÔNG viết tay ở đây nữa.
    |
    | Nguồn sự thật là App\Enums\Role. Trước đây danh sách này chép tay và đã
    | trôi khỏi thực tế; nay lấy thẳng từ enum để không thể lệch.
    */
    'protected_roles' => App\Enums\Role::names(),

    /*
    |---------------------------------------------------------------------------
    | Tên role cũ còn nằm trong middleware nhưng KHÔNG tồn tại trong DB
    |---------------------------------------------------------------------------
    |
    | 12 tên dưới đây xuất hiện trong `role:a|b|c` ở 108 route nhưng không có
    | role nào mang tên đó. Spatie KHÔNG báo lỗi với tên lạ — nó chỉ lặng lẽ
    | không khớp ai. Vì vậy chúng vô hại về bảo mật nhưng gây hiểu nhầm khi đọc
    | code: thấy `role:technical|technical|technician` dễ tưởng ba nhóm vào được,
    | thực tế chỉ `technical`.
    |
    | CỐ Ý GIỮ LẠI (chủ hệ thống quyết 2026-08-06) thay vì rà xoá ở 108 route:
    | rủi ro sửa nhầm cao hơn lợi ích, và có thể sau này tạo thật các role đó.
    |
    | Vai trò của danh sách này là làm HÀNG RÀO: mọi tên trong middleware phải
    | hoặc là role thật (`protected_roles`), hoặc nằm ở đây. Thêm một tên lạ thứ
    | 13 — thường là lỗi chính tả — sẽ làm test đỏ ngay.
    | Xem tests/Feature/Auth/RoleNameContractTest.php và `php artisan authz:audit`.
    |
    | Ghi chú bên dưới là ĐO THẬT chứ không suy đoán: với mỗi tên, đã đối chiếu
    | xem trên chính những route dùng nó có role thật nào cùng đứng. Nhờ vậy trả
    | lời được câu "tên này có role thay thế sẵn chưa".
    |
    | 8/12 tên đã có role thật tương đương đứng cùng trên 100% route của nó, tức
    | xoá đi không đổi ai vào được. 4 tên còn lại (manager, cskh, assistant,
    | tro_ly) KHÔNG có role tương đương — nếu sau này công ty có nhân sự đúng
    | những vai đó thì phải tạo role thật, chứ tên trong middleware không tự sinh
    | ra quyền.
    |
    */
    'legacy_role_aliases' => [
        /*
        | 2026-08-06 đã DỌN 10 trong 12 tên: 8 tên có role thật tương đương đứng
        | cùng trên 100% route của nó nên gỡ thẳng khỏi middleware; 2 tên `cskh`
        | và `assistant` nay đã thành role thật (xem App\Enums\Role).
        |
        | `technical` được ĐỔI TÊN thành `technical` chứ không gỡ, nhờ vậy bốn tên
        | tiếng Anh vốn không tồn tại (technical, technician, technical_staff,
        | technical_leader) bỏ đi được mà không mất nhóm nào.
        */
        'manager' => 'Quản lý chung — role `management` KHÔNG đứng cùng trên 45 route dùng tên này. Muốn dùng thật phải tạo role `manager` hoặc thêm `management` vào middleware.',
    ],

    /*
    | Chỉ các biểu thức role cấp module bên dưới mới được quyền dùng page.*
    | thay thế role cũ. Các route nhạy cảm như duyệt/xóa vẫn giữ lớp role hoặc
    | permission chuyên biệt đang có trong source.
    */
    'legacy_role_fallbacks' => [
        'marketing|marketing_manager|admin',
        'marketing|marketing_manager|admin|accounting',
        'technical|accounting|admin|warehouse|sales',
        'admin|accounting',
        'admin|accounting|manager',
        'technical|accounting|admin|manager',
        'technical|technical_manager|accounting|admin|manager|warehouse|sales|sales_manager|cskh',
    ],

    'page_permissions' => [
        'page.dashboard' => [
            'label' => 'Dashboard điều hành',
            'description' => 'Xem trang tổng quan, chỉ số và cảnh báo điều hành.',
            'icon' => 'bi-speedometer2',
            'group' => 'Truy cập trang',
            'routes' => ['dashboard'],
            'exact_paths' => ['/'],
            'path_prefixes' => [],
        ],
        'page.booking' => [
            'label' => 'Booking phòng họp',
            'description' => 'Xem và thao tác module đặt phòng họp.',
            'icon' => 'bi-calendar2-check',
            'group' => 'Truy cập trang',
            'routes' => ['meeting-room-bookings.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/booking-phong-hop'],
        ],
        'page.customers' => [
            'label' => 'Khách hàng',
            'description' => 'Truy cập danh sách khách hàng và hồ sơ khách hàng.',
            'icon' => 'bi-people',
            'group' => 'Truy cập trang',
            'routes' => ['customers.*', 'customer-profiles.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/customers', '/customer-profiles'],
        ],
        'page.orders' => [
            'label' => 'Đơn hàng & Báo giá',
            'description' => 'Truy cập đơn hàng, báo giá, đổi trả và hoàn tiền.',
            'icon' => 'bi-receipt',
            'group' => 'Truy cập trang',
            'routes' => ['orders.*', 'sales-quotations.*', 'order-returns.*', 'order-refunds.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/orders', '/bao-gia', '/order-returns', '/order-refunds'],
        ],
        'page.sites' => [
            'label' => 'Công trình & Đơn vật tư',
            'description' => 'Truy cập công trình, lắp ráp và quy trình đơn vật tư.',
            'icon' => 'bi-building-gear',
            'group' => 'Truy cập trang',
            'routes' => ['sites.*', 'sites-v2.*', 'project-test.*', 'material-requests.*', 'site-assemblies.*'],
            'exact_paths' => ['/theo-doi-trang-thai'],
            'path_prefixes' => ['/cong-trinh', '/cong-trinh-moi', '/cong-trinh-test-new', '/don-vat-tu'],
        ],
        'page.projects' => [
            'label' => 'Dự án',
            'description' => 'Truy cập màn hình dự án hợp nhất, bảo trì - bảo hành và kho bảo hành.',
            'icon' => 'bi-kanban',
            'group' => 'Truy cập trang',
            'routes' => ['projects-unified.*', 'projects.factory.*', 'projects.residential.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/du-an'],
        ],
        'page.payment_requests' => [
            'label' => 'Đề nghị thanh toán',
            'description' => 'Truy cập danh sách, chi tiết và hồ sơ đề nghị thanh toán.',
            'icon' => 'bi-cash-stack',
            'group' => 'Truy cập trang',
            'routes' => ['payment_requests.*', 'payment-requests.*', 'payment-attachments.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/payment-requests'],
        ],
        'page.proposals' => [
            'label' => 'Đề xuất nội bộ',
            'description' => 'Truy cập module đề xuất và quy trình phê duyệt.',
            'icon' => 'bi-lightbulb',
            'group' => 'Truy cập trang',
            'routes' => ['de-xuat.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/de-xuat'],
        ],
        'page.technical' => [
            'label' => 'Kỹ thuật & Bảo hành',
            'description' => 'Truy cập kỹ thuật, bảo trì, bảo hành và serial.',
            'icon' => 'bi-tools',
            'group' => 'Truy cập trang',
            'routes' => ['ky-thuat.*', 'serial-warranty.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/ky-thuat', '/serial-warranty'],
        ],
        'page.sales' => [
            'label' => 'Sales',
            'description' => 'Truy cập KPI, hoa hồng và báo cáo công việc Sales.',
            'icon' => 'bi-graph-up-arrow',
            'group' => 'Truy cập trang',
            'routes' => ['sales.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/sales'],
        ],
        'page.marketing' => [
            'label' => 'Marketing',
            'description' => 'Truy cập kế hoạch, chiến dịch, lead, KPI và báo cáo Marketing.',
            'icon' => 'bi-megaphone',
            'group' => 'Truy cập trang',
            'routes' => ['marketing.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/marketing'],
        ],
        'page.tasks' => [
            'label' => 'Công việc',
            'description' => 'Truy cập danh sách, giao việc và kết quả công việc.',
            'icon' => 'bi-list-check',
            'group' => 'Truy cập trang',
            'routes' => ['tasks.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/chat/tasks'],
        ],
        'page.products' => [
            'label' => 'Sản phẩm',
            'description' => 'Truy cập sản phẩm, danh mục, thương hiệu, bảng giá và nhập hàng.',
            'icon' => 'bi-box-seam',
            'group' => 'Truy cập trang',
            'routes' => ['products.*', 'categories.*', 'brands.*', 'price-tiers.*', 'media.*', 'product-goods-receipts.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/products', '/san-pham-kho', '/categories', '/brands', '/price-tiers', '/media'],
        ],
        'page.warehouses' => [
            'label' => 'Kho hàng',
            'description' => 'Truy cập kho, tồn kho và kiểm kê.',
            'icon' => 'bi-boxes',
            'group' => 'Truy cập trang',
            'routes' => ['warehouses.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/warehouses'],
        ],
        'page.finance' => [
            'label' => 'Tài chính',
            'description' => 'Truy cập công nợ, thu chi, tài khoản, ngân sách và báo cáo tài chính.',
            'icon' => 'bi-bank',
            'group' => 'Truy cập trang',
            'routes' => ['finance.*', 'payment-methods.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/finance', '/payment-methods'],
        ],
        'page.hr' => [
            'label' => 'Nhân sự',
            'description' => 'Truy cập nhân sự, tuyển dụng, hồ sơ, chấm công và hành chính.',
            'icon' => 'bi-person-workspace',
            'group' => 'Truy cập trang',
            'routes' => ['hr.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/nhan-su', '/hr'],
            'exclude_prefixes' => [
                // Nhân viên nào đăng nhập cũng được sử dụng chấm công cá nhân
                '/nhan-su/cham-cong-cua-toi',
                '/nhan-su/cham-cong/check-in',
                '/nhan-su/cham-cong/check-out',
                '/nhan-su/cham-cong/yeu-cau-sua',
                '/nhan-su/huong-dan-cham-cong',
            ],
        ],
        'page.chat' => [
            'label' => 'Tin nhắn',
            'description' => 'Truy cập hội thoại và tin nhắn nội bộ.',
            'icon' => 'bi-chat-dots',
            'group' => 'Truy cập trang',
            'routes' => ['chat.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/chat'],
            'exclude_prefixes' => ['/chat/tasks'],
        ],
        'page.company' => [
            'label' => 'Công ty & Pháp nhân',
            'description' => 'Truy cập quản lý công ty, pháp nhân và tài liệu doanh nghiệp.',
            'icon' => 'bi-buildings',
            'group' => 'Truy cập trang',
            'routes' => ['company-management.*', 'companies.*', 'company-documents.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/company-management', '/companies', '/company-documents'],
        ],
        'page.solar' => [
            'label' => 'Công cụ Solar',
            'description' => 'Truy cập tính toán và cấu hình công cụ Solar.',
            'icon' => 'bi-sun',
            'group' => 'Truy cập trang',
            'routes' => ['solar.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/solar'],
        ],
        'page.users' => [
            'label' => 'Quản lý người dùng',
            'description' => 'Truy cập danh sách và biểu mẫu tài khoản người dùng.',
            'icon' => 'bi-person-gear',
            'group' => 'Truy cập trang',
            'routes' => ['users.index', 'users.create', 'users.store', 'users.edit', 'users.update', 'users.destroy'],
            'exact_paths' => [],
            'path_prefixes' => ['/users'],
        ],
        'page.settings' => [
            'label' => 'Cài đặt hệ thống',
            'description' => 'Truy cập trung tâm cài đặt, vai trò, quyền trang, quyền menu và nhật ký.',
            'icon' => 'bi-shield-lock',
            'group' => 'Truy cập trang',
            'routes' => ['admin.settings.*', 'admin.role-permissions.*'],
            'exact_paths' => [],
            'path_prefixes' => ['/cai-dat'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quyền hiển thị menu
    |--------------------------------------------------------------------------
    | menu.* chỉ quyết định mục nào xuất hiện trong sidebar.
    | page.* mới là lớp chặn truy cập URL ở backend.
    */
    'menu_permissions' => [
        'menu.dashboard' => [
            'label' => 'Trang chủ',
            'description' => 'Hiển thị Dashboard trong sidebar.',
            'icon' => 'bi-house-door',
            'page_permission' => 'page.dashboard',
        ],
        'menu.booking' => [
            'label' => 'Booking phòng họp',
            'description' => 'Hiển thị module đặt phòng họp.',
            'icon' => 'bi-calendar2-check',
            'page_permission' => 'page.booking',
        ],
        'menu.customers' => [
            'label' => 'Khách hàng',
            'description' => 'Hiển thị nhóm menu khách hàng.',
            'icon' => 'bi-people',
            'page_permission' => 'page.customers',
        ],
        'menu.orders' => [
            'label' => 'Đơn hàng',
            'description' => 'Hiển thị nhóm menu đơn hàng và báo giá.',
            'icon' => 'bi-receipt',
            'page_permission' => 'page.orders',
        ],
        'menu.project_test' => [
            'label' => 'Công Trình Test new',
            'description' => 'Hiển thị quy trình công trình thử nghiệm mới.',
            'icon' => 'bi-diagram-3',
            'page_permission' => 'page.sites',
        ],
        'menu.sites' => [
            'label' => 'Công trình',
            'description' => 'Hiển thị công trình, đơn vật tư và lắp ráp.',
            'icon' => 'bi-building-gear',
            'page_permission' => 'page.sites',
        ],
        'menu.payment_requests' => [
            'label' => 'Đề nghị thanh toán',
            'description' => 'Hiển thị đề nghị thanh toán.',
            'icon' => 'bi-cash-stack',
            'page_permission' => 'page.payment_requests',
        ],
        'menu.proposals' => [
            'label' => 'Đề xuất',
            'description' => 'Hiển thị đề xuất nội bộ.',
            'icon' => 'bi-lightbulb',
            'page_permission' => 'page.proposals',
        ],
        'menu.technical' => [
            'label' => 'Kỹ thuật',
            'description' => 'Hiển thị kỹ thuật, bảo trì và bảo hành.',
            'icon' => 'bi-tools',
            'page_permission' => 'page.technical',
        ],
        'menu.sales' => [
            'label' => 'Sales',
            'description' => 'Hiển thị KPI, hoa hồng và báo cáo Sales.',
            'icon' => 'bi-graph-up-arrow',
            'page_permission' => 'page.sales',
        ],
        'menu.marketing' => [
            'label' => 'Marketing',
            'description' => 'Hiển thị kế hoạch và báo cáo Marketing.',
            'icon' => 'bi-megaphone',
            'page_permission' => 'page.marketing',
        ],
        'menu.tasks' => [
            'label' => 'Công việc',
            'description' => 'Hiển thị việc của tôi và giao việc.',
            'icon' => 'bi-list-check',
            'page_permission' => 'page.tasks',
        ],
        'menu.products' => [
            'label' => 'Sản phẩm',
            'description' => 'Hiển thị sản phẩm, kho và nhập xuất.',
            'icon' => 'bi-box-seam',
            'page_permission' => 'page.products',
        ],
        'menu.finance' => [
            'label' => 'Tài chính',
            'description' => 'Hiển thị công nợ, thu chi và báo cáo tài chính.',
            'icon' => 'bi-bank',
            'page_permission' => 'page.finance',
        ],
        'menu.company' => [
            'label' => 'Hồ sơ công ty',
            'description' => 'Hiển thị hồ sơ pháp nhân và tài liệu công ty.',
            'icon' => 'bi-folder2-open',
            'page_permission' => 'page.company',
        ],
        'menu.hr' => [
            'label' => 'Nhân sự',
            'description' => 'Hiển thị tuyển dụng, hồ sơ và chấm công.',
            'icon' => 'bi-person-workspace',
            'page_permission' => 'page.hr',
        ],
        'menu.settings' => [
            'label' => 'Cài đặt',
            'description' => 'Hiển thị trung tâm Cài đặt hệ thống.',
            'icon' => 'bi-gear',
            'page_permission' => 'page.settings',
        ],
    ],

    'group_labels' => [
        'lead' => ['label' => 'Lead', 'icon' => 'bi-person-plus'],
        'customer' => ['label' => 'Khách hàng', 'icon' => 'bi-people'],
        'order' => ['label' => 'Đơn hàng', 'icon' => 'bi-receipt'],
        'orders' => ['label' => 'Đổi trả & Hoàn tiền', 'icon' => 'bi-arrow-left-right'],
        'payment' => ['label' => 'Thanh toán', 'icon' => 'bi-cash-stack'],
        'product' => ['label' => 'Sản phẩm', 'icon' => 'bi-box-seam'],
        'products' => ['label' => 'Sản phẩm', 'icon' => 'bi-box-seam'],
        'categories' => ['label' => 'Danh mục', 'icon' => 'bi-tags'],
        'brands' => ['label' => 'Thương hiệu', 'icon' => 'bi-award'],
        'price-tiers' => ['label' => 'Bảng giá', 'icon' => 'bi-currency-dollar'],
        'warehouse' => ['label' => 'Kho hàng', 'icon' => 'bi-boxes'],
        'site' => ['label' => 'Công trình', 'icon' => 'bi-building-gear'],
        'material_request' => ['label' => 'Đơn vật tư', 'icon' => 'bi-clipboard-check'],
        'report' => ['label' => 'Báo cáo', 'icon' => 'bi-bar-chart'],
        'user' => ['label' => 'Người dùng', 'icon' => 'bi-person-gear'],
        'maintenance' => ['label' => 'Bảo trì/Bảo hành', 'icon' => 'bi-tools'],
        'settings' => ['label' => 'Quản trị hệ thống', 'icon' => 'bi-gear'],
        'hr' => ['label' => 'Nhân sự', 'icon' => 'bi-person-workspace'],
        'tasks' => ['label' => 'Công việc', 'icon' => 'bi-check2-square'],
        'project-test' => ['label' => 'Dự án thử nghiệm', 'icon' => 'bi-kanban'],
        'system' => ['label' => 'Hệ thống', 'icon' => 'bi-hdd-stack'],
        'setting' => ['label' => 'Cấu hình', 'icon' => 'bi-sliders'],
    ],

    'action_labels' => [
        'view' => 'Xem',
        'view_all' => 'Xem tất cả',
        'view_own' => 'Xem dữ liệu của mình',
        'view_sales_all' => 'Xem toàn bộ Sales',
        'view_sales' => 'Xem Sales',
        'view_status' => 'Xem trạng thái',
        'view_warehouse' => 'Xem cho kho',
        'create' => 'Tạo mới',
        'update' => 'Cập nhật',
        'update_all' => 'Cập nhật tất cả',
        'update_own' => 'Cập nhật dữ liệu của mình',
        'update_sales' => 'Cập nhật Sales',
        'delete' => 'Xóa',
        'approve' => 'Duyệt',
        'reject' => 'Từ chối',
        'submit' => 'Gửi duyệt',
        'assign' => 'Phân công',
        'manage' => 'Quản lý',
        'export' => 'Xuất dữ liệu',
        'stock_check' => 'Kiểm kho',
        'stock_update' => 'Cập nhật tồn kho',
        'mark_paid' => 'Đánh dấu đã thanh toán',
        'force_approve' => 'Duyệt cưỡng bức',
        'approve_level1' => 'Duyệt cấp 1',
        'approve_level2' => 'Duyệt cấp 2',
        'approve_accounting' => 'Kế toán duyệt',
        'request_revision' => 'Yêu cầu chỉnh sửa',
        'reopen' => 'Mở lại',
        'restore' => 'Khôi phục',
        'upload' => 'Tải lên',
        'download' => 'Tải xuống',
        'inspect' => 'Kiểm tra',
        'receive' => 'Tiếp nhận',
        'stock_in' => 'Nhập kho',
        'process' => 'Xử lý',
        'rate' => 'Đánh giá',
        'convert' => 'Chuyển đổi',
        'check_debt' => 'Kiểm tra công nợ',
        'handover' => 'Bàn giao',
        'revenue' => 'Doanh thu',
        'view_basic' => 'Xem cơ bản',
        'view_logs' => 'Xem nhật ký',
        'view_staff' => 'Xem nhân sự',
        'manage_sales' => 'Quản lý Sales',
        'leave_approve_department' => 'Duyệt nghỉ phép trong phòng',
        'leave_manage_all' => 'Quản lý toàn bộ nghỉ phép',
        'leave_transfer' => 'Chuyển người duyệt nghỉ phép',
        'assign_department' => 'Giao việc trong phòng',
        'manage_all' => 'Quản lý toàn bộ',
        'files_view' => 'Xem tệp đính kèm',
        'files_upload' => 'Tải tệp lên',
        'files_delete' => 'Xoá tệp đính kèm',
        'materials_manage' => 'Quản lý vật tư',
        'reports_view' => 'Xem báo cáo',
        'settings_manage' => 'Quản lý cấu hình',
        'refund_create' => 'Tạo phiếu hoàn tiền',
        'refund_approve' => 'Duyệt hoàn tiền',
        'refund_process' => 'Xử lý hoàn tiền',
        'return_view' => 'Xem phiếu trả hàng',
        'return_create' => 'Tạo phiếu trả hàng',
        'return_update' => 'Sửa phiếu trả hàng',
        'return_approve' => 'Duyệt trả hàng',
        'return_receive' => 'Nhận hàng trả',
        'return_inspect' => 'Kiểm tra hàng trả',
        'return_stock_in' => 'Nhập kho hàng trả',
        'return_reports_view' => 'Xem báo cáo trả hàng',
        'audit_view' => 'Xem nhật ký phân quyền',
        'menus_manage' => 'Quản lý quyền menu',
        'pages_manage' => 'Quản lý quyền trang',
        'roles_view' => 'Xem vai trò',
        'roles_manage' => 'Quản lý vai trò',
        'users_manage' => 'Quản lý người dùng',
        'access' => 'Truy cập',
        'acceptance' => 'Nghiệm thu',
        'admin' => 'Quản trị',
        'sales' => 'Vai trò Sales',
        'technical' => 'Vai trò Kỹ thuật',
        'technical-manager' => 'Vai trò Quản lý kỹ thuật',
        'warehouse' => 'Vai trò Kho',
        'system' => 'Cấu hình hệ thống',
    ],
];
