<?php

declare(strict_types=1);

namespace App\View\Presenters\Technical;

use App\DTOs\Technical\MaintenanceScheduleRow;
use App\Models\SolarMaintenanceSchedule;
use App\Models\SolarWarrantyClaim;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\ViewErrorBag;

/**
 * Chuẩn bị giá trị cho view `technical.maintenance.index` (dashboard O&M / bảo hành).
 *
 * Trước 2026-09-07 view khai bảng màu trạng thái, tab, luồng, KPI, tính vòng tròn tiến độ, gộp
 * lỗi form và cấu hình JS trong `@php` 100 dòng, rồi 5 khối nhỏ tính theo từng lịch / kỹ thuật
 * viên / đợt / thẻ KPI. Tên khoá trả về giữ tên biến cũ của view; kiểm bằng so HTML 7 trang.
 */
final class MaintenanceDashboardPresenter
{
    private const STATUS_TONE = [
        'draft' => 'muted', 'scheduled' => 'info', 'unassigned' => 'violet', 'assigned' => 'indigo', 'customer_confirmed' => 'cyan',
        'travelling' => 'cyan', 'in_progress' => 'warning', 'waiting_material' => 'orange', 'waiting_submission' => 'slate',
        'pending_approval' => 'pending', 'revision_requested' => 'orange', 'approved' => 'success', 'waiting_customer' => 'violet',
        'completed' => 'success', 'postponed' => 'orange', 'cancelled' => 'danger',
    ];

    private const CLAIM_TONE = [
        'received' => 'info', 'eligibility_check' => 'violet', 'diagnosing' => 'warning', 'solution_proposed' => 'cyan',
        'pending_approval' => 'pending', 'approved' => 'success', 'waiting_stock' => 'orange', 'replacing' => 'warning',
        'waiting_customer' => 'violet', 'completed' => 'success', 'rejected' => 'danger', 'cancelled' => 'muted',
    ];

    private const PRIORITY_TONE = ['low' => 'muted', 'normal' => 'info', 'high' => 'orange', 'urgent' => 'danger'];

    private const STOCK_TONE = ['pending' => 'pending', 'approved' => 'info', 'completed' => 'success', 'cancelled' => 'danger'];

    /** Trạng thái do quy trình duyệt quyết định, không cho đổi tay trong modal. */
    private const WORKFLOW_ONLY_STATUSES = ['pending_approval', 'approved', 'revision_requested', 'completed'];

    private const TABS = [
        'overview' => ['label' => 'Tổng quan O&M', 'icon' => 'bi-grid-1x2'],
        'maintenance' => ['label' => 'Lịch bảo trì', 'icon' => 'bi-calendar2-week'],
        'claims' => ['label' => 'Phiếu sự cố & bảo hành', 'icon' => 'bi-shield-exclamation'],
        'stock' => ['label' => 'Kho & đổi thiết bị', 'icon' => 'bi-box-seam'],
        'files' => ['label' => 'Hồ sơ công trình', 'icon' => 'bi-folder2-open'],
    ];

    private const MAINTENANCE_FLOW = [
        ['icon' => 'calendar-plus', 'label' => 'Tạo kế hoạch'], ['icon' => 'people', 'label' => 'Phân công'], ['icon' => 'person-check', 'label' => 'Khách xác nhận'],
        ['icon' => 'tools', 'label' => 'Thực hiện'], ['icon' => 'file-earmark-text', 'label' => 'Báo cáo'], ['icon' => 'shield-check', 'label' => 'Trưởng phòng duyệt'],
        ['icon' => 'check2-square', 'label' => 'Hoàn thành'], ['icon' => 'arrow-repeat', 'label' => 'Sinh đợt tiếp'],
    ];

