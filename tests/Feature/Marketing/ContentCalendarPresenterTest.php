<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\DTOs\Content\ContentCalendarEntry;
use App\Models\Content\ContentCalendar;
use App\Models\Content\ContentFile;
use App\View\Presenters\Content\ContentCalendarPresenter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * {@see ContentCalendarPresenter} thay 9 khối `@php` của marketing/reports/content_calendar
 * (2026-09-07). Model dựng trong bộ nhớ, không chạm DB.
 */
final class ContentCalendarPresenterTest extends TestCase
{
    private ContentCalendarPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new ContentCalendarPresenter;
    }

    public function test_tach_nen_tang_va_icon_nhan(): void
    {
        $entry = $this->entry(['platform' => 'facebook|EGO Solar - Page A']);
        $this->assertSame(['facebook', 'EGO Solar - Page A', 'bi-facebook', 'Facebook'], [$entry->platformType, $entry->platformAccount, $entry->platformIcon, $entry->platformLabel]);

        $entry = $this->entry(['platform' => ' YouTube|Kênh EGO ']);
        $this->assertSame(['youtube', 'Kênh EGO', 'bi-youtube', 'YouTube'], [$entry->platformType, $entry->platformAccount, $entry->platformIcon, $entry->platformLabel]);

        $entry = $this->entry(['platform' => 'zalo']);
        $this->assertSame(['zalo', '', 'bi-share', 'Zalo'], [$entry->platformType, $entry->platformAccount, $entry->platformIcon, $entry->platformLabel]);

        $entry = $this->entry(['platform' => '']);
        $this->assertSame(['', '', 'bi-share', ''], [$entry->platformType, $entry->platformAccount, $entry->platformIcon, $entry->platformLabel]);
    }

    public function test_nguoi_phu_trach_uu_tien_json_bo_trung_khong_phan_biet_hoa_thuong(): void
    {
        $entry = $this->entry(['assignees' => ['An Nguyễn', 'Bình', 'an nguyễn', ' ', 'Chi'], 'assignee' => 'Bỏ qua']);
        $this->assertSame(['An Nguyễn', 'Bình', 'Chi'], $entry->assignees);
        $this->assertSame(['An Nguyễn' => 'A', 'Bình' => 'B', 'Chi' => 'C'], $entry->assigneeInitials);

        $entry = $this->entry(['assignees' => null, 'assignee' => 'Hà, Khánh; Long | hà']);
        $this->assertSame(['Hà', 'Khánh', 'Long'], $entry->assignees);

        $entry = $this->entry(['assignees' => [], 'assignee' => '']);
        $this->assertSame([], $entry->assignees);
        $this->assertSame([], $entry->assigneeInitials);
    }

    public function test_trang_thai_ngay_link_chien_dich_va_file(): void
    {
        $file = new ContentFile(['file_name' => 'a.png']);
        $entry = $this->entry(
            ['status' => 'scheduled', 'publish_date' => '2026-09-10', 'link' => 'https://www.facebook.com/egosolar/posts/1', 'campaign_id' => 5],
            [$file, $file],
        );
        $this->assertSame(['warning', 'Lên lịch'], [$entry->statusColor, $entry->statusLabel]);
        $this->assertSame(['2026-09-10', '10/09/2026', '10/09'], [$entry->publishYmd, $entry->publishDmy, $entry->publishDm]);
        $this->assertSame('facebook.com', $entry->linkHost);
        $this->assertSame('Campaign #5', $entry->campaignText);
        $this->assertSame(2, $entry->filesCount);

        $entry = $this->entry(['status' => 'weird', 'link' => 'not a url', 'campaign_id' => null]);
        $this->assertSame(['secondary', 'WEIRD'], [$entry->statusColor, $entry->statusLabel]);
        $this->assertSame('', $entry->linkHost);
        $this->assertSame('Không chiến dịch', $entry->campaignText);
        $this->assertSame(0, $entry->filesCount);
    }

    public function test_chuoi_tim_kiem_gom_ten_user_va_ten_tach(): void
    {
        $entry = $this->entry(
            ['title' => 'Clip TikTok', 'description' => 'Mô Tả', 'link' => 'http://t.co/x', 'assignee_user_id' => 7, 'assignee' => 'Hà, Khánh'],
            [],
            collect([7 => 'Lan Marketing']),
        );
        $this->assertSame('clip tiktok mô tả http://t.co/x lan marketing hà khánh', $entry->searchText);
        $this->assertSame(7, $entry->assigneeUserId);

        $entry = $this->entry(['title' => 'A', 'assignee_user_id' => 99, 'assignees' => ['B']], [], collect([7 => 'Lan']));
        $this->assertSame('a    b', $entry->searchText, 'user không thuộc marketing → không có tên');
    }

    public function test_view_data_gom_loc_dem_ngay_va_kanban(): void
    {
        $items = new EloquentCollection([
            $this->model(['id' => 1, 'publish_date' => '2026-09-10', 'platform' => 'facebook|A', 'status' => 'scheduled', 'title' => 'Một']),
            $this->model(['id' => 2, 'publish_date' => '2026-09-10', 'platform' => 'tiktok', 'status' => 'posted', 'title' => 'Hai']),
            $this->model(['id' => 3, 'publish_date' => '2026-09-08', 'platform' => '', 'status' => 'draft', 'title' => 'Ba']),
            $this->model(['id' => 4, 'publish_date' => '2026-09-12', 'platform' => 'zalo', 'status' => 'weird', 'title' => 'Bốn']),
        ]);

        $data = $this->presenter->viewData($items, collect());

        $this->assertContainsOnlyInstancesOf(ContentCalendarEntry::class, $data['items']);
        $this->assertSame(['facebook' => 'Facebook', 'tiktok' => 'TikTok', 'zalo' => 'Zalo'], $data['platformOptions']);
        $this->assertSame(['draft' => 'Nháp', 'posted' => 'Đã đăng', 'scheduled' => 'Lên lịch', 'weird' => 'Weird'], $data['statusOptions']);
        $this->assertSame([4, 1, 1, 1], [$data['countAll'], $data['countDraft'], $data['countSch'], $data['countPost']]);
        $this->assertSame(ContentCalendarPresenter::STATUS_LABELS, $data['statusLabels']);

        $this->assertSame(['2026-09-08', '2026-09-10', '2026-09-12'], array_column($data['days'], 'ymd'));
        $this->assertSame(['Ba'], array_map(fn (ContentCalendarEntry $e) => $e->title, $data['days'][0]['items']));
        $this->assertSame(['Một', 'Hai'], array_map(fn (ContentCalendarEntry $e) => $e->title, $data['days'][1]['items']), 'cùng ngày giữ thứ tự gốc');
        $this->assertStringStartsWith('08/09/2026 (', $data['days'][0]['label']);

        $this->assertSame(['draft', 'scheduled', 'posted', 'submitted', 'approved', 'rejected'], array_column($data['kanbanColumns'], 'key'));
        $this->assertSame(['Ba'], array_map(fn (ContentCalendarEntry $e) => $e->title, $data['kanbanColumns'][0]['items']));
        $this->assertSame([], $data['kanbanColumns'][3]['items']);
    }

    /** @param  list<ContentFile>  $files */
    private function entry(array $attributes, array $files = [], ?Collection $userNames = null): ContentCalendarEntry
    {
        return $this->presenter->entry($this->model($attributes, $files), $userNames ?? collect());
    }

    /** @param  list<ContentFile>  $files */
    private function model(array $attributes, array $files = []): ContentCalendar
    {
        $item = (new ContentCalendar)->forceFill($attributes + [
            'id' => 1, 'title' => 'Tiêu đề', 'publish_date' => '2026-09-01', 'platform' => 'facebook', 'status' => 'draft', 'assignees' => null, 'assignee' => null,
        ]);
        $item->setRelation('files', new EloquentCollection($files));

        return $item;
    }
}
