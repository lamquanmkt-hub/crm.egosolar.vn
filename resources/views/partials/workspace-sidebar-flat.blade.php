{{-- EGO_FINANCE_EXCEL_MENU_SWITCH_START --}}

@if(
    ($egoActiveWorkspace ?? session('active_workspace'))
    === 'finance'
)

    @include('partials.workspace-sidebar-finance-excel')

@elseif(
    ($egoActiveWorkspace ?? session('active_workspace'))
    === 'hr'
)

    @include('partials.workspace-sidebar-hr-excel')

@else

{{-- EGO_WORKSPACE_SERVER_FLAT_PARTIAL_V1 --}}

@php
    /*
     * Nguồn menu DUY NHẤT:
     * DepartmentWorkspaceService.
     *
     * Đây cũng chính là nguồn đang cấp ứng dụng cho
     * /workspace/{department}.
     *
     * => Workspace App và Sidebar luôn đồng bộ.
     */

    $egoWsService =
        app(\App\Services\Workspace\DepartmentWorkspaceService::class);

    $egoWsGroups =
        $egoWsService->apps(
            auth()->user(),
            $egoActiveWorkspace
        );

    $egoWsDepartmentApps =
        collect($egoWsGroups['department'] ?? []);

    $egoWsPersonalApps =
        collect($egoWsGroups['personal'] ?? []);


    /*
     * EGO_HR_PERSONAL_APPS_FALLBACK_V1
     * Ba chức năng cá nhân phải hiện ở mọi workspace, không phụ thuộc
     * DepartmentWorkspaceService có trả về hay không.
     */
    $egoHrPersonalFallbacks = [
        [
            'label' => 'Chấm công',
            'icon' => 'bi-check2-circle',
            'route' => 'hr.attendance.my',
            'url' => \Illuminate\Support\Facades\Route::has('hr.attendance.my')
                ? route('hr.attendance.my')
                : null,
        ],
        [
            'label' => 'Đơn nghỉ phép',
            'icon' => 'bi-calendar2-x',
            'route' => 'hr.leave.index',
            'url' => \Illuminate\Support\Facades\Route::has('hr.leave.index')
                ? route('hr.leave.index', ['tab' => 'mine'])
                : null,
        ],
        [
            'label' => 'Đăng ký tăng ca',
            'icon' => 'bi-clock-history',
            'route' => 'hr.overtime.index',
            'url' => \Illuminate\Support\Facades\Route::has('hr.overtime.index')
                ? route('hr.overtime.index')
                : null,
        ],
    ];

    foreach ($egoHrPersonalFallbacks as $fallbackApp) {
        if (empty($fallbackApp['url'])) {
            continue;
        }

        $exists = $egoWsPersonalApps->contains(function ($app) use ($fallbackApp) {
            return ($app['route'] ?? null) === $fallbackApp['route']
                || ($app['url'] ?? null) === $fallbackApp['url'];
        });

        if (!$exists) {
            $egoWsPersonalApps->push($fallbackApp);
        }
    }


    /*
     * Tổng quan:
     * app đầu tiên có route dashboard hoặc tên Tổng quan.
     */
    $egoWsOverview =
        $egoWsDepartmentApps->first(
            function ($app) {
                return
                    ($app['route'] ?? null) === '__workspace_dashboard__'
                    || str_starts_with(
                        mb_strtolower((string)($app['label'] ?? '')),
                        'tổng quan'
                    );
            }
        );


    /*
     * Nếu workspace có dashboard nhưng app config chưa khai báo,
     * vẫn luôn có Tổng quan.
     */
    if (!$egoWsOverview) {
        $egoWsOverview = [
            'label' => 'Tổng quan',
            'icon' => 'bi-speedometer2',
            'route' => '__workspace_dashboard__',
            'url' => route(
                'ego.workspace.dashboard',
                ['workspace' => $egoActiveWorkspace]
            ),
        ];
    }


    /*
     * Bỏ overview khỏi danh sách nghiệp vụ.
     */
    $egoWsRest =
        $egoWsDepartmentApps
            ->reject(function ($app) use ($egoWsOverview) {
                return
                    ($app['url'] ?? null)
                    ===
                    ($egoWsOverview['url'] ?? null);
            })
            ->values();


    /*
     * Phối hợp:
     * chỉ các app dùng xuyên phòng ban.
     */
    $egoWsCollaborationNames = [
        'Công việc',
        'Đề xuất',
        'Đề nghị thanh toán',
        'Đề nghị tạm ứng',
        'Hồ sơ công ty',
    ];


    $egoWsCollaboration =
        $egoWsRest
            ->filter(function ($app) use ($egoWsCollaborationNames) {
                return in_array(
                    (string)($app['label'] ?? ''),
                    $egoWsCollaborationNames,
                    true
                );
            })
            ->values();


    /*
     * Nghiệp vụ:
     * phần còn lại của ứng dụng phòng ban.
     */
    $egoWsBusiness =
        $egoWsRest
            ->reject(function ($app) use ($egoWsCollaborationNames) {
                return in_array(
                    (string)($app['label'] ?? ''),
                    $egoWsCollaborationNames,
                    true
                );
            })
            ->values();


    /*
     * Badge hiện tại.
     */
    $egoWsPendingPayment =
        (int)($egoPendingPaymentRequestsCount ?? 0);

    $egoWsPendingProposal =
        (int)($egoPendingProposalsCount ?? 0);

    $egoWsPendingOrders =
        (int)($egoPendingOrdersCount ?? 0);

    $egoWsTaskCount =
        (int)($egoMyUnfinishedTasksCount ?? 0);


    $egoWsBadge = static function (array $app) use (
        $egoWsPendingPayment,
        $egoWsPendingProposal,
        $egoWsPendingOrders,
        $egoWsTaskCount
    ): int {

        return match ($app['route'] ?? '') {
            'payment_requests.index' => $egoWsPendingPayment,
            'de-xuat.index' => $egoWsPendingProposal,
            'orders.index' => $egoWsPendingOrders,
            'tasks.index' => $egoWsTaskCount,
            default => 0,
        };
    };


    /*
     * Active state.
     */
    $egoWsIsActive =
        static function (array $app) use ($egoActiveWorkspace): bool {

            $routeName =
                (string)($app['route'] ?? '');

            if ($routeName === '__workspace_dashboard__') {
                return
                    request()->routeIs('ego.workspace.dashboard')
                    &&
                    (string)request()->route('workspace')
                    ===
                    (string)$egoActiveWorkspace;
            }

            if ($routeName === '') {
                return false;
            }

            if (request()->routeIs($routeName)) {
                return true;
            }

            /*
             * index -> wildcard cho các page detail/edit.
             *
             * Riêng products.* có nhiều app:
             * Sản phẩm / Nhập / Xuất nên không wildcard.
             */
            if (
                str_ends_with($routeName, '.index')
                && !str_starts_with($routeName, 'products.')
            ) {
                $prefix =
                    substr(
                        $routeName,
                        0,
                        -strlen('.index')
                    );

                return request()->routeIs($prefix.'.*');
            }

            return false;
        };
@endphp


{{-- ======================================================
     WORKSPACE CARD
     ====================================================== --}}

<li class="ego-workspace-static">

    <div class="ego-ws-server-card">

        <div class="ego-ws-server-card__label">
            Không gian làm việc
        </div>

        <div class="ego-ws-server-card__name">

            <i class="bi bi-grid-1x2-fill"></i>

            <span>
                {{ $egoActiveWorkspaceLabel ?: ucfirst($egoActiveWorkspace) }}
            </span>

        </div>

        <div class="ego-ws-server-card__actions">

            <a href="{{
                route(
                    'ego.workspace.department',
                    ['workspace' => $egoActiveWorkspace]
                )
            }}">
                <i class="bi bi-grid-3x3-gap-fill"></i>
                Ứng dụng
            </a>

            <a href="{{ route('ego.workspace.index') }}">
                <i class="bi bi-arrow-left-right"></i>
                Đổi phòng
            </a>

        </div>

    </div>

</li>


{{-- ======================================================
     1. TỔNG QUAN - BẮT BUỘC ĐẦU TIÊN
     ====================================================== --}}

<li class="
        ego-item
        ego-ws-server-item
        ego-ws-server-overview
    "
    data-title="Tổng quan">

    <a href="{{ $egoWsOverview['url'] }}"
       class="
            ego-link
            {{ $egoWsIsActive($egoWsOverview) ? 'active' : '' }}
       "
       data-ego-type="nav">

        <span class="ego-ic">
            <i class="bi {{ $egoWsOverview['icon'] ?? 'bi-speedometer2' }}"></i>
        </span>

        <span class="ego-txt">
            Tổng quan
        </span>

    </a>

</li>


{{-- ======================================================
     2. NGHIỆP VỤ
     ====================================================== --}}

@if($egoWsBusiness->isNotEmpty())

    <li class="ego-ws-server-section">
        Nghiệp vụ
    </li>

    @foreach($egoWsBusiness as $app)

        @php
            $badge = $egoWsBadge($app);
        @endphp

        <li class="ego-item ego-ws-server-item"
            data-title="{{ $app['label'] }}">

            <a href="{{ $app['url'] }}"
               class="
                    ego-link
                    {{ $egoWsIsActive($app) ? 'active' : '' }}
               "
               data-ego-type="nav">

                <span class="ego-ic">
                    <i class="bi {{ $app['icon'] ?? 'bi-grid' }}"></i>
                </span>

                <span class="ego-txt">
                    {{ $app['label'] }}
                </span>

                @if($badge > 0)
                    <span class="ego-ws-server-badge">
                        {{ $badge }}
                    </span>
                @endif

            </a>

        </li>

    @endforeach

@endif


{{-- ======================================================
     3. PHỐI HỢP
     ====================================================== --}}

@if($egoWsCollaboration->isNotEmpty())

    <li class="ego-ws-server-section">
        Phối hợp
    </li>

    @foreach($egoWsCollaboration as $app)

        @php
            $badge = $egoWsBadge($app);
        @endphp

        <li class="ego-item ego-ws-server-item"
            data-title="{{ $app['label'] }}">

            <a href="{{ $app['url'] }}"
               class="
                    ego-link
                    {{ $egoWsIsActive($app) ? 'active' : '' }}
               "
               data-ego-type="nav">

                <span class="ego-ic">
                    <i class="bi {{ $app['icon'] ?? 'bi-grid' }}"></i>
                </span>

                <span class="ego-txt">
                    {{ $app['label'] }}
                </span>

                @if($badge > 0)
                    <span class="ego-ws-server-badge">
                        {{ $badge }}
                    </span>
                @endif

            </a>

        </li>

    @endforeach

@endif


{{-- ======================================================
     4. CÁ NHÂN - LUÔN CUỐI
     ====================================================== --}}

@if($egoWsPersonalApps->isNotEmpty())

    <li class="ego-ws-server-section">
        Cá nhân
    </li>

    @foreach($egoWsPersonalApps as $app)

        <li class="ego-item ego-ws-server-item"
            data-title="{{ $app['label'] }}">

            <a href="{{ $app['url'] }}"
               class="
                    ego-link
                    {{ $egoWsIsActive($app) ? 'active' : '' }}
               "
               data-ego-type="nav">

                <span class="ego-ic">
                    <i class="bi {{ $app['icon'] ?? 'bi-person' }}"></i>
                </span>

                <span class="ego-txt">

                    @if(($app['label'] ?? '') === 'Đơn nghỉ phép')
                        Nghỉ phép

                    @elseif(($app['label'] ?? '') === 'Đăng ký tăng ca')
                        Tăng ca

                    @else
                        {{ $app['label'] }}
                    @endif

                </span>

            </a>

        </li>

    @endforeach

@endif


@endif

{{-- EGO_FINANCE_EXCEL_MENU_SWITCH_END --}}
