@extends('layouts.app')

@section('title', 'Kho hàng')

@section('content')
<style>
    .ego-wh-page{padding:14px 18px 36px;background:#f3f7fb;min-height:calc(100vh - 80px)}
    .ego-wh-head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-bottom:12px}
    .ego-wh-eyebrow{font-size:10px;font-weight:950;letter-spacing:.08em;text-transform:uppercase;color:#0891b2;margin-bottom:3px}
    .ego-wh-head h1{font-size:22px;line-height:1.15;font-weight:950;margin:0;color:#0f172a;letter-spacing:-.025em}
    .ego-wh-head p{font-size:12px;font-weight:700;color:#64748b;margin:4px 0 0}
    .ego-wh-btn{border:0;border-radius:12px;padding:9px 13px;font-size:13px;font-weight:950;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:7px;background:linear-gradient(135deg,#0891b2,#0f766e);color:#fff;box-shadow:0 8px 18px rgba(8,145,178,.16)}
    .ego-wh-card{background:#fff;border:1px solid #dbe7ef;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.05);overflow:hidden}
    .ego-wh-table{margin:0;min-width:920px}
    .ego-wh-table thead th{background:#f8fafc;border-bottom:1px solid #dbe7ef;padding:11px 12px;color:#64748b;font-size:10.5px;font-weight:950;text-transform:uppercase;letter-spacing:.035em;white-space:nowrap}
    .ego-wh-table tbody td{padding:12px;border-bottom:1px solid #edf2f7;vertical-align:middle;color:#0f172a;font-size:12.5px;font-weight:700}
    .ego-wh-table tbody tr:hover{background:linear-gradient(90deg,rgba(14,165,233,.045),#fff)}
    .ego-wh-name{font-size:13.5px;font-weight:950}.ego-wh-company{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:5px 8px;background:#ecfeff;border:1px solid #a5f3fc;color:#0f766e;font-size:10.5px;font-weight:900}
    .ego-wh-actions{display:flex;justify-content:flex-end;gap:6px}.ego-wh-actions .wh-btn{border-radius:10px;font-size:11px;font-weight:900}
    @media(max-width:768px){.ego-wh-page{padding:10px}.ego-wh-head{align-items:flex-start;flex-direction:column}}
</style>

{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="container-fluid ego-wh-page ego-inventory-enterprise tw:py-4">
    @include('products.partials.module-nav', ['active' => 'warehouses'])

    <header class="ego-wh-head ego-inventory-page-head">
        <div>
            <div class="ego-wh-eyebrow">Trung tâm kho</div>
            <h1>Kho hàng</h1>
            <p>Quản lý danh sách kho và phạm vi lưu trữ sản phẩm của EGO Việt Nam.</p>
        </div>
        <a href="{{ route('warehouses.create') }}" class="ego-wh-btn">
            <i class="bi bi-plus-circle"></i> Thêm kho
        </a>
    </header>

    @if(session('success'))
        <x-ui.alert variant="success" :dismissible="true" class="tw:rounded-[12px] tw:text-[12px] tw:[font-weight:650]">
            <i class="bi bi-check-circle tw:mr-2"></i>{{ session('success') }}
        </x-ui.alert>
    @endif

    <div class="ego-wh-card">
        <div class="table-responsive">
            <table class="table ego-wh-table">
                <thead>
                <tr>
                    <th style="width:70px">ID</th>
                    <th>Tên kho</th>
                    <th>Địa điểm</th>
                    <th>Công ty</th>
                    <th>Quản lý</th>
                    <th class="tw:text-right">Hành động</th>
                </tr>
                </thead>
                <tbody>
                @forelse($warehouses as $warehouse)
                    <tr>
                        <td><strong>#{{ $warehouse->id }}</strong></td>
                        <td><div class="ego-wh-name">{{ $warehouse->name }}</div></td>
                        <td>{{ $warehouse->location ?: 'Chưa cập nhật' }}</td>
                        <td><span class="ego-wh-company"><i class="bi bi-building-check"></i> EGO Việt Nam</span></td>
                        <td>{{ $warehouse->manager?->name ?: 'Chưa phân công' }}</td>
                        <td>
                            <div class="ego-wh-actions">
                                <x-ui.button href="{{ route('warehouses.inventory', $warehouse->id) }}" variant="outline-secondary" size="none" class="wh-btn tw:px-2 tw:py-1 tw:leading-[1.5]"><i class="bi bi-boxes"></i> Tồn kho</x-ui.button>
                                <x-ui.button href="{{ route('warehouses.edit', $warehouse->id) }}" variant="outline-primary" size="none" class="wh-btn tw:px-2 tw:py-1 tw:leading-[1.5]"><i class="bi bi-pencil"></i> Sửa</x-ui.button>
                                <form action="{{ route('warehouses.destroy', $warehouse->id) }}" method="POST" onsubmit="return confirm('Bạn chắc chắn muốn xóa?')">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="outline-danger" size="none" class="wh-btn tw:px-2 tw:py-1 tw:leading-[1.5]"><i class="bi bi-trash"></i> Xóa</x-ui.button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="tw:text-center tw:text-[rgba(33,37,41,0.75)]! py-5">Chưa có kho hàng nào.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="tw:p-4">{{ $warehouses->links() }}</div>
    </div>
</div>
@endsection

@include('products.partials.enterprise-assets')
