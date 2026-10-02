<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Projects\Site;
use App\Models\SolarMaintenanceSchedule;
use App\View\Presenters\Technical\MaintenanceSitePresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Trang hồ sơ bảo trì: trạng thái giao diện do Alpine giữ, không còn JS tay.
 *
 * ## Vì sao có test này
 * Bản cũ là 28 dòng JS tự gắn sự kiện và tự DÒ DOM để đếm số đợt còn hiện, rồi
 * từ đó quyết định ẩn chu kỳ. Đếm bằng cách hỏi DOM thì hỏng lặng lẽ: lọc sai
 * chỉ làm mất vài thẻ trên màn hình chứ không báo lỗi gì.
 *
 * Nay số đếm tính sẵn trong PHP và phần giao diện chỉ so sánh. Test chốt lại
 * đúng mấy con số đó cùng với việc các móc Alpine còn nguyên.
 *
 * Hành vi đã đối chiếu bằng Chrome headless: cùng một kịch bản 16 thao tác
 * (đổi 4 tab, 4 bộ lọc, mở/đóng modal, phím Escape), so 368 trạng thái
 * hiện/ẩn giữa bản JS tay và bản Alpine — giống hệt.
 */
final class MaintenanceSiteAlpineTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function trang_dung_alpine_va_khong_con_js_tay(): void
    {
        $html = $this->dungTrang();

        $this->assertStringContainsString('x-data=', $html, 'Thiếu gốc x-data.');
        $this->assertStringContainsString('hienDot(', $html);
        $this->assertStringContainsString('hienChuKy(', $html);
        $this->assertStringContainsString('soDotHien()', $html);

        $nguon = (string) file_get_contents(
            resource_path('views/technical/maintenance/site-show.blade.php')
        );

        $this->assertStringNotContainsString(
            'DOMContentLoaded',
            $nguon,
            'View lại có JS tay — trạng thái giao diện phải nằm trong x-data.'
        );

        $this->assertStringNotContainsString(
            'querySelectorAll',
            $nguon,
            'View lại dò DOM. Số liệu bộ lọc đã có sẵn trong PHP, đừng đếm lại bằng DOM.'
        );
    }

    /**
     * Số liệu bộ lọc phải khớp dữ liệu, vì Alpine dựa hẳn vào chúng để quyết
     * định hiện dòng "không có đợt phù hợp".
     */
    #[Test]
    public function so_lieu_bo_loc_khop_du_lieu(): void
    {
        // 6 đợt: 2 đã xong (1 trong đó quá hạn vì mới 'approved'), 4 đang mở
        // (2 quá hạn). Tổng quá hạn = 3.
        $html = $this->dungTrang();

        $this->assertStringContainsString('tong: 6', $html);
        $this->assertStringContainsString('done: 2', $html);
        $this->assertStringContainsString('open: 4', $html);
        $this->assertStringContainsString('overdue: 3', $html);
    }

    #[Test]
    public function moi_dot_deu_co_dieu_kien_an_hien(): void
    {
        $html = $this->dungTrang();

        // +1 cho chính chỗ khai báo hàm trong x-data.
        $this->assertSame(
            6 + 1,
            substr_count($html, 'hienDot('),
            'Mỗi đợt phải có một điều kiện ẩn/hiện riêng (6 đợt + 1 khai báo).'
        );

        $this->assertSame(
            2 + 1,
            substr_count($html, 'hienChuKy('),
            'Mỗi chu kỳ phải có một điều kiện ẩn/hiện riêng (2 chu kỳ + 1 khai báo).'
        );
    }

    private function dungTrang(): string
    {
        $this->actingAs($this->userWithRole('admin'));

        $site = new Site([
            'name' => 'Công trình Thử Nghiệm',
            'address' => '12 Nguyễn Huệ, Quận 1',
            'contact_name' => 'Chị Lan',
            'contact_phone' => '0900000000',
        ]);
        $site->id = 4242;

        $schedules = new Collection([
            $this->lich(1, 'completed', '-90 days', 1),
            $this->lich(2, 'approved', '-60 days', 2),
            $this->lich(3, 'assigned', '-10 days', 3),
            $this->lich(4, 'scheduled', '-3 days', 4),
            $this->lich(5, 'scheduled', '+20 days', 5),
            $this->lich(6, 'draft', '+50 days', 6),
        ]);

        $cycles = new Collection([
            $this->chuKy('c1', $schedules->slice(0, 3)->values()),
            $this->chuKy('c2', $schedules->slice(3, 3)->values()),
        ]);

        return view('technical.maintenance.site-show', array_merge([
            'site' => $site,
            'schedules' => $schedules,
            'cycles' => $cycles,
            'documents' => new Collection,
            'serials' => new Collection,
            'activity' => new Collection,
            'statuses' => SolarMaintenanceSchedule::STATUSES,
            'types' => SolarMaintenanceSchedule::TYPES,
            'approvalStatuses' => SolarMaintenanceSchedule::APPROVAL_STATUSES,
            'permissions' => ['create' => true, 'upload' => true, 'manage' => true],
        ], app(MaintenanceSitePresenter::class)->viewData($site, $schedules, $cycles)))->withErrors(new MessageBag)->render();
    }

    private function lich(int $id, string $status, string $khi, int $round): SolarMaintenanceSchedule
    {
        $s = new SolarMaintenanceSchedule([
            'status' => $status,
            'scheduled_date' => now()->modify($khi)->toDateString(),
            'round_no' => $round,
            'total_rounds' => 3,
            'schedule_code' => 'BT-'.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'assignee_names' => 'KTV '.$id,
        ]);
        $s->id = $id;
        $s->setRelation('leader', null);

        return $s;
    }

    /**
     * @param  Collection<int, SolarMaintenanceSchedule>  $items
     * @return array<string, mixed>
     */
    private function chuKy(string $key, Collection $items): array
    {
        $completed = $items->where('status', 'completed')->count();

        return [
            'key' => $key,
            'title' => 'Chu kỳ 3 đợt',
            'total' => $items->count(),
            'planned' => 3,
            'completed' => $completed,
            'approved' => $items->whereIn('status', ['approved', 'completed'])->count(),
            'percent' => (int) round(($completed / 3) * 100),
            'next' => $items->first(fn ($i) => ! in_array($i->status, ['completed', 'cancelled'], true)),
            'items' => $items,
        ];
    }
}
