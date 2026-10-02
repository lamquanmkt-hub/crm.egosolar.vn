@extends('layouts.app')

@section('content')
<div class="container-fluid tw:py-4">
    <div class="tw:flex tw:justify-between tw:items-start flex-wrap tw:gap-2 tw:mb-4">
        <div>
            <h2 class="tw:mb-1 tw:font-bold">Phòng ban</h2>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Quản lý danh mục phòng ban</div>
        </div>

        <div class="tw:flex tw:gap-2">
            <x-ui.button href="{{ route('hr.dashboard') }}" variant="outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </x-ui.button>
            <x-ui.button href="{{ route('hr.departments.create') }}" variant="primary">
                <i class="bi bi-plus-circle me-1"></i> Thêm phòng ban
            </x-ui.button>
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    <x-ui.card class="border-0 shadow-sm">
        <x-ui.card-body class="tw:p-0">
            <div class="table-responsive">
                <table class="table align-middle tw:mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Tên phòng ban</th>
                            <th>Mã</th>
                            <th>Mô tả</th>
                            <th>Ngày tạo</th>
                            <th class="tw:text-right pe-3">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departments as $department)
                            <tr>
                                <td class="ps-3">{{ $department->id }}</td>
                                <td>{{ $department->name }}</td>
                                <td>{{ $department->code ?? '—' }}</td>
                                <td>{{ $department->description ?? '—' }}</td>
                                <td>{{ optional($department->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="tw:text-right pe-3">
                                    <div class="tw:flex tw:justify-end tw:gap-2">
                                        <x-ui.button href="{{ route('hr.departments.edit', $department->id) }}" variant="warning" size="sm" class="tw:text-[#ffffff]">
                                            <i class="bi bi-pencil-square"></i>
                                        </x-ui.button>

                                        <form action="{{ route('hr.departments.destroy', $department->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xoá phòng ban này?')">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button variant="danger" size="sm" type="submit">
                                                <i class="bi bi-trash"></i>
                                            </x-ui.button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="tw:text-center tw:py-6 tw:text-[rgba(33,37,41,0.75)]">
                                    Chưa có phòng ban nào
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card-body>

        @if($departments->hasPages())
            <x-ui.card-footer class="bg-white">
                {{ $departments->links() }}
            </x-ui.card-footer>
        @endif
    </x-ui.card>
</div>
@endsection