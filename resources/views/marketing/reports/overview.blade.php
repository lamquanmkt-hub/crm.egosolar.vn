@extends('layouts.app')
@section('title', 'Báo cáo tổng quan Marketing')

@section('content')
    @include('marketing.reports._dang-cap-nhat', [
        'tieuDe' => 'Báo cáo tổng quan Marketing',
        'moTa' => 'Trang tổng quan đang được xây dựng. Số liệu ngân sách và chỉ số hiện xem ở trang Ngân sách + chỉ số.',
    ])
@endsection
