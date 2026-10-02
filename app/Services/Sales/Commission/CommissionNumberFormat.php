<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use App\Support\DisplayFormat;

/**
 * Định dạng số cho báo cáo hoa hồng, theo quy ước Việt Nam: dấu chấm ngăn nghìn,
 * dấu phẩy ngăn thập phân.
 *
 * Trước đây là bốn closure khai ngay trong view (`$fmtMoney`, `$fmtNum`,
 * `$fmtPercent`, `$fmtRate`). Gom vào một lớp vì chúng cùng một mối quan tâm —
 * cách trang này hiển thị số — nên sửa quy ước là sửa đúng một chỗ.
 */
final class CommissionNumberFormat
{
    /** Tiền, không thập phân: `1.234.567 đ`. */
    public function money(mixed $value): string
    {
        return $this->group($value, 0).' đ';
    }

    /** Số đếm, không thập phân: `1.234`. */
    public function number(mixed $value): string
    {
        return $this->group($value, 0);
    }

    /** Phần trăm, luôn hai chữ số: `12,50%`. */
    public function percent(mixed $value): string
    {
        return $this->group($value, 2).'%';
    }

    /**
     * Tỉ lệ, bỏ số 0 thừa ở đuôi: `4%`, `4,5%`.
     *
     * Khác `percent()` ở chỗ dùng cho tỉ lệ do người dùng nhập, nơi `4%` đọc tự
     * nhiên hơn `4,00%`.
     */
    public function rate(mixed $value): string
    {
        $text = rtrim(rtrim($this->group($value, 2), '0'), ',');

        return ($text === '' ? '0' : $text).'%';
    }

    private function group(mixed $value, int $decimals): string
    {
        return DisplayFormat::number($value, $decimals);
    }
}
