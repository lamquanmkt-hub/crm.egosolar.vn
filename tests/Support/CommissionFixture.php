<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu cố định cho miền hoa hồng sales.
 *
 * ## Vì sao cần
 * CSDL test rỗng hoàn toàn ở bảng đơn hàng, nên mọi phép so ảnh chụp trang hoa
 * hồng trước đây chỉ chạm được nhánh "không có dữ liệu": phần tính tiền chưa hề
 * được chạy lần nào. Fixture này dựng đúng những tình huống mà code phân nhánh,
 * để con số hoa hồng bị ghim lại bằng test thay vì bằng niềm tin.
 *
 * ## Các nhánh được phủ
 * - Trước VAT nguồn 1: đơn có `tax_amount` -> lấy tổng sau VAT trừ thuế.
 * - Trước VAT nguồn 2: đơn KHÔNG có thuế, dòng hàng `vat_percent = 0`
 *   -> phải tra bảng giá `crm_product_prices`.
 * - Đủ điều kiện / không: thu đủ, thu thiếu (còn công nợ), và đơn tổng bằng 0.
 * - Khách qua `lead_id`: tên và nhóm khách phải lần qua lead rồi tới khách.
 *   Cột `customer_status` là ENUM('lead','member','retail') nên fixture dùng đúng
 *   ba giá trị đó, không tự đặt chuỗi mới.
 * - `crm_order_items.line_total` là cột SINH TỰ ĐỘNG từ số lượng, đơn giá và
 *   chiết khấu — không được chèn tay, CSDL sẽ từ chối.
 * - Quy tắc: có mốc doanh thu (0,5% từ 500tr), quy tắc chung 1%, quy tắc tấm
 *   pin tính theo từng món, và một quy tắc đã tắt để chắc chắn bị bỏ qua.
 * - KPI: hai bậc, và một sales KHÔNG có thiết lập lương để chạy giá trị mặc định.
 */
final class CommissionFixture
{
    public const MONTH = '2026-09';

    public const SALES_A = 970601;

    public const SALES_B = 970602;

    /** Tấm pin — khớp quy tắc theo từ khoá. */
    public const PRODUCT_PANEL = 970901;

    public const TIER = 970301;

    public const WAREHOUSE = 970001;

    public static function seed(): void
    {
        self::referenceRows();
        self::salesUsers();
        self::customersAndLeads();
        self::products();
        self::policyAndRules();
        self::kpiAndSalary();
        self::orders();
    }

    /** Bậc giá và kho — chỉ để thoả khoá ngoại, không ảnh hưởng phép tính. */
    private static function referenceRows(): void
    {
        DB::table('crm_price_tiers')->insert([
            'id' => self::TIER, 'code' => 'TIER-TEST', 'name' => 'Bac gia kiem thu', 'priority' => 1, 'is_active' => 1,
        ]);

        DB::table('crm_warehouses')->insert([
            'id' => self::WAREHOUSE, 'name' => 'Kho kiem thu',
        ]);
    }

    private static function salesUsers(): void
    {
        /*
         * CSDL test chỉ có sẵn vai trò `admin`; `assignRole('sales')` sẽ ném
         * RoleDoesNotExist nếu chưa tạo. Trước đây fixture chạy được là nhờ test
         * gọi `userWithRole()` (hàm này tự `findOrCreate`) TRƯỚC khi seed — tức
         * phụ thuộc thứ tự, hỏng ngay khi có test seed trực tiếp trong setUp.
         */
        \Spatie\Permission\Models\Role::findOrCreate('sales', 'web');

        foreach ([self::SALES_A => 'Sales A', self::SALES_B => 'Sales B'] as $id => $name) {
            User::factory()->create([
                'id' => $id,
                'name' => $name,
                'email' => 'sales'.$id.'@example.test',
                // Truy vấn tìm sales lọc `is_active = 1`; factory không đặt cột này.
                'is_active' => 1,
            ])->assignRole('sales');
        }
    }

    private static function customersAndLeads(): void
    {
        DB::table('crm_customers')->insert([
            ['id' => 970701, 'name' => 'Cong ty Lead ADS', 'customer_status' => 'lead'],
            ['id' => 970702, 'name' => 'Khach le Nguyen Van B', 'customer_status' => 'retail'],
        ]);

        DB::table('crm_leads')->insert([
            ['id' => 970801, 'customer_id' => 970701],
            ['id' => 970802, 'customer_id' => 970702],
        ]);
    }

    private static function products(): void
    {
        DB::table('crm_product_catalog')->insert([
            ['id' => self::PRODUCT_PANEL, 'name' => 'Tam pin mat troi 580W', 'sku' => 'PANEL-580', 'vat_percent' => 8],
            ['id' => 970902, 'name' => 'Inverter 5kW', 'sku' => 'INV-5K', 'vat_percent' => 10],
        ]);

        // Giá TRƯỚC VAT — nguồn duy nhất tra ngược được khi dòng hàng không lưu thuế.
        DB::table('crm_product_prices')->insert([
            ['product_id' => self::PRODUCT_PANEL, 'price_tier_id' => self::TIER, 'price' => 4_000_000],
            ['product_id' => 970902, 'price_tier_id' => self::TIER, 'price' => 20_000_000],
        ]);
    }

