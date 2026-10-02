<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\User;
use App\Services\RolePermission\PageAccessService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class DepartmentWorkspaceService
{
    public function __construct(
        private readonly PageAccessService $pageAccess,
    ) {}

    public function definitions(): array
    {
        return [
            'executive' => [
                'label' => 'Ban Giám đốc',
                'short' => 'BGĐ',
                'icon' => 'bi-buildings-fill',
                'accent' => '#7c3aed',
                'description' => 'Điều hành, phê duyệt và theo dõi toàn công ty.',
            ],
            'sales' => [
                'label' => 'Kinh doanh',
                'short' => 'KD',
                'icon' => 'bi-graph-up-arrow',
                'accent' => '#0891b2',
                'description' => 'Khách hàng, đơn hàng, công trình và hoạt động bán hàng.',
            ],
            'technical' => [
                'label' => 'Kỹ thuật',
                'short' => 'KT',
                'icon' => 'bi-tools',
                'accent' => '#2563eb',
                'description' => 'Công trình, bảo trì, vật tư và công việc kỹ thuật.',
            ],
            'finance' => [
                'label' => 'TÀI CHÍNH KẾ TOÁN',
                'short' => 'KT',
                'icon' => 'bi-calculator-fill',
                'accent' => '#16a34a',
                'description' => 'Bảng tiền lương, báo cáo tài chính/thuế và công nợ.',
            ],
            'warehouse' => [
                'label' => 'Kho',
                'short' => 'KHO',
                'icon' => 'bi-box-seam-fill',
                'accent' => '#d97706',
                'description' => 'Sản phẩm, nhập xuất tồn và chuẩn bị vật tư.',
            ],
            'hr' => [
                'label' => 'Nhân sự',
                'short' => 'NS',
                'icon' => 'bi-people-fill',
                'accent' => '#db2777',
                'description' => 'Nhân sự, hành chánh, chấm công và hồ sơ.',
            ],
            'marketing' => [
                'label' => 'Marketing',
                'short' => 'MKT',
                'icon' => 'bi-megaphone-fill',
                'accent' => '#ea580c',
                'description' => 'Kế hoạch, lead, nội dung và báo cáo Marketing.',
            ],
        ];
    }

    public function canonical(string $workspace): string
    {
        return match ($workspace) {
            'sales_manager' => 'sales',
            'marketing_manager' => 'marketing',
            'technical_manager' => 'technical',
            default => $workspace,
        };
    }

    public function primary(User $user): string
    {
        $user->loadMissing(['department', 'roles', 'position']);

        if ($this->isExecutive($user)) {
            return 'executive';
        }

        $department = $this->normalize(
            trim((string) optional($user->department)->code.' '.(string) optional($user->department)->name)
        );

        $fromDepartment = match (true) {
            Str::contains($department, ['ban_giam_doc', 'giam_doc', 'management', 'director']) => 'executive',
            Str::contains($department, ['kinh_doanh', 'sales']) => 'sales',
            Str::contains($department, ['ky_thuat', 'technical']) => 'technical',
            Str::contains($department, ['ke_toan', 'ketoan', 'accounting', 'finance']) => 'finance',
            Str::contains($department, ['warehouse', 'kho']) => 'warehouse',
            Str::contains($department, ['nhan_su', 'human_resource', 'hanh_chinh', 'hanh_chanh']) => 'hr',
            Str::contains($department, ['marketing']) => 'marketing',
            default => null,
        };

        if ($fromDepartment !== null) {
            return $fromDepartment;
        }

        $roles = $user->roles
            ->pluck('name')
            ->map(fn ($name) => $this->normalize((string) $name));

        $roleMap = [
            'sales' => ['sales', 'sale', 'sales_staff', 'sales_manager', 'sales_leader', 'kinh_doanh', 'nhan_vien_kinh_doanh'],
            'technical' => ['technical', 'technician', 'technical_staff', 'technical_manager', 'technical_leader'],
            'finance' => ['accounting', 'finance', 'ke_toan', 'ketoan'],
            'warehouse' => ['warehouse', 'kho', 'warehouse_manager'],
            'hr' => ['hr', 'human_resource', 'nhan_su', 'hanh_chinh_nhan_su'],
            'marketing' => ['marketing', 'marketing_staff', 'marketing_manager', 'marketing_leader'],
        ];

        foreach ($roleMap as $workspace => $aliases) {
            if ($roles->intersect($aliases)->isNotEmpty()) {
                return $workspace;
            }
        }

        return 'personal';
    }

    public function isExecutive(User $user): bool
    {
        $user->loadMissing(['roles', 'department']);

        if ($user->hasAnyRole([
            'admin', 'super_admin', 'management', 'manager', 'director',
            'ban_giam_doc', 'giam_doc', 'ceo',
        ])) {
            return true;
        }

        $department = $this->normalize(
            trim((string) optional($user->department)->code.' '.(string) optional($user->department)->name)
        );

        return Str::contains($department, ['ban_giam_doc', 'giam_doc', 'management', 'director']);
    }

    public function canAccess(User $user, string $workspace): bool
    {
        if (! array_key_exists($workspace, $this->definitions())) {
            return false;
        }

        /*
         * EGO_MULTI_DEPARTMENT_WORKSPACE_ACCESS
         *
         * Một nhân sự có thể thuộc phòng ban kết hợp, ví dụ:
         * - Kế toán & Kho
         * - Marketing & Sales
         * - Kỹ thuật & Kho
         *
         * primary() vẫn dùng để xác định Workspace mặc định,
         * nhưng quyền truy cập phải xét TẤT CẢ phòng ban / role.
         */

        if ($this->isExecutive($user)) {
            return true;
        }

        $user->loadMissing(['department', 'roles', 'position']);

        $department = $this->normalize(
            trim(
                (string) optional($user->department)->code
                .' '.
                (string) optional($user->department)->name
            )
        );

        $departmentMap = [
            'executive' => [
                'ban_giam_doc',
                'giam_doc',
                'management',
                'director',
            ],

            'sales' => [
                'kinh_doanh',
                'sales',
            ],

            'technical' => [
                'ky_thuat',
                'technical',
            ],

            'finance' => [
                'ke_toan',
                'ketoan',
                'accounting',
                'finance',
            ],

            'warehouse' => [
                'warehouse',
                'kho',
            ],

            'hr' => [
                'nhan_su',
                'human_resource',
                'hanh_chinh',
                'hanh_chanh',
            ],

            'marketing' => [
                'marketing',
            ],
        ];

        /*
         * Nếu tên/code phòng ban chứa workspace tương ứng
         * thì được phép truy cập.
         *
         * Ví dụ "Kế toán & Kho":
         * finance   => TRUE
         * warehouse => TRUE
         */
        if (
            isset($departmentMap[$workspace])
            && Str::contains($department, $departmentMap[$workspace])
        ) {
            return true;
        }

        /*
         * Kiểm tra thêm theo role.
         */
        $roles = $user->roles
            ->pluck('name')
            ->map(fn ($name) => $this->normalize((string) $name));

        $roleMap = [
            'sales' => [
                'sales',
                'sale',
                'sales_staff',
                'sales_manager',
                'sales_leader',
                'kinh_doanh',
                'nhan_vien_kinh_doanh',
            ],

            'technical' => [
                'technical',
                'technician',
                'technical_staff',
                'technical_manager',
                'technical_leader',
            ],

            'finance' => [
                'accounting',
                'finance',
                'ke_toan',
                'ketoan',
            ],

            'warehouse' => [
                'warehouse',
                'kho',
                'warehouse_manager',
            ],

            'hr' => [
                'hr',
                'human_resource',
                'nhan_su',
                'hanh_chinh_nhan_su',
            ],

            'marketing' => [
                'marketing',
                'marketing_staff',
                'marketing_manager',
                'marketing_leader',
            ],
        ];

        if (
            isset($roleMap[$workspace])
            && $roles->intersect($roleMap[$workspace])->isNotEmpty()
        ) {
            return true;
        }

        /*
         * Giữ tương thích với logic cũ.
         */
        return $this->primary($user) === $workspace;
    }

    public function cards(User $user): array
    {
        $primary = $this->primary($user);
        $executive = $this->isExecutive($user);

        return collect($this->definitions())
            ->map(function (array $meta, string $key) use ($user, $primary, $executive): array {
                return $meta + [
                    'key' => $key,
                    'allowed' => $this->canAccess($user, $key),
                    'is_primary' => $primary === $key,
                    'all_access' => $executive,
                ];
            })
            ->values()
            ->all();
    }

    public function apps(User $user, string $workspace): array
    {
        $definitions = $this->appDefinitions()[$workspace] ?? [];

        /*
         * EGO_FINANCE_REQUESTS_ALL_WORKSPACES_V1
         *
         * Đề nghị thanh toán + Đề nghị tạm ứng là nghiệp vụ dùng chung.
         * Mọi phòng ban đều nhìn thấy ở Workspace launcher/sidebar.
         * Quyền DUYỆT vẫn do controller/role quyết định; ở đây chỉ mở quyền truy cập ứng dụng.
         */
        $sharedFinanceApps = [
            [
                'label' => 'Đề nghị thanh toán',
                'subtitle' => 'Tạo và theo dõi đề nghị chi phí',
                'icon' => 'bi-receipt-cutoff',
                'route' => 'payment_requests.index',
            ],
            [
                'label' => 'Đề nghị tạm ứng',
                'subtitle' => 'Tạm ứng, hoàn ứng và quyết toán',
                'icon' => 'bi-cash-coin',
                'route' => 'advance_requests.index',
            ],
        ];

        foreach ($sharedFinanceApps as $sharedApp) {
            $routeName = $sharedApp['route'];
            $found = false;

            foreach ($definitions as &$definition) {
                if (($definition['route'] ?? null) !== $routeName) {
                    continue;
                }

                // Chuẩn hóa app dùng chung và không ẩn theo menu_permission của phòng ban.
                $definition = array_merge($definition, $sharedApp);
                unset($definition['menu_permission']);
                $found = true;
                break;
            }
            unset($definition);

            if (! $found && Route::has($routeName)) {
                $definitions[] = $sharedApp;
            }
        }

        /*
         * EGO_COMPANY_DOCUMENTS_ALL_WORKSPACES_V1
         *
         * Hồ sơ công ty là ứng dụng dùng chung toàn công ty.
         * - Tất cả Workspace đều nhìn thấy.
         * - Không phụ thuộc menu.company để HIỂN THỊ launcher.
         * - Nếu workspace đã có route này thì chuẩn hóa tên.
         */
        $hasCompanyDocuments = false;

        foreach ($definitions as &$definition) {
            if (($definition['route'] ?? null) !== 'company-documents.index') {
                continue;
            }

            $definition['label'] = 'Hồ sơ công ty';
            $definition['subtitle'] = 'Tài liệu và hồ sơ công ty';
            $definition['icon'] = 'bi-folder2-open';

            /*
             * Không ẩn app chỉ vì menu.company.
             */
            unset($definition['menu_permission']);

            $hasCompanyDocuments = true;
        }

        unset($definition);

        if (
            ! $hasCompanyDocuments
            && Route::has('company-documents.index')
        ) {
            $definitions[] = [
                'label' => 'Hồ sơ công ty',
                'subtitle' => 'Tài liệu và hồ sơ công ty',
                'icon' => 'bi-folder2-open',
                'route' => 'company-documents.index',
            ];
        }

        $groups = ['department' => [], 'personal' => []];

        foreach ($definitions as $app) {
            $routeName = $app['route'] ?? null;

            if ($routeName === '__workspace_dashboard__') {
                $url = route('ego.workspace.dashboard', ['workspace' => $workspace]);
            } elseif (! $routeName || ! Route::has($routeName)) {
                continue;
            } else {
                $url = route($routeName, $app['params'] ?? []);
            }

            $menuPermission = $app['menu_permission'] ?? null;
            if ($menuPermission && ! $this->pageAccess->canSeeMenu($user, $menuPermission)) {
                continue;
            }

            $group = $app['group'] ?? 'department';
            $groups[$group][] = $app + ['url' => $url];
        }

        return $groups;
    }

    private function appDefinitions(): array
    {
        $personal = [
            ['label' => 'Chấm công', 'subtitle' => 'Chấm công của tôi', 'icon' => 'bi-check2-circle', 'route' => 'hr.attendance.my', 'group' => 'personal'],
            ['label' => 'Đơn nghỉ phép', 'subtitle' => 'Tạo và theo dõi đơn', 'icon' => 'bi-calendar2-x', 'route' => 'hr.leave.index', 'group' => 'personal'],
            ['label' => 'Đăng ký tăng ca', 'subtitle' => 'Tăng ca của tôi', 'icon' => 'bi-clock-history', 'route' => 'hr.overtime.index', 'group' => 'personal'],
        ];

        return [
            'executive' => [
                ['label' => 'Tổng quan điều hành', 'subtitle' => 'Dashboard Ban Giám đốc', 'icon' => 'bi-speedometer2', 'route' => '__workspace_dashboard__'],
                ['label' => 'Booking phòng họp', 'subtitle' => 'Lịch phòng họp', 'icon' => 'bi-calendar2-check', 'route' => 'meeting-room-bookings.index', 'menu_permission' => 'menu.booking'],
                ['label' => 'Đơn hàng', 'subtitle' => 'Theo dõi và phê duyệt đơn bán', 'icon' => 'bi-cart-check-fill', 'route' => 'orders.index', 'menu_permission' => 'menu.orders'],
                ['label' => 'Công việc', 'subtitle' => 'Giao việc và theo dõi', 'icon' => 'bi-briefcase-fill', 'route' => 'tasks.index', 'menu_permission' => 'menu.tasks'],
                ['label' => 'Đề xuất', 'subtitle' => 'Phê duyệt đề xuất', 'icon' => 'bi-lightbulb-fill', 'route' => 'de-xuat.index', 'menu_permission' => 'menu.proposals'],
                ['label' => 'Đề nghị thanh toán', 'subtitle' => 'Theo dõi và phê duyệt', 'icon' => 'bi-receipt-cutoff', 'route' => 'payment_requests.index', 'menu_permission' => 'menu.payment_requests'],
                ['label' => 'Hồ sơ công ty', 'subtitle' => 'Tài liệu nội bộ', 'icon' => 'bi-folder2-open', 'route' => 'company-documents.index', 'menu_permission' => 'menu.company'],
            ],
            'sales' => array_merge([
                ['label' => 'Tổng quan kinh doanh', 'subtitle' => 'Dashboard Kinh doanh', 'icon' => 'bi-speedometer2', 'route' => '__workspace_dashboard__'],
                ['label' => 'Khách hàng', 'subtitle' => 'CRM khách hàng', 'icon' => 'bi-people-fill', 'route' => 'customers.index', 'menu_permission' => 'menu.customers'],
                ['label' => 'Đơn hàng', 'subtitle' => 'Quản lý đơn bán', 'icon' => 'bi-cart-check-fill', 'route' => 'orders.index', 'menu_permission' => 'menu.orders'],
                ['label' => 'Công trình', 'subtitle' => 'Theo dõi công trình', 'icon' => 'bi-buildings', 'route' => 'sites.index', 'menu_permission' => 'menu.sites'],
                ['label' => 'Báo giá', 'subtitle' => 'Báo giá bán hàng', 'icon' => 'bi-file-earmark-text', 'route' => 'sales-quotations.index', 'menu_permission' => 'menu.sales'],
                ['label' => 'KPI kinh doanh', 'subtitle' => 'KPI và hiệu suất', 'icon' => 'bi-bar-chart-fill', 'route' => 'sales.kpi.index', 'menu_permission' => 'menu.sales'],
                ['label' => 'Báo cáo công việc', 'subtitle' => 'Báo cáo Sales', 'icon' => 'bi-clipboard-data-fill', 'route' => 'sales.work-reports.index', 'menu_permission' => 'menu.sales'],
                ['label' => 'Công việc', 'subtitle' => 'Việc của phòng và của tôi', 'icon' => 'bi-briefcase-fill', 'route' => 'tasks.index', 'menu_permission' => 'menu.tasks'],
                ['label' => 'Đề xuất', 'subtitle' => 'Phiếu đề xuất nội bộ', 'icon' => 'bi-lightbulb-fill', 'route' => 'de-xuat.index', 'menu_permission' => 'menu.proposals'],
                ['label' => 'Đề nghị thanh toán', 'subtitle' => 'Tạo và theo dõi phiếu', 'icon' => 'bi-receipt-cutoff', 'route' => 'payment_requests.index', 'menu_permission' => 'menu.payment_requests'],
            ], $personal),
            'technical' => array_merge([
                ['label' => 'Tổng quan kỹ thuật', 'subtitle' => 'Dashboard Kỹ thuật', 'icon' => 'bi-speedometer2', 'route' => '__workspace_dashboard__'],
                ['label' => 'Công trình', 'subtitle' => 'Thi công và điều phối', 'icon' => 'bi-buildings', 'route' => 'sites.index', 'menu_permission' => 'menu.sites'],
                ['label' => 'Bảo trì / Bảo hành', 'subtitle' => 'Lịch và hồ sơ bảo trì', 'icon' => 'bi-tools', 'route' => 'ky-thuat.maintenance.index', 'menu_permission' => 'menu.technical'],
                ['label' => 'Sản phẩm', 'subtitle' => 'Tra cứu sản phẩm / vật tư', 'icon' => 'bi-box-seam', 'route' => 'products.index', 'menu_permission' => 'menu.products'],
                ['label' => 'Công việc', 'subtitle' => 'Việc kỹ thuật', 'icon' => 'bi-briefcase-fill', 'route' => 'tasks.index', 'menu_permission' => 'menu.tasks'],
                ['label' => 'Đề xuất', 'subtitle' => 'Đề xuất nội bộ', 'icon' => 'bi-lightbulb-fill', 'route' => 'de-xuat.index', 'menu_permission' => 'menu.proposals'],
                ['label' => 'Đề nghị thanh toán', 'subtitle' => 'Chi phí công trình', 'icon' => 'bi-receipt-cutoff', 'route' => 'payment_requests.index', 'menu_permission' => 'menu.payment_requests'],
            ], $personal),
            'finance' => array_merge([
                [
                    'label' => 'Tổng quan',
                    'subtitle' => 'Tổng quan Tài chính Kế toán',
                    'icon' => 'bi-speedometer2',
                    'route' => '__workspace_dashboard__',
                ],
                [
                    'label' => '1. BẢNG TIỀN LƯƠNG',
                    'subtitle' => 'Bảng tiền lương',
                    'icon' => 'bi-cash-stack',
                    'route' => 'finance.salary',
                    'menu_permission' => 'menu.finance',
                ],
                [
                    'label' => '2. BÁO CÁO TÀI CHÍNH/THUẾ',
                    'subtitle' => 'Báo cáo tài chính và thuế',
                    'icon' => 'bi-file-earmark-bar-graph',
                    'route' => 'finance.reports',
                    'menu_permission' => 'menu.finance',
                ],
                [
                    'label' => '3. NỢ PHẢI THU',
                    'subtitle' => 'Theo dõi các khoản phải thu',
                    'icon' => 'bi-arrow-down-left-circle-fill',
                    'route' => 'finance.customer-debts.index',
                    'menu_permission' => 'menu.finance',
                ],
                [
                    'label' => '4. NỢ PHẢI TRẢ',
                    'subtitle' => 'Theo dõi các khoản phải trả',
                    'icon' => 'bi-arrow-up-right-circle-fill',
                    'route' => 'finance.supplier-debts.index',
                    'menu_permission' => 'menu.finance',
                ],
                [
                    'label' => 'Đề nghị thanh toán',
                    'subtitle' => 'Kiểm tra và phê duyệt',
                    'icon' => 'bi-receipt-cutoff',
                    'route' => 'payment_requests.index',
                    'menu_permission' => 'menu.payment_requests',
                ],
                [
                    'label' => 'Công việc',
                    'subtitle' => 'Việc kế toán',
                    'icon' => 'bi-briefcase-fill',
                    'route' => 'tasks.index',
                    'menu_permission' => 'menu.tasks',
                ],
            ], $personal),
            'warehouse' => array_merge([
                ['label' => 'Tổng quan kho', 'subtitle' => 'Dashboard Kho', 'icon' => 'bi-speedometer2', 'route' => '__workspace_dashboard__'],
                ['label' => 'Sản phẩm', 'subtitle' => 'Danh sách sản phẩm', 'icon' => 'bi-box-seam-fill', 'route' => 'products.index', 'menu_permission' => 'menu.products'],
                ['label' => 'Nhập kho', 'subtitle' => 'Nhập sản phẩm / vật tư', 'icon' => 'bi-box-arrow-in-down', 'route' => 'products.input', 'menu_permission' => 'menu.products'],
                ['label' => 'Xuất kho', 'subtitle' => 'Xuất sản phẩm / vật tư', 'icon' => 'bi-box-arrow-up', 'route' => 'products.output', 'menu_permission' => 'menu.products'],
                ['label' => 'Vật tư công trình', 'subtitle' => 'Thi công, bảo trì / bảo hành', 'icon' => 'bi-box-seam', 'route' => 'material-requests.index', 'menu_permission' => 'menu.products'],
                ['label' => 'Phiếu nhập hàng', 'subtitle' => 'Goods receipt', 'icon' => 'bi-clipboard2-check-fill', 'route' => 'product-goods-receipts.index', 'menu_permission' => 'menu.products'],
                ['label' => 'Công nợ nhà cung cấp', 'subtitle' => 'Theo dõi công nợ NCC', 'icon' => 'bi-receipt-cutoff', 'route' => 'finance.supplier-debts.index'],
                ['label' => 'Công nợ phải thu', 'subtitle' => 'Theo dõi công nợ khách hàng', 'icon' => 'bi-cash-coin', 'route' => 'finance.customer-debts.index'],
                ['label' => 'Kho hàng', 'subtitle' => 'Danh sách kho và tồn', 'icon' => 'bi-house-gear-fill', 'route' => 'warehouses.index', 'menu_permission' => 'menu.products'],
                ['label' => 'Công trình', 'subtitle' => 'Chuẩn bị / xuất vật tư', 'icon' => 'bi-buildings', 'route' => 'sites.index', 'menu_permission' => 'menu.sites'],
                ['label' => 'Công việc', 'subtitle' => 'Việc của Kho', 'icon' => 'bi-briefcase-fill', 'route' => 'tasks.index', 'menu_permission' => 'menu.tasks'],
            ], $personal),
            'hr' => [
                ['label' => 'Tổng quan nhân sự', 'subtitle' => 'Dashboard Nhân sự', 'icon' => 'bi-speedometer2', 'route' => '__workspace_dashboard__'],
                ['label' => 'Danh sách nhân viên', 'subtitle' => 'Nhân sự công ty', 'icon' => 'bi-people-fill', 'route' => 'hr.employees.index', 'menu_permission' => 'menu.hr'],
                ['label' => 'Hồ sơ nhân viên', 'subtitle' => 'Hồ sơ và tài liệu', 'icon' => 'bi-person-vcard-fill', 'route' => 'hr.records.index', 'menu_permission' => 'menu.hr'],
                ['label' => 'Chấm công', 'subtitle' => 'Bảng chấm công', 'icon' => 'bi-check2-circle', 'route' => 'hr.attendance.index', 'menu_permission' => 'menu.hr'],
                ['label' => 'Tăng ca', 'subtitle' => 'Đăng ký và duyệt tăng ca', 'icon' => 'bi-clock-history', 'route' => 'hr.overtime.index', 'menu_permission' => 'menu.hr'],
                ['label' => 'Đơn nghỉ phép', 'subtitle' => 'Nghỉ phép và duyệt đơn', 'icon' => 'bi-calendar2-x', 'route' => 'hr.leave.index', 'menu_permission' => 'menu.hr'],
                ['label' => 'Hành chánh', 'subtitle' => 'Chi phí, tài sản, nhà cung cấp', 'icon' => 'bi-building-gear', 'route' => 'hr.operations.index', 'menu_permission' => 'menu.hr'],
                ['label' => 'Văn phòng phẩm', 'subtitle' => 'Nhập / xuất / tồn VPP', 'icon' => 'bi-box2-heart-fill', 'route' => 'hr.office-supply-process.index', 'menu_permission' => 'menu.hr'],
                ['label' => 'Tuyển dụng', 'subtitle' => 'Ứng viên và tuyển dụng', 'icon' => 'bi-person-plus-fill', 'route' => 'hr.recruitment.index', 'menu_permission' => 'menu.hr'],
                ['label' => 'Văn thư lưu trữ', 'subtitle' => 'Giao nhận hồ sơ', 'icon' => 'bi-folder-symlink-fill', 'route' => 'hr.document-handovers.index', 'menu_permission' => 'menu.hr'],
                ['label' => 'Tài liệu nội bộ', 'subtitle' => 'Hồ sơ công ty', 'icon' => 'bi-folder2-open', 'route' => 'company-documents.index', 'menu_permission' => 'menu.company'],
            ],
            'marketing' => array_merge([
                ['label' => 'Tổng quan Marketing', 'subtitle' => 'Dashboard Marketing', 'icon' => 'bi-speedometer2', 'route' => 'marketing.dashboard', 'menu_permission' => 'menu.marketing'],
                ['label' => 'Kế hoạch Marketing', 'subtitle' => 'Kế hoạch và ngân sách', 'icon' => 'bi-calendar2-week-fill', 'route' => 'marketing.plan.overview', 'menu_permission' => 'menu.marketing'],
                ['label' => 'Tiến độ', 'subtitle' => 'Theo dõi tiến độ', 'icon' => 'bi-kanban-fill', 'route' => 'marketing.progress.index', 'menu_permission' => 'menu.marketing'],
                ['label' => 'Lead Marketing', 'subtitle' => 'Nguồn lead và upload', 'icon' => 'bi-person-lines-fill', 'route' => 'marketing.leads.index', 'menu_permission' => 'menu.marketing'],
                ['label' => 'Báo cáo Marketing', 'subtitle' => 'Tổng quan hiệu quả', 'icon' => 'bi-bar-chart-line-fill', 'route' => 'marketing.report.overview', 'menu_permission' => 'menu.marketing'],
                ['label' => 'Lịch nội dung', 'subtitle' => 'Content calendar', 'icon' => 'bi-calendar3', 'route' => 'marketing.reports.content-calendar', 'menu_permission' => 'menu.marketing'],
                ['label' => 'Công việc', 'subtitle' => 'Việc Marketing', 'icon' => 'bi-briefcase-fill', 'route' => 'tasks.index', 'menu_permission' => 'menu.tasks'],
                ['label' => 'Đề xuất', 'subtitle' => 'Đề xuất nội bộ', 'icon' => 'bi-lightbulb-fill', 'route' => 'de-xuat.index', 'menu_permission' => 'menu.proposals'],
                ['label' => 'Đề nghị thanh toán', 'subtitle' => 'Chi phí Marketing', 'icon' => 'bi-receipt-cutoff', 'route' => 'payment_requests.index', 'menu_permission' => 'menu.payment_requests'],
            ], $personal),
        ];
    }

    private function normalize(string $value): string
    {
        return (string) Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }
}
