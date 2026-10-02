<?php

declare(strict_types=1);

namespace App\Services\Inventory\ProductEdit;

use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gom dữ liệu cho form sửa sản phẩm.
 *
 * Trước đây toàn bộ phần này nằm trong hai khối `@php` (275 + 81 dòng) ngay trong
 * `resources/views/products/edit.blade.php`, kèm 9 lệnh `DB::`. View vừa lấy dữ
 * liệu vừa hiển thị.
 *
 * Lớp này chỉ điều phối; mỗi việc đọc dữ liệu nằm ở một lớp riêng để có đúng một
 * lý do phải sửa: {@see StockLotQuery}, {@see StockHistoryBuilder},
 * {@see SerialUnitQuery}.
 */
final class ProductEditPageData
{
    /** Trạng thái serial hiển thị cho người dùng. */
    private const SERIAL_STATE_LABELS = [
        'in_stock' => 'Trong kho',
        'sold' => 'Đã bán',
        'delivered' => 'Đã giao',
        'returned' => 'Hàng trả',
        'removed' => 'Đã xóa',
    ];

    /** Kho của công ty vận hành mặc định. */
    private const DEFAULT_COMPANY_ID = 1;

    public function __construct(
        private readonly StockLotQuery $stockLots,
        private readonly StockHistoryBuilder $stockHistory,
        private readonly SerialUnitQuery $serialUnits,
        private readonly NumberInputFormatter $formatNumber,
    ) {}

    /**
     * @param  Collection<int, object>  $companies
     * @param  array<int|string, iterable<object>>  $companyWarehouses
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public function build(
        object $product,
        Collection $companies,
        array $companyWarehouses,
        array $formData,
    ): array {
        $lots = $this->stockLots->forProduct($product);
        $lines = $this->formLines($lots);

        /*
         * Danh sách id này dùng cho BA việc: tra serial, tra tên sản phẩm, và
         * chính template lặp qua nó. Tính một lần rồi truyền đi — tra tên theo
         * kết quả serial sẽ thiếu những sản phẩm chưa có serial nào.
         */
        $serialProductIds = $this->serialProductIds($lines, $lots, $product);
        $serialRows = $this->serialUnits->forProducts($serialProductIds);

        return [
            'companyOptions' => $this->companyOptions($companies),
            'warehouseOptions' => $this->warehouseOptions($companyWarehouses),

            'groupRows' => $lots,
            'initialLines' => $lines,
            'currentStockQty' => $this->stockQty($lots),
            'stockHistoryRows' => $this->stockHistory->build($lots, $product->getTable()),

            // Hai hình dạng của CÙNG một kết quả: mảng cho JavaScript, nhóm cho Blade.
            'serialRowsByProduct' => $serialRows->groupBy('product_id')->map->values()->toArray(),
            'egoSerialRowsByProduct' => $serialRows->groupBy('product_id'),
            'egoSerialProductIds' => $serialProductIds,
            'egoSerialProducts' => $this->serialProductNames($serialProductIds),
            'egoSerialWarehouses' => $this->defaultCompanyWarehouses(),
            'egoStateLabels' => self::SERIAL_STATE_LABELS,

            'savedTierPrices' => collect($formData['tierPrices'] ?? []),
            'fmtInput' => $this->formatNumber,
        ];
    }

    /**
     * @param  Collection<int, object>  $companies
     * @return Collection<int, array{id: mixed, name: mixed}>
     */
    private function companyOptions(Collection $companies): Collection
    {
        return $companies->map(fn ($company) => ['id' => $company->id, 'name' => $company->name])->values();
    }

    /**
     * @param  array<int|string, iterable<object>>  $companyWarehouses
     * @return Collection<int, array{id: mixed, name: mixed, company_id: string}>
     */
    private function warehouseOptions(array $companyWarehouses): Collection
    {
        return collect($companyWarehouses)
            ->flatMap(fn ($warehouses, $companyId) => collect($warehouses)->map(fn ($warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'company_id' => (string) $companyId,
            ]))
            ->values();
    }

    /** @param Collection<int, object> $lots */
    private function stockQty(Collection $lots): float
    {
        return (float) $lots->sum(fn ($lot) => (float) ($lot->qty_remaining ?? 0));
    }

    /**
     * Một dòng nhập cho mỗi lô.
     *
     * Form sửa hiển thị TỒN HIỆN TẠI; không ép tồn 0 thành 1 như bản cũ từng làm.
     *
     * @param  Collection<int, object>  $lots
     * @return Collection<int, array<string, mixed>>
     */
    private function formLines(Collection $lots): Collection
    {
        return $lots->map(function ($lot, $index) {
            $remaining = (float) ($lot->qty_remaining ?? 0);
            $received = (float) ($lot->qty_in ?? $remaining);

            return [
                'product_id' => (int) ($lot->product_id ?? 0),
                'stock_lot_id' => (int) ($lot->stock_lot_id ?? 0),
                'display_name' => (string) (($lot->lot_name ?? '') ?: ('Dòng tồn / SKU '.($index + 1))),
                'sku' => (string) ($lot->sku ?? ''),
                'cost' => (float) ($lot->cost_before_vat ?? $lot->price_agent ?? 0),
                'vat' => (float) ($lot->cost_vat_percent ?? $lot->product_cost_vat ?? 0),
                'qty' => $remaining,
                'qty_in_original' => $received,
                'qty_sold' => max(0, $received - $remaining),
                'date' => $lot->received_at ? date('Y-m-d', strtotime($lot->received_at)) : date('Y-m-d'),
                'extra' => (float) ($lot->extra_cost ?? 0),
                'note' => (string) ($lot->lot_note ?? ''),
                'company_id' => (string) ($lot->company_id ?? ''),
                'warehouse_id' => (string) ($lot->warehouse_id ?? ''),
            ];
        })->values();
    }

    /**
     * Id sản phẩm cần tra serial: ưu tiên dòng form, rồi tới lô, cuối cùng là
     * chính sản phẩm đang sửa.
     *
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  Collection<int, object>  $lots
     * @return Collection<int, int>
     */
    private function serialProductIds(Collection $lines, Collection $lots, object $product): Collection
    {
        $fromLines = $lines->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($fromLines->isNotEmpty()) {
            return $fromLines;
        }

        $fromLots = $lots->pluck('product_id')->map(fn ($id) => (int) $id)->filter()->unique()->values();

        if ($fromLots->isNotEmpty()) {
            return $fromLots;
        }

        return collect([(int) $product->id])->filter()->values();
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, object>
     */
    private function serialProductNames(Collection $ids): Collection
    {
        if ($ids->isEmpty() || ! SchemaCache::hasTable('crm_product_catalog')) {
            return collect();
        }

        return DB::table('crm_product_catalog')
            ->whereIn('id', $ids->all())
            ->select('id', 'name', 'sku')
            ->get()
            ->keyBy('id');
    }

    /** @return Collection<int, object> */
    private function defaultCompanyWarehouses(): Collection
    {
        if (! SchemaCache::hasTable('crm_warehouses')) {
            return collect();
        }

        return DB::table('crm_warehouses')
            ->select('id', 'name')
            ->where(fn ($query) => $query
                ->where('company_id', self::DEFAULT_COMPANY_ID)
                ->orWhereIn('id', DB::table('company_warehouse')
                    ->select('warehouse_id')
                    ->where('company_id', self::DEFAULT_COMPANY_ID)))
            ->orderBy('name')
            ->get();
    }
}
