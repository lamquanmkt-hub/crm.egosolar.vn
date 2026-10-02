<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Canh giữ 5 trang biểu mẫu chuyển cùng lượt: hồ sơ, báo cáo tài chính, sửa công
 * việc, thêm/sửa danh mục.
 *
 * ## Luật `.btn` NẰM TRONG @media — bẫy khó thấy nhất của đợt này
 * `tasks/edit` có `.te-actions .btn{flex:1}` đặt trong `@media(max-width:700px)`.
 * Nó là luật BỐ CỤC và chỉ có hiệu lực ở khổ hẹp, nên đo ở một khổ rộng duy nhất
 * sẽ không bao giờ thấy mất mát. Đây là lý do phép đo chạy 4 khổ, trong đó có 390px.
 *
 * ## Ba mức phụ thuộc CSS, ba cách xử lý
 * - `users/profile-edit`: có luật bám `.btn` -> trỏ sang `.pf-btn`/`.pf-btn-primary`.
 * - `tasks/edit`: `.te-btn` khai font-size 11px mà không khai line-height (trước
 *   16.5px = 1.5 x 11 đến từ `.btn`) -> `size="none"` + `tw:leading-[1.5]`.
 * - `finance/reports`, `product-categories/*`: không luật nào bám `.btn`; utility
 *   Bootstrap (`rounded-pill`, `px-4`, `fw-bold`) mang `!important` nên vẫn thắng
 *   utility Tailwind. Chỉ đổi thẻ.
 */
final class FormPagesButtonsTest extends TestCase
{
    use DatabaseTransactions;

    /** @return list<string> */
    private function buttonTags(string $view): array
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        preg_match_all('/<x-ui\.button\b(?:[^>"]|"[^"]*")*>/s', $source, $m);

        return $m[0];
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function views(): array
    {
        return [
            'hồ sơ' => ['users/profile-edit.blade.php', 4],
            'báo cáo tài chính' => ['finance/reports.blade.php', 5],
            'sửa công việc' => ['tasks/edit.blade.php', 3],
            'sửa danh mục' => ['product-categories/edit.blade.php', 3],
            'thêm danh mục' => ['product-categories/create.blade.php', 3],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function test_khong_con_class_nut_bootstrap(string $view, int $count): void
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));

        $this->assertDoesNotMatchRegularExpression('/class="[^"]*(?<![\w-])btn(?![\w-])[^"]*(?<![\w-])btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)(?![\w-])/', $source);
        $this->assertCount($count, $this->buttonTags($view));
    }

    /** Luật bám `.btn` của trang hồ sơ phải trỏ sang `.pf-btn`, và mọi nút mang lớp đó. */
    public function test_ho_so_tro_css_sang_pf_btn(): void
    {
        $source = (string) file_get_contents(resource_path('views/users/profile-edit.blade.php'));

        $this->assertStringContainsString('.profile-modern-page .pf-btn{', $source);
        $this->assertStringContainsString('.profile-modern-page .pf-btn-primary{', $source);
        $this->assertDoesNotMatchRegularExpression('/\.profile-modern-page \.btn[-{ ]/', $source);

        // `border:none` đặt border-color về currentColor -> lệch giá trị tính toán.
        preg_match('/\.profile-modern-page \.pf-btn-primary\{([^}]*)\}/', $source, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('border:0 none transparent', $m[1]);

        foreach ($this->buttonTags('users/profile-edit.blade.php') as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\bpf-btn\b/', $tag);
        }
    }

    /**
     * Luật bố cục của trang sửa công việc nằm TRONG @media — phải trỏ sang `.te-btn`.
     *
     * Mất nó thì hàng nút ở footer không còn chia đều trên màn hẹp, mà đo ở khổ rộng
     * thì không thấy gì.
     */
    public function test_sua_cong_viec_giu_luat_bo_cuc_trong_media(): void
    {
        $source = (string) file_get_contents(resource_path('views/tasks/edit.blade.php'));

        $this->assertStringContainsString('.te-actions .te-btn{flex:1}', $source);
        $this->assertStringNotContainsString('.te-actions .btn{flex:1}', $source);

        foreach ($this->buttonTags('tasks/edit.blade.php') as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\bte-btn\b/', $tag);
            $this->assertStringContainsString('size="none"', $tag);
            $this->assertStringContainsString('tw:leading-[1.5]', $tag);
        }
    }

    /** Nút Lưu nằm NGOÀI form, liên kết bằng thuộc tính `form` — không được mất. */
    public function test_sua_cong_viec_giu_lien_ket_form(): void
    {
        $luu = array_values(array_filter(
            $this->buttonTags('tasks/edit.blade.php'),
            static fn (string $t): bool => str_contains($t, 'type="submit"'),
        ));

        $this->assertCount(1, $luu);
        $this->assertStringContainsString('form="taskEditForm"', $luu[0]);
    }

    /** Nút In dùng onclick nên phải giữ type="button"; nút Lọc dựa vào submit ngầm định. */
    public function test_bao_cao_tai_chinh_dung_type(): void
    {
        $tags = $this->buttonTags('finance/reports.blade.php');

        $in = array_values(array_filter($tags, static fn (string $t): bool => str_contains($t, 'window.print()')));
        $this->assertCount(1, $in);
        $this->assertStringContainsString('type="button"', $in[0]);

        $loc = array_values(array_filter(
            $tags,
            static fn (string $t): bool => str_contains($t, 'rounded-pill tw:font-bold') && ! str_contains($t, 'href='),
        ));
        $this->assertCount(1, $loc);
        $this->assertStringContainsString('type="submit"', $loc[0]);
    }

    /** Nút gửi form của hai trang danh mục phải khai type. */
    public function test_danh_muc_giu_submit(): void
    {
        foreach (['product-categories/edit.blade.php', 'product-categories/create.blade.php'] as $view) {
            $submit = array_values(array_filter(
                $this->buttonTags($view),
                static fn (string $t): bool => ! str_contains($t, 'href='),
            ));

            $this->assertCount(1, $submit, "Sai số nút gửi form ở {$view}");
            $this->assertStringContainsString('type="submit"', $submit[0]);
        }
    }

    /** Năm trang đều render được. */
    public function test_render_nam_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        // Trang danh mục qua ProductCategoryPolicy (categories.manage), không phải
        // chỉ EnforcePageAccess — admin của test không tự có quyền này.
        foreach (['page.products', 'categories.manage', 'product.view'] as $perm) {
            Permission::findOrCreate($perm, 'web');
            $user->givePermissionTo($perm);
        }

        DB::table('crm_product_categories')->insert([
            'id' => 970010, 'name' => 'Danh muc canh test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tasks')->insert([
            'id' => 970011, 'title' => 'Cong viec canh test', 'description' => 'Noi dung',
            'requester_id' => $user->id, 'assignee_id' => $user->id,
            'priority' => 'normal', 'status' => 'new', 'progress_percent' => 0,
            'due_at' => '2026-09-20 17:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/profile/edit',
            '/finance/reports',
            '/chat/tasks/970011/edit',
            '/categories/970010/edit',
            '/categories/create',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}
