<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canh giữ ba trang danh sách chuyển cùng một lượt: đề xuất, chấm công, tăng ca.
 *
 * ## Ba kiểu quan hệ với CSS, ba cách xử lý khác nhau
 * - `proposals/index`: `.btn-pill` và `.proposal-detail-btn` khai font-size nhưng
 *   KHÔNG khai line-height (trước đây 1.5 đến từ `.btn`). Phải bù `tw:leading-[1.5]`.
 * - `hr/attendance/index`: có luật bám `.btn` (`.attendance-stats-page .btn`), phải
 *   trỏ sang `.att-btn`. Ngoài ra nút Lọc lấy nền/màu từ `.attendance-filter-btn`
 *   nên dùng `variant="none"`.
 * - `hr/overtime/index`: KHÔNG có luật nào bám `.btn`; các lớp `rounded-pill`,
 *   `rounded-4`, `px-4` là utility Bootstrap và mang `!important` nên vẫn thắng
 *   utility Tailwind. Chỉ cần đổi thẻ, không cần bù gì.
 *
 * ## Trạng thái nhấn của nút Lọc chấm công
 * Bootstrap đặt màu chữ lúc `:active` qua `:not(.btn-check)+.btn:active` — đặc hiệu
 * 0-3-0, thắng cả `.attendance-filter-btn:hover`. Bỏ `.btn` là chữ giữ nguyên trắng
 * lúc nhấn thay vì chuyển #212529. Không lộ ra ở ảnh tĩnh, chỉ lộ khi đo trạng thái
 * bằng sự kiện chuột thật. Trang nay tự khai lại ba trạng thái đó.
 */
final class HrAndProposalListButtonsTest extends TestCase
{
    use DatabaseTransactions;

    /** @return list<string> */
    private function buttonTags(string $view): array
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        preg_match_all('/<x-ui\.button\b(?:[^>"]|"[^"]*")*>/s', $source, $m);

        return $m[0];
    }

    /** @return array<string, string> */
    public static function views(): array
    {
        return [
            'đề xuất' => ['proposals/index.blade.php', 6],
            'chấm công' => ['hr/attendance/index.blade.php', 6],
            'tăng ca' => ['hr/overtime/index.blade.php', 5],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function test_khong_con_class_nut_bootstrap(string $view, int $count): void
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));

        $this->assertDoesNotMatchRegularExpression('/class="[^"]*(?<![\w-])btn(?![\w-])[^"]*(?<![\w-])btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)(?![\w-])/', $source);
        $this->assertCount($count, $this->buttonTags($view));
    }

    /** Lớp của trang khai font-size mà không khai line-height -> phải bù. */
    public function test_de_xuat_bu_line_height(): void
    {
        foreach ($this->buttonTags('proposals/index.blade.php') as $tag) {
            $this->assertStringContainsString('size="none"', $tag);
            $this->assertStringContainsString('tw:leading-[1.5]', $tag,
                'Thiếu bù line-height: '.mb_substr((string) preg_replace('/\s+/', ' ', $tag), 0, 80));
        }
    }

    /** Luật bám `.btn` phải trỏ sang `.att-btn`, và mọi nút phải mang lớp đó. */
    public function test_cham_cong_tro_css_sang_att_btn(): void
    {
        $source = (string) file_get_contents(resource_path('views/hr/attendance/index.blade.php'));

        $this->assertStringContainsString('.attendance-stats-page .att-btn{', $source);
        $this->assertStringNotContainsString('.attendance-stats-page .btn{', $source);

        foreach ($this->buttonTags('hr/attendance/index.blade.php') as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\batt-btn\b/', $tag);
            $this->assertStringContainsString('tw:py-[6px]', $tag);
            $this->assertStringContainsString('tw:leading-[1.5]', $tag);
        }
    }

    /**
     * Nút Lọc chấm công phải giữ ba trạng thái mà `.btn` từng lo.
     *
     * Thiếu chúng thì chữ không đổi màu lúc nhấn — ảnh tĩnh vẫn y hệt nên chỉ đo
     * bằng sự kiện thật mới thấy.
     */
    public function test_cham_cong_giu_trang_thai_nut_loc(): void
    {
        $source = (string) file_get_contents(resource_path('views/hr/attendance/index.blade.php'));

        $this->assertMatchesRegularExpression(
            '/\.attendance-filter-btn:active,\s*\.attendance-filter-btn:focus-visible\{\s*color:#212529;/',
            $source,
            'Mất màu chữ khi nhấn/focus mà `.btn` từng lo',
        );
        $this->assertMatchesRegularExpression('/\.attendance-filter-btn:active\{\s*border-color:#212529;/', $source);
    }

    /** Nút Lọc dùng variant="none" vì màu do CSS trang lo, và phải là nút gửi form. */
    public function test_cham_cong_nut_loc_la_submit_variant_none(): void
    {
        $loc = array_values(array_filter(
            $this->buttonTags('hr/attendance/index.blade.php'),
            static fn (string $t): bool => str_contains($t, 'attendance-filter-btn'),
        ));

        $this->assertCount(1, $loc);
        $this->assertStringContainsString('variant="none"', $loc[0]);
        $this->assertStringContainsString('type="submit"', $loc[0]);
    }

    /** Trang tăng ca: ba nút gửi form vẫn khai type; bán kính nay là utility `tw:` (đợt 2026-10-01). */
    public function test_tang_ca_giu_utility_va_submit(): void
    {
        $tags = $this->buttonTags('hr/overtime/index.blade.php');
        $submits = 0;

        foreach ($tags as $tag) {
            if (str_contains($tag, 'href=')) {
                continue;
            }
            $this->assertStringContainsString('type="submit"', $tag);
            $submits++;
        }

        $this->assertSame(3, $submits);
        // Trước 2026-10-01 là `rounded-pill tw:px-6` (Bootstrap). Nay quy đổi theo GIÁ TRỊ:
        // `rounded-pill` = var(--bs-border-radius-pill) = 50rem; `!` vì <x-ui.button> không nhường.
        $this->assertStringContainsString('tw:rounded-[50rem]! tw:px-6', implode(' ', $tags));
    }

    /** Ba trang đều render được. */
    public function test_render_ba_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        DB::table('proposals')->insert([
            'id' => 950020, 'user_id' => $user->id, 'title' => 'De xuat canh test',
            'proposal_type' => 'purchase', 'priority' => 'normal',
            'department_name' => 'Ky thuat', 'amount' => 1000000,
            'needed_date' => '2026-09-20', 'content' => 'Noi dung',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach (['/de-xuat', '/nhan-su/cham-cong', '/nhan-su/tang-ca'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}
