<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EGO Workspace')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root{
            --ws-text:#f8fafc;--ws-muted:#a9b7c7;--ws-line:rgba(255,255,255,.12);
            --ws-panel:rgba(9,22,34,.76);--ws-panel2:rgba(15,33,48,.82);--ws-cyan:#22d3ee;
        }
        *{box-sizing:border-box}
        html,body{margin:0;min-height:100%;font-family:"Be Vietnam Pro",system-ui,-apple-system,"Segoe UI",sans-serif;background:#07131e;color:var(--ws-text)}
        body{min-height:100vh;background:
            radial-gradient(circle at 12% 12%,rgba(34,211,238,.15),transparent 28%),
            radial-gradient(circle at 86% 16%,rgba(99,102,241,.16),transparent 27%),
            linear-gradient(135deg,#06111b 0%,#0b2231 48%,#07131e 100%)}
        body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.18;background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);background-size:42px 42px;mask-image:linear-gradient(to bottom,#000,transparent 82%)}
        a{text-decoration:none;color:inherit}
        button,input{font:inherit}
        .ws-shell{min-height:100vh;position:relative;z-index:1}
        .ws-topbar{height:82px;display:flex;align-items:center;justify-content:space-between;padding:0 48px;border-bottom:1px solid var(--ws-line);background:rgba(6,17,27,.62);backdrop-filter:blur(18px);position:sticky;top:0;z-index:20}
        .ws-brand{display:flex;align-items:center;gap:14px;min-width:0}.ws-brand img{width:44px;height:44px;object-fit:contain;border-radius:13px;background:#fff;padding:5px}.ws-brand strong{display:block;font-size:15px;letter-spacing:.04em}.ws-brand small{display:block;color:var(--ws-muted);font-size:10px;font-weight:800;letter-spacing:.16em;margin-top:2px}
        .ws-user{display:flex;align-items:center;gap:12px}.ws-user-meta{text-align:right}.ws-user-name{font-size:13px;font-weight:800}.ws-user-dept{font-size:10px;color:var(--ws-muted);margin-top:3px}.ws-avatar{width:42px;height:42px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#0ea5e9,#14b8a6);font-size:17px;font-weight:900;box-shadow:0 10px 30px rgba(14,165,233,.22)}
        .ws-logout{border:1px solid var(--ws-line);background:rgba(255,255,255,.055);color:#dce7f1;border-radius:12px;padding:9px 11px;cursor:pointer}.ws-logout:hover{background:rgba(255,255,255,.10)}
        .ws-main{width:min(1180px,calc(100% - 44px));margin:0 auto;padding:54px 0 70px}
        .ws-kicker{display:inline-flex;align-items:center;gap:8px;color:#9cecf7;font-size:11px;font-weight:850;letter-spacing:.12em;text-transform:uppercase}.ws-title{font-size:clamp(28px,4vw,48px);line-height:1.08;margin:10px 0 12px;letter-spacing:-.035em}.ws-desc{margin:0;max-width:720px;color:#b6c4d1;font-size:14px;line-height:1.7}
        .ws-toolbar{margin-top:26px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}.ws-pill{display:inline-flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid var(--ws-line);border-radius:999px;background:rgba(255,255,255,.045);font-size:11px;color:#cbd5e1}.ws-pill b{color:#fff}
        .ws-alert{margin:22px 0 0;padding:15px 17px;border-radius:16px;border:1px solid rgba(248,113,113,.35);background:rgba(127,29,29,.24);display:flex;gap:12px;align-items:flex-start;color:#fee2e2}.ws-alert i{font-size:20px;color:#fca5a5}.ws-alert strong{display:block;font-size:13px}.ws-alert span{display:block;font-size:11px;color:#fecaca;margin-top:3px;line-height:1.55}
        .ws-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin-top:34px}.ws-card{position:relative;min-height:205px;padding:22px;border:1px solid var(--ws-line);border-radius:24px;background:linear-gradient(160deg,rgba(255,255,255,.09),rgba(255,255,255,.035));box-shadow:0 22px 55px rgba(0,0,0,.18);overflow:hidden;transition:.2s ease}.ws-card:before{content:"";position:absolute;inset:auto -40px -70px auto;width:150px;height:150px;border-radius:999px;background:var(--accent,#22d3ee);filter:blur(55px);opacity:.16}.ws-card:hover{transform:translateY(-4px);border-color:color-mix(in srgb,var(--accent,#22d3ee) 44%,transparent);background:linear-gradient(160deg,rgba(255,255,255,.12),rgba(255,255,255,.045))}.ws-card.is-locked{opacity:.58;filter:saturate(.65)}.ws-card.is-locked:hover{transform:none;border-color:rgba(255,255,255,.16)}
        .ws-card-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.ws-icon{width:56px;height:56px;border-radius:18px;display:grid;place-items:center;background:color-mix(in srgb,var(--accent,#22d3ee) 20%,transparent);border:1px solid color-mix(in srgb,var(--accent,#22d3ee) 30%,transparent);font-size:24px;color:#fff}.ws-lock{width:31px;height:31px;border-radius:10px;display:grid;place-items:center;border:1px solid var(--ws-line);background:rgba(0,0,0,.17);color:#cbd5e1;font-size:13px}.ws-card h3{font-size:16px;margin:19px 0 7px}.ws-card p{font-size:11px;line-height:1.55;color:#aebdca;margin:0;min-height:34px}.ws-card-foot{margin-top:17px;display:flex;align-items:center;justify-content:space-between;font-size:10px;font-weight:800;color:#d7e4ed}.ws-status{display:inline-flex;align-items:center;gap:6px}.ws-dot{width:7px;height:7px;border-radius:999px;background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.11)}.is-locked .ws-dot{background:#94a3b8;box-shadow:none}.ws-arrow{font-size:15px;color:var(--accent,#22d3ee)}
        .ws-back{display:inline-flex;align-items:center;gap:8px;padding:10px 13px;border:1px solid var(--ws-line);border-radius:12px;background:rgba(255,255,255,.05);font-size:11px;font-weight:800;color:#dbeafe}.ws-back:hover{background:rgba(255,255,255,.09)}
        .ws-dept-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}.ws-dept-title{display:flex;align-items:center;gap:16px}.ws-dept-icon{width:62px;height:62px;border-radius:20px;display:grid;place-items:center;background:color-mix(in srgb,var(--accent) 20%,transparent);border:1px solid color-mix(in srgb,var(--accent) 34%,transparent);font-size:26px}.ws-dept-title h1{margin:0;font-size:31px}.ws-dept-title p{margin:7px 0 0;color:#aebdca;font-size:12px}.ws-search{width:min(370px,100%);position:relative}.ws-search i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#8ba0b4}.ws-search input{width:100%;height:45px;border:1px solid var(--ws-line);border-radius:14px;background:rgba(255,255,255,.055);color:#fff;padding:0 14px 0 40px;outline:none}.ws-search input:focus{border-color:rgba(34,211,238,.46);box-shadow:0 0 0 4px rgba(34,211,238,.07)}.ws-search input::placeholder{color:#7f93a6}
        .ws-section{margin-top:38px}.ws-section-head{display:flex;align-items:end;justify-content:space-between;gap:14px;margin-bottom:14px}.ws-section h2{margin:0;font-size:14px;letter-spacing:.02em}.ws-section small{color:#7f93a6;font-size:10px}.ws-app-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.ws-app{min-height:126px;padding:17px;border-radius:19px;border:1px solid var(--ws-line);background:rgba(255,255,255,.055);display:flex;gap:14px;align-items:flex-start;transition:.18s ease}.ws-app:hover{transform:translateY(-3px);background:rgba(255,255,255,.085);border-color:color-mix(in srgb,var(--accent) 38%,transparent)}.ws-app-icon{width:45px;height:45px;flex:0 0 45px;border-radius:14px;display:grid;place-items:center;background:color-mix(in srgb,var(--accent) 19%,transparent);color:#fff;font-size:19px}.ws-app h3{font-size:12px;margin:2px 0 6px}.ws-app p{font-size:10px;line-height:1.5;color:#9fb0bf;margin:0}.ws-app-go{margin-left:auto;color:var(--accent);font-size:14px}.ws-empty{padding:28px;border:1px dashed var(--ws-line);border-radius:18px;color:#9fb0bf;font-size:12px;text-align:center}
        @media(max-width:1000px){.ws-grid,.ws-app-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.ws-topbar{padding:0 24px}}
        @media(max-width:760px){.ws-grid,.ws-app-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ws-user-meta{display:none}.ws-main{width:min(100% - 28px,1180px);padding-top:35px}.ws-topbar{height:70px;padding:0 14px}.ws-dept-head{display:block}.ws-search{margin-top:18px;width:100%}}
        @media(max-width:520px){.ws-grid,.ws-app-grid{grid-template-columns:1fr}.ws-card{min-height:170px}.ws-brand strong{font-size:12px}.ws-brand small{font-size:8px}.ws-brand img{width:38px;height:38px}.ws-title{font-size:30px}}
    </style>
    @stack('styles')
</head>
<body>
<div class="ws-shell">
    <header class="ws-topbar">
        <a href="{{ route('ego.workspace.index') }}" class="ws-brand">
            <img src="{{ asset('logo/ego-solar-white.png') }}" alt="EGO">
            <span><strong>EGO VIET NAM</strong><small>WORKSPACE</small></span>
        </a>
        <div class="ws-user">
            <div class="ws-user-meta">
                <div class="ws-user-name">{{ auth()->user()->name }}</div>
                <div class="ws-user-dept">{{ optional(auth()->user()->department)->name ?: 'Chưa gán phòng ban' }}</div>
            </div>
            <div class="ws-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name ?: 'U', 0, 1)) }}</div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="ws-logout" type="submit" title="Đăng xuất"><i class="bi bi-box-arrow-right"></i></button></form>
        </div>
    </header>
    @yield('content')
</div>
@stack('scripts')
</body>
</html>
