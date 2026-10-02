<?php

declare(strict_types=1);

namespace App\DTOs\Order;

use App\Models\CRM\Orders\OrderItem;

/**
 * Một dòng hàng trên bảng sản phẩm của trang chi tiết đơn, tách VAT.
 *
 * Đơn giá lưu trong đơn là giá SAU VAT; giá trước VAT và tiền thuế suy ngược theo `vatPercent`
 * (của dòng, thiếu thì của sản phẩm). `lineAfter` là thành tiền sau chiết khấu theo cùng luật
 * với lúc lưu đơn (OrderItemCalculator::calcLineTotal).
 */
final readonly class OrderProductLineView
{
    public function __construct(
        public OrderItem $item,
        public int $quantity,
        public float $vatPercent,
        public string $vatLabel,
        public float $unitBefore,
        public float $unitAfter,
        public float $lineBefore,
        public float $lineAfter,
        public float $vatAmount,
    ) {}
}
