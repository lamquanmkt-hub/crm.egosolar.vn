@extends('layouts.app')

@section('content')
    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    <div class="container-fluid tw:px-6 tw:py-4">
        <div class="tw:flex tw:justify-between tw:items-center tw:mb-6">
            <h1 class="tw:font-bold tw:uppercase tw:text-[#6c757d]">Chỉnh sửa danh mục sản phẩm</h1>
            <x-ui.button href="{{ route('categories.index') }}" variant="outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </x-ui.button>
        </div>

        @if(session('error'))
            <x-ui.alert variant="danger" :dismissible="true">
                <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
            </x-ui.alert>
        @endif

        <x-ui.card class="shadow-sm">
            <x-ui.card-body>
                <form action="{{ route('categories.update', $category->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="tw:row">
                        <div class="tw:md:col12-8">
                            <div class="tw:mb-4">
                                <x-ui.label for="name">
                                    Tên danh mục <span class="tw:text-[#dc3545]">*</span>
                                </x-ui.label>
                                <x-ui.input type="text"
                                       class="@error('name') is-invalid @enderror"
                                       id="name"
                                       name="name"
                                       value="{{ old('name', $category->name) }}"
                                       placeholder="Nhập tên danh mục"
                                       required />
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="tw:mb-4">
                                <x-ui.label for="description">Mô tả</x-ui.label>
                                <x-ui.input as="textarea" class="@error('description') is-invalid @enderror"
                                          id="description"
                                          name="description"
                                          rows="4"
                                          placeholder="Nhập mô tả danh mục">{{ old('description', $category->description) }}</x-ui.input>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="tw:md:col12-4">
                            <div class="tw:mb-4">
                                <x-ui.label for="parent_id">Danh mục cha</x-ui.label>
                                <x-ui.select class="@error('parent_id') is-invalid @enderror"
                                        id="parent_id"
                                        name="parent_id">
                                    <option value="">— Không có —</option>
                                    @foreach($categories as $cat)
                                        @if($cat->id != $category->id)
                                            <option value="{{ $cat->id }}" 
                                                    {{ old('parent_id', $category->parent_id) == $cat->id ? 'selected' : '' }}>
                                                {{ $cat->name }}
                                                @if($cat->parent)
                                                    ({{ $cat->parent->name }})
                                                @endif
                                            </option>
                                        @endif
                                    @endforeach
                                </x-ui.select>
                                @error('parent_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="form-text tw:text-[rgba(33,37,41,0.75)]">
                                    Chọn danh mục cha nếu muốn tạo danh mục con
                                </small>
                            </div>

                            <x-ui.card class="bg-light">
                                <x-ui.card-body>
                                    <h6 class="tw:mb-2">Thông tin</h6>
                                    <p class="tw:last:mb-0 small tw:mb-1">
                                        <strong>ID:</strong> {{ $category->id }}
                                    </p>
                                    <p class="tw:last:mb-0 small tw:mb-1">
                                        <strong>Số sản phẩm:</strong> {{ $category->products()->count() }}
                                    </p>
                                    <p class="tw:last:mb-0 small tw:mb-0">
                                        <strong>Số danh mục con:</strong> {{ $category->children()->count() }}
                                    </p>
                                </x-ui.card-body>
                            </x-ui.card>
                        </div>
                    </div>

                    <div class="tw:flex tw:justify-end tw:gap-2">
                        <x-ui.button href="{{ route('categories.index') }}" variant="secondary">
                            <i class="bi bi-x-circle"></i> Hủy
                        </x-ui.button>
                        <x-ui.button variant="primary" type="submit">
                            <i class="bi bi-check-circle"></i> Cập nhật
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card-body>
        </x-ui.card>
    </div>
@endsection

