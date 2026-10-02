<?php

declare(strict_types=1);

namespace App\DTOs\Technical;

/**
 * Một dòng kỹ sư trong bảng "KPI công trình".
 *
 * Thay khối `@php` nằm TRONG `@foreach`: nó tra bản ghi minh chứng và tín hiệu workflow cho từng
 * kỹ sư. Các trường `*Value` là giá trị đã có trong DB để `@selected`/`@checked` so sánh.
 */
final readonly class ProjectKpiEngineerRow
{
    /**
     * @param  bool  $hasSignal  có hoạt động workflow trong kỳ hay không
     * @param  string|null  $onTime  `'ok'` đúng hạn, `'late'` trễ hạn, null khi chưa đủ dữ liệu —
     *                               bản cũ phân biệt `=== true` / `=== false` / còn lại
     * @param  int|null  $qualityFirstPass  giữ NGUYÊN kiểu để `@selected(… === 1)` của bản cũ còn đúng
     * @param  float  $penaltyPoints  bản cũ so bằng `(float) … === 0.0|10.0|20.0`
     */
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public bool $hasSignal,
        public string $deadlineText,
        public string $completedText,
        public ?string $onTime,
        public bool $timelineExcluded,
        public string $exclusionReason,
        public ?int $qualityFirstPass,
        public string $materialWastePercent,
        public ?int $hsePass,
        public ?int $evnAppRequired,
        public ?int $evnAppCompleted,
        public float $penaltyPoints,
        public string $note,
    ) {}
}
