<?php

declare(strict_types=1);

namespace App\DTOs\Order;

/** Tổng bảng sản phẩm của trang chi tiết đơn: trước VAT, tiền thuế theo từng mức, sau VAT. */
final readonly class OrderVatSummary
{
    /**
     * @param  array<int|string, float>  $groups  nhãn mức VAT => tiền thuế, theo thứ tự xuất hiện; nhãn nguyên
     *                                            ("8", "10") bị PHP ép thành khoá số, nhãn lẻ ("8.5") giữ chuỗi
     */
    public function __construct(
        public float $beforeVat,
        public float $vatAmount,
        public float $afterVat,
        public array $groups,
    ) {}
}
