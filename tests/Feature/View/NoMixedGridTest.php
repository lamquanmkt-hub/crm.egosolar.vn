<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cấm một view vừa dùng lưới Tailwind vừa còn lớp lưới Bootstrap.
 *
 * ## Vì sao — lỗi này đã lọt ra production
 * Lưới là quan hệ CHA-CON: `.tw:row` định kiểu cho `.tw:row > *`
 * (`width:100%`). Nếu hàng đã chuyển mà một cột còn `col-md-auto`, thì luật con
 * ấy đè `.col-md-auto{width:auto}` của Bootstrap — vì app.css nạp sau CDN —
 * nên cột bung rộng hết hàng và rớt xuống dòng.
 *
 * Trang `brands` đã hỏng đúng như vậy: ô tìm kiếm và hai nút "Tìm" / "Reset"
 * mỗi thứ một dòng thay vì thẳng hàng.
 *
 * ## Vì sao bộ chuyển không tự bắt được
 * Nó dùng chính hàm quy đổi để nhận diện lớp lưới. `col-md-auto` và `col-xl`
 * (không kèm số) KHÔNG có trong bảng quy đổi, nên hàm trả null và bộ chuyển coi
 * như đó không phải lớp lưới — vừa không dịch, vừa không chặn.
 *
 * Bài học nằm ở chỗ: **bộ nhận diện phải độc lập với bộ quy đổi.** Test này
 * dùng một biểu thức nhận diện ĐẦY ĐỦ lớp lưới Bootstrap, không phụ thuộc việc
 * dịch được hay không.
 */
final class NoMixedGridTest extends TestCase
{
    /** Nhận diện MỌI lớp lưới Bootstrap, kể cả biến thể `auto` và không kèm số. */
    private const LUOI = '/^(row|row-cols-(\d+|auto)|col|col-(auto|\d{1,2})'
        .'|col-(sm|md|lg|xl|xxl)(-(auto|\d{1,2}))?'
        .'|g[xy]?-[0-5]|offset(-(sm|md|lg|xl|xxl))?-\d{1,2})$/';

    #[Test]
    public function khong_view_nao_tron_hai_he_luoi(): void
    {
        $viPham = [];

        foreach ($this->dsBlade() as $duongDan) {
            $noiDung = (string) file_get_contents($duongDan);

            if (! str_contains($noiDung, 'tw:row')) {
                continue;
            }

            $conLai = [];
            // `(?<![-:\w])` để KHÔNG vơ luôn `x-bind:class="…"` / `:class="…"` của Alpine —
            // trong đó là biểu thức JS, không phải danh sách lớp.
            preg_match_all('/(?<![-:\\w])class="([^"]*)"/', $noiDung, $khop);

            foreach ($khop[1] as $danhSach) {
                foreach (preg_split('/\s+/', trim($danhSach)) ?: [] as $lop) {
                    if ($lop !== '' && preg_match(self::LUOI, $lop) === 1) {
                        $conLai[$lop] = true;
                    }
                }
            }

            if ($conLai !== []) {
                $viPham[] = str_replace(base_path().'/', '', $duongDan)
                    .' -> '.implode(' ', array_keys($conLai));
            }
        }

        $this->assertSame([], $viPham, sprintf(
            "%d view vừa dùng tw:row vừa còn lớp lưới Bootstrap:\n%s\n\n".
            'Lưới là quan hệ cha-con nên phải chuyển CẢ HÀNG một lượt. Thiếu một cột '.
            'là `.tw:row > *{width:100%%}` đè lớp Bootstrap còn sót và cột rớt dòng.',
            count($viPham),
            implode("\n", $viPham)
        ));
    }

    /** Mỗi lớp lưới Bootstrap đang dùng đều phải có utility tương ứng trong app.css. */
    #[Test]
    public function moi_lop_luoi_deu_co_utility_tuong_ung(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        foreach (['row', 'col12', 'col12-auto', 'col12-1', 'col12-12', 'g-0', 'g-3', 'gx-3', 'gy-3'] as $ten) {
            $this->assertMatchesRegularExpression(
                '/@utility\s+'.preg_quote($ten, '/').'\s*\{/',
                $css,
                "app.css thiếu @utility $ten — chuyển lưới sẽ tạo ra hàng lai."
            );
        }
    }
}
