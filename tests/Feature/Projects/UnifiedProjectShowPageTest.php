<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\DTOs\Projects\MaterialProposalItemRow;
use App\DTOs\Projects\MaterialProposalRow;
use App\DTOs\Projects\ProjectRailItem;
use App\DTOs\Projects\WorkflowDocumentRequirementRow;
use App\Models\Projects\Site;
use App\View\Presenters\Projects\ProjectFinancePanelPresenter;
use App\View\Presenters\Projects\ProjectWorkflowStepPresenter;
use App\View\Presenters\Projects\UnifiedProjectDetailPresenter;
use Tests\TestCase;

/**
 * View `projects-unified/show` sau khi dời 4 khối `@php` sang UnifiedProjectDetailPresenter (2026-09-08):
 * view chỉ in — không `@php`, mọi biến do presenter/controller cấp, thuộc tính DTO là thật.
 * Partial tài chính/workflow còn `@php` riêng, không thuộc file này.
 */
final class UnifiedProjectShowPageTest extends TestCase
{
    private const VIEW = 'resources/views/projects-unified/show.blade.php';

    /** Khoá view() của UnifiedProjectController@show (không do presenter cấp). */
    private const CONTROLLER_KEYS = [
        'site', 'project', 'phases', 'leadEngineer', 'engineers', 'tasks', 'materials', 'maintenance', 'paymentTerms', 'history', 'documents',
        'documentStats', 'phaseChecklist', 'legacy', 'finance', 'exportedMaterialRequests', 'financeExpenses', 'canManage', 'canSeeFinance',
        'canProposeMaterials', 'canApproveMaterials', 'canWarehouse', 'canAdminApproveMaterials', 'canConfirmMaterialReceipt',
        'canConfirmMaterialSupervisor', 'canRecordPayment', 'materialProposals', 'materialProposalItems', 'materialKpis', 'productOptions',
        'warehouseOptions', 'paymentRecords', 'accounts', 'paymentTermPaid', 'progressEngine', 'phaseReview', 'phaseApprovalStatus',
        'canSubmitPhase', 'canApprovePhase', 'workflow',
    ];

    private const LOOP_AND_BLADE_VARIABLES = ['railItem', 'row', 'line', 'engineer', 'document', 'item', 'errors', 'error', 'e', 'loop', 'slot', 'attributes', 'component'];

    /** Biến vòng lặp/Blade của ba partial: workflow-word, admin-finance, workflow-document-settings. */
    private const PARTIAL_LOOP_VARIABLES = ['docRow', 'option', 'assignment', 'document', 'revisionFile', 'approvalType', 'track', 'extension', 'event', 'setting', 'payment', 'expense', 'dispatch', 'request', 'errors', 'error', 'e', 'loop', 'slot', 'attributes', 'component'];

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys(
            (new UnifiedProjectDetailPresenter)->viewData(new Site, [], [], [], collect(), collect(), true, null),
        ));
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)), 'biến view dùng mà presenter/controller không cấp');

        $this->assertDtoProperties($source, ['railItem' => ProjectRailItem::class, 'row' => MaterialProposalRow::class, 'line' => MaterialProposalItemRow::class]);
    }

    /** Ba partial của trang (workflow, tài chính, cài đặt hồ sơ) cũng chỉ in; biến do 3 presenter + controller cấp. */
    public function test_partial_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $site = (new Site)->forceFill(['id' => 1]);
        $provided = array_merge(
            self::CONTROLLER_KEYS,
            self::PARTIAL_LOOP_VARIABLES,
            array_keys((new UnifiedProjectDetailPresenter)->viewData($site, [], [], [], collect(), collect(), true, null)),
            array_keys((new ProjectWorkflowStepPresenter)->viewData($site, [], null, null)),
            array_keys((new ProjectFinancePanelPresenter)->viewData(null)),
        );

        foreach (['workflow-word', 'admin-finance', 'workflow-document-settings'] as $partial) {
            $source = (string) file_get_contents(base_path('resources/views/projects-unified/partials/'.$partial.'.blade.php'));
            $this->assertStringNotContainsString('@php', $source, $partial);
            preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)), "{$partial}: biến view dùng mà presenter/controller không cấp");
        }
        $this->assertDtoProperties((string) file_get_contents(base_path('resources/views/projects-unified/partials/workflow-word.blade.php')), ['docRow' => WorkflowDocumentRequirementRow::class]);
    }

    /** @param  array<string, class-string>  $variables */
    private function assertDtoProperties(string $source, array $variables): void
    {
        foreach ($variables as $variable => $dto) {
            preg_match_all('/\$'.$variable.'->([a-zA-Z]+)/', $source, $m);
            $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass($dto))->getProperties());
            $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), "view đọc thuộc tính không có của \${$variable}");
        }
    }
}
