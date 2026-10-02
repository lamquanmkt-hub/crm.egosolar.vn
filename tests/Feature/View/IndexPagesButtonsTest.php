<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Canh giữ 8 trang danh sách/biểu mẫu: phòng ban, chức danh, phương thức thanh
 * toán (3 trang), thông báo, người dùng, danh mục sản phẩm.
 *
 * ## Nút nằm trong bộ chứa của Bootstrap thì mất nhiều hơn là màu
 * - `.btn-group > .btn` cho position, flex-grow và bo góc hai đầu → nút Sửa của
 *   trang danh mục phải tự khai lại cả ba.
 * - `.input-group > .btn` cho position và z-index → nút Tìm của trang phương thức
 *   thanh toán phải tự khai lại, nếu không viền chồng bị đè.
 * Cả hai chỉ lộ khi đo hình học; màu thì vẫn đúng nên nhìn ảnh tĩnh không thấy.
 *
 * ## `size="none"` bỏ cả font-weight
 * `.btn-icon` của trang người dùng khai kích thước và padding:0 nhưng không khai
 * font. Dùng `size="none"` mà quên bù thì chữ thừa kế font-weight 650 của cha thay
 * vì 400 mà `.btn` từng đặt.
 */
final class IndexPagesButtonsTest extends TestCase
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
            'phòng ban' => ['hr/departments/index.blade.php', 4, 1],
            'chức danh' => ['hr/positions/index.blade.php', 4, 1],
            'PTTT danh sách' => ['payment_methods/index.blade.php', 4, 2],
            'PTTT thêm' => ['payment_methods/create.blade.php', 2, 1],
            'PTTT sửa' => ['payment_methods/edit.blade.php', 2, 1],
            'thông báo' => ['notifications/index.blade.php', 3, 3],
            'người dùng' => ['users/index.blade.php', 3, 1],
            'danh mục' => ['product-categories/index.blade.php', 3, 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function test_da_chuyen_va_giu_submit(string $view, int $count, int $submits): void
    {
        $source = (string) file_get_contents(resource_path('views/'.$view));
        // Mẫu phải cho phép lớp phụ xen giữa (`btn btn-sm btn-danger`) và KHÔNG bắt nhầm
        // biến thể riêng của trang như `btn-ego`.
        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*\bbtn\b[^"]*\bbtn-(?:outline-)?(?:primary|secondary|success|danger|warning|info|light|dark|link)\b/',
            $source,
        );

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

    /** Nút Sửa là con trực tiếp của `.btn-group` — phải tự khai lại hình học. */
    public function test_danh_muc_giu_hinh_hoc_btn_group(): void
    {
        $sua = array_values(array_filter(
            $this->buttonTags('product-categories/index.blade.php'),
            static fn (string $t): bool => str_contains($t, 'categories.edit'),
        ));

        $this->assertCount(1, $sua);
        foreach (['tw:relative', 'tw:grow', 'tw:[border-radius:.25rem_0_0_.25rem]'] as $need) {
            $this->assertStringContainsString($need, $sua[0], "Nút Sửa thiếu {$need}");
        }
    }

    /** Nút Tìm là con của `.input-group` — phải tự khai lại position/z-index. */
    public function test_pttt_giu_z_index_input_group(): void
    {
        $tim = array_values(array_filter(
            $this->buttonTags('payment_methods/index.blade.php'),
            static fn (string $t): bool => str_contains($t, 'tw:z-[2]'),
        ));

        $this->assertCount(1, $tim);
        $this->assertStringContainsString('tw:relative', $tim[0]);
    }

    /** `size="none"` bỏ cả font-weight — `.btn-icon` phải tự bù. */
    public function test_nguoi_dung_bu_font_cho_btn_icon(): void
    {
        // Chỉ hai nút icon; nút "Thêm người dùng" (`.btn-ego`, đợt btn 2026-09-06) có font riêng.
        $icons = array_values(array_filter(
            $this->buttonTags('users/index.blade.php'),
            static fn (string $t): bool => str_contains($t, 'btn-icon'),
        ));
        $this->assertCount(2, $icons);

        foreach ($icons as $tag) {
            $this->assertStringContainsString('tw:text-[16px]/[24px]', $tag);
            $this->assertStringContainsString('tw:font-normal', $tag);
        }
    }

    /** Tám trang đều render được; trang người dùng có hai người để nút Xoá xuất hiện. */
    public function test_render_tam_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value, ['id' => 999200]);
        $this->userWithRole(Role::Admin->value, ['id' => 999201, 'email' => 'b2@example.test']);

        foreach (['page.products', 'product.view', 'products.manage', 'categories.manage'] as $perm) {
            Permission::findOrCreate($perm, 'web');
            $user->givePermissionTo($perm);
        }

        DB::table('departments')->insert([
            'id' => 995010, 'name' => 'Phong canh test', 'code' => 'PCT2',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'id' => 995011, 'name' => 'Chuc danh canh test', 'code' => 'CDT2',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('crm_payment_methods')->insert([
            'id' => 995012, 'method_name' => 'Chuyen khoan', 'code' => 'CK2',
            'is_active' => 1, 'description' => 'Mo ta',
        ]);
        DB::table('crm_product_categories')->insert([
            'id' => 995013, 'name' => 'Danh muc canh test',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/nhan-su/departments',
            '/nhan-su/positions',
            '/payment-methods',
            '/payment-methods/create',
            '/payment-methods/995012/edit',
            '/notifications',
            '/categories',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        // Nút Xoá chỉ hiện với user KHÁC — giữ điều kiện này để không mất che phủ.
        $html = (string) $this->actingAs($user)->get('/users')->assertOk()->getContent();
        $this->assertStringContainsString('title="Delete"', $html);
    }
}
