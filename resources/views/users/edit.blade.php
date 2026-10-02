@extends('layouts.app')

@section('content')
    {{-- tw:py-4 — khoảng hở dọc chuẩn của trang. Thiếu nó thì nội dung dính sát
         thanh trên cùng, không có chỗ thở. Đo được 32 trang bị vậy; giá trị này là
         quy ước đang dùng nhiều nhất trong repo (29 trang). --}}
    <div class="container tw:py-4">
        <h3>Edit User</h3>
        @if(session('error'))
            <x-ui.alert variant="danger" :dismissible="true">
                {{ session('error') }}
            </x-ui.alert>
        @endif
        @include('users._form', [
            'action' => route('users.update', $user),
            'method' => 'PUT',
            'user' => $user
        ])
    </div>
@endsection
