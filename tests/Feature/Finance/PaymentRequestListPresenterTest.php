<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\PaymentRequestRow;
use App\View\Presenters\Finance\PaymentRequestListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * {@see PaymentRequestListPresenter} thay 4 khối `@php` của view `payment_requests.index` (2026-09-08):
 * quyền thao tác từng phiếu theo trạng thái/chủ phiếu/vai, hạn thanh toán, nội dung, tham số xuất.
 */
final class PaymentRequestListPresenterTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/payment_requests/index.blade.php';

    /** Khoá view() của PaymentRequestController@index (không do presenter cấp). */
    private const CONTROLLER_KEYS = ['items', 'companyOptions', 'statusLabels', 'creatorOptions', 'totalPaid', 'totalRequests', 'pendingCount', 'approvedCount', 'totalAmount', 'canViewAll', 'canAdminApprove', 'canAccountingApprove', 'canHrEditSubmitted', 'canBulkApprove', 'selectedDatePreset', 'effectiveDateFrom', 'effectiveDateTo', 'paginationQuery'];

    private const LOOP_AND_BLADE_VARIABLES = ['row', 'c', 'st', 'u', 'key', 'value', 'size', 'errors', 'error', 'e', 'loop', 'slot', 'attributes', 'component'];

    private const LABELS = ['draft' => 'Nháp', 'submitted' => 'Đã gửi duyệt', 'admin_approved' => 'QLTC đã duyệt', 'accounting_approved' => 'Kế toán đã chi'];

    public function test_quyen_thao_tac_theo_trang_thai_chu_phieu_va_vai(): void
    {
        $mine = fn (string $status, array $extra = []) => (object) (['created_by' => 5, 'status' => $status, 'code' => 'DN-'.$status, 'payment_due_date' => null, 'payment_content' => null, 'reason' => null] + $extra);
        $theirs = fn (string $status) => (object) ['created_by' => 9, 'status' => $status, 'code' => 'DN-x-'.$status, 'payment_due_date' => null, 'payment_content' => null, 'reason' => null];
        $items = $this->paginate([$mine('draft'), $mine('submitted'), $theirs('submitted'), $theirs('admin_approved'), $mine('accounting_approved'), $theirs('accounting_rejected'), (object) ['created_by' => 5, 'code' => 'DN-nostatus', 'status' => null, 'payment_due_date' => null, 'payment_content' => null, 'reason' => null]]);
        $presenter = new PaymentRequestListPresenter;

        // Chủ phiếu (sales): sửa/gửi/xoá nháp + phiếu bị trả của mình, không duyệt, không chọn hàng loạt
        $rows = $presenter->viewData($items, self::LABELS, 5, false, false, false, [])['rows'];
        $this->assertContainsOnlyInstancesOf(PaymentRequestRow::class, $rows);
        $flags = fn (PaymentRequestRow $r) => [$r->canEdit, $r->canSubmit, $r->canDelete, $r->bulkSelectable, $r->canDownloadPdf];
        $this->assertSame([true, true, true, false, false], $flags($rows[0]), 'nháp của mình');
        $this->assertSame([false, false, false, false, false], $flags($rows[1]), 'đã gửi của mình: sales không sửa');
        $this->assertSame([false, false, false, false, true], $flags($rows[4]), 'đã chi: chỉ tải PDF');
        $this->assertSame(['draft', 'draft', 'Nháp', true], [$rows[6]->status, $rows[6]->statusSlug, $rows[6]->statusLabel, $rows[6]->canEdit], 'không trạng thái → nháp');
        $this->assertSame('accounting-approved', $rows[4]->statusSlug, 'slug gạch nối cho lớp CSS');
        $this->assertSame([true, true], [$rows[1]->canAdminAction, $rows[3]->canAccountingAction], 'nút duyệt/chi theo trạng thái, view còn chặn theo vai');

        // HR chủ phiếu: sửa được phiếu đã gửi của mình, không phải của người khác
        $rows = $presenter->viewData($items, self::LABELS, 5, false, false, true, [])['rows'];
        $this->assertSame([true, false, false], [$rows[1]->canEdit, $rows[1]->canSubmit, $rows[2]->canEdit]);

        // Admin (không phải chủ): chọn hàng loạt phiếu đã gửi; kế toán: chọn phiếu QLTC đã duyệt và sửa mọi phiếu đã gửi
        $rows = $presenter->viewData($items, self::LABELS, 1, true, false, false, [])['rows'];
        $this->assertSame([true, true, false, false], [$rows[1]->bulkSelectable, $rows[2]->bulkSelectable, $rows[3]->bulkSelectable, $rows[2]->canEdit]);
        $rows = $presenter->viewData($items, self::LABELS, 1, false, true, false, [])['rows'];
        $this->assertSame([false, true, true, 'accounting_rejected'], [$rows[1]->bulkSelectable, $rows[3]->bulkSelectable, $rows[2]->canEdit, $rows[5]->statusLabel], 'nhãn thiếu → mã trạng thái');
    }

    public function test_han_thanh_toan_noi_dung_va_tham_so_xuat(): void
    {
        $items = $this->paginate([
            (object) ['created_by' => 1, 'status' => 'submitted', 'code' => 'A', 'payment_due_date' => '2020-01-05', 'payment_content' => '<b>Mua</b> <i>giấy</i> ', 'reason' => 'x'],
            (object) ['created_by' => 1, 'status' => 'accounting_approved', 'code' => 'B', 'payment_due_date' => '2020-01-05', 'payment_content' => '', 'reason' => 'Lý do'],
            (object) ['created_by' => 1, 'status' => 'draft', 'code' => 'C', 'payment_due_date' => '2999-12-31', 'payment_content' => null, 'reason' => null],
            (object) ['created_by' => 1, 'status' => 'draft', 'code' => 'D', 'payment_due_date' => null, 'payment_content' => null, 'reason' => null],
        ]);
        $data = (new PaymentRequestListPresenter)->viewData($items, self::LABELS, null, false, false, false, ['q' => 'DN', 'per_page' => 50, 'page' => 2, 'status' => '']);
        $rows = $data['rows'];

        $this->assertSame([['05/01/2020', true, 'Mua giấy'], ['05/01/2020', false, 'Lý do'], ['31/12/2999', false, '-'], ['-', false, '-']],
            array_map(fn (PaymentRequestRow $r) => [$r->dueText, $r->isOverdue, $r->contentText], $rows), 'quá hạn trừ phiếu đã chốt; nội dung bỏ thẻ, rơi về lý do rồi gạch');
        $this->assertSame(['A' => '05/01/2020', 'B' => '05/01/2020', 'C' => '31/12/2999', 'D' => '-'], $data['egoPaymentDueMap']);
        $this->assertSame(['q' => 'DN', 'status' => ''], $data['exportParams'], 'bỏ per_page/page');
        $this->assertSame([[], [], []], array_values((new PaymentRequestListPresenter)->viewData(null, [], null, false, false, false, [])), 'không paginator → rỗng');
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);
        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $provided = array_merge(self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, array_keys((new PaymentRequestListPresenter)->viewData(null, [], null, false, false, false, [])));
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)), 'biến view dùng mà presenter/controller không cấp');

        preg_match_all('/\$row->([a-zA-Z]+)/', $source, $m);
        $properties = array_map(fn (\ReflectionProperty $p) => $p->getName(), (new \ReflectionClass(PaymentRequestRow::class))->getProperties());
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $properties)), 'view đọc thuộc tính không có của $row');
    }

    public function test_trang_that_in_dung_quyen_theo_vai(): void
    {
        $admin = $this->userWithRole('admin');
        $sales = $this->userWithRole('sales');
        $now = now();
        $ids = [];
        foreach ([['PR-T-1', $sales->id, 'submitted', '2020-01-01'], ['PR-T-2', $admin->id, 'draft', null]] as [$code, $by, $status, $due]) {
            $ids[$code] = (int) DB::table('payment_requests')->insertGetId(['code' => $code, 'created_by' => $by, 'receiver_name' => 'NCC', 'amount' => 1_234_000, 'status' => $status, 'payment_due_date' => $due, 'payment_content' => '<b>Mua</b> máy', 'created_at' => $now, 'updated_at' => $now]);
        }

        $html = $this->actingAs($admin)->get('/payment-requests?date_preset=all_time')->assertOk()->getContent();
        $this->assertStringContainsString('PR-T-1', $html);
        $this->assertStringContainsString('PR-T-2', $html, 'admin thấy phiếu của mọi người');
        $this->assertStringContainsString('01/01/2020', $html);
        $this->assertStringContainsString('title="Mua máy"', $html, 'nội dung bỏ thẻ HTML');
        $this->assertStringContainsString(route('payment_requests.admin_approve', $ids['PR-T-1']), $html, 'admin có nút duyệt phiếu đã gửi');
        $this->assertStringNotContainsString(route('payment_requests.admin_approve', $ids['PR-T-2']), $html, 'phiếu nháp không có nút duyệt');

        $html = $this->actingAs($sales)->get('/payment-requests?date_preset=all_time')->assertOk()->getContent();
        $this->assertStringContainsString('PR-T-1', $html, 'sales thấy phiếu của mình');
        $this->assertStringNotContainsString('PR-T-2', $html, 'không thấy phiếu người khác');
        $this->assertStringNotContainsString(route('payment_requests.admin_approve', $ids['PR-T-1']), $html, 'sales không có nút duyệt');
    }

    /** @param  list<object>  $items */
    private function paginate(array $items): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, count($items), 20);
    }
}
