@extends('layouts.app')

@section('content')
<div class="container tw:py-4">
    <div class="tw:flex tw:items-center tw:justify-between tw:mb-4">
        <h4 class="tw:mb-0">Upload Lead (CSV)</h4>
        <x-ui.button href="{{ route('marketing.leads.index') }}" variant="outline-secondary" size="sm">
            Quay lại danh sách
        </x-ui.button>
    </div>

    @if($errors->any())
        <x-ui.alert variant="danger">
            <div class="tw:font-semibold tw:mb-1">Có lỗi:</div>
            <ul class="tw:mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <x-ui.card>
        <x-ui.card-body>
            <form action="{{ route('marketing.leads.import') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="tw:mb-4">
                    <x-ui.label>File CSV</x-ui.label>
                    <x-ui.input type="file" name="file" accept=".csv,text/csv" required />
                </div>

                <div class="tw:row">
                    <div class="tw:md:col12-6 tw:mb-4">
                        <x-ui.label>Nguồn (source)</x-ui.label>
                        <x-ui.input type="text" name="source" placeholder="VD: Facebook Ads" />
                    </div>

                    <div class="tw:md:col12-6 tw:mb-4">
                        <x-ui.label>Ngày import</x-ui.label>
                        <x-ui.input type="date" name="import_date" />
                        <div class="form-text">Nếu để trống: lấy ngày hôm nay.</div>
                    </div>
                </div>

                <x-ui.button variant="primary" type="submit">Import</x-ui.button>
            </form>
        </x-ui.card-body>
    </x-ui.card>
</div>
@endsection
