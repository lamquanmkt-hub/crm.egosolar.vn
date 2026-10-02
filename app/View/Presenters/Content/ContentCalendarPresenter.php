<?php

declare(strict_types=1);

namespace App\View\Presenters\Content;

use App\DTOs\Content\ContentCalendarEntry;
use App\Models\Content\ContentCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Chuẩn bị giá trị cho view `marketing.reports.content_calendar`.
 *
 * Trước 2026-09-07 view khai 5 closure (tách nền tảng "facebook|Trang", gộp người phụ trách,
 * icon/nhãn nền tảng) trong `@php` 115 dòng rồi gọi lại trong 3 khối hiển thị + modal; nay mỗi
 * mục được dựng một lần thành {@see ContentCalendarEntry}.
 */
final class ContentCalendarPresenter
{
    public const STATUS_LABELS = [
        'draft' => 'Nháp',
        'scheduled' => 'Lên lịch',
        'posted' => 'Đã đăng',
        'submitted' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
    ];

    private const STATUS_COLORS = [
        'draft' => 'secondary',
        'scheduled' => 'warning',
        'posted' => 'success',
        'submitted' => 'info',
        'approved' => 'primary',
        'rejected' => 'danger',
    ];

    private const PLATFORM_ICONS = [
        'facebook' => 'bi-facebook',
        'tiktok' => 'bi-tiktok',
        'youtube' => 'bi-youtube',
        'website' => 'bi-globe2',
    ];

    private const PLATFORM_LABELS = [
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'website' => 'Website',
    ];

    /**
     * @param  Collection<int, ContentCalendar>  $items  đã sắp theo ngày đăng giảm dần, đã nạp files
     * @param  Collection<int, object>  $marketingUsers  user có role marketing (id, name, email)
     * @return array<string, mixed>
     */
    public function viewData(Collection $items, Collection $marketingUsers): array
    {
        $userNames = $marketingUsers->keyBy('id')->map(fn ($user) => (string) $user->name);
        $entries = $items->map(fn (ContentCalendar $item) => $this->entry($item, $userNames))->values();
        $byDate = $entries->sortBy('publishYmd');

        return [
            'items' => $entries,
            'marketingUsers' => $marketingUsers,
            'statusLabels' => self::STATUS_LABELS,
            'platformOptions' => $entries->pluck('platformType')->filter()->unique()->sort()
                ->mapWithKeys(fn (string $type) => [$type => $this->platformLabel($type)])->all(),
            'statusOptions' => $entries->pluck('status')->filter()->unique()->sort()
                ->mapWithKeys(fn (string $status) => [$status => self::STATUS_LABELS[$status] ?? Str::ucfirst($status)])->all(),
            'countAll' => $entries->count(),
            'countDraft' => $entries->where('status', 'draft')->count(),
            'countSch' => $entries->where('status', 'scheduled')->count(),
            'countPost' => $entries->where('status', 'posted')->count(),
            'days' => $byDate->groupBy('publishYmd')->map(fn (Collection $dayEntries, string $ymd) => [
                'ymd' => $ymd,
                'label' => Carbon::parse($ymd)->translatedFormat('d/m/Y (l)'),
                'items' => $dayEntries->values()->all(),
            ])->values()->all(),
            'kanbanColumns' => collect(self::STATUS_LABELS)->map(fn (string $label, string $status) => [
                'key' => $status,
                'label' => $label,
                'items' => $byDate->where('status', $status)->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Chuỗi tìm kiếm ẩn (`data-title`) gồm tiêu đề, mô tả, link, tên user phụ trách VÀ các tên
     * phụ trách tách từ cột — trước 2026-09-07 khối danh sách chỉ lấy tên user, agenda/kanban
     * chỉ lấy tên tách, nên gõ cùng một từ khoá ra kết quả khác nhau giữa ba khối.
     *
     * @param  Collection<int, string>  $userNames  id user marketing → tên
     */
    public function entry(ContentCalendar $item, Collection $userNames): ContentCalendarEntry
    {
        [$platformType, $platformAccount] = $this->parsePlatform($item->platform);
        $assignees = $this->parseAssignees($item);
        $assigneeName = $item->assignee_user_id ? $userNames->get($item->assignee_user_id) : null;
        $publishDate = Carbon::parse($item->publish_date);

        return new ContentCalendarEntry(
            id: (int) $item->id,
            title: (string) $item->title,
            description: $item->description,
            link: $item->link,
            linkHost: $this->linkHost($item->link),
            status: (string) $item->status,
            statusColor: self::STATUS_COLORS[$item->status] ?? 'secondary',
            statusLabel: self::STATUS_LABELS[$item->status] ?? strtoupper((string) $item->status),
            contentType: $item->content_type,
            campaignText: $item->campaign_id ? 'Campaign #'.$item->campaign_id : 'Không chiến dịch',
            filesCount: $item->files ? $item->files->count() : 0,
            assigneeUserId: $item->assignee_user_id === null ? null : (int) $item->assignee_user_id,
            assignee: $item->assignee,
            platform: $item->platform,
            platformType: $platformType,
            platformAccount: $platformAccount,
            platformIcon: self::PLATFORM_ICONS[$platformType] ?? 'bi-share',
            platformLabel: $this->platformLabel($platformType),
            publishYmd: $publishDate->format('Y-m-d'),
            publishDmy: $publishDate->format('d/m/Y'),
            publishDm: $publishDate->format('d/m'),
            assignees: $assignees,
            assigneeInitials: array_combine($assignees, array_map(fn (string $name) => Str::upper(mb_substr(trim($name), 0, 1)), $assignees)),
            searchText: Str::lower(implode(' ', [$item->title, $item->description ?? '', $item->link ?? '', $assigneeName ?? '', implode(' ', $assignees)])),
        );
    }

    /**
     * "facebook|EGO Solar - Page A" → [facebook, "EGO Solar - Page A"]; "facebook" → [facebook, ""].
     *
     * @return array{0: string, 1: string}
     */
    private function parsePlatform(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return ['', ''];
        }
        if (Str::contains($raw, '|')) {
            [$type, $account] = array_pad(explode('|', $raw, 2), 2, '');

            return [Str::lower(trim($type)), trim($account)];
        }

        return [Str::lower($raw), ''];
    }

    /**
     * Ưu tiên cột `assignees` (JSON), thiếu thì tách chuỗi `assignee` "A, B; C"; bỏ trùng không
     * phân biệt hoa thường, giữ lần xuất hiện đầu.
     *
     * @return list<string>
     */
    private function parseAssignees(ContentCalendar $item): array
    {
        $names = [];
        $raw = $item->assignees;
        if (is_array($raw)) {
            $names = $raw;
        } elseif (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $names = $decoded;
            }
        }

        if (empty($names)) {
            $names = array_values(array_filter(array_map('trim', preg_split('/,|;|\|/', (string) ($item->assignee ?? '')) ?: [])));
        }

        $seen = [];
        $unique = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $name;
        }

        return $unique;
    }

    private function platformLabel(string $type): string
    {
        return self::PLATFORM_LABELS[$type] ?? Str::ucfirst($type);
    }

    /** Host của link, bỏ "www."; link không hợp lệ thì rỗng. */
    private function linkHost(?string $link): string
    {
        if (empty($link)) {
            return '';
        }
        $parts = parse_url($link);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';

        return (string) preg_replace('/^www\./', '', $host);
    }
}
