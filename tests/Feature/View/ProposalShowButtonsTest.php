<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canh giữ trang chi tiết đề xuất (9 nút đã chuyển).
 *
 * ## Bẫy line-height
 * `.btn-pill` khai `font-size:13px` nhưng KHÔNG khai `line-height`. Trước đây
 * line-height đến từ `.btn` của Bootstrap (1.5 không đơn vị -> 19.5px). Bỏ `.btn`
 * mà không bù thì component đưa vào 24px tuyệt đối: chữ vẫn 13px nhưng dòng cao
 * thêm 4.5px trên 5 nút. Đây là lý do các nút đó dùng `size="none"` +
 * `tw:leading-[1.5]` thay vì một cỡ dựng sẵn.
 * `.action-main` thì ngược lại: nó khai padding/bo góc/độ đậm nhưng không khai
 * font, nên các nút đó cần `tw:text-[16px]/[24px]`.
 *
 * ## Bẫy type
 * Cả 4 nút `<button>` của trang đều KHÔNG khai `type` và dựa vào mặc định submit
 * của HTML — duyệt, từ chối, xoá, tạo ĐNTT. Component mặc định `type="button"`,
 * nên thiếu khai báo là bốn hành động đó im lặng ngừng chạy. Đã đo `.type` và
 * `.form` trên DOM cả hai bản.
 */
final class ProposalShowButtonsTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/proposals/show.blade.php';

    /** @return list<string> thẻ mở của mọi component nút trong view */
    private function buttonTags(): array
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));
        preg_match_all('/<x-ui\.button\b(?:[^>"]|"[^"]*")*>/s', $source, $m);

        return $m[0];
    }

    public function test_khong_con_class_nut_bootstrap(): void
    {
        $source = (string) file_get_contents(resource_path(self::VIEW));

        $this->assertDoesNotMatchRegularExpression('/class="[^"]*(?<![\w-])btn(?![\w-])[^"]*(?<![\w-])btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)(?![\w-])/', $source);
    }

    /** `.btn-pill` không khai line-height; thiếu bù thì dòng cao 24px thay vì 19.5px. */
    public function test_nut_pill_phai_bu_line_height(): void
    {
        $pill = array_values(array_filter(
            $this->buttonTags(),
            static fn (string $t): bool => (bool) preg_match('/\bclass="[^"]*\bbtn-pill\b/', $t),
        ));

        $this->assertCount(5, $pill);

        foreach ($pill as $tag) {
            $this->assertStringContainsString('size="none"', $tag);
            $this->assertStringContainsString('tw:leading-[1.5]', $tag,
                'Nút btn-pill thiếu tw:leading-[1.5]: '.mb_substr((string) preg_replace('/\s+/', ' ', $tag), 0, 90));
        }
    }

    /** `.action-main` không khai font; thiếu bù thì mất cỡ chữ 16px/24px. */
    public function test_nut_action_main_phai_bu_font(): void
    {
        $main = array_values(array_filter(
            $this->buttonTags(),
            static fn (string $t): bool => (bool) preg_match('/\bclass="[^"]*\baction-main\b/', $t),
        ));

        $this->assertCount(4, $main);

        foreach ($main as $tag) {
            $this->assertStringContainsString('size="none"', $tag);
            $this->assertStringContainsString('tw:text-[16px]/[24px]', $tag);
        }
    }

    /** Nút không phải liên kết đều là nút gửi form — cả 4 đều dựa vào mặc định HTML. */
    public function test_nut_khong_phai_lien_ket_deu_la_submit(): void
    {
        $submits = 0;

        foreach ($this->buttonTags() as $tag) {
            if (str_contains($tag, 'href=')) {
                continue;
            }

            $this->assertStringContainsString('type="submit"', $tag,
                'Nút gửi form thiếu type="submit": '.mb_substr((string) preg_replace('/\s+/', ' ', $tag), 0, 90));
            $submits++;
        }

        $this->assertSame(4, $submits);
    }

    /** Trang render được ở cả hai trạng thái, nút gửi form vẫn là <button type="submit">. */
    public function test_render_hai_trang_thai(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        foreach (['pending' => 950001, 'approved' => 950002] as $status => $id) {
            DB::table('proposals')->insert([
                'id' => $id, 'user_id' => $user->id, 'title' => 'De xuat canh test',
                'proposal_type' => 'purchase', 'priority' => 'normal',
                'department_name' => 'Ky thuat', 'amount' => 15000000,
                'needed_date' => '2026-09-20', 'content' => 'Noi dung',
                'status' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $html = (string) $this->actingAs($user)->get('/de-xuat/'.$id)->assertOk()->getContent();

            $this->assertMatchesRegularExpression('/<button[^>]*type="submit"/', $html,
                "Trạng thái {$status} không còn nút gửi form");
        }
    }
}
