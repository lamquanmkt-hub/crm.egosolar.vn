<?php

declare(strict_types=1);

namespace App\Services\System;

use App\Services\Hr\LeaveApprovalAccessService;
use App\Services\Hr\LeaveDashboardAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Trạng thái "menu nào đang mở / đang active / thấy được" của sidebar.
 *
 * Trước 2026-09-07 mười ba khối `@php` trong `partials/sidebar.blade.php` tự tính các cờ
 * này bằng `request()->routeIs(...)`, `auth()->user()`, `Route::has(...)` và gọi thẳng
 * service nghỉ phép. View chỉ nên NHẬN giá trị, nên toàn bộ phép tính dời về đây và bơm
 * qua View Composer (`ViewComposerServiceProvider`), cạnh `SidebarStatusService` và
 * `SidebarMenuVisibilityService`.
 *
 * Tên biến trả về giữ NGUYÊN tên cũ trong view để markup không phải đổi một chữ; đã kiểm
 * bằng so HTML render trước/sau trên 42 trang.
 *
 * Ba cờ đầu vào (`egoCanCustomerMenu`, `egoCanProductMenuByRole`, `egoSidebarIsExecutive`)
 * là kết quả của SidebarMenuVisibilityService — truyền vào chứ không tính lại.
 */
final class SidebarRouteStateService
{
    public function __construct(
        private readonly Request $request,
        private readonly LeaveApprovalAccessService $leaveAccess,
        private readonly LeaveDashboardAlertService $leaveAlerts,
    ) {}

    /**
     * @param  array<string, mixed>  $visibility  Kết quả SidebarMenuVisibilityService::viewData()
     * @return array<string, mixed>
     */
    public function viewData(array $visibility): array
    {
        $user = $this->request->user();
        $routeIs = fn (string ...$patterns): bool => $this->request->routeIs(...$patterns);

        // Tổng quan = Dashboard của Workspace đang sử dụng (EGO_WORKSPACE_OVERVIEW_LINK_V1).
        $overviewWorkspace = session('active_workspace');
        if ($overviewWorkspace && Route::has('ego.workspace.dashboard')) {
            $overviewUrl = route('ego.workspace.dashboard', ['workspace' => $overviewWorkspace]);
        } elseif (Route::has('ego.workspace.index')) {
            $overviewUrl = route('ego.workspace.index');
        } else {
            $overviewUrl = url('/workspace');
        }

        // Menu khách hàng: nhận diện "kho" theo vai trò hoặc theo chữ trong hồ sơ người dùng.
        $customerRoleText = strtolower(implode(' ', array_filter([
            $user->role ?? null,
            $user->role_name ?? null,
            $user->department ?? null,
            $user->position ?? null,
            $user->type ?? null,
        ])));
        $customerIsKho = $user && (
            (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kho', 'warehouse', 'admin']))
            || (method_exists($user, 'hasRole') && (
                $user->hasRole('kho') || $user->hasRole('warehouse') || $user->hasRole('admin')
            ))
            || str_contains($customerRoleText, 'kho')
            || str_contains($customerRoleText, 'warehouse')
            || str_contains($customerRoleText, 'admin')
        );

        // Nghỉ phép: quyền xem chấm công toàn công ty + số đơn chờ duyệt.
        $leaveSnapshot = $user !== null ? $this->leaveAlerts->snapshot($user) : [];

        $baoCaoTaiChinhActive = $routeIs('finance.reports') || $routeIs('finance.settlement')
            || $routeIs('finance.audit') || $routeIs('finance.accounts.*');
        $noPhaiThuActive = $routeIs('finance.project-receivables.*') || $routeIs('finance.customer-debts.*');
        $noPhaiTraActive = $routeIs('finance.supplier-debts.*')
            || ($routeIs('finance.ledger.*') && $this->request->route('direction') === 'payable');

        return [
            'egoOverviewWorkspace' => $overviewWorkspace,
            'egoOverviewUrl' => $overviewUrl,
            'egoOverviewActive' => $routeIs('ego.workspace.dashboard'),

            'egoCustomerMenuUser' => $user,
            'egoCustomerMenuRoleText' => $customerRoleText,
            'egoCustomerMenuIsKho' => $customerIsKho,
            'canCustomerMenu' => $visibility['egoCanCustomerMenu'] ?? false,

            'egoProjectMenuOpen' => ($routeIs('projects-unified.*') && ! $routeIs('projects-unified.maintenance.*'))
                || $routeIs('material-requests.*'),
            'egoMyProjects' => $routeIs('projects-unified.index')
                && (int) $this->request->input('engineer_id') === (int) $this->request->user()?->getAuthIdentifier(),

            'egoTechnicalMenuOpen' => $routeIs(
                'ky-thuat.tong-quan', 'ky-thuat.ke-hoach*', 'ky-thuat.bao-cao*', 'ky-thuat.kpis.*', 'ky-thuat.luong.*'
            ),
            'egoPaymentMenuOpen' => $routeIs('payment_requests.*', 'advance_requests.*', 'settlement_requests.*'),

            'hasLeadIndex' => Route::has('marketing.leads.index'),
            'hasContentCalendar' => Route::has('marketing.reports.content-calendar'),

            'u' => $user,
            'canProductMenu' => $user && ($visibility['egoCanProductMenuByRole'] ?? false),
            'canSeeInputProducts' => $user && (
                ($visibility['egoSidebarIsExecutive'] ?? false)
                || $user->hasAnyRole(['warehouse', 'kho', 'accounting'])
            ),

            'egoHanhChanhActive' => $routeIs('hr.operations.*') || $routeIs('hr.office-supply-process.*')
                || $routeIs('hr.document-handovers.*'),

            'egoLeaveAccess' => $this->leaveAccess,
            'egoCanViewCompanyAttendance' => $user !== null && $this->leaveAccess->canManageAll($user),
            'egoLeaveSidebarSnapshot' => $leaveSnapshot,
            'egoPendingLeaveApprovalCount' => (int) ($leaveSnapshot['pending_approval_count'] ?? 0),
            'egoCanReviewLeave' => (bool) ($leaveSnapshot['can_review'] ?? false),

            'egoNhanSuExcelActive' => $routeIs('hr.employees.*') || $routeIs('hr.records.*')
                || $routeIs('hr.recruitment.interviews') || $routeIs('hr.recruitment.offers')
                || $routeIs('hr.attendance.*') || $routeIs('hr.overtime.*') || $routeIs('hr.leave.*'),

            'egoBaoCaoTaiChinhActive' => $baoCaoTaiChinhActive,
            'egoNoPhaiThuActive' => $noPhaiThuActive,
            'egoNoPhaiTraActive' => $noPhaiTraActive,
            'egoTaiChinhKeToanActive' => $routeIs('finance.salary*') || $baoCaoTaiChinhActive
                || $noPhaiThuActive || $noPhaiTraActive,

            'egoSettingsRouteActive' => $routeIs('admin.settings.*') || $routeIs('payment-methods.*')
                || $this->request->is('companies*'),
        ];
    }
}
