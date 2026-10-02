<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use App\View\Components\Ui\Button;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Canh giữ lượt chuyển gộp 3 trang (41 nút): kpi-payroll/settings, finance/budget,
 * marketing/budget.
 *
 * Ba trang này KHÔNG có quy tắc CSS nào bám `.btn`, nên nút lấy thẳng kích thước từ
 * `.btn` của Bootstrap — dùng được cỡ mặc định của component, đơn giản hơn hẳn 4 trang
 * trước.
 */
final class BatchButtonsTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEWS = [
        'views/marketing/kpi_payroll/settings.blade.php',
        'views/finance/budget.blade.php',
        'views/marketing/budget.blade.php',
    ];

    /** Không còn class nút Bootstrap ở cả ba trang. */
    public function test_khong_con_class_nut_bootstrap(): void
    {
        foreach (self::VIEWS as $view) {
            $source = (string) file_get_contents(resource_path($view));

            $this->assertDoesNotMatchRegularExpression(
                '/class="[^"]*\bbtn btn-/',
                $source,
                "Còn nút Bootstrap chưa chuyển trong {$view}"
            );
        }
    }

    /** Không dùng !important. */
    public function test_khong_dung_important(): void
    {
        foreach (self::VIEWS as $view) {
            $this->assertKhongLamDungImportant((string) file_get_contents(resource_path($view)));
        }
    }

    /**
     * `rounded-pill` phải quy đổi thành `50rem`, KHÔNG phải `tw:rounded-full`.
     *
     * Bootstrap `.rounded-pill` = `border-radius:50rem` = 800px; `tw:rounded-full` là
     * `calc(infinity*1px)` = 33.554.432px. Với nút hẹp trông giống nhau, nhưng đây là
     * giá trị KHÁC và phép so đã bắt được (800px -> 3.35544e+07px).
     */
    public function test_rounded_pill_dung_50rem(): void
    {
        $source = (string) file_get_contents(resource_path('views/finance/budget.blade.php'));

        $this->assertStringContainsString('tw:rounded-[50rem]', $source);
        $this->assertStringNotContainsString('tw:rounded-full', $source);
    }

    /**
     * Component phải phủ hết biến thể đang được dùng trong view.
     *
     * `outline-success` từng thiếu và làm một nút xanh lá hoá xám (rơi về biến thể mặc
     * định). Phép so trang bắt được, nhưng chỉ vì trang đó tình cờ dùng nó — nên chốt
     * bằng test quét toàn bộ view.
     */
    public function test_component_phu_het_bien_the_dang_dung(): void
    {
        $bs = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'link'];
        $known = array_merge($bs, array_map(static fn (string $v): string => 'outline-'.$v, $bs));

        $used = [];
        foreach (glob(resource_path('views').'/**/*.blade.php', GLOB_BRACE) ?: [] as $f) {
            $used[] = [];
        }

        $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($dir as $file) {
            if (! str_ends_with((string) $file, '.blade.php')) {
                continue;
            }
            $s = (string) file_get_contents((string) $file);
            preg_match_all('/variant="([a-z-]+)"/', $s, $m1);
            preg_match_all('/class="[^"]*\bbtn btn-([a-z-]+)/', $s, $m2);
            foreach (array_merge($m1[1], $m2[1]) as $v) {
                if (in_array($v, $known, true)) {
                    $used[$v] = true;
                }
            }
        }

        $missing = array_diff(array_keys(array_filter($used, 'is_bool')), Button::variants());

        $this->assertSame([], array_values($missing), 'Component thiếu biến thể đang được dùng: '.implode(', ', $missing));
    }

    /** Ba trang đều render được. */
    public function test_ba_trang_render_duoc(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        foreach (['/marketing/kpi-payroll/settings', '/finance/budget', '/marketing/budget'] as $uri) {
            $this->actingAs($user)->get($uri)->assertOk();
        }
    }
}
