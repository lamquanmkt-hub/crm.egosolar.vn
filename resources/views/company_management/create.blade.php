@extends('layouts.app')

@section('content')
<div class="container-fluid tw:py-6">
    <div class="tw:flex tw:items-center tw:justify-between tw:mb-4">
        <div>
            <h3 class="tw:mb-1 tw:font-bold">THÊM CÔNG TY</h3>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Tạo công ty mới để gắn với kho và in PDF đơn hàng.</div>
        </div>

        <x-ui.button href="{{ route('company-management.index') }}" variant="light">
            Quay lại
        </x-ui.button>
    </div>

    @include('company_management.form', [
        'company' => $company,
        'action' => route('company-management.store'),
        'method' => 'POST',
        'buttonText' => 'Thêm công ty'
    ])
</div>
@endsection