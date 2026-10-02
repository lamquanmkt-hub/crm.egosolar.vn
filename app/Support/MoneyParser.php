<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Chuyển chuỗi tiền tệ người dùng nhập về số.
 *
 * ## Vì sao gom về một chỗ
 * Trước đây có HAI bản parse tiền khác nhau trên cùng một luồng lưu đơn:
 *
 * - `OrderRequest::parseMoney()` chỉ bỏ `.`, `,`, khoảng trắng. Gặp
 *   `"1.234.567 đ"` (đúng cái mà form đang hiển thị) thì còn lại `"1234567đ"`,
 *   không phải số → trả **0**. Dòng hàng sau đó bị bỏ qua vì đơn giá bằng 0:
 *   người dùng lưu đơn mà giá âm thầm không được ghi.
 * - `OrderController::egoParseMoney()` xử lý đầy đủ nhưng chạy SAU khi
 *   FormRequest đã ghi đè input, nên không bao giờ nhìn thấy chuỗi gốc.
 *
 * Nay chỉ còn một bản dùng chung, xử lý đúng các định dạng đang gặp thật.
 *
 * ## Quy tắc phân biệt dấu ngăn cách
 * - Có cả `.` và `,`: dấu nào đứng sau cùng là dấu thập phân.
 * - Chỉ có `,`: `,` theo sau đúng 3 chữ số ở cuối → ngăn cách hàng nghìn
 *   (`1,500` = 1500); ngược lại là dấu thập phân (`1,5` = 1.5).
 * - Chỉ có `.`: các nhóm 3 chữ số → ngăn cách hàng nghìn (`1.234.567`);
 *   ngược lại giữ nguyên là dấu thập phân (`1.5`).
 */
final class MoneyParser
{
    /** Ký tự tiền tệ/đơn vị cần loại bỏ trước khi phân tích. */
    private const NOISE = ["\xc2\xa0", ' ', 'VND', 'vnd', 'VNĐ', 'vnđ', 'đ', 'd'];

    /**
     * Phân tích giá trị tiền tệ; giá trị không hợp lệ trả 0.0.
     */
    public static function parse(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if ($value === null || is_array($value) || is_object($value)) {
            return 0.0;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return 0.0;
        }

        $text = str_replace(self::NOISE, '', $text);
        $text = preg_replace('/[^0-9,.\-]/', '', $text) ?? '';

        if ($text === '' || $text === '-') {
            return 0.0;
        }

        $text = self::normalizeSeparators($text);

        return is_numeric($text) ? (float) $text : 0.0;
    }

    /**
     * Phân tích rồi ép về số nguyên không âm (số lượng, số suất...).
     */
    public static function parseQuantity(mixed $value, int $minimum = 0): int
    {
        return max($minimum, (int) self::parse($value));
    }

    /**
     * Phân tích rồi kẹp vào khoảng [min, max] — dùng cho % VAT, % chiết khấu.
     */
    public static function parsePercent(mixed $value, float $min = 0, float $max = 100): float
    {
        return max($min, min($max, self::parse($value)));
    }

    /**
     * Đưa dấu ngăn cách hàng nghìn/thập phân về dạng chuẩn của PHP.
     */
    private static function normalizeSeparators(string $value): string
    {
        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            return strrpos($value, ',') > strrpos($value, '.')
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        }

        if ($hasComma) {
            return preg_match('/,\d{3}$/', $value) === 1
                ? str_replace(',', '', $value)
                : str_replace(',', '.', $value);
        }

        if ($hasDot && preg_match('/\.\d{3}(\.\d{3})*$/', $value) === 1) {
            return str_replace('.', '', $value);
        }

        return $value;
    }
}
