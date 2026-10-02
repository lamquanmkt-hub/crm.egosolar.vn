<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CRM\Orders\Order;
use App\Support\EgoCompanyScope;
use App\Support\ProbeFailureLog;
use App\Support\SchemaCache;
use App\View\Presenters\Finance\CustomerDebtListPresenter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Theo dõi công nợ khách hàng dựa trên đơn hàng CRM.
 */
class CustomerDebtController extends Controller
{
    /**
     * Danh sách công nợ gom theo khách hàng kèm chi tiết từng đơn và tổng hợp.
     */
    public function index(Request $request, CustomerDebtListPresenter $presenter)
    {
        if ((string) $request->input('debt_type', '') === 'construction') {
            return redirect()->route('finance.project-receivables.index');
        }

        $query = Order::query()
            ->with(['lead.customer.customerType'])
            ->latest();

        $this->applyCompanyScope($query);
        $this->excludeHiddenDebts($query);

        $debtContext = $this->debtContext($request);
        $this->applyDebtTypeFilter($query, $debtContext['type']);

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->where(function ($q) {
                    $q->where('payment_recorded', 1)
                        ->orWhere('total_amount', '<=', 0);
                });
            }

            if ($request->payment_status === 'unpaid') {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('payment_recorded')
                            ->orWhere('payment_recorded', 0);
                    })->where('total_amount', '>', 0);
                });
            }
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $query->where(function ($q) use ($keyword) {
                $q->where('order_code', 'like', '%'.$keyword.'%')
                    ->orWhere('receiver_name', 'like', '%'.$keyword.'%')
                    ->orWhereHas('lead.customer', function ($sub) use ($keyword) {
                        $sub->where('name', 'like', '%'.$keyword.'%');
                    });
            });
        }

        $orders = $query->get();

        $grouped = $orders
            ->groupBy(function ($order) {
                $customer = optional(optional($order->lead)->customer);

                if (! empty($customer->id)) {
                    return 'customer_'.$customer->id;
                }

                return 'name_'.md5($this->resolveCustomerName($order));
            })
            ->map(function (Collection $items, $groupKey) {
                $first = $items->first();

                $total = 0;
                $paid = 0;
                $debt = 0;

                $details = $items->map(function ($order) use (&$total, &$paid, &$debt) {
                    $money = $this->mapMoney($order);

                    $total += $money['total'];
                    $paid += $money['paid'];
                    $debt += $money['debt'];

                    return (object) [
                        'id' => $order->id,
                        'order_code' => $order->order_code ?? ('#'.$order->id),
                        'total_amount' => $money['total'],
                        'paid_amount' => $money['paid'],
                        'debt_amount' => $money['debt'],
                        'payment_recorded' => (int) ($order->payment_recorded ?? 0),
                        'created_at' => $order->created_at,
                    ];
                })->values();

                return (object) [
                    'group_key' => $groupKey,
                    'customer_name' => $this->resolveCustomerName($first),
                    'total_orders' => $items->count(),
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'debt_amount' => $debt,
                    'orders' => $details,
                ];
            })
            ->sortByDesc('debt_amount')
            ->values();

        $fullSummary = [
            'total_customers' => $grouped->count(),
            'total_amount' => (float) $grouped->sum('total_amount'),
            'paid_amount' => (float) $grouped->sum('paid_amount'),
            'debt_amount' => (float) $grouped->sum('debt_amount'),
        ];

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $grouped->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $customers = new LengthAwarePaginator(
            $currentItems,
            $grouped->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('finance.debt-customers', array_merge([
            'fullSummary' => $fullSummary,
            'debtContext' => $debtContext,

            // Giá trị đang lọc + query hiện tại, để view khỏi tự đọc request.
            'filterQuery' => $request->query(),
            'filterKeyword' => $request->input('keyword'),
            'filterFromDate' => $request->input('from_date'),
            'filterToDate' => $request->input('to_date'),
            'filterPaymentStatus' => $request->input('payment_status'),
        ], $presenter->viewData($customers, $fullSummary)));
    }

    /**
     * Tổng hợp công nợ theo từng khách hàng (không kèm chi tiết đơn).
     */
    public function byCustomer(Request $request)
    {
        if ((string) $request->input('debt_type', '') === 'construction') {
            return redirect()->route('finance.project-receivables.index');
        }

        $query = Order::query()
            ->with(['lead.customer.customerType'])
            ->latest();

        $this->applyCompanyScope($query);
        $this->excludeHiddenDebts($query);

        $debtContext = $this->debtContext($request);
        $this->applyDebtTypeFilter($query, $debtContext['type']);

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->where(function ($q) {
                    $q->where('payment_recorded', 1)
                        ->orWhere('total_amount', '<=', 0);
                });
            }

            if ($request->payment_status === 'unpaid') {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('payment_recorded')
                            ->orWhere('payment_recorded', 0);
                    })->where('total_amount', '>', 0);
                });
            }
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $query->where(function ($q) use ($keyword) {
                $q->where('order_code', 'like', '%'.$keyword.'%')
                    ->orWhere('receiver_name', 'like', '%'.$keyword.'%')
                    ->orWhereHas('lead.customer', function ($sub) use ($keyword) {
                        $sub->where('name', 'like', '%'.$keyword.'%');
                    });
            });
        }

        $orders = $query->get();

        $grouped = $orders
            ->groupBy(function ($order) {
                $customer = optional(optional($order->lead)->customer);

                if (! empty($customer->id)) {
                    return 'customer_'.$customer->id;
                }

                return 'name_'.md5($this->resolveCustomerName($order));
            })
            ->map(function (Collection $items) {
                $first = $items->first();

                $total = 0;
                $paid = 0;
                $debt = 0;

                foreach ($items as $order) {
                    $money = $this->mapMoney($order);
                    $total += $money['total'];
                    $paid += $money['paid'];
                    $debt += $money['debt'];
                }

                return (object) [
                    'customer_name' => $this->resolveCustomerName($first),
                    'total_orders' => $items->count(),
                    'total_amount' => $total,
                    'paid_amount' => $paid,
                    'debt_amount' => $debt,
                ];
            })
            ->sortByDesc('debt_amount')
            ->values();

        $perPage = 20;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $grouped->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $debts = new LengthAwarePaginator(
            $currentItems,
            $grouped->count(),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('finance.debt-customers-by-user', compact('debts', 'debtContext'));
    }

    /**
     * Lịch sử thanh toán theo từng đơn hàng có phân trang.
     */
    public function paymentHistory(Request $request)
    {
        if ((string) $request->input('debt_type', '') === 'construction') {
            return redirect()->route('finance.project-receivables.index');
        }

        $query = Order::query()
            ->with(['lead.customer.customerType'])
            ->latest();

        $this->applyCompanyScope($query);
        $this->excludeHiddenDebts($query);

        $debtContext = $this->debtContext($request);
        $this->applyDebtTypeFilter($query, $debtContext['type']);

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('payment_status')) {
            if ($request->payment_status === 'paid') {
                $query->where(function ($q) {
                    $q->where('payment_recorded', 1)
                        ->orWhere('total_amount', '<=', 0);
                });
            }

            if ($request->payment_status === 'unpaid') {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('payment_recorded')
                            ->orWhere('payment_recorded', 0);
                    })->where('total_amount', '>', 0);
                });
            }
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $query->where(function ($q) use ($keyword) {
                $q->where('order_code', 'like', '%'.$keyword.'%')
                    ->orWhere('receiver_name', 'like', '%'.$keyword.'%')
                    ->orWhereHas('lead.customer', function ($sub) use ($keyword) {
                        $sub->where('name', 'like', '%'.$keyword.'%');
                    });
            });
        }

        $orders = $query->paginate(20)->appends($request->query());

        $orders->getCollection()->transform(function ($order) {
            $money = $this->mapMoney($order);

            $order->finance_customer_name = $this->resolveCustomerName($order);
            $order->finance_total_amount = $money['total'];
            $order->finance_paid_amount = $money['paid'];
            $order->finance_debt_amount = $money['debt'];
            $order->finance_payment_status = $money['debt'] > 0 ? 'Công nợ' : 'Đã hoàn thành';

            return $order;
        });

        return view('finance.payment-history', compact('orders', 'debtContext'));
    }

    /**
     * Xóa một đơn khỏi màn công nợ của công ty hiện tại.
     *
     * Không xóa đơn hàng CRM; chỉ ẩn đơn khỏi module công nợ để tránh mất dữ liệu gốc.
     */
    public function destroy(Request $request, int $id)
    {
        if (! SchemaCache::hasTable('finance_customer_debt_exclusions')) {
            return back()->with('error', 'Chưa có bảng ẩn công nợ. Vui lòng chạy migration trước.');
        }

        $companyId = (int) EgoCompanyScope::currentId();

        $orderQuery = Order::query()->whereKey($id);
        $this->applyCompanyScope($orderQuery);

        if (! $orderQuery->exists()) {
            return back()->with('error', 'Không tìm thấy công nợ trong công ty hiện tại.');
        }

        DB::table('finance_customer_debt_exclusions')->updateOrInsert(
            [
                'order_id' => $id,
                'company_id' => $companyId,
            ],
            [
                'deleted_by' => optional($request->user())->id,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'Đã xóa khoản này khỏi danh sách công nợ. Đơn hàng CRM vẫn được giữ nguyên.');
    }

    /**
     * Giới hạn đơn hàng theo công ty đang được chọn (Ego VN / Ego QT).
     */
    private function applyCompanyScope($query): void
    {
        $companyId = (int) EgoCompanyScope::currentId();

        if ($companyId <= 0 || ! SchemaCache::hasColumn('crm_orders', 'company_id')) {
            return;
        }

        $query->where('crm_orders.company_id', $companyId);
    }

    /**
     * Loại các khoản mà kế toán đã chủ động xóa khỏi màn công nợ.
     */
    private function excludeHiddenDebts($query): void
    {
        if (! SchemaCache::hasTable('finance_customer_debt_exclusions')) {
            return;
        }

        $companyId = (int) EgoCompanyScope::currentId();

        $query->whereNotIn('crm_orders.id', function ($sub) use ($companyId) {
            $sub->select('order_id')
                ->from('finance_customer_debt_exclusions')
                ->where('company_id', $companyId);
        });
    }

    /**
     * Ngữ cảnh từng nhóm nợ phải thu trên menu Tài chính - Kế toán.
     */
    private function debtContext(Request $request): array
    {
        $type = (string) $request->input('debt_type', 'all');

        return match ($type) {
            'walk_in' => [
                'type' => 'walk_in',
                'title' => 'Phải thu khách vãng lai',
                'subtitle' => 'Theo dõi công nợ đơn hàng khách cá nhân / khách lẻ và các đơn chưa gắn hồ sơ khách hàng.',
                'kicker' => 'WALK-IN RECEIVABLES',
            ],
            'dealer' => [
                'type' => 'dealer',
                'title' => 'Phải thu đại lý',
                'subtitle' => 'Tự động tổng hợp công nợ từ các đơn hàng của khách hàng có loại Đại lý.',
                'kicker' => 'DEALER RECEIVABLES',
            ],
            'investment' => [
                'type' => 'investment',
                'title' => 'Phải thu dự án đầu tư',
                'subtitle' => 'Tự động tổng hợp công nợ từ các đơn hàng của khách hàng thuộc nhóm Dự án.',
                'kicker' => 'PROJECT RECEIVABLES',
            ],
            default => [
                'type' => 'all',
                'title' => 'Công nợ khách hàng',
                'subtitle' => 'Tổng hợp công nợ từ đơn hàng CRM. Riêng Phải thu công trình được lấy trực tiếp từ module Dự án/Công trình.',
                'kicker' => 'CUSTOMER RECEIVABLES',
            ],
        };
    }

    /**
     * Lọc trực tiếp từ loại khách hàng CRM để các page mới có dữ liệu thật,
     * không tạo một bảng công nợ trùng với đơn hàng.
     */
    private function applyDebtTypeFilter($query, string $type): void
    {
        if ($type === 'all') {
            return;
        }

        if ($type === 'dealer') {
            $query->whereHas('lead.customer.customerType', function ($q) {
                $q->where('name', 'Đại lý');
            });

            return;
        }

        if ($type === 'investment') {
            $query->whereHas('lead.customer.customerType', function ($q) {
                $q->where('name', 'Dự án');
            });

            return;
        }

        if ($type === 'walk_in') {
            $query->where(function ($q) {
                $q->whereDoesntHave('lead.customer')
                    ->orWhereHas('lead.customer.customerType', function ($sub) {
                        $sub->where('name', 'Cá nhân');
                    });
            });

            return;
        }

        // Công trình: ưu tiên 2 loại khách hàng nghiệp vụ đang có trong seeder.
        // Các hồ sơ chưa gắn loại vẫn được giữ lại để tránh mất dữ liệu legacy.
        $query->where(function ($q) {
            $q->whereHas('lead.customer.customerType', function ($sub) {
                $sub->whereIn('name', ['Lắp mới', 'Mở rộng']);
            })->orWhereHas('lead.customer', function ($sub) {
                $sub->whereNull('customer_type_id');
            });
        });
    }

    /**
     * Xác định tên khách hàng của đơn: khách CRM, người nhận hoặc khách lẻ.
     */
    private function resolveCustomerName($order): string
    {
        $customer = optional(optional($order->lead)->customer);

        if (! empty($customer->name)) {
            return $customer->name;
        }

        if (! empty($order->receiver_name)) {
            return $order->receiver_name;
        }

        return 'Khách lẻ / Chưa xác định';
    }

    /**
     * Tính tổng tiền, đã trả và còn nợ của một đơn hàng.
     *
     * @return array{total: float, paid: float, debt: float}
     */
    private function mapMoney($order): array
    {
        $total = (float) ($order->total_amount ?? 0);

        if ($total <= 0) {
            return [
                'total' => 0.0,
                'paid' => 0.0,
                'debt' => 0.0,
            ];
        }

        $orderId = (int) ($order->id ?? 0);

        $paidFromPayments = 0.0;
        $paidFromDebtTable = 0.0;

        try {
            if ($orderId > 0 && SchemaCache::hasTable('crm_payments')) {
                $paidFromPayments = (float) DB::table('crm_payments')
                    ->where('order_id', $orderId)
                    ->sum('amount');
            }
        } catch (\Throwable $e) {
            ProbeFailureLog::warn('CustomerDebtController::mapMoney', $e);

            $paidFromPayments = 0.0;
        }

        try {
            if ($orderId > 0 && SchemaCache::hasTable('crm_customer_debts')) {
                $paidFromDebtTable = (float) DB::table('crm_customer_debts')
                    ->where('order_id', $orderId)
                    ->max('paid_amount');
            }
        } catch (\Throwable $e) {
            ProbeFailureLog::warn('CustomerDebtController::mapMoney', $e);

            $paidFromDebtTable = 0.0;
        }

        $paid = max($paidFromPayments, $paidFromDebtTable);

        if ($paid <= 0 && (int) ($order->payment_recorded ?? 0) === 1) {
            $paid = $total;
        }

        $paid = min(max($paid, 0.0), $total);
        $debt = max($total - $paid, 0.0);

        return [
            'total' => $total,
            'paid' => $paid,
            'debt' => $debt,
        ];
    }
}
