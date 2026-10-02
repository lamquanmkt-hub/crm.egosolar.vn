<!DOCTYPE html>
<html lang="vi" class="crm-navigation-ready">
<head>
    {{-- `charset` phải đứng ĐẦU <head>: spec buộc nó nằm trong 1024 byte đầu, và đặt sau một
         <link> CDN vừa sai thứ tự vừa chiếm mất lượt kết nối sớm. --}}
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <title>@yield('title', 'CRM System')</title>

    {{-- Google Font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Bootstrap 5 — GIỮ NGUYÊN qua CDN, cố ý không đưa vào bundle/@layer.
         Đưa vào @layer thì utility Tailwind mới thắng được Bootstrap, nhưng đã đo:
         cách đó làm bung submenu sidebar vì 34.937 dòng CSS nội tuyến (143 view) và
         75 file public/css đều ngoài lớp, sẽ thắng ngược Bootstrap. Xem app.css. --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/main.css') }}?v={{ filemtime(public_path('css/main.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/crm-topbar.css') }}?v={{ filemtime(public_path('css/crm-topbar.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/ego-ai-assistant.css') }}?v={{ filemtime(public_path('css/ego-ai-assistant.css')) }}">
    {{-- CRM_NAVIGATION_PRO_V2_CSS --}}
    <link rel="stylesheet" href="{{ asset('css/crm-navigation-pro.css') }}?v={{ filemtime(public_path('css/crm-navigation-pro.css')) }}">

    <style>
        :root{
            --app-font: "Be Vietnam Pro", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            --bg:#f6f8fc;
            --card:#ffffff;
            --border: rgba(15,23,42,.08);
            --radius: 16px;
        }

        /* Skip-link: ẩn khỏi mắt nhưng vẫn ở trong luồng tab; hiện ra khi được focus. */
        .ego-skip-link{
            position:absolute; left:-9999px; top:0; z-index:9999;
            padding:10px 16px; border-radius:0 0 12px 0;
            background:#0f172a; color:#fff; font-weight:700; text-decoration:none;
        }
        .ego-skip-link:focus{ left:0; }

        html, body{
            font-family: var(--app-font) !important;
            background: var(--bg);
            margin: 0;
            padding: 0;
            height: 100%;
        }
        body, button, input, select, textarea, .btn, .form-control, .form-select, table{
            font-family: var(--app-font) !important;
        }

        /* 
 SHELL: sidebar full top + page bên phải */
        .ego-shell{
            min-height: 100dvh;
            display: flex;
            width: 100%;
        }

        /* 
 PAGE: navbar + content theo cột */
        .ego-page{
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* 
 Navbar “dính” trên cùng của khu vực page */
        .ego-topbar{
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        /* 
 Content body */
        .ego-page__body{
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
        }

        /* `[data-ego-card]` là móc của thẻ đã quy đổi khỏi Bootstrap; giữ `.card`
           cho phần markup chưa chuyển. Nền/viền/bo PHẢI ở đây chứ không nằm trong
           lớp Tailwind của thẻ: hai luật này `!important` nên vẫn thắng Tailwind,
           và bán kính còn do chủ đề chạy động quyết định (xem system-branding-runtime). */
        .card,
        [data-ego-card]{
            border: 1px solid var(--border) !important;
            border-radius: var(--radius) !important;
            background: var(--card);
        }

        /* helper padding nếu trang nào cần */
        .ego-container{ padding: 18px 18px 22px; }
        @media (max-width: 991.98px){
            .ego-container{ padding: 12px 12px 18px; }
        }

        .table-responsive{ overflow-x:auto !important; -webkit-overflow-scrolling: touch; }
        table{ max-width:100%; }

        /* Mobile: sidebar là offcanvas nên shell vẫn ok */
        @media (max-width: 991.98px){
            .ego-shell{ display:block; }
            .ego-page{ display:block; }
            .ego-page__body{ display:block; }
        }

        /* Only Ads report page: full width */
        .mr-ads-page{ max-width:none !important; width:100%; }
        :root{
  --ego-sb: 292px;
  --ego-sb-collapsed: 86px;
}

/* ===== Desktop layout: sidebar dính + content full ===== */
@media (min-width: 992px){
  main.ego-main{
    display: flex !important;
    align-items: stretch;
    min-height: 100vh;
  }

 /* Sidebar là 1 cột cố định + đứng yên khi cuộn */
#sidebar.ego-sidebar{
  flex: 0 0 var(--ego-sb, 292px);
  position: sticky !important;
  top: 0;
  height: 100dvh;
  max-height: 100dvh;
  overflow-y: auto;
  overflow-x: hidden;
  align-self: flex-start;
  z-index: 1040;
  scrollbar-width: thin;
}
  /* Content là cột còn lại */
  .main-content{
    flex: 1 1 auto;
    width: auto !important;
    max-width: 100% !important;
    margin-left: 0 !important; /* 
 bỏ margin-left kiểu cũ */
    min-width: 0; /* 
 tránh table đẩy bung layout */
  }

  /* Khi collapsed */
  body.ego-sidebar-collapsed #sidebar.ego-sidebar{
    flex-basis: var(--ego-sb-collapsed, 86px);
  }
}
    </style>

    @yield('styles')
    @stack('styles')
{{-- EGO_SYSTEM_BRANDING_RUNTIME_V2 --}}
    @include('partials.system-branding-runtime')
    {{-- EGO_LEAVE_DASHBOARD_ALERTS_CSS_V110_START --}}
    <link rel="stylesheet" href="{{ asset('css/ego-leave-dashboard-alerts.css') }}?v={{ file_exists(public_path('css/ego-leave-dashboard-alerts.css')) ? filemtime(public_path('css/ego-leave-dashboard-alerts.css')) : '1.1.0' }}">
    {{-- EGO_LEAVE_DASHBOARD_ALERTS_CSS_V110_END --}}

    {{-- Tailwind v4 + JS ứng dụng, qua Vite. ĐẶT CUỐI <head> LÀ CÓ CHỦ Ý:
         utility mang tiền tố `tw:` nên không trùng tên với bất cứ thứ gì ở trên,
         đặt cuối để chúng thắng cả Bootstrap lẫn public/css khi chuyển từng trang.
         Trang chưa chuyển không bị ảnh hưởng vì không có class nào trùng. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>

<a class="ego-skip-link" href="#ego-main-content">Bỏ qua điều hướng, tới nội dung chính</a>

<div class="ego-shell">
    {{-- Sidebar (full top) --}}
    @include('partials.sidebar')

    <div class="ego-page">
        {{-- Navbar (chỉ nằm bên phải, không đẩy sidebar xuống nữa) --}}
        @include('partials.navbar')

        <main id="ego-main-content" class="ego-page__body">
            @yield('content')
        </main>
    </div>
</div>


{{-- EGO_TASK_FLOAT_ALL_ROLES_V150_START --}}
@if(
    auth()->check()
    && (
        request()->routeIs('dashboard')
        || request()->is('/')
    )
)
    @include('dashboard.partials.task-float')

    <script
        src="{{ asset('js/ego-task-dashboard-drawer.js') }}?v={{ file_exists(public_path('js/ego-task-dashboard-drawer.js')) ? filemtime(public_path('js/ego-task-dashboard-drawer.js')) : '1.5.0' }}"
        defer
    ></script>
@endif
{{-- EGO_TASK_FLOAT_ALL_ROLES_V150_END --}}

{{-- Thay bootstrap.bundle.min.js (80KB, CDN) bằng bản trong dự án (21KB).
     Hành vi DOM đã đo đối chiếu 4061/4061 thuộc tính qua 14 bước thao tác.
     Phải là script cổ điển và đặt đúng chỗ cũ: @stack('scripts') phía dưới
     có mã nội tuyến gọi thẳng new bootstrap.Modal(...) lúc phân tích trang. --}}
<script src="{{ asset('js/bootstrap-compat.js') }}?v={{ filemtime(public_path('js/bootstrap-compat.js')) }}"></script>
<script src="{{ asset('js/main.js') }}?v={{ filemtime(public_path('js/main.js')) }}"></script>
<script src="{{ asset('js/crm-topbar.js') }}?v={{ filemtime(public_path('js/crm-topbar.js')) }}"></script>
<script src="{{ asset('js/ego-ai-assistant.js') }}?v={{ filemtime(public_path('js/ego-ai-assistant.js')) }}"></script>
{{-- CRM_NAVIGATION_PRO_V2_JS --}}
<script src="{{ asset('js/crm-navigation-pro.js') }}?v={{ filemtime(public_path('js/crm-navigation-pro.js')) }}"></script>

{{-- Ghim phiên bản CDN, đừng bỏ. URL không ghim (…/npm/chart.js) để production tự
     nhảy theo bản mới nhất của bên thứ ba — có lúc đo được JS trả 2.6.2 còn CSS
     trả 2.6.1 của cùng một thư viện. Bản ghim ở đây đã đối chiếu giống hệt byte
     với thứ CDN đang phục vụ lúc ghim (2026-09-05). --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>

{{-- Tom Select phải nạp TRƯỚC hai lệnh dưới. Trước đây nó nằm sau, nên mã
     của view chạy trong stack không thấy TomSelect và phải tự nạp thêm một
     bản riêng (orders/create từng nạp bản 2.3.1, thành ra mỗi lần mở trang
     tải hai bản thư viện khác phiên bản). --}}
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/js/tom-select.complete.min.js"></script>

@stack('scripts')
@yield('scripts')

<!-- 
 Global Toast container -->
<div class="position-fixed top-0 end-0 tw:p-4" style="z-index: 999999;">
  <div id="egoToast" class="toast tw:items-center tw:text-[#ffffff] tw:bg-[#dc3545] border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="tw:flex">
      <div class="toast-body" id="egoToastMsg">...</div>
      {{-- `m-auto` của Bootstrap là `margin:auto!important`. Bỏ được dấu `!` của
           `tw:mr-2` vì Tailwind nạp SAU Bootstrap: hai khai báo cùng `!important`
           thì thứ tự tệp quyết định. Trong CSS Tailwind, `mr-2` lại đứng sau
           `m-auto`, nên `tw:m-auto tw:mr-2` cho đúng auto/8px/auto/auto như cũ. --}}
      <x-ui.close-button white class="tw:m-auto tw:mr-2" type="button" data-bs-dismiss="toast" aria-label="Close" />
    </div>
  </div>
</div>

{{-- Đã gỡ @include('company_context.switcher').
     Partial đó không in ra gì: khối @if(...) của nó rỗng từ commit đầu tiên
     (8a2fa03). Nhưng mỗi trang nó vẫn chạy 5 lần đọc session, 3 lần kiểm schema
     và một câu SELECT trên bảng companies rồi vứt kết quả đi. Tệp giữ lại và
     đánh dấu ở đầu tệp, chưa xoá. --}}
</body>
</html>