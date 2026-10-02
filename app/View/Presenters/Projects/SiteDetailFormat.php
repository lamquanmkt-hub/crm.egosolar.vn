<?php

declare(strict_types=1);

namespace App\View\Presenters\Projects;

use App\Support\DisplayFormat;

/**
 * Định dạng cho trang chi tiết công trình — trước 2026-09-07 là 5 closure trong `@php` của
 * `sites/show` (`$money`, `$fmtQty`, `$fmtDate`, `$fmtDateTime`, `$methodLabel`).
 */
final class SiteDetailFormat
{
    private const PAYMENT_METHOD_LABELS = [
        'cash' => 'Tiền mặt',
        'bank_transfer' => 'Chuyển khoản',
        'card' => 'Thẻ',
        'momo' => 'MoMo',
        'vnpay' => 'VNPay',
        'other' => 'Khác',
    ];

    public function money(mixed $value): string
    {
        return DisplayFormat::money($value);
    }

    public function qty(mixed $value): string
    {
        return DisplayFormat::quantity($value);
    }

    public function date(mixed $value): string
    {
        return DisplayFormat::date($value);
    }

    /** Nhãn hình thức thanh toán; mã lạ giữ nguyên, rỗng → `—`. */
    public function methodLabel(mixed $method): string
    {
        $method = (string) $method;

        return self::PAYMENT_METHOD_LABELS[$method] ?? ($method ?: '—');
    }
}
