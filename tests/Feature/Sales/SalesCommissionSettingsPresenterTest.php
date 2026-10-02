<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\DTOs\Sales\CommissionRuleRow;
use App\View\Presenters\Sales\SalesCommissionSettingsPresenter;
use Tests\TestCase;

/** {@see SalesCommissionSettingsPresenter} thay 4 khối `@php` (119 dòng) của sales/commissions/settings (2026-09-08). Không cần DB. */
final class SalesCommissionSettingsPresenterTest extends TestCase
{
    private SalesCommissionSettingsPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new SalesCommissionSettingsPresenter;
    }

    public function test_dong_quy_tac_dien_giai_khoang_doanh_thu_cach_tinh_va_doi_tuong(): void
    {
        $rows = $this->presenter->viewData(collect([
            $this->rule(['rate_percent' => 2.5, 'from_amount' => '10000000.00', 'to_amount' => '50000000.00', 'target_type' => 'brand', 'target_text' => 'Jinko']),
            $this->rule(['commission_type' => 'solar_panel', 'calculation_type' => 'fixed_per_kwp', 'amount_per_kwp' => 120000, 'base_type' => 'kwp', 'from_amount' => '5000000.00']),
            $this->rule(['commission_type' => 'project', 'calculation_type' => 'fixed_per_order', 'fixed_amount' => 300000, 'to_amount' => '20000000.00', 'is_active' => 0]),
            $this->rule(['target_type' => 'customer_status', 'target_text' => 'lead', 'base_type' => 'gross_profit', 'rate_percent' => 0.5, 'target_id' => 77]),
            $this->rule(['target_type' => 'product', 'target_id' => 4242, 'calculation_type' => 'fixed_per_item', 'amount_per_unit' => 25000, 'fixed_amount' => 0]),
            $this->rule(['target_type' => 'customer_status', 'target_text' => null, 'target_id' => null]),
        ]), collect(), collect(), collect(), (object) [])['ruleRows'];

        $this->assertContainsOnlyInstancesOf(CommissionRuleRow::class, $rows);
        $this->assertSame(['10.000.000 đ - 50.000.000 đ', '2,50%', 'Thương hiệu: Jinko', 'Tất cả khách', '% doanh thu', 'Thương mại', true], [
            $rows[0]->revenueRange, $rows[0]->valueText, $rows[0]->applyText, $rows[0]->customerStatusText, $rows[0]->calcText, $rows[0]->typeText, $rows[0]->isActive,
        ]);
        $this->assertSame(['Từ 5.000.000 đ', '120.000 đ/kWp', 'kWp', 'Tấm pin'], [$rows[1]->revenueRange, $rows[1]->valueText, $rows[1]->baseText, $rows[1]->typeText]);
        $this->assertSame(['Đến 20.000.000 đ', '300.000 đ/đơn', 'Cố định / đơn', false, 300000.0], [$rows[2]->revenueRange, $rows[2]->valueText, $rows[2]->calcText, $rows[2]->isActive, $rows[2]->fixedAmount]);
        $this->assertSame(['Không giới hạn', 'Theo trạng thái khách', 'lead', 'Lead / Khách ADS', 'Lợi nhuận gộp', '0,50%'], [
            $rows[3]->revenueRange, $rows[3]->applyText, $rows[3]->customerStatus, $rows[3]->customerStatusText, $rows[3]->baseText, $rows[3]->valueText,
        ]);
        $this->assertSame(['#4242', 'Sản phẩm: #4242', '25.000 đ/SP', 25000.0], [$rows[4]->targetText, $rows[4]->applyText, $rows[4]->valueText, $rows[4]->fixedAmount], 'không có chữ thì lấy #id; tiền/SP rơi về amount_per_unit');
        $this->assertSame(['all', 'Tất cả khách'], [$rows[5]->customerStatus, $rows[5]->customerStatusText], 'trạng thái khách trống → all');
    }

    public function test_luong_sales_bac_kpi_tong_va_nhan_chinh_sach(): void
    {
        $users = collect([(object) ['id' => 1, 'name' => 'Sales Một', 'email' => 's1@x'], (object) ['id' => 2, 'name' => 'Sales Hai', 'email' => 's2@x']]);
        $salaries = collect([1 => (object) ['base_salary' => 9000000, 'target_revenue' => 600000000, 'target_commission' => 2000000, 'is_active' => 1, 'note' => 'Ghi chú']]);
        $tiers = collect([
            (object) ['sales_id' => 1, 'from_revenue' => 200000000, 'to_revenue' => 400000000, 'bonus_type' => 'percent_revenue', 'bonus_amount' => 1.5, 'is_active' => 0],
            (object) ['sales_id' => 999, 'from_revenue' => 100000000, 'to_revenue' => null, 'bonus_type' => 'salary_percent', 'bonus_amount' => 10, 'is_active' => 1],
            (object) ['sales_id' => null, 'from_revenue' => 500000000, 'to_revenue' => null, 'bonus_type' => 'fixed', 'bonus_amount' => 1000000, 'is_active' => 1],
        ]);
        $rules = collect([$this->rule(['is_active' => 1]), $this->rule(['is_active' => 0]), $this->rule(['is_active' => '1'])]);

        $data = $this->presenter->viewData($rules, $users, $salaries, $tiers, (object) ['project_rate_percent' => 4, 'trade_rate_percent' => 1.25, 'panel_fixed_amount' => 15000]);

        $this->assertSame(2, $data['activeRules'], 'is_active so lỏng như view cũ');
        $this->assertSame(['9.000.000 đ', '600.000.000 đ'], [$data['totalBaseSalaryText'], $data['totalTargetRevenueText']], 'chỉ cộng thiết lập đang bật');
        $this->assertSame(['4,00%', '1,25%', '15.000 đ'], [$data['policyProjectRateText'], $data['policyTradeRateText'], $data['policyPanelFixedText']]);
        $this->assertSame([1 => 'Sales Một', 2 => 'Sales Hai'], $data['salesNameMap']);

        [$one, $two] = $data['salaryRows'];
        $this->assertSame([9000000.0, '9.000.000 đ', '2.000.000 đ', true, 'Ghi chú'], [$one->baseSalary, $one->baseSalaryText, $one->targetCommissionText, $one->isActive, $one->note]);
        $this->assertSame([7000000.0, 500000000.0, 0.0, false, '', null], [$two->baseSalary, $two->targetRevenue, $two->targetCommission, $two->isActive, $two->note, $two->salary], 'chưa thiết lập → mặc định 7 triệu / 500 triệu, tắt');

        [$own, $unknown, $all] = $data['tierRows'];
        $this->assertSame(['Sales Một', '200.000.000 đ - 400.000.000 đ', '% doanh số', '1,50%', false], [$own->salesName, $own->range, $own->bonusTypeText, $own->bonusValue, $own->isActive]);
        $this->assertSame(['Sales #999', 'Từ 100.000.000 đ', '% lương cứng', '10,00%'], [$unknown->salesName, $unknown->range, $unknown->bonusTypeText, $unknown->bonusValue]);
        $this->assertSame(['Tất cả Sales', 'Thưởng cố định', '1.000.000 đ'], [$all->salesName, $all->bonusTypeText, $all->bonusValue]);
    }

    /** Bản ghi quy tắc thô như DB trả (mọi cột có mặt, null khi trống). */
    private function rule(array $overrides): object
    {
        return (object) array_merge([
            'id' => 1, 'commission_type' => 'trade_product', 'target_type' => 'all', 'target_id' => null, 'target_text' => null,
            'base_type' => 'revenue_before_vat', 'calculation_type' => 'percent', 'rate_percent' => null, 'fixed_amount' => null,
            'amount_per_unit' => null, 'amount_per_kwp' => null, 'from_amount' => null, 'to_amount' => null, 'from_qty' => null, 'to_qty' => null,
            'priority' => 10, 'is_active' => 1, 'note' => null,
        ], $overrides);
    }
}
