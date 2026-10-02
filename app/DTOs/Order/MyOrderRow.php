<?php

declare(strict_types=1);

namespace App\DTOs\Order;

/**
 * Một dòng bảng "Đơn hàng gần đây" của `orders/my-orders`.
 *
 * Tồn tại vì view có khối `@php` NGAY TRONG `@forelse`: nó dựng lại bản đồ 6 bộ phận → tông badge
 * cho TỪNG dòng, rồi tra đúng một lần. Bản đồ đó là hằng, không phụ thuộc dòng nào cả.
 */
final readonly class MyOrderRow
{
    /**
     * @param  string  $totalText  đã định dạng `1.000.000` (chấm ngăn nghìn); view tự thêm `đ`
     * @param  string  $statusColor  mã màu nền badge trạng thái; `#6c757d` khi đơn chưa có trạng thái
     * @param  string  $departmentLabel  tên bộ phận đã viết hoa chữ đầu, đúng `ucfirst()` của bản cũ
     * @param  string  $departmentBadge  CHUỖI LỚP nền đầy đủ (không phải tên tông): `bg-{{ … }}`
     *                                   ghép lúc chạy thì Tailwind không sinh ra
     */
    public function __construct(
        public int $id,
        public string $orderCode,
        public string $customerName,
        public string $customerPhone,
        public string $totalText,
        public string $statusName,
        public string $statusColor,
        public string $departmentLabel,
        public string $departmentBadge,
    ) {}
}
