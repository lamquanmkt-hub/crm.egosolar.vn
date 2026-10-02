<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CRM\Commission\CommissionEngineService;
use App\Services\Sales\Commission\CommissionEligibilityPolicy;
use App\Services\Sales\Commission\CommissionSchema;
use App\Services\Sales\Commission\OrderColumnMap;
use App\Services\Sales\SalesCommissionScope;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ghi hoa hồng đã chốt vào `sales_commissions` theo ĐÚNG chính sách của kỳ.
 *
 * ## Bản cũ ghi toàn số 0
 * Bản trước có dòng `$rate = 0; // tạm thời` nên mọi bản ghi đều
 * `rate = 0, commission_amount = 0`; nó còn lấy `base_amount` từ
 * `crm_orders.total_amount` (tổng SAU VAT) trong khi chính sách tính trên doanh
 * thu TRƯỚC VAT. Trên production để lại 120 dòng toàn số 0 (ghi 2026-03-31).
 *
 * Nguy hiểm ở chỗ: {@see \App\Services\Sales\Commission\RecordedCommissionQuery}
 * ưu tiên số ĐÃ CHỐT hơn số tính lại. Nếu trỏ nó sang bảng này khi dữ liệu còn
 * toàn 0 thì báo cáo sẽ hiện 0 đồng cho những đơn có hoa hồng thật.
 *
 * ## Bản này
 * Không tự tính lại công thức nào — dùng lại đúng ba mảnh mà màn hình và PDF
 * dùng:
 *   - {@see SalesCommissionScope} chọn nhân sự kinh doanh,
 *   - {@see CommissionEngineService::recalculateRows()} tính theo chính sách kỳ,
 *   - {@see CommissionEligibilityPolicy} lọc đơn đủ điều kiện.
 *
 * Ghi bằng `updateOrInsert` theo `order_id` nên chạy lại được nhiều lần và sửa
 * được các dòng 0 cũ.
 */
final class BackfillSalesCommission extends Command
{
    protected $signature = 'commission:backfill
        {--month= : Chỉ xử lý một kỳ YYYY-MM. Bỏ trống = mọi kỳ có đơn đã thu đủ tiền}
        {--dry-run : Chỉ in ra sẽ ghi gì, KHÔNG đụng cơ sở dữ liệu}';

    protected $description = 'Ghi hoa hồng đã chốt theo chính sách của từng kỳ (chạy lại được)';

    /** Số đơn mỗi lô — xem ghi chú trong handle(). */
    private const CHUNK = 10;

    public function handle(CommissionEngineService $engine): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyMonth = $this->option('month') ? (string) $this->option('month') : null;

        $orders = $this->eligibleOrders($onlyMonth);

        if ($orders->isEmpty()) {
            $this->warn('Không có đơn nào đủ điều kiện.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Tìm thấy %d đơn đã thu đủ tiền do nhân sự kinh doanh tạo.', $orders->count()));

        $eligibilityCols = OrderColumnMap::discover(app(CommissionSchema::class));
        $written = 0;
        $skipped = 0;
        $totalCommission = 0.0;

        foreach ($orders->groupBy('period_month') as $month => $group) {
            $policy = $engine->currentPolicy((string) $month);
            $rules = $engine->rulesForPolicy((int) $policy->id);
            $eligibility = new CommissionEligibilityPolicy($eligibilityCols, $policy);

            /*
             * Chia lô nhỏ. Gọi `recalculateRows()` với cả tháng một lượt bị
             * hệ điều hành GIẾT trên hosting dùng chung: đo 2026-09-05 với 59 đơn
             * tháng 4/2026, tiến trình chết sau 1m44s dù `memory_limit` đã nâng
             * lên 1G, trong khi chạy từng đơn thì bộ nhớ phẳng ở 42,5 MB. Lô nhỏ
             * chặn được cả hai kiểu tăng trưởng mà không cần biết chỗ nào tích tụ.
             */
            $ordered = $group->values();

            foreach ($ordered->chunk(self::CHUNK) as $offset => $chunk) {
                $rows = $engine->recalculateRows(
                    $chunk->map(fn ($o) => (object) ['order_id' => $o->id])->all(),
                    $policy,
                    $rules,
                );

                $this->writeChunk($rows, $chunk->values(), $eligibility, (string) $month,
                    $dryRun, $written, $skipped, $totalCommission);
            }
        }

