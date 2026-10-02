<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\DTOs\Projects\WorkflowDocumentRequirementRow;
use App\Models\Projects\Site;
use App\Models\User;
use App\View\Presenters\Projects\ProjectFinancePanelPresenter;
use App\View\Presenters\Projects\ProjectWorkflowStepPresenter;
use Tests\TestCase;

/**
 * {@see ProjectWorkflowStepPresenter} và {@see ProjectFinancePanelPresenter} thay 5 khối `@php` của hai partial
 * trang dự án hợp nhất (2026-09-08). Dữ liệu dựng trong bộ nhớ, không cần DB.
 */
final class ProjectWorkflowStepPresenterTest extends TestCase
{
    public function test_boc_buoc_dang_mo_phan_cong_va_hoi_thoai(): void
    {
        $site = (new Site)->forceFill(['id' => 77]);
        $me = (new User)->forceFill(['id' => 5, 'name' => 'Tôi']);
        $assignments = collect([
            (object) ['user_id' => 9, 'assignment_role' => 'primary', 'user_name' => 'Trưởng', 'status' => 'in_progress'],
            (object) ['user_id' => 5, 'assignment_role' => 'collaborator', 'user_name' => 'Tôi', 'status' => 'assigned'],
        ]);
        $workflow = [
            'selected_code' => 'contract',
            'selected' => [
                'status' => 'revision', 'row' => (object) ['data' => '{"step_note":"ghi chú","has_incident":1}', 'returned_reason' => 'Thiếu phụ lục'],
                'assignments' => $assignments, 'documents' => collect(), 'approvals' => collect([(object) ['status' => 'approved'], (object) ['status' => 'revision', 'reviewer_name' => 'Admin']]),
                'document_state' => ['items' => [], 'file_missing' => ['Hợp đồng đã ký'], 'information_missing' => ['Ngày ký'], 'files_complete' => false],
                'due_at' => null, 'overdue_days' => 3, 'definition' => ['approval_tracks' => ['admin' => []]],
            ],
            'permissions' => ['is_admin' => false, 'can_upload' => true],
            'approval_permissions' => ['admin' => false],
            'events' => [(object) ['action' => 'step_revision_requested', 'payload' => '{"recipient_id":5}'], (object) ['action' => 'other']],
            'document_settings' => [['code' => 'x']],
        ];

        $data = (new ProjectWorkflowStepPresenter)->viewData($site, $workflow, $me, 'survey');

        $this->assertSame(['contract', 'revision', 'Yêu cầu bổ sung', false, 3], [$data['wfStepCode'], $data['wfStatus'], $data['wfApprovalDisplay'], $data['wfIsAdmin'], $data['wfOverdueDays']], 'selected_code thắng ?step');
        $this->assertSame('HỢP ĐỒNG & PHÁP LÝ', $data['meta'][1]);
        $this->assertSame(['ghi chú', 1], [$data['wfStepData']['step_note'], $data['wfStepData']['has_incident']]);
        $this->assertSame([5, 'Trưởng', ['Tôi']], [$data['wfMyAssignment']->user_id, $data['primary']->user_name, $data['collaborators']->pluck('user_name')->all()]);
        $this->assertSame(['Hợp đồng đã ký'], $data['wfMissingFiles'], 'ưu tiên file_missing');
        $this->assertSame([['Ngày ký'], false], [$data['wfMissingInformation'], $data['wfFilesComplete']]);
        $this->assertSame('revision', $data['wfRevisionApproval']->status);
        $this->assertSame('Tôi', $data['wfRevisionRecipient']->user_name, 'người nhận lấy từ payload sự kiện trả hồ sơ');
        $this->assertSame(['pword-assign-dialog-77-contract', 'ewd-settings-dialog-77-contract', 'pword-revision-dialog-77-contract-'], [$data['wfAssignDialogId'], $data['wfSettingsDialogId'], $data['wfRevisionDialogPrefix']]);
        $this->assertSame('Tôi', $data['wfCurrentUserName']);
        $this->assertSame([['code' => 'x']], $data['ewdSettings']->all());
        $this->assertSame([], $data['documentRows']);
    }

