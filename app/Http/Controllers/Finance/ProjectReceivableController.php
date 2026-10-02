<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Projects\SiteQuoteLookup;
use App\Support\SchemaCache;
use App\View\Presenters\Finance\ProjectReceivableListPresenter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phải thu công trình lấy trực tiếp từ module Dự án/Công trình (sites),
 * không lấy từ đơn hàng CRM.
 *
 * Hỗ trợ song song dữ liệu cũ:
 * - receipts.site_id / receipts.site_payment_term_id
 * và dữ liệu mới:
 * - project_payment_records (mỗi record mới đồng bộ sang receipts).
 *
 * Khi cộng tiền phải loại receipt đã được project_payment_records trỏ tới
 * để không bị tính hai lần.
 */
class ProjectReceivableController extends Controller
{
    public function index(Request $request, ProjectReceivableListPresenter $presenter)
    {
        abort_unless(SchemaCache::hasTable('sites'), 503, 'Chưa có bảng dự án/công trình.');

        $keyword = trim((string) $request->input('keyword', ''));
        $status = trim((string) $request->input('status', ''));
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $companyId = (int) $request->input('company_id', 0);
        if ($companyId <= 0) {
            foreach (['ego_company_id', 'active_company_id', 'selected_company_id'] as $sessionKey) {
                $sessionCompanyId = (int) session($sessionKey, 0);
                if ($sessionCompanyId > 0) {
                    $companyId = $sessionCompanyId;
                    break;
                }
            }
        }

        // Hai cột báo giá lấy từ bản mới nhất trong site_quotes; alias giữ tên quen để phần tính
        // toán bên dưới (contractAmount, customerName) đọc như một cột của dòng.
        $query = DB::table('sites as s')->select('s.*');
        if (SchemaCache::hasTable('site_quotes')) {
            $query->selectRaw(SiteQuoteLookup::latest('customer_company', 's').' as quote_customer_company');
            $query->selectRaw(SiteQuoteLookup::latest('grand_total', 's').' as quote_grand_total');
        }

        if ($companyId > 0 && SchemaCache::hasColumn('sites', 'company_id')) {
            $query->where('s.company_id', $companyId);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                foreach (['project_code', 'name', 'contact_name', 'contact_phone', 'address'] as $column) {
                    if (SchemaCache::hasColumn('sites', $column)) {
                        $method = $column === 'project_code' ? 'where' : 'orWhere';
                        $q->{$method}('s.'.$column, 'like', '%'.$keyword.'%');
                    }
                }
                if (SchemaCache::hasTable('site_quotes')) {
                    $q->orWhereRaw(SiteQuoteLookup::latest('customer_company', 's').' like ?', ['%'.$keyword.'%']);
                }
            });
        }

        if ($fromDate) {
            $dateColumn = SchemaCache::hasColumn('sites', 'contract_signed_at') ? 'contract_signed_at' : 'created_at';
            $query->whereDate('s.'.$dateColumn, '>=', $fromDate);
        }

        if ($toDate) {
            $dateColumn = SchemaCache::hasColumn('sites', 'contract_signed_at') ? 'contract_signed_at' : 'created_at';
            $query->whereDate('s.'.$dateColumn, '<=', $toDate);
        }

        $sites = $query
            ->orderByDesc('s.id')
            ->get();

        $companies = SchemaCache::hasTable('companies')
            ? DB::table('companies')->select('id', 'name')->orderBy('name')->get()
            : collect();

        $siteIds = $sites->pluck('id')->map(fn ($id) => (int) $id)->filter()->values();
        $payments = $this->unifiedPayments($siteIds);
        $terms = $this->paymentTerms($siteIds);

        $today = now()->toDateString();

        $rows = $sites->map(function ($site) use ($payments, $terms, $today) {
            $sitePayments = $payments->get((int) $site->id, collect());
            $siteTerms = $terms->get((int) $site->id, collect());

            $contract = $this->contractAmount($site);
            $received = (float) $sitePayments->sum('amount');
            $receivable = max(0, $contract - $received);

            $validTermIds = $siteTerms->pluck('id')->map(fn ($id) => (int) $id)->all();
            $termPaid = $sitePayments
                ->filter(fn ($row) => ! empty($row->payment_term_id) && in_array((int) $row->payment_term_id, $validTermIds, true))
                ->groupBy('payment_term_id')
                ->map(fn ($items) => (float) $items->sum('amount'));

            // Một số phiếu thu lịch sử chưa gắn payment_term_id hoặc đang trỏ tới id đợt cũ
            // đã bị tạo lại. Coi các khoản đó là chưa phân bổ và phân bổ FIFO vào đợt hiện tại.
            $unallocatedReceived = (float) $sitePayments
                ->filter(fn ($row) => empty($row->payment_term_id) || ! in_array((int) $row->payment_term_id, $validTermIds, true))
                ->sum('amount');

            $overdue = 0.0;
            $nextTerm = null;

            foreach ($siteTerms as $term) {
                $paid = (float) ($termPaid[(int) $term->id] ?? 0);
                $rawRemaining = max(0, (float) $term->amount - $paid);
                $fifoApplied = min($rawRemaining, $unallocatedReceived);
                $paid += $fifoApplied;
                $unallocatedReceived -= $fifoApplied;
                $remaining = max(0, (float) $term->amount - $paid);

                $term->finance_paid_amount = $paid;
                $term->finance_remaining_amount = $remaining;

                if ($remaining <= 0) {
                    continue;
                }

                if ($term->due_date && $term->due_date < $today) {
                    $overdue += $remaining;
                }

                if ($nextTerm === null) {
                    $nextTerm = $term;
                } elseif ($term->due_date && (! $nextTerm->due_date || $term->due_date < $nextTerm->due_date)) {
                    $nextTerm = $term;
                }
            }

            // Không để công nợ theo từng đợt lịch sử vượt quá số còn phải thu của hợp đồng.
            // Một số dữ liệu cũ đã thu đủ nhưng mapping payment_term chưa đầy đủ.
            $overdue = min($overdue, $receivable);
            if ($receivable <= 0.5) {
                $nextTerm = null;
            } elseif ($nextTerm && isset($nextTerm->finance_remaining_amount)) {
                $nextTerm->finance_remaining_amount = min((float) $nextTerm->finance_remaining_amount, $receivable);
            }

            $overpaid = max(0, $received - $contract);
            $financeStatus = 'unpaid';
            if ($contract > 0 && $overpaid > 1000) {
                $financeStatus = 'overpaid';
            } elseif ($contract > 0 && $receivable <= 0.5) {
                $financeStatus = 'paid';
            } elseif ($overdue > 0.5) {
                $financeStatus = 'overdue';
            } elseif ($received > 0) {
                $financeStatus = 'partial';
            }

            $site->finance_contract_amount = $contract;
            $site->finance_received_amount = $received;
            $site->finance_receivable_amount = $receivable;
            $site->finance_overdue_amount = $overdue;
            $site->finance_overpaid_amount = $overpaid;
            $site->finance_status = $financeStatus;
            $site->finance_terms_count = $siteTerms->count();
            $site->finance_next_term = $nextTerm;
            $site->finance_payments_count = $sitePayments->count();
            $site->finance_customer_name = $this->customerName($site);

            return $site;
        });

        if (in_array($status, ['unpaid', 'partial', 'overdue', 'paid', 'overpaid'], true)) {
            $rows = $rows->where('finance_status', $status)->values();
        }

        $summary = [
            'project_count' => $rows->count(),
            'contract_amount' => (float) $rows->sum('finance_contract_amount'),
            'received_amount' => (float) $rows->sum('finance_received_amount'),
            'receivable_amount' => (float) $rows->sum('finance_receivable_amount'),
            'overdue_amount' => (float) $rows->sum('finance_overdue_amount'),
            'overpaid_amount' => (float) $rows->sum('finance_overpaid_amount'),
        ];

        $perPage = 20;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pageRows = $rows->slice(($page - 1) * $perPage, $perPage)->values();
        $projects = new LengthAwarePaginator(
            $pageRows,
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('finance.project-receivables.index', array_merge(compact(
            'projects',
            'summary',
            'keyword',
            'status',
            'fromDate',
            'toDate',
            'companyId',
            'companies'
        ), $presenter->viewData(
            projects: $projects,
            summary: $summary,
        )));
    }

    private function contractAmount($site): float
    {
        foreach (['contract_amount_after_vat', 'contract_amount', 'quote_grand_total'] as $column) {
            if (isset($site->{$column}) && (float) $site->{$column} > 0) {
                return (float) $site->{$column};
            }
        }

        return 0.0;
    }

    private function customerName($site): string
    {
        foreach (['quote_customer_company', 'contact_name', 'name'] as $field) {
            $value = trim((string) ($site->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return 'Chưa xác định';
    }

    /**
     * @return Collection<int, Collection<int, object>> keyed by site_id
     */
    private function unifiedPayments(Collection $siteIds): Collection
    {
        if ($siteIds->isEmpty()) {
            return collect();
        }

        $rows = collect();
        $linkedReceiptIds = collect();

        if (SchemaCache::hasTable('project_payment_records')) {
            $recordQuery = DB::table('project_payment_records as p')
                ->whereIn('p.site_id', $siteIds->all());

            if (SchemaCache::hasColumn('project_payment_records', 'status')) {
                $recordQuery->where('p.status', 'CONFIRMED');
            }

            $records = $recordQuery
                ->select([
                    'p.id', 'p.site_id', 'p.payment_term_id', 'p.receipt_id', 'p.amount',
                    'p.paid_at', 'p.payment_method', 'p.account_id', 'p.transaction_reference',
                    'p.payer_name', 'p.note', 'p.attachment_path', 'p.created_by', 'p.status',
                ])
                ->get()
                ->map(function ($row) {
                    $row->finance_source = 'project_payment_record';

                    return $row;
                });

            $rows = $rows->concat($records);
            $linkedReceiptIds = $records->pluck('receipt_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        }

        if (SchemaCache::hasTable('receipts') && SchemaCache::hasColumn('receipts', 'site_id')) {
            $receiptQuery = DB::table('receipts as r')
                ->whereIn('r.site_id', $siteIds->all());

            if ($linkedReceiptIds->isNotEmpty()) {
                $receiptQuery->whereNotIn('r.id', $linkedReceiptIds->all());
            }

            $receipts = $receiptQuery
                ->select([
                    'r.id', 'r.site_id',
                    DB::raw((SchemaCache::hasColumn('receipts', 'site_payment_term_id') ? 'r.site_payment_term_id' : 'NULL').' as payment_term_id'),
                    DB::raw('r.id as receipt_id'),
                    'r.amount',
                    DB::raw('r.receipt_date as paid_at'),
                    'r.payment_method', 'r.account_id',
                    DB::raw((SchemaCache::hasColumn('receipts', 'transaction_reference') ? 'r.transaction_reference' : 'NULL').' as transaction_reference'),
                    'r.payer_name', 'r.note',
                    DB::raw((SchemaCache::hasColumn('receipts', 'attachment_path') ? 'r.attachment_path' : 'NULL').' as attachment_path'),
                    'r.created_by',
                    DB::raw("'CONFIRMED' as status"),
                ])
                ->get()
                ->map(function ($row) {
                    $row->finance_source = 'legacy_receipt';

                    return $row;
                });

            $rows = $rows->concat($receipts);
        }

        return $rows
            ->sortByDesc(fn ($row) => (string) $row->paid_at.'-'.str_pad((string) $row->id, 12, '0', STR_PAD_LEFT))
            ->groupBy(fn ($row) => (int) $row->site_id);
    }

    /**
     * @return Collection<int, Collection<int, object>> keyed by site_id
     */
    private function paymentTerms(Collection $siteIds): Collection
    {
        if ($siteIds->isEmpty() || ! SchemaCache::hasTable('site_payment_terms')) {
            return collect();
        }

        return DB::table('site_payment_terms')
            ->whereIn('site_id', $siteIds->all())
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => (int) $row->site_id);
    }
}
