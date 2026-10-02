<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Canh giữ 17 view nhỏ còn lại của đợt chuyển.
 *
 * ## Lớp riêng của trang khai gì thì bù nấy — không có khuôn chung
 * - `.btn-pill` (hướng dẫn chấm công) và `.task-v7-action` (danh sách công việc)
 *   khai cỡ chữ nhưng KHÔNG khai line-height -> `size="none"` + `tw:leading-[1.5]`.
 * - `.wt-action-btn` (báo cáo tuần) khai padding/bo góc/chiều cao nhưng không khai
 *   font -> `size="sm"` cấp đúng 14/21 như `btn-sm` cũ.
 * - `.remove-holiday-row` chỉ là hook JS, không có CSS -> giữ nguyên làm class.
 *
 * ## Chỗ CHƯA đo được
 * Nút "Tạo đơn vật tư" của `sites/show` nằm sau `@if($egoCanCreateMrFromSite && ...)`
 * mà điều kiện vai trò không thoả trong môi trường test, nên nó không render và phép
 * so không chạm tới. Chỉ canh được ở mức mã nguồn.
 */
final class RemainingViewsButtonsTest extends TestCase
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
            'thêm brand' => ['brands/create.blade.php', 1],
            'sửa brand' => ['brands/edit.blade.php', 1],
            'danh sách công ty' => ['companies/index.blade.php', 1],
            'thêm công ty (QL)' => ['company_management/create.blade.php', 1],
            'sửa công ty (QL)' => ['company_management/edit.blade.php', 1],
            'sửa loại giá' => ['price_tiers/edit.blade.php', 1],
            'form loại giá' => ['price_tiers/_form.blade.php', 2],
            'chi tiết danh mục' => ['product-categories/show.blade.php', 2],
            'chi tiết công trình' => ['sites/show.blade.php', 10],
            'form người dùng' => ['users/_form.blade.php', 1],
            'tạo trả hàng' => ['orders/returns/create.blade.php', 2],
            'tải lead' => ['marketing/leads/upload.blade.php', 2],
            'cài đặt chấm công' => ['hr/attendance/settings.blade.php', 5],
            'báo cáo tuần' => ['marketing/reports/weekly_tasks.blade.php', 6],
            'danh sách công việc' => ['tasks/index.blade.php', 1],
            'hướng dẫn máy tính' => ['hr/attendance/guides/desktop.blade.php', 1],
            'hướng dẫn điện thoại' => ['hr/attendance/guides/mobile.blade.php', 1],
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
    }

    /** Lớp khai cỡ chữ mà không khai line-height thì nút phải bù theo tỷ lệ. */
    public function test_bu_line_height_cho_lop_khai_co_chu(): void
    {
        foreach ([
            'tasks/index.blade.php',
            'hr/attendance/guides/desktop.blade.php',
            'hr/attendance/guides/mobile.blade.php',
        ] as $view) {
            foreach ($this->buttonTags($view) as $tag) {
                $this->assertStringContainsString('size="none"', $tag, $view);
                $this->assertStringContainsString('tw:leading-[1.5]', $tag, $view);
            }
        }
    }

    /** `.remove-holiday-row` là hook JS — mất là nút xoá ngày nghỉ ngừng chạy. */
    public function test_giu_hook_js_cai_dat_cham_cong(): void
    {
        // Đợt btn 2026-09-06 thêm 4 nút `setting-btn`; ở đây chỉ canh nút có hook JS.
        $tags = array_values(array_filter(
            $this->buttonTags('hr/attendance/settings.blade.php'),
            static fn (string $t): bool => str_contains($t, 'remove-holiday-row'),
        ));

        $this->assertCount(1, $tags);
        $this->assertMatchesRegularExpression('/\bclass="[^"]*\bremove-holiday-row\b/', $tags[0]);
        $this->assertStringContainsString('type="button"', $tags[0]);
    }

    /**
     * Nút "Tạo đơn vật tư" của trang công trình: chỉ canh được ở mức mã nguồn.
     *
     * Nó nằm sau `@if($egoCanCreateMrFromSite && $egoSiteIdForMr)` và điều kiện vai
     * trò không thoả trong môi trường test nên không render — phép so phần tử không
     * bao phủ nó.
     */
    public function test_cong_trinh_nut_tao_don_vat_tu(): void
    {
        // Đợt btn 2026-09-06 chuyển thêm 9 nút `btn-ego*` của trang; ở đây chỉ canh nút này.
        $tags = array_values(array_filter(
            $this->buttonTags('sites/show.blade.php'),
            static fn (string $t): bool => str_contains($t, 'material-requests.create'),
        ));

        $this->assertCount(1, $tags);
        $this->assertStringContainsString('variant="primary"', $tags[0]);
        $this->assertStringContainsString('material-requests.create', $tags[0]);
        $this->assertStringContainsString('border-radius:12px;font-weight:800;', $tags[0]);
    }

    /** Các trang render được. */
    public function test_render(): void
    {
        $user = $this->userWithRole(Role::Admin->value, ['id' => 999900]);

        foreach (['page.products', 'product.view', 'products.manage', 'categories.manage'] as $perm) {
            Permission::findOrCreate($perm, 'web');
            $user->givePermissionTo($perm);
        }

        DB::table('crm_brands')->insert([
            'id' => 999101, 'name' => 'Brand', 'slug' => 'brand-rv',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('crm_price_tiers')->insert([
            'id' => 999102, 'code' => 'BL-RV', 'name' => 'Ban le', 'priority' => 1,
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('crm_product_categories')->insert([
            'id' => 999103, 'name' => 'Danh muc',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sites')->insert([
            'id' => 999104, 'name' => 'Cong trinh',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/brands/create',
            '/brands/999101/edit',
            '/companies',
            '/company-management/create',
            '/price-tiers/create',
            '/price-tiers/999102/edit',
            '/categories/999103',
            '/cong-trinh/999104',
            '/users/create',
            '/marketing/leads/upload',
            '/nhan-su/cham-cong/settings',
            '/marketing/reports/weekly-tasks',
            '/chat/tasks',
            '/nhan-su/huong-dan-cham-cong/may-tinh',
            '/nhan-su/huong-dan-cham-cong/dien-thoai',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}
