<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Services\Sales\Commission\CommissionEligibilityPolicy;
use App\Services\Sales\Commission\CommissionRuleMatcher;
use App\Services\Sales\Commission\CommissionSchema;
use App\Services\Sales\Commission\OrderColumnMap;
use App\Support\ProbeFailureLog;
use App\Support\SchemaCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xuất Excel bảng hoa hồng sales theo tháng (PhpSpreadsheet).
 *
 * Sales chỉ export dữ liệu đơn của chính mình; các role khác export toàn bộ.
 *
 * Tách nguyên trạng từ SalesCommissionController::exportExcel (P1c refactor) —
 * hành vi chốt bằng SalesCommissionPagesCharacterizationTest.
 */
class SalesCommissionExcelExporter
{
    /**
     * Dựng workbook hoa hồng và trả response tải file .xlsx.
     *
     * @param  string  $requestedMonth  Tháng dạng Y-m ('' = tháng hiện tại)
     * @param  int  $filterSalesId  Lọc theo 1 nhân viên sales (0 = tất cả)
     */
    public function download(string $requestedMonth, int $filterSalesId): Response
    {

        if (! class_exists(Spreadsheet::class)) {
            return response(
                'Server chưa có PhpSpreadsheet. Chạy: composer require phpoffice/phpspreadsheet',
                500
            );
        }

        $user = Auth::user();
        $month = $requestedMonth !== '' ? $requestedMonth : now()->format('Y-m');

        try {
            $periodStart = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        } catch (\Throwable $e) {
            $month = now()->format('Y-m');
            $periodStart = now()->startOfMonth();
        }

        $periodEnd = $periodStart->copy()->endOfMonth();
        $periodFrom = $periodStart->format('Y-m-d 00:00:00');
        $periodTo = $periodEnd->format('Y-m-d 23:59:59');

        $hasTable = fn ($t) => SchemaCache::hasTable($t);
        $hasCol = fn ($t, $c) => $hasTable($t) && SchemaCache::hasColumn($t, $c);

        $firstCol = function ($table, array $cols) use ($hasCol) {
            foreach ($cols as $c) {
                if ($hasCol($table, $c)) {
                    return $c;
                }
            }

            return null;
        };

        $findTable = function (array $candidates) {
            try {
                $tables = collect(DB::select('SHOW TABLES'))
                    ->map(fn ($r) => array_values((array) $r)[0] ?? null)
                    ->filter()
                    ->values();

                foreach ($candidates as $name) {
                    if ($tables->contains($name)) {
                        return $name;
                    }
                }

                foreach ($candidates as $name) {
                    $found = $tables->first(fn ($t) => str_contains(strtolower($t), strtolower($name)));
                    if ($found) {
                        return $found;
                    }
                }
            } catch (\Throwable $e) {
                ProbeFailureLog::warn('SalesCommissionExcelExporter::download', $e);

                //
            }

            return null;
        };

        $orderTable = $hasTable('crm_orders') ? 'crm_orders' : ($hasTable('orders') ? 'orders' : null);

        if (! $orderTable) {
            return response('Không tìm thấy bảng đơn hàng.', 500);
        }

        $orderDateCol = $firstCol($orderTable, ['order_date', 'ordered_at', 'date', 'created_at']);
        $orderTotalCol = $firstCol($orderTable, ['total_amount', 'final_amount', 'grand_total', 'total', 'amount']);
        $orderCodeCol = $firstCol($orderTable, ['order_code', 'code', 'order_no']);
        $orderSalesCol = $firstCol($orderTable, ['created_by', 'sales_id', 'sale_id', 'user_id']);
        $orderCustomerNameCol = $firstCol($orderTable, ['customer_name', 'lead_name', 'name']);
        $orderCustomerIdCol = $firstCol($orderTable, ['customer_id', 'client_id', 'buyer_id']);
        $orderLeadIdCol = $firstCol($orderTable, ['lead_id', 'crm_lead_id']);
        $orderStatusCol = $firstCol($orderTable, ['status', 'order_status', 'current_department']);
        $orderNoteCol = $firstCol($orderTable, ['note', 'notes', 'description']);

        if (! $orderDateCol || ! $orderTotalCol || ! $orderSalesCol) {
            return response('Thiếu cột ngày / tổng tiền / sales trong bảng đơn hàng.', 500);
        }

        $salesUsers = collect();

        try {
            /*
             * Nhận diện nhân sự kinh doanh theo phòng ban / chức danh / vai trò —
             * xem SalesCommissionScope. Chỉ xét vai trò như trước thì danh sách
             * rỗng trên production (0/235 đơn), tức bản Excel cũng rỗng.
             */
            $query = DB::table('users')
                ->select('users.id', 'users.name', 'users.email')
                ->where('users.name', '!=', SalesCommissionScope::EXCLUDED_SALES_NAME);

            SalesCommissionScope::constrainToSalesStaff($query, 'users');

            $salesUsers = $query->orderBy('users.name')->get();
        } catch (\Throwable $e) {
            $salesUsers = DB::table('users')
                ->select('id', 'name', 'email')
                ->where('name', '!=', SalesCommissionScope::EXCLUDED_SALES_NAME)
                ->orderBy('name')
                ->get();
        }

        $validSalesIds = $salesUsers->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();
        $salesMap = $salesUsers->keyBy('id');

        $ordersQ = DB::table($orderTable)
            ->whereBetween($orderDateCol, [$periodFrom, $periodTo]);

        if ($filterSalesId > 0) {
            $ordersQ->where($orderSalesCol, $filterSalesId);
        } elseif (! empty($validSalesIds)) {
            $ordersQ->whereIn($orderSalesCol, $validSalesIds);
        }

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('sales')) {
            $ordersQ->where($orderSalesCol, $user->id);
        }

