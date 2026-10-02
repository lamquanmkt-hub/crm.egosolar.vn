<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Một panel trong `<x-ui.tabs>` — thay `.tab-pane` của Bootstrap.
 *
 * Đọc biến `tab` từ phạm vi Alpine của `<x-ui.tabs>` bao ngoài; đặt ngoài dải tab là panel
 * không bao giờ hiện (và Alpine sẽ báo `tab is not defined` trong console).
 */
final class TabPanel extends Component
{
    private const BASE = '';

    public function __construct(
        /** Phải trùng KHOÁ trong mảng `:tabs` của `<x-ui.tabs>`. */
        public string $name,
    ) {}

    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div
            role="tabpanel"
            x-show="tab === @js($name)"
            x-cloak
            {{ $attributes->class($classes($attributes->get('class', ''))) }}
        >{{ $slot }}</div>
        BLADE;
    }
}
