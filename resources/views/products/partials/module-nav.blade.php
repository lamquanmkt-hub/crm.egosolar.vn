@php
    $inventoryNavActive = $active ?? match (true) {
        request()->routeIs('product-goods-receipts.*') => 'receipts',
        request()->routeIs('products.output') => 'output',
        request()->routeIs('warehouses.*') => 'warehouses',
        default => 'input',
    };

    $inventoryUser = auth()->user();
    $canOpenReceipts = $inventoryUser
        && method_exists($inventoryUser, 'hasAnyRole')
        && $inventoryUser->hasAnyRole(['admin', 'warehouse', 'accounting']);

    $inventoryTabs = [
        ['key'=>'input','label'=>'Sản phẩm đầu vào','description'=>'Tồn kho & giá vốn','icon'=>'bi-box-seam','url'=>route('products.input'),'visible'=>true],
        ['key'=>'receipts','label'=>'Nhập sản phẩm','description'=>'Phiếu nhập & công nợ','icon'=>'bi-box-arrow-in-down','url'=>\Illuminate\Support\Facades\Route::has('product-goods-receipts.index') ? route('product-goods-receipts.index') : '#','visible'=>$canOpenReceipts && \Illuminate\Support\Facades\Route::has('product-goods-receipts.index')],
        ['key'=>'output','label'=>'Sản phẩm đầu ra','description'=>'Giá bán & khả dụng','icon'=>'bi-box-arrow-up-right','url'=>route('products.output'),'visible'=>true],
        ['key'=>'warehouses','label'=>'Kho hàng','description'=>'Danh sách & tồn kho','icon'=>'bi-buildings','url'=>route('warehouses.index'),'visible'=>\Illuminate\Support\Facades\Route::has('warehouses.index')],
    ];
@endphp

<nav class="ego-inventory-nav" aria-label="Điều hướng Kho và Sản phẩm">
    <div class="ego-inventory-nav__identity">
        <span class="ego-inventory-nav__identity-icon"><i class="bi bi-boxes"></i></span>
        <span class="ego-inventory-nav__identity-copy">
            <small>TRUNG TÂM KHO</small>
            <strong>Kho &amp; Sản phẩm</strong>
        </span>
    </div>

    <div class="ego-inventory-nav__tabs" role="list">
        @foreach($inventoryTabs as $tab)
            @continue(!$tab['visible'])
            <a href="{{ $tab['url'] }}"
               class="ego-inventory-nav__tab {{ $inventoryNavActive === $tab['key'] ? 'is-active' : '' }}"
               @if($inventoryNavActive === $tab['key']) aria-current="page" @endif>
                <span class="ego-inventory-nav__tab-icon"><i class="bi {{ $tab['icon'] }}"></i></span>
                <span class="ego-inventory-nav__tab-copy">
                    <b>{{ $tab['label'] }}</b>
                    <small>{{ $tab['description'] }}</small>
                </span>
            </a>
        @endforeach
    </div>
</nav>
