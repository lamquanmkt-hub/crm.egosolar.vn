<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\DTOs\Hr\AttendanceRecordRow;
use App\View\Presenters\Hr\MyAttendancePresenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * MyAttendancePresenter thay 2 khối `@php` của trang chấm công cá nhân (2026-09-26).
 *
 * Khối đầu duyệt `$records` SÁU lần để đếm các con số tổng hợp và khai closure đổi trạng thái
 * thành lớp CSS; khối sau nằm trong `@forelse`, chạy lại cho từng dòng.
 */
final class MyAttendancePresenterTest extends TestCase
{
    private const VIEW = 'resources/views/hr/attendance/my.blade.php';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attributes */
    private function record(array $attributes = [], array $corrections = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'work_date' => Carbon::parse('2026-09-01'),
            'check_in_at' => '2026-09-01 08:00:00',
            'check_out_at' => '2026-09-01 17:00:00',
            'late_minutes' => 0,
            'work_minutes' => 480,
            'status' => 'completed',
            'correctionRequests' => new Collection($corrections),
        ], $attributes);
    }

    public function test_view_khong_con_php_va_chi_doc_thuoc_tinh_that_cua_dto(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $source);
        // Biến của hai khối cũ không được còn dùng trực tiếp.
        $this->assertDoesNotMatchRegularExpression('/(?<!row->)\$record(?![A-Za-z0-9_])/', $source);
        $this->assertDoesNotMatchRegularExpression('/\$statusClass/', $source);
        $this->assertDoesNotMatchRegularExpression('/\$isActiveToday(?![A-Za-z0-9_])/', $source);

        preg_match_all('/\$row->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(
            fn (\ReflectionProperty $p) => $p->getName(),
            (new \ReflectionClass(AttendanceRecordRow::class))->getProperties()
        );
        $this->assertNotSame([], $m[1]);
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)));
    }

    public function test_thang_chua_co_ban_cong_nao(): void
    {
        $data = (new MyAttendancePresenter)->viewData([]);

        $this->assertSame(0, $data['validDays']);
        $this->assertSame(0, $data['lateDays']);
        $this->assertSame(0, $data['completedDays']);
        $this->assertSame(0.0, $data['totalHours']);
        $this->assertSame(0, $data['onTimeDays']);
        // Không có ngày hợp lệ -> 0 chứ không chia cho 0.
        $this->assertSame(0, $data['onTimeRate']);
        $this->assertSame([], $data['recordRows']);
    }

    public function test_dem_dung_cac_con_so_tong_hop(): void
    {
        $data = (new MyAttendancePresenter)->viewData([
            $this->record(['work_minutes' => 480, 'late_minutes' => 0, 'status' => 'completed']),
            $this->record(['work_minutes' => 455, 'late_minutes' => 25, 'status' => 'late']),
            $this->record(['work_minutes' => 390, 'late_minutes' => 0, 'status' => 'early_leave']),
            // Không check-in: KHÔNG tính là ngày công hợp lệ, cũng không tính đúng giờ.
            $this->record(['check_in_at' => null, 'check_out_at' => null, 'work_minutes' => 0, 'status' => 'absent']),
        ]);

        $this->assertSame(3, $data['validDays'], 'chỉ đếm ngày CÓ check-in');
        $this->assertSame(1, $data['lateDays']);
        $this->assertSame(1, $data['completedDays']);
        $this->assertSame(2, $data['onTimeDays'], 'có check-in và late_minutes = 0');
        // (480+455+390+0)/60 = 22,0833… -> làm tròn 1 chữ số
        $this->assertSame(22.1, $data['totalHours']);
        // 2/3 * 100 = 66.67 -> round() = 67
        $this->assertSame(67.0, $data['onTimeRate']);
    }

    public function test_lop_css_theo_tung_trang_thai(): void
    {
        $data = (new MyAttendancePresenter)->viewData([
            $this->record(['status' => 'completed']),
            $this->record(['status' => 'checked_in']),
            $this->record(['status' => 'late']),
            $this->record(['status' => 'early_leave']),
            $this->record(['status' => 'incomplete']),
            $this->record(['status' => 'absent']),
            $this->record(['status' => null]),
        ]);

        $classes = array_map(fn (AttendanceRecordRow $r) => $r->statusClass, $data['recordRows']);
        $this->assertSame(
            ['success', 'primary', 'warning', 'warning', 'danger', 'secondary', 'secondary'],
            $classes,
            'trạng thái lạ và null đều về secondary như nhánh default của match() cũ'
        );
    }

    public function test_don_xin_sua_dang_cho_duoc_tim_ra(): void
    {
        $pending = (object) ['id' => 9, 'status' => 'pending'];
        $approved = (object) ['id' => 8, 'status' => 'approved'];

        $data = (new MyAttendancePresenter)->viewData([
            $this->record([], [$approved, $pending]),   // lấy đúng đơn pending, không phải đơn đầu
            $this->record([], [$approved]),             // không có đơn chờ -> null
            $this->record([], []),
        ]);

        $this->assertSame($pending, $data['recordRows'][0]->pendingCorrection);
        $this->assertNull($data['recordRows'][1]->pendingCorrection);
        $this->assertNull($data['recordRows'][2]->pendingCorrection);
    }

    public function test_co_dang_lam_hom_nay(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 14:30:00'));

        $data = (new MyAttendancePresenter)->viewData([
            // hôm nay, chưa check-out -> đang làm
            $this->record(['work_date' => Carbon::parse('2026-09-15'), 'check_out_at' => null]),
            // hôm nay nhưng ĐÃ check-out -> không còn đang làm
            $this->record(['work_date' => Carbon::parse('2026-09-15'), 'check_out_at' => '2026-09-15 17:00:00']),
            // hôm khác, chưa check-out -> không phải hôm nay
            $this->record(['work_date' => Carbon::parse('2026-09-14'), 'check_out_at' => null]),
        ]);

        $this->assertTrue($data['recordRows'][0]->isActiveToday);
        $this->assertFalse($data['recordRows'][1]->isActiveToday);
        $this->assertFalse($data['recordRows'][2]->isActiveToday);
    }
}