    private const WARRANTY_FLOW = [
        ['icon' => 'exclamation-triangle', 'label' => 'Tiếp nhận'], ['icon' => 'shield-search', 'label' => 'Kiểm tra BH'], ['icon' => 'search', 'label' => 'Chẩn đoán'],
        ['icon' => 'lightbulb', 'label' => 'Đề xuất'], ['icon' => 'clipboard-check', 'label' => 'Duyệt'], ['icon' => 'box-arrow-up', 'label' => 'Kho xuất'],
        ['icon' => 'box-arrow-in-down', 'label' => 'Thu hồi lỗi'], ['icon' => 'wrench-adjustable', 'label' => 'Thay thế'], ['icon' => 'person-check', 'label' => 'Khách xác nhận'],
        ['icon' => 'shield-check', 'label' => 'Đóng phiếu'],
    ];

    private const STOCK_TRANSITIONS = [
        'pending' => ['approved', 'completed', 'cancelled'],
        'approved' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => ['pending'],
    ];

    private const ROUND_TONES = ['green', 'amber', 'blue', 'violet'];

    /**
     * @param  array<string, mixed>  $summary  số liệu lịch bảo trì (SolarMaintenanceQueryService::summary)
     * @param  array<string, mixed>  $warrantySummary  số liệu phiếu bảo hành
     * @param  array<string, string>  $statuses  mã → nhãn trạng thái lịch
     * @param  array<string, bool>  $permissions  create, claim_create, stock_manage, approve, manager…
     * @param  iterable<SolarMaintenanceSchedule>  $overviewSchedules  lịch ưu tiên ở tab tổng quan
     * @param  iterable<SolarMaintenanceSchedule>  $schedules  danh sách lịch (có thể là paginator)
     * @param  iterable<array<string, mixed>>  $technicianWorkload  mỗi phần tử có `user`
     * @param  iterable<array<string, mixed>>  $maintenanceRounds  round, total, start_date, end_date, days_to_start, progress
     * @return array<string, mixed> chỉ các giá trị dẫn xuất; controller gộp với dữ liệu gốc
     */
    public function viewData(array $summary, array $warrantySummary, array $statuses, array $permissions, iterable $overviewSchedules, iterable $schedules, iterable $technicianWorkload, iterable $maintenanceRounds, ViewErrorBag $errors): array
    {
        $operationTotal = max(1, (int) ($summary['total'] ?? 0));
        $scheduledPct = round((($summary['scheduled_bucket'] ?? 0) / $operationTotal) * 100);
        $processingPct = round((($summary['processing_bucket'] ?? 0) / $operationTotal) * 100);
        $approvalPct = round((($summary['approval_bucket'] ?? 0) / $operationTotal) * 100);
        $completedPct = round((($summary['completed'] ?? 0) / $operationTotal) * 100);

        return [
            'statusTone' => self::STATUS_TONE,
            'claimTone' => self::CLAIM_TONE,
            'priorityTone' => self::PRIORITY_TONE,
            'stockTone' => self::STOCK_TONE,
            'manualStatuses' => array_diff_key($statuses, array_flip(self::WORKFLOW_ONLY_STATUSES)),
            'tabs' => self::TABS,
            'maintenanceFlow' => self::MAINTENANCE_FLOW,
            'warrantyFlow' => self::WARRANTY_FLOW,

            // Vòng tròn tiến độ vận hành: 4 cung nối tiếp, mỗi mốc là góc kết thúc (độ).
            'scheduledPct' => $scheduledPct,
            'processingPct' => $processingPct,
            'approvalPct' => $approvalPct,
            'completedPct' => $completedPct,
            'scheduledDeg' => round($scheduledPct * 3.6, 2),
            'processingEndDeg' => round(min(100, $scheduledPct + $processingPct) * 3.6, 2),
            'approvalEndDeg' => round(min(100, $scheduledPct + $processingPct + $approvalPct) * 3.6, 2),
            'completedEndDeg' => round(min(100, $scheduledPct + $processingPct + $approvalPct + $completedPct) * 3.6, 2),
            'pendingApprovalTotal' => (int) ($summary['pending_approval'] ?? 0) + (int) ($warrantySummary['pending_approval'] ?? 0),
            'completedThisMonth' => (int) ($summary['completed_this_month'] ?? 0) + (int) ($warrantySummary['completed_this_month'] ?? 0),

            'maintenanceKpis' => [
                $this->kpi('calendar2-week', 'Tổng lịch', $summary['total'] ?? 0, 'blue', ''),
                $this->kpi('calendar-check', 'Hôm nay', $summary['today'] ?? 0, 'green', ''),
                $this->kpi('alarm', 'Sắp đến hạn', $summary['upcoming'] ?? 0, 'amber', ''),
                $this->kpi('exclamation-octagon', 'Quá hạn', $summary['overdue'] ?? 0, 'red', 'overdue'),
                $this->kpi('person-exclamation', 'Chờ phân công', $summary['unassigned'] ?? 0, 'violet', 'unassigned'),
                $this->kpi('patch-question', 'Chờ duyệt', $summary['pending_approval'] ?? 0, 'teal', 'pending_approval'),
            ],
            'claimKpis' => [
                ['icon' => 'shield-exclamation', 'label' => 'Tổng phiếu', 'value' => $warrantySummary['total'] ?? 0, 'tone' => 'blue'],
                ['icon' => 'activity', 'label' => 'Đang mở', 'value' => $warrantySummary['open'] ?? 0, 'tone' => 'amber'],
                ['icon' => 'patch-question', 'label' => 'Chờ duyệt', 'value' => $warrantySummary['pending_approval'] ?? 0, 'tone' => 'violet'],
                ['icon' => 'box-seam', 'label' => 'Chờ kho', 'value' => $warrantySummary['waiting_stock'] ?? 0, 'tone' => 'red'],
                ['icon' => 'check2-circle', 'label' => 'Hoàn thành', 'value' => $warrantySummary['completed'] ?? 0, 'tone' => 'green'],
            ],

            'overviewRows' => collect($overviewSchedules)->map(fn (SolarMaintenanceSchedule $schedule) => $this->row($schedule))->values(),
            'scheduleRows' => $this->items($schedules)->map(fn (SolarMaintenanceSchedule $schedule) => $this->row($schedule))->values(),
            'technicianWorkload' => collect($technicianWorkload)->map(fn (array $workload) => $workload + [
                'initial' => mb_strtoupper(mb_substr(trim((string) $workload['user']->name), 0, 1)),
            ]),
            'maintenanceRounds' => collect($maintenanceRounds)->map(fn (array $round) => $this->round($round)),

            'allFormErrors' => $this->allFormErrors($errors),
            'tmConfigForJs' => [
                'scheduleHasErrors' => $errors->getBag('default')->any(),
                'claimHasErrors' => $errors->getBag('warrantyClaim')->any(),
                'stockHasErrors' => $errors->getBag('warrantyStock')->any(),
                'canCreate' => (bool) ($permissions['create'] ?? false),
                'canCreateClaim' => (bool) ($permissions['claim_create'] ?? false),
                'canManageStock' => (bool) ($permissions['stock_manage'] ?? false),
                'canApprove' => (bool) ($permissions['approve'] ?? false),
                'isManager' => (bool) ($permissions['manager'] ?? false),
                'claimTransitions' => SolarWarrantyClaim::TRANSITIONS,
                'stockTransitions' => self::STOCK_TRANSITIONS,
            ],
        ];
    }

    /**
     * Một dòng lịch: quá hạn, đợt, trưởng nhóm (assignee leader → tên đã gán), chữ tắt, nhãn thời gian.
     */
    public function row(SolarMaintenanceSchedule $schedule): MaintenanceScheduleRow
    {
        $overdue = $schedule->isOverdue();
        $leaderName = $schedule->leader?->user?->name ?: $schedule->assignee_names;
        $isUnassigned = trim((string) $leaderName) === '' || $leaderName === 'Chưa phân công';
        $scheduledDate = $schedule->scheduled_date;
        $daysDelta = $scheduledDate ? (int) today()->diffInDays($scheduledDate, false) : null;

        [$timeLabel, $timeTone] = match (true) {
            $schedule->status === 'completed' => ['Đã hoàn thành', 'done'],
            $overdue => ['Quá hạn '.abs((int) $daysDelta).' ngày', 'overdue'],
            $daysDelta === 0 => ['Hôm nay', 'today'],
            $daysDelta !== null && $daysDelta > 0 => ['Còn '.$daysDelta.' ngày', $daysDelta <= 3 ? 'soon' : 'safe'],
            default => ['Chưa xác định', 'muted'],
        };

        return new MaintenanceScheduleRow(
            schedule: $schedule,
            overdue: $overdue,
            roundNo: max(1, (int) ($schedule->round_no ?: 1)),
            totalRounds: max(1, (int) ($schedule->total_rounds ?: 1)),
            leaderName: $leaderName,
            isUnassigned: $isUnassigned,
            avatarInitial: $isUnassigned ? '!' : mb_strtoupper(mb_substr(trim((string) $leaderName), 0, 1)),
            scheduledDate: $scheduledDate,
            timeLabel: $timeLabel,
            timeTone: $timeTone,
            capacity: $schedule->site?->system_kwp ?: $schedule->system_kwp,
        );
    }

