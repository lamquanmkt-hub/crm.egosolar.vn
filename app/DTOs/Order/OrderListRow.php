<?php

declare(strict_types=1);

namespace App\DTOs\Order;

/**
 * Một đơn trên trang danh sách đơn hàng (bảng máy tính và thẻ di động dùng chung): bản ghi + khách,
 * tiền đã thu/còn nợ/phần trăm, trạng thái hiển thị, nhãn bộ phận đang giữ đơn, hậu mãi đang xử lý.
 */
final readonly class OrderListRow
{
    public function __construct(
        public object $order,
        public ?object $customer,
        public float $total,
        public float $paid,
        public float $remain,
        public int $payPct,
        public bool $isCancelled,
        public string $statusText,
        public string $statusClass,
        public string $departmentLabel,
        public int $activeReturnCount,
        public ?object $latestReturn,
    ) {}
}
