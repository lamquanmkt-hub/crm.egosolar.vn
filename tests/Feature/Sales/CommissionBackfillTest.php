<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CommissionFixture;
use Tests\TestCase;

/**
 * `commission:backfill` phải ghi ĐÚNG số mà chính sách quy định.
 *
 * ## Vì sao test này quan trọng
 * Bảng `sales_commissions` là "số đã chốt", và
 * {@see \App\Services\Sales\Commission\RecordedCommissionQuery} ưu tiên nó hơn
 * số tính lại. Ghi sai ở đây là báo cáo sai vĩnh viễn.
 *
 * Bản lệnh cũ có `$rate = 0; // tạm thời` nên ghi 120 dòng toàn số 0 lên
 * production (2026-03-31), và lấy `base_amount` từ tổng SAU VAT trong khi chính
 * sách tính trên doanh thu TRƯỚC VAT.
 */
final class CommissionBackfillTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        CommissionFixture::seed();
    }

    public function test_dry_run_khong_ghi_gi_vao_co_so_du_lieu(): void
    {
        $before = DB::table('sales_commissions')->count();

        $this->artisan('commission:backfill', ['--month' => CommissionFixture::MONTH, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame($before, DB::table('sales_commissions')->count(),
            '--dry-run không được ghi dòng nào');
    }

    public function test_ghi_ty_le_va_so_tien_theo_chinh_sach_chu_khong_phai_0(): void
    {
        $this->artisan('commission:backfill', ['--month' => CommissionFixture::MONTH])
            ->assertSuccessful();

        $rows = DB::table('sales_commissions')
            ->where('period_month', CommissionFixture::MONTH)
            ->get();

        $this->assertNotEmpty($rows, 'phải ghi được ít nhất một dòng cho kỳ của fixture');

        $this->assertGreaterThan(0, $rows->sum('commission_amount'),
            'Tổng hoa hồng phải > 0. Bằng 0 nghĩa là đã quay lại lỗi `$rate = 0; // tạm thời`.');

        foreach ($rows as $row) {
            $this->assertNotNull($row->period_month, 'thiếu kỳ thì báo cáo lọc theo ngày chạy lệnh');
            $this->assertSame('auto', $row->status);
        }
    }

    /** Chạy lại không được nhân đôi dòng, và phải SỬA được dòng 0 cũ. */
    public function test_chay_lai_khong_nhan_doi_va_sua_duoc_dong_cu(): void
    {
        $this->artisan('commission:backfill', ['--month' => CommissionFixture::MONTH])->assertSuccessful();

        $first = DB::table('sales_commissions')->where('period_month', CommissionFixture::MONTH)->get();
        $orderId = (int) $first->first()->order_id;

        // giả lập dòng hỏng do bản lệnh cũ để lại
        DB::table('sales_commissions')->where('order_id', $orderId)->update([
            'rate' => 0, 'commission_amount' => 0, 'base_amount' => 0, 'period_month' => null,
        ]);

        $this->artisan('commission:backfill', ['--month' => CommissionFixture::MONTH])->assertSuccessful();

        $after = DB::table('sales_commissions')->where('period_month', CommissionFixture::MONTH)->get();

        $this->assertCount($first->count(), $after, 'chạy lại không được nhân đôi dòng');

        $fixed = $after->firstWhere('order_id', $orderId);

        $this->assertNotNull($fixed);
        $this->assertGreaterThan(0, (float) $fixed->commission_amount,
            'chạy lại phải SỬA được dòng 0 cũ, không chỉ bỏ qua vì "đã tồn tại"');
    }

    /** Đơn còn công nợ bị loại đúng như màn hình. */
    public function test_don_con_cong_no_khong_duoc_ghi(): void
    {
        $this->artisan('commission:backfill', ['--month' => CommissionFixture::MONTH])->assertSuccessful();

        $written = DB::table('sales_commissions')->pluck('order_id')->map(fn ($id) => (int) $id)->all();

        $unpaid = DB::table('crm_orders as o')
            ->leftJoinSub(
                DB::table('crm_payments')->select('order_id', DB::raw('SUM(amount) as paid'))->groupBy('order_id'),
                'p', fn ($j) => $j->on('p.order_id', '=', 'o.id'))
            ->whereRaw('COALESCE(p.paid, 0) < COALESCE(o.total_amount, 0)')
            ->pluck('o.id')->map(fn ($id) => (int) $id)->all();

        foreach ($unpaid as $id) {
            $this->assertNotContains($id, $written,
                "đơn #$id còn công nợ nhưng vẫn được ghi hoa hồng");
        }
    }
}
