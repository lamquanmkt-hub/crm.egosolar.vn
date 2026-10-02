<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\CRM\Orders\Order;
use App\Support\SchemaCache;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gom toàn bộ dữ liệu phụ trợ của trang chi tiết đơn hàng (`orders.show`).
 *
 * Trước đây controller lặp qua từng dòng hàng và bắn 3 truy vấn cho mỗi dòng
 * (số đã trả, số đang chờ trả, danh sách serial) — đơn 40 dòng hàng tốn hơn
 * 120 truy vấn chỉ để render 1 trang. Ở đây mỗi loại dữ liệu chỉ còn ĐÚNG MỘT
 * truy vấn gộp theo `order_item_id`, phần ghép về từng dòng hàng làm bằng
 * bảng băm trong bộ nhớ: độ phức tạp O(số dòng trả về) thay vì O(số dòng hàng)
 * lần round-trip DB.
 *
 * Các guard `Schema::hasTable` được giữ nguyên (schema production thay đổi dần
 * theo từng đợt migration) nhưng chỉ chạy MỘT lần cho cả trang thay vì lặp lại
 * trong vòng lặp.
 */
final class OrderDetailViewService
{
    /** Phiếu trả ở các trạng thái này coi như không tồn tại khi tính tồn trả. */
    private const VOIDED_RETURN_STATUSES = ['rejected', 'cancelled'];

    /** Phiếu trả đã kết thúc — chỉ còn tính phần đã chấp nhận, không tính phần chờ. */
    private const SETTLED_RETURN_STATUSES = ['completed', 'rejected', 'cancelled'];

    /** Giới hạn số dòng lịch sử xuất/nhập kho hiển thị trên trang. */
    private const STOCK_MOVEMENT_LIMIT = 100;

    /**
     * Số lượng còn được phép tạo phiếu trả cho từng dòng hàng.
     *
     * Công thức giữ nguyên như bản cũ: `đã đặt - đã chấp nhận trả - đang chờ trả`,
     * không bao giờ âm.
     *
     * @param  Collection<int, object>  $items  Dòng hàng của đơn (cần `id`, `quantity`)
     * @return array<int, int> order_item_id => số lượng còn trả được
     */
    public function returnableQuantitiesByItem(Collection $items): array
    {
        $itemIds = $items->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $totals = $this->returnTotalsByItem($itemIds);

        $result = [];

        foreach ($items as $item) {
            $itemId = (int) $item->id;
            $totalsForItem = $totals[$itemId] ?? ['used' => 0, 'pending' => 0];

            $result[$itemId] = max(
                0,
                (int) $item->quantity - $totalsForItem['used'] - $totalsForItem['pending'],
            );
        }

        return $result;
    }

    /**
     * Serial đã gắn cho từng dòng hàng, dùng cho form chọn serial khi trả hàng.
     *
     * @param  list<int>  $itemIds
     * @return array<int, Collection<int, object>> order_item_id => serial (id, code, state)
     */
    public function serialsByOrderItem(array $itemIds): array
    {
        /** @var array<int, Collection<int, object>> $empty */
        $empty = array_fill_keys($itemIds, collect());

        if ($itemIds === [] || ! $this->serialTablesAvailable()) {
            return $empty;
        }

        $rows = $this->orderItemSerialQuery($itemIds)
            ->select(
                'oi.order_item_id',
                'su.id',
                DB::raw("COALESCE(MAX(si.code), CONCAT('#', su.id)) as code"),
                DB::raw("COALESCE(MAX(st.state), 'unknown') as state"),
            )
            ->groupBy('oi.order_item_id', 'su.id')
            ->orderBy('su.id')
            ->get();

        foreach ($rows as $row) {
            $itemId = (int) $row->order_item_id;
            unset($row->order_item_id);
            $empty[$itemId] = ($empty[$itemId] ?? collect())->push($row);
        }

        return $empty;
    }

    /**
     * Danh sách serial của cả đơn, kèm tên sản phẩm và kho (bảng tổng hợp).
     *
     * @param  list<int>  $itemIds
     * @return Collection<int, object>
     */
    public function orderSerials(array $itemIds): Collection
    {
        if ($itemIds === [] || ! $this->serialTablesAvailable()) {
            return collect();
        }

        $query = $this->orderItemSerialQuery($itemIds)
            ->leftJoin('crm_order_items as item', 'item.id', '=', 'oi.order_item_id');

        if (SchemaCache::hasTable('crm_product_catalog')) {
            $query->leftJoin('crm_product_catalog as product', 'product.id', '=', 'su.product_id');
        }

        if (SchemaCache::hasTable('crm_warehouses')) {
            $query->leftJoin('crm_warehouses as warehouse', 'warehouse.id', '=', 'su.warehouse_id');
        }

        return $query
            ->select(
                'oi.order_item_id',
                'su.id as serial_unit_id',
                'su.product_id',
                DB::raw("COALESCE(MAX(product.name), MAX(item.product_name), CONCAT('SP #', su.product_id)) as product_name"),
                DB::raw("COALESCE(MAX(si.code), CONCAT('#', su.id)) as serial_code"),
                DB::raw("COALESCE(MAX(st.state), 'unknown') as state"),
                DB::raw('MAX(warehouse.name) as warehouse_name'),
            )
            ->groupBy('oi.order_item_id', 'su.id', 'su.product_id')
            ->orderBy('oi.order_item_id')
            ->get();
    }

