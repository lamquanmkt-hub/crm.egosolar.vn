<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sổ công nợ mở rộng cho các nhóm chưa có module nguồn riêng.
 *
 * - Nợ phải thu CRM (công trình / khách vãng lai / đại lý / dự án) dùng
 *   CustomerDebtController để tận dụng dữ liệu đơn hàng thật.
 * - Công nợ NCC trong nước dùng SupplierDebtController hiện hữu.
 * - Controller này quản lý các nhóm phải trả còn lại: nhập khẩu, ngân hàng,
 *   nợ vay và cổ đông.
 */
class FinanceLedgerController extends Controller
{
    private const CATEGORIES = [
        'payable' => [
            'import_goods' => [
                'title' => 'Phải trả hàng nhập khẩu',
                'counterparty' => 'Nhà cung cấp / đối tác nhập khẩu',
                'description' => 'Theo dõi công nợ hàng nhập khẩu, hạn thanh toán, số đã chi và số dư còn phải trả.',
                'icon' => 'bi-box-arrow-in-down',
            ],
            'bank' => [
                'title' => 'Phải trả ngân hàng',
                'counterparty' => 'Ngân hàng / tổ chức tín dụng',
                'description' => 'Theo dõi nghĩa vụ phải trả ngân hàng theo chứng từ, kỳ hạn và lịch sử thanh toán.',
                'icon' => 'bi-bank',
            ],
            'loan' => [
                'title' => 'Phải trả nợ vay',
                'counterparty' => 'Bên cho vay',
                'description' => 'Quản lý các khoản vay, kỳ hạn, số đã trả và dư nợ còn lại.',
                'icon' => 'bi-currency-dollar',
            ],
            'shareholder' => [
                'title' => 'Phải trả cổ đông',
                'counterparty' => 'Cổ đông / thành viên góp vốn',
                'description' => 'Theo dõi các khoản phải trả cổ đông, hoàn vốn, tạm ứng và nghĩa vụ liên quan.',
                'icon' => 'bi-people',
            ],
        ],
    ];

    public function index(Request $request, string $direction, string $category)
    {
        $config = $this->categoryConfig($direction, $category);
        $schemaReady = Schema::hasTable('finance_ledger_entries')
            && Schema::hasTable('finance_ledger_transactions');

        $filters = [
            'keyword' => trim((string) $request->input('keyword')),
            'status' => (string) $request->input('status', ''),
            'from_date' => (string) $request->input('from_date', ''),
            'to_date' => (string) $request->input('to_date', ''),
        ];

        $entries = collect();
        $transactionsByEntry = collect();
        $summary = [
            'total_amount' => 0.0,
            'paid_amount' => 0.0,
            'remain_amount' => 0.0,
            'overdue_amount' => 0.0,
            'count' => 0,
        ];

        if ($schemaReady) {
            $this->refreshStatuses($direction, $category);

            $base = $this->filteredQuery($request, $direction, $category);

            $summaryRow = (clone $base)
                ->selectRaw('COUNT(*) as total_count')
                ->selectRaw('COALESCE(SUM(total_amount), 0) as total_amount')
                ->selectRaw('COALESCE(SUM(paid_amount), 0) as paid_amount')
                ->selectRaw('COALESCE(SUM(GREATEST(total_amount - paid_amount, 0)), 0) as remain_amount')
                ->selectRaw("COALESCE(SUM(CASE WHEN status = 'overdue' THEN GREATEST(total_amount - paid_amount, 0) ELSE 0 END), 0) as overdue_amount")
                ->first();

            $summary = [
                'total_amount' => (float) ($summaryRow->total_amount ?? 0),
                'paid_amount' => (float) ($summaryRow->paid_amount ?? 0),
                'remain_amount' => (float) ($summaryRow->remain_amount ?? 0),
                'overdue_amount' => (float) ($summaryRow->overdue_amount ?? 0),
                'count' => (int) ($summaryRow->total_count ?? 0),
            ];

            $entries = (clone $base)
                ->orderByRaw('CASE WHEN status = ? THEN 0 WHEN status = ? THEN 1 ELSE 2 END', ['overdue', 'partial'])
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->paginate(20)
                ->appends($request->query());

            $ids = $entries->getCollection()->pluck('id')->all();

            if ($ids) {
                $transactionQuery = DB::table('finance_ledger_transactions as t')
                    ->whereIn('t.finance_ledger_entry_id', $ids);

                if (Schema::hasTable('accounts')) {
                    $transactionQuery
                        ->leftJoin('accounts as a', 'a.id', '=', 't.account_id')
                        ->select('t.*', 'a.name as account_name', 'a.type as account_type');
                } else {
                    $transactionQuery
                        ->select('t.*')
                        ->selectRaw('NULL as account_name, NULL as account_type');
                }

                $transactionsByEntry = $transactionQuery
                    ->orderByDesc('t.transaction_date')
                    ->orderByDesc('t.id')
                    ->get()
                    ->groupBy('finance_ledger_entry_id');
            }
        }

        $accounts = Schema::hasTable('accounts')
            ? DB::table('accounts')->where('is_active', 1)->orderBy('type')->orderBy('name')->get()
            : collect();

        return view('finance.ledger.index', compact(
            'direction',
            'category',
            'config',
            'schemaReady',
            'filters',
            'entries',
            'transactionsByEntry',
            'summary',
            'accounts'
        ));
    }

