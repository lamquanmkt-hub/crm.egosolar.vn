<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Services\CRM\Commission\CommissionEngineService;
use App\Services\Sales\Commission\CommissionCalculator;
use App\Services\Sales\Commission\CommissionEligibilityPolicy;
use App\Services\Sales\Commission\CommissionNumberFormat;
use App\Services\Sales\Commission\CommissionSchema;
use App\Services\Sales\Commission\KpiBonusCalculator;
use App\Services\Sales\Commission\OrderBeforeVatAmount;
use App\Services\Sales\Commission\OrderColumnMap;
use App\Services\Sales\Commission\OrderCustomerLookup;
use App\Services\Sales\Commission\OrderItemReader;
use App\Services\Sales\Commission\OrderPaidAmount;
use App\Services\Sales\Commission\OrderProductSummary;
use App\Services\Sales\Commission\RecentOrdersBuilder;
use App\Services\Sales\Commission\RecordedCommissionQuery;
use App\Services\Sales\Commission\SalesOrderAggregator;
use App\Services\Sales\Commission\SalesPeriod;
use App\Services\Sales\Commission\SalesRowBuilder;
use Illuminate\Support\Facades\Log;

/**
 * Dữ liệu cho trang hoa hồng sales.
 *
 * ## Vì sao có lớp này
 * Toàn bộ phần tính toán từng nằm trong MỘT khối `@php` 1.299 dòng ngay đầu
 * `resources/views/sales/commissions/index.blade.php` — gồm cả 16 lệnh `DB::`,
 * dò schema và đọc `request()`. View vừa lấy dữ liệu vừa hiển thị.
 *
 * Nặng hơn: controller `SalesCommissionController@index` cũng chạy một bộ truy vấn
 * riêng rồi truyền 15 biến sang, nhưng view KHÔNG dùng biến nào cả — 5 biến không
 * đụng tới, 10 biến bị gán đè ngay trong khối `@php`. Tức mỗi lần mở trang, cả một
 * loạt truy vấn chạy rồi kết quả bị vứt.
 *
 * ## Lớp này làm gì hôm nay
 * Chỉ còn điều phối: nhận tháng và bộ lọc, dựng các cộng tác viên bên dưới, gom
 * kết quả cho view. Mỗi phép tính nằm ở một lớp riêng trong `Commission\`, đặt
 * tên theo đúng câu hỏi nghiệp vụ nó trả lời.
 *
 * Đợt tách được làm từng bước; sau MỖI bước đều render lại trang và so HTML
 * từng byte với bản gốc, nên giao diện không đổi một ký tự nào.
 *
 * ## Vì sao dựng bằng `new` chứ không tiêm qua container
 * Gần hết các cộng tác viên phụ thuộc thứ chỉ biết lúc chạy: bản đồ cột vừa dò
 * được, bộ quy tắc của kỳ, danh sách bậc KPI của tháng. Chúng sống đúng một
 * lượt dựng báo cáo nên không phải singleton.
 *
 * @see \App\Http\Controllers\CRM\SalesCommissionController::index()
 */
final class CommissionReportData
{
    public function __construct(
        private readonly CommissionNumberFormat $format,
        private readonly CommissionSchema $schema,
        private readonly CommissionEngineService $engine,
    ) {}

