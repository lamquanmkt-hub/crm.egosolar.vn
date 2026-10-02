<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\View\Presenters\Finance\AccountListPresenter;
use Tests\TestCase;

/** `AccountListPresenter` — assert giá trị thật. */
final class AccountListPresenterTest extends TestCase
{
    private function tk(array $attrs = []): object
    {
        return (object) array_merge([
            'id' => 5, 'name' => 'Quỹ tiền mặt', 'code' => 'TM01', 'type_label' => 'Tiền mặt',
            'opening_balance' => '50000000.00', 'current_balance' => '123456789.00',
            'note' => 'Quỹ chính', 'is_active' => true,
        ], $attrs);
    }

    public function test_dong_tai_khoan_in_dung(): void
    {
        $row = (new AccountListPresenter)->viewData([$this->tk()], [])['accountRows'][0];

        $this->assertSame(5, $row->id);
        $this->assertSame('Quỹ tiền mặt', $row->name);
        $this->assertSame('TM01', $row->codeText);
        $this->assertSame('Tiền mặt', $row->typeLabel);
        // `đ` nối LIỀN, dấu phân cách kiểu Việt — KHÔNG dùng DisplayFormat::money() (hàm đó có khoảng trắng).
        $this->assertSame('50.000.000đ', $row->openingText);
        $this->assertSame('123.456.789đ', $row->currentText);
        $this->assertTrue($row->isActive);
    }

    public function test_ma_trong_ra_gach_dai(): void
    {
        // Bản cũ dùng `?:` nên cả null LẪN chuỗi rỗng đều ra `—`.
        $this->assertSame('—', (new AccountListPresenter)->viewData([$this->tk(['code' => null])], [])['accountRows'][0]->codeText);
        $this->assertSame('—', (new AccountListPresenter)->viewData([$this->tk(['code' => ''])], [])['accountRows'][0]->codeText);
    }

    public function test_so_du_am_va_bang_khong(): void
    {
        $p = new AccountListPresenter;

        $this->assertSame('-2.500.000đ', $p->viewData([$this->tk(['current_balance' => -2500000])], [])['accountRows'][0]->currentText);
        $this->assertSame('0đ', $p->viewData([$this->tk(['current_balance' => 0])], [])['accountRows'][0]->currentText);
    }

    public function test_o_chi_so_dung_dau_tieng_viet(): void
    {
        $c = (new AccountListPresenter)->viewData([], [
            'total_accounts' => 1234, 'active_accounts' => 12,
            'total_balance' => 9876543210, 'cash_balance' => 1000,
        ])['statsCards'];

        // ⚠️ Bản cũ in `1,234` (number_format trơn, dấu kiểu Anh) — đổi có chủ ý theo quyết định 2026-09-29.
        $this->assertSame('1.234', $c->totalAccountsText);
        $this->assertSame('12', $c->activeAccountsText);
        $this->assertSame('9.876.543.210đ', $c->totalBalanceText);
        $this->assertSame('1.000đ', $c->cashBalanceText);
    }
}
