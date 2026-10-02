<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CRM\CustomerProfileOrderBoardService;
use App\Services\Sales\SalesManagerDirectory;
use App\Services\System\SidebarMenuVisibilityService;
use App\Services\System\SidebarRouteStateService;
use App\Services\System\SidebarStatusService;
use App\Services\System\SystemBrandingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Đăng ký view composer — đưa dữ liệu dùng chung vào Blade từ MỘT nguồn.
 *
 * Nguyên tắc: view không tự truy vấn DB. Trước đây 7 file Blade cùng chép một
 * khối `@php ... User::query()->get() ...` để dựng danh sách trưởng phòng
 * sales; nay lấy qua service và bơm vào bằng composer.
 */
final class ViewComposerServiceProvider extends ServiceProvider
{
    /**
     * View cần biến $egoSalesManagerOptions (dropdown chọn sales phụ trách).
     *
     * @var list<string>
     */
    private const SALES_MANAGER_VIEWS = [
        'partials.sidebar',
        'sales.commissions.index',
        'sales.work_reports.*',
    ];

    /**
     * View cần danh sách công ty để chọn (dropdown "Công ty").
     *
     * @var list<string>
     */
    private const SITE_COMPANY_VIEWS = [
        'sites.index',
        'sites.create',
        'sites.edit',
    ];

    public function register(): void
    {
        $this->app->scoped(SalesManagerDirectory::class);
        $this->app->scoped(SidebarStatusService::class);
        $this->app->scoped(SidebarMenuVisibilityService::class);
        $this->app->scoped(CustomerProfileOrderBoardService::class);
        // scoped: partial branding nằm trong layout nên có thể được resolve nhiều lần
        // trong một request; chỉ cần đọc cache/DB một lần.
        $this->app->scoped(SystemBrandingService::class);
    }

    public function boot(): void
    {
        // Partial branding nằm trong CẢ HAI layout (app + guest) nên chạy ở mọi request;
        // trước đây nó tự cache + truy vấn + làm sạch trong một khối @php 62 dòng.
        View::composer(
            'partials.system-branding-runtime',
            function ($view): void {
                $view->with('branding', $this->app->make(SystemBrandingService::class)->theme());
            }
        );

        View::composer(
            self::SALES_MANAGER_VIEWS,
            function ($view): void {
                $view->with(
                    'egoSalesManagerOptions',
                    $this->app->make(SalesManagerDirectory::class)->options(),
                );
            }
        );

        View::composer(
            'sites.show',
            function ($view): void {
                // Bản cũ trong view: data_get($site, 'id', request()->route('id')).
                // Giữ nguyên cả nhánh dự phòng lấy từ route.
                $siteId = (int) data_get(
                    $view->getData()['site'] ?? null,
                    'id',
                    request()->route('id') ?? 0
                );

                $view->with(
                    $this->app->make(\App\Services\Projects\SitePaymentEditorService::class)
                        ->viewData($siteId)
                );
            }
        );

        View::composer(
            self::SITE_COMPANY_VIEWS,
            function ($view): void {
                $options = \App\Support\EgoDefaultCompany::activeOptions();

                $view->with([
                    'egoSiteCompanyOptions' => $options,
                    'egoSiteCompanyMap' => \App\Support\EgoDefaultCompany::optionLabels($options),
                ]);
            }
        );

        View::composer(
            'ego_order_documents.profile_box',
            function ($view): void {
                $view->with(
                    $this->app->make(CustomerProfileOrderBoardService::class)
                        ->viewData($view->getData()['profile'] ?? null)
                );
            }
        );

        View::composer(
            'partials.sidebar',
            function ($view): void {
                $view->with($this->app->make(SidebarStatusService::class)->viewData());
                $visibility = $this->app->make(SidebarMenuVisibilityService::class)->viewData();
                $view->with($visibility);
                // Cờ "menu nào đang mở/active" — trước đây là 13 khối @php trong chính view.
                $view->with($this->app->make(SidebarRouteStateService::class)->viewData($visibility));
            }
        );
    }
}
