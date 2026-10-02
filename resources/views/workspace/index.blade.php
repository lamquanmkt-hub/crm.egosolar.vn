@extends('workspace.layout')
@section('title','Chọn phòng ban · EGO Workspace')
@section('content')
<main class="ws-main">
    <div class="ws-kicker"><i class="bi bi-grid-1x2-fill"></i> EGO Workspace</div>
    <h1 class="ws-title">Chọn không gian làm việc</h1>
    <p class="ws-desc">Chọn phòng ban để mở bộ ứng dụng tương ứng. Phòng ban không thuộc quyền của bạn vẫn được hiển thị nhưng sẽ bị khóa.</p>

    <div class="ws-toolbar">
        <div class="ws-pill"><i class="bi bi-person-badge"></i> Phòng ban hiện tại: <b>{{ optional(auth()->user()->department)->name ?: 'Chưa gán' }}</b></div>
        <div class="ws-pill"><i class="bi {{ $isExecutive ? 'bi-shield-check' : 'bi-lock' }}"></i> {{ $isExecutive ? 'Quyền điều hành · truy cập tất cả phòng ban' : 'Chỉ truy cập phòng ban được cấp quyền' }}</div>
    </div>

    @if(session('workspace_error'))
        <div class="ws-alert">
            <i class="bi bi-shield-lock-fill"></i>
            <div><strong>Bạn chưa có quyền truy cập</strong><span>{{ session('workspace_error') }}</span></div>
        </div>
    @endif

    @if($primaryWorkspace === 'personal')
        <div class="ws-alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><strong>Tài khoản chưa xác định được phòng ban</strong><span>Vui lòng liên hệ quản trị viên để gán đúng Phòng ban hoặc Vai trò trước khi truy cập Workspace.</span></div>
        </div>
    @endif

    <section class="ws-grid">
        @foreach($departments as $department)
            <a href="{{ route('ego.workspace.department', ['workspace' => $department['key']]) }}"
               class="ws-card {{ $department['allowed'] ? '' : 'is-locked' }}"
               style="--accent:{{ $department['accent'] }}">
                <div class="ws-card-top">
                    <div class="ws-icon"><i class="bi {{ $department['icon'] }}"></i></div>
                    @if(!$department['allowed'])<span class="ws-lock"><i class="bi bi-lock-fill"></i></span>@elseif($department['is_primary'])<span class="ws-lock" style="color:#86efac"><i class="bi bi-check-lg"></i></span>@endif
                </div>
                <h3>{{ $department['label'] }}</h3>
                <p>{{ $department['description'] }}</p>
                <div class="ws-card-foot">
                    <span class="ws-status"><span class="ws-dot"></span>{{ $department['allowed'] ? ($department['is_primary'] ? 'Phòng ban của bạn' : 'Được phép truy cập') : 'Không thuộc phòng ban' }}</span>
                    <i class="bi {{ $department['allowed'] ? 'bi-arrow-right' : 'bi-shield-lock' }} ws-arrow"></i>
                </div>
            </a>
        @endforeach
    </section>
</main>
@endsection
