<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
| The full marketing site (home, pricing, quick-estimate calculator, CMS
| pages, etc.) is ported in a later phase. For now the root redirects
| straight to login, matching an unauthenticated visitor's next step.
*/
Route::get('/', fn () => redirect('/login'));

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Company (/app) panel — owner/admin/estimator/accountant/viewer roles
|--------------------------------------------------------------------------
*/
Route::prefix('app')->middleware('company.user')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);

    // Projects, clients, estimates, invoices, billing, business setup, schedule, team,
    // leads, quick estimate, reports, takeoffs, consultations, integrations, materials,
    // suppliers, documents, settings — ported module-by-module in the next phase.
});

/*
|--------------------------------------------------------------------------
| Admin (/admin) panel — super_admin (full) / support_admin (read-only)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware('admin.panel')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index']);

    // Companies, plans, payments, reports, settings, translations, quick-estimate data,
    // estimate templates, pages, audit log, usage, consultations — ported next phase.

    Route::middleware('admin.super')->group(function () {
        // Mutating/destructive admin-only actions (support_admin is read-only) land here,
        // mirroring the original app's nested super-admin-only route group.
    });
});

/*
|--------------------------------------------------------------------------
| Client portal (/portal) — separate `clients` auth guard
|--------------------------------------------------------------------------
*/
Route::prefix('portal')->group(function () {
    Route::get('/login', fn () => view('portal.login'))->name('portal.login');

    Route::middleware('portal.client')->group(function () {
        // Client-facing read-only views of their own projects/estimates/invoices — next phase.
    });
});