    public function store(Request $request, string $direction, string $category)
    {
        $this->categoryConfig($direction, $category);
        $this->requireSchema();
        $this->normalizeMoneyFields($request, ['total_amount', 'paid_amount']);

        $data = $request->validate([
            'counterparty_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'reference_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'total_amount' => ['required', 'numeric', 'min:1'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'bank_info' => ['nullable', 'string', 'max:3000'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $paid = (float) ($data['paid_amount'] ?? 0);
        $total = (float) $data['total_amount'];

        if ($paid > $total) {
            return back()->withInput()->withErrors([
                'paid_amount' => 'Số đã thanh toán ban đầu không được lớn hơn tổng công nợ.',
            ]);
        }

        $id = DB::table('finance_ledger_entries')->insertGetId([
            'direction' => $direction,
            'category' => $category,
            'counterparty_name' => trim($data['counterparty_name']),
            'company_name' => $data['company_name'] ? trim($data['company_name']) : null,
            'reference_no' => $data['reference_no'] ? trim($data['reference_no']) : null,
            'reference_date' => $data['reference_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'bank_info' => $data['bank_info'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => $this->statusFor($total, $paid, $data['due_date'] ?? null),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($paid > 0) {
            DB::table('finance_ledger_transactions')->insert([
                'finance_ledger_entry_id' => $id,
                'amount' => $paid,
                'transaction_date' => $data['reference_date'] ?? now()->toDateString(),
                'method' => 'opening',
                'reference_no' => $data['reference_no'] ?? null,
                'note' => 'Số đã thanh toán ghi nhận khi tạo công nợ.',
                'account_id' => null,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Đã thêm công nợ '.$this->categoryConfig($direction, $category)['title'].'.');
    }

    public function update(Request $request, string $direction, string $category, int $entry)
    {
        $this->categoryConfig($direction, $category);
        $this->requireSchema();
        $item = $this->entryOrFail($direction, $category, $entry);
        $this->normalizeMoneyFields($request, ['total_amount']);

        $data = $request->validate([
            'counterparty_name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'reference_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'total_amount' => ['required', 'numeric', 'min:1'],
            'bank_info' => ['nullable', 'string', 'max:3000'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $total = (float) $data['total_amount'];
        $paid = (float) $item->paid_amount;

        if ($total < $paid) {
            return back()->withInput()->withErrors([
                'total_amount' => 'Tổng công nợ không được nhỏ hơn số đã thanh toán '.number_format($paid, 0, ',', '.').' đ.',
            ]);
        }

        DB::table('finance_ledger_entries')->where('id', $entry)->update([
            'counterparty_name' => trim($data['counterparty_name']),
            'company_name' => $data['company_name'] ? trim($data['company_name']) : null,
            'reference_no' => $data['reference_no'] ? trim($data['reference_no']) : null,
            'reference_date' => $data['reference_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'total_amount' => $total,
            'bank_info' => $data['bank_info'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => $this->statusFor($total, $paid, $data['due_date'] ?? null),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Đã cập nhật công nợ.');
    }

    public function destroy(string $direction, string $category, int $entry)
    {
        $this->categoryConfig($direction, $category);
        $this->requireSchema();
        $this->entryOrFail($direction, $category, $entry);

        DB::transaction(function () use ($entry) {
            DB::table('finance_ledger_transactions')->where('finance_ledger_entry_id', $entry)->delete();
            DB::table('finance_ledger_entries')->where('id', $entry)->delete();
        });

        return back()->with('success', 'Đã xóa công nợ và lịch sử thanh toán liên quan.');
    }

    public function storeTransaction(Request $request, string $direction, string $category, int $entry)
    {
        $this->categoryConfig($direction, $category);
        $this->requireSchema();
        $item = $this->entryOrFail($direction, $category, $entry);
        $this->normalizeMoneyFields($request, ['amount']);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_date' => ['required', 'date'],
            'method' => ['required', Rule::in(['bank', 'cash', 'other'])],
            'account_id' => ['nullable', 'integer'],
            'reference_no' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:3000'],
        ]);

        $remaining = max((float) $item->total_amount - (float) $item->paid_amount, 0);
        $amount = (float) $data['amount'];

        if ($amount > $remaining) {
            return back()->withInput()->withErrors([
                'amount' => 'Số tiền vượt quá dư nợ còn lại '.number_format($remaining, 0, ',', '.').' đ.',
            ]);
        }

        $accountId = ! empty($data['account_id']) && Schema::hasTable('accounts')
            && DB::table('accounts')->where('id', $data['account_id'])->where('is_active', 1)->exists()
                ? (int) $data['account_id']
                : null;

        DB::transaction(function () use ($entry, $item, $data, $amount, $accountId) {
            DB::table('finance_ledger_transactions')->insert([
                'finance_ledger_entry_id' => $entry,
                'amount' => $amount,
                'transaction_date' => $data['transaction_date'],
                'method' => $data['method'],
                'account_id' => $accountId,
                'reference_no' => ! empty($data['reference_no']) ? trim($data['reference_no']) : null,
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $newPaid = min((float) $item->total_amount, (float) $item->paid_amount + $amount);

            DB::table('finance_ledger_entries')->where('id', $entry)->update([
                'paid_amount' => $newPaid,
                'status' => $this->statusFor((float) $item->total_amount, $newPaid, $item->due_date),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', $direction === 'receivable' ? 'Đã ghi nhận khoản thu.' : 'Đã ghi nhận khoản chi.');
    }

    public function destroyTransaction(string $direction, string $category, int $entry, int $transaction)
    {
        $this->categoryConfig($direction, $category);
        $this->requireSchema();
        $item = $this->entryOrFail($direction, $category, $entry);

        $tx = DB::table('finance_ledger_transactions')
            ->where('id', $transaction)
            ->where('finance_ledger_entry_id', $entry)
            ->first();

        abort_unless($tx, 404);

        DB::transaction(function () use ($entry, $transaction, $item, $tx) {
            DB::table('finance_ledger_transactions')->where('id', $transaction)->delete();
            $newPaid = max((float) $item->paid_amount - (float) $tx->amount, 0);

            DB::table('finance_ledger_entries')->where('id', $entry)->update([
                'paid_amount' => $newPaid,
                'status' => $this->statusFor((float) $item->total_amount, $newPaid, $item->due_date),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Đã xóa lần ghi nhận thanh toán.');
    }

    public function export(Request $request, string $direction, string $category): StreamedResponse
    {
        $config = $this->categoryConfig($direction, $category);
        $this->requireSchema();
        $rows = $this->filteredQuery($request, $direction, $category)->orderByDesc('id')->get();
        $fileName = 'cong-no-'.$category.'-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows, $config) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [$config['title']]);
            fputcsv($out, ['Đối tượng', 'Công ty', 'Chứng từ', 'Ngày chứng từ', 'Hạn thanh toán', 'Tổng công nợ', 'Đã thanh toán', 'Còn lại', 'Trạng thái', 'Thông tin NH', 'Ghi chú']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->counterparty_name,
                    $row->company_name,
                    $row->reference_no,
                    $row->reference_date,
                    $row->due_date,
                    (float) $row->total_amount,
                    (float) $row->paid_amount,
                    max((float) $row->total_amount - (float) $row->paid_amount, 0),
                    $row->status,
                    $row->bank_info,
                    $row->note,
                ]);
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function categoryConfig(string $direction, string $category): array
    {
        abort_unless(isset(self::CATEGORIES[$direction][$category]), 404);

        return self::CATEGORIES[$direction][$category] + [
            'direction' => $direction,
            'category' => $category,
            'is_receivable' => $direction === 'receivable',
        ];
    }

    private function filteredQuery(Request $request, string $direction, string $category)
    {
        $query = DB::table('finance_ledger_entries')
            ->where('direction', $direction)
            ->where('category', $category);

        $keyword = trim((string) $request->input('keyword'));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('counterparty_name', 'like', '%'.$keyword.'%')
                    ->orWhere('company_name', 'like', '%'.$keyword.'%')
                    ->orWhere('reference_no', 'like', '%'.$keyword.'%')
                    ->orWhere('bank_info', 'like', '%'.$keyword.'%')
                    ->orWhere('note', 'like', '%'.$keyword.'%');
            });
        }

        $status = (string) $request->input('status');
        if (in_array($status, ['unpaid', 'partial', 'paid', 'overdue'], true)) {
            $query->where('status', $status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate(DB::raw('COALESCE(reference_date, created_at)'), '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate(DB::raw('COALESCE(reference_date, created_at)'), '<=', $request->input('to_date'));
        }

        return $query;
    }

    private function entryOrFail(string $direction, string $category, int $entry): object
    {
        $item = DB::table('finance_ledger_entries')
            ->where('id', $entry)
            ->where('direction', $direction)
            ->where('category', $category)
            ->first();

        abort_unless($item, 404);

        return $item;
    }

    private function refreshStatuses(string $direction, string $category): void
    {
        $rows = DB::table('finance_ledger_entries')
            ->where('direction', $direction)
            ->where('category', $category)
            ->select('id', 'total_amount', 'paid_amount', 'due_date', 'status')
            ->get();

        foreach ($rows as $row) {
            $status = $this->statusFor((float) $row->total_amount, (float) $row->paid_amount, $row->due_date);
            if ($status !== $row->status) {
                DB::table('finance_ledger_entries')->where('id', $row->id)->update([
                    'status' => $status,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function statusFor(float $total, float $paid, ?string $dueDate): string
    {
        $remain = max($total - $paid, 0);
        if ($remain <= 0) {
            return 'paid';
        }

        if ($dueDate && $dueDate < now()->toDateString()) {
            return 'overdue';
        }

        if ($paid > 0) {
            return 'partial';
        }

        return 'unpaid';
    }

    private function normalizeMoneyFields(Request $request, array $fields): void
    {
        $payload = [];
        foreach ($fields as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $value = trim((string) $request->input($field));
            if ($value === '') {
                $payload[$field] = null;

                continue;
            }

            $value = str_replace(["\xc2\xa0", ' ', '.'], '', $value);
            $value = str_replace(',', '.', $value);
            $payload[$field] = is_numeric($value) ? $value : $request->input($field);
        }

        if ($payload) {
            $request->merge($payload);
        }
    }

    private function requireSchema(): void
    {
        abort_unless(
            Schema::hasTable('finance_ledger_entries') && Schema::hasTable('finance_ledger_transactions'),
            503,
            'Module công nợ mở rộng chưa được migrate.'
        );
    }
}
