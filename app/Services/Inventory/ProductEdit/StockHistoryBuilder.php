<?php

declare(strict_types=1);

namespace App\Services\Inventory\ProductEdit;

use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dựng lịch sử kho của sản phẩm: nhập kho (từ lô) + xuất kho (từ phân bổ đơn hàng).
 *
 * Chỉ có lô tồn hiện tại là chưa đủ — phần xuất kho nằm ở bảng phân bổ, nên phải
 * ghép hai nguồn rồi sắp theo thời gian.
 */
final class StockHistoryBuilder
{
    /**
     * @param  Collection<int, object>  $lots
     * @return Collection<int, array<string, mixed>>
     */
    public function build(Collection $lots, string $productTable): Collection
    {
        $productIds = $this->productIds($lots);

        if ($productIds->isEmpty()) {
            return collect();
        }

        return $this->incoming($lots)
            ->concat($this->outgoing($productIds, $productTable))
            ->sortBy('sort_at')
            ->values();
    }

    /** @return Collection<int, int> */
    private function productIds(Collection $lots): Collection
    {
        return $lots->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, object>  $lots
     * @return Collection<int, array<string, mixed>>
     */
    private function incoming(Collection $lots): Collection
    {
        return $lots->filter(fn ($row) => ! empty($row->stock_lot_id))->map(function ($row) {
            $qty = (float) ($row->qty_in ?? 0);
            $cost = (float) ($row->cost_before_vat ?? $row->price_agent ?? 0);
            $vat = (float) ($row->cost_vat_percent ?? $row->product_cost_vat ?? 0);
            $after = (float) ($row->actual_cost_calc ?? ($cost * (1 + $vat / 100)));

            return [
                'type' => 'Nhập kho',
                'sign' => '+',
                'product_id' => (int) ($row->product_id ?? 0),
                'product_name' => (string) ($row->product_name ?? ''),
                'sku' => (string) ($row->sku ?? ''),
                'company_name' => (string) ($row->company_name ?? ''),
                'warehouse_name' => (string) ($row->warehouse_name ?? ''),
                'date' => $row->received_at ? date('Y-m-d', strtotime($row->received_at)) : '',
                'sort_at' => $row->received_at ? strtotime($row->received_at) : 0,
                'cost' => $cost,
                'vat' => $vat,
                'after' => $after,
                'qty' => $qty,
                'extra' => (float) ($row->extra_cost ?? 0),
                'actual' => $after,
                'total' => $after * $qty,
                'note' => (string) (($row->lot_note ?? '') ?: 'Nhập kho'),
            ];
        });
    }

    /**
     * @param  Collection<int, int>  $productIds
     * @return Collection<int, array<string, mixed>>
     */
    private function outgoing(Collection $productIds, string $productTable): Collection
    {
        if (! SchemaCache::hasTable('crm_order_item_stock_allocations')) {
            return collect();
        }

        return DB::table('crm_order_item_stock_allocations as a')
            ->leftJoin('crm_orders as o', 'o.id', '=', 'a.order_id')
            ->leftJoin('crm_order_items as oi', 'oi.id', '=', 'a.order_item_id')
            ->leftJoin($productTable.' as p', 'p.id', '=', 'a.product_id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'a.warehouse_id')
            ->leftJoin('companies as c', 'c.id', '=', 'a.company_id')
            ->whereIn('a.product_id', $productIds->all())
            ->select([
                'a.product_id',
                'a.qty',
                'a.unit_cost_before_vat',
                'a.unit_cost_after_vat',
                'a.total_cost_after_vat',
                'a.created_at',
                'p.name as product_name',
                'p.sku',
                'w.name as warehouse_name',
                'c.name as company_name',
                'o.order_code',
                'oi.vat_percent as order_vat_percent',
            ])
            ->get()
            ->map(fn ($row) => $this->outgoingRow($row));
    }

    /** @return array<string, mixed> */
    private function outgoingRow(object $row): array
    {
        $qty = (float) ($row->qty ?? 0);
        $before = (float) ($row->unit_cost_before_vat ?? 0);
        $after = (float) ($row->unit_cost_after_vat ?? 0);

        return [
            'type' => 'Xuất kho đơn '.(string) ($row->order_code ?? ''),
            'sign' => '-',
            'product_id' => (int) ($row->product_id ?? 0),
            'product_name' => (string) ($row->product_name ?? ''),
            'sku' => (string) ($row->sku ?? ''),
            'company_name' => (string) ($row->company_name ?? ''),
            'warehouse_name' => (string) ($row->warehouse_name ?? ''),
            'date' => $row->created_at ? date('Y-m-d H:i', strtotime($row->created_at)) : '',
            'sort_at' => $row->created_at ? strtotime($row->created_at) : 0,
            'cost' => $before,
            'vat' => $this->vatPercent($row, $before, $after),
            'after' => $after,
            'qty' => -1 * abs($qty),
            'extra' => 0,
            'actual' => $after,
            'total' => -1 * abs((float) ($row->total_cost_after_vat ?? ($after * $qty))),
            'note' => 'Xuất kho bán hàng',
        ];
    }

    /** Đơn cũ có thể không lưu %VAT — khi đó suy ngược từ giá trước/sau VAT. */
    private function vatPercent(object $row, float $before, float $after): float
    {
        $vat = (float) ($row->order_vat_percent ?? 0);

        if ($vat <= 0 && $before > 0 && $after > $before) {
            return round((($after / $before) - 1) * 100, 2);
        }

        return $vat;
    }
}
