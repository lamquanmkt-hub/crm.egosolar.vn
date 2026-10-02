<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Trang bảng lương không được truy vấn CSDL theo từng dòng nhân viên.
 *
 * Controller đã gộp sẵn số lần đi trễ bằng MỘT câu GROUP BY (`$lateCountMap`),
 * nhưng view còn một nhánh dự phòng chạy lại đúng câu COUNT đó cho từng nhân
 * viên có `late_count <= 0` — tức toàn bộ nhân viên đi làm đúng giờ. Cùng bộ lọc,
 * cùng khoảng ngày, nên nhánh ấy luôn trả về 0: tốn N câu truy vấn để lấy lại
 * một con số đã biết.
 *
 * Test này chốt số câu truy vấn chứ không chốt thời gian chạy — thời gian phụ
 * thuộc máy, còn số câu truy vấn thì không.
 */
final class SalaryViewQueryCountTest extends TestCase
{
    use DatabaseTransactions;

    private const SO_NHAN_VIEN = 12;

    #[Test]
    public function so_cau_truy_van_bang_cham_cong_khong_tang_theo_so_nhan_vien(): void
    {
        $admin = $this->userWithRole('admin');

        User::factory()->count(self::SO_NHAN_VIEN)->create(['is_active' => 1]);

        $cauTruyVan = [];
        DB::listen(function ($truyVan) use (&$cauTruyVan): void {
            if (str_contains($truyVan->sql, 'attendance_records')) {
                $cauTruyVan[] = $truyVan->sql;
            }
        });

        $this->actingAs($admin)->get(route('finance.salary'))->assertOk();

        $demTheoDong = array_values(array_filter(
            $cauTruyVan,
            static fn (string $sql): bool => str_contains($sql, '"user_id" = ?')
                || str_contains($sql, '`user_id` = ?')
        ));

        $this->assertSame(
            [],
            $demTheoDong,
            sprintf(
                "Có %d câu truy vấn attendance_records theo từng nhân viên (N+1).\n".
                "Số liệu đã gộp sẵn ở FinanceDashboardController::salary() qua \$lateCountMap.\n".
                'Câu đầu tiên: %s',
                count($demTheoDong),
                $demTheoDong[0] ?? ''
            )
        );
    }
}
