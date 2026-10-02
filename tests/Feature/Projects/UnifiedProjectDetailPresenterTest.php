<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\DTOs\Projects\ProjectRailItem;
use App\Models\Projects\Site;
use App\View\Presenters\Projects\UnifiedProjectDetailPresenter;
use Tests\TestCase;

/** {@see UnifiedProjectDetailPresenter} thay 4 khối `@php` (100 dòng) của projects-unified/show (2026-09-08). Không cần DB. */
final class UnifiedProjectDetailPresenterTest extends TestCase
{
    private UnifiedProjectDetailPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new UnifiedProjectDetailPresenter;
    }

    public function test_thanh_quy_trinh_theo_trang_thai_tung_buoc(): void
    {
        $workflow = ['progress' => 39, 'steps' => [
            'survey' => ['status' => 'approved', 'document_state' => ['file_missing' => ['a', 'b', 'c', 'd']], 'overdue_days' => 0, 'row' => (object) ['approved_at' => '2026-08-20 10:00:00']],
            'contract' => ['status' => 'revision', 'document_state' => ['missing' => ['x']], 'overdue_days' => 0],
            'construction' => ['status' => 'in_progress', 'document_state' => ['missing' => []], 'overdue_days' => 5],
            'acceptance' => ['status' => 'submitted', 'document_state' => [], 'overdue_days' => 0],
        ]];
        $proposals = collect([(object) ['id' => 3, 'status' => 'NEEDS_REVISION'], (object) ['id' => 2, 'status' => 'EXPORTED'], (object) ['id' => 1, 'status' => 'SUBMITTED']]);

        $data = $this->presenter->viewData(new Site, ['progress' => 10], $workflow, ['calculated' => 20], $proposals, collect(), true, 'contract');

        $this->assertSame(['contract', 39, 'Hợp đồng & Pháp lý'], [$data['uiStep'], $data['deploymentProgress'], $data['railTitle']], 'tiến độ ưu tiên workflow');
        $rail = $data['rail'];
        $this->assertContainsOnlyInstancesOf(ProjectRailItem::class, $rail);
        $this->assertSame(
            [['overview', '•', 'working', 'Tiến độ 39%', false], ['survey', '✓', 'complete', 'Đã duyệt · còn thiếu 4 hồ sơ', true], ['contract', '!', 'danger', 'Cần bổ sung · thiếu 1 hồ sơ', false],
                ['materials', '•', 'working', '3 đề xuất · cần sửa', false], ['construction', '!', 'danger', 'Quá hạn 5 ngày', false], ['acceptance', '•', 'pending', 'Đang chờ duyệt', false],
                ['finance', '₫', 'working', 'Chỉ Admin · Thu chi & giá vốn', false]],
            array_map(fn (ProjectRailItem $item) => [$item->code, $item->icon, $item->tone, $item->status, $item->done], $rail),
        );
        $this->assertSame(['Tổng quan', 'Khảo sát & PA', 'HĐ & Pháp lý', 'Đề xuất vật tư', 'Thi công', 'Nghiệm thu', 'Tài chính công trình'], array_map(fn (ProjectRailItem $i) => $i->label, $rail));
    }

    public function test_cac_nhanh_con_lai_va_khong_thay_tai_chinh(): void
    {
        $workflow = ['steps' => [
            'survey' => ['status' => 'approved', 'document_state' => ['missing' => []], 'overdue_days' => 0, 'row' => (object) ['approved_at' => null, 'updated_at' => '2026-08-21 09:00:00']],
            'contract' => ['status' => 'assigned', 'document_state' => ['missing' => ['x', 'y']], 'overdue_days' => 0],
            'construction' => ['status' => 'in_progress', 'document_state' => ['missing' => []], 'overdue_days' => 0],
            'acceptance' => ['status' => 'not_assigned', 'document_state' => [], 'overdue_days' => 0],
        ]];

        $data = $this->presenter->viewData(new Site, [], $workflow, [], collect(), collect(), false, 'finance');

        $this->assertSame('overview', $data['uiStep'], 'không thấy tài chính → step finance rơi về tổng quan');
        $this->assertSame(['Tổng quan', 'Tiến độ 0%'], [$data['railTitle'], $data['rail'][0]->status]);
        $statuses = array_map(fn (ProjectRailItem $i) => $i->status, $data['rail']);
        $this->assertSame(['Tiến độ 0%', 'Đã duyệt · 21/08/2026', 'Đang làm · còn thiếu 2 hồ sơ', 'Chưa có đề xuất', 'Đang làm · đủ hồ sơ', 'Chưa phân công'], $statuses, 'duyệt xong đủ hồ sơ in ngày (rơi về updated_at); không có mục tài chính');
        $this->assertSame(['working', 'complete', 'warning', 'muted', 'working', 'muted'], array_map(fn (ProjectRailItem $i) => $i->tone, $data['rail']));
        $this->assertSame(['Chưa cập nhật', 'Đang thực hiện'], [$data['customerName'], $data['projectStatus']]);
    }

    public function test_de_xuat_vat_tu_nhan_tong_so_luong_va_dem_theo_trang_thai(): void
    {
        $proposals = collect([
            (object) ['id' => 3, 'status' => 'NEEDS_REVISION'],
            (object) ['id' => 2, 'status' => 'EXPORTED'],
            (object) ['id' => 1, 'status' => 'ADMIN_APPROVED'],
        ]);
        $items = collect([
            3 => collect([(object) ['requested_qty' => '7.25', 'material_request_id' => null]]),
            2 => collect([(object) ['requested_qty' => '100.00', 'material_request_id' => 998901], (object) ['requested_qty' => '0.50', 'material_request_id' => null]]),
        ]);

        $data = $this->presenter->viewData(new Site, [], [], [], $proposals, $items, true, null);

        $this->assertSame([1, 1, 1], [$data['waitingCount'], $data['warehouseCount'], $data['exportedCount']]);
        [$revision, $exported, $approved] = $data['proposalRows'];
        $this->assertSame(['NEEDS_REVISION', 'Cần chỉnh sửa', 'revision', 1, '7,25', null], [$revision->status, $revision->statusLabel, $revision->tone, $revision->itemCount, $revision->totalQuantityText, $revision->linkedMaterialRequestId]);
        $this->assertSame(['Đã xuất kho', 'complete', 2, '100,5', 998901], [$exported->statusLabel, $exported->tone, $exported->itemCount, $exported->totalQuantityText, $exported->linkedMaterialRequestId]);
        $this->assertSame(['100', '0,5'], $exported->items->map(fn ($line) => $line->quantityText)->all(), 'bỏ số 0 thừa: 100,00 → 100; 0,50 → 0,5');
        $this->assertSame(['Đã duyệt · Chờ Kho', 'warehouse', 0, '0'], [$approved->statusLabel, $approved->tone, $approved->itemCount, $approved->totalQuantityText], 'không có dòng → tổng 0');
    }
}
