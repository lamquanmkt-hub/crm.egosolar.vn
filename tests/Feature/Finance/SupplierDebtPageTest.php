<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\View\Presenters\Finance\SupplierDebtPagePresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Trang công nợ NCC sau khi dời 6 khối `@php` sang SupplierDebtPagePresenter (2026-09-07). */
final class SupplierDebtPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'resources/views/finance/supplier-debts/index.blade.php';

    private const URL = '/finance/supplier-debts';

    /** Biến vòng lặp/Blade và dữ liệu controller truyền thẳng. */
    private const VIEW_VARIABLES_NOT_FROM_PRESENTER = ['item', 'round', 'company', 'error', 'errors', 'loop', 'slot', 'attributes', 'component', 'debts', 'summary', 'scope', 'companyOptions',
        // Magic của Alpine — cũng bắt đầu bằng `$` nên regex quét biến Blade vớt phải.
        'el', 'tien', 'dispatch', 'event',
    ];

    public function test_trang_in_co_va_nhan_da_tinh(): void
    {
        $admin = $this->userWithRole(Role::Admin->value);
        $editor = $this->userWithRole(Role::Admin->value, ['email' => config('ego.finance_full_access_emails')[0]]);
        $now = '2026-09-01 08:00:00';
        DB::table('payment_requests')->insert(['id' => 998801, 'code' => 'DNTT-1', 'created_by' => $admin->id, 'receiver_name' => 'NCC A', 'amount' => 3000000, 'status' => 'accounting_approved', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('finance_supplier_debts')->insert([
            ['id' => 998701, 'supplier_name' => 'NCC A', 'company_name' => 'Công ty TNHH Ego Việt Nam', 'document_no' => 'HD-A', 'document_date' => '2026-09-02', 'total_amount' => 10000000, 'paid_amount' => 0, 'status' => 'pending', 'source_type' => null, 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 998702, 'supplier_name' => 'NCC B', 'company_name' => 'Công ty TNHH Ego Việt Nam', 'document_no' => 'HD-B', 'document_date' => '2026-09-03', 'total_amount' => 1234567.5, 'paid_amount' => 0, 'status' => 'pending', 'source_type' => 'product_goods_receipt', 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('finance_supplier_debt_payments')->insert([
            ['supplier_debt_id' => 998701, 'payment_request_id' => null, 'payment_round' => 1, 'amount' => 2000000, 'status' => 'paid', 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now],
            ['supplier_debt_id' => 998701, 'payment_request_id' => 998801, 'payment_round' => 2, 'amount' => 4000000, 'status' => 'requested', 'created_by' => $admin->id, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $html = $this->actingAs($admin)->get(self::URL)->assertOk()->getContent();
        // Huy hiệu nay là chuỗi lớp Tailwind, không còn tên biến thể BEM.
        $this->assertStringContainsString('Đã thanh toán</span>', $html);
        $this->assertStringContainsString('tw:bg-[#dcfce7] tw:text-[#047857]', $html, 'huy hiệu xanh');
        $this->assertStringContainsString('Đã chi ĐNTT', $html, 'ĐNTT đã chi 3tr trên đợt 4tr → thanh toán một phần');
        $this->assertStringContainsString('Còn thiếu', $html);
        $this->assertStringNotContainsString('Tạo đợt còn lại', $html, 'admin thường không thấy nút đặc quyền');
        $this->assertStringContainsString('value="1234567.5"', $html, 'ô nhập tiền bỏ số 0 thừa');
        // Số đợt kế tiếp và số tiền đã có nay là tham số của component Alpine `chiaDotCongNo`,
        // không còn là thuộc tính `data-*` trên thẻ.
        $this->assertStringContainsString('dotKeTiep: 3', $html);
        $this->assertStringContainsString('daCo: 6000000', $html);
        $this->assertStringContainsString('Dữ liệu gốc lấy từ phiếu nhập kho', $html);
        $this->assertStringContainsString('<option value="" selected>Tất cả trạng thái</option>', $html, 'status null → "" như cũ');

        $html = $this->actingAs($editor)->get(self::URL)->assertOk()->getContent();
        $this->assertStringContainsString('Tạo đợt còn lại', $html);
        $this->assertStringContainsString('EGO_FINANCE_EDITOR_UNLOCK_UI', $html);
    }

    public function test_view_khong_tu_tinh_va_moi_bien_do_presenter_cap(): void
    {
        $source = (string) file_get_contents(base_path(self::VIEW));
        $this->assertStringNotContainsString('@php', $source);
        $this->assertStringNotContainsString('buibichthao', $source, 'email đặc quyền không còn viết cứng trong view');

        preg_match_all('/\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $m);
        $used = array_values(array_unique(array_diff($m[1], self::VIEW_VARIABLES_NOT_FROM_PRESENTER)));
        $provided = array_keys(app(SupplierDebtPagePresenter::class)->viewData(collect(), null, null, null, null, null));
        $this->assertSame([], array_values(array_diff($used, $provided)), 'biến view dùng mà presenter không trả');
    }
}
