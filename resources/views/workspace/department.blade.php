@extends('workspace.layout')
@section('title',$workspace['label'].' · EGO Workspace')
@section('content')
<main class="ws-main" style="--accent:{{ $workspace['accent'] }}">
    <a class="ws-back" href="{{ route('ego.workspace.index') }}"><i class="bi bi-arrow-left"></i> Tất cả phòng ban</a>

    <div class="ws-toolbar ws-dept-head" style="margin-top:24px">
        <div class="ws-dept-title">
            <div class="ws-dept-icon"><i class="bi {{ $workspace['icon'] }}"></i></div>
            <div><div class="ws-kicker">Không gian đang sử dụng</div><h1>{{ $workspace['label'] }}</h1><p>{{ $workspace['description'] }}</p></div>
        </div>
        <div class="ws-search"><i class="bi bi-search"></i><input id="wsAppSearch" type="search" placeholder="Tìm ứng dụng, chức năng..."></div>
    </div>

    <section class="ws-section">
        <div class="ws-section-head"><div><h2>Ứng dụng phòng ban</h2><small>Chỉ hiển thị ứng dụng phù hợp với phòng ban và quyền tài khoản.</small></div></div>
        @if(count($apps['department']))
            <div class="ws-app-grid" data-app-grid>
                @foreach($apps['department'] as $app)
                    <a class="ws-app" href="{{ $app['url'] }}" data-app-name="{{ mb_strtolower($app['label'].' '.$app['subtitle']) }}">
                        <div class="ws-app-icon"><i class="bi {{ $app['icon'] }}"></i></div>
                        <div><h3>{{ $app['label'] }}</h3><p>{{ $app['subtitle'] }}</p></div>
                        <i class="bi bi-arrow-up-right ws-app-go"></i>
                    </a>
                @endforeach
            </div>
        @else
            <div class="ws-empty">Chưa có ứng dụng nào được cấp quyền trong phòng ban này.</div>
        @endif
    </section>

    @if(count($apps['personal']))
        <section class="ws-section">
            <div class="ws-section-head"><div><h2>Ứng dụng cá nhân</h2><small>Các chức năng dùng chung cho tài khoản của bạn.</small></div></div>
            <div class="ws-app-grid" data-app-grid>
                @foreach($apps['personal'] as $app)
                    <a class="ws-app" href="{{ $app['url'] }}" data-app-name="{{ mb_strtolower($app['label'].' '.$app['subtitle']) }}">
                        <div class="ws-app-icon"><i class="bi {{ $app['icon'] }}"></i></div>
                        <div><h3>{{ $app['label'] }}</h3><p>{{ $app['subtitle'] }}</p></div>
                        <i class="bi bi-arrow-up-right ws-app-go"></i>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</main>
@endsection
@push('scripts')
<script>
(function(){
    const input=document.getElementById('wsAppSearch');
    if(!input)return;
    input.addEventListener('input',function(){
        const q=(this.value||'').trim().toLocaleLowerCase('vi');
        document.querySelectorAll('[data-app-name]').forEach(function(card){
            card.style.display=!q||card.dataset.appName.includes(q)?'flex':'none';
        });
    });
})();
</script>
@endpush
