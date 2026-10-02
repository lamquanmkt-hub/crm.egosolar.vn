<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/** Một dòng quỹ / tài khoản trên `finance/accounts/index`. */
final readonly class AccountRow
{
    public function __construct(
        public int $id,
        public string $name,
        public string $note,
        /** Mã tài khoản; `—` (gạch DÀI) khi trống — giữ đúng `?:` của bản cũ. */
        public string $codeText,
        public string $typeLabel,
        /** Số dư đã định dạng kiểu Việt, `đ` nối LIỀN (`123.456.789đ`). */
        public string $openingText,
        public string $currentText,
        public bool $isActive,
    ) {}
}
