<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canh giữ trang sửa công trình (7 nút đã chuyển, 5 nút `-ego` giữ nguyên).
 *
 * ## Cái bẫy tìm ra ở trang này
 * Thuộc tính `{{ $dk ? 'disabled' : '' }}` để TRẦN trong thẻ `<x-ui.button>` làm hỏng
 * bộ phân tích thẻ component của Blade: thẻ không được nhận diện, phần `<?php echo ... ?>`
 * lọt thẳng vào giữa thuộc tính và PHP sinh ra vỡ —
 * `Parse error: syntax error, unexpected token "endif"`.
 * Cùng cú pháp đó chạy tốt trên thẻ HTML thường, nên lỗi chỉ lộ khi đổi sang component.
 * Cách đúng là ràng buộc boolean: `:disabled="$dk"`.
 * Test dưới canh cho TOÀN BỘ cây view, không riêng trang này.
 *
 * ## Vì sao canh riêng các lớp hook JS
 * Bộ lọc dọn class Bootstrap lúc chuyển đã suýt xoá `btnRemoveDevice`/`btnRemovePlan`/
 * `btnRemoveTerm` vì chúng bắt đầu bằng "btn". Mất chúng thì nút xoá dòng im lặng
 * ngừng hoạt động — không lỗi, không cảnh báo.
 */
final class SiteEditButtonsTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/sites/edit.blade.php';

    /** Các lớp JS dùng làm hook phải còn nguyên trên nút. */
    public function test_giu_lop_hook_js(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        foreach (['btnRemoveDevice', 'btnRemovePlan', 'btnRemoveTerm'] as $hook) {
            $this->assertMatchesRegularExpression(
                '/<x-ui\.button\b[^>]*\bclass="[^"]*\b'.$hook.'\b/',
                $source,
                "Nút mất lớp hook JS: {$hook}",
            );
        }
    }

    /** Chỉ còn các nút `-ego` mang class Bootstrap; đó là biến thể riêng của trang. */
    public function test_chi_con_nut_ego(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        preg_match_all('/class="([^"]*\bbtn btn-[^"]*)"/', $source, $m);

        // Đợt btn 2026-09-06 chuyển nốt cả *-ego → phải rỗng (foreach rỗng làm test risky).
        $this->assertSame([], $m[1], 'Còn nút Bootstrap chưa chuyển');
    }

    /** Không dùng !important. */
    public function test_khong_dung_important(): void
    {
        $this->assertKhongLamDungImportant((string) file_get_contents(resource_path(self::VIEW)));
    }

    /**
     * KHÔNG view nào được đặt `{{ }}` trần ở vị trí thuộc tính của thẻ component.
     *
     * Bản canh cho lỗi phân tích nói trên. Quét cả cây view vì cái bẫy này áp cho mọi
     * `<x-...>`, và nó làm vỡ trang ở mức fatal chứ không phải lệch giao diện.
     */
    public function test_khong_co_thuoc_tinh_echo_tran_trong_the_component(): void
    {
        $offenders = [];
        $dir = resource_path('views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            foreach ($this->componentTags($source) as $tag) {
                if ($this->hasBareEcho($tag)) {
                    $offenders[] = str_replace($dir.'/', '', $file->getPathname())
                        .': '.mb_substr((string) preg_replace('/\s+/', ' ', $tag), 0, 110);
                }
            }
        }

        $this->assertSame([], $offenders, "Thuộc tính `{{ }}` trần trong thẻ component sẽ làm vỡ PHP sinh ra:\n".implode("\n", $offenders));
    }

    /** Nút xoá dòng bị khoá đúng như trước khi chuyển: khoá khi chỉ còn một dòng. */
    public function test_render_va_khoa_nut_xoa_dung_nhu_truoc(): void
    {
        $user = $this->userWithRole(Role::Admin->value);
        $now = now();

        DB::table('sites')->insert([
            'id' => 940001, 'name' => 'Cong trinh canh test',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $html = (string) $this->actingAs($user)->get('/cong-trinh/940001/edit')->assertOk()->getContent();

        // Chỉ có một dòng thiết bị / một dòng kế hoạch => nút xoá bị khoá.
        foreach (['btnRemoveDevice', 'btnRemovePlan'] as $hook) {
            $this->assertSame(
                1,
                $this->countDisabled($html, $hook),
                "Nút {$hook} phải bị khoá khi chỉ còn một dòng",
            );
        }

        // Ba dòng điều khoản thanh toán => không nút nào bị khoá.
        $this->assertSame(0, $this->countDisabled($html, 'btnRemoveTerm'));
    }

    /**
     * Đếm nút mang hook đang thực sự có thuộc tính `disabled`.
     *
     * Phải bỏ giá trị `class` trước khi tìm: class mới chứa `tw:disabled:opacity-65`,
     * khớp nhầm và làm phép đo báo lệch giả.
     */
    private function countDisabled(string $html, string $hook): int
    {
        preg_match_all('/<button[^>]*\b'.$hook.'\b[^>]*>/', $html, $m);

        return count(array_filter(
            $m[0],
            static fn (string $tag): bool => (bool) preg_match(
                '/\bdisabled\b/',
                (string) preg_replace('/class="[^"]*"/', '', $tag),
            ),
        ));
    }

    /**
     * Cắt ra từng thẻ `<x-...>`, kết thúc ở `>` NGOÀI dấu nháy.
     *
     * Regex ngây thơ `<x-[^>]*>` cắt cụt thẻ ngay tại `>` của `{{ $item->id }}` và sinh
     * ra hàng loạt báo động giả — đã dính đúng bẫy này khi viết test.
     *
     * @return list<string>
     */
    private function componentTags(string $source): array
    {
        $tags = [];
        $len = strlen($source);
        $i = 0;

        while (($i = strpos($source, '<x-', $i)) !== false) {
            $j = $i + 3;
            $quote = null;

            while ($j < $len) {
                $c = $source[$j];

                if ($quote !== null) {
                    if ($c === $quote) {
                        $quote = null;
                    }
                } elseif ($c === '"' || $c === "'") {
                    $quote = $c;
                } elseif ($c === '>') {
                    break;
                }

                $j++;
            }

            $tags[] = substr($source, $i, $j - $i + 1);
            $i = $j + 1;
        }

        return $tags;
    }

    /** `{{` ở vị trí THUỘC TÍNH (ngoài dấu nháy) — cái làm vỡ bộ phân tích Blade. */
    private function hasBareEcho(string $tag): bool
    {
        $quote = null;

        for ($i = 0, $len = strlen($tag) - 1; $i < $len; $i++) {
            $c = $tag[$i];

            if ($quote !== null) {
                if ($c === $quote) {
                    $quote = null;
                }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif ($c === '{' && $tag[$i + 1] === '{') {
                return true;
            }
        }

        return false;
    }
}
