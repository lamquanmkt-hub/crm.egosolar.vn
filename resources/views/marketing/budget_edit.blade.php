@extends('layouts.app')

@section('content')
<div class="container-fluid tw:px-6 tw:mt-4">
    <div class="tw:flex tw:justify-between tw:items-center tw:mb-4">
        <div>
            <h4 class="tw:font-bold tw:mb-0">Sửa ngân sách</h4>
            <small class="tw:text-[rgba(33,37,41,0.75)]">Marketing / Ngân sách</small>
        </div>

        <div class="tw:flex tw:gap-2">
            @hasanyrole('marketing_manager|admin')
            <form method="POST" action="{{ route('marketing.budget.clone_next', $row->id) }}" class="d-inline">
                @csrf
                <x-ui.button variant="outline-primary" type="submit">+ Tạo dòng tháng sau</x-ui.button>
            </form>
            @endhasanyrole

            <x-ui.button href="{{ route('marketing.budget') }}" variant="outline-secondary">Quay lại</x-ui.button>
        </div>
    </div>

    @if (session('success'))
        <x-ui.alert variant="success" class="tw:py-2">{{ session('success') }}</x-ui.alert>
    @endif

    @if ($errors->any())
        <x-ui.alert variant="danger">
            <div class="tw:font-semibold tw:mb-1">Có lỗi dữ liệu:</div>
            <ul class="tw:mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card class="border-0 shadow-sm">
        <x-ui.card-body>
            <form method="POST" action="{{ route('marketing.budget.update', $row->id) }}" class="tw:row tw:g-2">
                @csrf
                @method('PUT')

                <div class="tw:md:col12-2">
                    <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)]">Tháng</x-ui.label>
                    <x-ui.input type="month" name="month" required
                           value="{{ old('month', optional($row->month)->format('Y-m')) }}" />
                </div>

                <div class="tw:md:col12-2">
                    <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)]">Kênh</x-ui.label>
                    <x-ui.input type="text" name="platform" required
                           value="{{ old('platform', $row->platform) }}" />
                </div>

                <div class="tw:md:col12-3">
                    <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)]">Chiến dịch</x-ui.label>
                    <x-ui.select name="campaign_id" required>
                        <option value="">-- Chọn chiến dịch --</option>
                        @foreach($campaigns as $c)
                            <option value="{{ $c->id }}"
                                {{ (string)old('campaign_id', $row->campaign_id) === (string)$c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="tw:md:col12-2">
                    <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)]">Ngân sách (đ)</x-ui.label>
                    <x-ui.input type="number" name="budget" min="0" required
                           value="{{ old('budget', $row->budget) }}" />
                </div>

                <div class="tw:md:col12-2">
                    <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)]">Đã chi (đ)</x-ui.label>
                    <x-ui.input type="number" name="actual_spent" min="0"
                           value="{{ old('actual_spent', $row->actual_spent) }}" />
                </div>

                <div class="tw:md:col12-1 tw:flex tw:items-end">
                    <x-ui.button variant="success" type="submit" class="tw:w-full">Lưu</x-ui.button>
                </div>

                <div class="tw:col12-12">
                    <x-ui.label class="small tw:text-[rgba(33,37,41,0.75)]">Ghi chú</x-ui.label>
                    <x-ui.input type="text" name="note" value="{{ old('note', $row->note) }}" />
                </div>
            </form>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection
