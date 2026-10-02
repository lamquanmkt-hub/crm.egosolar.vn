<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Nhãn trường thay cho `.form-label`.
 *
 * ## `.form-label` của Bootstrap CHỈ có đúng một khai báo
 *     .form-label { margin-bottom: .5rem; }
 * Không cỡ chữ, không line-height, không font-weight, không display.
 *
 * ⚠️ Bản đầu của component này ghi thêm `text-[16px]/[24px]`, `font-normal` và
 * `inline-block`. Trên trang mà nhãn được cấp cỡ chữ khác (ví dụ kèm `.small`,
 * hoặc nằm trong bảng có `font-size: 14px`), giá trị ghi cứng đó ĐÈ mất cỡ chữ
 * thừa kế — đo trên sites/edit thấy lệch. Nay chỉ giữ đúng phần Bootstrap có.
 *
 * ## Vì sao phải nhường `mb-*` của nơi gọi
 * `$attributes->class()` chỉ NỐI chuỗi, thứ tự trong HTML không quyết định
 * ai thắng — thứ tự trong tệp CSS mới quyết định. Tailwind xuất `mb-1`
 * TRƯỚC `mb-2`, nên `<x-ui.label class="tw:mb-1">` bị BASE `tw:mb-2` đè,
 * và trước đây phải chữa bằng `tw:mb-1!`. Nay bỏ hẳn dấu `!`: nếu nơi gọi
 * đã tự cho lề dưới thì component không thêm lề mặc định nữa.
 */
final class Label extends Component
{
    private const BASE = 'tw:mb-2';

    /** Nơi gọi đã tự đặt lề dưới thì thôi, tránh hai lớp `mb-*` chọi nhau. */
    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <label {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</label>
        BLADE;
    }
}
