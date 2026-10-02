<?php

declare(strict_types=1);

namespace App\View\Presenters\Projects;

use App\DTOs\Projects\MaterialProposalItemRow;
use App\DTOs\Projects\MaterialProposalRow;
use App\DTOs\Projects\ProjectRailItem;
use App\Models\Projects\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị giá trị cho view `projects-unified.show` (trang dự án hợp nhất, phần khung + bước
 * "Đề xuất vật tư"; các partial tài chính/workflow có presenter riêng sau).
 *
 * Trước 2026-09-08 view tự tính trong 4 khối `@php` (100 dòng): bước đang mở (đọc `request()`),
 * tiến độ, tên khách, thanh quy trình với biểu tượng/tông/dòng trạng thái từng bước (62 dòng
 * rẽ nhánh theo trạng thái workflow, hồ sơ thiếu, quá hạn, đề xuất vật tư), và bảng đề xuất vật
 * tư (nhãn/tông trạng thái, số lượng bỏ số 0 thừa). Tên khoá trả về giữ tên biến cũ của view;
 * kiểm bằng so HTML 11 trang (7 bước admin + 3 bước kỹ thuật + bước lạ).
 */
final class UnifiedProjectDetailPresenter
{
    private const STEPS = ['overview', 'survey', 'contract', 'materials', 'construction', 'acceptance'];

    /** code => [nhãn trên rail, tiêu đề ở hero — null là "Tổng quan"] */
    private const RAIL = [
        'overview' => ['Tổng quan', null],
        'survey' => ['Khảo sát & PA', 'Khảo sát & Phương án'],
        'contract' => ['HĐ & Pháp lý', 'Hợp đồng & Pháp lý'],
        'materials' => ['Đề xuất vật tư', 'Đề xuất vật tư'],
        'construction' => ['Thi công', 'Thi công'],
        'acceptance' => ['Nghiệm thu', 'Nghiệm thu'],
        'finance' => ['Tài chính công trình', 'Tài chính công trình'],
    ];

    private const MATERIAL_RAIL_STATUS = [
        'SUBMITTED' => 'chờ Admin', 'NEEDS_REVISION' => 'cần sửa', 'ADMIN_APPROVED' => 'chờ Kho', 'PARTIALLY_ALLOCATED' => 'Kho đang soạn',
        'WAREHOUSE_ALLOCATED' => 'Kho đã soạn', 'READY_FOR_EXPORT' => 'chờ xuất kho', 'EXPORTED' => 'đã xuất kho',
    ];

    public const PROPOSAL_STATUS_LABELS = [
        'SUBMITTED' => 'Chờ Admin duyệt', 'NEEDS_REVISION' => 'Cần chỉnh sửa', 'ADMIN_APPROVED' => 'Đã duyệt · Chờ Kho', 'PARTIALLY_ALLOCATED' => 'Kho đang soạn',
        'WAREHOUSE_ALLOCATED' => 'Kho đã soạn', 'READY_FOR_EXPORT' => 'Đã chuyển Kho', 'EXPORTED' => 'Đã xuất kho',
    ];

    private const WAITING_STATUSES = ['SUBMITTED', 'NEEDS_REVISION'];

    private const WAREHOUSE_STATUSES = ['ADMIN_APPROVED', 'PARTIALLY_ALLOCATED', 'WAREHOUSE_ALLOCATED', 'READY_FOR_EXPORT'];

    /**
     * @param  array<string, mixed>  $project  presentProject() của controller
     * @param  array<string, mixed>  $workflow  ProjectWorkflowV2Service::present()
     * @param  array<string, mixed>  $progressEngine  calculateProjectProgress()
     * @param  Collection<int, object>  $materialProposals  mới nhất trước
     * @param  Collection<int, Collection<int, object>>  $materialProposalItems  khoá theo proposal_id
     * @param  string|null  $requestedStep  `?step=` trên URL
     * @return array<string, mixed>
     */
    public function viewData(Site $site, array $project, array $workflow, array $progressEngine, Collection $materialProposals, Collection $materialProposalItems, bool $canSeeFinance, ?string $requestedStep): array
    {
        $allowedSteps = self::STEPS;
        if ($canSeeFinance) {
            $allowedSteps[] = 'finance';
        }
        $uiStep = in_array((string) $requestedStep, $allowedSteps, true) ? (string) $requestedStep : 'overview';
        $deploymentProgress = (int) ($workflow['progress'] ?? $progressEngine['calculated'] ?? $project['progress'] ?? 0);

        $rail = [];
        foreach (self::RAIL as $code => [$label]) {
            if ($code === 'finance' && ! $canSeeFinance) {
                continue;
            }
            $rail[] = $this->railItem($code, $label, $workflow, $deploymentProgress, $materialProposals);
        }

        return [
            'uiStep' => $uiStep,
            'deploymentProgress' => $deploymentProgress,
            'customerName' => data_get($site, 'customer.name')
                ?? data_get($site, 'client.name')
                ?? ($site->customer_name ?? $site->client_name ?? 'Chưa cập nhật'),
            'projectStatus' => $project['phase_info']['label'] ?? 'Đang thực hiện',
            'railTitle' => self::RAIL[$uiStep][1] ?? 'Tổng quan',
            'rail' => $rail,
            'proposalRows' => $materialProposals
                ->map(fn (object $proposal) => $this->proposalRow($proposal, $materialProposalItems->get((int) $proposal->id, collect())))
                ->values()
                ->all(),
            'waitingCount' => $materialProposals->whereIn('status', self::WAITING_STATUSES)->count(),
            'warehouseCount' => $materialProposals->whereIn('status', self::WAREHOUSE_STATUSES)->count(),
            'exportedCount' => $materialProposals->where('status', 'EXPORTED')->count(),
        ];
    }

