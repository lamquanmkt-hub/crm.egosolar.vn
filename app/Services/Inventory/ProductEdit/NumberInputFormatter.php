<?php

declare(strict_types=1);

namespace App\Services\Inventory\ProductEdit;

/**
 * Định dạng số cho ô nhập: bỏ phần thập phân thừa, giữ tối đa 2 chữ số.
 *
 * Trước đây là một closure `$fmtInput` khai ngay trong view. Là đối tượng
 * `__invoke` nên view gọi y như cũ (`$fmtInput($v)`) mà không còn định nghĩa hàm
 * nào trong template.
 */
final class NumberInputFormatter
{
    public function __invoke(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $number = (float) $value;

        if (abs($number - round($number)) < 0.00001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