    public function test_dong_ho_so_dem_file_con_thieu_va_quyen_tai_them(): void
    {
        $documents = collect([(object) ['document_code' => 'signed_contract', 'version' => 1], (object) ['document_code' => 'signed_contract', 'version' => 2], (object) ['document_code' => 'other', 'version' => 1]]);
        $workflow = ['selected' => [
            'documents' => $documents, 'assignments' => collect(), 'approvals' => collect(), 'status' => 'assigned',
            'document_state' => ['items' => [
                ['code' => 'signed_contract', 'label' => 'Hợp đồng đã ký', 'required' => true, 'count' => 2, 'minimum' => 1, 'maximum' => 2],
                ['code' => 'appendix', 'label' => 'Phụ lục', 'required' => true, 'count' => 0, 'minimum' => 1],
                ['code' => 'optional', 'label' => 'Khác', 'required' => false, 'count' => 0],
                ['code' => 'data:sign_date', 'is_data_requirement' => true],
                ['code' => 'system:x', 'is_system_requirement' => true],
            ], 'missing' => ['Phụ lục'], 'complete' => false],
        ], 'permissions' => ['can_upload' => true]];

        $data = (new ProjectWorkflowStepPresenter)->viewData((new Site)->forceFill(['id' => 1]), $workflow, null, null);
        $rows = $data['documentRows'];

        $this->assertSame(['survey', 'Chưa gửi duyệt', null, ''], [$data['wfStepCode'], $data['wfApprovalDisplay'], $data['wfMyAssignment'], $data['wfCurrentUserName']], 'không selected_code, không ?step → survey; không user');
        $this->assertSame(['Phụ lục'], $data['wfMissingFiles'], 'rơi về missing khi không có file_missing');
        $this->assertContainsOnlyInstancesOf(WorkflowDocumentRequirementRow::class, $rows);
        $this->assertCount(3, $rows, 'bỏ mục dữ liệu/hệ thống');
        $this->assertSame([2, 1, 2, 0, false, 2], [$rows[0]->count, $rows[0]->minimum, $rows[0]->maximum, $rows[0]->missing, $rows[0]->canUpload, $rows[0]->documents->count()], 'đã tối đa → không tải thêm');
        $this->assertSame([0, 1, null, 1, true, 0], [$rows[1]->count, $rows[1]->minimum, $rows[1]->maximum, $rows[1]->missing, $rows[1]->canUpload, $rows[1]->documents->count()]);
        $this->assertSame([0, 0], [$rows[2]->missing, $rows[2]->count], 'tuỳ chọn không tính thiếu');
    }

    public function test_bang_tai_chinh_lai_lo_va_nguon_thanh_toan(): void
    {
        $presenter = new ProjectFinancePanelPresenter;
        $data = $presenter->viewData(['profit' => -5, 'profit_margin' => -1.5]);
        $this->assertSame([-1.5, false], [$data['grossMargin'], $data['isProfitable']]);
        $this->assertSame('Chuyển khoản', $data['paymentMethodLabels']['bank_transfer']);

        $data = $presenter->viewData(null);
        $this->assertSame([[], 0.0, true], [$data['financeData'], $data['grossMargin'], $data['isProfitable']], 'không có tài chính → rỗng, coi như không lỗ');
        $this->assertSame(['receipt', 'record', 'record'], [
            ProjectFinancePanelPresenter::paymentSource((object) ['finance_source' => 'legacy_receipt']),
            ProjectFinancePanelPresenter::paymentSource((object) ['finance_source' => 'manual']),
            ProjectFinancePanelPresenter::paymentSource((object) []),
        ]);
    }
}