    /**
     * Kho được phép nhận hàng trả — giới hạn theo công ty của đơn.
     *
     * @return Collection<int, object>
     */
    public function returnWarehouses(?int $companyId): Collection
    {
        if (! SchemaCache::hasTable('crm_warehouses')) {
            return collect();
        }

        return DB::table('crm_warehouses')
            ->when($companyId, static fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->orderBy('name')
            ->get();
    }

    /**
     * Phân bổ tồn kho theo lô của đơn (lô nào xuất cho dòng hàng nào).
     *
     * @return Collection<int, object>
     */
    public function stockAllocations(int $orderId): Collection
    {
        if (! SchemaCache::hasTable('crm_order_item_stock_allocations')) {
            return collect();
        }

        $query = DB::table('crm_order_item_stock_allocations as allocation')
            ->where('allocation.order_id', $orderId);

        if (SchemaCache::hasTable('crm_product_stock_lots')) {
            $query->leftJoin('crm_product_stock_lots as lot', 'lot.id', '=', 'allocation.stock_lot_id');
        }

        if (SchemaCache::hasTable('crm_product_catalog')) {
            $query->leftJoin('crm_product_catalog as product', 'product.id', '=', 'allocation.product_id');
        }

        if (SchemaCache::hasTable('crm_warehouses')) {
            $query->leftJoin('crm_warehouses as warehouse', 'warehouse.id', '=', 'allocation.warehouse_id');
        }

        return $query
            ->select(
                'allocation.*',
                DB::raw('lot.lot_code as lot_code'),
                DB::raw('product.name as product_name'),
                DB::raw('warehouse.name as warehouse_name'),
            )
            ->orderBy('allocation.id')
            ->get();
    }

    /**
     * Lịch sử biến động kho liên quan tới đơn (theo id tham chiếu hoặc mã đơn).
     *
     * @return Collection<int, object>
     */
    public function stockMovements(Order $order): Collection
    {
        if (! SchemaCache::hasTable('crm_stock_movements')) {
            return collect();
        }

        $query = DB::table('crm_stock_movements as movement')
            ->where(static function (Builder $filter) use ($order): void {
                $filter->where('movement.reference_id', $order->id)
                    ->orWhere('movement.reason', 'like', '%'.$order->order_code.'%');
            });

        if (SchemaCache::hasTable('crm_product_catalog')) {
            $query->leftJoin('crm_product_catalog as product', 'product.id', '=', 'movement.product_id');
        }

        return $query
            ->select('movement.*', DB::raw('product.name as product_name'))
            ->latest('movement.id')
            ->limit(self::STOCK_MOVEMENT_LIMIT)
            ->get();
    }

    /**
     * Chứng từ đính kèm của đơn (ĐNTT, hợp đồng, hoá đơn...).
     *
     * @return Collection<int, object>
     */
    public function documents(int $orderId): Collection
    {
        if (! SchemaCache::hasTable('crm_order_documents')) {
            return collect();
        }

        return DB::table('crm_order_documents')
            ->where('order_id', $orderId)
            ->latest('id')
            ->get();
    }

    /**
     * Tổng số đã chấp nhận trả và đang chờ trả của từng dòng hàng — MỘT truy vấn.
     *
     * @param  list<int>  $itemIds
     * @return array<int, array{used: int, pending: int}>
     */
    private function returnTotalsByItem(array $itemIds): array
    {
        if ($itemIds === [] || ! SchemaCache::hasTable('order_return_items') || ! SchemaCache::hasTable('order_returns')) {
            return [];
        }

        $voided = $this->quotedList(self::VOIDED_RETURN_STATUSES);
        $settled = $this->quotedList(self::SETTLED_RETURN_STATUSES);

        $rows = DB::table('order_return_items as ri')
            ->join('order_returns as r', 'r.id', '=', 'ri.order_return_id')
            ->whereIn('ri.order_item_id', $itemIds)
            ->groupBy('ri.order_item_id')
            ->select(
                'ri.order_item_id',
                DB::raw("SUM(CASE WHEN r.status NOT IN ({$voided}) THEN ri.accepted_quantity ELSE 0 END) as used"),
                DB::raw("SUM(CASE WHEN r.status NOT IN ({$settled}) THEN ri.requested_quantity ELSE 0 END) as pending"),
            )
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row->order_item_id] = [
                'used' => (int) $row->used,
                'pending' => (int) $row->pending,
            ];
        }

        return $totals;
    }

    /**
     * Query nền cho serial của dòng hàng: link → serial unit → mã → trạng thái.
     *
     * @param  list<int>  $itemIds
     */
    private function orderItemSerialQuery(array $itemIds): Builder
    {
        $query = DB::table('crm_order_item_serial_units as oi')
            ->join('crm_serial_units as su', 'su.id', '=', 'oi.serial_unit_id')
            ->whereIn('oi.order_item_id', $itemIds);

        if (SchemaCache::hasTable('crm_serial_unit_identifiers')) {
            $query->leftJoin('crm_serial_unit_identifiers as sui', 'sui.serial_unit_id', '=', 'su.id');
        }

        if (SchemaCache::hasTable('crm_serial_identifiers')) {
            $query->leftJoin('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id');
        }

        if (SchemaCache::hasTable('crm_serial_unit_states')) {
            $query->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id');
        }

        return $query;
    }

    /** Hai bảng bắt buộc để tra được serial của dòng hàng. */
    private function serialTablesAvailable(): bool
    {
        return SchemaCache::hasTable('crm_order_item_serial_units')
            && SchemaCache::hasTable('crm_serial_units');
    }

    /**
     * Chuỗi danh sách hằng trạng thái đã escape, dùng trong biểu thức CASE WHEN.
     *
     * Danh sách là hằng của lớp (không phải input người dùng) nhưng vẫn escape
     * để không tạo tiền lệ nối chuỗi thô vào SQL.
     *
     * @param  list<string>  $statuses
     */
    private function quotedList(array $statuses): string
    {
        return implode(',', array_map(
            static fn (string $status): string => "'".str_replace("'", "''", $status)."'",
            $statuses,
        ));
    }
}
