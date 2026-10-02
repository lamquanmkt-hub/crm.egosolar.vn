<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\DTOs\Finance\AccountRow;
use App\DTOs\Finance\AccountStatsCards;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guard trang `finance/accounts/index` sau đợt 2026-10-01. */
final class AccountListPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/finance/accounts/index.blade.php';

    public function test_view_sach_bootstrap_va_khong_tu_dinh_dang(): void
    {
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('number_format', $view, 'định dạng số phải ở presenter');
        $this->assertStringNotContainsString('style=', $view, 'gradient inline đã thành utility');
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
        $this->assertSame([], array_keys($token), 'view còn lớp không phải `tw:*`');

        // Bảng phải đi qua component để giữ móc JS của main.js…
        $this->assertStringContainsString('<x-ui.table-wrap>', $view);
        // …nhưng đầu bảng CỐ Ý là <thead> trơn: <x-ui.table-head> tái hiện `.table-light` (đổi biến
        // nền/viền/màu chữ của Ô) trong khi bản cũ chỉ đặt `bg-light` lên chính <thead>, nền ô vẫn TRẮNG.
        $this->assertStringNotContainsString('<x-ui.table-head', $view);
        $this->assertStringContainsString('<thead class="tw:bg-[rgb(248,249,250)]">', $view);
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        foreach (['row' => AccountRow::class, 'statsCards' => AccountStatsCards::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $view, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)));
        }
    }

    public function test_trang_that_in_dung(): void
    {
        $admin = $this->userWithRole('admin', ['id' => 812001, 'name' => 'QT Guard', 'email' => 'qt-acc-guard@example.test']);

        DB::table('accounts')->insert([
            ['id' => 812101, 'name' => 'Quỹ tiền mặt công ty', 'code' => 'TM01', 'type' => 'cash',
                'opening_balance' => 50000000, 'current_balance' => 123456789, 'note' => 'Quỹ chính',
                'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 812102, 'name' => 'Vietcombank', 'code' => null, 'type' => 'bank',
                'opening_balance' => 0, 'current_balance' => -2500000, 'note' => null,
                'is_active' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $html = (string) $this->actingAs($admin)->get('/finance/accounts')->assertOk()->getContent();

        $this->assertStringContainsString('123.456.789đ', $html);
        $this->assertStringContainsString('-2.500.000đ', $html);
        $this->assertStringContainsString('Tiền mặt', $html);
        $this->assertStringContainsString('Ngân hàng', $html);
        $this->assertStringContainsString('—', $html, 'tài khoản không có mã in gạch dài');
        $this->assertStringContainsString('Hoạt động', $html);
        $this->assertStringContainsString('Ngưng', $html);
        // Tổng số dư = 123.456.789 - 2.500.000
        $this->assertStringContainsString('120.956.789đ', $html);
    }

    public function test_bo_loc_va_trang_thai_rong(): void
    {
        $admin = $this->userWithRole('admin', ['id' => 812002, 'name' => 'QT Guard 2']);

        DB::table('accounts')->insert([
            'id' => 812103, 'name' => 'Ví MoMo', 'code' => 'MOMO', 'type' => 'ewallet',
            'opening_balance' => 1000, 'current_balance' => 999, 'note' => null,
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $loc = (string) $this->actingAs($admin)->get('/finance/accounts?type=cash')->assertOk()->getContent();
        $this->assertStringContainsString('Chưa có quỹ / tài khoản nào', $loc);

        $co = (string) $this->actingAs($admin)->get('/finance/accounts?type=ewallet')->assertOk()->getContent();
        $this->assertStringContainsString('Ví điện tử', $co);
        $this->assertStringContainsString('999đ', $co);
    }

    /**
     * Loại tài khoản lạ KHÔNG xảy ra được: `accounts.type` là ENUM('cash','bank','ewallet').
     *
     * Nghĩa là nhánh `default => 'Khác'` của `Account::getTypeLabelAttribute()` là code chết trên
     * production. Ghi lại bằng test để không ai tưởng nó là nhánh sống.
     */
    public function test_cot_type_la_enum_ba_gia_tri(): void
    {
        $cot = DB::selectOne("SHOW COLUMNS FROM accounts WHERE Field = 'type'");

        $this->assertSame("enum('cash','bank','ewallet')", $cot->Type);
    }
}
