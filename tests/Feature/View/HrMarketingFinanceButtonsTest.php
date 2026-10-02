<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Canh giữ 6 trang: thêm/sửa nhân viên, danh sách lead, sửa chỉ số marketing,
 * bảng lương KPI cá nhân, tạo phiếu chi.
 *
 * Cả sáu trang dùng đúng kích thước mặc định (6px/12px, 16/24) — đã đo. Lớp riêng
 * của trang (`.ego-leads .ego-btn`, `.btn-round`) chỉ khai bo góc, đổ bóng, độ đậm
 * và chiều cao, KHÔNG khai font hay padding, nên `size=""` là đúng và không cần bù.
 * Đây là điểm khác với `.btn-pill`/`.te-btn`/`.ego-btn` của dashboard marketing —
 * những lớp đó có khai font-size nên bắt buộc phải bù line-height hoặc cỡ chữ.
 *
 * Bốn nút Lưu/Lọc không khai `type`, dựa vào mặc định submit của HTML.
 */
final class HrMarketingFinanceButtonsTest extends TestCase
{
    use DatabaseTransactions;

    /** @return list<string> */
    private function buttonTags(string $view): array
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        preg_match_all('/<x-ui\.button\b(?:[^>"]|"[^"]*")*>/s', $source, $m);

        return $m[0];
    }

    /** @return array<string, array{0: string}> */
    public static function views(): array
    {
        return [
            'thêm nhân viên' => ['hr/employees/create.blade.php'],
            'sửa nhân viên' => ['hr/employees/edit.blade.php'],
            'danh sách lead' => ['marketing/leads/index.blade.php'],
            'sửa chỉ số' => ['marketing/metrics_edit.blade.php'],
            'lương KPI cá nhân' => ['marketing/kpi_payroll/my.blade.php'],
            'tạo phiếu chi' => ['finance/payments/create.blade.php'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function test_da_chuyen_va_giu_submit(string $view): void
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*(?<![\w-])btn(?![\w-])[^"]*(?<![\w-])btn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)(?![\w-])/', $source);

        $tags = $this->buttonTags($view);
        $this->assertCount(3, $tags);

        $submits = 0;
        foreach ($tags as $tag) {
            if (str_contains($tag, 'href=')) {
                continue;
            }
            $this->assertStringContainsString('type="submit"', $tag,
                'Nút gửi form thiếu type="submit": '.mb_substr((string) preg_replace('/\s+/', ' ', $tag), 0, 80));
            $submits++;
        }

        $this->assertSame(1, $submits);
    }

    /** Lớp riêng của trang phải còn trên nút, nếu không mất bo góc/đổ bóng/chiều cao. */
    public function test_giu_lop_rieng_cua_trang(): void
    {
        foreach ($this->buttonTags('marketing/leads/index.blade.php') as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\bego-btn\b/', $tag);
        }

        foreach ($this->buttonTags('marketing/kpi_payroll/my.blade.php') as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\bbtn-round\b/', $tag);
        }
    }

    /** Sáu trang đều render được. */
    public function test_render_sau_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value);

        DB::table('marketing_metrics')->insert([
            'id' => 992010, 'date' => '2026-09-01', 'platform' => 'facebook',
            'campaign' => 'Chien dich canh test', 'spend' => 1000000, 'leads' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/nhan-su/employees/create',
            '/nhan-su/employees/'.$user->id.'/edit',
            '/marketing/leads',
            '/marketing/metrics/992010/edit',
            '/marketing/kpi-payroll/my',
            '/finance/payments/create',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}
