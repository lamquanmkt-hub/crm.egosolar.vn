<?php

declare(strict_types=1);

namespace App\View\Presenters\Projects;

use App\DTOs\Projects\WarehouseItemRow;

/**
 * Chuẩn bị trang xuất kho công trình bản thử (`project-test/warehouse-show-v2`).
 *
 * Thay ba khối `@php`: một khối suy ba cờ trạng thái, và HAI khối một dòng giống hệt nhau
 * (`$allocation = $item->allocations->first()`) nằm trong hai vòng lặp khác nhau.
 *
 * Lớp này thuần: không Facade, không query, không `request()`.
 */
final class ProjectTestWarehousePresenter
{
    /**
     * @param  object  $materialRequest  đã eager load `items.allocations` ở controller
     * @return array{state: string, locked: bool, reserved: bool, itemRows: list<WarehouseItemRow>}
     */
    public function viewData(object $materialRequest): array
    {
        $rows = [];
        foreach ($materialRequest->items ?? [] as $item) {
            // `->first()` trên quan hệ ĐÃ eager load nên không phát sinh truy vấn mới.
            $rows[] = new WarehouseItemRow(
                item: $item,
                allocation: $item->allocations->first(),
            );
        }

        return [
            // Controller gán sẵn bằng setAttribute() trước khi gọi view.
            'state' => (string) ($materialRequest->computed_warehouse_state ?? ''),
            'locked' => $materialRequest->status === 'issued',
            'reserved' => $materialRequest->warehouse_status === 'reserved',
            'itemRows' => $rows,
        ];
    }
}
