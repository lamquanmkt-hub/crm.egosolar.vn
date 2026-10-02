<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một dòng khách hàng trong bảng công nợ (`finance/debt-customers`).
 *
 * Tồn tại vì view có khối `@php` trong `@forelse` suy lại cặp lớp/chữ trạng thái cho từng khách.
 */
final readonly class CustomerDebtRow
{
    /**
     * @param  string  $detailId  id hàng chi tiết, `md5(group_key)` — view dùng cho cả nút mở lẫn
     *                            thuộc tính `id`, nên tính MỘT lần thay vì hai lần như bản cũ
     * @param  string  $avatarInitial  chữ cái đầu tên khách (`mb_substr`, an toàn với tiếng Việt)
     * @param  list<CustomerDebtOrderRow>  $orders
     */
    public function __construct(
        public string $detailId,
        public string $customerName,
        public string $avatarInitial,
        public string $totalOrdersText,
        public string $totalText,
        public string $paidText,
        public string $debtText,
        public string $statusClass,
        public string $statusText,
        public array $orders,
    ) {}
}
