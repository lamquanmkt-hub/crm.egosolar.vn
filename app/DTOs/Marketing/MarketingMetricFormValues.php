<?php

declare(strict_types=1);

namespace App\DTOs\Marketing;

/**
 * Giá trị biểu mẫu sửa chỉ số marketing, đã gộp `old()`.
 *
 * Thay khối `@php` 15 dòng trong view: khối đó tự `json_decode` ba cột breakdown, tự dựng biến
 * `$m` từ chuỗi `$metric ?? $m ?? $row ?? null`, và tự `$campaigns ?? collect()`.
 */
final readonly class MarketingMetricFormValues
{
    /**
     * @param  array<string, int|string>  $gender  khoá `male`/`female`/`unknown`
     * @param  array<string, int|string>  $age  khoá theo dải tuổi (`18-24`…)
     * @param  array<string, int|string>  $region  khoá `HCM`/`Hà Nội`/`Khác`
     */
    public function __construct(
        public int $id,
        /** `Y-m-d` cho `<input type="date">`; `''` khi chưa có. */
        public string $dateFrom,
        public string $dateTo,
        public string $platform,
        /** So sánh với id chiến dịch bằng `===` sau khi ép chuỗi — giữ đúng bản cũ. */
        public string $campaignId,
        public string $reach,
        public string $leads,
        public string $spend,
        public string $note,
        public array $gender,
        public array $age,
        public array $region,
    ) {}
}
