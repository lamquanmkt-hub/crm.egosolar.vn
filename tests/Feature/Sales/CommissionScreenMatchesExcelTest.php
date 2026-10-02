<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Services\Sales\CommissionReportData;
use App\Services\Sales\SalesCommissionExcelExporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Support\CommissionFixture;
use Tests\TestCase;

/**
 * Màn hình hoa hồng và bản xuất Excel phải ra CÙNG con số.
 *
 * ## Vì sao cần test này
 * Hai bên từng là hai bản cài đặt riêng của cùng một nghiệp vụ, và chúng đã trôi
 * lệch: bản Excel đọc bốn công tắc trong chính sách của kỳ, còn màn hình bỏ qua
 * cả bốn. Để chính sách mặc định thì không ai thấy gì bất thường; chỉ khi kế
 * toán bật `only_completed` hoặc tắt `only_paid` thì hai màn hình mới nói hai
 * con số khác nhau về cùng số tiền.
 *
 * Test chạy qua đúng những cấu hình làm lộ ra khác biệt đó.
 */
final class CommissionScreenMatchesExcelTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<string, array{0: array<string, int>, 1: float}> */
    public static function policies(): array
    {
        return [
            'mặc định' => [['only_paid' => 1, 'hold_if_debt' => 1, 'only_completed' => 0], 2_821_296.0],
            'không đòi thu đủ' => [['only_paid' => 0, 'hold_if_debt' => 0, 'only_completed' => 0], 3_821_296.0],
            'chỉ đơn hoàn tất' => [['only_paid' => 1, 'hold_if_debt' => 1, 'only_completed' => 1], 0.0],
        ];
    }

    /**
     * @param  array<string, int>  $flags
     */
    #[DataProvider('policies')]
    public function test_hai_ben_cung_con_so(array $flags, float $expected): void
    {
        Role::findOrCreate('sales', 'web');
        CommissionFixture::seed();
        DB::table('crm_commission_policies')->where('period_month', CommissionFixture::MONTH)->update($flags);

        $screen = (float) collect(app(CommissionReportData::class)->build(CommissionFixture::MONTH, 0)['salesRows'])
            ->sum('commission');

        $this->assertSame($expected, round($screen), 'con số trên màn hình');
        $this->assertSame($expected, $this->excelCommissionTotal(), 'con số trong file Excel');
    }

    public function test_chi_don_da_xuat_kho(): void
    {
        Role::findOrCreate('sales', 'web');
        CommissionFixture::seed();
        DB::table('crm_commission_policies')->where('period_month', CommissionFixture::MONTH)
            ->update(['only_shipped' => 1]);
        DB::table('crm_orders')->where('id', 971001)->update(['inventory_issued' => 0]);

        $screen = (float) collect(app(CommissionReportData::class)->build(CommissionFixture::MONTH, 0)['salesRows'])
            ->sum('commission');

        // Đơn lớn của Sales A bị loại, chỉ còn hoa hồng của Sales B.
        $this->assertSame(275_000.0, round($screen));
        $this->assertSame(275_000.0, $this->excelCommissionTotal());
    }

    /** Cột I của sheet tổng hợp; đọc bằng chính thư viện đã sinh ra file. */
    private function excelCommissionTotal(): float
    {
        $response = app(SalesCommissionExcelExporter::class)->download(CommissionFixture::MONTH, 0);

        ob_start();
        $response->sendContent();
        $binary = (string) ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'commission').'.xlsx';
        file_put_contents($path, $binary);

        try {
            $rows = IOFactory::createReader('Xlsx')->load($path)
                ->getSheetByName('Tong hop Sales')
                ->toArray(null, true, false, false);
        } finally {
            @unlink($path);
        }

        // 3 dòng tiêu đề + 1 dòng tên cột.
        return (float) collect(array_slice($rows, 4))->sum(fn ($row) => (float) ($row[8] ?? 0));
    }
}
