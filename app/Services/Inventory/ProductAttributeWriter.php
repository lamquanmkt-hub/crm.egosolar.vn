<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\Catalog\Product;
use App\Support\EgoCompanyContext;
use App\Support\SchemaCache;

/**
 * Gán thuộc tính sản phẩm từ dữ liệu form nhập kho.
 *
 * ## Vì sao tách
 * `ProductController::store()` có HAI nhánh — nhập một SKU và nhập nhiều dòng
 * "v2_lines" — và cả hai cùng chép một khối ~40 dòng gán y hệt nhau: tên, SKU,
 * danh mục, thương hiệu, ghi chú, giá vốn trước/sau VAT, giá bán lẻ trước/sau
 * VAT, cờ serial, cờ hoạt động, công ty vận hành. Sửa quy tắc giá ở một nhánh mà
 * quên nhánh kia là lỗi chỉ lộ ra ở đúng một trong hai màn nhập.
 *
 * ## Cột phụ thuộc schema
 * Nhiều cột (`warehouse_note`, `cost_vat_percent`, `price_retail`,
 * `price_retail_vat`, `vat_percent`) chỉ có trên một số phiên bản schema. Ở đây
 * vẫn kiểm tra trước khi gán, nhưng qua `SchemaCache` nên không tốn truy vấn lặp.
 *
 * ## Quy ước giá (giữ nguyên như bản cũ)
 * - `price_agent` là giá vốn TRƯỚC VAT, `price_agent_vat` là giá vốn SAU VAT.
 * - `price_retail` là giá bán TRƯỚC VAT, `price_retail_vat` là giá bán SAU VAT.
 * - Giá sau VAT luôn được tính lại từ giá trước VAT, không nhận từ form.
 */
final class ProductAttributeWriter
{
    /**
     * Gán toàn bộ thuộc tính chung rồi lưu sản phẩm.
     *
     * @param  array<string, mixed>  $data  Dữ liệu form (đã qua validate)
     * @param  float  $costBeforeVat  Giá vốn trước VAT của lần nhập này
     * @param  float  $costVatPercent  % VAT giá vốn của lần nhập này
     * @param  bool  $serialized  Sản phẩm có quản lý theo serial không
     */
    public function fillAndSave(
        Product $product,
        array $data,
        float $costBeforeVat,
        float $costVatPercent,
        bool $serialized,
    ): Product {
        $table = $product->getTable();

        $product->name = (string) ($data['name'] ?? '');
        $product->category_id = $data['category_id'] ?? null;
        $product->brand_id = $data['brand_id'] ?? null;
        $product->note = $data['note'] ?? null;

        if (SchemaCache::hasColumn($table, 'warehouse_note')) {
            $product->warehouse_note = $data['warehouse_note'] ?? null;
        }

        $this->applyCost($product, $table, $costBeforeVat, $costVatPercent);
        $this->applyRetailPrice($product, $table, $data);

        $product->is_serialized = $serialized ? 1 : 0;
        $product->is_active = 1;

        if (SchemaCache::hasColumn($table, 'company_id')) {
            $product->company_id = EgoCompanyContext::defaultCompanyId();
        }

        $product->save();

        return $product;
    }

    /**
     * Đánh dấu sản phẩm thuộc công ty vận hành mặc định (không lưu).
     */
    public function assignDefaultCompany(Product $product): void
    {
        if (SchemaCache::hasColumn($product->getTable(), 'company_id')) {
            $product->company_id = EgoCompanyContext::defaultCompanyId();
        }
    }

    /**
     * Giá vốn: lưu cả trước VAT, % VAT và sau VAT.
     */
    private function applyCost(Product $product, string $table, float $costBeforeVat, float $costVatPercent): void
    {
        $product->price_agent = $costBeforeVat;

        if (SchemaCache::hasColumn($table, 'cost_vat_percent')) {
            $product->cost_vat_percent = $costVatPercent;
        }

        $product->price_agent_vat = round($costBeforeVat * (1 + $costVatPercent / 100), 2);
    }

    /**
     * Giá bán lẻ: lưu cả trước VAT, % VAT và sau VAT (khi schema có cột).
     *
     * @param  array<string, mixed>  $data
     */
    private function applyRetailPrice(Product $product, string $table, array $data): void
    {
        $retailBeforeVat = isset($data['price_retail']) ? (float) $data['price_retail'] : 0.0;
        $retailVatPercent = isset($data['vat_percent']) ? (float) $data['vat_percent'] : 0.0;

        if (SchemaCache::hasColumn($table, 'price_retail')) {
            $product->price_retail = $retailBeforeVat;
        }

        if (SchemaCache::hasColumn($table, 'price_retail_vat')) {
            $product->price_retail_vat = $retailBeforeVat * (1 + $retailVatPercent / 100);
        }

        if (SchemaCache::hasColumn($table, 'vat_percent')) {
            $product->vat_percent = $retailVatPercent;
        }
    }
}
