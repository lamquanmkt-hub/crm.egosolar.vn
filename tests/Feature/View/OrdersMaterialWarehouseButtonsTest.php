<?php

declare(strict_types=1);

namespace Tests\Feature\View;

use App\Enums\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\SeedsOrderFixture;
use Tests\TestCase;

/**
 * Canh giữ 5 trang: đơn của tôi, tạo/sửa đơn vật tư, quản lý công ty, danh sách kho.
 *
 * ## Luật `.btn` có thể nằm NGOÀI view — trong public/css
 * Trang kho bị `public/css/ego-inventory-enterprise.css` chi phối qua
 * `.ego-inventory-enterprise .ego-wh-actions .btn` (min-height 34px, 10.5px, 800,
 * bo 9px) — đặc hiệu hơn luật cùng tên trong view. Quét CSS trong view là KHÔNG đủ;
 * phải hỏi trình duyệt xem luật nào thực sự khớp.
 *
 * ## Biến thể `link` của component từng thiếu gạch chân
 * Bootstrap gạch chân `.btn-link` ở mọi trạng thái, còn BASE của component đặt
 * `tw:no-underline`. Lỗi nằm im cho tới khi trang này dùng tới biến thể link.
 *
 * ## Nhiễu dữ liệu làm phép so báo động giả
 * Id tự tăng và mã sinh bằng `uniqid()` đổi mỗi lần chạy, làm đổi bề rộng chữ và
 * kéo theo hàng chục khác biệt bố cục giả. Đã chuẩn hoá GIỐNG NHAU ở cả hai bản
 * trước khi so; sau đó cả 5 trang về 0 khác biệt.
 */
final class OrdersMaterialWarehouseButtonsTest extends TestCase
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

    /** @return array<string, array{0: string, 1: int}> */
    public static function views(): array
    {
        return [
            'đơn của tôi' => ['orders/my-orders.blade.php', 5],
            'tạo đơn vật tư' => ['material_requests/create.blade.php', 7],
            'sửa đơn vật tư' => ['material_requests/edit.blade.php', 7],
            'quản lý công ty' => ['company_management/index.blade.php', 3],
            'danh sách kho' => ['warehouses/index.blade.php', 3],
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

    /**
     * Luật của trang kho nằm ở public/css và phải trỏ vào `.wh-btn`.
     *
     * Đây là luật ĐẶC HIỆU NHẤT (0-3-0) nên nó mới là luật thắng; luật cùng tên
     * trong view chỉ là lớp dưới.
     */
    public function test_kho_tro_css_ngoai_view_sang_wh_btn(): void
    {
        $css = (string) file_get_contents(public_path('css/ego-inventory-enterprise.css'));

        $this->assertStringContainsString('.ego-inventory-enterprise .ego-wh-actions .wh-btn{', $css);
        $this->assertStringNotContainsString('.ego-inventory-enterprise .ego-wh-actions .btn{', $css);

        $view = (string) file_get_contents(resource_path('views/warehouses/index.blade.php'));
        $this->assertStringContainsString('.ego-wh-actions .wh-btn{', $view);
        $this->assertStringNotContainsString('.ego-wh-actions .btn{', $view);

        // `.wh-btn` khai cỡ chữ nhưng không khai line-height -> nút phải bù.
        foreach ($this->buttonTags('warehouses/index.blade.php') as $tag) {
            $this->assertMatchesRegularExpression('/\bclass="[^"]*\bwh-btn\b/', $tag);
            $this->assertStringContainsString('tw:leading-[1.5]', $tag);
        }
    }

    /** Nút "Đánh dấu tất cả đã đọc" dùng biến thể link — phải giữ gạch chân. */
    public function test_don_cua_toi_dung_bien_the_link(): void
    {
        $link = array_values(array_filter(
            $this->buttonTags('orders/my-orders.blade.php'),
            static fn (string $t): bool => str_contains($t, 'variant="link"'),
        ));

        $this->assertCount(1, $link);
        $this->assertStringContainsString('type="submit"', $link[0]);
    }

    /** Năm trang đều render được, kèm dữ liệu để các nhánh có điều kiện cùng hiện. */
    public function test_render_nam_trang(): void
    {
        $user = $this->userWithRole(Role::Admin->value, ['id' => 999500]);
        $this->actor = (int) $user->id;

        foreach (['page.products', 'product.view', 'products.manage',
            'page.warehouses', 'warehouse.manage', 'warehouse.stock_check'] as $perm) {
            Permission::findOrCreate($perm, 'web');
            $user->givePermissionTo($perm);
        }

        $this->seedShippableOrder();

        DB::table('sites')->insert([
            'id' => 997500, 'name' => 'Cong trinh canh test',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('material_requests')->insert([
            'id' => 997501, 'site_id' => 997500, 'warehouse_id' => $this->seed['warehouseId'],
            'created_by' => $user->id, 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Không có bản ghi này thì 3 nút của trang "đơn của tôi" không render,
        // và phép so sẽ không bao giờ chạm tới chúng.
        DB::table('crm_order_notifications')->insert([
            'id' => 997502, 'order_id' => $this->seed['orderId'], 'user_id' => $user->id,
            'type' => 'status_change', 'title' => 'Doi trang thai',
            'message' => 'Don hang da chuyen buoc.', 'is_read' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([
            '/don-vat-tu/create',
            '/don-vat-tu/997501/edit',
            '/company-management',
            '/warehouses',
        ] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $html = (string) $this->actingAs($user)->get('/orders/my/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('Đánh dấu tất cả đã đọc', $html);
    }
}
