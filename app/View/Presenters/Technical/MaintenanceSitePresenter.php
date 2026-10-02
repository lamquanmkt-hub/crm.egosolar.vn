<?php

declare(strict_types=1);

namespace App\View\Presenters\Technical;

use App\Models\Projects\Site;
use App\Models\SolarMaintenanceSchedule;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị giá trị cho view `technical.maintenance.site-show` (hồ sơ bảo trì một công trình).
 *
 * Trước 2026-09-07 view tự tính trong 5 khối `@php`: bảng màu trạng thái, số đợt xong / quá hạn,
 * đợt kế tiếp, % tiến độ, chu kỳ (tháng/lần) suy từ hai đợt đầu, link bản đồ, số đếm cho bộ lọc
 * Alpine, cờ theo chu kỳ và theo đợt. Tên khoá trả về giữ tên biến cũ của view (kể cả `tongDot`,
 * `soDotXong`… mà Alpine `x-data` đang đọc).
 */
final class MaintenanceSitePresenter
{
    private const STATUS_TONE = [
        'draft' => 'muted', 'scheduled' => 'info', 'unassigned' => 'warning', 'assigned' => 'info', 'customer_confirmed' => 'info', 'travelling' => 'info',
        'in_progress' => 'info', 'waiting_material' => 'warning', 'waiting_submission' => 'warning', 'pending_approval' => 'warning', 'revision_requested' => 'danger',
        'approved' => 'success', 'waiting_customer' => 'warning', 'completed' => 'success', 'postponed' => 'warning', 'cancelled' => 'danger',
    ];

    private const DONE_STATUSES = ['approved', 'completed'];

    private const DOCUMENT_CATEGORIES = [
        'contract' => 'Hợp đồng', 'survey' => 'Biên bản khảo sát', 'handover' => 'Biên bản bàn giao', 'acceptance' => 'Biên bản nghiệm thu', 'diagram' => 'Sơ đồ điện',
        'datasheet' => 'Datasheet', 'warranty' => 'Phiếu bảo hành', 'invoice' => 'Hóa đơn', 'overview' => 'Ảnh tổng thể', 'video' => 'Video', 'other' => 'Khác',
    ];

    /**
     * @param  Collection<int, SolarMaintenanceSchedule>  $schedules  mọi lịch của công trình
     * @param  Collection<int, array<string, mixed>>  $cycles  chu kỳ (mỗi phần tử có `items`)
     * @return array<string, mixed> chỉ các giá trị dẫn xuất; controller gộp với dữ liệu gốc
     */
    public function viewData(?Site $site, Collection $schedules, Collection $cycles): array
    {
        $completedCount = $schedules->whereIn('status', self::DONE_STATUSES)->count();
        $activeSchedules = $schedules->reject(fn (SolarMaintenanceSchedule $item) => in_array($item->status, [...self::DONE_STATUSES, 'cancelled'], true));
        $nextSchedule = $activeSchedules->sortBy(fn (SolarMaintenanceSchedule $item) => optional($item->scheduled_date)->timestamp ?: PHP_INT_MAX)->first();
        $sortedDates = $schedules->filter(fn (SolarMaintenanceSchedule $item) => $item->scheduled_date)->sortBy('scheduled_date')->values();
        $cycleMonths = $sortedDates->count() >= 2 ? max(1, (int) round(abs($sortedDates[0]->scheduled_date->diffInMonths($sortedDates[1]->scheduled_date)))) : null;
        $shownItems = $cycles->flatMap(fn (array $cycle) => $cycle['items']);
        $shownTotal = $shownItems->count();
        $shownDone = $shownItems->filter(fn (SolarMaintenanceSchedule $item) => $this->isDone($item))->count();

        return [
            'statusTone' => self::STATUS_TONE,
            'documentCategories' => self::DOCUMENT_CATEGORIES,
            'completedCount' => $completedCount,
            'overdueCount' => $schedules->filter(fn (SolarMaintenanceSchedule $item) => $item->isOverdue())->count(),
            'activeSchedules' => $activeSchedules,
            'nextSchedule' => $nextSchedule,
            'progressPercent' => $schedules->count() ? min(100, (int) round(($completedCount / $schedules->count()) * 100)) : 0,
            'cycleLabel' => $cycleMonths ? $cycleMonths.' tháng/lần' : ($schedules->count() > 1 ? 'Theo lịch công trình' : 'Theo yêu cầu'),
            'acceptedDate' => $site?->accepted_at ?? $site?->installed_at ?? null,
            'mapUrl' => $site?->address ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($site->address) : null,
            // Số đếm cho bộ lọc đợt của Alpine: giao diện chỉ so sánh, không dò DOM đếm lại.
            'tongDot' => $shownTotal,
            'soDotXong' => $shownDone,
            'soDotMo' => $shownTotal - $shownDone,
            'soDotQuaHan' => $shownItems->filter(fn (SolarMaintenanceSchedule $item) => $item->isOverdue())->count(),
            'cycles' => $cycles->map(fn (array $cycle) => $this->cycle($cycle, $nextSchedule)),
        ];
    }

    /**
     * Chu kỳ: cờ có đợt xong / còn mở / quá hạn (để ẩn cả chu kỳ theo bộ lọc) và từng đợt kèm cờ.
     *
     * @param  array<string, mixed>  $cycle
     * @return array<string, mixed>
     */
    private function cycle(array $cycle, ?SolarMaintenanceSchedule $nextSchedule): array
    {
        $items = collect($cycle['items']);

        return $cycle + [
            'hasDone' => $items->contains(fn (SolarMaintenanceSchedule $item) => $this->isDone($item)),
            'hasOpen' => $items->contains(fn (SolarMaintenanceSchedule $item) => ! $this->isDone($item)),
            'hasOverdue' => $items->contains(fn (SolarMaintenanceSchedule $item) => $item->isOverdue()),
            'rows' => $items->map(fn (SolarMaintenanceSchedule $item) => [
                'item' => $item,
                'done' => $this->isDone($item),
                'overdue' => $item->isOverdue(),
                'active' => $nextSchedule && (int) $nextSchedule->id === (int) $item->id,
            ])->values()->all(),
        ];
    }

    private function isDone(SolarMaintenanceSchedule $item): bool
    {
        return in_array($item->status, self::DONE_STATUSES, true);
    }
}
