<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Models\User;
use App\Services\RolePermission\PageAccessService;
use Illuminate\Support\Facades\Auth;

/**
 * Quyết định menu nào hiện trên sidebar.
 *
 * ## Vì sao tách khỏi Blade
 * Toàn bộ phần này trước nằm trong `partials/sidebar.blade.php` dưới dạng 225
 * dòng `@php` (dòng 116–340): view tự gọi service phân quyền, tự giữ ma trận
 * workspace, tự tính lại cờ. Sidebar render ở MỌI trang mà logic thì không test
 * được, và ma trận cấu hình nằm lẫn trong HTML nên sửa quyền phải mò trong view.
 *
 * ## Hai tầng quyết định, giữ nguyên thứ tự cũ
 * 1. Quyền theo vai trò — `PageAccessService::canSeeMenu()`.
 * 2. Nếu đang ở một Workspace phòng ban (không phải `executive`) thì **ghi đè**
 *    tầng 1 bằng ma trận bên dưới. Admin/BGĐ vào được mọi workspace, nhưng
 *    sidebar luôn theo workspace ĐANG CHỌN — nên admin ở workspace "hr" chỉ thấy
 *    menu của hr.
 */
final class SidebarMenuVisibilityService
{
    /**
     * Menu được phép hiện ứng với từng Workspace phòng ban.
     *
     * `executive` cố ý có mặt nhưng không kích hoạt chế độ ghi đè (xem
     * `$workspaceMode`): Ban giám đốc giữ nguyên menu đầy đủ theo vai trò.
     *
     * @var array<string, list<string>>
     */
    public const WORKSPACE_MENU_MATRIX = [
        'executive' => [
            'menu.dashboard', 'menu.booking', 'menu.tasks',
            'menu.proposals', 'menu.payment_requests', 'menu.company',
        ],
        'sales' => [
            'menu.dashboard', 'menu.booking', 'menu.customers', 'menu.orders',
            'menu.sites', 'menu.sales', 'menu.tasks', 'menu.proposals',
            'menu.payment_requests',
        ],
        'technical' => [
            'menu.dashboard', 'menu.sites', 'menu.technical', 'menu.products',
            'menu.tasks', 'menu.proposals', 'menu.payment_requests',
        ],
        'finance' => [
            'menu.dashboard', 'menu.finance', 'menu.payment_requests',
            'menu.tasks', 'menu.company',
        ],
        'warehouse' => [
            'menu.dashboard', 'menu.products', 'menu.sites', 'menu.tasks',
            'menu.payment_requests', 'menu.company',
        ],
        'hr' => [
            'menu.dashboard', 'menu.hr', 'menu.tasks',
            'menu.payment_requests', 'menu.company',
        ],
        'marketing' => [
            'menu.dashboard', 'menu.marketing', 'menu.tasks',
            'menu.proposals', 'menu.payment_requests', 'menu.company',
        ],
    ];

    /** Vai trò được tra cứu bảo hành khi KHÔNG ở chế độ workspace. */
    private const WARRANTY_LOOKUP_ROLES = [
        'sales', 'sales_manager', 'accounting', 'warehouse',
        'technical', 'technical_manager',
    ];

    /** Cờ menu, theo đúng tên biến mà sidebar đang dùng. */
    private const MENU_FLAGS = [
        'egoCanDashboardMenu' => 'menu.dashboard',
        'egoCanBookingMenu' => 'menu.booking',
        'egoCanCustomerMenu' => 'menu.customers',
        'egoCanOrdersMenu' => 'menu.orders',
        'egoCanProjectTestMenu' => 'menu.project_test',
        'egoCanConstructionMenu' => 'menu.sites',
        'egoCanProposalsMenu' => 'menu.proposals',
        'egoCanTechnicalMenu' => 'menu.technical',
        'egoCanSalesMenu' => 'menu.sales',
        'egoCanMarketingMenu' => 'menu.marketing',
        'egoCanTasksMenu' => 'menu.tasks',
        'egoCanProductMenuByRole' => 'menu.products',
        'egoCanFinanceMenu' => 'menu.finance',
        'egoCanCompanyMenu' => 'menu.company',
        'egoCanHrMenu' => 'menu.hr',
        'egoCanSettingsMenu' => 'menu.settings',
    ];

    public function __construct(private readonly PageAccessService $access) {}

