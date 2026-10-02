
@if($branding->faviconUrl !== '')
    <link rel="icon" href="{{ $branding->faviconUrl }}">
@endif

<style id="ego-system-branding-v2">
    :root {
        --ego-brand-primary: {{ $branding->primaryColor }};
        --ego-brand-secondary: {{ $branding->secondaryColor }};
        --ego-theme-sidebar: {{ $branding->sidebarColor }};
        --ego-theme-topbar: {{ $branding->topbarColor }};
        --ego-theme-page: {{ $branding->pageBackground }};
        --ego-theme-radius: {{ $branding->cardRadius }}px;
        --bg: var(--ego-theme-page);
        --radius: var(--ego-theme-radius);
    }

    body:not(.ego-cinematic-page),
    body:not(.ego-cinematic-page) .ego-page,
    body:not(.ego-cinematic-page) .ego-page__body,
    body:not(.ego-cinematic-page) .main-content {
        background-color: var(--ego-theme-page) !important;
    }

    html body #sidebar.ego-sidebar {
        background:
            radial-gradient(720px 430px at -20% 0%, color-mix(in srgb, var(--ego-brand-primary) 24%, transparent), transparent 58%),
            radial-gradient(620px 420px at 112% 18%, color-mix(in srgb, var(--ego-brand-secondary) 18%, transparent), transparent 55%),
            linear-gradient(180deg, color-mix(in srgb, var(--ego-theme-sidebar) 88%, #ffffff 12%), var(--ego-theme-sidebar)) !important;
    }

    html body .ego-topbar,
    html body .crm-topbar,
    html body .app-topbar,
    html body nav.navbar {
        background-color: var(--ego-theme-topbar) !important;
    }

    html body #sidebar .ego-link.active,
    html body #sidebar .ego-link[aria-expanded="true"] {
        border-color: color-mix(in srgb, var(--ego-brand-primary) 78%, #ffffff 22%) !important;
        box-shadow:
            inset 3px 0 0 var(--ego-brand-primary),
            0 8px 22px color-mix(in srgb, var(--ego-brand-primary) 18%, transparent) !important;
    }

    html body #sidebar .ego-sublink.active {
        color: #ffffff !important;
        background: linear-gradient(135deg,
            color-mix(in srgb, var(--ego-brand-primary) 30%, transparent),
            color-mix(in srgb, var(--ego-brand-secondary) 18%, transparent)
        ) !important;
    }

    html body .btn-primary,
    html body .ego-btn-primary,
    html body .cx-btn--primary,
    html body .eas-btn--primary {
        color: #ffffff !important;
        border-color: transparent !important;
        background: linear-gradient(135deg, var(--ego-brand-primary), var(--ego-brand-secondary)) !important;
    }

    html body .card,
    html body .cx-panel,
    html body .dashboard-card,
    html body .content-card,
    html body .modal-content,
    html body [data-ego-card] {
        border-radius: var(--ego-theme-radius) !important;
    }

    html[data-ego-density="compact"] .card-body,
    html[data-ego-density="compact"] .cx-card-body,
    html[data-ego-density="compact"] [data-ego-card-body] {
        padding-top: .72rem !important;
        padding-bottom: .72rem !important;
    }

    html[data-ego-density="compact"] .table > :not(caption) > * > *,
    html[data-ego-density="compact"] table td,
    html[data-ego-density="compact"] table th {
        padding-top: .46rem !important;
        padding-bottom: .46rem !important;
    }
</style>

<script id="ego-system-branding-data" type="application/json">{!! $branding->runtimeJson ?: '{}' !!}</script>
<script>
    (() => {
        const applyEgoBranding = () => {
            const node = document.getElementById('ego-system-branding-data');
            if (!node) return;

            let theme = {};
            try { theme = JSON.parse(node.textContent || '{}'); } catch (_) { return; }

            document.documentElement.setAttribute(
                'data-ego-density',
                theme.density || 'comfortable'
            );

            document.querySelectorAll('#sidebar .ego-brand__logo-big').forEach((image) => {
                if (theme.logoSidebar) image.src = theme.logoSidebar;
            });

            document.querySelectorAll('.ego-login-brand__logo, [data-ego-logo-light]').forEach((image) => {
                if (theme.logoLight) image.src = theme.logoLight;
            });

            document.querySelectorAll('[data-ego-brand-name]').forEach((element) => {
                element.textContent = theme.brandName || 'EGO Solar CRM';
            });

            const themeMeta = document.querySelector('meta[name="theme-color"]');
            if (themeMeta && theme.sidebarColor) themeMeta.content = theme.sidebarColor;
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyEgoBranding, { once: true });
        } else {
            applyEgoBranding();
        }
    })();
</script>
