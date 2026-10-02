@extends('layouts.app')

@section('content')
{{-- `.container-fluid` = width 100% + đệm 12px + margin auto; trang đã có `tw:px-6` nên đệm đó
     vốn đã bị đè — giữ nguyên để không đổi gì. --}}
<div class="tw:w-full tw:mx-auto tw:px-6 tw:mt-4">

    <div class="tw:flex tw:justify-between tw:items-center tw:mb-4">
        <div>
            <h4 class="tw:font-bold tw:mb-0">Sửa chỉ số Marketing</h4>
            <small class="tw:text-[rgba(33,37,41,0.75)]">Marketing / Chỉ số</small>
        </div>
        <x-ui.button href="{{ route('marketing.budget') }}" variant="outline-secondary">Quay lại</x-ui.button>
    </div>

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


    <x-ui.card class="tw:[border:0] tw:[box-shadow:0_0.125rem_0.25rem_rgba(0,0,0,0.075)]">
        <x-ui.card-body>
            <form method="POST" action="{{ route('marketing.metrics.update', $formValues->id) }}" class="tw:row tw:g-2">
                @csrf
                @method('PUT')

                <div class="tw:md:col12-3">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Từ ngày</x-ui.label>
                    <x-ui.input type="date" name="date_from" required value="{{ $formValues->dateFrom }}" />
                </div>

                <div class="tw:md:col12-3">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Đến ngày</x-ui.label>
                    <x-ui.input type="date" name="date_to" required value="{{ $formValues->dateTo }}" />
                </div>

                <div class="tw:md:col12-3">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Kênh</x-ui.label>
                    <x-ui.select name="platform" required>
                        @foreach(\App\View\Presenters\Marketing\MarketingMetricEditPresenter::PLATFORMS as $p)
                            <option value="{{ $p }}" {{ $formValues->platform == $p ? 'selected' : '' }}>
                                {{ $p }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="tw:md:col12-3">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Chiến dịch</x-ui.label>
                    <x-ui.select name="campaign_id" required>
                        <option value="">-- Chọn chiến dịch --</option>
                        @foreach($campaigns as $c)
                            <option value="{{ $c->id }}" {{ $formValues->campaignId === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </x-ui.select>
                </div>

                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Reach (tiếp cận)</x-ui.label>
                    <x-ui.input type="number" name="reach" min="0" required value="{{ $formValues->reach }}" />
                </div>

                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Số lead</x-ui.label>
                    <x-ui.input type="number" name="leads" min="0" required value="{{ $formValues->leads }}" />
                </div>

                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Chi tiêu (nếu có)</x-ui.label>
                    <x-ui.input type="number" name="spend" min="0" value="{{ $formValues->spend }}" />
                </div>

                <div class="tw:col12-12"><div class="tw:font-semibold tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mt-2">Giới tính (số lượng)</div></div>
                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:text-[0.875em]">Nam</x-ui.label>
                    <x-ui.input type="number" name="gender[male]" min="0" value="{{ $formValues->gender['male'] }}" />
                </div>
                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:text-[0.875em]">Nữ</x-ui.label>
                    <x-ui.input type="number" name="gender[female]" min="0" value="{{ $formValues->gender['female'] }}" />
                </div>
                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:text-[0.875em]">Không rõ</x-ui.label>
                    <x-ui.input type="number" name="gender[unknown]" min="0" value="{{ $formValues->gender['unknown'] }}" />
                </div>

                <div class="tw:col12-12"><div class="tw:font-semibold tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mt-2">Độ tuổi (số lượng)</div></div>
                @foreach(\App\View\Presenters\Marketing\MarketingMetricEditPresenter::AGE_RANGES as $ar)
                    <div class="tw:md:col12-4">
                        <x-ui.label class="tw:text-[0.875em]">{{ $ar }}</x-ui.label>
                        <x-ui.input type="number" name="age[{{ $ar }}]" min="0" value="{{ $formValues->age[$ar] }}" />
                    </div>
                @endforeach

                <div class="tw:col12-12"><div class="tw:font-semibold tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)] tw:mt-2">Khu vực (số lượng)</div></div>
                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:text-[0.875em]">HCM</x-ui.label>
                    <x-ui.input type="number" name="region[HCM]" min="0" value="{{ $formValues->region['HCM'] }}" />
                </div>
                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:text-[0.875em]">Hà Nội</x-ui.label>
                    <x-ui.input type="number" name="region[Hà Nội]" min="0" value="{{ $formValues->region['Hà Nội'] }}" />
                </div>
                <div class="tw:md:col12-4">
                    <x-ui.label class="tw:text-[0.875em]">Khác</x-ui.label>
                    <x-ui.input type="number" name="region[Khác]" min="0" value="{{ $formValues->region['Khác'] }}" />
                </div>

                <div class="tw:col12-12">
                    <x-ui.label class="tw:text-[0.875em] tw:text-[rgba(33,37,41,0.75)]">Ghi chú</x-ui.label>
                    <x-ui.input type="text" name="note" value="{{ $formValues->note }}" />
                </div>

                <div class="tw:col12-12 tw:flex tw:justify-end tw:gap-2 tw:mt-2">
                    <x-ui.button href="{{ route('marketing.budget') }}" variant="light">Hủy</x-ui.button>
                    <x-ui.button variant="primary" type="submit">Lưu chỉ số</x-ui.button>
                </div>
            </form>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection
