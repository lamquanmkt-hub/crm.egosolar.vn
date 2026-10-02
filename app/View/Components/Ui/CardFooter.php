<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Chân thẻ — thay `.card-footer` của Bootstrap. Đối xứng với CardHeader:
 * viền TRÊN thay vì dưới, bo góc DƯỚI 5px khi là con cuối.
 */
final class CardFooter extends Component
{
    private const BASE = 'tw:py-2 tw:px-4 tw:bg-[rgba(33,37,41,0.03)] '
        .'tw:border-t tw:[border-top-style:solid] tw:border-t-[rgba(0,0,0,0.175)] tw:last:rounded-b-[5px]';

    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div data-ego-card-footer {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</div>
        BLADE;
    }
}
