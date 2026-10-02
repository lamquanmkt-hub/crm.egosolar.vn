<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Đầu bảng — thay `<thead class="table-light">` của Bootstrap.
 *
 * Chỉ gán lại ba biến mà `<x-ui.table>` đọc; không tự đặt nền/màu/viền lên ô, vì luật ô của bảng
 * có độ đặc hiệu cao hơn và sẽ thắng (xem PHPDoc của `Ui\Table`).
 *
 * Số đo từ `.table-light` của bootstrap@5.3.3: nền `#f8f9fa`, chữ `#000`,
 * viền dưới `rgb(198,199,200)`.
 */
final class TableHead extends Component
{
    /**
     * Ngoài ba biến, PHẢI đặt cả `color` và `border-color` lên chính thẻ `<thead>`.
     *
     * `.table-light` của Bootstrap làm đúng hai việc đó, và reboot khai
     * `tbody,td,tfoot,th,thead,tr { border-color: inherit }` — nên màu viền của ô đi lên từ
     * thead chứ không phải từ bảng. Bản đầu của lớp này chỉ đặt biến và đo ra 64 ô lệch
     * (color + border-*-color trên thead/tr/th). Đều là cạnh dày 0 nên không nhìn thấy, nhưng
     * giữ đúng thì bản đo mới sạch và lần sau đổi viền sẽ không ra kết quả bất ngờ.
     */
    private const BASE = 'tw:[--ego-table-bg:#f8f9fa] tw:[--ego-table-color:#000000] '
        .'tw:[--ego-table-border:rgb(198,199,200)] '
        .'tw:text-[#000000] tw:border-[rgb(198,199,200)]';

    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <thead data-ego-table-head {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</thead>
        BLADE;
    }
}
