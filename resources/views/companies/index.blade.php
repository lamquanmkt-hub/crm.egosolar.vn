@extends('layouts.app')

@section('content')
<div class="container-fluid tw:py-6">
    <div class="tw:flex tw:items-center tw:justify-between tw:mb-4">
        <div>
            <h4 class="tw:mb-1">Thông tin công ty</h4>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Quản lý thông tin dùng để in PDF đơn hàng.</div>
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    <x-ui.card class="shadow-sm border-0">
        <x-ui.card-body class="tw:p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle tw:mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th>Tên công ty</th>
                            <th>Mã</th>
                            <th>MST</th>
                            <th>Email</th>
                            <th>Ngân hàng</th>
                            <th style="width:120px;" class="tw:text-right">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($companies as $company)
                            <tr>
                                <td>{{ $company->id }}</td>
                                <td>
                                    <div class="tw:font-semibold">{{ $company->name }}</div>
                                    @if(!$company->is_active)
                                        <span class="badge bg-secondary">Đã tắt</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-info tw:text-[#212529]">{{ $company->code }}</span></td>
                                <td>{{ $company->tax_code ?: '---' }}</td>
                                <td>{{ $company->email ?: '---' }}</td>
                                <td>
                                    <div>{{ $company->bank_account ?: '---' }}</div>
                                    <small class="tw:text-[rgba(33,37,41,0.75)]">{{ $company->bank_name ?: '' }}</small>
                                </td>
                                <td class="tw:text-right">
                                    <x-ui.button href="{{ route('companies.edit', $company) }}" variant="warning" size="sm">
                                        Sửa
                                    </x-ui.button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-6">
                                    Chưa có công ty.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card-body>

        @if($companies->hasPages())
            <x-ui.card-footer class="bg-white">
                {{ $companies->links() }}
            </x-ui.card-footer>
        @endif
    </x-ui.card>
</div>
@endsection