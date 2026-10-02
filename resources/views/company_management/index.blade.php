@extends('layouts.app')

@section('content')
<div class="container-fluid tw:py-6">
    <div class="tw:flex tw:items-center tw:justify-between tw:mb-4">
        <div>
            <h3 class="tw:mb-1 tw:font-bold">QUẢN LÝ CÔNG TY</h3>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Thêm, sửa, xóa công ty dùng cho kho hàng và PDF đơn hàng.</div>
        </div>

        <x-ui.button href="{{ route('company-management.create') }}" variant="primary">
            + Thêm công ty
        </x-ui.button>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    @if($errors->any())
        <x-ui.alert variant="danger">
            <div class="tw:font-semibold">Có lỗi xảy ra:</div>
            <ul class="tw:mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
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
                            <th>Thanh toán</th>
                            <th>Trạng thái</th>
                            <th style="width:160px;" class="tw:text-right">Hành động</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($companies as $company)
                            <tr>
                                <td>{{ $company->id }}</td>

                                <td>
                                    <div class="tw:font-semibold">{{ $company->name }}</div>
                                    <small class="tw:text-[rgba(33,37,41,0.75)]">{{ $company->address ?: 'Chưa có địa chỉ' }}</small>
                                </td>

                                <td>
                                    <span class="badge bg-info tw:text-[#212529]">{{ $company->code }}</span>
                                </td>

                                <td>{{ $company->tax_code ?: '---' }}</td>
                                <td>{{ $company->email ?: '---' }}</td>

                                <td>
                                    <div><b>STK:</b> {{ $company->bank_account ?: '---' }}</div>
                                    <div><b>NH:</b> {{ $company->bank_name ?: '---' }}</div>
                                    <small class="tw:text-[rgba(33,37,41,0.75)]">{{ $company->bank_holder ?: '' }}</small>
                                </td>

                                <td>
                                    @if($company->is_active)
                                        <span class="badge bg-success">Hoạt động</span>
                                    @else
                                        <span class="badge bg-secondary">Không hoạt động</span>
                                    @endif
                                </td>

                                <td class="tw:text-right">
                                    <x-ui.button href="{{ route('company-management.edit', $company) }}" variant="warning" size="sm">
                                        Sửa
                                    </x-ui.button>

                                    <form method="POST"
                                          action="{{ route('company-management.destroy', $company) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('Bạn chắc chắn muốn xóa công ty này? Nếu công ty đã có đơn/kho liên quan, hệ thống sẽ chỉ tắt hoạt động.');">
                                        @csrf
                                        @method('DELETE')

                                        <x-ui.button variant="danger" size="sm" type="submit">
                                            Xóa
                                        </x-ui.button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="tw:text-center tw:text-[rgba(33,37,41,0.75)] tw:py-6">
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