    /** @param  array<string, mixed>  $workflow */
    private function railItem(string $code, string $label, array $workflow, int $deploymentProgress, Collection $materialProposals): ProjectRailItem
    {
        $row = in_array($code, ['materials', 'finance'], true) ? null : ($workflow['steps'][$code] ?? null);
        $done = $row && (($row['status'] ?? '') === 'approved');
        $icon = '○';
        $tone = 'muted';
        $status = 'Chưa bắt đầu';

        if ($code === 'overview') {
            [$icon, $tone, $status] = ['•', 'working', 'Tiến độ '.$deploymentProgress.'%'];
        } elseif ($code === 'finance') {
            [$icon, $tone, $status] = ['₫', 'working', 'Chỉ Admin · Thu chi & giá vốn'];
        } elseif ($code === 'materials') {
            $count = $materialProposals->count();
            if ($count > 0) {
                $latest = (string) ($materialProposals->first()->status ?? 'SUBMITTED');
                $icon = $latest === 'EXPORTED' ? '✓' : '•';
                $tone = $latest === 'EXPORTED' ? 'complete' : 'working';
                $status = $count.' đề xuất · '.(self::MATERIAL_RAIL_STATUS[$latest] ?? 'đang xử lý');
            } else {
                $status = 'Chưa có đề xuất';
            }
        } elseif ($row) {
            $stepStatus = (string) ($row['status'] ?? 'not_assigned');
            $missingCount = count($row['document_state']['file_missing'] ?? $row['document_state']['missing'] ?? []);
            $overdueDays = (int) ($row['overdue_days'] ?? 0);
            $stepRow = $row['row'] ?? null;

            if ($overdueDays > 0 && $stepStatus !== 'approved') {
                [$icon, $tone, $status] = ['!', 'danger', 'Quá hạn '.$overdueDays.' ngày'];
            } elseif ($stepStatus === 'approved') {
                $approvedAt = $stepRow->approved_at ?? $stepRow->updated_at ?? null;
                [$icon, $tone] = ['✓', 'complete'];
                $status = 'Đã duyệt'.($missingCount > 0
                    ? ' · còn thiếu '.$missingCount.' hồ sơ'
                    : ($approvedAt ? ' · '.Carbon::parse($approvedAt)->format('d/m/Y') : ''));
            } elseif ($stepStatus === 'revision') {
                [$icon, $tone] = ['!', 'danger'];
                $status = 'Cần bổ sung'.($missingCount > 0 ? ' · thiếu '.$missingCount.' hồ sơ' : '');
            } elseif ($stepStatus === 'submitted') {
                [$icon, $tone, $status] = ['•', 'pending', 'Đang chờ duyệt'];
            } elseif (in_array($stepStatus, ['assigned', 'in_progress'], true)) {
                [$icon, $tone] = ['•', $missingCount > 0 ? 'warning' : 'working'];
                $status = 'Đang làm'.($missingCount > 0 ? ' · còn thiếu '.$missingCount.' hồ sơ' : ' · đủ hồ sơ');
            } else {
                $status = 'Chưa phân công';
            }
        }

        return new ProjectRailItem(code: $code, label: $label, done: (bool) $done, icon: $icon, tone: $tone, status: $status);
    }

    /** @param  Collection<int, object>  $items */
    private function proposalRow(object $proposal, Collection $items): MaterialProposalRow
    {
        $status = (string) $proposal->status;

        return new MaterialProposalRow(
            proposal: $proposal,
            items: $items->map(fn (object $item) => new MaterialProposalItemRow(item: $item, quantityText: self::quantity($item->requested_qty)))->values(),
            status: $status,
            statusLabel: self::PROPOSAL_STATUS_LABELS[$status] ?? $status,
            tone: match ($status) {
                'SUBMITTED' => 'pending',
                'NEEDS_REVISION' => 'revision',
                'EXPORTED' => 'complete',
                default => 'warehouse',
            },
            linkedMaterialRequestId: ($linked = $items->pluck('material_request_id')->filter()->first()) !== null ? (int) $linked : null,
            itemCount: $items->count(),
            totalQuantityText: self::quantity($items->sum('requested_qty')),
        );
    }

    /** `20,50` → `20,5`, `3,00` → `3` — bỏ số 0 thừa sau dấu phẩy (khác DisplayFormat::quantity giữ hai chữ số). */
    private static function quantity(mixed $quantity): string
    {
        return rtrim(rtrim(number_format((float) $quantity, 2, ',', '.'), '0'), ',');
    }
}
