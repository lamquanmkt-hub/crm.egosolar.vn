<?php

declare(strict_types=1);

namespace App\DTOs\Technical;

/**
 * Một hệ số lương KPI kỹ thuật đã định dạng sẵn cho view.
 *
 * Tồn tại vì trang cài đặt trước đây gọi `number_format()` ngay trong Blade cho từng hệ số, với
 * hai cách định dạng khác nhau (ô tóm tắt không thập phân, ô input hai thập phân dấu chấm) —
 * và mỗi chỗ tự viết lại giá trị mặc định.
 */
final readonly class KpiSettingRow
{
    /**
     * @param  string  $percentSummary  phần trăm cho ô tóm tắt, không thập phân: `70`
     * @param  string  $percentInput  phần trăm cho `<input type=number>`: `70.00` — dấu CHẤM
     *                                thập phân, KHÔNG phân cách nghìn, theo đúng bản cũ
     * @param  string  $label  nhãn lưu kèm khi submit
     * @param  string  $note  ghi chú hiển thị, có mặc định riêng cho từng hệ số
     */
    public function __construct(
        public string $percentSummary,
        public string $percentInput,
        public string $label,
        public string $note,
    ) {}
}
