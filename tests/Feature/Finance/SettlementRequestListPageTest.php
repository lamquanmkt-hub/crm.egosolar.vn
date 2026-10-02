<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\SettlementAdvanceOption;
use App\DTOs\Finance\SettlementKpiCards;
use App\DTOs\Finance\SettlementRequestRow;
use App\View\Presenters\Finance\SettlementRequestListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard trang `settlement_requests/index` sau đợt 2026-09-30.
 *
 * ⚠️ Trang vẫn CỐ Ý nạp `css/ego-payment-requests-enterprise.css` + `.js` — hệ dùng chung cho 6 view
 * của 3 module; gỡ nó là việc của cả module.
 */
final class SettlementRequestListPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/settlement_requests/index.blade.php';

    private const CONTROLLER_KEYS = [
        'items', 'stats', 'advances', 'creators', 'canViewAll', 'labels',
        'canApproveManagement', 'canApproveAccounting',
    ];

    private const LOOP_AND_BLADE_VARIABLES = [
        'row', 'option', 'file', 'u', 'k', 'v', 'error', 'loop', 'errors', 'slot', 'attributes', 'component',
    ];

    private const ALPINE_MAGICS = ['el', 'dispatch', 'event', 'nextTick', 'refs', 'data', 'store'];

    public function test_view_chi_in_khong_tu_tinh(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $view, 'view còn khối @php');
        $this->assertStringNotContainsString('Carbon', $view, 'view còn tự parse ngày');
        $this->assertStringNotContainsString('number_format', $view, 'view còn tự định dạng số');
        $this->assertStringNotContainsString('auth()', $view, 'view còn tự hỏi người đăng nhập');
        $this->assertStringNotContainsString('request(', $view, 'view còn tự đọc request');
        $this->assertStringNotContainsString('old(', $view, 'view còn tự đọc old input');

        $this->assertStringNotContainsString('<style', $view, 'view dùng lại khối style nội tuyến');
        $this->assertStringNotContainsString('data-bs-', $view, 'view dùng lại Bootstrap JS');
        $this->assertStringNotContainsString('ego-hu-', $view, 'view dùng lại hệ class ego-hu-* cũ');
        $this->assertSame(1, substr_count($view, '<script'), 'chỉ được còn thẻ nạp JS dùng chung');

        // 🚨 Bốn lớp CHẾT của bản cũ: chúng không có CSS ở bất kỳ đâu trong repo, nên thẻ bảng mất
        // hẳn vỏ. Hai trang anh em cùng module dùng bộ tên đúng là `ego-pr-data-*`.
        foreach (['ego-pr-table-card', 'ego-pr-table-head', 'ego-pr-count-badge', 'ego-pr-table-wrap'] as $chet) {
            $this->assertStringNotContainsString($chet, $view, "lớp {$chet} không có CSS ở đâu cả");
        }
        $this->assertStringContainsString('ego-pr-data-card', $view);
        $this->assertStringContainsString('ego-pr-data-count', $view);
        $this->assertStringContainsString('ego-pr-table-scroll', $view);

        $this->assertKhongLamDungImportant($view, 'settlement_requests/index');

        $data = (new SettlementRequestListPresenter)->viewData(
            items: [], advances: [], labels: [], currentUserId: null, canViewAll: false,
            canApproveManagement: false, canApproveAccounting: false,
            stats: [], pageCount: 0, totalCount: 0, rawFilters: [], oldInput: [],
        );

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $view, $m);
        $provided = array_merge(
            self::CONTROLLER_KEYS, self::LOOP_AND_BLADE_VARIABLES, self::ALPINE_MAGICS, array_keys($data)
        );

        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $provided)),
            'view dùng biến mà controller/presenter không cấp');
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        $canh = [
            'row' => SettlementRequestRow::class,
            'option' => SettlementAdvanceOption::class,
            'kpi' => SettlementKpiCards::class,
        ];

        foreach ($canh as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $view, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien ({$dto})");
        }
    }

    public function test_hop_thoai_dung_ten_ma_alpine_dang_nghe(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertSame(2, substr_count($view, "\$dispatch('open-modal', 'createSettlement')"),
            'nút mở + nhánh mở lại khi có lỗi xác thực');
        $this->assertStringContainsString("\$dispatch('close-modal', 'createSettlement')", $view);
        $this->assertStringContainsString('<x-ui.modal name="createSettlement"', $view);
        $this->assertStringContainsString("\$el.classList.add('ego-pr-ui-ready')", $view);
        // Ô tóm tắt dựa vào x-cloak; thiếu nó thì nó hiện một nhịp lúc mở hộp thoại.
        $this->assertStringContainsString('x-cloak', $view);
    }

    public function test_trang_that_in_dung_gia_tri(): void
    {
        Carbon::setTestNow('2026-09-30 09:15:00');

        $kt = $this->userWithRole('accounting', ['id' => 850001, 'name' => 'KT Guard'], ['page.finance']);

        DB::table('advance_requests')->insert([[
            'id' => 850010, 'code' => 'AR-GUARD', 'company' => 'EGO', 'recipient_name' => 'Nguyễn Nhận',
            'amount' => 12500000, 'reason' => 'Chi phí', 'settlement_due_date' => '2026-10-15',
            'status' => 'accounting_approved', 'created_by' => 850001,
            'accounting_approved_at' => '2026-09-15 08:00:00',
            'created_at' => '2026-09-10 08:00:00', 'updated_at' => '2026-09-10 08:00:00',
        ]]);

        DB::table('settlement_requests')->insert([[
            'id' => 850020, 'code' => 'ST-GUARD', 'advance_request_id' => null, 'created_by' => 850001,
            'company' => 'EGO', 'recipient_name' => 'Nguyễn Nhận', 'advance_amount' => 12500000,
            'actual_amount' => 11000000, 'refund_amount' => 1500000, 'difference_amount' => -1500000,
            'settlement_type' => 'refund', 'reason' => 'Quyết toán', 'status' => 'draft',
            'attachments' => json_encode(['settlements/x.pdf']),
            'created_at' => '2026-09-20 08:00:00', 'updated_at' => '2026-09-20 08:00:00',
        ]]);

        $html = (string) $this->actingAs($kt)->get('/settlement-requests')->assertOk()->getContent();

        $this->assertStringContainsString('ST-GUARD', $html);
        $this->assertStringContainsString('Hoàn lại 1.500.000 đ', $html);
        $this->assertStringContainsString('12.500.000 đ', $html, 'tiền kiểu Việt');
        $this->assertStringContainsString('CT 1', $html, 'chứng từ đánh số từ 1');
        // Phiếu tạm ứng chưa hoàn ứng phải có trong ô chọn của hộp thoại.
        $this->assertStringContainsString('AR-GUARD — Nguyễn Nhận — 12.500.000 đ', $html);
        $this->assertStringContainsString('scope="col"', $html);
    }
}
