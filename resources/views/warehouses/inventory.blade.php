@extends('layouts.app')
@section('title', 'Tồn kho - '.$warehouse->name)
@section('content')
{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="container-fluid ego-inventory-enterprise ego-warehouse-inventory-page tw:py-4">
    @include('products.partials.module-nav', ['active' => 'warehouses'])
    <header class="ego-inventory-page-head">
        <div class="ego-inventory-page-copy">
            <div class="ego-inventory-eyebrow">CHI TIẾT KHO</div>
            <div class="tw:flex tw:items-center tw:gap-2"><span class="ego-inventory-title-icon"><i class="bi bi-boxes"></i></span><h1>{{ $warehouse->name }}</h1></div>
            <p>Theo dõi sản phẩm và số lượng tồn hiện tại trong kho.</p>
        </div>
        <div class="tw:flex tw:gap-2 flex-wrap"><a href="{{ route('warehouses.edit',$warehouse) }}" class="ego-inventory-btn ego-inventory-btn--secondary"><i class="bi bi-pencil"></i> Sửa kho</a><a href="{{ route('warehouses.index') }}" class="ego-inventory-btn ego-inventory-btn--secondary"><i class="bi bi-arrow-left"></i> Danh sách kho</a></div>
    </header>
    <section class="ego-inventory-table-card">
        <div class="ego-inventory-card-head"><div><h2>Danh sách tồn kho</h2><p>{{ number_format($inventory->count()) }} dòng sản phẩm trong kho.</p></div></div>
        <div class="table-responsive"><table class="table ego-inventory-data-table tw:mb-0"><thead><tr><th>Sản phẩm</th><th>SKU</th><th class="tw:text-right">Tồn kho</th><th>Cập nhật lần cuối</th></tr></thead><tbody>
        @forelse($inventory as $stock)
            <tr><td><strong>{{ $stock->product?->name ?? 'Sản phẩm không còn tồn tại' }}</strong></td><td><span class="ego-inventory-code">{{ $stock->product?->sku ?? '—' }}</span></td><td class="tw:text-right"><span class="ego-inventory-qty">{{ number_format((float)$stock->qty, 0) }}</span></td><td>{{ $stock->last_updated ? \Illuminate\Support\Carbon::parse($stock->last_updated)->format('d/m/Y H:i') : 'Chưa cập nhật' }}</td></tr>
        @empty
            <tr><td colspan="4" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] py-5">Kho chưa có sản phẩm.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>
</div>
@endsection
@include('products.partials.enterprise-assets')
