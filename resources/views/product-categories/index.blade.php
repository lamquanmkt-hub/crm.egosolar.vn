@extends('layouts.app')

@section('content')
    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    <div class="container-fluid tw:px-6 tw:py-4">
        <div class="tw:flex tw:justify-between tw:items-center tw:mb-4">
            <h1 class="tw:font-bold tw:uppercase tw:text-[#6c757d]">Danh sách danh mục sản phẩm</h1>
            <x-ui.button href="{{ route('categories.create') }}" variant="primary">
                <i class="bi bi-plus-circle"></i> Thêm danh mục
            </x-ui.button>
        </div>

        {{-- Hiển thị message --}}
        @if(session('success'))
            <x-ui.alert variant="success" :dismissible="true">
                <i class="bi bi-check-circle"></i> {{ session('success') }}
            </x-ui.alert>
        @endif

        @if(session('error'))
            <x-ui.alert variant="danger" :dismissible="true">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
            </x-ui.alert>
        @endif

        <x-ui.card class="shadow-sm">
            <x-ui.card-body>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="50">ID</th>
                                <th>Tên danh mục</th>
                                <th>Mô tả</th>
                                <th>Danh mục cha</th>
                                <th>Số sản phẩm</th>
                                <th>Số danh mục con</th>
                                <th width="180" class="tw:text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $category)
                                <tr>
                                    <td>{{ $category->id }}</td>
                                    <td>
                                        <strong>{{ $category->name }}</strong>
                                    </td>
                                    <td>
                                        <span class="tw:text-[rgba(33,37,41,0.75)]">
                                            {{ Str::limit($category->description ?? 'Không có mô tả', 50) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($category->parent)
                                            <span class="badge bg-info">{{ $category->parent->name }}</span>
                                        @else
                                            <span class="tw:text-[rgba(33,37,41,0.75)]">—</span>
                                        @endif
                                    </td>
                                    <td class="tw:text-center">
                                        <span class="badge bg-primary">{{ $category->products_count ?? $category->products()->count() }}</span>
                                    </td>
                                    <td class="tw:text-center">
                                        <span class="badge bg-secondary">{{ $category->children_count ?? $category->children()->count() }}</span>
                                    </td>
                                    <td>
                                        <div class="btn-group cat-btn-group" role="group">
                                            {{-- Nút này là con trực tiếp của `.btn-group`: Bootstrap từng cho nó
                                                 position/flex-grow và bo góc phải vuông qua `.btn-group > .btn`.
                                                 Bỏ `.btn` là mất cả ba, nên khai lại ngay trên nút. --}}
                                            <x-ui.button href="{{ route('categories.edit', $category->id) }}"
                                               variant="outline-warning" size="none"
                                               class="tw:relative tw:grow tw:px-2 tw:py-1 tw:text-[14px]/[21px] tw:font-normal tw:[border-radius:.25rem_0_0_.25rem]"
                                               title="Sửa">
                                                <i class="bi bi-pencil"></i> Sửa
                                            </x-ui.button>
                                            <form action="{{ route('categories.destroy', $category->id) }}"
                                                  method="POST"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Bạn có chắc chắn muốn xóa danh mục này?')">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.button type="submit"
                                                        variant="outline-danger" size="sm"
                                                        title="Xóa">
                                                    <i class="bi bi-trash"></i> Xóa
                                                </x-ui.button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-6">
                                        <i class="bi bi-inbox"></i> Chưa có danh mục nào
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($categories->hasPages())
                    <div class="tw:mt-4">
                        {{ $categories->links() }}
                    </div>
                @endif
            </x-ui.card-body>
        </x-ui.card>
    </div>
@endsection

