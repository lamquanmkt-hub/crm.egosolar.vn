<?php

namespace App\Providers;

use App\Http\Middleware\EgoCompanyContextMiddleware;
use App\Models\Tasks\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use App\Services\Debug\SchemaInspector;
use App\Services\Debug\TableFilter;
use App\Services\Debug\TableMetadataReader;
use App\Services\Push\PushSubscriptionGateway;
use App\Services\Push\UserModelPushSubscriptionGateway;
use App\Services\Workspace\DepartmentWorkspaceService;
use App\Support\ProbeFailureLog;
use App\Support\SchemaCache;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TableFilter::class);
        $this->app->singleton(TableMetadataReader::class);
        $this->app->singleton(SchemaInspector::class);
        $this->app->bind(PushSubscriptionGateway::class, UserModelPushSubscriptionGateway::class);
    }

    public function boot(): void
    {
        try {
            app('router')->pushMiddlewareToGroup('web', EgoCompanyContextMiddleware::class);
        } catch (\Throwable $e) {
            ProbeFailureLog::warn('AppServiceProvider::boot', $e);

            // Ignore middleware registration issues during artisan optimize/package discovery.
        }

        // Map policy cho Task
        Gate::policy(Task::class, TaskPolicy::class);

        /*
         * Quyền vào workspace của một phòng ban.
         *
         * Luật vẫn nằm nguyên trong DepartmentWorkspaceService::canAccess() — Gate
         * này chỉ đưa nó ra đúng cửa authorization của framework, để chỗ kiểm quyền
         * nhìn thấy được từ bên ngoài controller (RouteAuthorizationContractTest
         * canh đúng việc đó) thay vì lẩn trong một lời gọi service.
         */
        Gate::define(
            'access-workspace',
            static fn (User $user, string $workspace): bool => app(DepartmentWorkspaceService::class)->canAccess($user, $workspace),
        );
        Paginator::useBootstrap();

        // Schema đổi sau migration -> bỏ cache "bảng/cột có tồn tại không",
        // cần cho worker chạy dài và cho test tự tạo bảng.
        Event::listen(MigrationsEnded::class, static fn () => SchemaCache::flush());

        // Một số controller/service còn tạo bảng lúc chạy (ensureSchema).
        // Bắt trực tiếp câu lệnh DDL để cache schema không bao giờ lạc hậu
        // ngay trong cùng một request.
        DB::listen(static function (QueryExecuted $query): void {
            if (SchemaCache::isDdl($query->sql)) {
                SchemaCache::flush();
            }
        });
    }
}
