<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Dải tab — thay `.nav .nav-tabs` + `data-bs-toggle="tab"` + `.tab-pane` của Bootstrap, chạy bằng Alpine.
 *
 * ## Vì sao nhận MẢNG nhãn thay vì đọc từ các `<x-ui.tab-panel>` con
 * Blade dựng component con KHI render slot, nên lúc component cha vẽ dải nút thì nó chưa biết
 * gì về các panel bên trong. Muốn tự suy ra nhãn thì phải dựng thêm cơ chế đăng ký ngược —
 * đúng kiểu over-engineering mà `rules/architecture.md` §3 cấm. Khai thẳng mảng là đủ và đọc rõ:
 *
 *     <x-ui.tabs :tabs="['budget' => 'Ngân sách', 'metric' => 'Chỉ số']">
 *         <x-ui.tab-panel name="budget"> … </x-ui.tab-panel>
 *         <x-ui.tab-panel name="metric"> … </x-ui.tab-panel>
 *     </x-ui.tabs>
 *
 * Panel con đọc biến `tab` từ phạm vi Alpine của cha (Alpine cho scope kế thừa xuống cây con),
 * nên không cần truyền gì thêm.
 *
 * ## Diện mạo
 * KHÔNG chép `.nav-tabs` của Bootstrap (viền dưới + tab nổi lên như thẻ hồ sơ). Chủ dự án đã
 * chốt cho phép theo hệ `x-ui.*`: tab là nút bo tròn, tab đang chọn tô nền chủ đạo — cùng ngôn
 * ngữ với `<x-ui.button>`. Đây là thay đổi diện mạo CÓ CHỦ Ý, không phải quy đổi giữ nguyên.
 */
final class Tabs extends Component
{
    private const STRIP = 'tw:flex tw:flex-wrap tw:gap-2 tw:mb-4';

    private const TAB_BASE = 'tw:inline-flex tw:items-center tw:gap-2 tw:px-4 tw:py-2 tw:rounded-full '
        .'tw:border tw:border-solid tw:text-[0.875rem] tw:font-semibold tw:cursor-pointer '
        .'tw:transition-colors tw:duration-150';

    private const TAB_ACTIVE = 'tw:bg-[#0d6efd] tw:text-[#ffffff] tw:border-[#0d6efd]';

    private const TAB_IDLE = 'tw:bg-[#ffffff] tw:text-[#334155] tw:border-[#dbe3ef] tw:hover:bg-[#f1f5f9]';

    /**
     * @param  array<string, string>  $tabs  khoá = tên panel, giá trị = nhãn hiển thị
     * @param  string|null  $default  panel mở sẵn; bỏ trống thì lấy khoá đầu tiên
     */
    public function __construct(
        public array $tabs,
        public ?string $default = null,
    ) {}

    /** Panel mở sẵn — khoá đầu tiên nếu nơi gọi không chỉ định. */
    public function activeTab(): string
    {
        return $this->default ?? (string) array_key_first($this->tabs);
    }

    public function stripClasses(): string
    {
        return self::STRIP;
    }

    public function tabBase(): string
    {
        return self::TAB_BASE;
    }

    public function tabActive(): string
    {
        return self::TAB_ACTIVE;
    }

    public function tabIdle(): string
    {
        return self::TAB_IDLE;
    }

    public function render(): View
    {
        return view('components.ui.tabs');
    }
}
