<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Services\Projects\SitePaymentEditorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dữ liệu hộp thoại "Sửa thanh toán" ở trang chi tiết công trình.
 *
 * Trước đây là 87 dòng `@php` nằm giữa `sites/show.blade.php` (tệp 2.100 dòng):
 * hai truy vấn có leftJoin rồi định dạng tiền/ngày/tên người tạo ngay trong view.
 * Lẫn giữa HTML nên vừa khó thấy vừa không test được.
 */
final class SitePaymentEditorTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function tra_ve_dot_thanh_toan_va_phieu_thu_da_dinh_dang(): void
    {
        $du = $this->duLieuMau();

        $data = $this->service()->viewData($du['siteId']);

        $this->assertSame($du['siteId'], $data['egoFixSiteId']);
        $this->assertCount(3, $data['egoFixTermOptions']);
        $this->assertSame(
            'Đợt 1 - Đặt cọc',
            $data['egoFixTermOptions'][0]['name']
        );
        $this->assertSame('150.000.000 đ', $data['egoFixTermOptions'][0]['amount_text']);

        $this->assertCount(3, $data['egoFixPayments']);
        $this->assertSame('100.000.000 đ', $data['egoFixPayments'][1]['amount_text']);
        $this->assertSame('Chuyển khoản', $data['egoFixPayments'][0]['payment_method_text']);
        $this->assertSame('Tiền mặt', $data['egoFixPayments'][1]['payment_method_text']);
    }

    /** Mã hình thức lạ thì hiện nguyên mã, đừng nuốt thành "Khác". */
    #[Test]
    public function ma_hinh_thuc_khong_biet_thi_giu_nguyen(): void
    {
        $du = $this->duLieuMau();

        $data = $this->service()->viewData($du['siteId']);
        $la = $data['egoFixPayments']->firstWhere('payment_method', 'khong_biet');

        $this->assertNotNull($la);
        $this->assertSame('khong_biet', $la['payment_method_text']);
    }

    /** Chỉ lấy phiếu thu ĐÃ gắn đợt — phiếu chưa gắn không thuộc hộp thoại này. */
    /*
     * KHÔNG có test cho nhánh ngày '0000-00-00': MariaDB production và DB test đều
     * bật sql_mode NO_ZERO_DATE nên không chèn được giá trị đó. Lý do giữ nhánh ấy
     * ghi ở SitePaymentEditorService::ngay().
     */

    #[Test]
    public function bo_qua_phieu_thu_chua_gan_dot(): void
    {
        $du = $this->duLieuMau();

        DB::table('receipts')->insert([
            'code' => 'PT-roi', 'receipt_date' => '2026-08-30', 'payer_name' => 'Khách lẻ',
            'site_id' => $du['siteId'], 'site_payment_term_id' => null, 'amount' => 9000000,
            'payment_method' => 'cash', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertCount(3, $this->service()->viewData($du['siteId'])['egoFixPayments']);
    }

    #[Test]
    public function khong_co_cong_trinh_thi_tra_rong_chu_khong_truy_van(): void
    {
        $dem = 0;
        DB::listen(function () use (&$dem): void {
            $dem++;
        });

        $data = $this->service()->viewData(0);

        $this->assertSame(0, $dem);
        $this->assertCount(0, $data['egoFixPayments']);
        $this->assertCount(0, $data['egoFixTermOptions']);
    }

    /** @return array{siteId: int, termIds: list<int>} */
    private function duLieuMau(): array
    {
        $ctyId = DB::table('companies')->insertGetId([
            'name' => 'EGO Solar Việt Nam', 'code' => 'EGOVN'.random_int(100, 999), 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $siteId = (int) DB::table('sites')->insertGetId([
            'name' => 'Công trình mẫu', 'company_id' => $ctyId, 'status' => 'installing',
            'contract_amount' => 500000000, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $termIds = [];

        foreach ([['Đợt 1 - Đặt cọc', 30, 150000000], ['Đợt 2 - Triển khai', 40, 200000000],
            ['Đợt 3 - Nghiệm thu', 30, 150000000]] as [$ten, $pc, $tien]) {
            $termIds[] = (int) DB::table('site_payment_terms')->insertGetId([
                'site_id' => $siteId, 'name' => $ten, 'percent' => $pc, 'amount' => $tien,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $phieu = [
            [$termIds[0], 150000000, 'bank_transfer', '2026-08-01'],
            [$termIds[1], 100000000, 'cash', '2026-08-20'],
            [$termIds[1], 50000000, 'khong_biet', '2026-08-25'],
        ];

        foreach ($phieu as $i => [$term, $tien, $pt, $ngay]) {
            DB::table('receipts')->insert([
                'code' => 'PT-'.$siteId.'-'.$i, 'receipt_date' => $ngay,
                'payer_name' => 'Khách hàng', 'site_id' => $siteId,
                'site_payment_term_id' => $term, 'amount' => $tien, 'payment_method' => $pt,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['siteId' => $siteId, 'termIds' => $termIds];
    }

    private function service(): SitePaymentEditorService
    {
        return app(SitePaymentEditorService::class);
    }
}
