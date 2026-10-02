<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu cho khối "Hồ sơ đơn hàng" trong trang hồ sơ khách hàng.
 *
 * ## Vì sao tách khỏi Blade
 * Toàn bộ phần này trước nằm trong `ego_order_documents/profile_box.blade.php`
 * dưới dạng 141 dòng `@php`: view tự chạy 7 câu truy vấn, tự dò schema, tự gộp
 * số liệu. Không test được, và mỗi lần sửa cách tìm đơn phải mò trong HTML.
 *
 * ## Cách tìm đơn của khách — giữ nguyên
 * `crm_orders` KHÔNG có cột `customer_id` (đã đối chiếu schema production), nên
 * đường duy nhất là: hồ sơ -> danh sách khách (theo customer_id, số điện thoại,
 * tên đại lý) -> lead của các khách đó -> đơn theo lead_id.
 *
 * Các nhánh `SchemaCache::hasTable/hasColumn` giữ nguyên vì cùng một bộ mã chạy
 * trên nhiều bản schema; thiếu bảng thì trả rỗng chứ không làm vỡ trang.
 */
final class CustomerProfileOrderBoardService
{
    /** Nhãn loại tài liệu, dùng để hiển thị. */
    public const DOC_TYPES = [
        'payment_request' => 'ĐNTT',
        'purchase_contract' => 'Hợp đồng mua bán',
        'agency_contract' => 'Hợp đồng đại lý',
        'deposit' => 'Phiếu đặt cọc',
        'quotation' => 'Báo giá',
        'invoice' => 'Hóa đơn / UNC',
        'delivery' => 'Vận chuyển / Giao nhận',
        'warranty' => 'Bảo hành',
        'po' => 'PO',
        'ci' => 'Commercial Invoice',
        'pl' => 'Packing List',
        'co_cq' => 'CO / CQ',
        'customs' => 'Hải quan',
        'other' => 'Khác',
    ];

    /** Bảng thanh toán — dùng bảng đầu tiên có mặt. */
    private const PAYMENT_TABLES = ['crm_order_payments', 'order_payments', 'crm_payments'];

    /** Số đơn tối đa lấy về, giữ đúng ngưỡng cũ. */
    private const ORDER_LIMIT = 100;

    /**
     * @return array<string, mixed>
     */
    public function viewData(?object $profile): array
    {
        $profileId = (int) ($profile->id ?? 0);
        $customerIds = $this->customerIds($profile);
        $leadIds = $this->leadIds($customerIds);
        $orders = $this->orders($leadIds, $customerIds);
        $orderIds = $orders->pluck('id')->map(fn ($x) => (int) $x)->unique()->values();
        $paidByOrder = $this->paidByOrder($orderIds);
        $docs = $this->documents($profileId, $customerIds, $orderIds);
        $firstDoc = $docs->first();

        return [
            'egoDocTypes' => self::DOC_TYPES,
            'egoOrders' => $orders,
            'egoOrderDocs' => $docs,
            'egoDocsByOrder' => $docs->groupBy('order_id'),
            'egoPaidByOrder' => $paidByOrder,
            'egoTotalRevenue' => (float) $orders->sum(fn ($o) => (float) ($o->total_amount ?? 0)),
            'egoTotalPaid' => (float) $paidByOrder->sum(),
            'egoFirstPreviewUrl' => $firstDoc
                ? url('/orders/'.$firstDoc->order_id.'/documents-ego/'.$firstDoc->id.'/preview')
                : '',
            'egoFirstName' => $firstDoc
                ? ((($firstDoc->title ?: $firstDoc->original_name) ?: 'File xem trước'))
                : '',
        ];
    }

    /**
     * Khách hàng gắn với hồ sơ: theo customer_id, rồi mở rộng theo số điện thoại
     * và tên đại lý — một đại lý có thể có nhiều bản ghi khách.
     *
     * Lưu ý: `crm_customers.phone` có ràng buộc UNIQUE nên nhánh theo số điện
     * thoại trả về nhiều nhất một bản ghi. Đường mở rộng thật sự là theo `name`.
     * Giữ cả hai nhánh để không đổi hành vi, nhưng đừng tưởng nhánh phone làm
     * được nhiều hơn thế.
     *
     * @return Collection<int, int>
     */
    private function customerIds(?object $profile): Collection
    {
        $ids = collect([$profile->customer_id ?? null]);

        if (SchemaCache::hasTable('crm_customers')) {
            if (! empty($profile->phone) && SchemaCache::hasColumn('crm_customers', 'phone')) {
                $ids = $ids->merge(
                    DB::table('crm_customers')->where('phone', $profile->phone)->pluck('id')
                );
            }

            if (! empty($profile->agent_name) && SchemaCache::hasColumn('crm_customers', 'name')) {
                $ids = $ids->merge(
                    DB::table('crm_customers')->where('name', $profile->agent_name)->pluck('id')
                );
            }
        }

        return $ids->filter()->map(fn ($x) => (int) $x)->unique()->values();
    }

