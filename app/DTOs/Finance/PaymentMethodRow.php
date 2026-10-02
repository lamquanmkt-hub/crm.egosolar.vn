<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/** Một dòng phương thức thanh toán trên `payment_methods/index`. */
final readonly class PaymentMethodRow
{
    public function __construct(
        public int $id,
        public string $methodName,
        public string $code,
        /** Mô tả đã cắt 50 ký tự — bản cũ gọi `Str::limit()` NGAY TRONG view (Facade, bị cấm). */
        public string $descriptionText,
        public bool $isActive,
    ) {}
}
