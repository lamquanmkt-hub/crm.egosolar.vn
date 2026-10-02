<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Services\Sales\CommissionReportData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\Support\CommissionFixture;
use Tests\TestCase;

/**
 * Ghim các con số hoa hồng trên dữ liệu thật.
 *
 * ## Vì sao test này tồn tại
 * Trang hoa hồng từng được "xác minh" bằng cách so ảnh chụp HTML từng byte —
 * nhưng CSDL test rỗng sạch bảng đơn, nên phép so đó chỉ chứng minh nhánh không
 * có dữ liệu. Nó thậm chí không phát hiện nổi việc trang rỗng vì tham chiếu sai
 * lớp. Test này đi thẳng vào con số.
 *
 * Mọi giá trị kỳ vọng dưới đây đều tính tay được từ {@see CommissionFixture}.
 */
final class CommissionReportDataTest extends TestCase
{
    use DatabaseTransactions;

    private function report(int $filterSalesId = 0): array
    {
        Role::findOrCreate('sales', 'web');
        CommissionFixture::seed();

        return app(CommissionReportData::class)->build(CommissionFixture::MONTH, $filterSalesId);
    }

    public function test_co_du_lieu_chinh_sach_thay_vi_trang_rong(): void
    {
        $data = $this->report();

        // Đây chính là chỗ từng hỏng: thiếu chính sách thì mọi con số về 0.
        $this->assertNotNull($data['policy']);
        /*
         * 3 quy tắc bật của fixture + 2 quy tắc mặc định mà chính
         * `CommissionEngineService` tự chèn cho chính sách còn thiếu. Quy tắc đã
         * tắt trong fixture KHÔNG được đếm — đó là điều khẳng định ở đây.
         */
        $this->assertSame(5, $data['activeRules']);
        $this->assertSame(6, \Illuminate\Support\Facades\DB::table('crm_commission_rules')->count());
        $this->assertCount(2, $data['salesRows']);
    }

    public function test_doanh_thu_va_phan_du_dieu_kien(): void
    {
        $rows = collect($this->report()['salesRows'])->keyBy('id');
        $a = $rows[CommissionFixture::SALES_A];
        $b = $rows[CommissionFixture::SALES_B];

        // Sales A có 2 đơn: 550tr (thu đủ) + 110tr (thu 50tr, còn nợ).
        $this->assertSame(660_000_000.0, $a->revenue);
        $this->assertSame(600_000_000.0, $a->paid);
        $this->assertSame(60_000_000.0, $a->debt);
        $this->assertSame(2, $a->order_count);

        // Chỉ đơn thu đủ mới vào cơ sở tính hoa hồng: 550tr - 40.740.741 thuế.
        $this->assertSame(509_259_259.0, $a->eligible_revenue_before_vat);

        // Sales B: đơn không có thuế -> phải tra bảng giá, 5 x 4.000.000.
        $this->assertSame(20_000_000.0, $b->eligible_revenue_before_vat);
    }

    public function test_hoa_hong_theo_quy_tac(): void
    {
        $rows = collect($this->report()['salesRows'])->keyBy('id');

        // Sales A đạt mốc 500tr nên ăn quy tắc khách lead 0,5%.
        $this->assertSame(2_546_296.295, round($rows[CommissionFixture::SALES_A]->commission, 3));

        // Sales B chưa đạt mốc -> quy tắc chung 1% (200.000)
        // cộng thưởng tấm pin 5 x 15.000 (75.000).
        $this->assertSame(275_000.0, $rows[CommissionFixture::SALES_B]->commission);
    }

    public function test_thuong_kpi_va_luong_mac_dinh(): void
    {
        $rows = collect($this->report()['salesRows'])->keyBy('id');
        $a = $rows[CommissionFixture::SALES_A];
        $b = $rows[CommissionFixture::SALES_B];

        // Doanh thu 660tr -> bậc 2, thưởng cố định.
        $this->assertSame('Bac 2 - Tu 500tr', $a->kpi_name);
        $this->assertSame(1_000_000.0, $a->kpi_bonus);
        $this->assertSame(8_000_000.0, $a->base_salary, 'sales A có thiết lập lương riêng');

        // Doanh thu 21,6tr -> bậc 1, thưởng 0,1% doanh thu.
        $this->assertSame('Bac 1 - Duoi 500tr', $b->kpi_name);
        $this->assertSame(21_600.0, $b->kpi_bonus);
        $this->assertSame(7_000_000.0, $b->base_salary, 'sales B không có thiết lập -> mặc định');
    }

    public function test_don_gan_day_va_ten_khach_lan_qua_lead(): void
    {
        $orders = collect($this->report()['recentOrders'])->keyBy('code');

        $this->assertSame('Cong ty Lead ADS', $orders['DH-971001']->customer, 'tên khách phải lần qua lead');
        $this->assertTrue($orders['DH-971001']->commission_eligible);

        $this->assertFalse($orders['DH-971002']->commission_eligible, 'còn công nợ');
        $this->assertSame(0.0, (float) $orders['DH-971002']->commission);

        $this->assertFalse($orders['DH-971004']->commission_eligible, 'đơn tổng bằng 0');
    }

    public function test_loc_theo_mot_sales(): void
    {
        $data = $this->report(CommissionFixture::SALES_B);

        $this->assertCount(1, $data['salesRows']);
        $this->assertSame(CommissionFixture::SALES_B, $data['salesRows']->first()->id);
        $this->assertSame(21_600_000.0, (float) $data['totalRevenue']);
    }
}