    /**
     * Danh sách lịch có thể là paginator: collect($paginator) duyệt mảng meta (current_page, data…),
     * phải lấy items().
     *
     * @return Collection<int, mixed>
     */
    private function items(mixed $list): Collection
    {
        return $list instanceof Paginator ? collect($list->items()) : collect($list);
    }

    /** @return array{icon: string, label: string, value: mixed, tone: string, params: array<string, mixed>} */
    private function kpi(string $icon, string $label, mixed $value, string $tone, string $filter): array
    {
        $params = ['view' => 'maintenance', 'month' => ''];
        if ($filter === 'overdue') {
            $params['overdue'] = 1;
        } elseif ($filter !== '') {
            $params['status'] = $filter;
        }

        return ['icon' => $icon, 'label' => $label, 'value' => $value, 'tone' => $tone, 'params' => $params];
    }

    /**
     * Đợt bảo trì: màu theo số đợt, nhãn khoảng ngày và nhãn thời gian tới ngày bắt đầu.
     *
     * @param  array<string, mixed>  $round
     * @return array<string, mixed>
     */
    private function round(array $round): array
    {
        $dateLabel = ($round['start_date'] && $round['end_date'])
            ? $round['start_date']->format('d/m').' - '.$round['end_date']->format('d/m/Y')
            : 'Chưa thiết lập thời gian';

        $timeLabel = match (true) {
            $round['total'] === 0 => 'Chưa có kế hoạch',
            $round['days_to_start'] === null => 'Chưa xác định ngày',
            $round['days_to_start'] > 0 => 'Còn '.$round['days_to_start'].' ngày',
            $round['days_to_start'] === 0 => 'Bắt đầu hôm nay',
            default => 'Đã bắt đầu '.abs((int) $round['days_to_start']).' ngày',
        };

        return $round + [
            'tone' => self::ROUND_TONES[($round['round'] - 1) % 4],
            'dateLabel' => $dateLabel,
            'timeLabel' => $timeLabel,
        ];
    }

    /**
     * Mọi lỗi của mọi error bag (lịch, phiếu bảo hành, kho) gộp một danh sách không trùng.
     *
     * @return Collection<int, string>
     */
    private function allFormErrors(ViewErrorBag $errors): Collection
    {
        $messages = collect();
        foreach ($errors->getBags() as $bag) {
            foreach ($bag->all() as $message) {
                $messages->push($message);
            }
        }

        return $messages->unique()->values();
    }
}
