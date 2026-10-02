<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Đọc hoa hồng ĐÃ CHỐT trong `crm_sales_commissions`.
 *
 * Số đã chốt luôn thắng số tính lại: một khi kế toán đã ghi nhận, báo cáo phải
 * hiện đúng con số đó dù quy tắc hiện hành có đổi. Chỉ khi chưa có bản ghi mới
 * rơi xuống {@see CommissionCalculator}.
 *
 * Trước đây phần này viết hai lần — một bản cộng theo sales, một bản theo từng
 * đơn — mỗi bản tự lặp lại cách lọc kỳ, mà cách lọc ấy lại phụ thuộc kiểu cột
 * ngày tìm được.
 */
final class RecordedCommissionQuery
{
    private const TABLE = 'crm_sales_commissions';

    /** Cột kiểu 'Y-m' thì so bằng, còn lại là mốc thời gian nên so khoảng. */
    private const MONTH_COLUMNS = ['period_month', 'commission_month'];

    private readonly ?string $table;

    private readonly ?string $salesCol;

    private readonly ?string $amountCol;

    private readonly ?string $dateCol;

    private readonly bool $hasOrderId;

    public function __construct(CommissionSchema $schema)
    {
        $this->table = $schema->hasTable(self::TABLE) ? self::TABLE : null;
        $this->salesCol = $schema->firstColumn($this->table, ['sales_id', 'sale_id', 'user_id', 'created_by']);
        $this->amountCol = $schema->firstColumn($this->table, ['commission_amount', 'total_commission', 'amount', 'commission']);
        $this->dateCol = $schema->firstColumn($this->table, ['period_month', 'commission_month', 'created_at', 'date']);
        $this->hasOrderId = $this->table !== null && $schema->hasColumn($this->table, 'order_id');
    }

    /**
     * Tổng hoa hồng đã chốt của một sales cho đúng những đơn đủ điều kiện.
     *
     * @param  list<int>  $orderIds
     * @return float|null null = chưa ghi nhận được, hãy tính theo quy tắc
     */
    public function forSales(int $salesId, array $orderIds, SalesPeriod $period): ?float
    {
        if (! $this->isUsable() || $this->salesCol === null || $orderIds === []) {
            return null;
        }

        $query = DB::table((string) $this->table)
            ->where($this->salesCol, $salesId)
            ->whereIn('order_id', $orderIds);

        return (float) $this->inPeriod($query, $period)->sum((string) $this->amountCol);
    }

    /** Hoa hồng đã chốt của MỘT đơn; 0 nghĩa là chưa có, hãy tính theo quy tắc. */
    public function forOrder(mixed $orderId, int $salesId, SalesPeriod $period): float
    {
        if (! $this->isUsable() || ! $orderId) {
            return 0.0;
        }

        try {
            $query = DB::table((string) $this->table)->where('order_id', $orderId);

            if ($this->salesCol !== null && $salesId > 0) {
                $query->where($this->salesCol, $salesId);
            }

            return (float) $this->inPeriod($query, $period)->sum((string) $this->amountCol);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private function isUsable(): bool
    {
        return $this->table !== null && $this->amountCol !== null && $this->hasOrderId;
    }

    private function inPeriod(Builder $query, SalesPeriod $period): Builder
    {
        if ($this->dateCol === null) {
            return $query;
        }

        return in_array($this->dateCol, self::MONTH_COLUMNS, true)
            ? $query->where($this->dateCol, $period->month)
            : $query->whereBetween($this->dateCol, $period->range());
    }
}
