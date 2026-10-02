<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Bảng — thay `.table` / `.table-sm` / `.table-hover` của Bootstrap.
 *
 * ## Con số lấy từ đâu
 * ĐO trên bootstrap@5.3.3 đang chạy (không chép tài liệu): bảng `width:100%`, `margin-bottom:16px`,
 * `vertical-align:top`, `border-color:#dee2e6`; ô `padding:8px`, `color:#000`, nền `#fff`,
 * `border-bottom:1px solid #dee2e6`; `thead` `vertical-align:bottom`; bản `sm` ô `padding:4px`.
 *
 * ## Vì sao phải dùng BIẾN CSS chứ không lồng class
 * Bootstrap cho `.table-light` đổi nền/viền của ô bằng cách gán lại biến `--bs-table-bg` /
 * `--bs-table-border-color`; ô thì đọc `var(...)`. Nếu ở đây làm kiểu "thead có class riêng đặt
 * nền" thì THUA, vì luật của bảng `.X > :not(caption) > * > *` có độ đặc hiệu (0,1,1) còn luật
 * của thead `.Y > *` chỉ (0,1,0) — đầu bảng sẽ mất nền xám mà không báo gì. Nên giữ đúng cơ chế
 * biến: bảng đọc `var(--ego-table-bg)`, `<x-ui.table-head>` chỉ việc gán lại biến đó.
 *
 * ## Bóng đổ trong suốt ở ô
 * Bootstrap luôn đặt `box-shadow: inset 0 0 0 9999px <màu>` cho mọi ô (mặc định trong suốt) để
 * hover/striped chỉ cần đổi màu. Giữ nguyên cách đó: rẻ, và computed style khớp bản cũ.
 */
final class Table extends Component
{
    private const BASE = 'tw:w-full tw:mb-4 tw:align-top tw:border-[#dee2e6] '
        // Ô: đúng bộ chọn của Bootstrap `> :not(caption) > * > *` nên th/td/tfoot đều dính.
        .'tw:[&>:not(caption)>*>*]:[color:var(--ego-table-color,#000000)] '
        .'tw:[&>:not(caption)>*>*]:[background-color:var(--ego-table-bg,#ffffff)] '
        .'tw:[&>:not(caption)>*>*]:[border-bottom-width:1px] '
        .'tw:[&>:not(caption)>*>*]:[border-bottom-style:solid] '
        .'tw:[&>:not(caption)>*>*]:[border-bottom-color:var(--ego-table-border,#dee2e6)] '
        .'tw:[&>:not(caption)>*>*]:[box-shadow:inset_0_0_0_9999px_var(--ego-table-accent,transparent)] '
        .'tw:[&>thead]:align-bottom tw:[&>tbody]:[vertical-align:inherit]';

    /** Đệm ô: mặc định 8px, bản `sm` 4px (đo từ `.table` và `.table-sm`). */
    private const PADDING = [
        'md' => 'tw:[&>:not(caption)>*>*]:[padding:8px]',
        'sm' => 'tw:[&>:not(caption)>*>*]:[padding:4px]',
    ];

    /** `.table-hover`: đổi màu nền ô bằng chính biến accent, giống hệt Bootstrap. */
    private const HOVER = 'tw:[&>tbody>tr:hover>*]:[--ego-table-accent:rgba(0,0,0,0.075)]';

    public function __construct(
        /** sm | md */
        public string $size = 'md',
        public bool $hover = false,
    ) {}

    public function classes(string $classNoiGoi = ''): string
    {
        $nen = self::BASE.' '.(self::PADDING[$this->size] ?? self::PADDING['md']);

        if ($this->hover) {
            $nen .= ' '.self::HOVER;
        }

        return LopTienIch::nhuong($nen, $classNoiGoi);
    }

    /**
     * `data-ego-table` mang luôn CỠ (`sm`/`md`) chứ không để trống: nó vừa là móc cho
     * `main.js::autoWrapTables()`, vừa cho phép test và người debug trỏ đúng một bảng mà không
     * phải viết selector theo chuỗi utility dài (`[class~="tw:[&>...]:[padding:8px]"]`).
     */
    public function render(): string
    {
        return <<<'BLADE'
        <table data-ego-table="{{ $size }}" {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</table>
        BLADE;
    }
}
