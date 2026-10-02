@extends('layouts.app')

@section('content')
<div class="container tw:py-4">
    <div class="tw:flex tw:justify-between tw:items-start flex-wrap tw:gap-2 tw:mb-4">
        <div>
            <h2 class="tw:mb-1 tw:font-bold">Cập nhật phòng ban</h2>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Chỉnh sửa thông tin phòng ban</div>
        </div>

        <x-ui.button href="{{ route('hr.departments.index') }}" variant="outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Quay lại
        </x-ui.button>
    </div>

    <x-ui.card class="border-0 shadow-sm">
        <x-ui.card-body>
            <form method="POST" action="{{ route('hr.departments.update', $department->id) }}">
                @csrf
                @method('PUT')

                <div class="tw:row tw:g-3">
                    <div class="tw:md:col12-6">
                        <x-ui.label>Tên phòng ban</x-ui.label>
                        <x-ui.input type="text" name="name" class="@error('name') is-invalid @enderror"
                               value="{{ old('name', $department->name) }}" />
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="tw:md:col12-6">
                        <x-ui.label>Mã phòng ban</x-ui.label>
                        <x-ui.input type="text" name="code" value="{{ old('code', $department->code) }}" />
                    </div>

                    <div class="tw:col12-12">
                        <x-ui.label>Mô tả</x-ui.label>
                        <x-ui.input as="textarea" name="description" rows="4">{{ old('description', $department->description) }}</x-ui.input>
                    </div>
                </div>

                <div class="tw:mt-6 tw:flex tw:gap-2">
                    <x-ui.button variant="primary" type="submit">
                        <i class="bi bi-save me-1"></i> Cập nhật
                    </x-ui.button>
                    <x-ui.button href="{{ route('hr.departments.index') }}" variant="light" class="border">Huỷ</x-ui.button>
                </div>
            </form>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection