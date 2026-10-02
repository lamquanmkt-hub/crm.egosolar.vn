@extends('layouts.app')
@section('title', 'Danh sách Brand')
@section('content')
    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    <div class="container-fluid tw:px-6 tw:py-4">
        <div class="tw:flex tw:justify-between tw:items-center tw:mb-4">
            <h1 class="tw:font-bold tw:uppercase tw:text-[#6c757d] tw:mb-0">DANH SÁCH BRAND</h1>
            <x-ui.button href="{{ route('brands.create') }}" variant="primary">
                <i class="bi bi-plus-lg"></i> Thêm brand
            </x-ui.button>
        </div>
        @if(session('success'))
            <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
        @endif
        <x-ui.card class="shadow-sm tw:mb-4">
            <x-ui.card-body>
                <form method="GET" class="tw:row tw:g-2">
                    <div class="tw:md:col12-6">
                        <x-ui.input type="text" name="search" value="{{ $search ?? '' }}"
                               placeholder="Tìm theo tên hoặc slug..." />
                    </div>
                    <div class="tw:md:col12-auto">
                        <x-ui.button variant="outline-primary" type="submit">
                            <i class="bi bi-search"></i> Tìm
                        </x-ui.button>
                    </div>
                    <div class="tw:md:col12-auto">
                        <x-ui.button href="{{ route('brands.index') }}" variant="outline-secondary">Reset</x-ui.button>
                    </div>
                </form>
            </x-ui.card-body>
        </x-ui.card>
        <x-ui.card class="shadow-sm">
            <x-ui.card-body class="tw:p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle tw:mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width:80px">#</th>
                            <th>Tên brand</th>
                            <th>Slug</th>
                            <th style="width:130px" class="tw:text-center">Trạng thái</th>
                            <th style="width:200px" class="tw:text-center">Thao tác</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($brands as $brand)
                            <tr>
                                <td>{{ $brand->id }}</td>
                                <td class="tw:font-semibold">{{ $brand->name }}</td>
                                <td class="tw:text-[rgba(33,37,41,0.75)]">{{ $brand->slug }}</td>
                                <td class="tw:text-center">
                                    @if($brand->is_active)
                                        <span class="badge bg-success">Đang dùng</span>
                                    @else
                                        <span class="badge bg-secondary">Tắt</span>
                                    @endif
                                </td>
                                <td class="tw:text-center">
                                    <x-ui.button href="{{ route('brands.edit', $brand) }}" variant="outline-primary" size="sm">
                                        <i class="bi bi-pencil-square"></i> Sửa
                                    </x-ui.button>
                                    <form method="POST" action="{{ route('brands.destroy', $brand) }}"
                                          class="d-inline" onsubmit="return confirm('Xoá brand này?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="outline-danger" size="sm" type="submit">
                                            <i class="bi bi-trash"></i> Xoá
                                        </x-ui.button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-6!">
                                    Không có brand nào.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card-body>
            <x-ui.card-footer>
                {{ $brands->withQueryString()->links() }}
            </x-ui.card-footer>
        </x-ui.card>
    </div>
@endsection
