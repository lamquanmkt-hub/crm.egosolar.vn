<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Canh giữ 5 view nhỏ + nhóm nút chuyển chế độ của lịch biên tập.
 *
 * ## Một lỗi CŨ được tìm ra ở đây, không phải lỗi mới
 * Đợt chuyển `content_calendar` trước đó đã đổi các nút trong `.cc-kanban-card-actions`
 * sang component nhưng để nguyên luật `.cc-kanban-card-actions .btn{border-radius:12px}`.
 * Bỏ class `.btn` khiến luật lặng lẽ hết tác dụng: 4/5 nút tụt từ 12px về 4px.
 * Không lộ ra lúc đo vì thẻ kanban CHỈ render khi có dữ liệu, mà ảnh chụp lúc đó
 * không có mục nào. Đây là lý do test dưới bắt buộc seed một mục lịch.
 *
 * ## Nhóm nút `.btn-group` mang hai thứ mà `.btn` đang gánh
 * Ngoài màu, Bootstrap còn dùng `.btn-group > .btn` để đặt `position:relative`,
 * `flex:1 1 auto` và chồng viền `margin-left:-1px`. Bỏ `.btn` là nút hết giãn đều
 * và mép viền tách ra — chỉ thấy khi đo hình học, màu thì vẫn đúng.
 *
 * ## `.active` là CLASS, không phải pseudo-class
 * JS bật tắt `.active` để đánh dấu chế độ đang xem. Component chỉ sinh `:active`
 * (lúc đang nhấn chuột), hoàn toàn khác. Luật của trang phải tự lo trạng thái này.
 */
final class SmallFormsAndCalendarButtonsTest extends TestCase
{
    use DatabaseTransactions;

    private const CAL = 'views/marketing/reports/content_calendar.blade.php';

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
            'form brand' => ['brands/_form.blade.php', 2, 1],
            'form công ty' => ['company_management/form.blade.php', 2, 1],
            'tạo tài khoản' => ['finance/accounts/create.blade.php', 2, 1],
            'báo cáo quảng cáo' => ['marketing/report/ads.blade.php', 3, 1],
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
            $this->assertStringContainsString('type="submit"', $tag);
            $found++;
        }

        $this->assertSame($submits, $found);
    }

    /** `.mr-btn` khai bo góc/padding/độ đậm nhưng không khai font -> phải bù cỡ chữ. */
    public function test_bao_cao_quang_cao_bu_font(): void
    {
        foreach ($this->buttonTags('marketing/report/ads.blade.php') as $tag) {
            $this->assertStringContainsString('size="none"', $tag);
            $this->assertStringContainsString('tw:text-[16px]/[24px]', $tag);
        }
    }

    /**
     * Luật bo góc của thẻ kanban phải bám thẻ đã render, không bám `.btn`.
     *
     * Bản canh cho lỗi cũ: nút trong `.cc-kanban-card-actions` đã chuyển sang
     * component từ trước nên không còn `.btn`, luật cũ hết tác dụng và 4/5 nút
     * tụt từ 12px về 4px.
     */
    public function test_lich_bien_tap_bo_goc_kanban_bam_dung_the(): void
    {
        $source = (string) file_get_contents(resource_path(self::CAL));

        $this->assertStringNotContainsString('.cc-kanban-card-actions .btn{', $source);
        $this->assertMatchesRegularExpression(
            '/\.cc-kanban-card-actions > a,\s*\.cc-kanban-card-actions > button\{\s*border-radius: 12px;/',
            $source,
        );
    }

    /** Nhóm nút chuyển chế độ phải giữ cả hình học lẫn trạng thái `.active`. */
    public function test_lich_bien_tap_nhom_nut_giu_hinh_hoc_va_active(): void
    {
        $source = (string) file_get_contents(resource_path(self::CAL));

        $this->assertStringNotContainsString('.cc-view-switch .btn{', $source);
        $this->assertStringNotContainsString('.cc-view-switch .btn.active{', $source);

        // Hình học mà `.btn-group > .btn` của Bootstrap từng lo.
        preg_match('/\.cc-view-switch \.cc-view-btn\{([^}]*)\}/', $source, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('position: relative', $m[1]);
        $this->assertStringContainsString('flex: 1 1 auto', $m[1]);

        $this->assertStringContainsString('.cc-view-switch .cc-view-btn + .cc-view-btn{', $source);
        $this->assertStringContainsString('.cc-view-switch .cc-view-btn.active{', $source);

        // Ba nút phải mang lớp hook để cả JS lẫn CSS còn bám được.
        $sw = array_values(array_filter(
            $this->buttonTags('marketing/reports/content_calendar.blade.php'),
            static fn (string $t): bool => str_contains($t, 'data-cc-view='),
        ));
        $this->assertCount(3, $sw);

        foreach ($sw as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\bcc-view-btn\b/', $tag);
            $this->assertStringContainsString('type="button"', $tag);
        }
    }

    /** Các trang render được; lịch biên tập có dữ liệu để thẻ kanban thật sự xuất hiện. */
    public function test_render(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        foreach (['page.products', 'product.view', 'products.manage'] as $perm) {
            Permission::findOrCreate($perm, 'web');
            $user->givePermissionTo($perm);
        }

        DB::table('crm_brands')->insert([
            'id' => 993020, 'name' => 'Brand canh test', 'slug' => 'brand-canh-test',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('content_calendars')->insert([
            'id' => 993021, 'publish_date' => '2026-09-05', 'platform' => 'facebook',
            'content_type' => 'post', 'title' => 'Bai viet canh test',
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/brands/create',
            '/brands/993020/edit',
            '/company-management/create',
            '/finance/accounts/create',
            '/marketing/report/ads',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $html = (string) $this->actingAs($user)->get('/marketing/reports/content-calendar')->assertOk()->getContent();

        // Thẻ kanban chỉ render khi CÓ mục — chính chỗ lỗi cũ lọt lưới.
        $this->assertStringContainsString('cc-kanban-card-actions', $html);
    }
}