    private static function policyAndRules(): void
    {
        DB::table('crm_commission_policies')->insert([
            'id' => 970401,
            'name' => 'Chinh sach thang '.self::MONTH,
            'period_month' => self::MONTH,
            'trade_rate_percent' => 1,
            'panel_fixed_amount' => 15000,
            'only_paid' => 1,
            'hold_if_debt' => 1,
            'only_shipped' => 0,
            'only_completed' => 0,
            'is_active' => 1,
        ]);

        $rule = fn (array $extra) => array_merge([
            'policy_id' => 970401,
            'period_month' => self::MONTH,
            'target_type' => 'all',
            'base_type' => 'revenue_before_vat',
            'calculation_type' => 'percent',
            'is_active' => 1,
            'priority' => 10,
        ], $extra);

        $rules = [
            // 0,5% nhưng chỉ khi doanh số tháng của sales đạt từ 500 triệu.
            $rule(['id' => 970501, 'commission_type' => 'trade_product', 'target_type' => 'customer_status',
                'target_text' => 'lead, ads', 'rate_percent' => 0.5, 'from_amount' => 500_000_000, 'priority' => 30]),
            // Quy tắc chung 1%.
            $rule(['id' => 970502, 'commission_type' => 'trade_product', 'rate_percent' => 1, 'priority' => 20]),
            // Thưởng thêm theo từng tấm pin.
            $rule(['id' => 970503, 'commission_type' => 'solar_panel', 'target_type' => 'keyword',
                'target_text' => 'tam pin', 'calculation_type' => 'fixed_per_item', 'amount_per_unit' => 15000, 'priority' => 40]),
            // Đã tắt — phải bị bỏ qua hoàn toàn.
            $rule(['id' => 970504, 'commission_type' => 'trade_product', 'rate_percent' => 99, 'is_active' => 0, 'priority' => 99]),
        ];

        // Chèn từng dòng: mỗi quy tắc dùng một tập cột khác nhau.
        foreach ($rules as $row) {
            DB::table('crm_commission_rules')->insert($row);
        }
    }

    private static function kpiAndSalary(): void
    {
        DB::table('crm_sales_kpi_tiers')->insert([
            ['id' => 970201, 'period_month' => self::MONTH, 'tier_name' => 'Bac 1 - Duoi 500tr',
                'from_revenue' => 0, 'to_revenue' => 499_999_999, 'bonus_type' => 'percent_revenue',
                'bonus_amount' => 0.1, 'is_active' => 1, 'priority' => 10],
            ['id' => 970202, 'period_month' => self::MONTH, 'tier_name' => 'Bac 2 - Tu 500tr',
                'from_revenue' => 500_000_000, 'to_revenue' => null, 'bonus_type' => 'fixed',
                'bonus_amount' => 1_000_000, 'is_active' => 1, 'priority' => 50],
        ]);

        // CHỈ sales A có thiết lập lương; sales B phải rơi về giá trị mặc định.
        DB::table('crm_sales_salary_settings')->insert([
            'id' => 970101, 'period_month' => self::MONTH, 'sales_id' => self::SALES_A,
            'base_salary' => 8_000_000, 'target_revenue' => 600_000_000, 'target_commission' => 0, 'is_active' => 1,
        ]);
    }

    private static function orders(): void
    {
        $order = fn (array $extra) => array_merge([
            'order_date' => self::MONTH.'-15',
            'price_tier_id' => self::TIER,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'inventory_issued' => 1,
        ], $extra);

        DB::table('crm_orders')->insert([
            // Thu đủ, có thuế -> trước VAT = 550tr - 40.740.741.
            $order(['id' => 971001, 'order_code' => 'DH-971001', 'lead_id' => 970801, 'created_by' => self::SALES_A,
                'total_amount' => 550_000_000, 'tax_amount' => 40_740_741]),
            // Còn công nợ -> KHÔNG đủ điều kiện.
            $order(['id' => 971002, 'order_code' => 'DH-971002', 'lead_id' => 970802, 'created_by' => self::SALES_A,
                'total_amount' => 110_000_000, 'tax_amount' => 10_000_000]),
            // Không có thuế -> phải tra bảng giá qua dòng hàng.
            $order(['id' => 971003, 'order_code' => 'DH-971003', 'lead_id' => 970802, 'created_by' => self::SALES_B,
                'total_amount' => 21_600_000, 'tax_amount' => 0]),
            // Tổng bằng 0 -> KHÔNG đủ điều kiện.
            $order(['id' => 971004, 'order_code' => 'DH-971004', 'lead_id' => 970801, 'created_by' => self::SALES_B,
                'total_amount' => 0, 'tax_amount' => 0]),
        ]);

        DB::table('crm_order_items')->insert([
            ['id' => 971101, 'order_id' => 971003, 'warehouse_id' => self::WAREHOUSE, 'product_id' => self::PRODUCT_PANEL,
                'price_tier_id' => self::TIER, 'product_name' => 'Tam pin mat troi 580W',
                'quantity' => 5, 'unit_price' => 4_320_000, 'vat_percent' => 0],
        ]);

        DB::table('crm_payments')->insert([
            ['order_id' => 971001, 'payment_date' => self::MONTH.'-20', 'amount' => 550_000_000],
            ['order_id' => 971002, 'payment_date' => self::MONTH.'-20', 'amount' => 50_000_000],
            ['order_id' => 971003, 'payment_date' => self::MONTH.'-20', 'amount' => 21_600_000],
        ]);
    }
}
