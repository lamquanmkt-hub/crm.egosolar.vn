<?php

use App\Http\Controllers\System\WorkspaceController;
use Illuminate\Support\Facades\Route;

/* EGO_DEPARTMENT_WORKSPACE_V1_ROUTES */
Route::middleware(['auth'])->group(function () {
    Route::get('/workspace', [WorkspaceController::class, 'index'])
        ->name('ego.workspace.index');

    Route::get('/workspace/{workspace}/dashboard', [WorkspaceController::class, 'dashboard'])
        ->where('workspace', 'executive|sales|technical|finance|warehouse|hr|marketing')
        ->name('ego.workspace.dashboard');

    Route::get('/workspace/{workspace}', [WorkspaceController::class, 'department'])
        ->where('workspace', 'executive|sales|technical|finance|warehouse|hr|marketing')
        ->name('ego.workspace.department');

    // Được require cuối routes/web.php để / luôn về Workspace,
    // nhưng vẫn giữ route('dashboard') cũ sinh URL "/" bình thường.
    Route::get('/', [WorkspaceController::class, 'root'])
        ->name('ego.workspace.root');

    /*
     * EGO_WORKSPACE_LEGACY_DASHBOARD_COMPAT
     *
     * Các view/module CRM cũ vẫn dùng route('dashboard').
     * Giữ route name cũ nhưng điều hướng về Workspace.
     */
    Route::get('/dashboard', [WorkspaceController::class, 'root'])
        ->name('dashboard');
});
