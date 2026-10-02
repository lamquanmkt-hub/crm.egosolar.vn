<?php

declare(strict_types=1);

namespace App\View\Components\Ui;

use App\Support\Ui\LopTienIch;
use Illuminate\View\Component;

/**
 * Khung thẻ — thay `.card` của Bootstrap.
 *
 * ## Vì sao lớp này CỐ Ý không có nền, viền, bo góc
 * `.card` trong hệ này là MÓC CỦA GIAO DIỆN CHẠY ĐỘNG, không phải một kiểu tĩnh.
 * Đo trên trang thật thấy bốn tầng chồng lên nhau:
 *
 *   1. Bootstrap `.card` — phần cấu trúc (relative, flex, column, min-width 0…)
 *   2. `layouts/app` `.card{border:…!important; border-radius:var(--radius)!important;
 *      background:var(--card)}`
 *   3. lớp riêng của trang, ví dụ `.hr-glass-card{border:…!important; border-radius:26px!important}`
 *   4. `system-branding-runtime` `html body .card{border-radius:var(--ego-theme-radius)!important}`
 *      — bán kính do ADMIN đặt trong cấu hình, đổi lúc chạy
 *
 * Tầng 4 thắng vì `html body .card` (0,1,2) hơn `.hr-glass-card` (0,1,0). Nếu
 * component tự phát `tw:rounded-*` / `tw:bg-*` thì:
 *  - bo góc: vẫn thua (tầng 2 và 4 đều `!important`) → thừa;
 *  - nền: Tailwind nạp SAU `<style>` của layout nên sẽ THẮNG `background:var(--card)`
 *    → đổi giao diện, và cấu hình chủ đề mất tác dụng.
 *
 * Nên thẻ chỉ mang `data-ego-card` để hai luật kia bắt được, cộng đúng phần cấu
 * trúc của Bootstrap. Xem `layouts/app.blade.php` và `partials/system-branding-runtime`.
 */
final class Card extends Component
{
    /**
     * Phần Bootstrap `.card` đóng góp mà không luật nào khác đè.
     *
     * `height: var(--bs-card-height)` bỏ qua: biến không đặt giá trị nên tính ra
     * `auto`, giống hệt khi vắng mặt. `background-clip: border-box` cũng bỏ: đó
     * đã là giá trị khởi đầu của CSS.
     *
     * ⚠️ `tw:text-[#212529]` thì KHÔNG bỏ được, dù thoạt nhìn tưởng thừa. Bootstrap
     * khai `.card{color: var(--bs-body-color)}` với `--bs-body-color: #212529`, tức
     * thẻ ÉP màu chữ, đè màu `#0f172a` mà trang truyền xuống. Bản đầu của component
     * này bỏ qua nó và đo ra 15/38 thẻ lệch màu (kéo theo cả `border-top-color` ở
     * những thẻ dùng `currentColor`). Giữ đúng thứ đang hiển thị.
     *
     * Ghi lại để cân nhắc riêng: đây là chỗ Bootstrap lặng lẽ đè màu chữ của chính
     * hệ thiết kế trong app. Sửa nó là ĐỔI GIAO DIỆN nên không gộp vào đợt quy đổi.
     */
    private const BASE = 'tw:relative tw:flex tw:flex-col tw:min-w-0 '
        .'tw:[word-wrap:break-word] tw:text-[#212529]';

    public function classes(string $classNoiGoi = ''): string
    {
        return LopTienIch::nhuong(self::BASE, $classNoiGoi);
    }

    public function render(): string
    {
        return <<<'BLADE'
        <div data-ego-card {{ $attributes->class($classes($attributes->get('class', ''))) }}>{{ $slot }}</div>
        BLADE;
    }
}
