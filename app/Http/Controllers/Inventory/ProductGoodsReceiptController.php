<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Support\EgoCompanyContext;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Controller phiếu nhập hàng nhà cung cấp: tạo phiếu, nhập kho, theo dõi công nợ.
 */
class ProductGoodsReceiptController extends Controller
{
    private function vnWarehouseIdsQuery()
    {
        $query = DB::table('crm_warehouses')->select('id');

        $query->where(function ($warehouseQuery) {
            $hasCondition = false;

            if (SchemaCache::hasColumn('crm_warehouses', 'company_id')) {
                $warehouseQuery->where('company_id', EgoCompanyContext::defaultCompanyId());
                $hasCondition = true;
            }

            if (SchemaCache::hasTable('company_warehouse')) {
                $method = $hasCondition ? 'orWhereIn' : 'whereIn';
                $warehouseQuery->{$method}('id', DB::table('company_warehouse')
                    ->select('warehouse_id')
                    ->where('company_id', EgoCompanyContext::defaultCompanyId()));
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $warehouseQuery->whereRaw('1 = 0');
            }
        });

        return $query;
    }

    /**
     * Danh sách phiếu nhập hàng có tìm kiếm, lọc trạng thái thanh toán và thống kê.
     */
    public function index(Request $request)
    {
        abort_unless(SchemaCache::hasTable('product_goods_receipts'), 500, 'Chưa có bảng product_goods_receipts.');

        $q = trim((string) $request->get('q', ''));
        $paymentStatus = trim((string) $request->get('payment_status', ''));

        $query = DB::table('product_goods_receipts as r')
            ->leftJoin('companies as c', 'c.id', '=', 'r.company_id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->select([
                'r.*',
                'c.name as company_name',
                'w.name as warehouse_name',
            ])
            ->where('r.company_id', EgoCompanyContext::defaultCompanyId())
            ->whereIn('r.warehouse_id', $this->vnWarehouseIdsQuery());

        if ($q !== '') {
            $query->where(function ($x) use ($q) {
                $x->where('r.code', 'like', "%{$q}%")
                    ->orWhere('r.supplier_name', 'like', "%{$q}%")
                    ->orWhere('r.supplier_phone', 'like', "%{$q}%")
                    ->orWhere('r.invoice_no', 'like', "%{$q}%")
                    ->orWhere('r.note', 'like', "%{$q}%");
            });
        }

        if ($paymentStatus !== '') {
            $query->where('r.payment_status', $paymentStatus);
        }

        $receipts = $query->orderByDesc('r.id')->paginate(20)->appends($request->query());

        // Nạp chi tiết hàng hóa cho các phiếu trên trang hiện tại để người dùng
        // có thể bấm mở ngay trong danh sách, không cần thêm route hoặc truy vấn AJAX.
        $receiptItems = collect();
        $receiptIds = $receipts->getCollection()->pluck('id')->filter()->values();

