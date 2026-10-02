@extends('layouts.app')
@section('title', 'Cập nhật kho')
@section('content')
{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="container-fluid ego-inventory-enterprise ego-warehouse-form-page tw:py-4">
    @include('products.partials.module-nav', ['active' => 'warehouses'])
    <header class="ego-inventory-page-head ego-wh-form-head">
        <div class="ego-inventory-page-copy">
            <div class="ego-inventory-eyebrow">TRUNG TÂM KHO</div>
            <div class="tw:flex tw:items-center tw:gap-2">
                <span class="ego-inventory-title-icon"><i class="bi bi-building-gear"></i></span>
                <h1>Cập nhật kho</h1>
            </div>
            <p>Chỉnh sửa thông tin kho {{ $warehouse->name }} thuộc EGO Việt Nam.</p>
        </div>
        <div class="tw:flex tw:gap-2 flex-wrap">
            <a href="{{ route('warehouses.inventory', $warehouse) }}" class="ego-inventory-btn ego-inventory-btn--secondary"><i class="bi bi-boxes"></i> Xem tồn kho</a>
            <a href="{{ route('warehouses.index') }}" class="ego-inventory-btn ego-inventory-btn--secondary"><i class="bi bi-arrow-left"></i> Về danh sách</a>
        </div>
    </header>
    @include('warehouses._form', ['action'=>route('warehouses.update',$warehouse),'method'=>'PUT','warehouse'=>$warehouse,'companies'=>$companies ?? []])
</div>
@endsection
@include('products.partials.enterprise-assets')
