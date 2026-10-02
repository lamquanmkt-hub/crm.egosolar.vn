<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\AdvanceKpiCards;
use App\DTOs\Finance\AdvanceRequestRow;
use App\DTOs\Finance\AdvanceStage;
use App\View\Presenters\Finance\AdvanceRequestListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guard trang `advance_requests/index` sau đợt 2026-09-30: bỏ `@php`, bỏ `<style>`, bỏ JS thuần.
 *
 * ⚠️ Trang vẫn CỐ Ý nạp `css/ego-payment-requests-enterprise.css` + `.js`: đó là hệ dùng chung cho
 * 6 view của 3 module (payment_requests, advance_requests, settlement_requests), gỡ nó là việc của
 * cả module chứ không phải của một trang.
 */
final class AdvanceRequestListPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/advance_requests/index.blade.php';

    /** Khoá view() do controller cấp (không qua presenter). */
    private const CONTROLLER_KEYS = [
        'items', 'stats', 'labels', 'creators', 'canViewAll',
        'canApproveManagement', 'canApproveAccounting',
    ];

    private const LOOP_AND_BLADE_VARIABLES = [
        'row', 'u', 'k', 'v', 'e', 'loop', 'errors', 'slot', 'attributes', 'component',
    ];

    /** Magic của Alpine — JS trong thuộc tính `x-*`, không phải biến Blade. */
    private const ALPINE_MAGICS = ['el', 'dispatch', 'event', 'nextTick', 'refs', 'data', 'store'];

    public function test_view_chi_in_khong_tu_tinh(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertStringNotContainsString('@php', $view, 'view còn khối @php');
        $this->assertStringNotContainsString('Carbon', $view, 'view còn tự parse ngày');
        $this->assertStringNotContainsString('number_format', $view, 'view còn tự định dạng số');
        $this->assertStringNotContainsString('auth()', $view, 'view còn tự hỏi người đăng nhập');
        $this->assertStringNotContainsString('request(', $view, 'view còn tự đọc request');
        $this->assertStringNotContainsString('Str::limit', $view, 'view còn tự cắt chuỗi');

        // Không CSS riêng, không JS thuần, không Bootstrap JS.
        $this->assertStringNotContainsString('<style', $view, 'view dùng lại khối <style>');
        $this->assertStringNotContainsString('data-bs-', $view, 'view dùng lại Bootstrap JS');
        $this->assertStringNotContainsString('onclick=', $view, 'view dùng lại handler nội tuyến');
        $this->assertStringNotContainsString('ego-fin-', $view, 'view dùng lại hệ class ego-fin-* cũ');
        // `<script>` duy nhất được phép là thẻ nạp tệp JS DÙNG CHUNG của module.
        $this->assertSame(1, substr_count($view, '<script'), 'view chỉ được còn thẻ nạp JS dùng chung');
        $this->assertStringContainsString('ego-payment-requests-enterprise.js', $view);

        $this->assertKhongLamDungImportant($view, 'advance_requests/index');

        $data = (new AdvanceRequestListPresenter)->viewData(
            items: [], labels: [], currentUserId: null,
            canApproveManagement: false, canApproveAccounting: false,
            today: Carbon::parse('2026-09-30')->startOfDay(),
            stats: [], pageCount: 0, totalCount: 0, rawFilters: [], oldInput: [], currentUserName: '',
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

        foreach (['row' => AdvanceRequestRow::class, 'kpi' => AdvanceKpiCards::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $view, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien ({$dto})");
        }

        // `$row->stage->x` là thuộc tính của AdvanceStage.
        preg_match_all('/\$row->stage->([a-zA-Z0-9]+)/', $view, $hit);
        $props = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass(AdvanceStage::class))->getProperties()
        );
        $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)));
    }

    public function test_hop_thoai_va_hieu_ung_dung_ten_ma_alpine_dang_nghe(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        // Sai tên thì bấm không ra gì và cũng không báo lỗi — chốt bằng đếm.
        $this->assertSame(2, substr_count($view, "\$dispatch('open-modal', 'createAdvance')"),
            'nút mở + nhánh mở lại khi có lỗi xác thực');
        $this->assertStringContainsString("\$dispatch('close-modal', 'createAdvance')", $view);
        $this->assertStringContainsString('<x-ui.modal name="createAdvance"', $view);
        $this->assertStringContainsString("\$el.classList.add('ego-pr-ui-ready')", $view);
    }

    public function test_trang_that_in_dung_tien_do_va_ket_qua_quyet_toan(): void
    {
        Carbon::setTestNow('2026-09-30 09:15:00');

        $nv = $this->userWithRole('accounting', ['id' => 870001, 'name' => 'KT Guard'], ['page.finance']);

        $chung = [
            'company' => 'EGO', 'recipient_name' => 'Nguyễn Nhận', 'amount' => 12500000,
            'reason' => 'Chi phí công tác', 'settlement_due_date' => '2026-09-25',
            'created_by' => 870001, 'created_at' => '2026-09-10 08:00:00', 'updated_at' => '2026-09-10 08:00:00',
        ];

        DB::table('advance_requests')->insert([
            array_merge($chung, ['id' => 870001, 'code' => 'AR-GUARD-1', 'status' => 'accounting_approved']),
            array_merge($chung, ['id' => 870002, 'code' => 'AR-GUARD-2', 'status' => 'draft']),
        ]);

        $html = (string) $this->actingAs($nv)->get('/advance-requests')->assertOk()->getContent();

        $this->assertStringContainsString('AR-GUARD-1', $html);
        $this->assertStringContainsString('Quá hạn hoàn ứng 5 ngày', $html, 'hạn 25/09 so với 30/09');
        $this->assertStringContainsString('12.500.000 đ', $html, 'tiền kiểu Việt');
        $this->assertStringContainsString('Chưa lập hoàn ứng', $html);
        $this->assertStringContainsString('Nháp', $html);

        // Cột hạn hoàn ứng: màu đỏ nay nằm trên <span>, không phải trên <td> kèm `!important`.
        $this->assertStringContainsString('<span class="tw:[font-weight:850] tw:text-[#b42318]">25/09/2026</span>', $html);
    }

    public function test_o_chi_so_khong_con_in_ra_chu_else(): void
    {
        Carbon::setTestNow('2026-09-30 09:15:00');

        $nv = $this->userWithRole('accounting', ['id' => 870003, 'name' => 'KT Guard 2'], ['page.finance']);

        DB::table('advance_requests')->insert([[
            'id' => 870010, 'code' => 'AR-GUARD-3', 'company' => 'EGO', 'recipient_name' => 'Nguyễn Nhận',
            'amount' => 1000000, 'reason' => 'Chi phí', 'settlement_due_date' => '2026-09-01',
            'status' => 'accounting_approved', 'created_by' => 870003,
            'created_at' => '2026-09-10 08:00:00', 'updated_at' => '2026-09-10 08:00:00',
        ]]);

        $html = (string) $this->actingAs($nv)->get('/advance-requests')->assertOk()->getContent();

        // 🚨 Bản cũ viết `…quá hạn@else đã chi tiền…` — `@else` DÍNH LIỀN sau chữ nên Blade KHÔNG
        // biên dịch (regex `\B@` trong BladeCompiler::compileStatements cần ký tự trước không phải
        // chữ). Hệ quả trên production: ô KPI in nguyên văn "@else đã chi tiền, chờ nhân sự quyết toán".
        $this->assertStringNotContainsString('@else', $html, 'directive Blade lọt ra HTML');
        $this->assertStringContainsString('1 phiếu đã quá hạn', $html);
    }
}
