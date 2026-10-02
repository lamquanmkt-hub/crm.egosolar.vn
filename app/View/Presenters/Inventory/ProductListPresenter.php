<?php

declare(strict_types=1);

namespace App\View\Presenters\Inventory;

use App\DTOs\Inventory\ProductListRow;
use App\Models\Inventory\Catalog\Product;
use App\Models\User;

/**
 * Chuẩn bị giá trị cho ba view danh sách sản phẩm `products.index`, `products.index_input`,
 * `products.index_output`. Trước 2026-09-08 mỗi view tự tính trong 4 khối `@php`: bộ lọc đang chọn
 * (đọc `request()`), quyền quản lý/xem giá vốn (policy Product), colspan, và từng dòng: giá bán
 * theo bảng giá đang chọn, giá vốn, VAT, số lượng, ghi chú, ảnh chính, tên lô, tồn theo kho.
 * Tên khoá trả về giữ tên biến cũ của view; kiểm bằng so HTML 16 trang (admin + sales).
 */
final class ProductListPresenter
{
    /** Khoá bộ lọc trên query string mà ba view đọc lại để giữ lựa chọn. */
    public const FILTER_KEYS = ['warehouse_id', 'category_id', 'brand_id', 'price_tier_id'];

    /** STT + Tên + SKU + Ghi chú + Giá + SL + Danh mục + Thương hiệu + Hình ảnh; thêm cột giá vốn theo quyền, cột hành động luôn hiện. */
    private const INDEX_BASE_COLUMNS = 9;

    /** 12 cột dữ liệu + cột hành động luôn hiện. */
    private const INPUT_BASE_COLUMNS = 12;

    /** STT + Tên + SKU + Ghi chú + 3 cột giá bán + SL + Danh mục + Thương hiệu + Hình ảnh; thêm cột hành động theo quyền. */
    private const OUTPUT_BASE_COLUMNS = 11;

    /** Nút "Reset" đưa về công ty mặc định. */
    private const DEFAULT_COMPANY_ID = '1';

    private const UNNAMED_WAREHOUSE = 'Kho không tên';

    /**
     * Trang tổng: giá bán = giá bảng giá đang chọn (hoặc giá lẻ), giá vốn = giá vốn lô, số lượng theo kho đang chọn.
     *
     * @param  mixed  $products  paginator của controller (không phải paginator → không có dòng)
     * @param  array<string, mixed>  $filters  `request()->only(self::FILTER_KEYS)`
     * @return array<string, mixed>
     */
    public function index(?User $user, mixed $products, array $filters): array
    {
        $canViewCost = $this->can($user, 'viewCost');
        $tierId = $filters['price_tier_id'] ?? null;
        $warehouseId = $filters['warehouse_id'] ?? null;

        return $this->common($user, $filters) + [
            'canViewCost' => $canViewCost,
            'colspan' => self::INDEX_BASE_COLUMNS + ($canViewCost ? 1 : 0) + 1,
            'rows' => $this->rows($products, function (object $p) use ($tierId, $warehouseId): ProductListRow {
                $vatPercent = (float) ($p->vat_percent ?? 0);
                $sellBefore = (float) ($tierId ? ($this->tierPriceRow($p, $tierId)?->price ?? 0) : ($p->price_retail ?? $p->price ?? 0));

                return $this->row($p, [
                    'lotTitle' => $this->lotTitle($p),
                    'displayQty' => (int) ($warehouseId ? ($p->warehouse_qty ?? 0) : ($p->stocks_sum_qty ?? ($p->quantity ?? 0))),
                    'vatPercent' => $vatPercent,
                    'sellBefore' => $sellBefore,
                    'sellAfter' => $sellBefore * (1 + $vatPercent / 100),
                    'costBefore' => (float) ($p->lot_cost_before_vat ?? $p->price_agent ?? $p->price ?? 0),
                ]);
            }),
        ];
    }

    /**
     * Trang đầu vào: giá vốn trước/sau VAT, thành tiền theo tồn, tồn tách theo kho; tổng toàn trang ép kiểu số.
     *
     * @param  mixed  $products  paginator của controller
     * @param  array<string, mixed>  $filters  `request()->only(self::FILTER_KEYS)`
     * @return array<string, mixed>
     */
    public function input(?User $user, mixed $products, array $filters, mixed $totalQtyAll, mixed $totalAmountAll): array
    {
        return $this->common($user, $filters) + [
            'canViewCost' => $this->can($user, 'viewCost'),
            'colspan' => self::INPUT_BASE_COLUMNS + 1,
            'totalQtyAll' => (int) ($totalQtyAll ?? 0),
            'totalAmountAll' => (float) ($totalAmountAll ?? 0),
            'rows' => $this->rows($products, function (object $p): ProductListRow {
                $vatPercent = (float) ($p->vat_percent ?? 0);
                $costBefore = (float) ($p->price_agent ?? 0);
                $costAfter = (float) ($p->price_agent_vat ?? ($costBefore * (1 + $vatPercent / 100)));
                $displayQty = (int) ($p->stocks_sum_qty ?? 0);

                return $this->row($p, [
                    'displayQty' => $displayQty,
                    'vatPercent' => $vatPercent,
                    'costBefore' => $costBefore,
                    'costAfter' => $costAfter,
                    'rowAmount' => $costAfter * $displayQty,
                    'warehouseStocks' => $this->warehouseStocks($p),
                ]);
            }),
        ];
    }

