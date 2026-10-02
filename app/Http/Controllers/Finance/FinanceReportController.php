<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Support\SchemaCache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinanceReportController extends Controller
{
    public function index(Request $request)
    {
        $period = in_array($request->input('period'), ['quarter', 'year'], true)
            ? $request->input('period')
            : 'quarter';

        $year = (int) $request->input('year', now()->year);
        if ($year < 2000 || $year > 2100) {
            $year = now()->year;
        }

        if ($period === 'quarter') {
            $quarter = (int) $request->input('quarter', (int) ceil(now()->month / 3));
            $quarter = min(max($quarter, 1), 4);
            $start = Carbon::create($year, (($quarter - 1) * 3) + 1, 1)->startOfDay();
            $end = (clone $start)->addMonths(2)->endOfMonth()->endOfDay();
            $periodLabel = 'Quý '.$quarter.' / '.$year;
            $pageTitle = 'Báo cáo quý';
        } else {
            $quarter = null;
            $start = Carbon::create($year, 1, 1)->startOfDay();
            $end = Carbon::create($year, 12, 31)->endOfDay();
            $periodLabel = 'Năm '.$year;
            $pageTitle = 'Báo cáo năm';
        }

        $data = $this->buildReportData($start, $end);

        return view('finance.reports', array_merge($data, [
            'period' => $period,
            'year' => $year,
            'quarter' => $quarter,
            'periodLabel' => $periodLabel,
            'pageTitle' => $pageTitle,
            'rangeStart' => $start->toDateString(),
            'rangeEnd' => $end->toDateString(),
            // tương thích view cũ
            'month' => $start->format('Y-m'),
            'monthStart' => $start->toDateString(),
            'monthEnd' => $end->toDateString(),
        ]));
    }

    public function settlement(Request $request)
    {
        $year = $this->validatedYear($request);
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();
        $data = $this->buildReportData($start, $end);

        $accounts = collect();
        if (SchemaCache::hasTable('accounts')) {
            $accounts = DB::table('accounts')
                ->where('is_active', 1)
                ->orderBy('type')
                ->orderBy('name')
                ->get();
        }

        return view('finance.settlement', array_merge($data, [
            'year' => $year,
            'rangeStart' => $start->toDateString(),
            'rangeEnd' => $end->toDateString(),
            'accounts' => $accounts,
        ]));
    }

    public function audit(Request $request)
    {
        $year = $this->validatedYear($request);
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->endOfDay();
        $data = $this->buildReportData($start, $end);
        $summary = $data['summary'];

        $accountsCount = 0;
        $accountsBalance = 0;
        if (SchemaCache::hasTable('accounts')) {
            $accountQuery = DB::table('accounts')->where('is_active', 1);
            $accountsCount = (clone $accountQuery)->count();
            $accountsBalance = (float) (clone $accountQuery)->sum('current_balance');
        }

        $checks = collect([
            [
                'name' => 'Dữ liệu thu trong kỳ',
                'status' => ($summary['receipt_count'] ?? 0) > 0 ? 'ok' : 'warning',
                'value' => ($summary['receipt_count'] ?? 0).' giao dịch',
                'note' => 'Đối chiếu các khoản khách hàng đã thanh toán.',
            ],
            [
                'name' => 'Chi phí đã duyệt kế toán',
                'status' => ($summary['payment_count'] ?? 0) > 0 ? 'ok' : 'warning',
                'value' => ($summary['payment_count'] ?? 0).' phiếu',
                'note' => 'Nguồn payment_requests trạng thái accounting_approved.',
            ],
            [
                'name' => 'Đề nghị thanh toán còn chờ',
                'status' => ($summary['pending_requests_count'] ?? 0) > 0 ? 'warning' : 'ok',
                'value' => ($summary['pending_requests_count'] ?? 0).' phiếu',
                'note' => 'Nên xử lý các phiếu còn chờ trước khi khóa số liệu.',
            ],
            [
                'name' => 'Tài khoản/quỹ đang hoạt động',
                'status' => $accountsCount > 0 ? 'ok' : 'warning',
                'value' => $accountsCount.' tài khoản',
                'note' => 'Số dư hiện tại: '.number_format($accountsBalance, 0, ',', '.').' đ.',
            ],
            [
                'name' => 'Công nợ phải thu còn lại',
                'status' => ($summary['customer_remain_total'] ?? 0) > 0 ? 'warning' : 'ok',
                'value' => number_format((float) ($summary['customer_remain_total'] ?? 0), 0, ',', '.').' đ',
                'note' => 'Đối chiếu công nợ khách hàng tại thời điểm kiểm tra.',
            ],
        ]);

        return view('finance.audit', array_merge($data, [
            'year' => $year,
            'rangeStart' => $start->toDateString(),
            'rangeEnd' => $end->toDateString(),
            'checks' => $checks,
            'accountsCount' => $accountsCount,
            'accountsBalance' => $accountsBalance,
        ]));
    }

    private function validatedYear(Request $request): int
    {
        $year = (int) $request->input('year', now()->year);

        return ($year >= 2000 && $year <= 2100) ? $year : now()->year;
    }

    private function buildReportData(Carbon $start, Carbon $end): array
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $totalReceipts = 0;
        $receiptCount = 0;
        $receiptsByCategory = collect();

        if (
            SchemaCache::hasTable('crm_payments') &&
            SchemaCache::hasColumn('crm_payments', 'amount') &&
            SchemaCache::hasColumn('crm_payments', 'payment_date')
        ) {
            $crmPaymentQuery = DB::table('crm_payments')
                ->whereBetween('payment_date', [$startDate, $endDate]);

            $totalReceipts = (float) (clone $crmPaymentQuery)->sum('amount');
            $receiptCount = (clone $crmPaymentQuery)->count();

            if (
                SchemaCache::hasTable('crm_payment_methods') &&
                SchemaCache::hasColumn('crm_payments', 'method_id') &&
                SchemaCache::hasColumn('crm_payment_methods', 'method_name')
            ) {
                $receiptsByCategory = DB::table('crm_payments')
                    ->leftJoin('crm_payment_methods', 'crm_payments.method_id', '=', 'crm_payment_methods.id')
                    ->whereBetween('crm_payments.payment_date', [$startDate, $endDate])
                    ->selectRaw("COALESCE(NULLIF(crm_payment_methods.method_name, ''), CONCAT('Phương thức #', crm_payments.method_id), 'Chưa phân loại') as category")
                    ->selectRaw('SUM(crm_payments.amount) as total_amount')
                    ->selectRaw('COUNT(crm_payments.id) as total_count')
                    ->groupBy('crm_payments.method_id', 'crm_payment_methods.method_name')
                    ->orderByDesc('total_amount')
                    ->get();
            } elseif (SchemaCache::hasColumn('crm_payments', 'method_id')) {
                $receiptsByCategory = DB::table('crm_payments')
                    ->whereBetween('payment_date', [$startDate, $endDate])
                    ->selectRaw("COALESCE(CONCAT('Phương thức #', method_id), 'Chưa phân loại') as category")
                    ->selectRaw('SUM(amount) as total_amount')
                    ->selectRaw('COUNT(id) as total_count')
                    ->groupBy('method_id')
                    ->orderByDesc('total_amount')
                    ->get();
            } else {
                $receiptsByCategory = collect([(object) [
                    'category' => 'Khách thanh toán',
                    'total_amount' => $totalReceipts,
                    'total_count' => $receiptCount,
                ]]);
            }
        }

        $totalPayments = 0;
        $paymentCount = 0;
        $paymentsByCategory = collect();

        if (
            SchemaCache::hasTable('payment_requests') &&
            SchemaCache::hasColumn('payment_requests', 'amount') &&
            SchemaCache::hasColumn('payment_requests', 'status')
        ) {
            $approvedDateColumn = SchemaCache::hasColumn('payment_requests', 'accounting_approved_at')
                ? 'accounting_approved_at'
                : (SchemaCache::hasColumn('payment_requests', 'updated_at') ? 'updated_at' : 'created_at');

            $approvedExpenseQuery = DB::table('payment_requests')
                ->where('status', 'accounting_approved')
                ->whereDate($approvedDateColumn, '>=', $startDate)
                ->whereDate($approvedDateColumn, '<=', $endDate);

            $approvedRows = $approvedExpenseQuery->get();
            $totalPayments = (float) $approvedRows->sum(fn ($row) => (float) ($row->amount ?? 0));
            $paymentCount = $approvedRows->count();

            $paymentsByCategory = $approvedRows
                ->groupBy(function ($row) {
                    foreach (['cost_type', 'department', 'doc_type'] as $field) {
                        $value = trim((string) ($row->{$field} ?? ''));
                        if ($value !== '') {
                            return $value;
                        }
                    }

                    return 'Chưa phân loại';
                })
                ->map(fn ($rows, $category) => (object) [
                    'category' => $category,
                    'total_amount' => (float) $rows->sum(fn ($row) => (float) ($row->amount ?? 0)),
                    'total_count' => $rows->count(),
                ])
                ->sortByDesc('total_amount')
                ->values();
        }

        $budgets = collect();
        $totalBudget = 0;
        $budgetCount = 0;
        if (SchemaCache::hasTable('finance_budgets')) {
            $budgetRows = DB::table('finance_budgets')
                ->whereBetween('month', [$startDate, $endDate])
                ->orderBy('month')
                ->orderBy('category')
                ->get();

            $budgets = $budgetRows
                ->groupBy('category')
                ->map(function ($rows, $category) {
                    return (object) [
                        'category' => $category,
                        'budget_amount' => (float) $rows->sum('budget_amount'),
                        'note' => $rows->pluck('note')->filter()->unique()->implode(' • '),
                    ];
                })
                ->values();

            $totalBudget = (float) $budgetRows->sum('budget_amount');
            $budgetCount = $budgetRows->count();
        }

        $pendingRequestsAmount = 0;
        $pendingRequestsCount = 0;
        if (SchemaCache::hasTable('payment_requests')) {
            $pendingQuery = DB::table('payment_requests')
                ->whereIn('status', ['submitted', 'admin_approved']);
            $pendingRequestsAmount = (float) (clone $pendingQuery)->sum('amount');
            $pendingRequestsCount = (clone $pendingQuery)->count();
        }

        $customerDebtTotal = 0;
        $customerPaidTotal = 0;
        $customerRemainTotal = 0;
        if (SchemaCache::hasTable('crm_customer_debts')) {
            if (SchemaCache::hasColumn('crm_customer_debts', 'total_amount')) {
                $customerDebtTotal = (float) DB::table('crm_customer_debts')->sum('total_amount');
            }
            if (SchemaCache::hasColumn('crm_customer_debts', 'paid_amount')) {
                $customerPaidTotal = (float) DB::table('crm_customer_debts')->sum('paid_amount');
            }
            if (SchemaCache::hasColumn('crm_customer_debts', 'debt_amount')) {
                $customerRemainTotal = (float) DB::table('crm_customer_debts')->sum('debt_amount');
            } else {
                $customerRemainTotal = max($customerDebtTotal - $customerPaidTotal, 0);
            }
        }

        $orderAmount = 0;
        $orderCount = 0;
        if (SchemaCache::hasTable('crm_orders') && SchemaCache::hasColumn('crm_orders', 'total_amount')) {
            $orderQuery = DB::table('crm_orders');
            if (SchemaCache::hasColumn('crm_orders', 'order_date')) {
                $orderQuery->whereBetween('order_date', [$startDate, $endDate]);
            } elseif (SchemaCache::hasColumn('crm_orders', 'created_at')) {
                $orderQuery->whereDate('created_at', '>=', $startDate)->whereDate('created_at', '<=', $endDate);
            }
            $orderAmount = (float) (clone $orderQuery)->sum('total_amount');
            $orderCount = (clone $orderQuery)->count();
        }

        $netCashFlow = $totalReceipts - $totalPayments;
        $budgetRemain = $totalBudget - $totalPayments;
        $budgetUsageRate = $totalBudget > 0 ? round(($totalPayments / $totalBudget) * 100, 1) : 0;

        return [
            'summary' => [
                'account_balance' => 0,
                'total_receipts' => $totalReceipts,
                'receipt_count' => $receiptCount,
                'total_payments' => $totalPayments,
                'payment_count' => $paymentCount,
                'net_cash_flow' => $netCashFlow,
                'total_budget' => $totalBudget,
                'budget_count' => $budgetCount,
                'budget_remain' => $budgetRemain,
                'budget_usage_rate' => $budgetUsageRate,
                'pending_requests_amount' => $pendingRequestsAmount,
                'pending_requests_count' => $pendingRequestsCount,
                'approved_requests_amount' => $totalPayments,
                'approved_requests_count' => $paymentCount,
                'customer_debt_total' => $customerDebtTotal,
                'customer_paid_total' => $customerPaidTotal,
                'customer_remain_total' => $customerRemainTotal,
                'monthly_order_amount' => $orderAmount,
                'monthly_order_count' => $orderCount,
            ],
            'accounts' => collect(),
            'budgets' => $budgets,
            'receiptsByCategory' => $receiptsByCategory,
            'paymentsByCategory' => $paymentsByCategory,
        ];
    }
}
