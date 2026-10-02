@extends('layouts.app')

@include('products.partials.enterprise-assets')

@section('title', 'Nhập sản phẩm')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-goods-receipts-premium.css') }}?v={{ file_exists(public_path('css/ego-goods-receipts-premium.css')) ? filemtime(public_path('css/ego-goods-receipts-premium.css')) : '4.0.0' }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/ego-goods-receipts-premium.js') }}?v={{ file_exists(public_path('js/ego-goods-receipts-premium.js')) ? filemtime(public_path('js/ego-goods-receipts-premium.js')) : '4.0.0' }}" defer></script>
@endpush

@section('content')
@php
    $payMap = [
        'unpaid' => ['Chưa thanh toán', 'danger'],
        'partial' => ['Thanh toán một phần', 'warning'],
        'paid' => ['Đã thanh toán', 'success'],
    ];

    $statusMap = [
        'draft' => ['Nháp', 'warning'],
        'posted' => ['Đã nhập kho', 'success'],
    ];

    $productPolicyModel = class_exists(\App\Models\Inventory\Catalog\Product::class)
        ? \App\Models\Inventory\Catalog\Product::class
        : null;

    $canCreateProduct = $productPolicyModel
        ? (auth()->user()?->can('create', $productPolicyModel) ?? false)
        : false;

    $productPickerData = $products->map(function ($product) {
        return [
            'id' => (int) $product->id,
            'sku' => (string) ($product->sku ?? ''),
            'name' => (string) ($product->name ?? ''),
            'unit' => (string) ($product->unit ?? ''),
            'stock' => (float) ($product->stock_qty ?? 0),
        ];
    })->values();

    $oldItemsData = collect(old('items', []))->map(function ($item) {
        return [
            'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
            'qty' => $item['qty'] ?? 1,
            'unit_price' => $item['unit_price'] ?? 0,
            'vat_percent' => $item['vat_percent'] ?? 0,
            'note' => $item['note'] ?? '',
        ];
    })->values();

    $formatQuantity = static function ($value): string {
        $number = (float) $value;
        if (abs($number - round($number)) < 0.000001) {
            return number_format($number, 0, ',', '.');
        }

        return rtrim(rtrim(number_format($number, 3, ',', '.'), '0'), ',');
    };
@endphp

<div class="ego-inventory-enterprise ego-goods-receipts-page">
    @include('products.partials.module-nav', ['active' => 'receipts'])

    <header class="receipt-page-head">
        <div class="receipt-page-head__copy">
            <span class="receipt-eyebrow">TRUNG TÂM KHO</span>
            <h1>Nhập sản phẩm</h1>
            <p>Tạo phiếu nhập kho, quản lý hóa đơn và công nợ nhà cung cấp.</p>
        </div>
        <div class="receipt-page-head__actions">
            <a href="{{ route('products.input') }}" class="gr-btn gr-btn-light">
                <i class="bi bi-arrow-left"></i> Về sản phẩm
            </a>
            @if($canCreateProduct)
                <a href="{{ route('products.create') }}" class="gr-btn gr-btn-primary">
                    <i class="bi bi-plus-circle"></i> Tạo sản phẩm mới
                </a>
            @endif
        </div>
    </header>

    @if(session('success'))
        <x-ui.alert variant="success" class="tw:rounded-[12px] tw:text-[12px] tw:[font-weight:650] receipt-alert">{{ session('success') }}</x-ui.alert>
    @endif
    @if(session('error'))
        <x-ui.alert variant="danger" class="tw:rounded-[12px] tw:text-[12px] tw:[font-weight:650] receipt-alert">{{ session('error') }}</x-ui.alert>
    @endif
    @if($errors->any())
        <x-ui.alert variant="danger" class="tw:rounded-[12px] tw:text-[12px] tw:[font-weight:650] receipt-alert">
            <strong>Chưa thể lưu phiếu.</strong> {{ $errors->first() }}
        </x-ui.alert>
    @endif

    <section class="receipt-kpi-grid" aria-label="Tổng quan phiếu nhập">
        <article class="receipt-kpi">
            <span class="receipt-kpi__icon"><i class="bi bi-receipt"></i></span>
            <div><small>Tổng phiếu</small><strong>{{ number_format($stats['total'] ?? 0) }}</strong><span>Phiếu đã tạo</span></div>
        </article>
        <article class="receipt-kpi">
            <span class="receipt-kpi__icon is-success"><i class="bi bi-box-seam"></i></span>
            <div><small>Đã nhập kho</small><strong>{{ number_format($stats['posted'] ?? 0) }}</strong><span>Phiếu đã cộng tồn</span></div>
        </article>
        <article class="receipt-kpi">
            <span class="receipt-kpi__icon is-warning"><i class="bi bi-wallet2"></i></span>
            <div><small>Công nợ phải trả</small><strong>{{ number_format($stats['unpaid'] ?? 0) }} đ</strong><span>Chưa thanh toán đủ</span></div>
        </article>
        <article class="receipt-kpi">
            <span class="receipt-kpi__icon is-info"><i class="bi bi-check2-circle"></i></span>
            <div><small>Đã thanh toán</small><strong>{{ number_format($stats['paid'] ?? 0) }} đ</strong><span>Tổng giá trị đã trả</span></div>
        </article>
    </section>

    <section class="receipt-card receipt-form-card">
        <header class="receipt-card__head">
            <div class="receipt-card__title">
                <span class="receipt-card__icon"><i class="bi bi-file-earmark-plus"></i></span>
                <div>
                    <h2>Tạo phiếu nhập hàng</h2>
                    <p>Nhập thông tin nhà cung cấp, sau đó chọn hàng hóa cần nhập kho.</p>
                </div>
            </div>
            <span class="receipt-chip"><i class="bi bi-shield-check"></i> Kho EGO Việt Nam</span>
        </header>

        <form method="POST" action="{{ route('product-goods-receipts.store') }}" id="egoGoodsReceiptForm" class="receipt-form">
            @csrf
            <input type="hidden" name="company_id" id="grCompany" value="1">

            <section class="receipt-form-section">
                <div class="receipt-section-label">
                    <span>01</span>
                    <div><h3>Thông tin nhập hàng</h3><p>Kho nhận hàng và thông tin nhà cung cấp.</p></div>
                </div>

                <div class="receipt-form-grid">
                    <div class="receipt-field span-3">
                        <label>Công ty</label>
                        <div class="receipt-readonly"><i class="bi bi-building"></i><span>EGO_VN - CÔNG TY TNHH EGO VIỆT NAM</span></div>
                    </div>
                    <div class="receipt-field span-3">
                        <label>Kho nhập hàng <b>*</b></label>
                        <select class="receipt-control" name="warehouse_id" data-company-filter="1" required>
                            <option value="">-- Chọn kho nhập hàng --</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" data-company="{{ $warehouse->company_id }}" @selected((string) old('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="receipt-field span-4">
                        <label>Tên nhà cung cấp <b>*</b></label>
                        <input class="receipt-control" name="supplier_name" value="{{ old('supplier_name') }}" placeholder="Nhập tên nhà cung cấp" required>
                    </div>
                    <div class="receipt-field span-2">
                        <label>Số điện thoại</label>
                        <input class="receipt-control" name="supplier_phone" value="{{ old('supplier_phone') }}" placeholder="Không bắt buộc">
                    </div>
                    <div class="receipt-field span-2">
                        <label>Mã số thuế</label>
                        <input class="receipt-control" name="supplier_tax_code" value="{{ old('supplier_tax_code') }}" placeholder="Không bắt buộc">
                    </div>
                    <div class="receipt-field span-4">
                        <label>Địa chỉ nhà cung cấp</label>
                        <input class="receipt-control" name="supplier_address" value="{{ old('supplier_address') }}" placeholder="Nhập địa chỉ nhà cung cấp">
                    </div>
                    <div class="receipt-field span-2">
                        <label>Số hóa đơn</label>
                        <input class="receipt-control" name="invoice_no" value="{{ old('invoice_no') }}" placeholder="VD: HD001 / VAT001">
                    </div>
                    <div class="receipt-field span-2">
                        <label>Ngày hóa đơn</label>
                        <input class="receipt-control" type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}">
                    </div>
                    <div class="receipt-field span-2">
                        <label>Ngày TT dự kiến</label>
                        <input class="receipt-control" type="date" name="payment_due_date" value="{{ old('payment_due_date') }}">
                    </div>
                    <div class="receipt-field span-3">
                        <label>Trạng thái thanh toán <b>*</b></label>
                        <select class="receipt-control" name="payment_status" required>
                            <option value="unpaid" @selected(old('payment_status', 'unpaid') === 'unpaid')>Chưa thanh toán</option>
                            <option value="partial" @selected(old('payment_status') === 'partial')>Thanh toán một phần</option>
                            <option value="paid" @selected(old('payment_status') === 'paid')>Đã thanh toán</option>
                        </select>
                    </div>
                    <div class="receipt-field span-3">
                        <label>Số tiền đã thanh toán</label>
                        <input class="receipt-control" type="number" name="paid_amount" min="0" step="0.01" value="{{ old('paid_amount', 0) }}" inputmode="decimal">
                    </div>
                    <div class="receipt-payment-hint span-6" aria-live="polite">
                        <span><i class="bi bi-info-circle"></i> Công nợ sẽ được tính tự động theo tổng giá trị phiếu và số tiền đã thanh toán.</span>
                    </div>
                </div>
            </section>

            <section class="receipt-form-section receipt-items-section">
                <div class="receipt-section-toolbar">
                    <div class="receipt-section-label receipt-section-label--inline">
                        <span>02</span>
                        <div><h3>Hàng hóa nhập kho</h3><p>Chọn sản phẩm, nhập số lượng, đơn giá và VAT.</p></div>
                    </div>
                    <div class="receipt-section-actions">
                        <span class="receipt-line-count"><b data-item-count>1</b> dòng hàng</span>
                        <button class="gr-btn gr-btn-light" type="button" data-add-item><i class="bi bi-plus-lg"></i> Thêm hàng hóa</button>
                    </div>
                </div>

                <div class="receipt-items-scroll">
                    <div class="receipt-items-table" role="table" aria-label="Hàng hóa nhập kho">
                        <div class="receipt-items-head" role="row">
                            <span>STT</span><span>Sản phẩm</span><span>SL</span><span>Đơn giá</span><span>VAT</span><span>Thành tiền</span><span>Ghi chú</span><span></span>
                        </div>
                        <div id="grItems"></div>
                    </div>
                </div>

                <div class="receipt-items-bottom">
                    <div class="receipt-items-tip"><i class="bi bi-mouse"></i> Bấm vào ô sản phẩm để mở danh mục SKU.</div>
                    <div class="receipt-summary" id="egoReceiptTotals" aria-live="polite">
                        <div><span>Tiền hàng</span><strong data-receipt-subtotal>0 đ</strong></div>
                        <div><span>Tiền VAT</span><strong data-receipt-vat>0 đ</strong></div>
                        <div class="is-total"><span>Tổng thanh toán</span><strong data-receipt-total>0 đ</strong></div>
                    </div>
                </div>
            </section>

            <section class="receipt-form-section receipt-note-section">
                <div class="receipt-field">
                    <label>Ghi chú phiếu nhập</label>
                    <textarea class="receipt-control receipt-note" name="note" rows="2" placeholder="Ví dụ: Nhập hàng từ NCC, lịch thanh toán hoặc lưu ý khi nhận hàng...">{{ old('note') }}</textarea>
                </div>
            </section>

            <footer class="receipt-actionbar">
                <div><i class="bi bi-info-circle"></i> Lưu nháp để kiểm tra lại hoặc nhập kho ngay để cộng tồn.</div>
                <div class="receipt-actionbar__buttons">
                    <button class="gr-btn gr-btn-dark" name="action" value="draft"><i class="bi bi-save"></i> Lưu nháp</button>
                    <button class="gr-btn gr-btn-primary" name="action" value="post" data-confirm-post><i class="bi bi-box-arrow-in-down"></i> Lưu & nhập kho</button>
                </div>
            </footer>
        </form>
    </section>

    <section class="receipt-card receipt-list-card">
        <header class="receipt-card__head">
            <div class="receipt-card__title">
                <span class="receipt-card__icon"><i class="bi bi-clock-history"></i></span>
                <div><h2>Danh sách phiếu nhập hàng</h2><p>Mở chi tiết khi cần xem các sản phẩm trong phiếu.</p></div>
            </div>
            <span class="receipt-chip">{{ number_format($receipts->total()) }} phiếu</span>
        </header>

        <form method="GET" class="receipt-list-filter">
            <div class="receipt-search"><i class="bi bi-search"></i><input name="q" value="{{ $q }}" placeholder="Tìm mã phiếu, nhà cung cấp, số hóa đơn..." step="0.01" inputmode="decimal"></div>
            <select class="receipt-control" name="payment_status">
                <option value="">Tất cả trạng thái thanh toán</option>
                <option value="unpaid" @selected($paymentStatus === 'unpaid')>Chưa thanh toán</option>
                <option value="partial" @selected($paymentStatus === 'partial')>Thanh toán một phần</option>
                <option value="paid" @selected($paymentStatus === 'paid')>Đã thanh toán</option>
            </select>
            <button class="gr-btn gr-btn-dark"><i class="bi bi-funnel"></i> Lọc</button>
            <a href="{{ route('product-goods-receipts.index') }}" class="gr-btn gr-btn-light"><i class="bi bi-arrow-counterclockwise"></i> Đặt lại</a>
        </form>

        <div class="receipt-table-scroll">
            <table class="receipt-list-table">
                <thead>
                    <tr>
                        <th>Mã phiếu & ngày</th>
                        <th>Nhà cung cấp / Hóa đơn</th>
                        <th>Kho nhập</th>
                        <th class="tw:text-right">Giá trị phiếu</th>
                        <th class="tw:text-right">Công nợ</th>
                        <th>Thanh toán</th>
                        <th>Nhập kho</th>
                        <th class="tw:text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $row)
                        @php
                            $pay = $payMap[$row->payment_status] ?? [$row->payment_status, 'warning'];
                            $st = $statusMap[$row->status] ?? [$row->status, 'warning'];
                            $items = $receiptItems->get($row->id, collect());
                        @endphp
                        <tr class="receipt-main-row" data-receipt-main="{{ $row->id }}">
                            <td>
                                <button type="button" class="receipt-code" data-receipt-toggle="{{ $row->id }}" aria-expanded="false">
                                    <span class="receipt-code__copy">
                                        <b>{{ $row->code }}</b>
                                        <small>{{ $row->invoice_date ? \Illuminate\Support\Carbon::parse($row->invoice_date)->format('d/m/Y') : 'Chưa có ngày' }} · {{ $items->count() }} sản phẩm</small>
                                    </span>
                                    <i class="bi bi-chevron-down receipt-code__chevron"></i>
                                </button>
                            </td>
                            <td><strong>{{ $row->supplier_name }}</strong><small>HĐ: {{ $row->invoice_no ?: 'Chưa nhập' }}</small></td>
                            <td><strong>{{ $row->warehouse_name }}</strong><small>{{ $row->company_name }}</small></td>
                            <td class="tw:text-right"><strong class="receipt-money">{{ number_format($row->total_amount) }} đ</strong></td>
                            <td class="tw:text-right"><strong class="receipt-money {{ $row->debt_amount > 0 ? 'is-debt' : 'is-paid' }}">{{ number_format($row->debt_amount) }} đ</strong><small>Hạn: {{ $row->payment_due_date ? \Illuminate\Support\Carbon::parse($row->payment_due_date)->format('d/m/Y') : 'Chưa có' }}</small></td>
                            <td><span class="receipt-badge receipt-badge-{{ $pay[1] }}">{{ $pay[0] }}</span></td>
                            <td><span class="receipt-badge receipt-badge-{{ $st[1] }}">{{ $st[0] }}</span></td>
                            <td>
                                <div class="receipt-row-actions">
                                    <button type="button" class="receipt-icon-btn" data-receipt-toggle="{{ $row->id }}" title="Xem chi tiết"><i class="bi bi-eye"></i></button>
                                    @if($row->status !== 'posted')
                                        <form method="POST" action="{{ route('product-goods-receipts.post', $row->id) }}">
                                            @csrf
                                            <button class="receipt-icon-btn is-primary" onclick="return confirm('Nhập kho phiếu này?')" title="Nhập kho"><i class="bi bi-box-arrow-in-down"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('product-goods-receipts.destroy', $row->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="receipt-icon-btn is-danger" onclick="return confirm('Xóa phiếu nháp này?')" title="Xóa phiếu"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    @else
                                        <span class="receipt-lock" title="Phiếu đã nhập kho và được khóa"><i class="bi bi-lock"></i></span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr class="receipt-detail-row" id="grReceiptDetail{{ $row->id }}" hidden>
                            <td colspan="8">
                                <div class="receipt-detail">
                                    <header>
                                        <div><span>Chi tiết hàng hóa</span><strong>{{ $row->code }}</strong></div>
                                        <div class="receipt-detail__summary">
                                            <span><b>{{ $items->count() }}</b> sản phẩm</span>
                                            <span><b>{{ number_format($row->total_amount) }} đ</b> tổng phiếu</span>
                                        </div>
                                    </header>
                                    @if($items->isNotEmpty())
                                        <div class="receipt-detail-table-scroll">
                                            <div class="receipt-detail-table">
                                                <div class="receipt-detail-head"><span>Sản phẩm</span><span>SL</span><span>Đơn giá</span><span>VAT</span><span>Thành tiền</span><span>Ghi chú</span></div>
                                                @foreach($items as $item)
                                                    <div class="receipt-detail-product">
                                                        <div class="receipt-detail-product__name">
                                                            <span><i class="bi bi-box"></i></span>
                                                            <div>
                                                                <a href="{{ route('products.input', ['search' => $item->sku ?: $item->product_name]) }}">{{ $item->product_name ?: 'Sản phẩm #'.$item->product_id }}</a>
                                                                <small>{{ $item->sku ?: 'Chưa có SKU' }}{{ $item->unit ? ' · '.$item->unit : '' }}</small>
                                                            </div>
                                                        </div>
                                                        <strong>{{ $formatQuantity($item->qty) }}</strong>
                                                        <strong>{{ number_format($item->unit_price) }} đ</strong>
                                                        <span>{{ \App\Support\DisplayFormat::percent($item->vat_percent) }}</span>
                                                        <strong class="is-success">{{ number_format($item->amount) }} đ</strong>
                                                        <span>{{ $item->note ?: '—' }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <div class="receipt-empty"><i class="bi bi-inbox"></i> Phiếu chưa có dữ liệu hàng hóa.</div>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="receipt-empty"><i class="bi bi-inbox"></i> Chưa có phiếu nhập hàng.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="receipt-pagination">{{ $receipts->links() }}</div>
    </section>

    <template id="grItemTemplate">
        <div class="receipt-item-row" role="row">
            <div class="receipt-item-index"><span data-row-number>1</span></div>
            <div class="receipt-product-cell">
                <input type="hidden" data-name="product_id" data-product-id>
                <button type="button" class="receipt-product-trigger" data-product-trigger>
                    <span class="receipt-product-trigger__icon"><i class="bi bi-search"></i></span>
                    <span class="receipt-product-trigger__copy">
                        <b data-product-name>Chọn sản phẩm</b>
                        <small data-product-meta>Nhấn để mở danh mục SKU</small>
                    </span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <a href="{{ route('products.input') }}" class="receipt-product-link" data-product-link hidden title="Mở sản phẩm trong danh sách"><i class="bi bi-box-arrow-up-right"></i></a>
            </div>
            <div><input class="receipt-control" data-name="qty" type="number" min="0.001" step="0.001" value="1" required aria-label="Số lượng"></div>
            <div><input class="receipt-control" data-name="unit_price" type="number" min="0" step="any" value="0" aria-label="Đơn giá" inputmode="decimal"></div>
            <div><input class="receipt-control" data-name="vat_percent" type="number" min="0" step="1" value="0" aria-label="VAT"></div>
            <div><input class="receipt-control receipt-total-control" data-total readonly value="0 đ" aria-label="Thành tiền"></div>
            <div><input class="receipt-control" data-name="note" placeholder="Ghi chú" aria-label="Ghi chú"></div>
            <button type="button" class="receipt-item-remove" data-remove-item title="Xóa dòng"><i class="bi bi-trash3"></i></button>
        </div>
    </template>

    <script type="application/json" id="grProductsData">{!! $productPickerData->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
    <script type="application/json" id="grOldItemsData">{!! $oldItemsData->toJson(JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>

    <div class="receipt-product-modal" id="grProductModal" hidden aria-hidden="true">
        <div class="receipt-product-modal__backdrop" data-close-product-modal></div>
        <section class="receipt-product-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="grProductModalTitle">
            <header class="receipt-product-modal__head">
                <div class="receipt-product-modal__title">
                    <span><i class="bi bi-box-seam"></i></span>
                    <div><small>DANH MỤC EGO VIỆT NAM</small><h2 id="grProductModalTitle">Chọn sản phẩm nhập kho</h2></div>
                </div>
                <button type="button" class="receipt-product-modal__close" data-close-product-modal aria-label="Đóng"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="receipt-product-modal__toolbar">
                <div class="receipt-product-modal__search"><i class="bi bi-search"></i><input id="grProductSearch" placeholder="Tìm theo tên sản phẩm hoặc mã SKU..." autocomplete="off"></div>
                <div class="receipt-product-modal__filters">
                    <button type="button" class="is-active" data-product-filter="all">Tất cả</button>
                    <button type="button" data-product-filter="in-stock">Còn tồn</button>
                    <button type="button" data-product-filter="out-stock">Hết tồn</button>
                </div>
            </div>
            <div class="receipt-product-modal__meta"><span data-product-result-count>0 sản phẩm</span><small>Nhấp đúp hoặc bấm “Chọn” để đưa sản phẩm vào phiếu.</small></div>
            <div class="receipt-product-modal__results" id="grProductResults"></div>
            <footer class="receipt-product-modal__foot">
                <span>Không thấy sản phẩm cần nhập?</span>
                @if($canCreateProduct)
                    <a href="{{ route('products.create') }}" class="gr-btn gr-btn-primary"><i class="bi bi-plus-circle"></i> Tạo sản phẩm mới</a>
                @endif
            </footer>
        </section>
    </div>
</div>
@endsection
