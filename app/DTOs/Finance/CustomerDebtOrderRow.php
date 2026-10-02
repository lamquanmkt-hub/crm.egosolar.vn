<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một dòng đơn hàng trong bảng chi tiết của `finance/debt-customers`.
 *
 * Tồn tại vì view có khối `@php` NGAY TRONG `@foreach` LỒNG (đơn của từng khách): nó suy lại
 * cờ "đã hoàn thành" và cặp lớp/chữ trạng thái cho mỗi đơn, mỗi lần mở trang.
 */
final readonly class CustomerDebtOrderRow
{
    /**
     * @param  string  $totalText  đã định dạng `1.000.000 đ` (có dấu cách trước `đ`)
     * @param  string  $statusClass  chuỗi lớp đầy đủ của huy hiệu trạng thái
     * @param  string  $createdAtText  `d/m/Y H:i`; RỖNG khi không có ngày (đúng `optional()` cũ)
     * @param  string  $deleteConfirmText  câu hỏi trước khi xoá, CÓ xuống dòng thật (`\n`)
     */
    public function __construct(
        public int $id,
        public string $orderCode,
        public string $totalText,
        public string $paidText,
        public string $debtText,
        public string $statusClass,
        public string $statusText,
        public string $createdAtText,
        public string $deleteConfirmText,
    ) {}
}
