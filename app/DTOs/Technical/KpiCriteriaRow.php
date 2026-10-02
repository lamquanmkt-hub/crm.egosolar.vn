<?php

declare(strict_types=1);

namespace App\DTOs\Technical;

/**
 * Một dòng tiêu chí KPI kỹ thuật.
 *
 * Dùng cho CẢ hai nguồn mà trang cài đặt trộn lẫn: dòng lấy từ `technical_payroll_kpi_items`
 * (object, cột decimal ra chuỗi `"1.00"`) và 5 tiêu chí mặc định viết cứng trong view (mảng, số
 * nguyên `1`). Trước đây view phải có một khối PHP trong vòng lặp chỉ để phân biệt hai dạng đó.
 *
 * `plan`/`actual` để `mixed` là CỐ Ý: cột decimal của MariaDB trả `"1.00"` còn mặc định là `1`,
 * ép kiểu sẽ đổi chuỗi in ra HTML.
 */
final readonly class KpiCriteriaRow
{
    /**
     * @param  int|null  $id  id trong DB; null với tiêu chí mặc định chưa từng lưu
     * @param  string  $key  khoá trong `kpi_items[...]` khi submit: `solar_<idx>` hoặc `legacy_<id>`
     * @param  float  $weightPercent  trọng số đã nhân 100 (0.30 -> 30)
     * @param  string  $icon  lớp icon Bootstrap Icons; rỗng với dòng đã tắt (không hiển thị)
     */
    public function __construct(
        public ?int $id,
        public string $key,
        public string $name,
        public string $unit,
        public mixed $plan,
        public mixed $actual,
        public float $weightPercent,
        public string $calcType,
        public string $sourceCode,
        public string $note,
        public mixed $order,
        public string $icon = '',
    ) {}
}