    /**
     * Trang đầu ra: giá bán trước/VAT/sau theo dòng bảng giá đang chọn (ưu tiên giá sau VAT lưu sẵn), không thì giá lẻ.
     *
     * @param  mixed  $products  paginator của controller
     * @param  array<string, mixed>  $filters  `request()->only(self::FILTER_KEYS)`
     * @return array<string, mixed>
     */
    public function output(?User $user, mixed $products, array $filters): array
    {
        $common = $this->common($user, $filters);
        $tierId = $filters['price_tier_id'] ?? null;

        return $common + [
            'colspan' => self::OUTPUT_BASE_COLUMNS + ($common['canManageProducts'] ? 1 : 0),
            'rows' => $this->rows($products, function (object $p) use ($tierId): ProductListRow {
                $tierRow = $tierId ? $this->tierPriceRow($p, $tierId) : null;
                if ($tierRow) {
                    $sellBefore = (float) ($tierRow->price ?? 0);
                    $vatPercent = (float) ($tierRow->vat_percent ?? 0);
                    $sellAfter = (float) ($tierRow->price_after_vat ?? ($sellBefore * (1 + $vatPercent / 100)));
                } else {
                    $sellBefore = (float) ($p->price_retail ?? $p->price ?? 0);
                    $vatPercent = (float) ($p->vat_percent ?? 0);
                    $sellAfter = $sellBefore * (1 + $vatPercent / 100);
                }

                return $this->row($p, [
                    'lotTitle' => $this->lotTitle($p),
                    'displayQty' => (int) ($p->stocks_sum_qty ?? 0),
                    'vatPercent' => $vatPercent,
                    'sellBefore' => $sellBefore,
                    'sellAfter' => $sellAfter,
                ]);
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function common(?User $user, array $filters): array
    {
        return [
            'selectedWarehouse' => $filters['warehouse_id'] ?? null,
            'selectedCategory' => $filters['category_id'] ?? null,
            'selectedBrand' => $filters['brand_id'] ?? null,
            'selectedPriceTier' => $filters['price_tier_id'] ?? null,
            'currentCompanyId' => self::DEFAULT_COMPANY_ID,
            'canManageProducts' => $this->can($user, 'create'),
        ];
    }

    private function can(?User $user, string $ability): bool
    {
        return $user?->can($ability, Product::class) ?? false;
    }

    /**
     * @param  callable(object): ProductListRow  $build
     * @return list<ProductListRow>
     */
    private function rows(mixed $products, callable $build): array
    {
        if (! is_object($products) || ! method_exists($products, 'items')) {
            return [];
        }

        return array_map($build, array_values($products->items()));
    }

    /**
     * Dòng với ghi chú + ảnh chính chung cho ba trang; trường không truyền là 0 / rỗng.
     *
     * @param  array<string, mixed>  $values
     */
    private function row(object $p, array $values): ProductListRow
    {
        $media = $p->mainImage?->media;
        $note = $p->note ?? $p->notes ?? $p->description ?? null;

        return new ProductListRow(
            product: $p,
            lotTitle: $values['lotTitle'] ?? null,
            note: $note === null ? null : (string) $note,
            imageUrl: $media?->metadata?->url ?? ($media?->file_path ? $media->url() : null),
            displayQty: $values['displayQty'] ?? 0,
            vatPercent: $values['vatPercent'] ?? 0.0,
            sellBefore: $values['sellBefore'] ?? 0.0,
            sellAfter: $values['sellAfter'] ?? 0.0,
            costBefore: $values['costBefore'] ?? 0.0,
            costAfter: $values['costAfter'] ?? 0.0,
            rowAmount: $values['rowAmount'] ?? 0.0,
            warehouseStocks: $values['warehouseStocks'] ?? [],
        );
    }

    private function lotTitle(object $p): ?string
    {
        $title = $p->stock_lot_name ?? $p->stock_lot_code ?? null;

        return $title === null ? null : (string) $title;
    }

    /** Dòng bảng giá của sản phẩm theo bảng giá đang chọn; chỉ khi controller đã gắn quan hệ `prices`. */
    private function tierPriceRow(object $p, mixed $tierId): ?object
    {
        if (! method_exists($p, 'relationLoaded') || ! $p->relationLoaded('prices')) {
            return null;
        }

        return $p->prices->firstWhere('price_tier_id', (int) $tierId);
    }

    /** @return list<array{name: string, qty: float}> */
    private function warehouseStocks(object $p): array
    {
        return collect($p->stocks ?? [])
            ->filter(fn ($s) => (float) ($s->qty ?? 0) > 0)
            ->groupBy('warehouse_id')
            ->map(fn ($items) => [
                'name' => $items->first()?->warehouse?->name ?? self::UNNAMED_WAREHOUSE,
                'qty' => (float) $items->sum('qty'),
            ])
            ->sortByDesc('qty')
            ->values()
            ->all();
    }
}
