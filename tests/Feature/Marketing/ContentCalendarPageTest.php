<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\DTOs\Content\ContentCalendarEntry;
use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Trang lịch biên tập sau khi dời 9 khối `@php` sang ContentCalendarPresenter (2026-09-07). */
final class ContentCalendarPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/marketing/reports/content_calendar.blade.php';

    private const URL = '/marketing/reports/content-calendar';

    public function test_trang_in_gia_tri_da_tinh(): void
    {
        $actor = $this->userWithRole(Role::Admin->value);
        $lan = $this->userWithRole('marketing', ['name' => 'Lan Marketing']);
        $now = '2026-09-01 08:00:00';
        DB::table('content_calendars')->insert([
            ['id' => 990001, 'publish_date' => '2026-09-10', 'platform' => 'facebook|EGO Solar - Page A', 'content_type' => 'post', 'title' => 'Bài Facebook A', 'campaign_id' => 5,
                'created_by' => $actor->id, 'status' => 'scheduled', 'assignees' => json_encode(['An Nguyễn', 'Bình', 'an nguyễn', 'Chi', 'Dũng']), 'assignee' => null, 'assignee_user_id' => null, 'link' => 'https://www.facebook.com/egosolar/posts/1', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 990002, 'publish_date' => '2026-09-08', 'platform' => 'tiktok', 'content_type' => 'video', 'title' => 'Clip TikTok', 'campaign_id' => null,
                'created_by' => $actor->id, 'status' => 'posted', 'assignees' => null, 'assignee' => 'Hà, Khánh', 'assignee_user_id' => $lan->id, 'link' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $html = $this->actingAs($actor)->get(self::URL)->assertOk()->getContent();

        $this->assertStringContainsString('<option value="facebook">Facebook</option>', $html);
        $this->assertStringContainsString('<option value="scheduled">Lên lịch</option>', $html);
        // tiêu đề, mô tả (rỗng), link, tên user (rỗng), tên phụ trách đã bỏ trùng
        $this->assertStringContainsString('data-title="bài facebook a  https://www.facebook.com/egosolar/posts/1  an nguyễn bình chi dũng"', $html);
        $this->assertStringContainsString('data-title="clip tiktok   lan marketing hà khánh"', $html);
        $this->assertStringContainsString('<span class="cc-plat-acc">• EGO Solar - Page A</span>', $html);
        $this->assertStringContainsString('<i class="bi bi-link-45deg"></i> facebook.com', $html);
        $this->assertStringContainsString('Campaign #5', $html);
        $this->assertStringContainsString('cc-avatar-more', $html, '4 người phụ trách → +1');
        $this->assertStringContainsString('bg-warning cc-badge-status', $html);
        $this->assertMatchesRegularExpression('/<strong id="ccCountAll">2<\/strong>/', $html);
        $this->assertStringContainsString('08/09/2026 (', $html, 'nhãn ngày agenda');
    }

    /** View chỉ in: không `@php`; mọi `$item->x` là thuộc tính có thật của ContentCalendarEntry. */
    public function test_view_khong_tu_tinh(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$item->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(ContentCalendarEntry::class))->getProperties());
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), 'view đọc thuộc tính không có');
    }
}
