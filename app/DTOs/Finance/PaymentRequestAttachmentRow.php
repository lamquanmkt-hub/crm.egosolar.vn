<?php

declare(strict_types=1);

namespace App\DTOs\Finance;

use App\Models\Payments\PaymentAttachment;

/** Chứng từ của form sửa phiếu: giữ bản ghi gốc và giá trị hiển thị, không tính trong vòng lặp Blade. */
final readonly class PaymentRequestAttachmentRow
{
    public function __construct(
        public PaymentAttachment $attachment,
        public string $fileName,
        public string $fileMeta,
        public string $downloadPath,
    ) {}
}
