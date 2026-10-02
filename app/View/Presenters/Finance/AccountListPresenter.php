<?php

declare(strict_types=1);

namespace App\View\Presenters\Finance;

use App\DTOs\Finance\AccountRow;
use App\DTOs\Finance\AccountStatsCards;
use App\Support\DisplayFormat;

/**
 * Chuẩn bị trang `finance/accounts/index` (quỹ & tài khoản).
 *
 * Trang này không có khối `@php`, nhưng có **6 lượt `number_format`** viết thẳng trong Blade; dời
 * hết về đây để một chỗ quyết định cách hiển thị số.
 *
 * Lớp này thuần: không Facade, không query, không `request()`/`auth()`.
 */
final class AccountListPresenter
{
    /**
     * @param  iterable<object>  $accounts
     * @param  array<string, mixed>  $stats
     * @return array{statsCards: AccountStatsCards, accountRows: list<AccountRow>}
     */
    public function viewData(iterable $accounts, array $stats): array
    {
        $rows = [];

        foreach ($accounts as $account) {
            $rows[] = new AccountRow(
                id: (int) ($account->id ?? 0),
                name: (string) ($account->name ?? ''),
                note: (string) ($account->note ?? ''),
                // Bản cũ: `$account->code ?: '—'` — chuỗi RỖNG cũng ra gạch dài, nên dùng `?:`.
                codeText: ((string) ($account->code ?? '')) ?: '—',
                typeLabel: (string) ($account->type_label ?? ''),
                openingText: $this->tien($account->opening_balance ?? 0),
                currentText: $this->tien($account->current_balance ?? 0),
                isActive: (bool) ($account->is_active ?? false),
            );
        }

        return [
            'statsCards' => new AccountStatsCards(
                // ⚠️ Bản cũ dùng `number_format()` TRƠN cho hai ô đếm → dấu kiểu Anh (`1,234`) ngay
                // cạnh ô tiền kiểu Việt. Đổi theo quyết định 2026-09-29; chỉ thấy khác khi ≥ 1.000.
                totalAccountsText: DisplayFormat::number($stats['total_accounts'] ?? 0),
                activeAccountsText: DisplayFormat::number($stats['active_accounts'] ?? 0),
                totalBalanceText: $this->tien($stats['total_balance'] ?? 0),
                cashBalanceText: $this->tien($stats['cash_balance'] ?? 0),
            ),
            'accountRows' => $rows,
        ];
    }

    /**
     * Tiền trên trang này in `123.456.789đ` — chữ `đ` nối LIỀN, không có khoảng trắng.
     *
     * Nên dùng `DisplayFormat::number()` chứ KHÔNG phải `::money()` (hàm đó trả `… đ` có khoảng
     * trắng). Bản cũ là `number_format($x, 0, ',', '.')` — trùng đúng `number()` với 0 chữ số lẻ.
     */
    private function tien(mixed $value): string
    {
        return DisplayFormat::number($value).'đ';
    }
}
