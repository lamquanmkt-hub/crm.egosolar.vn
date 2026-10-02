{{-- Hai con số badge (đơn chờ duyệt, phiếu vật tư chờ duyệt) do
     App\Services\System\SidebarStatusService cấp cho partials.sidebar qua view
     composer. Trước đây đúng chỗ này có một khối 16 dòng CHÉP QUA 9 VIEW tự chạy
     lại hai câu COUNT rồi nuốt lỗi bằng catch(Throwable). Giá trị nó tính ra bị
     composer ghi đè nên không hiển thị ở đâu — chỉ tốn 2 câu truy vấn mỗi lần
     dựng trang. --}}


@extends('layouts.app')
@section('title', 'Sửa đơn hàng')

@section('content')
<link rel="stylesheet" href="{{ asset('css/ego-order.css') }}?v={{ filemtime(public_path('css/ego-order.css')) }}">

{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="container-fluid tw:px-6 ego-order tw:py-4">
  <div class="ego-topbar">
    <div class="ego-topbar__left">
      <div class="ego-topbar__icon"><i class="bi bi-receipt-cutoff"></i></div>
      <div>
        <div class="ego-topbar__title">SỬA ĐƠN HÀNG</div>
        <div class="ego-topbar__sub">Cập nhật thông tin • Chọn sản phẩm theo kho • Kiểm tra hoá đơn • Lưu</div>
      </div>
    </div>

    <div class="ego-topbar__right">
      <x-ui.button variant="none" size="none" class="btn-ghost tw:text-[16px]/[24px] tw:rounded-[12px]" href="{{ route('orders.index') }}">
        <i class="bi bi-arrow-left"></i> Quay lại
      </x-ui.button>

      <x-ui.button variant="none" size="none" class="btn-ego tw:text-[16px]/[24px] tw:font-medium tw:rounded-[12px]" type="submit" form="orderForm" name="action" value="submit">
        <i class="bi bi-check2-circle"></i> Lưu đơn
      </x-ui.button>
    </div>
  </div>

  {{-- Alerts --}}
  @if(session('success'))
    <x-ui.alert variant="success" class="ego-alert">{{ session('success') }}</x-ui.alert>
  @endif
  @if(session('error'))
    <x-ui.alert variant="danger" class="ego-alert">{{ session('error') }}</x-ui.alert>
  @endif
  @if($errors->any())
    <x-ui.alert variant="danger" class="ego-alert">
      <div class="tw:font-bold tw:mb-2">Có lỗi xảy ra:</div>
      <ul class="tw:mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </x-ui.alert>
  @endif

  <form action="{{ route('orders.update', $order->id) }}" method="POST" id="orderForm">
    @csrf
    @method('PUT')

    <div class="ego-layout">
      <div class="ego-main">
        {{-- Thông tin đơn --}}
        @include('orders.partials.order-info', [
          'customers' => $customers ?? [],
          'priceTiers' => $priceTiers ?? [],
          'order' => $order,
          'selectedCustomer' => $selectedCustomer ?? null,
          'mode' => 'edit',
        ])

        {{-- Bảng sản phẩm --}}
        @include('orders.partials.product-table', [
          'mode' => 'edit',
          'order' => $order,
          'companies' => $companies ?? [],
          'warehouses' => $warehouses ?? [],
          'priceTiers' => $priceTiers ?? [],
        ])
      </div>

      <div class="ego-side">
        @include('orders.partials.order-summary', [
          'mode' => 'edit',
          'order' => $order,
        ])
      </div>
    </div>
  </form>
</div>
@endsection

@section('scripts')
<script>
  window.rowIndex = {{ isset($order) && isset($order->items) ? $order->items->count() : 1 }};
  window.customerTypeId = {{ (int)($order->lead?->customer?->customer_type_id ?? 0) }};
  @php
    $orderEditPriceTiersJs = collect($priceTiers ?? [])
        ->map(fn($t) => ['id' => (int) $t->id, 'code' => (string) $t->code, 'name' => (string) $t->name])
        ->values()
        ->all();
  @endphp
  window.allPriceTiers = @json($orderEditPriceTiersJs);
</script>
<script src="{{ asset('js/order-form.js') }}?v={{ filemtime(public_path('js/order-form.js')) }}"></script>

<script id="order-edit-keep-original-price">
(function(){
  function toNumber(value){
    value = (value || '').toString().trim();
    if (!value) return 0;
    value = value.replace(/\s/g, '').replace(/\./g, '').replace(/,/g, '.');
    var n = parseFloat(value);
    return isNaN(n) ? 0 : n;
  }

  function formatVND(value){
    return new Intl.NumberFormat('vi-VN').format(Math.round(value || 0));
  }

  function restoreEditPrices(){
    document.querySelectorAll('#productTableBody tr.product-row').forEach(function(row){
      var originalPrice = toNumber(row.dataset.editUnitPrice);
      var originalLineTotal = toNumber(row.dataset.editLineTotal);

      var priceInput = row.querySelector('.unit-price');
      var lineInput = row.querySelector('.line-total');
      var qtyInput = row.querySelector('.quantity');
      var discountPercentInput = row.querySelector('.discount-percent');
      var discountAmountInput = row.querySelector('.discount-per-unit');
      var productSelect = row.querySelector('.product-select');

      if (!priceInput) return;

      var currentPrice = toNumber(priceInput.value);

      // Chỉ khôi phục khi bị rỗng/0. Không ghi đè nếu người dùng đã sửa giá hợp lệ.
      if (originalPrice > 0 && currentPrice <= 0) {
        priceInput.value = Math.round(originalPrice);
        currentPrice = originalPrice;
      }

      if (productSelect && productSelect.selectedOptions && productSelect.selectedOptions[0] && originalPrice > 0) {
        productSelect.selectedOptions[0].dataset.price = Math.round(originalPrice);
        productSelect.selectedOptions[0].dataset.priceAgent = Math.round(originalPrice);
        productSelect.selectedOptions[0].dataset.priceRetail = Math.round(originalPrice);
        productSelect.selectedOptions[0].dataset.existingPrice = Math.round(originalPrice);
      }

      if (lineInput) {
        var currentLine = toNumber(lineInput.value);

        if (originalLineTotal > 0 && currentLine <= 0) {
          lineInput.value = formatVND(originalLineTotal);
          return;
        }

        if (currentPrice > 0 && currentLine <= 0) {
          var qty = Math.max(1, toNumber(qtyInput ? qtyInput.value : 1));
          var discountPercent = toNumber(discountPercentInput ? discountPercentInput.value : 0);
          var discountAmount = toNumber(discountAmountInput ? discountAmountInput.value : 0);

          var subtotal = qty * currentPrice;
          var discount = discountAmount > 0 ? discountAmount * qty : subtotal * discountPercent / 100;
          var total = Math.max(subtotal - discount, 0);

          lineInput.value = formatVND(total);
        }
      }
    });

    if (typeof window.updateSummary === 'function') {
      window.updateSummary();
    }

    if (typeof window.calculateOrderSummary === 'function') {
      window.calculateOrderSummary();
    }
  }

  document.addEventListener('DOMContentLoaded', function(){
    restoreEditPrices();
    setTimeout(restoreEditPrices, 300);
    setTimeout(restoreEditPrices, 900);
  });

  document.addEventListener('change', function(e){
    if (
      e.target.matches('.warehouse-select') ||
      e.target.matches('.product-select') ||
      e.target.matches('.price-tier-select')
    ) {
      setTimeout(restoreEditPrices, 250);
    }
  });
})();
</script>

@endsection


