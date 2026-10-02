@extends('layouts.app')

@section('content')
<div class="container-fluid tw:py-4">

    <div class="tw:flex tw:justify-between tw:items-start flex-wrap tw:gap-2 tw:mb-4">
        <div>
            <h2 class="tw:mb-1 tw:font-bold">Chức vụ</h2>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Quản lý danh mục chức vụ trong hệ thống</div>
        </div>

        <div class="tw:flex tw:gap-2">
            <x-ui.button href="{{ route('hr.dashboard') }}" variant="outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </x-ui.button>
            <x-ui.button href="{{ route('hr.positions.create') }}" variant="primary">
                <i class="bi bi-plus-circle me-1"></i> Thêm chức vụ
            </x-ui.button>
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
            {{ session('success') }}
        </x-ui.alert>
    @endif

    <x-ui.card class="border-0 shadow-sm">
        <x-ui.card-body class="tw:p-0">
            <div class="table-responsive">
                <table class="table align-middle tw:mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Tên chức vụ</th>
                            <th>Mã</th>
                            <th>Mô tả</th>
                            <th>Ngày tạo</th>
                            <th class="tw:text-right pe-3">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($positions as $position)
                            <tr>
                                <td class="ps-3">{{ $position->id }}</td>
                                <td class="tw:font-semibold">{{ $position->name }}</td>
                                <td>{{ $position->code ?? '—' }}</td>
                                <td>{{ $position->description ?? '—' }}</td>
                                <td>{{ optional($position->created_at)->format('d/m/Y H:i') }}</td>
                                <td class="tw:text-right pe-3">
                                    <div class="tw:flex tw:justify-end tw:gap-2">
                                        <x-ui.button href="{{ route('hr.positions.edit', $position->id) }}" variant="warning" size="sm" class="tw:text-[#ffffff]">
                                            <i class="bi bi-pencil-square"></i>
                                        </x-ui.button>

                                        <form action="{{ route('hr.positions.destroy', $position->id) }}" method="POST" onsubmit="return confirm('Bạn có chắc muốn xoá chức vụ này?')">
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
                                <td colspan="6" class="tw:text-center py-5 tw:text-[rgba(33,37,41,0.75)]">
                                    Chưa có chức vụ nào.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card-body>

        @if($positions->hasPages())
            <x-ui.card-footer class="bg-white border-0">
                {{ $positions->links() }}
            </x-ui.card-footer>
        @endif
    </x-ui.card>
</div>
@endsection