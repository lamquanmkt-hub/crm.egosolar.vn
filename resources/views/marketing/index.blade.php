@extends('layouts.app')

@section('content')
{{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
     thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
     quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
<div class="container tw:py-4">
    <h2>Marketing Dashboard</h2>
    <p>Bạn đã vào được module Marketing 🎉</p>
</div>
@endsection