    /**
     * @param  string  $month  dạng `Y-m`; rỗng thì lấy tháng hiện tại
     * @param  int  $filterSalesId  0 = không lọc theo nhân viên
     * @return array<string, mixed> đúng 23 biến mà template dùng
     */
    public function build(string $month = '', int $filterSalesId = 0): array
    {
        $month = $month !== '' ? $month : now()->format('Y-m');

        try {
            $periodStart = \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        } catch (\Throwable $e) {
            $month = now()->format('Y-m');
            $periodStart = now()->startOfMonth();
        }

        $periodEnd = $periodStart->copy()->endOfMonth();
        $prevMonth = $periodStart->copy()->subMonth()->format('Y-m');
        $nextMonth = $periodStart->copy()->addMonth()->format('Y-m');

        /*
         * Thiếu chính sách thì trang vẫn phải mở được, nhưng KHÔNG được im lặng:
         * tham chiếu sai lớp ở đây từng làm trang rỗng suốt nhiều tuần mà không ai
         * biết, vì `catch` nuốt luôn lỗi không phân giải được lớp.
         */
        try {
            $policyData = $this->engine->settingsViewData($month);
        } catch (\Throwable $e) {
            Log::warning('COMMISSION_POLICY_LOAD_FAILED', [
                'month' => $month,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $policyData = [];
        }

        $policy = $policyData['policy'] ?? (object) [
            'period_month' => $month,
            'project_rate_percent' => 4,
            'trade_rate_percent' => 1,
            'panel_fixed_amount' => 15000,
            'only_paid' => 0,
            'only_shipped' => 0,
            'only_completed' => 0,
            'hold_if_debt' => 1,
        ];

        $rules = collect($policyData['rules'] ?? []);
        $activeRules = $rules->where('is_active', 1)->count();

        $salesUsers = collect($policyData['salesUsers'] ?? []);
        $salarySettings = collect($policyData['salarySettings'] ?? []);
        $kpiTiers = collect($policyData['kpiTiers'] ?? []);

        /*
         * Lược đồ thật của CSDL được dò một lần rồi truyền đi, thay vì mỗi phép
         * tính tự dò lại. Các đối tượng dưới đây dựng bằng `new` chứ không tiêm
         * qua container: chúng phụ thuộc bản đồ cột vừa dò, nên chỉ sống trong
         * một lượt dựng báo cáo.
         */
        $cols = OrderColumnMap::discover($this->schema);
        $items = new OrderItemReader($cols);

        $customers = new OrderCustomerLookup($this->schema, $cols);
        $paidAmount = new OrderPaidAmount($cols);
        $beforeVatAmount = new OrderBeforeVatAmount($cols, $items);
        $productSummary = new OrderProductSummary($items);
        // Điều kiện tính hoa hồng đọc thẳng từ chính sách của kỳ.
        $eligibility = new CommissionEligibilityPolicy($cols, $policy);

        $period = new SalesPeriod(
            $month,
            $periodStart->format('Y-m-d').' 00:00:00',
            $periodEnd->format('Y-m-d').' 23:59:59',
        );
        $recorded = new RecordedCommissionQuery($this->schema);
        $commissionAmountByRules = new CommissionCalculator($rules, $policy);

        $aggregator = new SalesOrderAggregator(
            $cols, $paidAmount, $beforeVatAmount, $productSummary, $customers, $eligibility,
        );

        $salesRows = (new SalesRowBuilder(
            $aggregator,
            $recorded,
            $commissionAmountByRules,
            new KpiBonusCalculator($kpiTiers),
        ))->build($salesUsers, $salarySettings, $period);

        /* EGO_FILTER_SALES_ROWS_START */
        $selectedSalesUser = $filterSalesId > 0
            ? $salesUsers->firstWhere('id', $filterSalesId)
            : null;

        if ($filterSalesId > 0 && ! $selectedSalesUser) {
            $filterSalesId = 0;
        }

        if ($filterSalesId > 0) {
            $salesRows = $salesRows
                ->filter(fn ($r) => (int) ($r->id ?? 0) === $filterSalesId)
                ->values();
        }
        /* EGO_FILTER_SALES_ROWS_END */

        $totalRevenue = $salesRows->sum('revenue');
        $totalPaid = $salesRows->sum('paid');
        $totalDebt = $salesRows->sum('debt');
        $totalBaseSalary = $salesRows->sum('base_salary');
        $totalCommission = $salesRows->sum('commission');
        $totalKpiBonus = $salesRows->sum('kpi_bonus');
        $totalIncome = $salesRows->sum('total_income');
        $totalOrders = $salesRows->sum('order_count');
        $topSales = $salesRows->first();
        $maxRevenue = max(1, $salesRows->max('revenue') ?: 1);

        $validSalesIds = $salesUsers
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        $recentOrders = (new RecentOrdersBuilder(
            $cols, $paidAmount, $beforeVatAmount, $productSummary, $customers,
            $eligibility, $recorded, $commissionAmountByRules,
        ))->build($salesRows, $validSalesIds, $filterSalesId, $period);

        return [
            'fmt' => $this->format,
        ] + compact(
            'activeRules',
            'filterSalesId',
            'maxRevenue',
            'month',
            'nextMonth',
            'policy',
            'prevMonth',
            'recentOrders',
            'salesRows',
            'salesUsers',
            'selectedSalesUser',
            'topSales',
            'totalCommission',
            'totalDebt',
            'totalIncome',
            'totalKpiBonus',
            'totalOrders',
            'totalPaid',
            'totalRevenue',
        );
    }
}
