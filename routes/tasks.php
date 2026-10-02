<?php

/*
|---------------------------------------------------------------------------
| Route: Công việc nội bộ
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

use App\Http\Controllers\CRM\OrderController;
use App\Http\Controllers\Marketing\ContentCalendarController;
use App\Http\Controllers\Marketing\KpiPayrollController;
use App\Http\Controllers\Marketing\MarketingBudgetController;
use App\Http\Controllers\Marketing\MarketingCampaignController;
use App\Http\Controllers\Marketing\MarketingDashboardController;
use App\Http\Controllers\Marketing\MarketingLeadController;
use App\Http\Controllers\Marketing\MarketingMetricController;
use App\Http\Controllers\Marketing\MarketingProgressController;
use App\Http\Controllers\Marketing\MarketingReportController;
use App\Http\Controllers\Marketing\WeeklyTaskController;
use App\Http\Controllers\System\ChatController;
use App\Http\Controllers\Tasks\TaskController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chat + Tasks
|--------------------------------------------------------------------------
*/

Route::middleware('auth')
    ->prefix('chat')
    ->group(function () {
        // Orders - Shipping
        Route::controller(OrderController::class)->group(function () {
            Route::post('/orders/{id}/shipping-info', 'updateShippingInfo')->name('orders.shippingInfo');
            Route::post('/orders/{id}/mark-shipped', 'markShipped')->name('orders.markShipped');
            Route::get('/orders/{id}/ship-serials', [OrderController::class, 'getShipSerials'])
                ->name('orders.ship-serials');

        });

        // Tasks
        Route::controller(TaskController::class)->group(function () {
            Route::get('/tasks/my', 'my')->name('tasks.my');
            Route::get('/tasks', 'index')->name('tasks.index');
            Route::get('/tasks/create', 'create')->name('tasks.create');
            Route::post('/tasks', 'store')->name('tasks.store');
            Route::match(['get', 'post'], '/tasks/{task}/submit', 'submitResult')->name('tasks.submit');
            Route::post('/tasks/{task}/attachments/{attachment}/replace', 'replaceAttachment')->whereNumber('attachment')->name('tasks.attachments.replace');
            Route::match(['post', 'delete'], '/tasks/{task}/attachments/{attachment}', 'destroyAttachment')->whereNumber('attachment')->name('tasks.attachments.destroy');
            Route::post('/tasks/{task}/return-revision', 'returnRevision')->name('tasks.return-revision');
            Route::post('/tasks/{task}/approve', 'approve')->name('tasks.approve');
            Route::patch('/tasks/{task}/status', 'updateStatus')->name('tasks.status');
            Route::get('/tasks/{task}/edit', 'edit')->name('tasks.edit');
            Route::put('/tasks/{task}', 'update')->name('tasks.update');
            Route::delete('/tasks/{task}', 'destroy')->name('tasks.destroy');
            Route::get('/tasks/{task}', 'show')->name('tasks.show');
        });

        // Chat
        Route::controller(ChatController::class)->group(function () {
            Route::get('/conversations/json', 'conversationsJson')->name('chat.conversations.json');
            Route::post('/{conversation}/read/json', 'markReadJson')->name('chat.read.json');
            Route::get('/', 'inbox')->name('chat.inbox');
            Route::get('/users', 'users')->name('chat.users');
            Route::post('/direct', 'direct')->name('chat.direct');
            Route::get('/departments/json', 'departmentsJson')->name('chat.departments.json');
            Route::post('/department/json', 'departmentJson')->name('chat.department.json');
            Route::post('/group/json', 'groupJson')->name('chat.group.json');
            Route::get('/messages/{message}/download', 'downloadAttachment')->name('chat.messages.download');
            Route::get('/users/json', 'usersJson')->name('chat.users.json');
            Route::post('/direct/json', 'directJson')->name('chat.direct.json');
            Route::post('/{conversation}/tasks/quick', 'quickTaskStore')->name('chat.tasks.quick');
            Route::get('/{conversation}/messages/json', 'messagesJson')->name('chat.messages.json');
            Route::post('/{conversation}/send/json', 'sendJson')->name('chat.send.json');
            Route::get('/{conversation}', 'show')->name('chat.show');
            Route::post('/{conversation}/send', 'send')->name('chat.send');
        });
    });

