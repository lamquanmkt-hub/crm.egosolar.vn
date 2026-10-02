@extends('layouts.app')
@section('title', 'Báo cáo SEO')

@section('content')
    @include('marketing.reports._dang-cap-nhat', [
        'tieuDe' => 'Báo cáo SEO',
        'moTa' => 'Trang báo cáo SEO đang được xây dựng. Số liệu SEO hiện xem tạm ở Kế hoạch SEO trong mục Kế hoạch Marketing.',
    ])
@endsection