        $orders = $ordersQ
            ->orderByDesc($orderDateCol)
            ->orderByDesc('id')
            ->get();

        $orderIds = $orders->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();

        $paymentTable = $findTable([
            'crm_order_payments',
            'order_payments',
            'crm_payments',
            'payments',
            'payment_histories',
            'crm_payment_histories',
            'crm_order_payment_histories',
        ]);

        $paymentOrderCol = $paymentTable ? $firstCol($paymentTable, ['order_id', 'crm_order_id']) : null;
        $paymentAmountCol = $paymentTable ? $firstCol($paymentTable, ['amount', 'paid_amount', 'payment_amount', 'money', 'value', 'total']) : null;
        $paymentDateCol = $paymentTable ? $firstCol($paymentTable, ['payment_date', 'paid_at', 'date', 'created_at']) : null;
        $paymentNoteCol = $paymentTable ? $firstCol($paymentTable, ['note', 'notes', 'description']) : null;

        $paymentsByOrder = collect();

        if ($paymentTable && $paymentOrderCol && $paymentAmountCol && ! empty($orderIds)) {
            try {
                $paymentsByOrder = DB::table($paymentTable)
                    ->whereIn($paymentOrderCol, $orderIds)
                    ->get()
                    ->groupBy($paymentOrderCol);
            } catch (\Throwable $e) {
                $paymentsByOrder = collect();
            }
        }

        $itemTable = $hasTable('crm_order_items') ? 'crm_order_items' : ($hasTable('order_items') ? 'order_items' : null);
        $itemOrderCol = $itemTable ? $firstCol($itemTable, ['order_id', 'crm_order_id']) : null;

        $itemsByOrder = collect();

        if ($itemTable && $itemOrderCol && ! empty($orderIds)) {
            try {
                $iq = DB::table($itemTable.' as oi')
                    ->whereIn('oi.'.$itemOrderCol, $orderIds);

                $selects = ['oi.*'];

                if ($hasCol($itemTable, 'product_id') && $hasTable('crm_product_catalog')) {
                    $iq->leftJoin('crm_product_catalog as pc', 'pc.id', '=', 'oi.product_id');

                    foreach ([
                        'name' => 'ego_product_name',
                        'barcode' => 'ego_product_code',
                        'model' => 'ego_product_model',
                        'sku' => 'ego_product_sku',
                        'vat_percent' => 'ego_product_vat_percent',
                    ] as $col => $alias) {
                        if ($hasCol('crm_product_catalog', $col)) {
                            $selects[] = DB::raw('pc.`'.$col.'` as '.$alias);
                        }
                    }
                }

                $itemsByOrder = $iq->get($selects)->groupBy($itemOrderCol);
            } catch (\Throwable $e) {
                ProbeFailureLog::warn('SalesCommissionExcelExporter::download', $e);

                $itemsByOrder = collect();
            }
        }

        $getOrderCode = fn ($o) => $orderCodeCol ? (string) ($o->{$orderCodeCol} ?? ('ORD'.$o->id)) : ('ORD'.$o->id);

        $customerCache = [];
        $leadCache = [];

