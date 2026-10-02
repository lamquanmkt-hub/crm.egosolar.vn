<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Canh giữ 4 trang: loại giá, brand, sửa ngân sách marketing, dashboard marketing.
 *
 * ## Kích thước dựng sẵn khớp thẳng, không cần bù
 * Ba trang đầu dùng đúng kích thước mặc định của Bootstrap (6px/12px, 16/24, bo 6px)
 * và `btn-sm` (4px/8px, 14/21, bo 4px) — trùng khít `size=""` và `size="sm"` của
 * component, đã đo từng giá trị. Không lớp riêng nào của trang chen vào.
 *
 * ## Dashboard marketing thì khác
 * `.ego-btn`/`.ego-btn-primary` khai padding, bo góc, độ đậm nhưng KHÔNG khai font;
 * trước đây 16px/24px đến từ `.btn`. Bỏ `.btn` mà không bù thì chữ tụt xuống cỡ
 * thừa kế. Vì vậy các nút đó dùng `size="none"` + `tw:text-[16px]/[24px]`.
 *
 * ## Bốn nút dựa vào submit ngầm định
 * Nút Tìm và Xoá của hai trang danh sách, nút "Tạo dòng tháng sau", nút Lưu và nút
 * "Lọc dữ liệu" đều không khai `type`. Component mặc định `button`, nên thiếu khai
 * báo là các form tìm kiếm và xoá im lặng ngừng chạy.
 */
final class ListAndDashboardButtonsTest extends TestCase
{
    use DatabaseTransactions;

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
            'loại giá' => ['price_tiers/index.blade.php', 5, 2],
            'brand' => ['brands/index.blade.php', 5, 2],
            'sửa ngân sách' => ['marketing/budget_edit.blade.php', 3, 2],
            'dashboard marketing' => ['marketing/dashboard.blade.php', 3, 1],
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
            if (str_contains($tag, 'href=')) {
                continue;
            }
            $this->assertStringContainsString('type="submit"', $tag,
                'Nút gửi form thiếu type="submit": '.mb_substr((string) preg_replace('/\s+/', ' ', $tag), 0, 80));
            $found++;
        }

        $this->assertSame($submits, $found);
    }

    /** `.ego-btn` không khai font -> nút phải tự bù, nếu không chữ tụt cỡ. */
    public function test_dashboard_bu_font_cho_ego_btn(): void
    {
        foreach ($this->buttonTags('marketing/dashboard.blade.php') as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\bego-btn(-primary)?\b/', $tag);
            $this->assertStringContainsString('size="none"', $tag);
            $this->assertStringContainsString('tw:text-[16px]/[24px]', $tag);
        }
    }

    /** Bốn trang đều render được. */
    public function test_render_bon_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        foreach (['page.products', 'product.view', 'products.manage'] as $perm) {
            Permission::findOrCreate($perm, 'web');
            $user->givePermissionTo($perm);
        }

        DB::table('crm_price_tiers')->insert([
            'id' => 980010, 'code' => 'BAN_LE_T', 'name' => 'Ban le', 'priority' => 1,
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('crm_brands')->insert([
            'id' => 980011, 'name' => 'Brand canh test', 'slug' => 'brand-canh-test',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketing_budgets')->insert([
            'id' => 980012, 'platform' => 'facebook', 'month' => '2026-09-01',
            'budget' => 10000000, 'actual_spent' => 2000000, 'campaign' => 'Chien dich',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/price-tiers',
            '/brands',
            '/marketing/budget/980012/edit',
            '/marketing/dashboard',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}
