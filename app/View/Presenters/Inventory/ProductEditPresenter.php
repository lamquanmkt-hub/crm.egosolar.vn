<?php

declare(strict_types=1);

namespace App\View\Presenters\Inventory;

use App\DTOs\Inventory\ProductSerialGroup;
use App\DTOs\Inventory\ProductSerialLine;
use App\DTOs\Inventory\StockMovementLogRow;
use App\DTOs\Inventory\TierPriceInputRow;
use App\Services\Inventory\ProductEdit\NumberInputFormatter;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Chuẩn bị ba bảng lặp của view `products.edit` (dữ liệu đã do ProductEditPageData và controller
 * truy vấn). Trước 2026-09-08 view tự tính trong 4 khối `@php`: nhóm serial theo sản phẩm + trạng
 * thái/khoá từng serial; phân loại nguồn của 80 dòng lịch sử xuất nhập (đơn hàng / công trình /
 * nhập tay / khác) với nhãn, lớp CSS, biểu tượng, ngày giờ; và giá trị ô nhập giá theo bảng giá
 * (old input thắng giá đã lưu). Kiểm bằng so HTML 3 trang (có/không old input, sản phẩm trống).
 */
final class ProductEditPresenter
{
    /** Serial đã bán/giao/bảo hành: khoá đổi kho và xoá. */
    private const LOCKED_SERIAL_STATES = ['sold', 'delivered', 'warranty', 'warranty_claim'];

    private const UNKNOWN_SERIAL_STATE = 'unknown';

    private const DATE_TIME_FORMAT = 'd/m/Y H:i';

    private const EMPTY = '—';

    public function __construct(private readonly NumberInputFormatter $formatNumber) {}

    /**
     * @param  Collection<int, int>  $serialProductIds  id sản phẩm cần hiện serial (ProductEditPageData)
     * @param  Collection<int, object>  $serialProducts  tên/SKU theo id sản phẩm
     * @param  Collection<int, Collection<int, object>>  $serialRowsByProduct  serial gom theo product_id
     * @param  iterable<object>  $stockLogs  lịch sử xuất nhập, đã join tên kho/người tạo/đơn hàng/công trình
     * @param  iterable<object>  $priceTiers
     * @param  Collection<int, mixed>  $savedTierPrices  giá đã lưu theo id bảng giá
     * @param  array<string, mixed>  $oldInput  `request()->old()` — giá trị form vừa nhập khi validate lỗi
     * @return array<string, mixed>
     */
    public function viewData(
        object $product,
        Collection $serialProductIds,
        Collection $serialProducts,
        Collection $serialRowsByProduct,
        iterable $stockLogs,
        iterable $priceTiers,
        Collection $savedTierPrices,
        array $oldInput,
    ): array {
        return [
            'serialGroups' => $serialProductIds->map(fn ($id) => $this->serialGroup((int) $id, $serialProducts, $serialRowsByProduct))->values()->all(),
            'stockLogRows' => collect($stockLogs)->map(fn (object $log) => $this->stockLogRow($log))->values()->all(),
            'tierPriceRows' => collect($priceTiers)->map(fn (object $tier) => $this->tierPriceRow($tier, $product, $savedTierPrices, $oldInput))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, object>  $serialProducts
     * @param  Collection<int, Collection<int, object>>  $serialRowsByProduct
     */
    private function serialGroup(int $productId, Collection $serialProducts, Collection $serialRowsByProduct): ProductSerialGroup
    {
        $lines = collect($serialRowsByProduct->get($productId, []))->map(function (object $serial): ProductSerialLine {
            $state = $serial->state ?: self::UNKNOWN_SERIAL_STATE;

            return new ProductSerialLine($serial, (string) $state, in_array($state, self::LOCKED_SERIAL_STATES, true));
        });

        return new ProductSerialGroup($productId, $serialProducts->get($productId), $lines->values()->all());
    }

    /** Nguồn của một dòng lịch sử: đơn hàng → công trình/vật tư → nhập tay → nhập/xuất khác. */
    private function stockLogRow(object $log): StockMovementLogRow
    {
        $changeQty = (int) ($log->change_qty ?? 0);
        $reason = (string) ($log->reason ?? '');
        $note = (string) ($log->note_safe ?? $log->note ?? '');
        $reasonLower = mb_strtolower($reason, 'UTF-8');
        $noteLower = mb_strtolower($note, 'UTF-8');
        $refTypeLower = mb_strtolower((string) ($log->reference_type_safe ?? $log->reference_type ?? ''), 'UTF-8');

        if (! empty($log->order_code) || str_contains($refTypeLower, 'order')) {
            [$label, $class, $icon] = ['Đơn hàng '.($log->order_code ?: ('#'.($log->reference_id ?? self::EMPTY))), 'is-order', 'bi-receipt-cutoff'];
        } elseif (! empty($log->site_name) || str_contains($refTypeLower, 'material') || str_contains($reasonLower, 'vật tư') || str_contains($reasonLower, 'công trình')) {
            [$label, $class, $icon] = [! empty($log->site_name) ? 'Công trình: '.$log->site_name : 'Đơn vật tư #'.($log->reference_id ?? self::EMPTY), 'is-site', 'bi-kanban'];
        } elseif (str_contains($refTypeLower, 'manual') || str_contains($reasonLower, 'manual') || str_contains($reasonLower, 'nhập tay')
            || str_contains($reasonLower, 'nhập kho') || str_contains($reasonLower, 'sản phẩm đầu vào') || str_contains($noteLower, 'nhập tay')) {
            [$label, $class, $icon] = [$changeQty >= 0 ? 'Nhập kho / nhập tay' : 'Điều chỉnh tay', 'is-other', 'bi-pencil-square'];
        } else {
            [$label, $class, $icon] = [$changeQty >= 0 ? 'Nhập kho' : 'Xuất kho / Điều chỉnh', 'is-other', $changeQty >= 0 ? 'bi-box-arrow-in-down' : 'bi-arrow-left-right'];
        }

        $before = $log->qty_before_safe ?? null;
        $after = $log->qty_after_safe ?? null;

        return new StockMovementLogRow(
            log: $log,
            changeQty: $changeQty,
            changeText: ($changeQty > 0 ? '+' : '').number_format($changeQty),
            sourceLabel: $label,
            sourceClass: $class,
            sourceIcon: $icon,
            beforeText: $before !== null ? number_format((int) $before) : self::EMPTY,
            afterText: $after !== null ? number_format((int) $after) : self::EMPTY,
            createdAtText: ! empty($log->created_at) ? Carbon::parse($log->created_at)->format(self::DATE_TIME_FORMAT) : self::EMPTY,
            noteText: $note !== '' ? $note : ($reason !== '' ? $reason : self::EMPTY),
        );
    }

    /**
     * @param  Collection<int, mixed>  $savedTierPrices
     * @param  array<string, mixed>  $oldInput
     */
    private function tierPriceRow(object $tier, object $product, Collection $savedTierPrices, array $oldInput): TierPriceInputRow
    {
        $saved = $savedTierPrices->get((int) $tier->id, []);

        return new TierPriceInputRow(
            tier: $tier,
            beforeVat: (string) Arr::get($oldInput, 'prices.'.$tier->id.'.before_vat', ($this->formatNumber)(data_get($saved, 'before_vat', data_get($saved, 'price', '')))),
            vatPercent: (string) Arr::get($oldInput, 'prices.'.$tier->id.'.vat_percent', ($this->formatNumber)(data_get($saved, 'vat_percent', $product->vat_percent ?? 0))),
        );
    }
}
