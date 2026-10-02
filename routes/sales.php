<?php

/*
|---------------------------------------------------------------------------
| Route: Sales: báo giá, hoa hồng, báo cáo công việc
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

use App\Http\Controllers\CRM\SalesCommissionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

Route::middleware(['auth'])
    ->prefix('bao-gia')
    ->name('sales-quotations.')
    ->controller(\App\Http\Controllers\CRM\SalesQuotationController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{salesQuotation}', 'show')->name('show');
        Route::get('/{salesQuotation}/edit', 'edit')->name('edit');
        Route::put('/{salesQuotation}', 'update')->name('update');
        Route::delete('/{salesQuotation}', 'destroy')->name('destroy');
        Route::post('/{salesQuotation}/da-gui', 'markSent')->name('sent');
        Route::get('/{salesQuotation}/pdf', 'pdf')->name('pdf');
        Route::get('/{salesQuotation}/pdf-download', 'downloadPdf')->name('pdf.download');
        Route::get('/{salesQuotation}/excel', 'excel')->name('excel');
    });

// Đăng ký công khai đã bị vô hiệu hóa vì lý do bảo mật (CRM nội bộ):
// tài khoản nhân viên do admin tạo qua module Quản lý người dùng / phân quyền.
// Nếu cần mở lại có kiểm soát, gate bằng role:admin thay vì mở public.

/*
|--------------------------------------------------------------------------
| Sales
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->group(function () {
    Route::get('/sales/commissions', [SalesCommissionController::class, 'index'])
        ->name('sales.commissions.index');

    Route::get('/sales/commissions/exports/excel', [SalesCommissionController::class, 'exportExcel'])
        ->name('sales.commissions.export.excel');

    Route::get('/sales/commissions/exports/pdf', [SalesCommissionController::class, 'exportPdf'])
        ->name('sales.commissions.export.pdf');

    Route::middleware(['role:admin|sales_manager|accounting'])->group(function () {
        Route::get('/sales/commissions/settings', [SalesCommissionController::class, 'commissionSettings'])
            ->name('sales.commissions.settings');

        Route::post('/sales/commissions/settings', [SalesCommissionController::class, 'commissionSettingsSave'])
            ->name('sales.commissions.settings.save');
    });

    Route::get('/sales/kpi', [SalesCommissionController::class, 'kpiDashboard'])
        ->name('sales.kpi.index');

    Route::get('/sales/kpi/my', [SalesCommissionController::class, 'kpiMyForm'])
        ->name('sales.kpi.my');

    Route::post('/sales/kpi/my', [SalesCommissionController::class, 'kpiMyStore'])
        ->name('sales.kpi.my.store');

    Route::middleware(['role:admin|sales_manager|accounting'])->group(function () {
        Route::get('/sales/kpi/settings', [SalesCommissionController::class, 'kpiSettings'])
            ->name('sales.kpi.settings');

        Route::post('/sales/kpi/settings', [SalesCommissionController::class, 'kpiSettingsSave'])
            ->name('sales.kpi.settings.save');
    });
});

/* EGO_SALES_WORK_REPORTS_START */
Route::middleware(['auth'])
    ->prefix('sales/work-reports')
    ->name('sales.work-reports.')
    ->controller(\App\Http\Controllers\CRM\SalesWorkReportController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/exports/csv', 'exportCsv')->name('export');
        Route::get('/{id}', 'show')->whereNumber('id')->name('show');
        Route::get('/{id}/edit', 'edit')->whereNumber('id')->name('edit');
        Route::put('/{id}', 'update')->whereNumber('id')->name('update');
        Route::delete('/{id}', 'destroy')->whereNumber('id')->name('destroy');
        Route::post('/{id}/duyet', 'approve')
            ->whereNumber('id')
            ->middleware('role:admin|sales_manager|accounting')
            ->name('approve');
    });

/* EGO_MARKETING_PLAN_FILE_PREVIEW_END */