/*
|--------------------------------------------------------------------------
| Marketing
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:marketing|marketing_manager|admin'])
    ->prefix('marketing')
    ->name('marketing.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */
        Route::get('/dashboard', [MarketingDashboardController::class, 'index'])
            ->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | Ngân sách & Chỉ số — KHÔI PHỤC 2026-09-02
        |--------------------------------------------------------------------------
        | Cụm này mất route ngày 2026-05-06 khi routes/web.php được khôi phục từ bản
        | cũ (xem web.php.broken_before_restore_20260506_102318 ở commit đầu). Hệ quả
        | dây chuyền: /marketing/budget không vào được -> cụm metrics trông như chết ->
        | bị xoá ngày 2026-07-20 -> /marketing/dashboard trả 500 vì còn trỏ tới
        | route('marketing.budget').
        |
        | 5 route budget dưới đây lấy nguyên văn từ routes/web.phpbk2 (commit 8a2fa03).
        | Bốn route còn lại (budget.clone_next, metrics.edit/update/destroy) CHƯA TỪNG
        | được khai dù view vẫn gọi và controller vẫn có method — tức trang gốc cũng đã
        | 500 khi có dữ liệu metrics. Khai nốt để cụm này chạy đủ.
        */
        Route::get('/budget', [MarketingBudgetController::class, 'index'])->name('budget');
        Route::post('/budget', [MarketingBudgetController::class, 'store'])->name('budget.store');
        Route::post('/metrics', [MarketingMetricController::class, 'store'])->name('metrics.store');
        Route::post('/campaigns', [MarketingCampaignController::class, 'store'])->name('campaigns.store');

        Route::middleware(['role:marketing_manager|admin'])->group(function () {
            Route::get('/budget/{id}/edit', [MarketingBudgetController::class, 'edit'])
                ->whereNumber('id')->name('budget.edit');
            Route::put('/budget/{id}', [MarketingBudgetController::class, 'update'])
                ->whereNumber('id')->name('budget.update');
            Route::delete('/budget/{id}', [MarketingBudgetController::class, 'destroy'])
                ->whereNumber('id')->name('budget.destroy');
            Route::post('/budget/{id}/clone-next', [MarketingBudgetController::class, 'cloneToNextMonth'])
                ->whereNumber('id')->name('budget.clone_next');

            Route::get('/metrics/{id}/edit', [MarketingMetricController::class, 'edit'])
                ->whereNumber('id')->name('metrics.edit');
            Route::put('/metrics/{id}', [MarketingMetricController::class, 'update'])
                ->whereNumber('id')->name('metrics.update');
            Route::delete('/metrics/{id}', [MarketingMetricController::class, 'destroy'])
                ->whereNumber('id')->name('metrics.destroy');
        });

        /*
|--------------------------------------------------------------------------
| ================== KẾ HOẠCH (PLAN) ==================
|--------------------------------------------------------------------------
*/
        Route::prefix('plan')
            ->name('plan.')
            ->controller(\App\Http\Controllers\Marketing\MarketingPlanController::class)
            ->group(function () {

                Route::get('/', 'index')->name('overview');

                // CREATE
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');

                // EDIT
                Route::get('/{id}/edit', 'edit')
                    ->whereNumber('id')
                    ->name('edit');

                // SHOW
                Route::get('/{id}', 'show')
                    ->whereNumber('id')
                    ->name('show');

                // UPDATE
                Route::put('/{id}', 'update')
                    ->whereNumber('id')
                    ->name('update');

                // DELETE
                Route::delete('/{id}', 'destroy')
                    ->whereNumber('id')
                    ->name('delete');

                // APPROVE
                Route::post('/{id}/approve', 'approve')
                    ->whereNumber('id')
                    ->middleware(['role:marketing_manager|admin'])
                    ->name('approve');
            });
        /*
        |--------------------------------------------------------------------------
        | ================== TIẾN ĐỘ ==================
        |--------------------------------------------------------------------------
        */
        Route::prefix('progress')
            ->name('progress.')
            ->controller(MarketingProgressController::class)
            ->group(function () {

                Route::get('/', 'index')->name('index');
                Route::get('/monthly', 'monthly')->name('monthly');

                // Sửa tiến độ = cập nhật tài nguyên -> PUT trên chính nó, không POST /update.
                Route::put('/', 'update')->name('update');

                Route::post('/tasks/{id}/status', 'updateStatus')
                    ->whereNumber('id')
                    ->name('tasks.status');
            });

        /*
        |--------------------------------------------------------------------------
        | ================== BÁO CÁO (REPORT)
        |--------------------------------------------------------------------------
        */
        Route::prefix('report')
            ->name('report.')
            ->controller(MarketingReportController::class)
            ->group(function () {

                Route::get('/ads', 'ads')->name('ads');
                Route::get('/seo', 'seo')->name('seo');
                Route::get('/overview', 'overview')->name('overview');
                // ✅ ADS INPUT
                Route::get('/ads/input', 'adsInput')->name('ads.input');
                Route::post('/ads/input', 'adsStore')->name('ads.store');
                Route::post('/ads/import', 'adsImport')->name('ads.import');
                // Xoá dòng KPI quảng cáo: định danh bằng body (ngày + kênh + chiến dịch)
                // nên vẫn là DELETE trên tập, không phải POST /delete.
                Route::delete('/ads', 'adsDelete')->name('ads.delete');

                Route::get('/seo', 'seo')->name('seo');
                Route::get('/overview', 'overview')->name('overview');
            });

        /*
        |--------------------------------------------------------------------------
        | ================== LEADS
        |--------------------------------------------------------------------------
        */
        Route::prefix('leads')
            ->name('leads.')
            ->controller(MarketingLeadController::class)
            ->group(function () {

                Route::get('/', 'index')->name('index');
                Route::get('/upload', 'upload')->name('upload');
                Route::post('/import', 'import')->name('import');
            });

        /*
        |--------------------------------------------------------------------------
        | ================== KPI & PAYROLL
        |--------------------------------------------------------------------------
        */
        Route::prefix('kpi-payroll')
            ->name('kpi-payroll.')
            ->group(function () {

                Route::get('/', [KpiPayrollController::class, 'index'])
                    ->name('index');

                Route::get('/my', [KpiPayrollController::class, 'my'])
                    ->name('my');

                Route::post('/my', [KpiPayrollController::class, 'saveMy'])
                    ->name('my.save');

                Route::middleware(['role:marketing_manager|admin'])
                    ->group(function () {

                        Route::get('/settings', [KpiPayrollController::class, 'settings'])
                            ->name('settings');

                        Route::post('/settings', [KpiPayrollController::class, 'saveSettings'])
                            ->name('settings.save');
                    });
            });

        /*
        |--------------------------------------------------------------------------
        | ================== REPORTS (CONTENT)
        |--------------------------------------------------------------------------
        */
        Route::prefix('reports')
            ->name('reports.')
            ->group(function () {

                /*
                |--------------------------------------------------------------------------
                | Content Calendar
                |--------------------------------------------------------------------------
                */
                Route::controller(ContentCalendarController::class)
                    ->group(function () {

                        Route::get('/content-calendar', 'index')
                            ->name('content-calendar');

                        Route::post('/content-calendar', 'store')
                            ->name('content-calendar.store');

                        Route::get('/content-calendar/{id}', 'show')
                            ->whereNumber('id')
                            ->name('content-calendar.show');

                        Route::put('/content-calendar/{id}', 'update')
                            ->whereNumber('id')
                            ->name('content-calendar.update');

                        Route::delete('/content-calendar/{id}', 'destroy')
                            ->whereNumber('id')
                            ->name('content-calendar.destroy');

                        Route::post('/content-calendar/{id}/files', 'uploadFile')
                            ->whereNumber('id')
                            ->name('content-calendar.files');

                        Route::delete('/content-calendar/{id}/files/{fileId}', 'deleteFile')
                            ->whereNumber('id')
                            ->whereNumber('fileId')
                            ->name('content-calendar.files.delete');

                        Route::post('/content-calendar/{id}/submit', 'submit')
                            ->whereNumber('id')
                            ->name('content-calendar.submit');

                        Route::post('/content-calendar/{id}/approve', 'approve')
                            ->whereNumber('id')
                            ->middleware(['role:marketing_manager|admin'])
                            ->name('content-calendar.approve');

                        Route::get('/content-calendar/{id}/weekly-metrics', 'getWeeklyMetrics')
                            ->whereNumber('id')
                            ->name('content-calendar.weekly-metrics.get');

                        Route::post('/content-calendar/{id}/weekly-metrics', 'saveWeeklyMetrics')
                            ->whereNumber('id')
                            ->name('content-calendar.weekly-metrics');

                        Route::get('/content-calendar/weekly-dashboard', 'weeklyDashboard')
                            ->name('content-calendar.weekly-dashboard');

                        Route::post('/content-calendar/{id}/feedback', 'storeFeedback')
                            ->whereNumber('id')
                            ->name('content-calendar.feedback.store');

                        Route::put('/content-calendar/{id}/feedback/{feedbackId}', 'updateFeedback')
                            ->whereNumber('id')
                            ->whereNumber('feedbackId')
                            ->name('content-calendar.feedback.update');

                        Route::delete('/content-calendar/{id}/feedback/{feedbackId}', 'deleteFeedback')
                            ->whereNumber('id')
                            ->name('content-calendar.feedback.delete');
                    });

                /*
                |--------------------------------------------------------------------------
                | Weekly Tasks
                |--------------------------------------------------------------------------
                */
                Route::controller(WeeklyTaskController::class)
                    ->group(function () {

                        Route::get('/weekly-tasks', 'index')
                            ->name('weekly-tasks');

                        Route::get('/weekly-tasks/create', 'create')
                            ->name('weekly-tasks.create');

                        Route::post('/weekly-tasks', 'store')
                            ->name('weekly-tasks.store');

                        Route::get('/weekly-tasks/{id}', 'show')
                            ->whereNumber('id')
                            ->name('weekly-tasks.show');

                        Route::get('/weekly-tasks/{id}/edit', 'edit')
                            ->whereNumber('id')
                            ->name('weekly-tasks.edit');

                        Route::put('/weekly-tasks/{id}', 'update')
                            ->whereNumber('id')
                            ->name('weekly-tasks.update');

                        Route::delete('/weekly-tasks/{id}', 'destroy')
                            ->whereNumber('id')
                            ->name('weekly-tasks.destroy');
                    });
            });

    });
