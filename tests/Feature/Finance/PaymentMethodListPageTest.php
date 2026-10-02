<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\PaymentMethodRow;
use App\View\Presenters\Finance\PaymentMethodListPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guard + presenter của trang `payment_methods/index` sau đợt 2026-10-01. */
final class PaymentMethodListPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/payment_methods/index.blade.php';

    public function test_view_khong_con_facade_va_chi_giu_dung_mot_lop_bootstrap(): void
    {
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('Str::', $view, 'Facade trong Blade bị cấm — đã sang presenter');
        $this->assertStringNotContainsString('<style', $view);

        preg_match_all('/(?<![-:\w])class="([^"]*)"/', $view, $khop);
        $token = [];
        foreach ($khop[1] as $ds) {
            foreach (preg_split('/\s+/', trim($ds)) ?: [] as $l) {
                if ($l !== '' && ! str_starts_with($l, 'tw:') && $l !== 'bi' && ! str_starts_with($l, 'bi-')) {
                    $token[$l] = true;
                }
            }
        }

        /*
         * CHỈ còn `.input-group`, và đó là CỐ Ý: nó là MÓC của chính CSS repo —
         * `resources/css/app.css` khai `.input-group > input:not(.form-control){position:relative;
         * flex:1 1 auto;width:1%;min-width:0}` để ô `<x-ui.input>` (không còn `.form-control`) vẫn co
         * giãn đúng, cộng luật con của Bootstrap bỏ bo góc giữa hai phần tử liền nhau.
         * Thay bằng utility đã đo: ô nở 363 → 405px và nút rớt xuống dòng (116 ô lệch).
         */
        $this->assertSame(['input-group'], array_keys($token));
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));
        preg_match_all('/\$row->([a-zA-Z0-9]+)/', $view, $hit);

        $props = array_map(
            static fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass(PaymentMethodRow::class))->getProperties()
        );

        $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)));
    }

    public function test_presenter_cat_mo_ta_dung_50_ky_tu(): void
    {
        $dai = str_repeat('a', 80);
        $rows = (new PaymentMethodListPresenter)->viewData([
            (object) ['id' => 1, 'method_name' => 'Chuyển khoản', 'code' => 'BANK', 'description' => $dai, 'is_active' => 1],
            (object) ['id' => 2, 'method_name' => 'Tiền mặt', 'code' => 'CASH', 'description' => null, 'is_active' => 0],
        ])['methodRows'];

        // `Str::limit($x, 50)` = 50 ký tự + '...' (bản cũ gọi ngay trong view).
        $this->assertSame(str_repeat('a', 50).'...', $rows[0]->descriptionText);
        $this->assertSame('', $rows[1]->descriptionText, 'mô tả null ra chuỗi rỗng');
        $this->assertTrue($rows[0]->isActive);
        $this->assertFalse($rows[1]->isActive);
    }

    public function test_trang_that_in_dung(): void
    {
        $admin = $this->userWithRole('admin', ['id' => 815001, 'name' => 'QT Guard', 'email' => 'qt-pm-guard@example.test']);

        DB::table('crm_payment_methods')->insert([
            ['id' => 815101, 'method_name' => 'Chuyển khoản ngân hàng', 'code' => 'BANK', 'is_active' => 1,
                'description' => str_repeat('x', 70)],
            ['id' => 815102, 'method_name' => 'Tiền mặt', 'code' => 'CASH', 'is_active' => 0, 'description' => null],
        ]);

        $html = (string) $this->actingAs($admin)->get('/payment-methods')->assertOk()->getContent();

        $this->assertStringContainsString('Chuyển khoản ngân hàng', $html);
        $this->assertStringContainsString('BANK', $html);
        $this->assertStringContainsString(str_repeat('x', 50).'...', $html, 'mô tả dài phải bị cắt');
        $this->assertStringContainsString('Hoạt động', $html);
        $this->assertStringContainsString('Tạm khóa', $html);
    }

    public function test_danh_sach_rong(): void
    {
        $admin = $this->userWithRole('admin', ['id' => 815002, 'name' => 'QT Guard 2']);

        $html = (string) $this->actingAs($admin)->get('/payment-methods?keyword=khong-co-gi')->assertOk()->getContent();

        $this->assertStringContainsString('Chưa có phương thức thanh toán nào.', $html);
    }
}
