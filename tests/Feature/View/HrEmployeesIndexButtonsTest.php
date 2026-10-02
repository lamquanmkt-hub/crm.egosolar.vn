<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Canh giữ trang danh sách nhân viên (12 nút đã chuyển).
 *
 * ## Trang này chuyển KHÁC các trang trước — và vì sao
 * CSS của chính trang bám vào `.btn`, mang ý đồ thiết kế áp cho MỌI nút:
 *   .hr-modern-page .btn        { border-radius:14px; font-weight:700 }
 *   .hr-modern-page .btn-primary{ background: gradient HR; border:0 none transparent }
 *   .hr-actions .btn            { flex:1 }          <- BỐ CỤC, không phải trang trí
 * Nhân bản ba luật này thành utility trên 12 nút là lặp ý đồ 12 lần. Thay vào đó
 * ba selector được trỏ sang `.hr-btn` / `.hr-btn-primary`, và mỗi nút mang thêm
 * một class. Đặc hiệu 0-2-0 của chúng thắng utility 0-1-0 một cách tất định, nên
 * không phụ thuộc thứ tự class trong file CSS sinh ra — thứ tự đó Tailwind quyết,
 * không phải thứ tự viết trong `class=""`.
 *
 * Hệ quả: nút nào thiếu `hr-btn` sẽ IM LẶNG mất bo góc 14px, mất chữ đậm, và nếu
 * nằm trong `.hr-actions` thì mất `flex:1` -> hàng nút co lại. Test dưới canh đúng
 * chỗ đó.
 *
 * ## Hai bẫy đã gặp khi đo
 * - `.px-3` của Bootstrap là 1rem, `tw:px-3` là .75rem. Bốn nút mang `px-3` phải
 *   dùng `size="none"` + `tw:px-4` mới giữ đúng 16px.
 * - `border:none` đặt border-color về `currentColor` (trắng) thay vì giữ trong suốt.
 *   Không vẽ ra vì rộng 0, nhưng làm phép so lệch 4 phần tử; khai đủ ba thành phần
 *   `0 none transparent` mới khớp hệt giá trị tính toán của bản cũ.
 */
final class HrEmployeesIndexButtonsTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/hr/employees/index.blade.php';

    /** Card nhân sự tách ra partial 2026-09-07 (3 khối giống nhau → 1); 2 nút Xem/Sửa nằm ở đây. */
    private const CARD_PARTIAL = 'views/hr/employees/partials/person-card.blade.php';

    private const URL = '/nhan-su/employees';

    /** Ba luật CSS phải trỏ vào class mới; còn bám `.btn` là nút đã chuyển không nhận được. */
    public function test_css_trang_tro_vao_class_moi(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        foreach ([
            '.hr-modern-page .hr-btn{border-radius:14px;font-weight:700;}',
            '.hr-actions .hr-btn{flex:1;}',
        ] as $rule) {
            $this->assertStringContainsString($rule, $source, "Thiếu luật: {$rule}");
        }

        $this->assertStringContainsString('.hr-modern-page .hr-btn-primary{', $source);

        // Không còn selector nào bám `.btn` của Bootstrap.
        $this->assertDoesNotMatchRegularExpression('/\.hr-(modern-page|actions) \.btn[{ ]/', $source);
    }

    /**
     * `border` phải khai đủ ba thành phần.
     *
     * `border:none` giữ nguyên bề rộng 0 nhưng đổi border-color thành currentColor,
     * làm giá trị tính toán lệch khỏi bản Bootstrap cũ.
     */
    public function test_border_khai_du_ba_thanh_phan(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        // Chỉ soi luật của nút: `.hr-hero` cũng dùng `border:none` nhưng không liên quan.
        preg_match('/\.hr-modern-page \.hr-btn-primary\{([^}]*)\}/', $source, $m);

        $this->assertNotEmpty($m, 'Không tìm thấy luật .hr-modern-page .hr-btn-primary');
        $this->assertStringContainsString('border:0 none transparent', $m[1]);
    }

    /** Mọi nút trong trang phải mang `hr-btn`, nếu không sẽ mất bo góc/chữ đậm/flex. */
    public function test_moi_nut_deu_mang_hr_btn(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW)).(string) file_get_contents(resource_path(self::CARD_PARTIAL));

        preg_match_all('/<x-ui\.button\b(?:[^>"]|"[^"]*")*>/', $source, $m);

        // 12 nút cũ = 6 ở trang + 2 trong card × 3 khối; card giờ là một partial nên còn 6 + 2.
        $this->assertCount(8, $m[0], 'Số nút đã chuyển thay đổi — đo lại trước khi sửa test.');

        foreach ($m[0] as $tag) {
            $this->assertMatchesRegularExpression(
                '/\bclass="[^"]*\bhr-btn\b/',
                $tag,
                'Nút thiếu class hr-btn: '.mb_substr((string) preg_replace('/\s+/', ' ', $tag), 0, 90),
            );
        }
    }

    /** Nút Lọc dựa vào type submit ngầm định của HTML; component mặc định là button. */
    public function test_nut_loc_van_gui_form(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertMatchesRegularExpression(
            '/<x-ui\.button[^>]*\btype="submit"[^>]*\bclass="[^"]*tw:h-full/',
            $source,
            'Nút Lọc phải khai type="submit", nếu không form lọc im lặng ngừng gửi.',
        );
    }

    /** Không còn class nút của Bootstrap. */
    public function test_khong_con_class_bootstrap(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*(?<![\w-])btn(?![\w-])[^"]*(?<![\w-])btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)(?![\w-])/',
            (string) file_get_contents(resource_path(self::VIEW)).(string) file_get_contents(resource_path(self::CARD_PARTIAL)),
        );
    }

    /** Không dùng !important. */
    public function test_khong_dung_important(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW)).(string) file_get_contents(resource_path(self::CARD_PARTIAL));

        preg_match_all('/<x-ui\.button\b(?:[^>"]|"[^"]*")*>/', $source, $m);

        foreach ($m[0] as $tag) {
            $this->assertKhongLamDungImportant($tag);
        }
    }

    /** Trang render được và nút vẫn là thẻ đúng loại. */
    public function test_render_va_giu_dung_the(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        $html = (string) $this->actingAs($user)->get(self::URL)->assertOk()->getContent();

        // Nút Lọc phải là <button type="submit">, các nút điều hướng phải là <a href>.
        $this->assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*>\s*<i class="bi bi-search/', $html);
        $this->assertMatchesRegularExpression('/<a href="[^"]*\/nhan-su\/employees\/create"[^>]*hr-btn-primary/', $html);
    }
}
