<?php

declare(strict_types=1);

namespace App\View\Presenters\Projects;

use App\DTOs\Projects\WorkflowDocumentRequirementRow;
use App\Models\Projects\Site;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị giá trị cho partial `projects-unified.partials.workflow-word` (bước workflow đang mở) và
 * `workflow-document-settings` (hộp cài đặt hồ sơ).
 *
 * Trước 2026-09-08 partial tự tính trong 3 khối `@php` (60 dòng): 25 biến `wf*` bóc từ
 * ProjectWorkflowV2Service::present() (có `auth()->id()` và `request('step')`), tiêu đề bước, nhãn
 * trạng thái duyệt, người trả/nhận hồ sơ khi bị trả, id hộp thoại; và trong vòng lặp hồ sơ: số file
 * đã tải, còn thiếu, được tải thêm. Tên khoá trả về giữ tên biến cũ của partial.
 */
final class ProjectWorkflowStepPresenter
{
    /** code => [eyebrow, tiêu đề, mô tả] */
    private const STEP_META = [
        'survey' => ['CÔNG TRÌNH - BƯỚC 1', 'KHẢO SÁT & PHƯƠNG ÁN', 'Chọn nhân sự thực hiện, lưu hồ sơ khảo sát/phương án và gửi duyệt.'],
        'contract' => ['CÔNG TRÌNH - BƯỚC 2', 'HỢP ĐỒNG & PHÁP LÝ', 'Quản lý nhân sự phụ trách, hợp đồng, phụ lục và hồ sơ pháp lý trong cùng một bước.'],
        'construction' => ['CÔNG TRÌNH - BƯỚC 4', 'THI CÔNG', 'Quản lý nhân sự thi công, kế hoạch, tiến độ và báo cáo thực tế tại công trình.'],
        'acceptance' => ['CÔNG TRÌNH - BƯỚC 5', 'NGHIỆM THU', 'Hoàn thiện hồ sơ nghiệm thu, duyệt và chuyển công trình sang Bảo trì/Bảo hành.'],
    ];

    /**
     * @param  array<string, mixed>  $workflow  ProjectWorkflowV2Service::present()
     * @param  string|null  $requestedStep  `?step=` — chỉ dùng khi present() không có `selected_code`
     * @return array<string, mixed>
     */
    public function viewData(Site $site, array $workflow, ?User $user, ?string $requestedStep): array
    {
        $selected = $workflow['selected'] ?? null;
        $row = $selected['row'] ?? null;
        $assignments = $selected['assignments'] ?? collect();
        $documents = $selected['documents'] ?? collect();
        $documentState = $selected['document_state'] ?? ['items' => [], 'missing' => [], 'complete' => false];
        $approvals = $selected['approvals'] ?? collect();
        $permissions = $workflow['permissions'] ?? [];
        $stepCode = (string) ($workflow['selected_code'] ?? ($requestedStep ?? 'survey'));
        $status = (string) ($selected['status'] ?? 'not_assigned');
        $userId = (int) ($user?->id ?? 0);

        $stepData = [];
        if ($row && ! empty($row->data)) {
            $decoded = json_decode((string) $row->data, true);
            $stepData = is_array($decoded) ? $decoded : [];
        }

        $revisionEvent = collect($workflow['events'] ?? [])->first(fn ($event) => (string) ($event->action ?? '') === 'step_revision_requested');
        $revisionPayload = ! empty($revisionEvent?->payload) ? json_decode((string) $revisionEvent->payload, true) : [];

        return [
            'wfSelected' => $selected,
            'wfDefinition' => $selected['definition'] ?? [],
            'wfRow' => $row,
            'wfAssignments' => $assignments,
            'wfMissingFiles' => (array) ($documentState['file_missing'] ?? $documentState['missing'] ?? []),
            'wfMissingInformation' => (array) ($documentState['information_missing'] ?? []),
            'wfFilesComplete' => (bool) ($documentState['files_complete'] ?? $documentState['complete'] ?? false),
            'wfPermissions' => $permissions,
            'wfApprovalPermissions' => $workflow['approval_permissions'] ?? [],
            'wfIsAdmin' => ! empty($permissions['is_admin']),
            'wfStepCode' => $stepCode,
            'wfStatus' => $status,
            'wfStepData' => $stepData,
            'wfMyAssignment' => $assignments->first(fn ($assignment) => (int) $assignment->user_id === $userId),
            'wfCurrentUserName' => (string) ($user?->name ?? ''),
            'meta' => self::STEP_META[$stepCode] ?? self::STEP_META['survey'],
            'primary' => $assignments->first(fn ($assignment) => (string) $assignment->assignment_role === 'primary'),
            'collaborators' => $assignments->filter(fn ($assignment) => (string) $assignment->assignment_role !== 'primary'),
            'wfApprovalDisplay' => match ($status) {
                'approved' => 'Đã duyệt',
                'submitted' => 'Chờ duyệt',
                'revision' => 'Yêu cầu bổ sung',
                default => 'Chưa gửi duyệt',
            },
            'documentRows' => collect($documentState['items'] ?? [])
                ->filter(fn ($item) => empty($item['is_data_requirement']) && empty($item['is_system_requirement']))
                ->values()
                ->map(fn (array $item) => $this->documentRow($item, $documents, $permissions))
                ->all(),
            'wfDueAt' => $selected['due_at'] ?? null,
            'wfOverdueDays' => (int) ($selected['overdue_days'] ?? 0),
            'wfAssignDialogId' => 'pword-assign-dialog-'.$site->id.'-'.$stepCode,
            'wfSettingsDialogId' => 'ewd-settings-dialog-'.$site->id.'-'.$stepCode,
            'wfRevisionDialogPrefix' => 'pword-revision-dialog-'.$site->id.'-'.$stepCode.'-',
            'wfRevisionFiles' => $documents->where('document_code', 'revision_feedback'),
            'wfRevisionApproval' => $approvals->first(fn ($approval) => (string) $approval->status === 'revision'),
            'wfRevisionRecipient' => $assignments->first(fn ($assignment) => (int) $assignment->user_id === (int) ($revisionPayload['recipient_id'] ?? 0)),
            'ewdSettings' => collect($workflow['document_settings'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  Collection<int, object>  $documents
     * @param  array<string, mixed>  $permissions
     */
    private function documentRow(array $item, Collection $documents, array $permissions): WorkflowDocumentRequirementRow
    {
        $count = (int) ($item['count'] ?? 0);
        $minimum = (int) ($item['minimum'] ?? 1);
        $maximum = ! empty($item['maximum']) ? (int) $item['maximum'] : null;

        return new WorkflowDocumentRequirementRow(
            requirement: $item,
            documents: $documents->where('document_code', (string) $item['code']),
            count: $count,
            minimum: $minimum,
            maximum: $maximum,
            missing: ! empty($item['required']) ? max(0, $minimum - $count) : 0,
            canUpload: ! empty($permissions['can_upload']) && ($maximum === null || $count < $maximum),
        );
    }
}
