<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Hộp thông báo — thay `.alert` của Bootstrap.
 *
 * ## Con số lấy từ đâu
 * Không chép từ tài liệu Bootstrap mà ĐO trên trình duyệt: dựng đủ 8 biến thể
 * bằng lớp Bootstrap thật rồi đọc computed style. Bootstrap 5.3 dựng alert qua
 * chuỗi biến `--bs-<biến thể>-text-emphasis` / `bg-subtle` / `border-subtle`,
 * đọc trong tệp CSS thì chỉ thấy tên biến chứ không ra màu cuối.
 *
 * Đệm 1rem, lề dưới 1rem, viền 1px, bo .375rem — giống nhau ở mọi biến thể;
 * chỉ ba màu là khác. Bản đóng được thì đệm phải 3rem để chừa chỗ nút đóng.
 *
 * ## Tương phản
 * Đã đo cả 8: thấp nhất là `dark` 5,47:1, cao nhất `secondary` 10,51:1 — đều
 * vượt WCAG AA. Không cần chỉnh màu, chỉ chuyển hệ.
 */
class Alert extends Component
{
    /** Khung chung, giống nhau ở mọi biến thể. */
    private const BASE = 'tw:relative tw:p-4 tw:mb-4 tw:border tw:border-solid tw:rounded-[.375rem]';

    /**
     * Đệm phải 3rem khi có nút đóng, để chữ không chui xuống dưới nút.
     *
     * Kèm transition mờ dần thay cho `.fade` của Bootstrap (`transition:opacity
     * .15s linear`): `resources/js/bs-compat/alert.js` quyết định có chờ hay không
     * bằng thời lượng transition ĐO ĐƯỢC, không còn dò lớp `.fade`.
     */
    private const DISMISSIBLE = 'tw:pr-12 tw:transition-opacity tw:duration-150 tw:ease-linear';

    /**
     * Màu chữ / nền / viền — đo từ Bootstrap 5.3.3.
     *
     * ⚠️ PHẢI là chuỗi lớp TĨNH, không ghép từ biến. Bản đầu viết
     * `"tw:bg-[$nen]"`; Tailwind quét mã nguồn theo văn bản nên chỉ thấy
     * `tw:bg-[` và KHÔNG sinh ra lớp nào — component render ra class không tồn
     * tại, hộp cảnh báo mất sạch màu. Kiểm bằng cách grep chính chuỗi lớp trong
     * `public/build/assets/app-*.css` sau khi build. `Button` cũng viết tĩnh vì
     * đúng lý do này.
     *
     * @var array<string, string>
     */
    private const VARIANTS = [
        'primary' => 'tw:text-[#052c65] tw:bg-[#cfe2ff] tw:border-[#9ec5fe]',
        'secondary' => 'tw:text-[#2b2f32] tw:bg-[#e2e3e5] tw:border-[#c4c8cb]',
        'success' => 'tw:text-[#0a3622] tw:bg-[#d1e7dd] tw:border-[#a3cfbb]',
        'danger' => 'tw:text-[#58151c] tw:bg-[#f8d7da] tw:border-[#f1aeb5]',
        'warning' => 'tw:text-[#664d03] tw:bg-[#fff3cd] tw:border-[#ffe69c]',
        'info' => 'tw:text-[#055160] tw:bg-[#cff4fc] tw:border-[#9eeaf9]',
        'light' => 'tw:text-[#495057] tw:bg-[#fcfcfd] tw:border-[#e9ecef]',
        'dark' => 'tw:text-[#495057] tw:bg-[#ced4da] tw:border-[#adb5bd]',
    ];

    public function __construct(
        public string $variant = 'info',
        public bool $dismissible = false,
    ) {}

    /**
     * @param  string  $classNoiGoi  Lớp do nơi gọi truyền vào, để nhường thuộc tính trùng.
     */
    public function classes(string $classNoiGoi = ''): string
    {
        $ra = self::BASE.' '.(self::VARIANTS[$this->variant] ?? self::VARIANTS['info']);

        if ($this->dismissible) {
            $ra .= ' '.self::DISMISSIBLE;
        }

        return LopTienIch::nhuong($ra, $classNoiGoi);
    }

    public function render(): View
    {
        return view('components.ui.alert');
    }
}
