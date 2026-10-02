{{--
    EGO_VIEW_CHET — VIEW CHẾT, KHÔNG AI RENDER (rà soát 2026-09-04)

    Không @include nào gọi partial này.

    Partial mồ côi.

    CHƯA XOÁ theo yêu cầu: chỉ đánh dấu để lần sau khỏi rà lại.
    Nếu bạn đấu view này vào một route/@include, hãy XOÁ dấu này —
    tests/Feature/View/DeadViewsMarkedTest.php sẽ báo đỏ để nhắc.
--}}
@php
    $formData = $formData ?? [];
    $warehouseQty = $formData['warehouseQty'] ?? [];
    $serialsByWarehouse = $formData['serialsByWarehouse'] ?? [];
    $totalQty = (int)($formData['totalQty'] ?? 0);
@endphp

<div class="tw:mb-2 tw:flex tw:justify-between tw:items-center">
    <label class="form-label tw:mb-0 tw:font-bold">Tồn kho theo kho</label>
    <div class="tw:flex tw:items-center tw:gap-2">
        <span class="tw:text-[rgba(33,37,41,0.75)]">Tổng:</span>
        <input type="text"
               id="total_qty_display"
               class="form-control form-control-sm tw:text-center tw:font-bold"
               style="width:100px"
               value="{{ (int)old('total_qty_display', $totalQty) }}"
               disabled>
    </div>
</div>

<div class="table-responsive mb-3">
    <table class="table table-bordered align-middle tw:mb-0">
        <thead class="table-light">
        <tr>
            <th>Kho</th>
            <th style="width:140px" class="tw:text-center">Số lượng</th>
            <th class="serial-column tw:text-center" style="width:180px;">Serial/IMEI</th>
        </tr>
        </thead>

        <tbody>
        @foreach($warehouses as $w)
            @php
                $currentQty = (int)($warehouseQty[$w->id] ?? 0);
                $existingSerials = $serialsByWarehouse[$w->id] ?? [];
                $existingSerials = is_array($existingSerials) ? $existingSerials : [];
                $serialCount = count($existingSerials);
            @endphp

            <tr>
                <td>
                    <div class="tw:font-semibold">{{ $w->name }}</div>
                    @if(!empty($w->location))
                        <div class="tw:text-[rgba(33,37,41,0.75)] small">{{ $w->location }}</div>
                    @endif
                </td>

                <td>
                    <input type="hidden"
                           name="stocks[{{ $w->id }}][warehouse_id]"
                           value="{{ $w->id }}">

                    <input type="number"
                           min="0"
                           class="form-control form-control-sm tw:text-center js-warehouse-qty"
                           data-warehouse-id="{{ $w->id }}"
                           name="stocks[{{ $w->id }}][qty]"
                           value="{{ old("stocks.{$w->id}.qty", $currentQty) }}">
                </td>

                <td class="serial-column tw:text-center">
                    <button type="button"
                            class="btn btn-sm {{ $serialCount > 0 ? 'btn-outline-success' : 'btn-outline-primary' }} js-open-serial"
                            data-warehouse-id="{{ $w->id }}"
                            data-warehouse-name="{{ $w->name }}">
                        <i class="bi bi-upc-scan"></i>
                        Nhập (<span id="serial-count-{{ $w->id }}">{{ $serialCount }}</span>)
                    </button>

                    <input type="hidden"
                           id="serials-{{ $w->id }}"
                           name="stocks[{{ $w->id }}][serials]"
                           value="{{ old("stocks.{$w->id}.serials", json_encode($existingSerials)) }}">
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<small class="tw:text-[rgba(33,37,41,0.75)] tw:block tw:mb-0">
    <i class="bi bi-info-circle"></i>
    Nếu bật Serial/IMEI: số lượng mỗi kho tự tính theo số serial đã nhập.
</small>
