<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\Services\Finance\FinanceFullAccess;
use App\View\Presenters\Finance\SupplierDebtPagePresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** {@see SupplierDebtPagePresenter} thay 6 khối `@php` của finance/supplier-debts/index (2026-09-07). Dòng là stdClass như query builder. */
final class SupplierDebtPagePresenterTest extends TestCase
{
    use DatabaseTransactions;

    private SupplierDebtPagePresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new SupplierDebtPagePresenter(new FinanceFullAccess);
    }

    public function test_quyen_sua_ho_so_hoan_tat_theo_config_va_mac_dinh_bo_loc(): void
    {
        $editor = $this->userWithRole(Role::Admin->value, ['email' => strtoupper(config('ego.finance_full_access_emails')[0])]);
        $other = $this->userWithRole(Role::Admin->value, ['email' => 'khac@example.test']);

        $data = $this->presenter->viewData(collect(), null, null, null, null, $editor);
        $this->assertTrue($data['financeCompletedEditor'], 'so email không phân biệt hoa thường');
        $this->assertSame(['', '', 'all', now()->format('Y-m')], [$data['status'], $data['keyword'], $data['period'], $data['month']], 'null → mặc định như view cũ để form so sánh === ""');
        $this->assertSame($this->presenter, $data['fmt']);

        $this->assertFalse($this->presenter->viewData(collect(), null, null, null, null, $other)['financeCompletedEditor']);
        $this->assertFalse($this->presenter->viewData(collect(), null, null, null, null, null)['financeCompletedEditor']);
    }

    public function test_dinh_dang_tien(): void
    {
        $this->assertSame('1.234.568 đ', $this->presenter->money('1234567.5'));
        $this->assertSame('1234567.5', $this->presenter->moneyInput(1234567.5));
        $this->assertSame('2000000', $this->presenter->moneyInput('2000000.00'));
        $this->assertSame('0', $this->presenter->moneyInput(null));
    }

    public function test_co_theo_cong_no_va_theo_dot(): void
    {
        $paidRound = (object) ['payment_round' => 1, 'amount' => '2000000.00', 'status' => 'paid', 'payment_request_id' => null];
        $requestRound = (object) ['payment_round' => 2, 'amount' => 4000000, 'status' => 'accounting_approved', 'payment_request_id' => 7, 'payment_request_is_locked' => true, 'is_partial_paid' => true, 'paid_amount_by_request' => '3000000', 'remaining_amount_by_request' => 1000000, 'remaining_round_exists' => 0];
        $missingRound = (object) ['payment_round' => 3, 'amount' => 1000000, 'status' => 'requested', 'payment_request_id' => 9, 'payment_request_missing' => true];
        $oddRound = (object) ['payment_round' => 4, 'amount' => 0, 'status' => 'admin_rejected', 'payment_request_id' => null];
        $blankRound = (object) ['payment_round' => 5, 'amount' => 500000, 'status' => null, 'payment_request_id' => null];
        $debt = (object) ['id' => 1, 'total_amount' => 10000000.0, 'source_type' => 'product_goods_receipt', 'payment_rounds' => collect([$paidRound, $requestRound, $missingRound, $oddRound, $blankRound])];
        $bare = (object) ['id' => 2, 'total_amount' => 0.0, 'source_type' => null, 'payment_rounds' => collect()];

        $this->presenter->viewData(collect([$debt, $bare]), 'partial', 'NCC', 'month', '2026-09', null);

        $this->assertSame([true, true, 6, 7500000.0], [$debt->has_linked_payment_request, $debt->synced_from_receipt, $debt->next_bulk_round, $debt->existing_round_amount]);
        $this->assertSame([false, false, 1, 0.0], [$bare->has_linked_payment_request, $bare->synced_from_receipt, $bare->next_bulk_round, $bare->existing_round_amount]);

        $this->assertSame(['paid', 'Đã thanh toán', 'tw:bg-[#dcfce7] tw:text-[#047857]', true, false, false, '20'], $this->flags($paidRound));
        $this->assertSame(['accounting_approved', 'Đã thanh toán', 'tw:bg-[#dcfce7] tw:text-[#047857]', true, true, true, '40'], $this->flags($requestRound));
        $this->assertSame([true, 3000000.0, 1000000.0, false], [$requestRound->is_partial_paid, $requestRound->paid_amount_by_request, $requestRound->remaining_amount_by_request, $requestRound->remaining_round_exists]);
        $this->assertSame(['requested', 'Đã lập ĐNTT', 'tw:bg-[#fef3c7] tw:text-[#b45309]', false, false, false, '10'], $this->flags($missingRound), 'ĐNTT đã mất → coi như không có');
        $this->assertSame(['admin_rejected', 'Admin từ chối', 'tw:bg-[#ffe4e6] tw:text-[#be123c]', false, false, false, '0'], $this->flags($oddRound));
        $this->assertSame(['planned', 'Dự kiến', 'tw:bg-[#fef3c7] tw:text-[#b45309]', false, false, false, '5'], $this->flags($blankRound), 'status null → planned');
        $this->assertSame([false, 0.0, 0.0, false], [$blankRound->is_partial_paid, $blankRound->paid_amount_by_request, $blankRound->remaining_amount_by_request, $blankRound->remaining_round_exists], 'thiếu meta ĐNTT → mặc định');
    }

    /** @return array{0: string, 1: string, 2: string, 3: bool, 4: bool, 5: bool, 6: string} */
    private function flags(object $round): array
    {
        return [$round->status_key, $round->status_text, $round->badge_class, $round->is_paid, $round->is_locked, $round->has_payment_request, $round->percent_text];
    }
}
