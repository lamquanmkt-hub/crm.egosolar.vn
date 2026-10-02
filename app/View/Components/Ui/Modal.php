<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Hộp thoại — thay `.modal` + `data-bs-toggle="modal"` của Bootstrap, chạy bằng Alpine.
 *
 * Mở từ bất cứ đâu trong trang bằng sự kiện có tên (cùng cơ chế `<x-ui.disclosure>`):
 *
 *     <x-ui.button x-on:click="$dispatch('open-modal', 'adsModal')">+ Thêm</x-ui.button>
 *     <x-ui.modal name="adsModal" title="Thêm dữ liệu" subtitle="…"> … </x-ui.modal>
 *
 * ## Khác biệt ĐÃ BIẾT so với bản Bootstrap, ghi ra để không tưởng là lỗi
 * 1. **Không bẫy tiêu điểm.** `bootstrap-compat.js` có `FocusTrap` giữ Tab quẩn trong hộp
 *    thoại. Alpine muốn làm vậy cần plugin `@alpinejs/focus` — chưa cài, và cài thêm gói chỉ
 *    vì một trang là vi phạm YAGNI. Hệ quả thật: bấm Tab đủ lâu thì tiêu điểm ra được nền sau.
 *    Cần thì thêm plugin rồi gắn `x-trap="open"` vào đúng một chỗ trong tệp này.
 * 2. **Khoá cuộn nền đơn giản hơn.** Bootstrap đo bề rộng thanh cuộn rồi bù `padding-right`
 *    cho `body` và các phần tử `position:fixed` để trang không giật ngang. Ở đây chỉ đặt
 *    `overflow:hidden`. Trên macOS (thanh cuộn nổi) không thấy khác biệt; trên Windows có thể
 *    giật ~15px khi mở. Chấp nhận, đổi được sau mà không đụng nơi gọi.
 * 3. **Mỗi lúc chỉ nên mở một hộp.** Đóng hộp này sẽ trả `overflow` của body về rỗng kể cả khi
 *    còn hộp khác đang mở. Repo chưa có trang nào mở hai hộp chồng nhau.
 *
 * ## Diện mạo
 * Theo hệ `x-ui.*` (chủ dự án đã chốt cho phép đổi), không chép `.modal-dialog` của Bootstrap.
 */
final class Modal extends Component
{
    /** Lớp phủ + hộp căn giữa. z-index 1055 giữ đúng thang của Bootstrap để không chui dưới sidebar. */
    private const BACKDROP = 'tw:fixed tw:inset-0 tw:z-[1055] tw:flex tw:items-center tw:justify-center tw:p-4';

    private const DIALOG = 'tw:relative tw:flex tw:flex-col tw:w-full tw:max-h-[calc(100vh-3.5rem)] '
        .'tw:bg-[#ffffff] tw:rounded-[.5rem] tw:shadow-[0_8px_16px_0_rgba(0,0,0,0.15)] tw:overflow-hidden';

    private const HEADER = 'tw:flex tw:items-start tw:justify-between tw:gap-4 tw:p-4 '
        .'tw:border-b tw:[border-bottom-style:solid] tw:border-b-[rgba(0,0,0,0.175)]';

    private const BODY = 'tw:p-4 tw:overflow-y-auto';

    /** Thang bề rộng của Bootstrap: modal-sm 300, mặc định 500, modal-lg 800, modal-xl 1140. */
    private const WIDTHS = [
        'sm' => 'tw:max-w-[300px]',
        'md' => 'tw:max-w-[500px]',
        'lg' => 'tw:max-w-[800px]',
        'xl' => 'tw:max-w-[1140px]',
    ];

    public function __construct(
        /** Tên để nơi khác gọi `$dispatch('open-modal', '<tên>')`. */
        public string $name,
        public string $title = '',
        public string $subtitle = '',
        /** sm | md | lg | xl */
        public string $size = 'lg',
    ) {}

    public function backdropClasses(): string
    {
        return self::BACKDROP;
    }

    public function dialogClasses(): string
    {
        return self::DIALOG.' '.(self::WIDTHS[$this->size] ?? self::WIDTHS['lg']);
    }

    public function headerClasses(): string
    {
        return self::HEADER;
    }

    public function bodyClasses(): string
    {
        return self::BODY;
    }

    public function render(): View
    {
        return view('components.ui.modal');
    }
}
