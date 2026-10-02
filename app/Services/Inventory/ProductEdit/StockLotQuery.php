<?php

declare(strict_types=1);

namespace App\Services\Inventory\ProductEdit;

use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Đọc các lô tồn kho của một sản phẩm (gộp theo TÊN, không theo id).
 *
 * Gộp theo tên là chủ ý của nghiệp vụ: cùng một mặt hàng có thể tồn tại nhiều
 * bản ghi catalog khác nhau, form sửa cần thấy hết.
 */
final class StockLotQuery
{
    /**
     * Giá vốn sau VAT của một lô, ưu tiên giá thực tế rồi mới tới giá tính ra.
     *
     * Đặt ở một chỗ vì cả câu SELECT lẫn phần dựng lịch sử đều cần cùng một
     * định nghĩa; trước đây chuỗi này viết thẳng trong view.
     */
    private const COST_AFTER_VAT_EXPR = 'COALESCE('
        .'NULLIF(l.actual_cost_after_vat,0), '
        .'NULLIF(l.cost_after_vat,0), '
        .'COALESCE(l.cost_before_vat,p.price_agent,0) '
        .'* (1 + COALESCE(l.cost_vat_percent,p.cost_vat_percent,p.vat_percent,0) / 100), '
        .'COALESCE(p.price_agent_vat,0), 0)';

    /**
     * @return Collection<int, object> rỗng thì trả về MỘT dòng mẫu để form có chỗ nhập
     */
    public function forProduct(object $product): Collection
    {
        $rows = $this->fetch($product);

        return $rows->isEmpty() ? collect([$this->blankRow($product)]) : $rows;
    }

    /** @return Collection<int, object> */
    private function fetch(object $product): Collection
    {
        $table = $product->getTable();

        if (! SchemaCache::hasTable('crm_product_stock_lots')) {
            return collect();
        }

        $query = DB::table($table.' as p')
            ->leftJoin('crm_product_stock_lots as l', 'l.product_id', '=', 'p.id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'l.warehouse_id')
            ->leftJoin('companies as c', 'c.id', '=', 'l.company_id')
            ->where('p.name', $product->name);

        if (SchemaCache::hasColumn($table, 'is_active')) {
            $query->where(fn ($q) => $q->where('p.is_active', 1)->orWhereNull('p.is_active'));
        }

        return $query->select([
            'p.id as product_id',
            'p.name as product_name',
            'p.sku',
            'p.price_agent',
            'p.price_agent_vat',
            'p.vat_percent',
            DB::raw('COALESCE(p.cost_vat_percent, p.vat_percent, 0) as product_cost_vat'),
            'l.id as stock_lot_id',
            'l.company_id',
            'l.warehouse_id',
            'l.received_at',
            'l.qty_in',
            'l.qty_remaining',
            'l.cost_before_vat',
            'l.cost_vat_percent',
            'l.cost_after_vat',
            'l.extra_cost',
            'l.actual_cost_after_vat',
            'l.note as lot_note',
            'l.lot_name',
            'w.name as warehouse_name',
            'c.name as company_name',
            DB::raw(self::COST_AFTER_VAT_EXPR.' as actual_cost_calc'),
        ])
            ->orderBy('p.sku')
            ->orderByRaw('COALESCE(l.received_at, l.created_at) ASC')
            ->orderBy('l.id')
            ->get();
    }

    /** Dòng mẫu khi sản phẩm chưa có lô nào — form vẫn phải có một hàng để nhập. */
    private function blankRow(object $product): object
    {
        $vat = $product->cost_vat_percent ?? $product->vat_percent ?? 0;

        return (object) [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'sku' => $product->sku,
            'price_agent' => $product->price_agent ?? 0,
            'product_cost_vat' => $vat,
            'stock_lot_id' => null,
            'company_id' => null,
            'warehouse_id' => null,
            'received_at' => date('Y-m-d'),
            'qty_in' => 1,
            'qty_remaining' => 1,
            'cost_before_vat' => $product->price_agent ?? 0,
            'cost_vat_percent' => $vat,
            'extra_cost' => 0,
            'lot_note' => '',
            'actual_cost_calc' => $product->price_agent_vat ?? 0,
            'company_name' => '',
            'warehouse_name' => '',
        ];
    }
}
