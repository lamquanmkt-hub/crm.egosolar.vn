@extends('layouts.app')

@section('content')
<div class="container-fluid tw:py-6">
    <div class="tw:flex tw:justify-between tw:items-center flex-wrap tw:gap-2 tw:mb-6">
        <div>
            <h3 class="tw:mb-1 tw:font-bold">Đăng ký tăng ca</h3>
            <div class="tw:text-[rgba(33,37,41,0.75)]">Tạo đơn tăng ca để HR / quản lý duyệt và ghi nhận vào bảng chấm công</div>
        </div>

        <x-ui.button href="{{ route('hr.overtime.index') }}" variant="outline-primary" class="rounded-pill tw:px-6">
            <i class="bi bi-list-ul me-1"></i> Danh sách tăng ca
        </x-ui.button>
    </div>

    @if(session('error'))
        <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">{{ session('error') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger" class="tw:border-0 tw:shadow-[0_2px_4px_0_rgba(0,0,0,0.075)]">
            <div class="tw:font-bold tw:mb-2">Có lỗi xảy ra:</div>
            <ul class="tw:mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card class="border-0 shadow-sm rounded-4">
        <x-ui.card-body class="tw:p-6">
            <form method="POST" action="{{ route('hr.overtime.store') }}" class="tw:row tw:g-4">
                @csrf

                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:font-semibold">Ngày tăng ca</x-ui.label>
                    <x-ui.input type="date" name="overtime_date" value="{{ old('overtime_date', now()->toDateString()) }}" class="rounded-4" required />
                </div>

                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:font-semibold">Từ giờ</x-ui.label>
                    <x-ui.input type="time" name="start_time" value="{{ old('start_time', '18:00') }}" class="rounded-4" required />
                </div>

                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:font-semibold">Đến giờ</x-ui.label>
                    <x-ui.input type="time" name="end_time" value="{{ old('end_time', '20:00') }}" class="rounded-4" required />
                </div>

                <div class="tw:md:col12-6">
                    <x-ui.label class="tw:font-semibold">Người duyệt</x-ui.label>
                    <x-ui.select name="approver_id" class="rounded-4">
                        <option value="">-- HR / Admin duyệt --</option>
                        @foreach($approvers as $approver)
                            <option value="{{ $approver->id }}" {{ old('approver_id') == $approver->id ? 'selected' : '' }}>
                                {{ $approver->name }} @if($approver->email) - {{ $approver->email }} @endif
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="tw:col12-12">
                    <x-ui.label class="tw:font-semibold">Lý do tăng ca</x-ui.label>
                    <x-ui.input as="textarea" name="reason" rows="4" class="rounded-4" placeholder="VD: xử lý đơn hàng gấp, hỗ trợ dự án, trực kho...">{{ old('reason') }}</x-ui.input>
                </div>

                <div class="tw:col12-12 tw:flex tw:gap-2 tw:justify-end">
                    <x-ui.button href="{{ route('hr.overtime.index') }}" variant="light" class="rounded-4 tw:px-6">Huỷ</x-ui.button>
                    <x-ui.button variant="primary" type="submit" class="rounded-4 tw:px-6">
                        <i class="bi bi-send me-1"></i> Gửi đơn tăng ca
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection
