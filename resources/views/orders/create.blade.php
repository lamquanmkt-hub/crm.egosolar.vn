{{-- Hai con số badge (đơn chờ duyệt, phiếu vật tư chờ duyệt) do
     App\Services\System\SidebarStatusService cấp cho partials.sidebar qua view
     composer. Trước đây đúng chỗ này có một khối 16 dòng CHÉP QUA 9 VIEW tự chạy
     lại hai câu COUNT rồi nuốt lỗi bằng catch(Throwable). Giá trị nó tính ra bị
     composer ghi đè nên không hiển thị ở đâu — chỉ tốn 2 câu truy vấn mỗi lần
     dựng trang. --}}
@php

    $orderCreatePriceTiersJs = collect($priceTiers ?? [])
        ->map(function ($tier) {
            return [
                'id' => (int) ($tier->id ?? 0),
                'code' => (string) ($tier->code ?? ''),
                'name' => (string) ($tier->name ?? ''),
            ];
        })
        ->values()
        ->all();
@endphp

@extends('layouts.app')
@section('title', 'Tạo đơn hàng mới')

@section('content')
    <link rel="stylesheet" href="{{ asset('css/ego-order.css') }}?v={{ filemtime(public_path('css/ego-order.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/order-create-pro-v4.css') }}?v={{ filemtime(public_path('css/order-create-pro-v4.css')) }}">

    @once
        {{-- Đã bỏ thẻ <script> tom-select@2.3.1 ở đây, GIỮ LẠI thẻ CSS. Lý do tách đôi:

             - JS: layout nạp bản 2.6.1 (URL không ghim phiên bản) SAU thẻ này, mà
               mọi chỗ dùng TomSelect trên trang đều chạy trong DOMContentLoaded.
               Nên bản 2.6.1 vẫn là bản thực thi; thẻ 2.3.1 chỉ tải thừa một tệp.

             - CSS thì KHÔNG bỏ được: layout nạp tom-select.css ở đầu <head>, còn thẻ
               này nằm trong <body> nên đang thắng cascade trước CSS của ứng dụng.
               Bỏ đi thì luật của app thắng và ô chọn khách hàng đổi padding-top
               0px -> 9px (đã đo bằng getComputedStyle). Muốn bỏ hẳn thì phải sửa
               CSS ứng dụng trước, không phải việc của đợt này. --}}
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
    @endonce

    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    <div class="container-fluid ego-order oc-shell tw:py-4">
        <header class="oc-header">
            <div class="oc-header-copy">
                <span class="oc-eyebrow">Đơn hàng mới</span>
                <h1>Tạo đơn hàng</h1>
                <p>Chọn khách hàng và sản phẩm để tạo đơn.</p>
            </div>

            <div class="oc-header-actions">
                <a href="{{ route('orders.index') }}" class="oc-btn oc-btn-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Quay lại
                </a>

                <button
                    type="submit"
                    form="orderForm"
                    name="action"
                    value="save_draft"
                    class="oc-btn oc-btn-outline"
                    data-submit-button
                >
                    <i class="bi bi-save"></i>
                    <span>Lưu nháp</span>
                </button>
            </div>
        </header>

        @if(session('success'))
            <div class="oc-alert oc-alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="oc-alert oc-alert-danger">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="oc-alert oc-alert-danger">
                <strong>Vui lòng kiểm tra lại dữ liệu.</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('orders.store') }}"
            method="POST"
            id="orderForm"
            novalidate
            data-order-form
        >
            @csrf

            <div class="oc-layout">
                <main class="oc-main">
                    @include('orders.partials.order-info', [
                        'customers' => $customers ?? [],
                        'priceTiers' => $priceTiers ?? [],
                        'mode' => 'create',
                    ])

                    @include('orders.partials.product-table', [
                        'mode' => 'create',
                        'warehouses' => $warehouses ?? [],
                        'priceTiers' => $priceTiers ?? [],
                    ])
                </main>

                <aside class="oc-side">
                    @include('orders.partials.order-summary', [
                        'mode' => 'create',
                    ])
                </aside>
            </div>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        window.customerTypeId = null;
        window.allPriceTiers = @json($orderCreatePriceTiersJs);
    </script>
    <script src="{{ asset('js/order-form.js') }}?v={{ filemtime(public_path('js/order-form.js')) }}"></script>
@endsection
