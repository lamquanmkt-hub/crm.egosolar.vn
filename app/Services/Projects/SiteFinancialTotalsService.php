<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Số tiền đã thu và các loại chi phí, cộng sẵn theo từng công trình.
 *
 * ## Vì sao có lớp này
 * `sites/index.blade.php` trước đây chạy BỐN câu truy vấn cho MỖI công trình
 * ngay trong vòng lặp hiển thị. Đo trên trang danh sách với 10 công trình: 42
 * câu truy vấn theo từng dòng, tổng 75 câu. Trang phân trang 20 dòng nên thực tế
 * là ~80 câu chỉ để lấy bốn con số.
 *
 * Nay là bốn câu GROUP BY, không phụ thuộc số công trình trên trang.
 *
 * Các nhánh `SchemaCache` giữ nguyên: thiếu bảng/cột thì trả về map rỗng, và
 * tra map rỗng ra 0 — đúng bằng hành vi cũ.
 */
final class SiteFinancialTotalsService
{
    /**
     * @param  iterable<int|string>  $siteIds
     * @return array{
     *     received: Collection<int|string, float>,
     *     materialCost: Collection<int|string, float>,
     *     paymentCost: Collection<int|string, float>,
     *     requestCost: Collection<int|string, float>
     * }
     */
    public function forSites(iterable $siteIds): array
    {
        $ids = collect($siteIds)->map(fn ($x) => (int) $x)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [
                'received' => collect(),
                'materialCost' => collect(),
                'paymentCost' => collect(),
                'requestCost' => collect(),
            ];
        }

        return [
            'received' => $this->tong('receipts', 'amount', $ids),
            'materialCost' => $this->tong('material_requests', 'total_cost', $ids),
            'paymentCost' => $this->tong('payments', 'amount', $ids),
            'requestCost' => $this->tongDeNghiDaDuyet($ids),
        ];
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int|string, float>
     */
    private function tong(string $bang, string $cot, Collection $ids): Collection
    {
        if (! SchemaCache::hasTable($bang)
            || ! SchemaCache::hasColumn($bang, 'site_id')
            || ! SchemaCache::hasColumn($bang, $cot)) {
            return collect();
        }

        return DB::table($bang)
            ->whereIn('site_id', $ids)
            ->groupBy('site_id')
            ->pluck(DB::raw('SUM('.$cot.')'), 'site_id')
            ->map(fn ($x) => (float) $x);
    }

    /**
     * Đề nghị thanh toán chỉ tính khi đã được kế toán duyệt — giữ đúng bộ lọc cũ.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int|string, float>
     */
    private function tongDeNghiDaDuyet(Collection $ids): Collection
    {
        if (! SchemaCache::hasTable('payment_requests')
            || ! SchemaCache::hasColumn('payment_requests', 'site_id')) {
            return collect();
        }

        $query = DB::table('payment_requests')->whereIn('site_id', $ids);

        if (SchemaCache::hasColumn('payment_requests', 'status')) {
            $query->where('status', 'accounting_approved');
        }

        return $query
            ->groupBy('site_id')
            ->pluck(DB::raw('SUM(amount)'), 'site_id')
            ->map(fn ($x) => (float) $x);
    }
}
