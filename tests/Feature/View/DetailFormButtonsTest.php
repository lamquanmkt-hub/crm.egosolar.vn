<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Canh giữ 6 trang: tạo/sửa đề xuất, sửa công ty, tài khoản tài chính, kiểm toán,
 * duyệt đơn hàng.
 *
 * ## `.btn-pill` lặp lại ở tạo/sửa đề xuất
 * Cùng bẫy đã gặp ở `proposals/show` và `proposals/index`: lớp khai font-size 13px
 * mà không khai line-height, trước đây 19.5px đến từ `.btn`. Bù `tw:leading-[1.5]`.
 *
 * ## Ba cỡ dựng sẵn đều được dùng và đều khớp số đo
 * `size=""` (6px/12px, 16/24, bo 6px), `size="sm"` (4px/8px, 14/21, bo 4px) và
 * `size="lg"` (8px/16px, 20/30, bo 8px — nút "Xác nhận duyệt" của trang duyệt đơn).
 *
 * ## Khoảng trống che phủ đã đóng
 * Nút "Xóa" của trang tài khoản chỉ render khi CÓ tài khoản. Lần đo đầu không có
 * dữ liệu nên nút không xuất hiện; đã seed một tài khoản rồi đo lại để nó được
 * bao phủ thật sự.
 */
final class DetailFormButtonsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsOrderFixture;

    /** @var array<string, int> */
    private array $seed = [];

    private int $actor = 0;

    protected function seedActorId(): int
    {
        return $this->actor;
    }

    /** @return list<string> */
    private function buttonTags(string $view): array
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        preg_match_all('/<x-ui\.button\b(?:[^>"]|"[^"]*")*>/s', $source, $m);

        return $m[0];
    }

    /** @return array<string, array{0: string, 1: int, 2: int}> */
    public static function views(): array
    {
        return [
            // view, tổng số nút, số nút gửi form
            'tạo đề xuất' => ['proposals/create.blade.php', 3, 1],
            'sửa đề xuất' => ['proposals/edit.blade.php', 3, 1],
            'sửa công ty' => ['companies/edit.blade.php', 3, 1],
            'tài khoản tài chính' => ['finance/accounts/index.blade.php', 4, 2],
            'kiểm toán' => ['finance/audit.blade.php', 3, 1],
            'duyệt đơn hàng' => ['orders/approval-form.blade.php', 3, 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function test_da_chuyen_va_giu_submit(string $view, int $count, int $submits): void
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*(?<![\w-])btn(?![\w-])[^"]*(?<![\w-])btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)(?![\w-])/', $source);

        $tags = $this->buttonTags($view);
        $this->assertCount($count, $tags);

        $found = 0;
        foreach ($tags as $tag) {
            // Nút In chạy window.print(), phải giữ type="button".
            if (str_contains($tag, 'href=') || str_contains($tag, 'window.print()')) {
                continue;
            }
            $this->assertStringContainsString('type="submit"', $tag,
                'Nút gửi form thiếu type="submit": '.mb_substr((string) preg_replace('/\s+/', ' ', $tag), 0, 80));
            $found++;
        }

        $this->assertSame($submits, $found);
    }

    /** `.btn-pill` khai font-size mà không khai line-height -> phải bù. */
    public function test_de_xuat_bu_line_height(): void
    {
        foreach (['proposals/create.blade.php', 'proposals/edit.blade.php'] as $view) {
            foreach ($this->buttonTags($view) as $tag) {
                $this->assertStringContainsString('size="none"', $tag);
                $this->assertStringContainsString('tw:leading-[1.5]', $tag);
            }
        }
    }

    /** Nút "Xác nhận duyệt" dùng cỡ lớn — đã đo 8px/16px, 20/30, bo 8px. */
    public function test_duyet_don_giu_co_lon(): void
    {
        $lg = array_values(array_filter(
            $this->buttonTags('orders/approval-form.blade.php'),
            static fn (string $t): bool => str_contains($t, 'id="submitBtn"'),
        ));

        $this->assertCount(1, $lg);
        $this->assertStringContainsString('size="lg"', $lg[0]);
        $this->assertStringContainsString('type="submit"', $lg[0]);
    }

    /** Nút In của trang kiểm toán chạy window.print() nên giữ type="button". */
    public function test_kiem_toan_nut_in_giu_type_button(): void
    {
        $in = array_values(array_filter(
            $this->buttonTags('finance/audit.blade.php'),
            static fn (string $t): bool => str_contains($t, 'window.print()'),
        ));

        $this->assertCount(1, $in);
        $this->assertStringContainsString('type="button"', $in[0]);
    }

    /** Sáu trang đều render được; trang tài khoản có dữ liệu để nút Xóa xuất hiện. */
    public function test_render_sau_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value);
        $this->actor = (int) $user->id;
        $this->seedShippableOrder();

        DB::table('accounts')->insert([
            'id' => 990020, 'name' => 'Tai khoan canh test', 'code' => 'TK-CANH', 'type' => 'bank',
            'opening_balance' => 0, 'current_balance' => 0, 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('proposals')->insert([
            'id' => 990021, 'user_id' => $user->id, 'title' => 'De xuat canh test',
            'proposal_type' => 'purchase', 'priority' => 'normal',
            'department_name' => 'Ky thuat', 'amount' => 1000000,
            'needed_date' => '2026-09-20', 'content' => 'Noi dung',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/de-xuat/create',
            '/de-xuat/990021/edit',
            '/companies/'.$this->seed['companyId'].'/edit',
            '/finance/accounts',
            '/finance/reports/audit',
            '/orders/'.$this->seed['orderId'].'/approval',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        // Nút Xóa chỉ có khi bảng có dòng — canh để lần sau không mất che phủ.
        $html = (string) $this->actingAs($user)->get('/finance/accounts')->getContent();
        $this->assertStringContainsString('Xóa', $html);
    }
}
