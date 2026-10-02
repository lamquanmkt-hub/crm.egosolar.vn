<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Đầu thẻ — thay `.card-header` của Bootstrap.
 *
 * Số đo lấy từ trình duyệt (Bootstrap 5.3.3): đệm 8px 16px, nền rgba(33,37,41,.03),
 * viền dưới 1px solid rgba(0,0,0,.175). Bo góc trên 5px CHỈ khi là con đầu
 * (`tw:first:rounded-t-[5px]`) — đó là `--bs-card-inner-border-radius` = 6−1px,
 * và giữ nguyên 5px kể cả khi bán kính thẻ bị chủ đề đổi thành 16px: Bootstrap
 * tính từ biến riêng của nó chứ không nhìn thẻ cha, đo trên hr/dashboard xác nhận.
 *
 * ⚠️ Kiểu viền phải đặt cho ĐÚNG MỘT CẠNH (`tw:[border-bottom-style:solid]`),
 * không dùng `tw:border-solid`: app đã bỏ preflight nên `border-solid` cho cả 4
 * cạnh sẽ làm cạnh không có bề dày lộ giá trị mặc định `medium` = 3px của trình
 * duyệt — đo được ngay khi so với markup gốc (viền trên 0px thành 3px).
 */
final class CardHeader extends Component
{
    private const BASE = 'tw:py-2 tw:px-4 tw:mb-0 tw:bg-[rgba(33,37,41,0.03)] '
        .'tw:border-b tw:[border-bottom-style:solid] tw:border-b-[rgba(0,0,0,0.175)] tw:first:rounded-t-[5px]';

    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div data-ego-card-header {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</div>
        BLADE;
    }
}
