<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/** Bốn ô chỉ số đầu trang quỹ & tài khoản, đã định dạng sẵn. */
final readonly class AccountStatsCards
{
    public function __construct(
        public string $totalAccountsText,
        public string $activeAccountsText,
        public string $totalBalanceText,
        public string $cashBalanceText,
    ) {}
}
