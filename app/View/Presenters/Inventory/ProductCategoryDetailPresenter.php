<?php

declare(strict_types=1);

namespace App\View\Presenters\Inventory;

use App\DTOs\Inventory\ProductCategoryChildRow;
use App\DTOs\Inventory\ProductCategoryDetail;

/**
 * Chuẩn bị trang `product-categories/show`.
 *
 * Hai thứ dời khỏi Blade: hai lượt `->format('d/m/Y H:i')`, và **`$child->products()->count()` gọi
 * trong vòng lặp** — mỗi danh mục con một truy vấn `COUNT(*)`. Nay dùng `products_count` do
 * `withCount()` nạp sẵn.
 *
 * Lớp này thuần: không Facade, không query, không `request()`/`auth()`.
 */
final class ProductCategoryDetailPresenter
{
    /** @return array{categoryDetail: ProductCategoryDetail} */
    public function viewData(object $category): array
    {
        $products = $category->products ?? collect();
        $children = $category->children ?? collect();
        $soSanPham = is_countable($products) ? count($products) : $products->count();
        $soCon = is_countable($children) ? count($children) : $children->count();

        $dongCon = [];
        foreach ($children as $child) {
            $dongCon[] = new ProductCategoryChildRow(
                id: (int) ($child->id ?? 0),
                name: (string) ($child->name ?? ''),
                // `withCount('products')` nạp sẵn; không có thì rơi về 0 chứ KHÔNG tự query.
                productCount: (int) ($child->products_count ?? 0),
            );
        }

        $muoiDau = [];
        foreach (collect($products)->take(10) as $product) {
            $muoiDau[] = ['id' => (int) ($product->id ?? 0), 'name' => (string) ($product->name ?? '')];
        }

        return [
            'categoryDetail' => new ProductCategoryDetail(
                id: (int) ($category->id ?? 0),
                name: (string) ($category->name ?? ''),
                // Bản cũ: `$category->description ?? '—'` — chuỗi RỖNG vẫn in rỗng, chỉ null mới ra gạch.
                descriptionText: $category->description === null ? '—' : (string) $category->description,
                parentName: isset($category->parent->name) ? (string) $category->parent->name : null,
                createdText: $this->gio($category->created_at ?? null),
                updatedText: $this->gio($category->updated_at ?? null),
                productCount: $soSanPham,
                childCount: $soCon,
                children: $dongCon,
                products: $muoiDau,
                extraProductCount: max(0, $soSanPham - 10),
            ),
        ];
    }

    /** `d/m/Y H:i` — y bản cũ; cột `created_at`/`updated_at` là cast datetime nên luôn có `format()`. */
    private function gio(mixed $value): string
    {
        return $value === null ? '' : $value->format('d/m/Y H:i');
    }
}
