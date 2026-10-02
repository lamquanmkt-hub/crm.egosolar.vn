<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

/**
 * Tóm tắt hàng hoá của một đơn: chuỗi mô tả để dò quy tắc, và tổng số lượng.
 *
 * `text` dùng để so khớp tên/mã sản phẩm với điều kiện của quy tắc hoa hồng,
 * nên nó là một chuỗi gộp chứ không phải danh sách — quy tắc chỉ cần biết đơn
 * này "có chứa" từ khoá nào.
 */
final class OrderProductInfo
{
    public function __construct(
        public readonly string $text = '',
        public readonly float $quantity = 0.0,
    ) {}
}
