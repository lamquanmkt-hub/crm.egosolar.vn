<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

/**
 * Một thẻ chứng từ trên trang chi tiết đề nghị thanh toán.
 *
 * Thay khối `@php` nằm TRONG `@foreach`: nó gọi `basename()`, `pathinfo()`, `number_format()`,
 * `url()` hai lần và dựng nhãn loại tệp cho từng bản ghi.
 */
final readonly class PaymentAttachmentCard
{
    /**
     * @param  string  $sizeText  `200.0 KB`; chuỗi RỖNG khi bản ghi không có kích thước.
     *                            ⚠️ Dùng `number_format($x, 1)` MẶC ĐỊNH (dấu chấm thập phân,
     *                            kiểu Anh) — giữ y bản cũ, KHÔNG đổi sang `DisplayFormat`.
     * @param  string  $badge  nhãn ô vuông đã VIẾT HOA: `ẢNH` với ảnh, ngược lại là phần mở rộng;
     *                         tệp không có phần mở rộng ra `FILE`
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $mimeText,
        public string $sizeText,
        public string $badge,
        public string $downloadUrl,
        public string $previewUrl,
    ) {}
}