        if (
            $receiptIds->isNotEmpty()
            && SchemaCache::hasTable('product_goods_receipt_items')
            && SchemaCache::hasTable('crm_product_catalog')
        ) {
            $receiptItems = DB::table('product_goods_receipt_items as i')
                ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'i.product_id')
                ->whereIn('i.receipt_id', $receiptIds->all())
                ->select([
                    'i.id',
                    'i.receipt_id',
                    'i.product_id',
                    'i.qty',
                    'i.unit_price',
                    'i.vat_percent',
                    'i.amount',
                    'i.note',
                    'p.sku',
                    'p.name as product_name',
                    'p.unit',
                ])
                ->orderBy('i.receipt_id')
                ->orderBy('i.id')
                ->get()
                ->groupBy('receipt_id');
        }

        $stats = [
            'total' => DB::table('product_goods_receipts')->where('company_id', EgoCompanyContext::defaultCompanyId())->count(),
            'posted' => DB::table('product_goods_receipts')->where('company_id', EgoCompanyContext::defaultCompanyId())->where('status', 'posted')->count(),
            'unpaid' => DB::table('product_goods_receipts')->where('company_id', EgoCompanyContext::defaultCompanyId())->whereIn('payment_status', ['unpaid', 'partial'])->sum('debt_amount'),
            'paid' => DB::table('product_goods_receipts')->where('company_id', EgoCompanyContext::defaultCompanyId())->where('payment_status', 'paid')->sum('total_amount'),
        ];

        return view('products.goods-receipts.index', array_merge(
            $this->formData(),
            compact('receipts', 'receiptItems', 'stats', 'q', 'paymentStatus')
        ));
    }

    /**
     * Tạo phiếu nhập hàng NCC kèm dòng hàng; nhập kho luôn nếu action=post.
     */
    public function store(Request $request)
    {
        $request->merge(['company_id' => EgoCompanyContext::defaultCompanyId()]);

        $validator = Validator::make($request->all(), [
            'company_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'supplier_phone' => ['nullable', 'string', 'max:80'],
            'supplier_tax_code' => ['nullable', 'string', 'max:80'],
            'supplier_address' => ['nullable', 'string', 'max:500'],
            'invoice_no' => ['nullable', 'string', 'max:120'],
            'invoice_date' => ['nullable', 'date'],
            'payment_status' => ['required', 'in:unpaid,partial,paid'],
            'payment_due_date' => ['nullable', 'date'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.vat_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string'],
        ]);

        $validator->validate();

        $items = collect((array) $request->input('items', []))
            ->map(function ($row) {
                $qty = (float) ($row['qty'] ?? 0);
                $price = (float) ($row['unit_price'] ?? 0);
                $vat = (float) ($row['vat_percent'] ?? 0);
                $amount = $qty * $price;
                $vatAmount = $amount * $vat / 100;

                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'qty' => $qty,
                    'unit_price' => $price,
                    'vat_percent' => $vat,
                    'amount' => $amount + $vatAmount,
                    'note' => trim((string) ($row['note'] ?? '')),
                ];
            })
            ->filter(fn ($row) => $row['product_id'] > 0 && $row['qty'] > 0)
            ->values();

        if ($items->isEmpty()) {
            return back()->withInput()->with('error', 'Vui lòng thêm ít nhất 1 hàng hóa nhập kho.');
        }

        try {
            $this->guardCompanyWarehouseProducts(
                (int) $request->input('company_id'),
                (int) $request->input('warehouse_id'),
                $items->pluck('product_id')->all()
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $action = (string) $request->input('action', 'draft');

        try {
            DB::transaction(function () use ($request, $items, $action) {
                $now = now();
                $totalAmount = (float) $items->sum('amount');
                $paidAmount = (float) $request->input('paid_amount', 0);

                if ($request->input('payment_status') === 'paid') {
                    $paidAmount = $totalAmount;
                }

                $id = DB::table('product_goods_receipts')->insertGetId([
                    'code' => $this->makeCode(),
                    'company_id' => EgoCompanyContext::defaultCompanyId(),
                    'warehouse_id' => (int) $request->input('warehouse_id'),
                    'supplier_name' => $request->input('supplier_name'),
                    'supplier_phone' => $request->input('supplier_phone'),
                    'supplier_tax_code' => $request->input('supplier_tax_code'),
                    'supplier_address' => $request->input('supplier_address'),
                    'invoice_no' => $request->input('invoice_no'),
                    'invoice_date' => $request->input('invoice_date') ?: now()->toDateString(),
                    'payment_status' => $request->input('payment_status'),
                    'payment_due_date' => $request->input('payment_due_date'),
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'debt_amount' => max(0, $totalAmount - $paidAmount),
                    'status' => 'draft',
                    'note' => $request->input('note'),
                    'created_by' => auth()->id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($items as $row) {
                    DB::table('product_goods_receipt_items')->insert([
                        'receipt_id' => $id,
                        'product_id' => $row['product_id'],
                        'qty' => $row['qty'],
                        'unit_price' => $row['unit_price'],
                        'vat_percent' => $row['vat_percent'],
                        'amount' => $row['amount'],
                        'note' => $row['note'] ?: null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                if ($action === 'post') {
                    $this->postInsideTransaction($id);
                }
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('product-goods-receipts.index')
            ->with('success', $action === 'post' ? 'Đã nhập hàng và cập nhật kho.' : 'Đã lưu phiếu nháp.');
    }

    /**
     * Ghi nhận nhập kho cho phiếu trong transaction.
     */
    public function post($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $this->postInsideTransaction((int) $id);
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Đã cập nhật kho cho phiếu nhập hàng.');
    }

    /**
     * Xóa phiếu nháp (phiếu đã nhập kho thì không xóa).
     */
    public function destroy($id)
    {
        $row = DB::table('product_goods_receipts')->where('id', (int) $id)->where('company_id', EgoCompanyContext::defaultCompanyId())->first();
        abort_unless($row, 404);

        if (($row->status ?? '') === 'posted') {
            return back()->with('error', 'Phiếu đã nhập kho nên không được xóa để tránh lệch tồn kho.');
        }

        DB::transaction(function () use ($id) {
            DB::table('product_goods_receipt_items')->where('receipt_id', (int) $id)->delete();
            DB::table('product_goods_receipts')->where('id', (int) $id)->delete();
        });

        return back()->with('success', 'Đã xóa phiếu nháp.');
    }

    /**
     * Xử lý nhập kho: cộng tồn từng dòng hàng, tạo lot giá vốn, sự kiện kho và chuyển trạng thái posted.
     */
    private function postInsideTransaction(int $id): void
    {
        $receipt = DB::table('product_goods_receipts')->where('id', $id)->where('company_id', EgoCompanyContext::defaultCompanyId())->lockForUpdate()->first();

        if (! $receipt) {
            throw new \RuntimeException('Không tìm thấy phiếu nhập hàng.');
        }

        if (($receipt->status ?? '') === 'posted') {
            throw new \RuntimeException('Phiếu này đã nhập kho trước đó.');
        }

        $items = DB::table('product_goods_receipt_items')->where('receipt_id', $id)->get();

        if ($items->isEmpty()) {
            throw new \RuntimeException('Phiếu chưa có hàng hóa.');
        }

        $eventId = $this->createInventoryEvent('goods_receipt', 'Nhập hàng NCC #'.$receipt->code);

        foreach ($items as $item) {
            $this->adjustStock(
                (int) $item->product_id,
                (int) $receipt->warehouse_id,
                (int) $receipt->company_id,
                (float) $item->qty,
                'goods_receipt',
                $id
            );

            $this->createStockLot($receipt, $item);
        }

        $this->createInventoryRef($eventId, 'product_goods_receipt', $id);

        DB::table('product_goods_receipts')->where('id', $id)->update([
            'status' => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
            'updated_at' => now(),
        ]);

        // Khi phiếu nhập được ghi sổ, tự sinh/cập nhật công nợ NCC trong nước.
        $this->syncSupplierDebtFromGoodsReceipt($id);
    }

    /**
     * Đồng bộ phiếu nhập kho đã ghi sổ sang Công nợ NCC trong nước.
     * Đây là liên kết 1-1 qua source_type + source_id, không tạo công nợ trùng.
     */
    private function syncSupplierDebtFromGoodsReceipt(int $id): void
    {
        if (! SchemaCache::hasTable('finance_supplier_debts')) {
            return;
        }

        foreach (['source_type', 'source_id', 'source_code', 'supplier_scope'] as $column) {
            if (! SchemaCache::hasColumn('finance_supplier_debts', $column)) {
                return;
            }
        }

        $receipt = DB::table('product_goods_receipts')->where('id', $id)->first();
        if (! $receipt || ($receipt->status ?? '') !== 'posted') {
            return;
        }

        $debt = DB::table('finance_supplier_debts')
            ->where('source_type', 'product_goods_receipt')
            ->where('source_id', $id)
            ->lockForUpdate()
            ->first();

        $companyName = SchemaCache::hasTable('companies')
            ? DB::table('companies')->where('id', (int) $receipt->company_id)->value('name')
            : null;

        $documentDate = $receipt->invoice_date ?: substr((string) ($receipt->posted_at ?: now()), 0, 10);
        $debtMonth = substr((string) $documentDate, 0, 7).'-01';
        $paidAmount = min(max(0, (float) ($receipt->paid_amount ?? 0)), max(0, (float) ($receipt->total_amount ?? 0)));
        $status = $paidAmount >= (float) $receipt->total_amount && (float) $receipt->total_amount > 0
            ? 'paid'
            : ($paidAmount > 0 ? 'partial' : 'unpaid');

        $payload = [
            'source_type' => 'product_goods_receipt',
            'source_id' => $id,
            'source_code' => (string) $receipt->code,
            'supplier_scope' => 'domestic',
            'supplier_name' => (string) $receipt->supplier_name,
            'company_name' => $companyName,
            'document_no' => $receipt->invoice_no ?: $receipt->code,
            'document_date' => $documentDate,
            'debt_month' => $debtMonth,
            'total_amount' => (float) $receipt->total_amount,
            'note' => 'Tự động đồng bộ từ phiếu nhập kho '.$receipt->code,
            'status' => $status,
            'updated_at' => now(),
        ];

        if (SchemaCache::hasColumn('finance_supplier_debts', 'due_date')) {
            $payload['due_date'] = $receipt->payment_due_date ?: null;
        }
        if (SchemaCache::hasColumn('finance_supplier_debts', 'company_id')) {
            $payload['company_id'] = $receipt->company_id ?: null;
        }

        if ($debt) {
            DB::table('finance_supplier_debts')->where('id', $debt->id)->update($payload);
            $debtId = (int) $debt->id;
        } else {
            $payload['paid_amount'] = $paidAmount;
            $payload['created_by'] = $receipt->posted_by ?: $receipt->created_by ?: auth()->id();
            $payload['created_at'] = now();
            if (SchemaCache::hasColumn('finance_supplier_debts', 'bank_info')) {
                $payload['bank_info'] = null;
            }
            $debtId = (int) DB::table('finance_supplier_debts')->insertGetId($payload);
        }

        // Nếu phiếu nhập đã khai báo có tiền thanh toán ngay, tạo đúng một đợt đã chi
        // để SupplierDebtService không làm mất số đã trả khi tính lại công nợ.
        if (SchemaCache::hasTable('finance_supplier_debt_payments')) {
            $autoRoundQuery = DB::table('finance_supplier_debt_payments')
                ->where('supplier_debt_id', $debtId);

            if (SchemaCache::hasColumn('finance_supplier_debt_payments', 'source_type')) {
                $autoRoundQuery->where('source_type', 'product_goods_receipt_paid')->where('source_id', $id);
            } else {
                $autoRoundQuery->where('note', 'Tự động ghi nhận số đã thanh toán từ phiếu nhập '.$receipt->code);
            }

            $autoRound = $autoRoundQuery->first();

            if ($paidAmount > 0) {
                $roundPayload = [
                    'amount' => $paidAmount,
                    'payment_date' => $documentDate,
                    'status' => 'paid',
                    'note' => 'Tự động ghi nhận số đã thanh toán từ phiếu nhập '.$receipt->code,
                    'updated_at' => now(),
                ];

                if (SchemaCache::hasColumn('finance_supplier_debt_payments', 'source_type')) {
                    $roundPayload['source_type'] = 'product_goods_receipt_paid';
                    $roundPayload['source_id'] = $id;
                }

                if ($autoRound) {
                    DB::table('finance_supplier_debt_payments')->where('id', $autoRound->id)->update($roundPayload);
                } else {
                    $roundPayload['supplier_debt_id'] = $debtId;
                    $roundPayload['payment_request_id'] = null;
                    $roundPayload['payment_round'] = ((int) DB::table('finance_supplier_debt_payments')->where('supplier_debt_id', $debtId)->max('payment_round')) + 1;
                    $roundPayload['created_by'] = $receipt->posted_by ?: $receipt->created_by ?: auth()->id();
                    $roundPayload['created_at'] = now();
                    DB::table('finance_supplier_debt_payments')->insert($roundPayload);
                }
            } elseif ($autoRound) {
                DB::table('finance_supplier_debt_payments')->where('id', $autoRound->id)->delete();
            }

            app(\App\Services\Finance\SupplierDebtService::class)->syncSupplierDebtTotals($debtId);
        }
    }

    /**
     * Dữ liệu dropdown cho form: công ty, kho, sản phẩm kèm tồn hiện tại.
     */
    private function formData(): array
    {
        $companies = collect();
        if (SchemaCache::hasTable('companies')) {
            $q = DB::table('companies')->select('id', 'code', 'name')->where('id', EgoCompanyContext::defaultCompanyId());

            if (SchemaCache::hasColumn('companies', 'is_active')) {
                $q->where('is_active', 1);
            }

            $companies = $q->orderBy('id')->get();
        }

        $warehouses = collect();
        if (SchemaCache::hasTable('crm_warehouses')) {
            $warehouses = DB::table('crm_warehouses')
                ->select('id', 'company_id', 'name', 'location')
                ->whereIn('id', $this->vnWarehouseIdsQuery())
                ->orderBy('name')
                ->orderBy('name')
                ->get();
        }

        $products = collect();
        if (SchemaCache::hasTable('crm_product_catalog')) {
            $q = DB::table('crm_product_catalog as p')
                ->leftJoin('crm_product_stock as st', 'st.product_id', '=', 'p.id')
                ->selectRaw('p.id, p.company_id, p.sku, p.name, p.unit, COALESCE(SUM(st.qty),0) as stock_qty')
                ->groupBy('p.id', 'p.company_id', 'p.sku', 'p.name', 'p.unit')
                ->orderBy('p.name')
                ->limit(3000);

            if (SchemaCache::hasColumn('crm_product_catalog', 'is_active')) {
                $q->where('p.is_active', 1);
            }

            if (SchemaCache::hasColumn('crm_product_catalog', 'company_id')) {
                $q->where(function ($productQuery) {
                    $productQuery->where('p.company_id', EgoCompanyContext::defaultCompanyId())
                        ->orWhereNull('p.company_id');
                });
            }

            $products = $q->get();
        }

        return compact('companies', 'warehouses', 'products');
    }

    /**
     * Kiểm tra kho và sản phẩm phải thuộc công ty đã chọn, sai thì ném exception.
     */
    private function guardCompanyWarehouseProducts(int $companyId, int $warehouseId, array $productIds): void
    {
        if ($companyId !== EgoCompanyContext::defaultCompanyId()) {
            throw new \RuntimeException('Kho/Sản phẩm chỉ sử dụng cho EGO Việt Nam.');
        }

        if ($warehouseId <= 0 || ! $this->vnWarehouseIdsQuery()->where('id', $warehouseId)->exists()) {
            throw new \RuntimeException('Kho nhập hàng không thuộc EGO Việt Nam.');
        }

        if (SchemaCache::hasTable('crm_warehouses') && SchemaCache::hasColumn('crm_warehouses', 'company_id')) {
            $warehouse = DB::table('crm_warehouses')->where('id', $warehouseId)->first();

            if (! $warehouse) {
                throw new \RuntimeException('Kho nhập hàng không tồn tại.');
            }

            if (! empty($warehouse->company_id) && (int) $warehouse->company_id !== $companyId) {
                throw new \RuntimeException('Kho nhập hàng không thuộc công ty đã chọn.');
            }
        }

        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if (! $productIds) {
            throw new \RuntimeException('Vui lòng chọn hàng hóa nhập kho.');
        }

        if (SchemaCache::hasTable('crm_product_catalog') && SchemaCache::hasColumn('crm_product_catalog', 'company_id')) {
            $bad = DB::table('crm_product_catalog')
                ->whereIn('id', $productIds)
                ->whereNotNull('company_id')
                ->where('company_id', '<>', $companyId)
                ->exists();

            if ($bad) {
                throw new \RuntimeException('Có sản phẩm không thuộc công ty đã chọn. Vui lòng chọn lại sản phẩm.');
            }
        }
    }

    /**
     * Sinh mã phiếu nhập hàng dạng NH-Ymd-XXXX.
     */
    private function makeCode(): string
    {
        $prefix = 'NH-'.now()->format('Ymd').'-';
        $count = DB::table('product_goods_receipts')->where('code', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Cộng/trừ tồn kho theo cột thực có, ghi stock movement và đồng bộ tổng lên catalog.
     */
    private function adjustStock(int $productId, int $warehouseId, int $companyId, float $changeQty, string $reason, int $referenceId): void
    {
        $stockTable = 'crm_product_stock';

        $query = DB::table($stockTable)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId);

        if (SchemaCache::hasColumn($stockTable, 'company_id')) {
            $query->where('company_id', $companyId);
        }

        $row = $query->lockForUpdate()->first();

        $before = $row ? (float) $row->qty : 0;
        $after = $before + $changeQty;

        if ($row) {
            $data = ['qty' => $after];

            if (SchemaCache::hasColumn($stockTable, 'last_updated')) {
                $data['last_updated'] = now();
            }

            if (SchemaCache::hasColumn($stockTable, 'updated_at')) {
                $data['updated_at'] = now();
            }

            DB::table($stockTable)->where('id', $row->id)->update($data);
        } else {
            $data = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'qty' => $after,
            ];

            if (SchemaCache::hasColumn($stockTable, 'company_id')) {
                $data['company_id'] = $companyId;
            }

            if (SchemaCache::hasColumn($stockTable, 'serials_json')) {
                $data['serials_json'] = null;
            }

            if (SchemaCache::hasColumn($stockTable, 'last_updated')) {
                $data['last_updated'] = now();
            }

            if (SchemaCache::hasColumn($stockTable, 'created_at')) {
                $data['created_at'] = now();
            }

            if (SchemaCache::hasColumn($stockTable, 'updated_at')) {
                $data['updated_at'] = now();
            }

            DB::table($stockTable)->insert($data);
        }

        if (SchemaCache::hasTable('crm_stock_movements')) {
            $cols = SchemaCache::columns('crm_stock_movements');

            $data = [
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'change_qty' => $changeQty,
                'qty_before' => $before,
                'qty_after' => $after,
                'reason' => $reason,
                'reference_id' => $referenceId,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (in_array('company_id', $cols, true)) {
                $data['company_id'] = $companyId;
            }

            DB::table('crm_stock_movements')->insert(array_intersect_key($data, array_flip($cols)));
        }

        if (SchemaCache::hasTable('crm_product_catalog') && SchemaCache::hasColumn('crm_product_catalog', 'quantity')) {
            $total = (float) DB::table('crm_product_stock')->where('product_id', $productId)->sum('qty');

            DB::table('crm_product_catalog')->where('id', $productId)->update([
                'quantity' => $total,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Tạo lot tồn kho với giá vốn trước/sau VAT cho dòng hàng nhập.
     */
    private function createStockLot(object $receipt, object $item): void
    {
        if (! SchemaCache::hasTable('crm_product_stock_lots')) {
            return;
        }

        $cols = SchemaCache::columns('crm_product_stock_lots');
        $unitCost = (float) $item->unit_price;
        $qty = (float) $item->qty;

        $data = [
            'product_id' => (int) $item->product_id,
            'company_id' => (int) $receipt->company_id,
            'warehouse_id' => (int) $receipt->warehouse_id,
            'lot_code' => $receipt->code,
            'lot_name' => 'Nhập hàng NCC '.$receipt->code,
            'received_at' => now(),
            'qty_in' => $qty,
            'qty_remaining' => $qty,
            'cost_before_vat' => $unitCost,
            'cost_vat_percent' => (float) $item->vat_percent,
            'cost_after_vat' => $qty > 0 ? ((float) $item->amount / $qty) : $unitCost,
            'extra_cost' => 0,
            'actual_cost_after_vat' => $qty > 0 ? ((float) $item->amount / $qty) : $unitCost,
            'source_type' => 'product_goods_receipt',
            'source_id' => (int) $receipt->id,
            'note' => $receipt->supplier_name.' - HĐ: '.$receipt->invoice_no,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('crm_product_stock_lots')->insert(array_intersect_key($data, array_flip($cols)));
    }

    /**
     * Tạo sự kiện kho, trả về id hoặc null nếu thiếu bảng.
     */
    private function createInventoryEvent(string $type, string $note): ?int
    {
        if (! SchemaCache::hasTable('crm_inventory_events')) {
            return null;
        }

        $cols = SchemaCache::columns('crm_inventory_events');

        $data = [
            'event_type' => $type,
            'occurred_at' => now(),
            'created_by' => auth()->id(),
            'note' => $note,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return (int) DB::table('crm_inventory_events')->insertGetId(array_intersect_key($data, array_flip($cols)));
    }

    /**
     * Gắn tham chiếu nguồn cho sự kiện kho.
     */
    private function createInventoryRef(?int $eventId, string $refType, int $refId): void
    {
        if (! $eventId || ! SchemaCache::hasTable('crm_inventory_event_refs')) {
            return;
        }

        $cols = SchemaCache::columns('crm_inventory_event_refs');

        $data = [
            'event_id' => $eventId,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('crm_inventory_event_refs')->insert(array_intersect_key($data, array_flip($cols)));
    }
}
