<?php

declare(strict_types=1);

namespace App\Services\Inventory\ProductEdit;

use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Đọc các đơn vị serial của một nhóm sản phẩm.
 *
 * ## Trước đây truy vấn này chạy HAI LẦN mỗi lần mở trang
 * View có hai khối `@php` cùng chạy đúng câu truy vấn này: một khối để đổ ra JSON
 * cho JavaScript, khối kia để render phía server. Cùng dữ liệu, chỉ khác cách gom
 * nhóm. Nay truy vấn một lần, hai chỗ dùng lấy hai hình dạng từ cùng kết quả.
 */
final class SerialUnitQuery
{
    private const REQUIRED_TABLES = [
        'crm_serial_units',
        'crm_serial_unit_identifiers',
        'crm_serial_identifiers',
        'crm_serial_unit_states',
    ];

    /**
     * @param  Collection<int, int>  $productIds
     * @return Collection<int, object>
     */
    public function forProducts(Collection $productIds): Collection
    {
        if ($productIds->isEmpty() || ! $this->tablesReady()) {
            return collect();
        }

        return DB::table('crm_serial_units as su')
            ->join('crm_serial_unit_identifiers as sui', function ($join) {
                $join->on('sui.serial_unit_id', '=', 'su.id')->where('sui.is_primary', 1);
            })
            ->join('crm_serial_identifiers as si', 'si.id', '=', 'sui.serial_identifier_id')
            ->leftJoin('crm_serial_unit_states as st', 'st.serial_unit_id', '=', 'su.id')
            ->leftJoin('crm_warehouses as w', 'w.id', '=', 'st.warehouse_id')
            ->leftJoin('crm_serial_warranties as wa', 'wa.serial_unit_id', '=', 'su.id')
            ->whereIn('su.product_id', $productIds->all())
            ->select([
                'su.id',
                'su.product_id',
                'si.code',
                'st.state',
                'st.warehouse_id',
                'w.name as warehouse_name',
                'wa.order_id',
                'wa.warranty_end_at',
            ])
            ->orderBy('si.code')
            ->get();
    }

    private function tablesReady(): bool
    {
        foreach (self::REQUIRED_TABLES as $table) {
            if (! SchemaCache::hasTable($table)) {
                return false;
            }
        }

        return true;
    }
}