        $this->line(sprintf('  ghi:  %d dòng | bỏ qua (không đủ điều kiện): %d', $written, $skipped));
        $this->line(sprintf('  tổng hoa hồng: %s đ', number_format($totalCommission)));

        if ($dryRun) {
            $this->warn('  --dry-run: KHÔNG ghi gì vào cơ sở dữ liệu.');
        }

        return self::SUCCESS;
    }

    /**
     * Ghi một lô, cộng dồn số đếm qua tham chiếu.
     *
     * @param  iterable<int, object>  $rows
     * @param  \Illuminate\Support\Collection<int, object>  $orders
     */
    private function writeChunk(
        iterable $rows,
        $orders,
        CommissionEligibilityPolicy $eligibility,
        string $month,
        bool $dryRun,
        int &$written,
        int &$skipped,
        float &$totalCommission,
    ): void {

        foreach ($rows as $i => $row) {
            $order = $orders[$i];
            $afterVat = (float) ($row->revenue_after_vat ?? 0);
            $paid = (float) $order->paid_total;

            if (! $eligibility->isEligible($order, $afterVat, $paid)) {
                $skipped++;

                continue;
            }

            $payload = [
                'customer_id' => (int) ($order->customer_id ?? 0),
                'sales_user_id' => (int) $order->created_by,
                'base_amount' => round((float) ($row->total_amount ?? 0), 2),
                'rate' => round((float) ($row->rate_percent ?? 0), 4),
                'commission_amount' => round((float) ($row->commission_calc ?? 0), 2),
                'status' => 'auto',
                'period_month' => (string) $month,
                'updated_at' => now(),
            ];

            $totalCommission += $payload['commission_amount'];
            $written++;

            if ($dryRun) {
                continue;
            }

            DB::table('sales_commissions')->updateOrInsert(
                ['order_id' => (int) $order->id],
                $payload + ['created_at' => now()],
            );
        }

    }

    /**
     * Đơn đã thu đủ tiền, do nhân sự kinh doanh tạo, kèm kỳ suy từ ngày thanh
     * toán cuối cùng.
     *
     * Kỳ lấy theo NGÀY THANH TOÁN CUỐI chứ không phải ngày tạo đơn: hoa hồng
     * phát sinh khi tiền về, và đó cũng là cách màn hình lọc kỳ.
     */
    private function eligibleOrders(?string $onlyMonth): \Illuminate\Support\Collection
    {
        $payments = DB::table('crm_payments as p')
            ->select(
                'p.order_id',
                DB::raw('SUM(COALESCE(p.amount, 0)) as paid_total'),
                DB::raw('MAX(COALESCE(p.payment_date, p.created_at)) as final_payment_date'),
            )
            ->groupBy('p.order_id');

        $query = DB::table('crm_orders as o')
            ->joinSub($payments, 'pay', fn ($join) => $join->on('pay.order_id', '=', 'o.id'))
            ->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
            ->join('users as u', 'u.id', '=', 'o.created_by')
            ->whereNotNull('pay.final_payment_date')
            ->whereRaw('COALESCE(pay.paid_total, 0) >= COALESCE(o.total_amount, 0)')
            ->where('u.name', '!=', SalesCommissionScope::EXCLUDED_SALES_NAME)
            ->select('o.*', 'pay.paid_total', 'pay.final_payment_date', 'l.customer_id');

        SalesCommissionScope::constrainToSalesStaff($query, 'u');

        if ($onlyMonth !== null) {
            $start = Carbon::createFromFormat('Y-m', $onlyMonth)->startOfMonth();
            $query->whereBetween('pay.final_payment_date', [$start, $start->copy()->endOfMonth()]);
        }

        return $query->get()->map(function ($order) {
            $order->period_month = Carbon::parse($order->final_payment_date)->format('Y-m');

            return $order;
        });
    }
}
