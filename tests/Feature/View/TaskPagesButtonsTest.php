<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canh giữ hai trang công việc — hai view CUỐI của đợt chuyển nút.
 *
 * ## Luật bố cục `flex:1` bám `.btn`
 * `tasks/create` có `.tcv6-footer-actions .btn{flex:1}`, `tasks/my` có
 * `.filter-actions .btn{flex:1}` và `.action-stack .btn{flex:1}`. Mất chúng thì
 * hàng nút không còn chia đều — chỉ thấy khi đo hình học, màu vẫn đúng.
 *
 * ## Biến thể ĐỘNG
 * Nút trong `.action-stack` đổi màu theo trạng thái công việc:
 * `btn-warning` khi cần sửa/bị từ chối, `btn-outline-primary` các trạng thái khác.
 * Chuyển sang `:variant="..."` chứ không nội suy chuỗi class — nội suy sẽ lại rơi
 * vào bẫy `{{ }}` trần trong thẻ component.
 *
 * ## tasks/show KHÔNG chuyển
 * Ba chỗ còn lại ở đó là `<summary>` (mở/đóng `<details>`) và `<label>` (nhãn chọn
 * tệp). Component chỉ sinh `<a>`/`<button>`; đổi sẽ phá ngữ nghĩa HTML.
 */
final class TaskPagesButtonsTest extends TestCase
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
            'tạo công việc' => ['tasks/create.blade.php', 2],
            'công việc của tôi' => ['tasks/my.blade.php', 3],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function test_da_chuyen(string $view, int $count): void
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));

        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*(?<![\w-])btn(?![\w-])[^"]*(?<![\w-])btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)(?![\w-])/',
            $source,
        );
        $this->assertCount($count, $this->buttonTags($view));

        foreach ($this->buttonTags($view) as $tag) {
            $this->assertStringContainsString('size="none"', $tag);
            $this->assertStringContainsString('tw:leading-[1.5]', $tag);
        }
    }

    /** Luật bố cục phải trỏ sang lớp mới, không còn bám `.btn`. */
    public function test_giu_luat_bo_cuc(): void
    {
        $create = (string) file_get_contents(resource_path('views/tasks/create.blade.php'));
        $this->assertStringContainsString('.tcv6-footer-actions .tcv6-btn{flex:1}', $create);
        $this->assertStringNotContainsString('.tcv6-footer-actions .btn{flex:1}', $create);

        $my = (string) file_get_contents(resource_path('views/tasks/my.blade.php'));
        $this->assertStringContainsString('.filter-actions .tm-btn{flex:1}', $my);
        $this->assertStringContainsString('.action-stack .tm-btn{flex:1}', $my);
        $this->assertStringNotContainsString('.filter-actions .btn{flex:1}', $my);
        $this->assertStringNotContainsString('.action-stack .btn{flex:1}', $my);

        foreach ($this->buttonTags('tasks/my.blade.php') as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\btm-btn\b/', $tag);
        }
        foreach ($this->buttonTags('tasks/create.blade.php') as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\btcv6-btn\b/', $tag);
        }
    }

    /** Biến thể động phải dùng `:variant`, không nội suy chuỗi class. */
    public function test_bien_the_dong_dung_rang_buoc(): void
    {
        $dyn = array_values(array_filter(
            $this->buttonTags('tasks/my.blade.php'),
            static fn (string $t): bool => str_contains($t, ':variant='),
        ));

        $this->assertCount(1, $dyn);
        $this->assertStringContainsString("'warning'", $dyn[0]);
        $this->assertStringContainsString("'outline-primary'", $dyn[0]);
    }

    /**
     * tasks/show: `<summary>` và `<label>` KHÔNG đổi thành <button> — chúng đi qua
     * `<x-ui.button as="summary|label">` (prop thêm 2026-09-06), thẻ giữ nguyên ngữ nghĩa.
     */
    public function test_tasks_show_giu_nguyen_the_ngu_nghia(): void
    {
        $source = (string) file_get_contents(resource_path('views/tasks/show.blade.php'));

        $this->assertStringContainsString('<x-ui.button variant="light" size="none" as="summary"', $source);
        $this->assertSame(2, substr_count($source, 'as="label"'), 'hai nhãn "Thay file" đi qua as="label"');
        $this->assertDoesNotMatchRegularExpression('/<(summary|label) class="[^"]*(?<![\w-])btn(?![\w-])/', $source);
    }

    /** Hai trang render được, kèm ba trạng thái để nhánh biến thể động cùng chạy. */
    public function test_render_hai_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value, ['id' => 999960]);

        foreach ([['new', 999301], ['revision', 999302], ['approved', 999303]] as [$st, $id]) {
            DB::table('tasks')->insert([
                'id' => $id, 'title' => 'Cong viec '.$st, 'description' => 'Noi dung',
                'requester_id' => $user->id, 'assignee_id' => $user->id,
                'priority' => 'normal', 'status' => $st, 'progress_percent' => 0,
                'due_at' => '2026-09-20 17:00:00', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($user)->get('/chat/tasks/create')->assertOk();
        $html = (string) $this->actingAs($user)->get('/chat/tasks/my')->assertOk()->getContent();

        // Ba trạng thái phải cùng render để cả hai nhánh biến thể được bao phủ.
        $this->assertStringContainsString('tm-btn', $html);
    }
}
