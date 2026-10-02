<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Định dạng hiển thị dùng chung theo quy ước Việt Nam: chấm ngăn nghìn, phẩy ngăn thập phân,
 * ngày `d/m/Y`. Chiều ngược lại (chuỗi người dùng gõ → số) là {@see MoneyParser}.
 *
 * Trước 2026-09-07 mỗi trang tự viết `number_format($v, 0, ',', '.').' đ'` (17 file) hoặc
 * closure trong `@php`; các formatter theo trang (`MarketingNumberFormat`, `SiteDetailFormat`…)
 * nay uỷ quyền về đây để sửa quy ước là sửa một chỗ.
 */
final class DisplayFormat
{
    /** Tiền, không thập phân: `1.234.567 đ`; null/chuỗi lạ → `0 đ`. */
    public static function money(mixed $value): string
    {
        return self::number($value).' đ';
    }

    /** Số với số chữ số thập phân cố định: `1.234` hoặc `1.234,50`. */
    public static function number(mixed $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals, ',', '.');
    }

    /**
     * Phần trăm theo quy ước VIỆT NAM: `0,0%`, `100,00%`, `1.234,5%`.
     *
     * Trước 2026-09-29 các trang gọi `number_format($v, 1)` TRƠN, tức dấu mặc định của PHP
     * (`0.0%`) — nên cùng một trang in tiền kiểu Việt (`1.000.000 đ`) mà phần trăm lại kiểu Anh.
     * Nay thống nhất: tiếng Việt thì dùng dấu tiếng Việt ở MỌI trang. Đổi đầu ra có chủ ý.
     */
    public static function percent(mixed $value, int $decimals = 0): string
    {
        return self::number($value, $decimals).'%';
    }

    /** Số lượng: `—` khi trống; số nguyên không thập phân, số lẻ hai chữ số (`20,50`). */
    public static function quantity(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $value = (float) $value;

        return self::number($value, floor($value) == $value ? 0 : 2);
    }

    /** Ngày `d/m/Y`; trống → `—`; không đọc được → trả nguyên chuỗi. */
    public static function date(mixed $value, string $format = 'd/m/Y'): string
    {
        if (empty($value)) {
            return '—';
        }
        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