        $customerInfoForOrder = function ($order) use (
            &$customerCache,
            &$leadCache,
            $orderCustomerNameCol,
            $orderCustomerIdCol,
            $orderLeadIdCol,
            $hasTable,
            $firstCol
        ) {
            $info = (object) [
                'name' => '-',
                'phone' => '',
                'status' => '',
                'email' => '',
                'tax_code' => '',
                'address' => '',
            ];

            if ($orderCustomerNameCol && ! empty($order->{$orderCustomerNameCol})) {
                $info->name = trim((string) $order->{$orderCustomerNameCol});
            }

            $readCustomer = function ($id) use (&$customerCache, $hasTable, $firstCol) {
                $id = (int) $id;
                if ($id <= 0) {
                    return null;
                }

                if (array_key_exists($id, $customerCache)) {
                    return $customerCache[$id];
                }

                foreach (['crm_customers', 'customers', 'clients'] as $table) {
                    if (! $hasTable($table)) {
                        continue;
                    }

                    try {
                        $row = DB::table($table)->where('id', $id)->first();
                        if ($row) {
                            $customerCache[$id] = (object) [
                                'name' => $row->{$firstCol($table, ['name', 'company_name', 'full_name', 'customer_name'])} ?? '-',
                                'phone' => $row->{$firstCol($table, ['phone', 'mobile', 'tel'])} ?? '',
                                'status' => $row->{$firstCol($table, ['customer_status', 'status', 'type', 'customer_type'])} ?? '',
                                'email' => $row->{$firstCol($table, ['email', 'mail'])} ?? '',
                                'tax_code' => $row->{$firstCol($table, ['tax_code', 'mst', 'vat_code'])} ?? '',
                                'address' => $row->{$firstCol($table, ['address', 'billing_address'])} ?? '',
                            ];

                            return $customerCache[$id];
                        }
                    } catch (\Throwable $e) {
                        //
                    }
                }

                $customerCache[$id] = null;

                return null;
            };

            if ($orderCustomerIdCol && ! empty($order->{$orderCustomerIdCol})) {
                $c = $readCustomer($order->{$orderCustomerIdCol});
                if ($c) {
                    return $c;
                }
            }

            if ($orderLeadIdCol && ! empty($order->{$orderLeadIdCol})) {
                $leadId = (int) $order->{$orderLeadIdCol};

                if (! array_key_exists($leadId, $leadCache)) {
                    $leadCache[$leadId] = null;

                    foreach (['crm_leads', 'leads'] as $leadTable) {
                        if (! $hasTable($leadTable)) {
                            continue;
                        }

                        try {
                            $lead = DB::table($leadTable)->where('id', $leadId)->first();

                            if ($lead) {
                                $leadCache[$leadId] = $lead;
                                break;
                            }
                        } catch (\Throwable $e) {
                            //
                        }
                    }
                }

                $lead = $leadCache[$leadId] ?? null;

                if ($lead) {
                    foreach (['customer_id', 'client_id'] as $col) {
                        if (isset($lead->{$col}) && ! empty($lead->{$col})) {
                            $c = $readCustomer($lead->{$col});
                            if ($c) {
                                return $c;
                            }
                        }
                    }

                    foreach (['customer_name', 'company_name', 'name', 'full_name', 'contact_name'] as $col) {
                        if (isset($lead->{$col}) && trim((string) $lead->{$col}) !== '') {
                            $info->name = trim((string) $lead->{$col});
                            break;
                        }
                    }

                    foreach (['phone', 'mobile', 'tel'] as $col) {
                        if (isset($lead->{$col}) && trim((string) $lead->{$col}) !== '') {
                            $info->phone = trim((string) $lead->{$col});
                            break;
                        }
                    }

                    foreach (['customer_status', 'status', 'type', 'source'] as $col) {
                        if (isset($lead->{$col}) && trim((string) $lead->{$col}) !== '') {
                            $info->status = trim((string) $lead->{$col});
                            break;
                        }
                    }
                }
            }

            return $info;
        };

        $lineTotalsForItem = function ($item) {
            $qty = (float) ($item->quantity ?? ($item->qty ?? 0));
            if ($qty <= 0) {
                $qty = 1;
            }

            $unitPrice = (float) ($item->unit_price ?? ($item->price ?? ($item->sale_price ?? 0)));
            $discountAmount = (float) ($item->discount_amount ?? 0);
            $discountPercent = (float) ($item->discount_percent ?? 0);

            $lineAfter = (float) ($item->line_total ?? ($item->total_amount ?? ($item->total ?? ($item->amount ?? 0))));

            if ($lineAfter <= 0) {
                $subtotal = $unitPrice * $qty;
                $discount = $discountAmount > 0 ? $discountAmount : ($subtotal * $discountPercent / 100);
                $lineAfter = max(0, $subtotal - $discount);
            }

            $vatPercent = (float) ($item->vat_percent ?? ($item->vat ?? 0));

            if ($vatPercent <= 0 && isset($item->ego_product_vat_percent)) {
                $vatPercent = (float) $item->ego_product_vat_percent;
            }

            $lineBefore = $vatPercent > 0
                ? $lineAfter / (1 + ($vatPercent / 100))
                : $lineAfter;

            $tax = max(0, $lineAfter - $lineBefore);

            return (object) [
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'vat_percent' => $vatPercent,
                'before_vat' => round($lineBefore, 2),
                'tax' => round($tax, 2),
                'after_vat' => round($lineAfter, 2),
            ];
        };

        $beforeVatForOrder = function ($order, $items) use ($orderTotalCol, $lineTotalsForItem) {
            $sumBefore = 0;
            $sumAfter = 0;

            foreach ($items as $item) {
                $t = $lineTotalsForItem($item);
                $sumBefore += $t->before_vat;
                $sumAfter += $t->after_vat;
            }

            if ($sumBefore > 0) {
                return round($sumBefore, 2);
            }

            $after = (float) ($order->{$orderTotalCol} ?? 0);

            foreach (['tax_amount', 'vat_amount', 'total_vat', 'total_tax'] as $col) {
                if (isset($order->{$col}) && (float) $order->{$col} > 0 && $after >= (float) $order->{$col}) {
                    return round($after - (float) $order->{$col}, 2);
                }
            }

            return round($after, 2);
        };

        $policy = (object) [
            'period_month' => $month,
            'project_rate_percent' => 4,
            'trade_rate_percent' => 1,
            'panel_fixed_amount' => 15000,
            'only_paid' => 1,
            'only_shipped' => 0,
            'only_completed' => 0,
            'hold_if_debt' => 1,
        ];

        if ($hasTable('crm_commission_policies')) {
            try {
                $policyRow = DB::table('crm_commission_policies')
                    ->where('period_month', $month)
                    ->orderByDesc('id')
                    ->first();

                if ($policyRow) {
                    $policy = (object) array_merge((array) $policy, (array) $policyRow);
                }
            } catch (\Throwable $e) {
                //
            }
        }

        $rules = collect();

        if ($hasTable('crm_commission_rules') && isset($policy->id)) {
            try {
                $rules = DB::table('crm_commission_rules')
                    ->where('policy_id', (int) $policy->id)
                    ->where('is_active', 1)
                    ->orderByDesc('priority')
                    ->orderBy('id')
                    ->get();
            } catch (\Throwable $e) {
                $rules = collect();
            }
        }

        /*
         * Điều kiện tính hoa hồng dùng CHUNG với màn hình báo cáo. Trước đây mỗi
         * bên tự viết một bản: bản này đọc bốn công tắc trong chính sách, bản kia
         * bỏ qua cả bốn — nên hai màn hình nói hai con số khác nhau ngay khi kế
         * toán đổi chính sách.
         */
        $matcher = new CommissionRuleMatcher;
        $eligibility = new CommissionEligibilityPolicy(
            OrderColumnMap::discover(new CommissionSchema),
            $policy,
        );

        $orderRows = collect();

        foreach ($orders as $order) {
            $orderId = (int) ($order->id ?? 0);
            $items = collect($itemsByOrder->get($orderId, collect()));
            $payments = collect($paymentsByOrder->get($orderId, collect()));
            $customer = $customerInfoForOrder($order);

            $afterVat = (float) ($order->{$orderTotalCol} ?? 0);
            $beforeVat = (float) $beforeVatForOrder($order, $items);
            $paid = (float) $payments->sum(fn ($p) => (float) ($p->{$paymentAmountCol} ?? 0));
            $debt = max(0, $afterVat - $paid);
            $sid = (int) ($order->{$orderSalesCol} ?? 0);
            $sales = $salesMap->get($sid);

            $quantity = 0;
            $productTexts = [];

            foreach ($items as $item) {
                $t = $lineTotalsForItem($item);
                $quantity += $t->qty;

                foreach (['ego_product_name', 'ego_product_code', 'ego_product_model', 'ego_product_sku', 'product_name', 'name', 'model', 'sku', 'barcode'] as $col) {
                    if (isset($item->{$col}) && trim((string) $item->{$col}) !== '') {
                        $productTexts[] = trim((string) $item->{$col});
                    }
                }
            }

            $statusRaw = $orderStatusCol ? strtolower((string) ($order->{$orderStatusCol} ?? '')) : '';
            $eligible = $eligibility->isEligible($order, $afterVat, $paid);

            $orderRows->push((object) [
                'order' => $order,
                'order_id' => $orderId,
                'order_code' => $getOrderCode($order),
                'order_date' => $order->{$orderDateCol} ?? null,
                'sales_id' => $sid,
                'sales_name' => $sales->name ?? ('Sales #'.$sid),
                'sales_email' => $sales->email ?? '',
                'customer_name' => $customer->name ?? '-',
                'customer_phone' => $customer->phone ?? '',
                'customer_email' => $customer->email ?? '',
                'customer_status' => $customer->status ?? '',
                'customer_tax_code' => $customer->tax_code ?? '',
                'customer_address' => $customer->address ?? '',
                'after_vat' => $afterVat,
                'before_vat' => $beforeVat,
                'paid' => $paid,
                'debt' => $debt,
                'quantity' => $quantity,
                'product_text' => implode(' ', array_unique($productTexts)),
                'eligible' => $eligible,
                'status' => $statusRaw ?: '-',
                'note' => $orderNoteCol ? (string) ($order->{$orderNoteCol} ?? '') : '',
                'commission' => 0,
                'commission_rate' => 0,
                'commission_note' => $eligible ? 'Đủ điều kiện' : 'Chưa đủ điều kiện',
            ]);
        }

        $monthlyRevenueBySales = $orderRows
            ->filter(fn ($r) => $r->eligible)
            ->groupBy('sales_id')
            ->map(fn ($g) => (float) $g->sum('before_vat'));

        /*
         * Việc đọc từng quy tắc dùng CHUNG với màn hình báo cáo qua
         * CommissionRuleMatcher. Phần chọn quy tắc nào thắng vẫn ở đây vì bản
         * Excel còn phải kèm ghi chú nguồn hoa hồng cho kế toán soát lại.
         */
        $calcCommissionForOrder = function ($r) use ($rules, $policy, $monthlyRevenueBySales, $matcher) {
            if (! $r->eligible) {
                return [0, 0, 'Chưa đủ điều kiện'];
            }

            $hasRevenueFloor = fn ($rule) => ($rule->from_amount ?? null) !== null
                && (float) ($rule->from_amount ?? 0) > 0;

            $monthlyBefore = (float) ($monthlyRevenueBySales->get($r->sales_id, 0));
            $anyRevenueFloor = $rules->contains(fn ($rule) => $matcher->isTrade($rule) && $hasRevenueFloor($rule));

            $tradeCommission = null;
            $extraCommission = 0;
            $fallbackCandidates = [];

            foreach ($rules as $rule) {
                $isTrade = $matcher->isTrade($rule);

                if (! $isTrade && ! $matcher->isSolarPanel($rule)) {
                    continue;
                }

                $amount = $matcher->amountFor(
                    $rule,
                    (float) $r->before_vat,
                    (float) $r->after_vat,
                    $monthlyBefore,
                    (float) $r->quantity
                );

                if ($amount === null) {
                    continue;
                }

                $amount = max(0, $amount);
                $matches = $matcher->matchesTarget($rule, (string) $r->customer_status, (string) $r->product_text);

                if (! $isTrade) {
                    if ($matches) {
                        $extraCommission += $amount;
                    }

                    continue;
                }

                if ($matches) {
                    $tradeCommission ??= $amount;

                    continue;
                }

                if (! $anyRevenueFloor || $hasRevenueFloor($rule)) {
                    $fallbackCandidates[] = [
                        'amount' => $amount,
                        'rate' => (float) ($rule->rate_percent ?? 0),
                        'name' => $rule->rule_name ?? ($rule->name ?? 'Fallback rule'),
                    ];
                }
            }

            $note = 'Theo rule';

            if ($tradeCommission === null && ! empty($fallbackCandidates)) {
                usort($fallbackCandidates, fn ($a, $b) => ($a['rate'] <=> $b['rate']));
                $tradeCommission = (float) ($fallbackCandidates[0]['amount'] ?? 0);
                $note = 'Fallback: '.($fallbackCandidates[0]['name'] ?? 'rule doanh thu');
            }

            if ($tradeCommission === null && $rules->isEmpty()) {
                $tradeCommission = (float) $r->before_vat * (float) ($policy->trade_rate_percent ?? 0) / 100;
                $note = 'Theo % mặc định';
            }

            $commission = (float) ($tradeCommission ?? 0) + (float) $extraCommission;
            $rate = $r->before_vat > 0 ? ($commission / $r->before_vat) * 100 : 0;

            return [round($commission), $rate, $note];
        };

        $orderRows = $orderRows->map(function ($r) use ($calcCommissionForOrder) {
            [$commission, $rate, $note] = $calcCommissionForOrder($r);

            $r->commission = $commission;
            $r->commission_rate = $rate;
            $r->commission_note = $r->eligible ? $note : $r->commission_note;

            return $r;
        });

        $summaryRows = $orderRows
            ->groupBy('sales_id')
            ->map(function ($g) {
                $before = (float) $g->sum('before_vat');
                $commission = (float) $g->sum('commission');

                return (object) [
                    'sales_id' => $g->first()->sales_id,
                    'sales_name' => $g->first()->sales_name,
                    'sales_email' => $g->first()->sales_email,
                    'orders' => $g->count(),
                    'after_vat' => (float) $g->sum('after_vat'),
                    'before_vat' => $before,
                    'paid' => (float) $g->sum('paid'),
                    'debt' => (float) $g->sum('debt'),
                    'commission' => $commission,
                    'commission_rate' => $before > 0 ? ($commission / $before) * 100 : 0,
                ];
            })
            ->sortByDesc('commission')
            ->values();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name', 'CRM'))
            ->setTitle('Hoa hồng Sales '.$month)
            ->setSubject('Xuất hoa hồng Sales');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF6FF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];

        $cellStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0891B2'],
            ],
        ];

        $colLetter = fn ($index) => Coordinate::stringFromColumnIndex($index);

        $autoSize = function ($sheet, int $count) use ($colLetter) {
            for ($i = 1; $i <= $count; $i++) {
                $sheet->getColumnDimension($colLetter($i))->setAutoSize(true);
            }
        };

        $writeTitle = function ($sheet, string $title, int $columns) use ($colLetter, $titleStyle, $month) {
            $last = $colLetter($columns);
            $sheet->mergeCells('A1:'.$last.'1');
            $sheet->setCellValue('A1', $title);
            $sheet->getStyle('A1:'.$last.'1')->applyFromArray($titleStyle);
            $sheet->mergeCells('A2:'.$last.'2');
            $sheet->setCellValue('A2', 'Tháng: '.$month.' | Xuất lúc: '.now()->format('d/m/Y H:i'));
        };

        $writeHeader = function ($sheet, array $headers, int $row) use ($headerStyle, $colLetter) {
            $sheet->fromArray($headers, null, 'A'.$row);
            $last = $colLetter(count($headers));
            $sheet->getStyle('A'.$row.':'.$last.$row)->applyFromArray($headerStyle);
            $sheet->freezePane('A'.($row + 1));
            $sheet->setAutoFilter('A'.$row.':'.$last.$row);
        };

        $applyTableStyle = function ($sheet, int $columns, int $startRow, int $endRow) use ($cellStyle, $colLetter) {
            if ($endRow < $startRow) {
                return;
            }

            $sheet->getStyle('A'.$startRow.':'.$colLetter($columns).$endRow)->applyFromArray($cellStyle);
        };

        $moneyFormat = function ($sheet, array $columns, int $startRow, int $endRow) {
            if ($endRow < $startRow) {
                return;
            }

            foreach ($columns as $col) {
                $sheet->getStyle($col.$startRow.':'.$col.$endRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            }
        };

        $rateFormat = function ($sheet, array $columns, int $startRow, int $endRow) {
            if ($endRow < $startRow) {
                return;
            }

            foreach ($columns as $col) {
                $sheet->getStyle($col.$startRow.':'.$col.$endRow)
                    ->getNumberFormat()
                    ->setFormatCode('0.00');
            }
        };

        /*
        |--------------------------------------------------------------------------
        | Sheet 1: Tổng hợp Sales
        |--------------------------------------------------------------------------
        */
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tong hop Sales');

        $headers = [
            'STT',
            'Sales',
            'Email',
            'Số đơn',
            'Doanh thu sau VAT',
            'Doanh thu trước VAT',
            'Đã thu',
            'Công nợ',
            'Hoa hồng',
            '% HH TB',
        ];

        $writeTitle($sheet, 'TỔNG HỢP HOA HỒNG SALES', count($headers));
        $writeHeader($sheet, $headers, 4);

        $r = 5;

        foreach ($summaryRows as $i => $row) {
            $sheet->fromArray([
                $i + 1,
                $row->sales_name,
                $row->sales_email,
                $row->orders,
                round($row->after_vat),
                round($row->before_vat),
                round($row->paid),
                round($row->debt),
                round($row->commission),
                round($row->commission_rate, 4),
            ], null, 'A'.$r);

            $r++;
        }

        $applyTableStyle($sheet, count($headers), 4, $r - 1);
        $moneyFormat($sheet, ['E', 'F', 'G', 'H', 'I'], 5, $r - 1);
        $rateFormat($sheet, ['J'], 5, $r - 1);
        $autoSize($sheet, count($headers));

        /*
        |--------------------------------------------------------------------------
        | Sheet 2: Hoa hồng đơn
        |--------------------------------------------------------------------------
        */
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Hoa hong don');

        $headers = [
            'STT',
            'Mã đơn',
            'Ngày',
            'Sales',
            'Khách hàng',
            'Trạng thái khách',
            'Tổng sau VAT',
            'Trước VAT tính HH',
            'Đã thu',
            'Còn nợ',
            '% HH',
            'Hoa hồng',
            'Điều kiện',
            'Ghi chú tính',
        ];

        $writeTitle($sheet, 'CHI TIẾT HOA HỒNG THEO ĐƠN', count($headers));
        $writeHeader($sheet, $headers, 4);

        $r = 5;

        foreach ($orderRows as $i => $row) {
            $sheet->fromArray([
                $i + 1,
                $row->order_code,
                $row->order_date ? Carbon::parse($row->order_date)->format('d/m/Y') : '',
                $row->sales_name,
                $row->customer_name,
                $row->customer_status,
                round($row->after_vat),
                round($row->before_vat),
                round($row->paid),
                round($row->debt),
                round($row->commission_rate, 4),
                round($row->commission),
                $row->eligible ? 'Đủ điều kiện' : 'Chưa tính',
                $row->commission_note,
            ], null, 'A'.$r);

            $r++;
        }

        $applyTableStyle($sheet, count($headers), 4, $r - 1);
        $moneyFormat($sheet, ['G', 'H', 'I', 'J', 'L'], 5, $r - 1);
        $rateFormat($sheet, ['K'], 5, $r - 1);
        $autoSize($sheet, count($headers));

        /*
        |--------------------------------------------------------------------------
        | Sheet 3: Thông tin đơn hàng
        |--------------------------------------------------------------------------
        */
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Thong tin don hang');

        $headers = [
            'STT',
            'Mã đơn',
            'Ngày',
            'Sales',
            'Khách hàng',
            'SĐT',
            'Email',
            'MST',
            'Địa chỉ',
            'Tổng sau VAT',
            'Trước VAT',
            'Đã thu',
            'Còn nợ',
            'Trạng thái đơn',
            'Ghi chú đơn',
        ];

        $writeTitle($sheet, 'THÔNG TIN ĐƠN HÀNG', count($headers));
        $writeHeader($sheet, $headers, 4);

        $r = 5;

        foreach ($orderRows as $i => $row) {
            $sheet->fromArray([
                $i + 1,
                $row->order_code,
                $row->order_date ? Carbon::parse($row->order_date)->format('d/m/Y') : '',
                $row->sales_name,
                $row->customer_name,
                $row->customer_phone,
                $row->customer_email,
                $row->customer_tax_code,
                $row->customer_address,
                round($row->after_vat),
                round($row->before_vat),
                round($row->paid),
                round($row->debt),
                $row->status,
                $row->note,
            ], null, 'A'.$r);

            $r++;
        }

        $applyTableStyle($sheet, count($headers), 4, $r - 1);
        $moneyFormat($sheet, ['J', 'K', 'L', 'M'], 5, $r - 1);
        $autoSize($sheet, count($headers));

        /*
        |--------------------------------------------------------------------------
        | Sheet 4: Sản phẩm trong đơn
        |--------------------------------------------------------------------------
        */
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('San pham trong don');

        $headers = [
            'STT',
            'Mã đơn',
            'Ngày',
            'Sales',
            'Khách hàng',
            'Mã SP',
            'Tên sản phẩm',
            'Model/SKU',
            'Số lượng',
            'Đơn giá',
            'VAT %',
            'Trước VAT',
            'VAT',
            'Sau VAT',
        ];

        $writeTitle($sheet, 'SẢN PHẨM TRONG ĐƠN', count($headers));
        $writeHeader($sheet, $headers, 4);

        $r = 5;
        $stt = 1;

        foreach ($orderRows as $row) {
            $items = collect($itemsByOrder->get($row->order_id, collect()));

            if ($items->isEmpty()) {
                $sheet->fromArray([
                    $stt++,
                    $row->order_code,
                    $row->order_date ? Carbon::parse($row->order_date)->format('d/m/Y') : '',
                    $row->sales_name,
                    $row->customer_name,
                    '',
                    'Không có sản phẩm',
                    '',
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                ], null, 'A'.$r);

                $r++;

                continue;
            }

            foreach ($items as $item) {
                $t = $lineTotalsForItem($item);

                $sheet->fromArray([
                    $stt++,
                    $row->order_code,
                    $row->order_date ? Carbon::parse($row->order_date)->format('d/m/Y') : '',
                    $row->sales_name,
                    $row->customer_name,
                    $item->ego_product_code ?? ($item->sku ?? ($item->barcode ?? '')),
                    $item->ego_product_name ?? ($item->product_name ?? ($item->name ?? '')),
                    $item->ego_product_model ?? ($item->ego_product_sku ?? ($item->model ?? ($item->sku ?? ''))),
                    $t->qty,
                    round($t->unit_price),
                    round($t->vat_percent, 2),
                    round($t->before_vat),
                    round($t->tax),
                    round($t->after_vat),
                ], null, 'A'.$r);

                $r++;
            }
        }

        $applyTableStyle($sheet, count($headers), 4, $r - 1);
        $moneyFormat($sheet, ['J', 'L', 'M', 'N'], 5, $r - 1);
        $rateFormat($sheet, ['K'], 5, $r - 1);
        $autoSize($sheet, count($headers));

        /*
        |--------------------------------------------------------------------------
        | Sheet 5: Thanh toán
        |--------------------------------------------------------------------------
        */
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Thanh toan');

        $headers = [
            'STT',
            'Mã đơn',
            'Ngày đơn',
            'Sales',
            'Khách hàng',
            'Ngày thanh toán',
            'Số tiền',
            'Ghi chú',
        ];

        $writeTitle($sheet, 'LỊCH SỬ THANH TOÁN', count($headers));
        $writeHeader($sheet, $headers, 4);

        $r = 5;
        $stt = 1;

        foreach ($orderRows as $row) {
            $payments = collect($paymentsByOrder->get($row->order_id, collect()));

            if ($payments->isEmpty()) {
                $sheet->fromArray([
                    $stt++,
                    $row->order_code,
                    $row->order_date ? Carbon::parse($row->order_date)->format('d/m/Y') : '',
                    $row->sales_name,
                    $row->customer_name,
                    '',
                    0,
                    'Chưa có thanh toán',
                ], null, 'A'.$r);

                $r++;

                continue;
            }

            foreach ($payments as $p) {
                $sheet->fromArray([
                    $stt++,
                    $row->order_code,
                    $row->order_date ? Carbon::parse($row->order_date)->format('d/m/Y') : '',
                    $row->sales_name,
                    $row->customer_name,
                    $paymentDateCol && ! empty($p->{$paymentDateCol}) ? Carbon::parse($p->{$paymentDateCol})->format('d/m/Y') : '',
                    round((float) ($p->{$paymentAmountCol} ?? 0)),
                    $paymentNoteCol ? (string) ($p->{$paymentNoteCol} ?? '') : '',
                ], null, 'A'.$r);

                $r++;
            }
        }

        $applyTableStyle($sheet, count($headers), 4, $r - 1);
        $moneyFormat($sheet, ['G'], 5, $r - 1);
        $autoSize($sheet, count($headers));

        /*
        |--------------------------------------------------------------------------
        | Sheet 6: Chính sách
        |--------------------------------------------------------------------------
        */
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Chinh sach');

        $headers = [
            'STT',
            'Nhóm HH',
            'Tên rule',
            'Áp dụng',
            'Target text',
            'Mốc từ',
            'Mốc đến',
            'Cách tính',
            'Base',
            'Tỉ lệ %',
            'Cố định',
            'Ưu tiên',
            'Trạng thái',
        ];

        $writeTitle($sheet, 'CHÍNH SÁCH HOA HỒNG ÁP DỤNG', count($headers));
        $writeHeader($sheet, $headers, 4);

        $r = 5;

        if ($rules->isEmpty()) {
            $sheet->fromArray([
                1,
                'Mặc định',
                'Thương mại mặc định',
                'Tất cả',
                '',
                '',
                '',
                'percent',
                'revenue_before_vat',
                (float) ($policy->trade_rate_percent ?? 0),
                '',
                '',
                'Đang dùng',
            ], null, 'A'.$r);
            $r++;
        } else {
            foreach ($rules as $i => $rule) {
                $sheet->fromArray([
                    $i + 1,
                    $rule->commission_type ?? '',
                    $rule->rule_name ?? ($rule->name ?? ''),
                    $rule->target_type ?? '',
                    $rule->target_text ?? '',
                    $rule->from_amount ?? '',
                    $rule->to_amount ?? '',
                    $rule->calculation_type ?? '',
                    $rule->base_type ?? '',
                    $rule->rate_percent ?? '',
                    $rule->fixed_amount ?? ($rule->amount_per_unit ?? ''),
                    $rule->priority ?? '',
                    ((int) ($rule->is_active ?? 0) === 1) ? 'Bật' : 'Tắt',
                ], null, 'A'.$r);

                $r++;
            }
        }

        $applyTableStyle($sheet, count($headers), 4, $r - 1);
        $moneyFormat($sheet, ['F', 'G', 'K'], 5, $r - 1);
        $rateFormat($sheet, ['J'], 5, $r - 1);
        $autoSize($sheet, count($headers));

        $spreadsheet->setActiveSheetIndex(0);

        /* EGO_EXPORT_EMPLOYEE_FILENAME_START */
        $exportSalesPart = 'tat-ca';

        if ($filterSalesId > 0 && isset($salesMap) && $salesMap->has($filterSalesId)) {
            $exportSalesPart = Str::slug((string) ($salesMap->get($filterSalesId)->name ?? ('sales-'.$filterSalesId)));
        }

        $fileName = 'hoa-hong-sales-'.$exportSalesPart.'-'.$month.'-'.now()->format('Ymd-His').'.xlsx';
        /* EGO_EXPORT_EMPLOYEE_FILENAME_END */

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
