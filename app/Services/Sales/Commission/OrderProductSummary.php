<?php

declare(strict_types=1);

namespace App\Services\Sales\Commission;

use App\Support\SchemaCache;
use Illuminate\Support\Facades\DB;

/**
 * Gom tên/mã hàng hoá và tổng số lượng của một đơn.
 *
 * Tên hàng có thể nằm ngay trên dòng đơn (bản chụp lúc bán) hoặc chỉ có
 * `product_id` trỏ sang danh mục. Lấy cả hai rồi gộp lại, vì quy tắc hoa hồng
 * dò theo từ khoá nên thừa còn hơn thiếu.
 */
final class OrderProductSummary
{
    /** Cột danh mục cần lấy kèm, ánh xạ sang bí danh không đụng cột của dòng đơn. */
    private const CATALOG_COLUMNS = [
        'name' => 'ego_product_name',
        'barcode' => 'ego_product_code',
        'model' => 'ego_product_model',
        'sku' => 'ego_product_sku',
    ];

    /** Thứ tự đọc mô tả: bản danh mục trước, rồi tới bản chụp trên dòng đơn. */
    private const TEXT_COLUMNS = [
        'ego_product_name',
        'ego_product_code',
        'ego_product_model',
        'ego_product_sku',
        'product_name',
        'name',
        'model',
        'sku',
        'barcode',
    ];

    private const CATALOG_TABLE = 'crm_product_catalog';

    public function __construct(private readonly OrderItemReader $items) {}

    public function forOrder(object $order): OrderProductInfo
    {
        $orderId = $order->id ?? null;

        if (! $this->items->isReady() || ! $orderId) {
            return new OrderProductInfo;
        }

        try {
            return $this->summarize($this->rows($orderId));
        } catch (\Throwable $e) {
            // Thiếu bảng danh mục thì bỏ phần mô tả, không chặn cả báo cáo.
            return new OrderProductInfo;
        }
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function rows(mixed $orderId): \Illuminate\Support\Collection
    {
        $query = $this->items->queryFor($orderId, 'oi');
        $selects = ['oi.*'];

        if ($this->items->has('product_id') && SchemaCache::hasTable(self::CATALOG_TABLE)) {
            $query->leftJoin(self::CATALOG_TABLE.' as pc', 'pc.id', '=', 'oi.product_id');

            foreach (self::CATALOG_COLUMNS as $column => $alias) {
                if (SchemaCache::hasColumn(self::CATALOG_TABLE, $column)) {
                    $selects[] = DB::raw('pc.`'.$column.'` as '.$alias);
                }
            }
        }

        return $query->get($selects);
    }

    /** @param  \Illuminate\Support\Collection<int, object>  $rows */
    private function summarize(\Illuminate\Support\Collection $rows): OrderProductInfo
    {
        $texts = [];
        $quantity = 0.0;

        foreach ($rows as $item) {
            $quantity += $this->items->quantity($item);

            foreach (self::TEXT_COLUMNS as $column) {
                if (isset($item->{$column}) && trim((string) $item->{$column}) !== '') {
                    $texts[] = trim((string) $item->{$column});
                }
            }
        }

        return new OrderProductInfo(implode(' ', array_unique($texts)), $quantity);
    }
}