/* EGO_SALES_WORK_REPORT_DETAIL_JSON_START */
Route::middleware(['auth'])
    ->get('/sales/work-reports/{id}/detail-json', function ($id) {
        $db = DB::class;
        $schema = Schema::class;
        $auth = Auth::class;

        $user = $auth::user();

        $canManage = false;
        if ($user) {
            if (method_exists($user, 'hasAnyRole')) {
                $canManage = $user->hasAnyRole(['admin', 'sales_manager', 'accounting']);
            } elseif (method_exists($user, 'hasRole')) {
                $canManage = $user->hasRole('admin') || $user->hasRole('sales_manager') || $user->hasRole('accounting');
            } elseif (isset($user->role)) {
                $canManage = in_array($user->role, ['admin', 'sales_manager', 'accounting'], true);
            }
        }

        $select = ['r.*'];
        $q = $db::table('sales_work_reports as r');

        if ($schema::hasTable('users')) {
            $q->leftJoin('users as u', 'u.id', '=', 'r.assigned_to')
                ->leftJoin('users as creator', 'creator.id', '=', 'r.created_by');
            $select[] = 'u.name as sales_name';
            $select[] = 'creator.name as creator_name';
        } else {
            $select[] = $db::raw('NULL as sales_name');
            $select[] = $db::raw('NULL as creator_name');
        }

        if ($schema::hasTable('crm_sources')) {
            $q->leftJoin('crm_sources as s', 's.id', '=', 'r.data_source_id');
            $select[] = 's.name as source_name';
        } else {
            $select[] = $db::raw('NULL as source_name');
        }

        $report = $q->select($select)->where('r.id', (int) $id)->first();

        abort_unless($report, 404);
        abort_if(! $canManage && (int) $report->assigned_to !== (int) $auth::id(), 403);

        $types = [
            'dealer' => 'Đại lý',
            'retail' => 'Mua lẻ',
            'turnkey' => 'Lắp đặt trọn gói',
            'personal' => 'Cá nhân / hộ gia đình',
            'business' => 'Doanh nghiệp',
            'contractor' => 'Nhà thầu',
            'factory' => 'Nhà xưởng / C&I',
            'other' => 'Khác',
        ];

        $stages = [
            'new_need_confirm' => 'Mới - cần xác nhận',
            'interested' => 'Quan tâm',
            'hot_need_quote' => 'Nóng - cần báo giá',
            'quoted_waiting' => 'Đã báo giá - chờ phản hồi',
            'comparing_price' => 'Đang so sánh giá',
            'need_follow' => 'Cần chăm sóc lại',
            'unreachable' => 'Chưa liên hệ được',
            'not_interested' => 'Không quan tâm',
            'closed_won' => 'Đã chốt',
            'closed_lost' => 'Đã mất',
        ];

        $statuses = [
            'new' => 'Data mới',
            'contacted' => 'Đã liên hệ',
            'consulting' => 'Đang tư vấn',
            'quoted' => 'Đã báo giá',
            'follow_up' => 'Cần chăm sóc lại',
            'won' => 'Chốt đơn',
            'lost' => 'Thất bại',
            'no_answer' => 'Không nghe máy',
            'invalid' => 'Data lỗi',
        ];

        $priorities = [
            'low' => 'Thấp',
            'normal' => 'Bình thường',
            'high' => 'Cao',
            'hot' => 'Rất nóng',
        ];

        $calls = [
            'not_called' => 'Chưa gọi',
            'answered' => 'Nghe máy',
            'no_answer' => 'Không nghe',
            'busy' => 'Máy bận',
            'call_back' => 'Hẹn gọi lại',
        ];

        $quotes = [
            'not_sent' => 'Chưa gửi',
            'sent' => 'Đã gửi',
            'viewed' => 'Khách đã xem',
            'waiting' => 'Đang chờ phản hồi',
        ];

        $dateText = function ($value) {
            if (! $value) {
                return '—';
            }
            try {
                return \Carbon\Carbon::parse($value)->format('d/m/Y H:i');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        };

        $moneyText = function ($value) {
            if ($value === null || $value === '') {
                return '—';
            }

            return number_format((float) $value, 0, ',', '.').' đ';
        };

        $row = (array) $report;
        $row['customer_type_label'] = $types[$report->customer_type] ?? ($report->customer_type ?: '—');
        $row['customer_stage_label'] = $stages[$report->customer_stage] ?? ($report->customer_stage ?: '—');
        $row['status_label'] = $statuses[$report->status] ?? ($report->status ?: '—');
        $row['priority_label'] = $priorities[$report->priority] ?? ($report->priority ?: '—');
        $row['call_1_result_label'] = $calls[$report->call_1_result] ?? ($report->call_1_result ?: '—');
        $row['call_2_result_label'] = $calls[$report->call_2_result] ?? ($report->call_2_result ?: '—');
        $row['quote_status_label'] = $quotes[$report->quote_status] ?? ($report->quote_status ?: '—');
        $row['created_at_text'] = $dateText($report->created_at ?? null);
        $row['updated_at_text'] = $dateText($report->updated_at ?? null);
        $row['data_received_at_text'] = $dateText($report->data_received_at ?? null);
        $row['call_1_at_text'] = $dateText($report->call_1_at ?? null);
        $row['call_2_at_text'] = $dateText($report->call_2_at ?? null);
        $row['last_contact_at_text'] = $dateText($report->last_contact_at ?? null);
        $row['quote_sent_at_text'] = $dateText($report->quote_sent_at ?? null);
        $row['next_followup_at_text'] = $dateText($report->next_followup_at ?? null);
        $row['revenue_expectation_text'] = $moneyText($report->revenue_expectation ?? null);

        $historyQ = $db::table('sales_work_reports as h')
            ->leftJoin('users as hu', 'hu.id', '=', 'h.assigned_to')
            ->select('h.id', 'h.customer_name', 'h.customer_phone', 'h.status', 'h.priority', 'h.customer_stage', 'h.call_1_result', 'h.quote_status', 'h.created_at', 'h.updated_at', 'hu.name as sales_name')
            ->where(function ($x) use ($report) {
                if (! empty($report->customer_phone)) {
                    $x->where('h.customer_phone', $report->customer_phone);
                } else {
                    $x->where('h.customer_name', $report->customer_name);
                }
            })
            ->orderByDesc('h.updated_at')
            ->limit(12);

        $history = $historyQ->get()->map(function ($h) use ($statuses, $priorities, $stages, $calls, $quotes, $dateText) {
            $a = (array) $h;
            $a['status_label'] = $statuses[$h->status] ?? ($h->status ?: '—');
            $a['priority_label'] = $priorities[$h->priority] ?? ($h->priority ?: '—');
            $a['customer_stage_label'] = $stages[$h->customer_stage] ?? ($h->customer_stage ?: '—');
            $a['call_1_result_label'] = $calls[$h->call_1_result] ?? ($h->call_1_result ?: '—');
            $a['quote_status_label'] = $quotes[$h->quote_status] ?? ($h->quote_status ?: '—');
            $a['updated_at_text'] = $dateText($h->updated_at ?? null);

            return $a;
        })->values();

        $followups = collect();

        if ($schema::hasTable('sales_customer_followups')) {
            $fq = $db::table('sales_customer_followups as f')
                ->leftJoin('users as fu', 'fu.id', '=', 'f.created_by')
                ->select('f.*', 'fu.name as creator_name')
                ->where(function ($x) use ($report) {
                    $x->where('f.sales_work_report_id', $report->id);
                    if (! empty($report->customer_phone)) {
                        $x->orWhere('f.customer_phone', $report->customer_phone);
                    }
                })
                ->orderByDesc('f.created_at')
                ->limit(10);

            $followups = $fq->get()->map(function ($f) use ($dateText) {
                $a = (array) $f;
                $a['created_at_text'] = $dateText($f->created_at ?? null);
                $a['followup_at_text'] = $dateText($f->followup_at ?? null);

                return $a;
            })->values();
        }

        return response()->json([
            'report' => $row,
            'history' => $history,
            'followups' => $followups,
        ]);
    })
    ->whereNumber('id')
    ->name('sales.work-reports.detail-json');
