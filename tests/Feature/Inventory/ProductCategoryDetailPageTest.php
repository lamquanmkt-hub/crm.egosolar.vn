<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\DTOs\Inventory\ProductCategoryChildRow;
use App\DTOs\Inventory\ProductCategoryDetail;
use App\View\Presenters\Inventory\ProductCategoryDetailPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Guard + hành vi trang `product-categories/show` sau đợt 2026-10-02. */
final class ProductCategoryDetailPageTest extends TestCase
{
    use DatabaseTransactions;

    private const VIEW = 'views/product-categories/show.blade.php';

    public function test_view_sach_bootstrap_va_khong_tu_truy_van(): void
    {
        $view = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents(resource_path(self::VIEW)));

        $this->assertStringNotContainsString('@php', $view);
        $this->assertStringNotContainsString('<style', $view);
        // Bản cũ gọi `$child->products()->count()` TRONG vòng lặp — mỗi danh mục con một COUNT(*).
        $this->assertStringNotContainsString('products()', $view, 'view không được tự truy vấn');
        $this->assertStringNotContainsString('->format(', $view, 'định dạng ngày phải ở presenter');

        preg_match_all('/(?<![-:\w])class="([^"]*)"/', $view, $khop);
        $token = [];
        foreach ($khop[1] as $ds) {
            foreach (preg_split('/\s+/', trim($ds)) ?: [] as $l) {
                if ($l !== '' && ! str_starts_with($l, 'tw:') && $l !== 'bi' && ! str_starts_with($l, 'bi-')) {
                    $token[$l] = true;
                }
            }
        }
        $this->assertSame([], array_keys($token), 'view còn lớp không phải `tw:*`');
    }

    public function test_moi_thuoc_tinh_doc_tu_dto_deu_ton_tai(): void
    {
        $view = (string) file_get_contents(resource_path(self::VIEW));

        foreach (['categoryDetail' => ProductCategoryDetail::class, 'child' => ProductCategoryChildRow::class] as $bien => $dto) {
            preg_match_all('/\$'.$bien.'->([a-zA-Z0-9]+)/', $view, $hit);
            $props = array_map(
                static fn (\ReflectionProperty $p): string => $p->getName(),
                (new \ReflectionClass($dto))->getProperties()
            );
            $this->assertSame([], array_values(array_diff(array_unique($hit[1]), $props)),
                "view đọc thuộc tính không có của \$$bien");
        }
    }

    public function test_presenter_in_dung_gia_tri(): void
    {
        $detail = (new ProductCategoryDetailPresenter)->viewData((object) [
            'id' => 9, 'name' => 'Thiết bị', 'description' => null,
            'parent' => (object) ['name' => 'Gốc'],
            'created_at' => \Illuminate\Support\Carbon::parse('2026-09-01 10:30:00'),
            'updated_at' => \Illuminate\Support\Carbon::parse('2026-09-20 15:45:00'),
            'children' => collect([(object) ['id' => 11, 'name' => 'Con A', 'products_count' => 4]]),
            'products' => collect(array_map(
                static fn (int $i): object => (object) ['id' => $i, 'name' => 'SP '.$i],
                range(1, 12)
            )),
        ])['categoryDetail'];

        $this->assertSame('—', $detail->descriptionText, 'mô tả null ra gạch dài');
        $this->assertSame('Gốc', $detail->parentName);
        $this->assertSame('01/09/2026 10:30', $detail->createdText);
        $this->assertSame('20/09/2026 15:45', $detail->updatedText);
        $this->assertSame(12, $detail->productCount);
        $this->assertSame(1, $detail->childCount);
        $this->assertCount(10, $detail->products, 'chỉ lấy 10 sản phẩm đầu');
        $this->assertSame(2, $detail->extraProductCount);
        $this->assertSame(4, $detail->children[0]->productCount, 'đọc products_count do withCount nạp');
    }

    public function test_mo_ta_chuoi_rong_khong_thanh_gach(): void
    {
        // Bản cũ dùng `?? '—'` nên chuỗi RỖNG vẫn in rỗng; chỉ null mới ra gạch.
        $detail = (new ProductCategoryDetailPresenter)->viewData((object) [
            'id' => 1, 'name' => 'X', 'description' => '', 'parent' => null,
            'created_at' => null, 'updated_at' => null, 'children' => collect(), 'products' => collect(),
        ])['categoryDetail'];

        $this->assertSame('', $detail->descriptionText);
        $this->assertNull($detail->parentName);
        $this->assertSame(0, $detail->extraProductCount);
    }

    public function test_trang_that_khong_con_n_cong_1_theo_so_danh_muc_con(): void
    {
        $admin = $this->userWithRole('admin', ['id' => 818001, 'name' => 'QT Guard'], ['product.view', 'categories.manage']);

        DB::table('crm_product_categories')->insert([
            ['id' => 818100, 'name' => 'Cha Guard', 'description' => 'Mô tả', 'parent_id' => null,
                'created_at' => '2026-09-01 10:30:00', 'updated_at' => '2026-09-20 15:45:00'],
        ]);

        $demTruyVan = function (int $soCon) use ($admin): int {
            DB::table('crm_product_categories')->where('parent_id', 818100)->delete();
            for ($i = 1; $i <= $soCon; $i++) {
                DB::table('crm_product_categories')->insert([
                    'id' => 818200 + $i, 'name' => 'Con '.$i, 'parent_id' => 818100,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->actingAs($admin)->get('/categories/818100')->assertOk();
            DB::getEventDispatcher()->forget(\Illuminate\Database\Events\QueryExecuted::class);

            return $n;
        };

        $demTruyVan(1);                 // làm nóng SchemaCache
        $mot = $demTruyVan(1);
        $nhieu = $demTruyVan(6);

        $this->assertSame($mot, $nhieu, sprintf(
            "Số truy vấn TĂNG theo số danh mục con (%d → %d).\n".
            'Bản cũ gọi `$child->products()->count()` trong vòng lặp; nay dùng `withCount`.',
            $mot, $nhieu
        ));
    }

    public function test_trang_that_in_dung(): void
    {
        $admin = $this->userWithRole('admin', ['id' => 818002, 'name' => 'QT Guard 2'], ['product.view', 'categories.manage']);

        DB::table('crm_product_categories')->insert([
            ['id' => 818300, 'name' => 'Danh mục cha', 'description' => null, 'parent_id' => null,
                'created_at' => '2026-09-01 10:30:00', 'updated_at' => '2026-09-20 15:45:00'],
            ['id' => 818301, 'name' => 'Danh mục chính', 'description' => 'Mô tả danh mục', 'parent_id' => 818300,
                'created_at' => '2026-09-02 08:15:00', 'updated_at' => '2026-09-21 09:05:00'],
            ['id' => 818302, 'name' => 'Con số một', 'description' => null, 'parent_id' => 818301,
                'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('crm_product_catalog')->insert([
            ['id' => 818400, 'name' => 'Sản phẩm A', 'sku' => 'SKU-GA', 'category_id' => 818301,
                'created_at' => now(), 'updated_at' => now()],
            ['id' => 818401, 'name' => 'Sản phẩm con', 'sku' => 'SKU-GC', 'category_id' => 818302,
                'created_at' => now(), 'updated_at' => now()],
        ]);

        $html = (string) $this->actingAs($admin)->get('/categories/818301')->assertOk()->getContent();

        $this->assertStringContainsString('Danh mục chính', $html);
        $this->assertStringContainsString('Mô tả danh mục', $html);
        $this->assertStringContainsString('Danh mục cha', $html, 'tên danh mục cha');
        $this->assertStringContainsString('02/09/2026 08:15', $html);
        $this->assertStringContainsString('21/09/2026 09:05', $html);
        $this->assertStringContainsString('Con số một', $html);
        $this->assertStringContainsString('1 sản phẩm', $html, 'số sản phẩm của danh mục con');
        $this->assertStringContainsString('Sản phẩm A', $html);
    }
}
