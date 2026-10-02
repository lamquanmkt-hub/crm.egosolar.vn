<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\User;
use App\Services\Projects\SiteFinancialTotalsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Số tiền theo công trình phải gộp sẵn, không truy vấn theo từng dòng.
 *
 * `sites/index.blade.php` từng chạy BỐN câu truy vấn cho MỖI công trình ngay
 * trong vòng lặp hiển thị. Đo được 42 câu theo từng dòng cho 10 công trình
 * (tổng 75). Trang phân trang 20 dòng nên thực tế khoảng 80 câu chỉ để lấy bốn
 * con số. Nay là bốn câu GROUP BY, không phụ thuộc số dòng.
 *
 * Test chốt số câu truy vấn chứ không chốt thời gian chạy: thời gian phụ thuộc
 * máy, số câu truy vấn thì không.
 */
final class SiteFinancialTotalsTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function trang_danh_sach_khong_truy_van_theo_tung_cong_trinh(): void
    {
        $admin = $this->userWithRole('admin');
        $this->taoCongTrinh($admin, 10);

        $theoDong = [];
        DB::listen(function ($q) use (&$theoDong): void {
            // Chỉ tính câu lọc theo MỘT site_id. Các câu đếm badge ở sidebar cũng
            // chạm mấy bảng này nhưng lọc theo `status`, không phải N+1 của trang.
            $la = preg_match(
                '/from `(receipts|material_requests|payments|payment_requests)`'
                .'.*`site_id` = \?/i',
                $q->sql
            );

            if ($la === 1) {
                $theoDong[] = $q->sql;
            }
        });

        $this->actingAs($admin)->get('/cong-trinh')->assertOk();

        $this->assertSame([], $theoDong, sprintf(
            "Có %d câu truy vấn tiền theo từng công trình (N+1).\n".
            "Số liệu đã gộp sẵn ở SiteFinancialTotalsService, tra map thay vì truy vấn.\n%s",
            count($theoDong),
            implode("\n", array_slice($theoDong, 0, 3))
        ));
    }

    #[Test]
    public function cong_dung_tien_theo_tung_cong_trinh(): void
    {
        $admin = $this->userWithRole('admin');
        $ids = $this->taoCongTrinh($admin, 3);

        // Công trình đầu: 2 phiếu thu, 1 phiếu chi, 1 đề nghị đã duyệt + 1 chưa duyệt.
        $this->thu($ids[0], 10_000_000);
        $this->thu($ids[0], 5_000_000);
        $this->chi($ids[0], 3_000_000);
        $this->deNghi($admin, $ids[0], 2_000_000, 'accounting_approved');
        $this->deNghi($admin, $ids[0], 9_000_000, 'submitted');

        // Công trình thứ hai chỉ có một phiếu thu.
        $this->thu($ids[1], 7_000_000);

        $tong = app(SiteFinancialTotalsService::class)->forSites($ids);

        $this->assertSame(15_000_000.0, $tong['received'][$ids[0]]);
        $this->assertSame(3_000_000.0, $tong['paymentCost'][$ids[0]]);
        $this->assertSame(
            2_000_000.0,
            $tong['requestCost'][$ids[0]],
            'Chỉ cộng đề nghị đã được kế toán duyệt.'
        );

        $this->assertSame(7_000_000.0, $tong['received'][$ids[1]]);

        // Công trình thứ ba không có gì: phải VẮNG khỏi map, để view tra ra 0.
        $this->assertArrayNotHasKey($ids[2], $tong['received']->all());
    }

    #[Test]
    public function danh_sach_rong_thi_khong_chay_truy_van_nao(): void
    {
        $dem = 0;
        DB::listen(function () use (&$dem): void {
            $dem++;
        });

        $tong = app(SiteFinancialTotalsService::class)->forSites([]);

        $this->assertSame(0, $dem, 'Không có công trình nào thì đừng hỏi CSDL.');
        $this->assertCount(0, $tong['received']);
    }

    /** @return list<int> */
    private function taoCongTrinh(User $admin, int $soLuong): array
    {
        $congTyId = DB::table('companies')->insertGetId([
            'name' => 'EGO Solar', 'code' => 'EGO'.random_int(1000, 9999),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $ids = [];

        foreach (range(1, $soLuong) as $i) {
            $ids[] = (int) DB::table('sites')->insertGetId([
                'name' => 'Công trình '.$i,
                'company_id' => $congTyId,
                'status' => ['planning', 'installing', 'done', 'warranty'][$i % 4],
                'contract_amount' => 100_000_000 + $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $ids;
    }

    private function thu(int $siteId, int $tien): void
    {
        DB::table('receipts')->insert([
            'code' => 'PT-'.$siteId.'-'.random_int(1000, 9999),
            'receipt_date' => now()->toDateString(),
            'payer_name' => 'Khách', 'site_id' => $siteId, 'amount' => $tien,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function chi(int $siteId, int $tien): void
    {
        DB::table('payments')->insert([
            'code' => 'PC-'.$siteId.'-'.random_int(1000, 9999),
            'payment_date' => now()->toDateString(),
            'payee_name' => 'NCC', 'site_id' => $siteId, 'amount' => $tien,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function deNghi(User $admin, int $siteId, int $tien, string $trangThai): void
    {
        DB::table('payment_requests')->insert([
            'code' => 'DN-'.$siteId.'-'.random_int(1000, 9999),
            'created_by' => $admin->id, 'receiver_name' => 'NCC',
            'amount' => $tien, 'site_id' => $siteId, 'status' => $trangThai,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
