<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\DTOs\Hr\OvertimeRow;
use App\DTOs\Hr\OvertimeSummaryCards;
use App\View\Presenters\Hr\OvertimeListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guard trang `hr/overtime/index` sau đợt 2026-10-01. */
final class OvertimeListPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/hr/overtime/index.blade.php';

    private const BADGE = 'views/components/hr/overtime-badge.blade.php';

    private const CONTROLLER_KEYS = ['requests', 'summary', 'employees', 'month', 'status', 'userId', 'canManage'];

    private const BLADE_VARIABLES = ['row', 'employee', 'loop', 'errors', 'slot', 'attributes', 'component'];

    public function test_view_chi_in_khong_tu_tinh(): void
    {
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view, 'view còn khối @php');
        $this->assertStringNotContainsString('auth()', $view, 'view còn tự hỏi người đang đăng nhập');
        $this->assertStringNotContainsString('number_format', $view);
        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('<script', $view);
        $this->assertStringNotContainsString('data-bs-', $view);

        $data = (new OvertimeListPresenter)->viewData([], [], 0, false);
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $view, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::BLADE_VARIABLES, array_keys($data));

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');
    }

    public function test_khong_con_lop_bootstrap_nao(): void
    {
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        // TÁCH TOKEN thay vì regex "chứa tên lớp": regex ngây thơ vớt luôn bản Tailwind
        // (`tw:align-middle`, `tw:flex-wrap`, `tw:[border-top:…]`) — đã vấp đúng lỗi này khi viết test.
        // Chỉ lấy `class="…"` thật, không lấy `x-bind:class="…"` của Alpine.
        preg_match_all('/(?<![-:\w])class="([^"]*)"/', $view, $khop);
        $token = [];
        foreach ($khop[1] as $danhSach) {
            foreach (preg_split('/\s+/', trim($danhSach)) ?: [] as $lop) {
                if ($lop !== '' && ! str_starts_with($lop, 'tw:')) {
                    $token[$lop] = true;
                }
            }
        }

        // 55 lượt Bootstrap của bản cũ; `table`/`table-responsive` là MÓC JS nên phải đi qua <x-ui.table*>.
        foreach (['container-fluid', 'rounded-pill', 'rounded-4', 'rounded-3', 'shadow-sm', 'border-0',
            'border-top', 'fs-3', 'py-5', 'me-1', 'badge', 'table', 'table-responsive', 'table-hover',
            'table-light', 'align-middle', 'flex-wrap', 'small'] as $lop) {
            $this->assertArrayNotHasKey($lop, $token, "view dùng lại lớp Bootstrap `{$lop}`");
        }

        // Và không còn token nào KHÔNG mang tiền tố `tw:`, trừ Bootstrap ICONS (`bi`, `bi-*`) — đó là
        // gói font RIÊNG (`bootstrap-icons@1.11.3`) mà repo cố ý giữ, không thuộc việc gỡ Bootstrap CSS.
        $con = array_values(array_filter(
            array_keys($token),
            static fn (string $l): bool => $l !== 'bi' && ! str_starts_with($l, 'bi-')
        ));
        $this->assertSame([], $con, 'view còn lớp không phải `tw:*`');

        $this->assertStringContainsString('<x-ui.table-wrap>', $view, 'bảng phải đi qua component (giữ móc JS)');
        $this->assertStringContainsString('<x-hr.overtime-badge', $view);
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        foreach (['row' => OvertimeRow::class, 'summaryCards' => OvertimeSummaryCards::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $view, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien");
        }
    }

    /**
     * Huy hiệu `warning` PHẢI dùng chữ đậm màu, không phải trắng.
     *
     * Đo theo WCAG 2.1: trắng trên #ffc107 chỉ 1,63:1 (ngưỡng AA 4,5:1); #212529 đạt 9,46:1.
     * Ai đổi lại thành chữ trắng là đưa lỗi tiếp cận quay lại mà không ai thấy.
     */
    public function test_huy_hieu_cho_duyet_du_tuong_phan(): void
    {
        $badge = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::BADGE)));

        $this->assertStringContainsString("tw:bg-[rgb(255,193,7)] tw:text-[#212529]' => \$tone === 'warning'", $badge);
        $this->assertStringNotContainsString('tw:bg-[rgb(255,193,7)] tw:text-white', $badge);
    }

    public function test_trang_that_in_dung_cho_quan_ly_hr(): void
    {
        Carbon::setTestNow('2026-10-15 08:00:00');
        $hr = $this->userWithRole('hr', ['id' => 802001, 'name' => 'QL Nhân sự', 'email' => 'hr-guard@example.test']);
        $nv = $this->userWithRole('technical', ['id' => 802002, 'name' => 'Kỹ sư B', 'email' => 'nv-guard@example.test']);

        DB::table('departments')->insert(['id' => 802100, 'name' => 'Phòng Kỹ thuật', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('id', 802002)->update(['department_id' => 802100]);

        DB::table('hr_overtime_requests')->insert([
            ['id' => 802201, 'user_id' => 802002, 'approver_id' => 802001, 'overtime_date' => '2026-10-12',
                'start_at' => '2026-10-12 18:00:00', 'end_at' => '2026-10-12 20:30:00', 'hours' => 2.5,
                'status' => 'pending', 'reason' => 'Chạy deadline', 'approval_note' => null,
                'created_at' => now(), 'updated_at' => now()],
            ['id' => 802202, 'user_id' => 802002, 'approver_id' => 802001, 'overtime_date' => '2026-10-10',
                'start_at' => '2026-10-10 18:00:00', 'end_at' => '2026-10-10 19:00:00', 'hours' => 1234.5,
                'status' => 'approved', 'reason' => '', 'approval_note' => 'Đã ghi công',
                'created_at' => now(), 'updated_at' => now()],
        ]);

        $html = (string) $this->actingAs($hr)->get('/nhan-su/tang-ca?month=2026-10')->assertOk()->getContent();

        $this->assertStringContainsString('Kỹ sư B', $html);
        $this->assertStringContainsString('Phòng Kỹ thuật', $html);
        $this->assertStringContainsString('12/10/2026', $html);
        $this->assertStringContainsString('18:00 - 20:30', $html);
        $this->assertStringContainsString('2,5 giờ', $html, 'số giờ dùng dấu phẩy tiếng Việt');
        $this->assertStringContainsString('1.234,5 giờ', $html, 'nghìn dùng dấu chấm');
        $this->assertStringContainsString('Đã ghi công', $html);
        $this->assertStringContainsString('Duyệt', $html, 'quản lý HR phải thấy nút duyệt');
        // Nhãn cho trình đọc màn hình (thêm ở đợt này) phải có và phải ẩn về mặt thị giác.
        $this->assertStringContainsString('tw:sr-only', $html);
        $this->assertStringContainsString('Ghi chú duyệt đơn tăng ca 12/10/2026 của Kỹ sư B', $html);

        // Nhân viên thường: thấy đơn của mình nhưng KHÔNG có nút duyệt.
        $htmlNv = (string) $this->actingAs($nv)->get('/nhan-su/tang-ca?month=2026-10')->assertOk()->getContent();
        $this->assertStringContainsString('2,5 giờ', $htmlNv);
        $this->assertStringContainsString('Không có thao tác', $htmlNv);
        $this->assertStringNotContainsString('Lý do từ chối đơn tăng ca', $htmlNv);
    }

    public function test_thang_khong_co_don(): void
    {
        $hr = $this->userWithRole('hr', ['id' => 802003, 'name' => 'QL Nhân sự 2']);

        $html = (string) $this->actingAs($hr)->get('/nhan-su/tang-ca?month=2026-01')->assertOk()->getContent();

        $this->assertStringContainsString('Chưa có đơn tăng ca nào.', $html);
        $this->assertStringContainsString('0 giờ', $html);
    }
}
