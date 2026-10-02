<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canh giữ 8 trang tài chính/kỹ thuật/marketing.
 *
 * ## Bù line-height phải theo TỶ LỆ, không phải theo con số
 * `.action-pill` của trang lương kỹ thuật không khai font-size (11.5px đến từ CSS
 * khác) và không khai line-height. Bù bằng `tw:text-[16px]/[24px]` là SAI — dòng cao
 * 24px thay vì 17.25px. Đúng là `tw:leading-[1.5]`: giữ đúng tỷ lệ mà `.btn` từng
 * đặt, bất kể cỡ chữ thực tế đến từ đâu.
 *
 * ## Nút xoá của hai trang phiếu chỉ render khi bảng CÓ dòng
 * Lần đo đầu không có phiếu nào nên hai nút đã chuyển không xuất hiện và phép so
 * không chạm tới. Test dưới seed một phiếu chi và một phiếu thu để giữ che phủ.
 */
final class FinanceTechButtonsTest extends TestCase
{
    use DatabaseTransactions;

    /** @return list<string> */
    private function buttonTags(string $view): array
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        preg_match_all('/<x-ui\.button\b(?:[^>"]|"[^"]*")*>/s', $source, $m);

        return $m[0];
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function views(): array
    {
        return [
            'công nợ theo khách' => ['finance/debt-customers-by-user.blade.php', 2],
            'lịch sử thanh toán' => ['finance/payment-history.blade.php', 2],
            'tạo phiếu thu' => ['finance/receipts/create.blade.php', 2],
            'quyết toán' => ['finance/settlement.blade.php', 2],
            'danh sách phiếu chi' => ['finance/payments/index.blade.php', 6],
            'danh sách phiếu thu' => ['finance/receipts/index.blade.php', 6],
            'sửa lương kỹ thuật' => ['kythuat/luong_edit.blade.php', 3],
            'bảng lương KPI' => ['marketing/kpi_payroll/index.blade.php', 2],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function test_da_chuyen(string $view, int $count): void
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));

        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*(?<![\w-])btn(?![\w-])[^"]*(?<![\w-])btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)(?![\w-])/',
            $source,
        );
        $this->assertCount($count, $this->buttonTags($view));
    }

    /**
     * `.action-pill` phải bù line-height theo TỶ LỆ.
     *
     * Cỡ chữ thật là 11.5px và đến từ CSS khác, nên khai `tw:text-[16px]/[24px]`
     * sẽ đặt dòng cao 24px thay vì 17.25px.
     */
    public function test_luong_ky_thuat_bu_line_height_theo_ty_le(): void
    {
        foreach ($this->buttonTags('kythuat/luong_edit.blade.php') as $tag) {
            $this->assertStringContainsString('size="none"', $tag);
            $this->assertStringContainsString('tw:leading-[1.5]', $tag);
            $this->assertStringNotContainsString('tw:text-[16px]', $tag,
                'Không khai cỡ chữ: 11.5px đến từ CSS khác, khai vào là lệch dòng');
        }
    }

    /** Nút In của trang quyết toán chạy window.print() nên giữ type="button". */
    public function test_quyet_toan_nut_in_giu_type_button(): void
    {
        $in = array_values(array_filter(
            $this->buttonTags('finance/settlement.blade.php'),
            static fn (string $t): bool => str_contains($t, 'window.print()'),
        ));

        $this->assertCount(1, $in);
        $this->assertStringContainsString('type="button"', $in[0]);
    }

    /** Tám trang render được; hai trang phiếu có dòng để nút xoá xuất hiện. */
    public function test_render_tam_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value, ['id' => 999700]);

        DB::table('technical_kpi_payrolls')->insert([
            'id' => 998100, 'user_id' => $user->id, 'employee_name' => 'Nguyen Van A',
            'position_name' => 'Ky thuat', 'payroll_month' => '2026-09',
            'month_label' => '09/2026', 'gross_salary' => 15000000,
            'status' => 'draft', 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('accounts')->insert([
            'id' => 998110, 'name' => 'Tai khoan canh test', 'code' => 'TK-CT', 'type' => 'bank',
            'opening_balance' => 0, 'current_balance' => 0, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payments')->insert([
            'id' => 998111, 'account_id' => 998110, 'code' => 'PC-CT',
            // ⚠️ Phải nằm TRONG tháng hiện tại: trang lọc mặc định từ `now()->startOfMonth()`
            // (PaymentController:23). Ngày cứng làm test xanh trong tháng đó rồi đỏ sang tháng sau.
            'payment_date' => now()->startOfMonth()->format('Y-m-d'), 'payee_name' => 'Nguoi nhan',
            'category' => 'other', 'payment_method' => 'cash', 'amount' => 500000,
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('receipts')->insert([
            'id' => 998112, 'account_id' => 998110, 'code' => 'PT-CT',
            // Cùng lý do với `payment_date` ở trên.
            'receipt_date' => now()->startOfMonth()->format('Y-m-d'), 'payer_name' => 'Nguoi nop',
            'category' => 'other', 'payment_method' => 'cash', 'amount' => 700000,
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/finance/customer-debts/by-customer',
            '/finance/customer-debts/payment-history',
            '/finance/receipts/create',
            '/finance/reports/settlement',
            '/ky-thuat/luong/998100/edit',
            '/marketing/kpi-payroll',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        // Nút xoá chỉ có khi bảng có dòng — giữ điều kiện để không mất che phủ.
        foreach (['/finance/payments', '/finance/receipts'] as $url) {
            $html = (string) $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('rounded-pill tw:px-4', $html, "Nút xoá không render ở {$url}");
        }
    }
}
