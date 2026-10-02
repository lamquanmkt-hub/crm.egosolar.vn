<?php

declare(strict_types=1);

namespace App\DTOs\Hr;

/** Năm ô chỉ số đầu trang tăng ca, đã định dạng sẵn. */
final readonly class OvertimeSummaryCards
{
    public function __construct(
        public string $totalText,
        public string $pendingText,
        public string $approvedText,
        public string $rejectedText,
        /** Giờ tăng ca đã duyệt, KHÔNG kèm chữ "giờ" (view tự thêm). */
        public string $hoursText,
    ) {}
}