    /**
     * @param  Collection<int, int>  $customerIds
     * @return Collection<int, int>
     */
    private function leadIds(Collection $customerIds): Collection
    {
        if ($customerIds->isEmpty()
            || ! SchemaCache::hasTable('crm_leads')
            || ! SchemaCache::hasColumn('crm_leads', 'customer_id')) {
            return collect();
        }

        return DB::table('crm_leads')
            ->whereIn('customer_id', $customerIds)
            ->pluck('id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $leadIds
     * @param  Collection<int, int>  $customerIds
     * @return Collection<int, object>
     */
    private function orders(Collection $leadIds, Collection $customerIds): Collection
    {
        if (! SchemaCache::hasTable('crm_orders') || ($leadIds->isEmpty() && $customerIds->isEmpty())) {
            return collect();
        }

        $query = DB::table('crm_orders as o')->select('o.*');

        if (SchemaCache::hasTable('crm_order_status_types')
            && SchemaCache::hasColumn('crm_orders', 'current_status_type_id')) {
            $query
                ->leftJoin('crm_order_status_types as st', 'st.id', '=', 'o.current_status_type_id')
                ->addSelect('st.name as ego_status_name');
        }

        $query->where(function ($q) use ($leadIds, $customerIds): void {
            $daCo = false;

            if ($leadIds->isNotEmpty() && SchemaCache::hasColumn('crm_orders', 'lead_id')) {
                $q->whereIn('o.lead_id', $leadIds);
                $daCo = true;
            }

            if ($customerIds->isNotEmpty() && SchemaCache::hasColumn('crm_orders', 'customer_id')) {
                $daCo
                    ? $q->orWhereIn('o.customer_id', $customerIds)
                    : $q->whereIn('o.customer_id', $customerIds);
            }
        });

        return $query->orderByDesc('o.id')->limit(self::ORDER_LIMIT)->get();
    }

    /**
     * @param  Collection<int, int>  $orderIds
     * @return Collection<int|string, mixed>
     */
    private function paidByOrder(Collection $orderIds): Collection
    {
        foreach (self::PAYMENT_TABLES as $bang) {
            if (SchemaCache::hasTable($bang)
                && SchemaCache::hasColumn($bang, 'order_id')
                && SchemaCache::hasColumn($bang, 'amount')) {
                return DB::table($bang)
                    ->whereIn('order_id', $orderIds)
                    ->select('order_id', DB::raw('SUM(amount) as paid'))
                    ->groupBy('order_id')
                    ->pluck('paid', 'order_id');
            }
        }

        return collect();
    }

    /**
     * @param  Collection<int, int>  $customerIds
     * @param  Collection<int, int>  $orderIds
     * @return Collection<int, object>
     */
    private function documents(int $profileId, Collection $customerIds, Collection $orderIds): Collection
    {
        if (! SchemaCache::hasTable('crm_order_documents')) {
            return collect();
        }

        return DB::table('crm_order_documents')
            ->where(function ($q) use ($profileId, $customerIds, $orderIds): void {
                if (SchemaCache::hasColumn('crm_order_documents', 'customer_profile_id')) {
                    $q->where('customer_profile_id', $profileId);
                } else {
                    // Không có cột nối theo hồ sơ thì bắt đầu từ tập rỗng, để hai
                    // nhánh orWhere bên dưới quyết định.
                    $q->whereRaw('1 = 0');
                }

                if ($customerIds->isNotEmpty() && SchemaCache::hasColumn('crm_order_documents', 'customer_id')) {
                    $q->orWhereIn('customer_id', $customerIds);
                }

                if ($orderIds->isNotEmpty() && SchemaCache::hasColumn('crm_order_documents', 'order_id')) {
                    $q->orWhereIn('order_id', $orderIds);
                }
            })
            ->orderByDesc('id')
            ->get();
    }
}
