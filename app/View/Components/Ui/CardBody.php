<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Thân thẻ — thay `.card-body` của Bootstrap.
 *
 * ## Vì sao phải chuyển CÙNG LÚC với `.card`
 * Bootstrap khai `--bs-card-spacer-y/x` trên CHÍNH `.card`, còn `.card-body` chỉ
 * đọc (`padding: var(--bs-card-spacer-y) var(--bs-card-spacer-x)`). Bỏ lớp `.card`
 * ở cha là biến mất giá trị, thân thẻ mất sạch đệm — đo được: cao 72px còn 40px,
 * đệm 16px thành 0. Nên component này ghi thẳng số đo (`tw:p-4` = 1rem), không
 * bám biến của Bootstrap.
 *
 * `data-ego-card-body` là móc cho mật độ compact
 * (`html[data-ego-density="compact"] [data-ego-card-body]{padding-top/bottom:.72rem!important}`
 * trong system-branding-runtime) và cho các luật riêng của trang đã nối cùng móc.
 */
final class CardBody extends Component
{
    private const BASE = 'tw:flex-auto tw:p-4';

    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div data-ego-card-body {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</div>
        BLADE;
    }
}
