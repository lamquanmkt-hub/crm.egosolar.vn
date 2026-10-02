<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Danh sách đơn gần đây của kỳ, kèm hoa hồng từng đơn.
 *
 * Chỉ hiện đơn có sales thuộc nhóm sales thật; đơn không gắn ai sẽ hiện "---"
 * và làm người đọc tưởng thiếu dữ liệu, nên lọc thẳng từ câu truy vấn.
 */
final class RecentOrdersBuilder
{
    /** Đủ để soi lại một tháng mà không kéo cả bảng đơn về. */
    private const LIMIT = 200;

    private const UNKNOWN_SALES = '---';

    public function __construct(
        private readonly OrderColumnMap $cols,
        private readonly OrderPaidAmount $paidAmount,
        private readonly OrderBeforeVatAmount $beforeVatAmount,
        private readonly OrderProductSummary $productSummary,
        private readonly OrderCustomerLookup $customers,
        private readonly CommissionEligibilityPolicy $eligibility,
        private readonly RecordedCommissionQuery $recorded,
        private readonly CommissionCalculator $calculator,
    ) {}

    /**
     * @param  Collection<int, object>  $salesRows  để tra tên và doanh số tháng của sales
     * @param  list<int>  $validSalesIds
     * @return Collection<int, object>
     */
    public function build(Collection $salesRows, array $validSalesIds, int $filterSalesId, SalesPeriod $period): Collection
    {
        if ($this->cols->orderTable === null || $this->cols->dateCol === null || $this->cols->totalCol === null) {
            return collect();
        }

        return $this->query($validSalesIds, $filterSalesId, $period)
            ->get()
            ->map(fn ($order) => $this->row($order, $salesRows, $period));
    }

    /** @param  list<int>  $validSalesIds */
    private function query(array $validSalesIds, int $filterSalesId, SalesPeriod $period): \Illuminate\Database\Query\Builder
    {
        $query = DB::table((string) $this->cols->orderTable)
            ->whereBetween((string) $this->cols->dateCol, $period->range());

        $salesCol = $this->cols->salesCol;

        if ($salesCol === null) {
            // Không biết đơn của ai thì không thể lọc, thà không hiện gì.
            $query->whereRaw('1 = 0');
        } elseif ($filterSalesId > 0) {
            $query->where($salesCol, $filterSalesId);
        } elseif ($validSalesIds !== []) {
            $query->whereIn($salesCol, $validSalesIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->orderByDesc((string) $this->cols->dateCol)->limit(self::LIMIT);
    }

    /** @param  Collection<int, object>  $salesRows */
    private function row(object $order, Collection $salesRows, SalesPeriod $period): object
    {
        $salesId = $this->cols->salesCol ? (int) ($order->{$this->cols->salesCol} ?? 0) : 0;
        $sales = $salesRows->firstWhere('id', $salesId);

        $total = $this->cols->totalCol ? (float) ($order->{$this->cols->totalCol} ?? 0) : 0.0;
        $paid = $this->paidAmount->forOrder($order);
        $beforeVat = $this->beforeVatAmount->forOrder($order);
        $eligible = $this->eligibility->isEligible($order, $total, $paid);

        $commission = $eligible
            ? $this->commissionFor($order, $salesId, $total, $beforeVat, $sales, $period)
            : 0.0;

        return (object) [
            'id' => $order->id ?? null,
            'code' => $this->codeOf($order),
            'date' => $this->cols->dateCol ? ($order->{$this->cols->dateCol} ?? '') : '',
            'customer' => $this->customers->name($order),
            'sales' => $sales->name ?? self::UNKNOWN_SALES,
            'total' => $total,
            'before_vat' => $beforeVat,
            'paid' => $paid,
            'debt' => max(0, $total - $paid),
            'commission' => $commission,
            'commission_rate' => $beforeVat > 0 ? ($commission / $beforeVat) * 100 : 0,
            'commission_eligible' => $eligible,
            'status' => $this->cols->statusCol ? ($order->{$this->cols->statusCol} ?? '') : '',
        ];
    }

    private function commissionFor(
        object $order,
        int $salesId,
        float $total,
        float $beforeVat,
        ?object $sales,
        SalesPeriod $period,
    ): float {
        $recorded = $this->recorded->forOrder($order->id ?? null, $salesId, $period);

        if ($recorded > 0) {
            return $recorded;
        }

        $products = $this->productSummary->forOrder($order);

        return $this->calculator->forOrder(
            $beforeVat,
            $total,
            (float) ($sales->eligible_revenue_before_vat ?? 0),
            $this->customers->status($order),
            $products->quantity,
            $products->text,
        );
    }

    /** Giữ nguyên kiểu giá trị trong CSDL, không ép chuỗi — view tự in. */
    private function codeOf(object $order): mixed
    {
        $fallback = '#'.($order->id ?? '');
        $codeCol = $this->cols->codeCol;

        return $codeCol ? ($order->{$codeCol} ?? $fallback) : $fallback;
    }
}
