<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\DepartmentDashboardService;
use App\Services\ExecutiveDashboardService;
use App\Services\Workspace\DepartmentWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class WorkspaceController extends Controller
{
    public function __construct(
        private readonly DepartmentWorkspaceService $workspaces,
        private readonly DepartmentDashboardService $departmentDashboard,
        private readonly ExecutiveDashboardService $executiveDashboard,
    ) {}

    public function root(): RedirectResponse
    {
        return redirect()->route('ego.workspace.index');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing(['department', 'position', 'roles']);

        $request->session()->forget(['active_workspace', 'active_workspace_label']);

        return view('workspace.index', [
            'departments' => $this->workspaces->cards($user),
            'primaryWorkspace' => $this->workspaces->primary($user),
            'isExecutive' => $this->workspaces->isExecutive($user),
        ]);
    }

    public function department(Request $request, string $workspace): View|RedirectResponse
    {
        $workspace = $this->workspaces->canonical($workspace);
        $definitions = $this->workspaces->definitions();

        if (! isset($definitions[$workspace])) {
            abort(404);
        }

        $user = $request->user();
        $user->loadMissing(['department', 'position', 'roles']);

        // Cùng một luật với DepartmentWorkspaceService::canAccess(), nhưng đi qua
        // Gate nên lớp kiểm quyền nhìn thấy được từ ngoài. Vẫn chuyển hướng kèm
        // thông báo thay vì 403 — giữ nguyên trải nghiệm đang có.
        if (! $user->can('access-workspace', $workspace)) {
            return redirect()
                ->route('ego.workspace.index')
                ->with('workspace_error', sprintf(
                    'Bạn không thuộc phòng ban %s để truy cập. Vui lòng chọn phòng ban được cấp quyền.',
                    $definitions[$workspace]['label']
                ));
        }

        $this->activate($request, $workspace, $definitions[$workspace]['label']);

        return view('workspace.department', [
            'workspaceKey' => $workspace,
            'workspace' => $definitions[$workspace] + ['key' => $workspace],
            'apps' => $this->workspaces->apps($user, $workspace),
        ]);
    }

    public function dashboard(Request $request, string $workspace): View|RedirectResponse
    {
        $workspace = $this->workspaces->canonical($workspace);
        $definitions = $this->workspaces->definitions();

        if (! isset($definitions[$workspace])) {
            abort(404);
        }

        $user = $request->user();
        $user->loadMissing(['department', 'position', 'roles']);

        if (! $user->can('access-workspace', $workspace)) {
            return redirect()
                ->route('ego.workspace.index')
                ->with('workspace_error', 'Bạn không thuộc phòng ban này để truy cập.');
        }

        $this->activate($request, $workspace, $definitions[$workspace]['label']);

        if ($workspace === 'executive') {
            $filters = $request->only(['period', 'from', 'to', 'from_date', 'to_date', 'sales_id', 'source']);

            return view('dashboard.index', $this->executiveDashboard->build($user, $filters));
        }

        $resolved = $this->departmentDashboard->resolveWorkspace($user);
        $resolvedCanonical = $this->workspaces->canonical($resolved);

        if ($resolvedCanonical === $workspace) {
            $buildWorkspace = $resolved;
        } elseif ($this->workspaces->isExecutive($user)) {
            $buildWorkspace = match ($workspace) {
                'sales' => 'sales_manager',
                'marketing' => 'marketing_manager',
                'technical' => 'technical_manager',
                default => $workspace,
            };
        } else {
            $buildWorkspace = $workspace;
        }

        return view('dashboard.role-home', [
            'dashboard' => $this->departmentDashboard->build($user, $request, $buildWorkspace),
        ]);
    }

    private function activate(Request $request, string $workspace, string $label): void
    {
        $request->session()->put('active_workspace', $workspace);
        $request->session()->put('active_workspace_label', $label);
    }
}