    /**
     * Biến bơm thẳng vào `partials/sidebar.blade.php`.
     *
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $user = Auth::user();

        $flags = $this->baseFlags($user);
        $workspace = (string) (session('active_workspace') ?? '');
        $allowed = self::WORKSPACE_MENU_MATRIX[$workspace] ?? [];

        // `executive` nằm trong ma trận nhưng KHÔNG bật chế độ ghi đè — Ban giám
        // đốc giữ menu đầy đủ theo vai trò.
        $workspaceMode = $workspace !== '' && $allowed !== [] && $workspace !== 'executive';

        if ($workspaceMode) {
            // Ghi đè CÓ CHỌN LỌC: chỉ các cờ menu. Ba cờ vai trò
            // (egoSidebarIsAdmin/IsManagement/IsExecutive) giữ nguyên giá trị
            // theo vai trò thật — bản cũ cũng không đụng tới chúng ở nhánh này.
            $flags = $this->workspaceFlags($allowed, $flags['egoSidebarIsExecutive']) + $flags;
        }

        return $flags + [
            'egoActiveWorkspace' => session('active_workspace'),
            'egoActiveWorkspaceLabel' => session('active_workspace_label'),
            'egoWorkspaceAllowedMenus' => $allowed,
            'egoWorkspaceMode' => $workspaceMode,
        ];
    }

    /**
     * Tầng 1: quyền theo vai trò.
     *
     * @return array<string, bool>
     */
    private function baseFlags(?User $user): array
    {
        $canSee = fn (string $permission): bool => $user !== null
            && $this->access->canSeeMenu($user, $permission);

        $isAdmin = $user !== null && $this->access->isAdmin($user);
        $isManagement = $user !== null && $user->hasRole('management');
        $isExecutive = $isAdmin || $isManagement;

        $flags = [];

        foreach (self::MENU_FLAGS as $bien => $quyen) {
            $flags[$bien] = $canSee($quyen);
        }

        // Đề nghị thanh toán mở cho mọi workspace (EGO_FINANCE_REQUESTS_ALL_WORKSPACES_V1).
        $flags['egoCanPaymentRequestsMenu'] = true;

        $flags['egoSidebarIsAdmin'] = $isAdmin;
        $flags['egoSidebarIsManagement'] = $isManagement;
        $flags['egoSidebarIsExecutive'] = $isExecutive;

        $flags['egoCanSiteWorkspaceMenu'] = $flags['egoCanConstructionMenu'];

        // Giữ nguyên phép rút gọn của bản cũ: $egoCanOrdersMenu sai thì không
        // chạm tới $user, nên user null cũng không vỡ.
        $flags['egoCanOrderSettingsMenu'] = $flags['egoCanOrdersMenu']
            && ($isExecutive || ($user !== null && $user->hasRole('accounting')));

        $flags['egoCanWarrantyLookupMenu'] = $user !== null
            && ($isExecutive || $user->hasAnyRole(self::WARRANTY_LOOKUP_ROLES));

        return $flags;
    }

    /**
     * Tầng 2: ở Workspace phòng ban thì cờ MENU lấy hẳn từ ma trận, bỏ qua tầng 1.
     * Chỉ trả về những khoá bị ghi đè; phần còn lại do tầng 1 giữ.
     *
     * @param  list<string>  $allowed
     * @return array<string, bool>
     */
    private function workspaceFlags(array $allowed, bool $isExecutive): array
    {
        $has = static fn (string $permission): bool => in_array($permission, $allowed, true);

        $flags = [];

        foreach (self::MENU_FLAGS as $bien => $quyen) {
            $flags[$bien] = $has($quyen);
        }

        $flags['egoCanPaymentRequestsMenu'] = true;

        // Cài đặt không thuộc Workspace phòng ban: muốn chỉnh thì quay về
        // workspace điều hành.
        $flags['egoCanSettingsMenu'] = false;

        $flags['egoCanSiteWorkspaceMenu'] = $flags['egoCanConstructionMenu'];
        $flags['egoCanOrderSettingsMenu'] = $flags['egoCanOrdersMenu'] && $isExecutive;
        $flags['egoCanWarrantyLookupMenu'] = $flags['egoCanConstructionMenu']
            || $flags['egoCanTechnicalMenu']
            || $flags['egoCanProductMenuByRole'];

        return $flags;
    }
}
