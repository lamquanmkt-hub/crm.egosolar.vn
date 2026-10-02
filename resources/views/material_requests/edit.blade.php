{{-- Hai con số badge (đơn chờ duyệt, phiếu vật tư chờ duyệt) do
     App\Services\System\SidebarStatusService cấp cho partials.sidebar qua view
     composer. Trước đây đúng chỗ này có một khối 16 dòng CHÉP QUA 9 VIEW tự chạy
     lại hai câu COUNT rồi nuốt lỗi bằng catch(Throwable). Giá trị nó tính ra bị
     composer ghi đè nên không hiển thị ở đâu — chỉ tốn 2 câu truy vấn mỗi lần
     dựng trang. --}}


@extends('layouts.app')

@section('content')
@php
    $selectedSiteId = old('site_id', $materialRequest->site_id ?? request('site_id'));

    $stripPrefix = function ($note) {
        $note = (string) $note;
        $note = preg_replace('/^\[(Thiết bị chính - Trong kho|Vật tư phụ - Trong kho|Thiết bị chính - Ngoài kho|Vật tư phụ - Ngoài kho)\]\s*/u', '', $note);
        return trim($note);
    };

    $parseExternalNote = function ($note) use ($stripPrefix) {
        $note = trim((string) $note);

        $parts = array_values(array_filter(array_map('trim', explode('|', $note)), function ($x) {
            return $x !== '';
        }));

        $name = $parts[0] ?? '';
        $unit = '';
        $noteParts = [];

        foreach (array_slice($parts, 1) as $part) {
            if (preg_match('/^ĐVT:\s*(.*)$/u', $part, $m)) {
                $unit = trim($m[1] ?? '');
            } else {
                $noteParts[] = $part;
            }
        }

        return [
            'name' => $name,
            'unit' => $unit,
            'note' => $stripPrefix(implode(' | ', $noteParts)),
        ];
    };

    $oldItems = old('items', null);
    $oldExtra = old('extra_items', null);

    $mainStockRows = [];
    $subStockRows = [];
    $mainExternalRows = [];
    $subExternalRows = [];

    if (is_array($oldItems) || is_array($oldExtra)) {
        foreach ((array) $oldItems as $row) {
            $kind = $row['kind'] ?? '';
            $note = (string)($row['note'] ?? '');

            $row['note'] = $stripPrefix($note);

            if ($kind === 'sub_material' || strpos($note, '[Vật tư phụ - Trong kho]') !== false) {
                $subStockRows[] = $row;
            } else {
                $mainStockRows[] = $row;
            }
        }

        foreach ((array) $oldExtra as $row) {
            $kind = $row['kind'] ?? '';
            $note = (string)($row['note'] ?? '');

            $row['note'] = $stripPrefix($note);

            if ($kind === 'main_device' || strpos($note, '[Thiết bị chính - Ngoài kho]') !== false) {
                $mainExternalRows[] = $row;
            } else {
                $subExternalRows[] = $row;
            }
        }
    } else {
        foreach (($materialRequest->items ?? []) as $item) {
            $note = (string)($item->note ?? '');
            $qty = $item->qty ?? 1;

            if (!empty($item->product_id)) {
                $kind = strpos($note, '[Vật tư phụ - Trong kho]') !== false
                    ? 'sub_material'
                    : 'main_device';

                $row = [
                    'warehouse_id' => $materialRequest->warehouse_id ?? '',
                    'product_id' => $item->product_id,
                    'qty' => $qty,
                    'note' => $stripPrefix($note),
                    'kind' => $kind,
                ];

                if ($kind === 'sub_material') {
                    $subStockRows[] = $row;
                } else {
                    $mainStockRows[] = $row;
                }
            } else {
                $kind = strpos($note, '[Thiết bị chính - Ngoài kho]') !== false
                    ? 'main_device'
                    : 'sub_material';

                $parsed = $parseExternalNote($note);

                $row = [
                    'name' => $parsed['name'],
                    'qty' => $qty,
                    'unit' => $parsed['unit'],
                    'note' => $parsed['note'],
                    'kind' => $kind,
                ];

                if ($kind === 'main_device') {
                    $mainExternalRows[] = $row;
                } else {
                    $subExternalRows[] = $row;
                }
            }
        }
    }

    if (count($mainStockRows) === 0) {
        $mainStockRows = [[
            'warehouse_id' => $materialRequest->warehouse_id ?? '',
            'product_id' => '',
            'qty' => 1,
            'note' => '',
            'kind' => 'main_device',
        ]];
    }

    if (count($mainExternalRows) === 0) {
        $mainExternalRows = [[
            'name' => '',
            'qty' => 1,
            'unit' => '',
            'note' => '',
            'kind' => 'main_device',
        ]];
    }

    if (count($subStockRows) === 0) {
        $subStockRows = [[
            'warehouse_id' => $materialRequest->warehouse_id ?? '',
            'product_id' => '',
            'qty' => 1,
            'note' => '',
            'kind' => 'sub_material',
        ]];
    }

    if (count($subExternalRows) === 0) {
        $subExternalRows = [[
            'name' => '',
            'qty' => 1,
            'unit' => '',
            'note' => '',
            'kind' => 'sub_material',
        ]];
    }

    $sections = [
        [
            'type' => 'stock',
            'id' => 'mainStockTbody',
            'kind' => 'main_device',
            'title' => 'Thiết bị chính - Trong kho',
            'desc' => 'Chọn inverter, pin lưu trữ, tấm pin, smart meter... có trong kho. Giá vốn tự lấy từ kho/catalog.',
            'button' => 'Thêm thiết bị',
            'label' => 'Thiết bị',
            'placeholder' => '-- Chọn thiết bị --',
            'icon' => 'bi-cpu',
            'icon_class' => 'main-icon',
            'rows' => $mainStockRows,
        ],
        [
            'type' => 'external',
            'id' => 'mainExternalTbody',
            'kind' => 'main_device',
            'title' => 'Thiết bị chính - Ngoài kho',
            'desc' => 'Kỹ thuật nhập tên thiết bị và số lượng. Warehouse nhập giá vốn khi kho duyệt/xuất.',
            'button' => 'Thêm thiết bị ngoài kho',
            'label' => 'Tên thiết bị ngoài kho',
            'placeholder' => 'VD: Inverter phát sinh, pin lưu trữ ngoài kho...',
            'icon' => 'bi-cpu-fill',
            'icon_class' => 'outside-main-icon',
            'rows' => $mainExternalRows,
        ],
        [
            'type' => 'stock',
            'id' => 'subStockTbody',
            'kind' => 'sub_material',
            'title' => 'Vật tư phụ - Trong kho',
            'desc' => 'Dây, CB, rail, ốc vít, tủ điện, phụ kiện... có trong kho. Giá vốn tự lấy từ kho/catalog.',
            'button' => 'Thêm vật tư phụ',
            'label' => 'Vật tư phụ',
            'placeholder' => '-- Chọn vật tư --',
            'icon' => 'bi-hdd-stack',
            'icon_class' => 'sub-icon',
            'rows' => $subStockRows,
        ],
        [
            'type' => 'external',
            'id' => 'subExternalTbody',
            'kind' => 'sub_material',
            'title' => 'Vật tư phụ - Ngoài kho',
            'desc' => 'Vật tư phát sinh ngoài kho. Warehouse nhập giá vốn khi kho duyệt/xuất.',
            'button' => 'Thêm vật tư ngoài kho',
            'label' => 'Tên vật tư ngoài kho',
            'placeholder' => 'VD: Ống gen, phụ kiện phát sinh...',
            'icon' => 'bi-tools',
            'icon_class' => 'outside-sub-icon',
            'rows' => $subExternalRows,
        ],
    ];
@endphp

<div class="container-fluid tw:px-6 tw:py-4 ego-mr-form">

    <div class="tw:flex flex-wrap tw:justify-between tw:items-start tw:gap-2 tw:mb-4 ego-header">
        <div>
            <div class="tw:flex tw:items-center tw:gap-2 tw:mb-1">
                <span class="page-icon">
                    <i class="bi bi-pencil-square"></i>
                </span>
                <h4 class="tw:font-bold tw:mb-0">Sửa đơn vật tư #{{ $materialRequest->id }}</h4>
            </div>

            <div class="tw:text-[rgba(33,37,41,0.75)] small">
                Cập nhật thiết bị chính và vật tư phụ, trong kho và ngoài kho.
            </div>
        </div>

        <x-ui.button href="{{ route('material-requests.index') }}" variant="outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại
        </x-ui.button>
    </div>

    @if ($errors->any())
        <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]" style="border-radius:16px;">
            <div class="tw:font-semibold tw:mb-1">
                <i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại:
            </div>

            <ul class="tw:mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <form method="POST" action="{{ route('material-requests.update', $materialRequest->id) }}" id="materialRequestForm">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-lg-8">

                {{-- CÔNG TRÌNH --}}
                <x-ui.card class="border-0 shadow-ego ego-card" style="border-radius:18px;">
                    <x-ui.card-header class="bg-white border-0 tw:py-4" style="border-radius:18px 18px 0 0;">
                        <div class="tw:flex tw:items-center tw:gap-2">
                            <span class="icon-pill">
                                <i class="bi bi-buildings"></i>
                            </span>

                            <div>
                                <div class="tw:font-bold">Công trình</div>
                                <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                    Đơn vật tư sẽ link vào công trình để tính chi phí thực tế.
                                </div>
                            </div>
                        </div>
                    </x-ui.card-header>

                    <x-ui.card-body>
                        <div class="row g-3 tw:items-end">
                            <div class="col-md-8">
                                <x-ui.label>
                                    Chọn công trình <span class="tw:text-[#dc3545]">*</span>
                                </x-ui.label>

                                <x-ui.select name="site_id" id="site_id" class="mr-input" required>
                                    <option value="">-- Chọn công trình --</option>

                                    @foreach($sites as $site)
                                        <option value="{{ $site->id }}"
                                                data-name="{{ $site->name }}"
                                                data-address="{{ $site->address ?? '' }}"
                                                data-contact="{{ $site->contact_name ?? '' }}"
                                                data-phone="{{ $site->contact_phone ?? '' }}"
                                                data-contract="{{ $site->contract_amount ?? 0 }}"
                                                {{ (string)$selectedSiteId === (string)$site->id ? 'selected' : '' }}>
                                            #{{ $site->id }} - {{ $site->name }}
                                        </option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="col-md-4">
                                <x-ui.label>Ghi chú đơn</x-ui.label>

                                <x-ui.input name="note"
                                       class="mr-input"
                                       value="{{ old('note', $materialRequest->note ?? '') }}"
                                       placeholder="VD: Đợt 1, bổ sung vật tư..." />
                            </div>
                        </div>

                        <div class="site-preview tw:mt-4" id="sitePreview">
                            <div class="tw:flex tw:items-start tw:gap-2">
                                <div class="preview-icon">
                                    <i class="bi bi-info-circle"></i>
                                </div>

                                <div>
                                    <div class="tw:font-bold" id="sitePreviewName">Chưa chọn công trình</div>
                                    <div class="small tw:text-[rgba(33,37,41,0.75)]" id="sitePreviewAddress">
                                        Vui lòng chọn công trình để xem thông tin nhanh.
                                    </div>
                                    <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-1" id="sitePreviewContact"></div>
                                </div>
                            </div>
                        </div>
                    </x-ui.card-body>
                </x-ui.card>

                {{-- 4 PHẦN VẬT TƯ --}}
                @foreach($sections as $section)
                    <x-ui.card class="border-0 shadow-ego ego-card tw:mt-4" style="border-radius:18px;">
                        <x-ui.card-header class="bg-white border-0 tw:py-4 tw:flex flex-wrap tw:justify-between tw:items-center tw:gap-2" style="border-radius:18px 18px 0 0;">
                            <div>
                                <div class="tw:flex tw:items-center tw:gap-2">
                                    <span class="icon-pill {{ $section['icon_class'] }}">
                                        <i class="bi {{ $section['icon'] }}"></i>
                                    </span>

                                    <div class="tw:font-bold">{{ $section['title'] }}</div>
                                </div>

                                <div class="tw:text-[rgba(33,37,41,0.75)] small tw:mt-1">
                                    {{ $section['desc'] }}
                                </div>
                            </div>

                            @if($section['type'] === 'stock')
                                <x-ui.button variant="none" size="none" class="btn-outline-ego btnAddStock tw:py-[6px] tw:px-3" type="button" data-target="{{ $section['id'] }}">
                                    <i class="bi bi-plus-lg"></i> {{ $section['button'] }}
                                </x-ui.button>
                            @else
                                <x-ui.button variant="none" size="none" class="btn-outline-ego btnAddExternal tw:py-[6px] tw:px-3" type="button" data-target="{{ $section['id'] }}">
                                    <i class="bi bi-plus-lg"></i> {{ $section['button'] }}
                                </x-ui.button>
                            @endif
                        </x-ui.card-header>

                        <x-ui.card-body class="tw:p-0">
                            <div class="table-responsive">

                                @if($section['type'] === 'stock')
                                    <table class="table table-hover align-middle tw:mb-0 ego-table">
                                        <thead class="table-light">
                                        <tr>
                                            <th style="width:54px" class="tw:text-center">#</th>
                                            <th style="min-width:180px">Kho</th>
                                            <th style="min-width:300px">{{ $section['label'] }}</th>
                                            <th style="width:110px" class="tw:text-right">Tồn</th>
                                            <th style="width:110px">ĐVT</th>
                                            <th style="width:150px" class="tw:text-right">Giá vốn</th>
                                            <th style="width:130px" class="tw:text-right">Số lượng</th>
                                            <th style="width:150px" class="tw:text-right">Tạm tính</th>
                                            <th style="min-width:190px">Ghi chú</th>
                                            <th style="width:75px" class="tw:text-right">Xóa</th>
                                        </tr>
                                        </thead>

                                        <tbody id="{{ $section['id'] }}"
                                               class="stock-tbody"
                                               data-kind="{{ $section['kind'] }}"
                                               data-placeholder="{{ $section['placeholder'] }}">
                                        @foreach($section['rows'] as $row)
                                            <tr class="stock-row">
                                                <td class="tw:text-center stock-idx">1</td>

                                                <td>
                                                    <x-ui.select class="mr-input stock-warehouse"
                                                            data-old="{{ $row['warehouse_id'] ?? '' }}">
                                                        <option value="">-- Chọn kho --</option>

                                                        @foreach($warehouses as $warehouse)
                                                            <option value="{{ $warehouse->id }}"
                                                                    {{ (string)($row['warehouse_id'] ?? '') === (string)$warehouse->id ? 'selected' : '' }}>
                                                                {{ $warehouse->name }}
                                                            </option>
                                                        @endforeach
                                                    </x-ui.select>
                                                </td>

                                                <td>
                                                    <x-ui.input type="search" class="mr-input stock-product-search tw:mb-2"
                                                           placeholder="Gõ tên hoặc SKU để tìm..." autocomplete="off" />
                                                    <x-ui.select class="mr-input stock-product"
                                                            data-old="{{ $row['product_id'] ?? '' }}">
                                                        <option value="">{{ $section['placeholder'] }}</option>
                                                    </x-ui.select>
                                                </td>

                                                <td class="tw:text-right">
                                                    <span class="stock-available badge bg-light tw:text-[#212529] border">—</span>
                                                </td>

                                                <td>
                                                    <span class="stock-unit pill-soft pill-muted">—</span>
                                                </td>

                                                <td class="tw:text-right">
                                                    <span class="stock-cost tw:font-bold tw:text-[#198754]">0 đ</span>
                                                </td>

                                                <td>
                                                    <x-ui.input type="number"
                                                           min="0"
                                                           step="any"
                                                           class="mr-input tw:text-right stock-qty"
                                                           value="{{ $row['qty'] ?? 1 }}" />
                                                </td>

                                                <td class="tw:text-right">
                                                    <span class="stock-line-total tw:font-bold">0 đ</span>
                                                </td>

                                                <td>
                                                    <x-ui.input class="mr-input stock-note"
                                                           value="{{ $row['note'] ?? '' }}"
                                                           placeholder="Ghi chú..." />
                                                </td>

                                                <td class="tw:text-right">
                                                    <x-ui.button type="button"
                                                            variant="outline-danger" size="sm" class="btnRemoveRow">
                                                        <i class="bi bi-trash"></i>
                                                    </x-ui.button>
                                                </td>

                                                <input type="hidden" class="stock-kind" value="{{ $section['kind'] }}">
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @else
                                    <table class="table table-hover align-middle tw:mb-0 ego-table">
                                        <thead class="table-light">
                                        <tr>
                                            <th style="width:54px" class="tw:text-center">#</th>
                                            <th style="min-width:300px">{{ $section['label'] }}</th>
                                            <th style="width:140px" class="tw:text-right">Số lượng</th>
                                            <th style="width:140px">Đơn vị</th>
                                            <th style="width:160px" class="tw:text-right">Giá vốn</th>
                                            <th style="min-width:240px">Ghi chú</th>
                                            <th style="width:75px" class="tw:text-right">Xóa</th>
                                        </tr>
                                        </thead>

                                        <tbody id="{{ $section['id'] }}"
                                               class="external-tbody"
                                               data-kind="{{ $section['kind'] }}">
                                        @foreach($section['rows'] as $row)
                                            <tr class="external-row">
                                                <td class="tw:text-center external-idx">1</td>

                                                <td>
                                                    <x-ui.input class="mr-input external-name"
                                                           value="{{ $row['name'] ?? '' }}"
                                                           placeholder="{{ $section['placeholder'] }}" />
                                                </td>

                                                <td>
                                                    <x-ui.input type="number"
                                                           min="0"
                                                           step="any"
                                                           class="mr-input tw:text-right external-qty"
                                                           value="{{ $row['qty'] ?? 1 }}" />
                                                </td>

                                                <td>
                                                    <x-ui.input class="mr-input external-unit"
                                                           value="{{ $row['unit'] ?? '' }}"
                                                           placeholder="m/cái/bộ" />
                                                </td>

                                                <td class="tw:text-right">
                                                    <span class="tw:text-[rgba(33,37,41,0.75)] small">Kho nhập khi duyệt</span>
                                                </td>

                                                <td>
                                                    <x-ui.input class="mr-input external-note"
                                                           value="{{ $row['note'] ?? '' }}"
                                                           placeholder="Ghi chú..." />
                                                </td>

                                                <td class="tw:text-right">
                                                    <x-ui.button type="button"
                                                            variant="outline-danger" size="sm" class="btnRemoveRow">
                                                        <i class="bi bi-trash"></i>
                                                    </x-ui.button>
                                                </td>

                                                <input type="hidden" class="external-kind" value="{{ $section['kind'] }}">
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>

                            @if($section['type'] === 'external')
                                <div class="tw:p-4 border-top">
                                    <div class="rule-box warning">
                                        <div class="tw:flex tw:gap-2 tw:items-start">
                                            <div class="rule-icon">
                                                <i class="bi bi-exclamation-circle"></i>
                                            </div>

                                            <div>
                                                <div class="tw:font-semibold">Lưu ý cho warehouse</div>
                                                <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                                    Hàng ngoài kho chưa có giá vốn ở bước sửa đơn.
                                                    Khi kho duyệt/xuất, warehouse phải nhập giá vốn để cộng chi phí về công trình.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </x-ui.card-body>
                    </x-ui.card>
                @endforeach

                <div id="payloadFields"></div>
            </div>

            {{-- RIGHT --}}
            <div class="col-lg-4">
                <div class="sticky-top" style="top:90px;">
                    <x-ui.card class="border-0 shadow-ego ego-card tw:mb-4" style="border-radius:18px;">
                        <x-ui.card-header class="bg-white border-0 tw:py-4" style="border-radius:18px 18px 0 0;">
                            <div class="tw:flex tw:items-center tw:gap-2">
                                <span class="icon-pill">
                                    <i class="bi bi-card-checklist"></i>
                                </span>

                                <div>
                                    <div class="tw:font-bold">Tóm tắt đơn vật tư</div>
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Kiểm tra nhanh trước khi lưu.</div>
                                </div>
                            </div>
                        </x-ui.card-header>

                        <x-ui.card-body>
                            <div class="summary-grid">
                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Thiết bị trong kho</div>
                                    <div class="tw:font-bold" id="mainStockCount">0</div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Thiết bị ngoài kho</div>
                                    <div class="tw:font-bold" id="mainExternalCount">0</div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Vật tư phụ trong kho</div>
                                    <div class="tw:font-bold" id="subStockCount">0</div>
                                </div>

                                <div class="summary-item">
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">Vật tư phụ ngoài kho</div>
                                    <div class="tw:font-bold" id="subExternalCount">0</div>
                                </div>
                            </div>

                            <div class="finance-summary tw:mt-4">
                                <div class="tw:flex tw:justify-between tw:gap-2">
                                    <span class="tw:text-[rgba(33,37,41,0.75)]">Giá vốn trong kho tạm tính</span>
                                    <strong id="stockCostTotal">0 đ</strong>
                                </div>

                                <div class="small tw:text-[rgba(33,37,41,0.75)] tw:mt-1">
                                    Vật tư ngoài kho chưa tính giá vốn ở bước này.
                                </div>
                            </div>

                            <div class="summary-hint tw:mt-4">
                                <div class="tw:flex tw:items-start tw:gap-2">
                                    <div class="hint-ic">
                                        <i class="bi bi-diagram-3"></i>
                                    </div>

                                    <div>
                                        <div class="tw:font-semibold">Luồng xử lý</div>
                                        <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                            Lưu nháp → gửi admin duyệt → admin duyệt → kho duyệt/xuất
                                            → cộng chi phí thực tế về công trình.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </x-ui.card-body>

                        <x-ui.card-footer class="bg-white border-0 tw:p-4" style="border-radius:0 0 18px 18px;">
                            <x-ui.button variant="none" size="none" type="submit" class="btn-ego tw:w-full" id="btnSubmitForm">
                                <i class="bi bi-save"></i> Lưu thay đổi
                            </x-ui.button>

                            <x-ui.button href="{{ route('material-requests.index') }}" variant="outline-secondary" class="tw:w-full tw:mt-2">
                                Quay lại
                            </x-ui.button>
                        </x-ui.card-footer>
                    </x-ui.card>

                    <x-ui.card class="border-0 shadow-ego ego-card" style="border-radius:18px;">
                        <x-ui.card-body>
                            <div class="tw:flex tw:gap-2 tw:items-start">
                                <div class="mini-icon">
                                    <i class="bi bi-cash-coin"></i>
                                </div>

                                <div>
                                    <div class="tw:font-bold">Quy tắc giá vốn</div>
                                    <div class="tw:text-[rgba(33,37,41,0.75)] small">
                                        Hàng trong kho tự lấy giá vốn từ kho/catalog.
                                        Hàng ngoài kho warehouse nhập giá vốn lúc duyệt/xuất.
                                    </div>
                                </div>
                            </div>
                        </x-ui.card-body>
                    </x-ui.card>
                </div>
            </div>
        </div>

    </form>
</div>

<script>
(function(){
    const inventories = @json($inventories ?? []);
    @php
        $egoMrCurrentProductMap = [];

        try {
            $egoMrCurrentItems = collect($materialRequest->items ?? [])
                ->filter(fn ($item) => !empty($item->product_id));

            $egoMrCurrentProductIds = $egoMrCurrentItems
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $egoMrProducts = collect();

            if ($egoMrCurrentProductIds->isNotEmpty() && \App\Support\SchemaCache::hasTable('crm_product_catalog')) {
                $egoMrProducts = \Illuminate\Support\Facades\DB::table('crm_product_catalog')
                    ->whereIn('id', $egoMrCurrentProductIds->all())
                    ->get()
                    ->keyBy('id');
            }

            foreach ($egoMrCurrentItems as $egoMrItem) {
                $egoMrProductId = (int) $egoMrItem->product_id;
                $egoMrProduct = $egoMrProducts->get($egoMrProductId) ?: ($egoMrItem->product ?? null);

                $egoMrProductName = is_object($egoMrProduct) ? (string) ($egoMrProduct->name ?? '') : '';
                $egoMrProductUnit = is_object($egoMrProduct) ? (string) ($egoMrProduct->unit ?? '') : '';
                $egoMrProductVat = is_object($egoMrProduct) ? (float) ($egoMrProduct->cost_vat_percent ?? $egoMrProduct->vat_percent ?? 0) : 0;
                $egoMrProductCost = is_object($egoMrProduct) ? (float) ($egoMrProduct->price_agent_vat ?? $egoMrProduct->price_agent ?? $egoMrProduct->price ?? 0) : 0;

                $egoMrUnitCost = (float) ($egoMrItem->unit_cost ?? 0);

                if ($egoMrUnitCost <= 0) {
                    $egoMrUnitCost = $egoMrProductCost;
                }

                $egoMrCurrentProductMap[(string) $egoMrProductId] = [
                    'warehouse_id' => (string) ($materialRequest->warehouse_id ?? ''),
                    'product_id' => $egoMrProductId,
                    'product_name' => $egoMrProductName !== '' ? $egoMrProductName : ('Sản phẩm #' . $egoMrProductId),
                    'quantity' => 0,
                    'unit' => (string) ($egoMrItem->unit ?? $egoMrProductUnit ?? ''),
                    'unit_cost' => $egoMrUnitCost,
                    'vat_percent' => (float) ($egoMrItem->vat_percent ?? $egoMrProductVat ?? 0),
                    'cost_label' => number_format($egoMrUnitCost, 0, ',', '.') . ' đ',
                ];
            }
        } catch (\Throwable $e) {
            $egoMrCurrentProductMap = [];
        }
    @endphp
    const currentItemProducts = @json($egoMrCurrentProductMap ?? []);
    const form = document.getElementById('materialRequestForm');

    function qsAll(sel, root = document){
        return Array.from(root.querySelectorAll(sel));
    }

    function num(v){
        const n = parseFloat(v || '0');
        return Number.isFinite(n) ? n : 0;
    }

    function money(v){
        return Number(v || 0).toLocaleString('vi-VN') + ' đ';
    }

    function qty(v){
        return Number(v || 0).toLocaleString('vi-VN');
    }

    function rowHasStockData(row){
        return !!(row.querySelector('.stock-product')?.value || '');
    }

    function rowHasExternalData(row){
        return !!((row.querySelector('.external-name')?.value || '').trim() !== '');
    }

    function selectedInventory(row){
        const productSelect = row.querySelector('.stock-product');
        const selected = productSelect?.options[productSelect.selectedIndex];

        if (!selected || !selected.dataset.inventory) {
            return null;
        }

        try {
            return JSON.parse(selected.dataset.inventory);
        } catch (e) {
            return null;
        }
    }

    function fillProducts(row){
        const warehouseSelect = row.querySelector('.stock-warehouse');
        const productSelect = row.querySelector('.stock-product');
        const tbody = row.closest('.stock-tbody');
        const placeholder = tbody?.dataset.placeholder || '-- Chọn vật tư --';

        if (!warehouseSelect || !productSelect) return;

        const warehouseId = String(warehouseSelect.value || '');
        const oldProductId = String(productSelect.dataset.old || productSelect.value || '');

        productSelect.innerHTML = '<option value="">' + placeholder + '</option>';

        inventories
            .filter(item => String(item.warehouse_id) === warehouseId)
            .forEach(item => {
                const opt = document.createElement('option');
                const savedMeta = currentItemProducts[String(item.product_id)] || null;
                const displayItem = Object.assign({}, item);

                if (
                    savedMeta
                    && String(item.product_id) === oldProductId
                    && num(savedMeta.unit_cost || 0) > 0
                ) {
                    displayItem.unit_cost = num(savedMeta.unit_cost || 0);
                    displayItem.vat_percent = num(savedMeta.vat_percent || 0);
                    displayItem.unit = savedMeta.unit || displayItem.unit || '';
                }

                opt.value = displayItem.product_id;
                opt.textContent = displayItem.product_name
                    + ' — tồn: ' + qty(displayItem.quantity)
                    + ' — giá vốn: ' + money(displayItem.unit_cost || 0);

                opt.dataset.inventory = JSON.stringify(displayItem);

                if (String(displayItem.product_id) === oldProductId) {
                    opt.selected = true;
                }

                productSelect.appendChild(opt);
            });
        function egoAppendCurrentProductFallback(){
            if (!oldProductId) return;

            const exists = Array.from(productSelect.options)
                .some(opt => String(opt.value) === oldProductId);

            if (exists) return;

            const meta = currentItemProducts[oldProductId] || {};
            const oldName = meta.product_name || ('Sản phẩm #' + oldProductId);
            const oldUnitCost = num(meta.unit_cost || 0);
            const oldQty = num(meta.quantity || 0);

            const fallback = {
                warehouse_id: warehouseId,
                product_id: oldProductId,
                product_name: oldName,
                quantity: oldQty,
                unit: meta.unit || '',
                unit_cost: oldUnitCost,
                vat_percent: num(meta.vat_percent || 0),
                cost_label: money(oldUnitCost)
            };

            const opt = document.createElement('option');
            opt.value = oldProductId;
            opt.textContent = oldName
                + ' — tồn: ' + qty(oldQty)
                + ' — giá vốn: ' + money(oldUnitCost)
                + ' — đang có trong đơn';
            opt.dataset.inventory = JSON.stringify(fallback);
            opt.selected = true;
            productSelect.appendChild(opt);
        }

        egoAppendCurrentProductFallback();
        productSelect.dataset.old = '';
        updateStockRow(row);
    }

    function filterProductOptions(row, keyword){
        const select = row.querySelector('.stock-product');
        if (!select) return;
        const query = String(keyword || '').trim().toLocaleLowerCase('vi');
        Array.from(select.options).forEach((option, index) => {
            if (index === 0 || option.selected) {
                option.hidden = false;
                return;
            }
            option.hidden = query !== '' && !option.textContent.toLocaleLowerCase('vi').includes(query);
        });
        if (query !== '') select.focus();
    }

    function updateStockRow(row){
        const inv = selectedInventory(row);
        const availableEl = row.querySelector('.stock-available');
        const unitEl = row.querySelector('.stock-unit');
        const costEl = row.querySelector('.stock-cost');
        const totalEl = row.querySelector('.stock-line-total');
        const qtyValue = num(row.querySelector('.stock-qty')?.value);

        if (!inv) {
            if (availableEl) availableEl.textContent = '—';
            if (unitEl) unitEl.textContent = '—';
            if (costEl) costEl.textContent = '0 đ';
            if (totalEl) totalEl.textContent = '0 đ';
            return;
        }

        const unitCost = num(inv.unit_cost);
        const lineTotal = unitCost * qtyValue;

        if (availableEl) availableEl.textContent = qty(inv.quantity || 0);
        if (unitEl) unitEl.textContent = inv.unit || '—';
        if (costEl) costEl.textContent = money(unitCost);
        if (totalEl) totalEl.textContent = money(lineTotal);
    }

    function renumberStock(tbody){
        const rows = qsAll('.stock-row', tbody);

        rows.forEach((row, index) => {
            const idx = row.querySelector('.stock-idx');
            if (idx) idx.textContent = index + 1;

            const removeBtn = row.querySelector('.btnRemoveRow');
            if (removeBtn) removeBtn.disabled = rows.length === 1;
        });
    }

    function renumberExternal(tbody){
        const rows = qsAll('.external-row', tbody);

        rows.forEach((row, index) => {
            const idx = row.querySelector('.external-idx');
            if (idx) idx.textContent = index + 1;

            const removeBtn = row.querySelector('.btnRemoveRow');
            if (removeBtn) removeBtn.disabled = rows.length === 1;
        });
    }

    function cloneStockRow(tbody){
        const first = tbody.querySelector('.stock-row');
        const clone = first.cloneNode(true);

        qsAll('select', clone).forEach(select => {
            select.value = '';
            select.dataset.old = '';
        });

        qsAll('input', clone).forEach(input => {
            if (input.classList.contains('stock-qty')) {
                input.value = 1;
            } else if (input.classList.contains('stock-kind')) {
                input.value = tbody.dataset.kind || '';
            } else {
                input.value = '';
            }
        });

        clone.querySelector('.stock-available').textContent = '—';
        clone.querySelector('.stock-unit').textContent = '—';
        clone.querySelector('.stock-cost').textContent = '0 đ';
        clone.querySelector('.stock-line-total').textContent = '0 đ';

        return clone;
    }

    function cloneExternalRow(tbody){
        const first = tbody.querySelector('.external-row');
        const clone = first.cloneNode(true);

        qsAll('input', clone).forEach(input => {
            if (input.classList.contains('external-qty')) {
                input.value = 1;
            } else if (input.classList.contains('external-kind')) {
                input.value = tbody.dataset.kind || '';
            } else {
                input.value = '';
            }
        });

        return clone;
    }

    function calcSummary(){
        let mainStockCount = 0;
        let subStockCount = 0;
        let mainExternalCount = 0;
        let subExternalCount = 0;
        let stockCostTotal = 0;

        qsAll('.stock-tbody').forEach(tbody => {
            const kind = tbody.dataset.kind || '';

            qsAll('.stock-row', tbody).forEach(row => {
                if (!rowHasStockData(row)) return;

                if (kind === 'main_device') {
                    mainStockCount++;
                } else {
                    subStockCount++;
                }

                const inv = selectedInventory(row);
                const unitCost = inv ? num(inv.unit_cost) : 0;
                const q = num(row.querySelector('.stock-qty')?.value);

                stockCostTotal += unitCost * q;
            });
        });

        qsAll('.external-tbody').forEach(tbody => {
            const kind = tbody.dataset.kind || '';

            qsAll('.external-row', tbody).forEach(row => {
                if (!rowHasExternalData(row)) return;

                if (kind === 'main_device') {
                    mainExternalCount++;
                } else {
                    subExternalCount++;
                }
            });
        });

        document.getElementById('mainStockCount').textContent = mainStockCount;
        document.getElementById('subStockCount').textContent = subStockCount;
        document.getElementById('mainExternalCount').textContent = mainExternalCount;
        document.getElementById('subExternalCount').textContent = subExternalCount;
        document.getElementById('stockCostTotal').textContent = money(stockCostTotal);
    }

    function syncSitePreview(){
        const siteSelect = document.getElementById('site_id');
        const selected = siteSelect?.options[siteSelect.selectedIndex];

        const nameEl = document.getElementById('sitePreviewName');
        const addressEl = document.getElementById('sitePreviewAddress');
        const contactEl = document.getElementById('sitePreviewContact');

        if (!selected || !selected.value) {
            nameEl.textContent = 'Chưa chọn công trình';
            addressEl.textContent = 'Vui lòng chọn công trình để xem thông tin nhanh.';
            contactEl.textContent = '';
            return;
        }

        nameEl.textContent = selected.dataset.name || selected.textContent || 'Công trình';
        addressEl.textContent = selected.dataset.address || 'Chưa có địa chỉ';

        const contact = selected.dataset.contact || '';
        const phone = selected.dataset.phone || '';

        contactEl.textContent = (contact || phone)
            ? 'Liên hệ: ' + [contact, phone].filter(Boolean).join(' - ')
            : '';
    }

    function buildPayload(){
        const payload = document.getElementById('payloadFields');
        payload.innerHTML = '';

        let itemIndex = 0;
        let extraIndex = 0;

        function addHidden(name, value){
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value ?? '';
            payload.appendChild(input);
        }

        qsAll('.stock-tbody').forEach(tbody => {
            const kind = tbody.dataset.kind || '';

            qsAll('.stock-row', tbody).forEach(row => {
                const warehouseId = row.querySelector('.stock-warehouse')?.value || '';
                const productId = row.querySelector('.stock-product')?.value || '';
                const quantityRaw = row.querySelector('.stock-qty')?.value || '';
                const noteRaw = row.querySelector('.stock-note')?.value || '';

                if (!productId) return;

                const quantity = num(quantityRaw) > 0 ? quantityRaw : 1;

                const prefix = kind === 'main_device'
                    ? '[Thiết bị chính - Trong kho]'
                    : '[Vật tư phụ - Trong kho]';

                const note = (prefix + ' ' + noteRaw).trim();

                const inv = selectedInventory(row);
                const unitCost = inv ? num(inv.unit_cost) : 0;
                const vatPercent = inv ? num(inv.vat_percent) : 0;

                addHidden(`items[${itemIndex}][warehouse_id]`, warehouseId);
                addHidden(`items[${itemIndex}][product_id]`, productId);
                addHidden(`items[${itemIndex}][qty]`, quantity);
                addHidden(`items[${itemIndex}][note]`, note);
                addHidden(`items[${itemIndex}][kind]`, kind);
                addHidden(`items[${itemIndex}][unit_cost]`, unitCost);
                addHidden(`items[${itemIndex}][vat_percent]`, vatPercent);

                itemIndex++;
            });
        });

        qsAll('.external-tbody').forEach(tbody => {
            const kind = tbody.dataset.kind || '';

            qsAll('.external-row', tbody).forEach(row => {
                const name = row.querySelector('.external-name')?.value || '';
                const quantityRaw = row.querySelector('.external-qty')?.value || '';
                const unit = row.querySelector('.external-unit')?.value || '';
                const noteRaw = row.querySelector('.external-note')?.value || '';

                if (name.trim() === '') return;

                const quantity = num(quantityRaw) > 0 ? quantityRaw : 1;

                const prefix = kind === 'main_device'
                    ? '[Thiết bị chính - Ngoài kho]'
                    : '[Vật tư phụ - Ngoài kho]';

                const note = (prefix + ' ' + noteRaw).trim();

                addHidden(`extra_items[${extraIndex}][name]`, name);
                addHidden(`extra_items[${extraIndex}][qty]`, quantity);
                addHidden(`extra_items[${extraIndex}][unit]`, unit);
                addHidden(`extra_items[${extraIndex}][note]`, note);
                addHidden(`extra_items[${extraIndex}][kind]`, kind);

                extraIndex++;
            });
        });
    }

    document.getElementById('site_id')?.addEventListener('change', syncSitePreview);

    qsAll('.btnAddStock').forEach(btn => {
        btn.addEventListener('click', () => {
            const tbody = document.getElementById(btn.dataset.target);
            tbody.appendChild(cloneStockRow(tbody));
            renumberStock(tbody);
            calcSummary();
        });
    });

    qsAll('.btnAddExternal').forEach(btn => {
        btn.addEventListener('click', () => {
            const tbody = document.getElementById(btn.dataset.target);
            tbody.appendChild(cloneExternalRow(tbody));
            renumberExternal(tbody);
            calcSummary();
        });
    });

    document.addEventListener('click', e => {
        const btn = e.target.closest('.btnRemoveRow');
        if (!btn) return;

        const stockTbody = btn.closest('.stock-tbody');
        const externalTbody = btn.closest('.external-tbody');

        if (stockTbody) {
            const rows = qsAll('.stock-row', stockTbody);
            if (rows.length <= 1) return;

            btn.closest('.stock-row')?.remove();
            renumberStock(stockTbody);
            calcSummary();
            return;
        }

        if (externalTbody) {
            const rows = qsAll('.external-row', externalTbody);
            if (rows.length <= 1) return;

            btn.closest('.external-row')?.remove();
            renumberExternal(externalTbody);
            calcSummary();
        }
    });

    document.addEventListener('change', e => {
        const stockRow = e.target.closest('.stock-row');

        if (stockRow && e.target.matches('.stock-warehouse')) {
            const productSelect = stockRow.querySelector('.stock-product');
            if (productSelect) productSelect.dataset.old = '';
            fillProducts(stockRow);
            calcSummary();
        }

        if (stockRow && e.target.matches('.stock-product-search')) {
            filterProductOptions(stockRow, e.target.value);
        }

        if (stockRow && e.target.matches('.stock-product')) {
            updateStockRow(stockRow);
            calcSummary();
        }
    });

    document.addEventListener('input', e => {
        const stockRow = e.target.closest('.stock-row');
        const externalRow = e.target.closest('.external-row');

        if (stockRow && e.target.matches('.stock-product-search')) {
            filterProductOptions(stockRow, e.target.value);
            return;
        }

        if (stockRow) {
            updateStockRow(stockRow);
            calcSummary();
        }

        if (externalRow) {
            calcSummary();
        }
    });

    form?.addEventListener('submit', () => {
        buildPayload();
    });

    qsAll('.stock-tbody').forEach(tbody => {
        qsAll('.stock-row', tbody).forEach(row => fillProducts(row));
        renumberStock(tbody);
    });

    qsAll('.external-tbody').forEach(tbody => {
        renumberExternal(tbody);
    });

    syncSitePreview();
    calcSummary();
    buildPayload();
})();
</script>

<style> .ego-mr-form{
        background:
            radial-gradient(circle at top left, rgba(11,201,170,.13), transparent 26%),
            linear-gradient(180deg, rgba(11,201,170,.10), rgba(11,201,170,.05) 28%, rgba(255,255,255,0) 75%);
        border-radius: 22px;
        padding-top: 18px;
        padding-bottom: 18px;
    }.ego-header{
        margin-top: 6px;
    }.page-icon{
        width: 42px;
        height: 42px;
        border-radius: 16px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background: linear-gradient(135deg, rgba(11,201,170,.20), rgba(59,130,246,.13));
        color:#0f766e;
        border:1px solid rgba(11,201,170,.22);
        box-shadow:0 12px 26px rgba(2,44,34,.08);
    }.ego-card{
        background: rgba(255,255,255,.96);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(15,118,110,.07) !important;
    }.shadow-ego{
        box-shadow: 0 14px 38px rgba(2,44,34,.08) !important;
    }.btn-ego{
        background: linear-gradient(135deg, #0BC9AA, #08b79b);
        border:0;
        color:#fff;
        border-radius:14px;
        padding:11px 14px;
        font-weight:800;
        box-shadow:0 10px 22px rgba(11,201,170,.26);
    }.btn-ego:hover{
        color:#fff;
        transform: translateY(-1px);
        box-shadow:0 14px 28px rgba(11,201,170,.32);
    }.btn-outline-ego{
        border-color: rgba(11,201,170,.55);
        color:#0f766e;
        background: rgba(11,201,170,.10);
        border-radius:13px;
        font-weight:700;
    }.btn-outline-ego:hover{
        border-color: rgba(11,201,170,.75);
        background: rgba(11,201,170,.16);
        color:#0f766e;
    }.icon-pill{
        width:36px;
        height:36px;
        border-radius:13px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(11,201,170,.12);
        color:#0f766e;
        border:1px solid rgba(0,0,0,.06);
        flex:0 0 auto;
    }.main-icon{
        background: rgba(59,130,246,.12);
        color:#1d4ed8;
    }.sub-icon{
        background: rgba(11,201,170,.12);
        color:#0f766e;
    }.outside-main-icon,
    .outside-sub-icon{
        background: rgba(245,158,11,.14);
        color:#b45309;
    }.mr-input{
        border-radius:13px;
        padding-top:.58rem;
        padding-bottom:.58rem;
        border-color: rgba(15,23,42,.12);
    }.mr-input:focus{
        border-color: rgba(11,201,170,.7);
        box-shadow: 0 0 0 .2rem rgba(11,201,170,.12);
    }.site-preview{
        border:1px solid rgba(11,201,170,.18);
        background: rgba(11,201,170,.06);
        border-radius:16px;
        padding:13px;
    }.preview-icon,
    .rule-icon,
    .hint-ic,
    .mini-icon{
        width:34px;
        height:34px;
        border-radius:12px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: rgba(11,201,170,.12);
        color:#0f766e;
        border:1px solid rgba(0,0,0,.06);
        flex:0 0 auto;
    }.rule-box{
        border:1px solid rgba(11,201,170,.18);
        background: rgba(11,201,170,.06);
        border-radius:16px;
        padding:13px;
    }.rule-box.warning{
        border-color: rgba(245,158,11,.22);
        background: rgba(245,158,11,.08);
    }.rule-box.warning .rule-icon{
        background: rgba(245,158,11,.14);
        color:#b45309;
    }.ego-table thead th{
        background:#f8fafc;
        color:#334155;
        font-size:.85rem;
        white-space:nowrap;
        vertical-align:middle;
        border-bottom:1px solid rgba(0,0,0,.06) !important;
    }.ego-table tbody td{
        padding-top:.8rem;
        padding-bottom:.8rem;
        vertical-align:middle;
        border-top:1px solid rgba(0,0,0,.04) !important;
    }.ego-table tbody tr:hover{
        background: rgba(11,201,170,.055);
    }.ego-table .mr-input{
        min-height:42px;
        font-size:14px;
    }.btnRemoveRow{
        width:42px;
        height:42px;
        border-radius:12px;
    }.pill-soft{
        display:inline-flex;
        align-items:center;
        gap:6px;
        padding:6px 10px;
        border-radius:999px;
        border:1px solid rgba(0,0,0,.06);
        font-size:12px;
        line-height:1;
        white-space:nowrap;
        font-weight:700;
    }.pill-muted{
        background:rgba(148,163,184,.12);
        color:#64748b;
        border-color:rgba(148,163,184,.22);
    }.summary-grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:10px;
    }.summary-item{
        border:1px solid rgba(0,0,0,.06);
        background: rgba(11,201,170,.06);
        border-radius:15px;
        padding:12px;
    }.finance-summary{
        border:1px solid rgba(11,201,170,.18);
        background: rgba(11,201,170,.06);
        border-radius:15px;
        padding:12px;
    }.summary-hint{
        border:1px solid rgba(0,0,0,.06);
        background: rgba(255,255,255,.88);
        border-radius:15px;
        padding:12px;
    }@media (max-width: 991.98px){.sticky-top{
            position: static !important;
        }
    }@media (max-width: 575.98px){.summary-grid{
            grid-template-columns:1fr;
        }
    }
</style>

@endsection
