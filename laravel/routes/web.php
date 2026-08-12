<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIntegrationController;
use App\Http\Controllers\Admin\AdminPageController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminTranslationController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CompanyZatcaController;
use App\Http\Controllers\Admin\ConsultationAdminController;
use App\Http\Controllers\Admin\EstimateTemplateAdminController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\QuickEstimateAdminController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Controllers\Admin\UsageController;
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
    Route::get('/reports', [AdminReportController::class, 'index']);
    Route::get('/usage', [UsageController::class, 'index']);
    Route::get('/audit-log', [AuditLogController::class, 'index']);

    Route::get('/plans', [PlanController::class, 'index']);
    Route::get('/plans/create', [PlanController::class, 'create']);
    Route::get('/plans/{id}/edit', [PlanController::class, 'edit']);

    Route::get('/profile', [AdminProfileController::class, 'index']);
    Route::post('/profile', [AdminProfileController::class, 'update']);

    Route::get('/admins', [AdminUserController::class, 'index']);

    Route::get('/settings', [SiteSettingsController::class, 'index']);
    Route::get('/settings/payments', [SiteSettingsController::class, 'payments']);
    Route::get('/settings/legal', [SiteSettingsController::class, 'legal']);
    Route::get('/settings/notifications', [SiteSettingsController::class, 'notifications']);
    Route::get('/settings/email', [SiteSettingsController::class, 'email']);
    Route::get('/settings/header', [SiteSettingsController::class, 'header']);
    Route::get('/settings/ai', [SiteSettingsController::class, 'ai']);
    Route::get('/integrations', [AdminIntegrationController::class, 'index']);

    Route::get('/pages', [AdminPageController::class, 'index']);
    Route::get('/pages/create', [AdminPageController::class, 'create']);
    Route::get('/pages/{id}/edit', [AdminPageController::class, 'edit']);

    Route::get('/translations', [AdminTranslationController::class, 'index']);

    Route::get('/quick-estimate', [QuickEstimateAdminController::class, 'index']);
    Route::get('/quick-estimate/regions', [QuickEstimateAdminController::class, 'regions']);
    Route::get('/quick-estimate/foundations', [QuickEstimateAdminController::class, 'foundations']);
    Route::get('/quick-estimate/addons', [QuickEstimateAdminController::class, 'addons']);
    Route::get('/quick-estimate/leads', [QuickEstimateAdminController::class, 'leads']);

    Route::get('/estimate-templates', [EstimateTemplateAdminController::class, 'index']);
    Route::get('/estimate-templates/{id}/items', [EstimateTemplateAdminController::class, 'items']);

    Route::get('/consultations', [ConsultationAdminController::class, 'index']);

    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/export.csv', [CompanyController::class, 'exportCsv']);
    Route::get('/companies/{id}', [CompanyController::class, 'show']);
    Route::get('/companies/{id}/zatca', [CompanyZatcaController::class, 'show']);

    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/export.csv', [PaymentController::class, 'exportCsv']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);

    Route::middleware('admin.super')->group(function () {
        Route::post('/companies/{id}/status', [CompanyController::class, 'updateStatus']);
        Route::post('/companies/{id}/plan', [CompanyController::class, 'updatePlan']);
        Route::post('/companies/{id}/profile', [CompanyController::class, 'updateProfile']);
        Route::post('/companies/{id}/impersonate', [CompanyController::class, 'impersonate']);
        Route::post('/companies/{id}/hard-delete', [CompanyController::class, 'hardDelete']);

        Route::post('/companies/{id}/zatca/environment', [CompanyZatcaController::class, 'updateEnvironment']);
        Route::post('/companies/{id}/zatca/csr', [CompanyZatcaController::class, 'generateCsr']);
        Route::post('/companies/{id}/zatca/compliance-csid', [CompanyZatcaController::class, 'requestComplianceCsid']);
        Route::post('/companies/{id}/zatca/production-csid', [CompanyZatcaController::class, 'requestProductionCsid']);

        Route::post('/payments/{id}/update', [PaymentController::class, 'update']);
        Route::post('/payments/{id}/apply-plan', [PaymentController::class, 'applyPlan']);
        Route::post('/payments/{id}/approve', [PaymentController::class, 'approve']);
        Route::post('/payments/{id}/reject', [PaymentController::class, 'reject']);

        Route::post('/quick-estimate/regions', [QuickEstimateAdminController::class, 'storeRegion']);
        Route::post('/quick-estimate/regions/{id}', [QuickEstimateAdminController::class, 'updateRegion']);
        Route::post('/quick-estimate/regions/{id}/delete', [QuickEstimateAdminController::class, 'destroyRegion']);
        Route::post('/quick-estimate/foundations', [QuickEstimateAdminController::class, 'storeFoundation']);
        Route::post('/quick-estimate/foundations/{id}', [QuickEstimateAdminController::class, 'updateFoundation']);
        Route::post('/quick-estimate/foundations/{id}/delete', [QuickEstimateAdminController::class, 'destroyFoundation']);
        Route::post('/quick-estimate/addons', [QuickEstimateAdminController::class, 'storeAddon']);
        Route::post('/quick-estimate/addons/{id}', [QuickEstimateAdminController::class, 'updateAddon']);
        Route::post('/quick-estimate/addons/{id}/delete', [QuickEstimateAdminController::class, 'destroyAddon']);
        Route::post('/quick-estimate/leads/{id}/status', [QuickEstimateAdminController::class, 'updateLeadStatus']);

        Route::post('/estimate-templates', [EstimateTemplateAdminController::class, 'store']);
        Route::post('/estimate-templates/{id}', [EstimateTemplateAdminController::class, 'update']);
        Route::post('/estimate-templates/{id}/delete', [EstimateTemplateAdminController::class, 'destroy']);
        Route::post('/estimate-templates/{id}/default', [EstimateTemplateAdminController::class, 'setDefault']);
        Route::post('/estimate-templates/{id}/items', [EstimateTemplateAdminController::class, 'storeItem']);
        Route::post('/estimate-templates/{id}/items/{itemId}', [EstimateTemplateAdminController::class, 'updateItem']);
        Route::post('/estimate-templates/{id}/items/{itemId}/delete', [EstimateTemplateAdminController::class, 'destroyItem']);

        Route::post('/consultations/{id}/update', [ConsultationAdminController::class, 'update']);

        Route::post('/pages', [AdminPageController::class, 'store']);
        Route::post('/pages/{id}', [AdminPageController::class, 'update']);
        Route::post('/pages/{id}/delete', [AdminPageController::class, 'destroy']);

        Route::post('/translations/update', [AdminTranslationController::class, 'update']);
        Route::post('/translations/store', [AdminTranslationController::class, 'store']);
        Route::post('/translations/reset', [AdminTranslationController::class, 'reset']);

        Route::post('/plans', [PlanController::class, 'store']);
        Route::post('/plans/{id}', [PlanController::class, 'update']);
        Route::post('/plans/{id}/delete', [PlanController::class, 'destroy']);

        Route::post('/admins', [AdminUserController::class, 'store']);
        Route::post('/admins/{id}/delete', [AdminUserController::class, 'destroy']);

        Route::post('/settings', [SiteSettingsController::class, 'update']);
        Route::post('/settings/payments', [SiteSettingsController::class, 'updatePayments']);
        Route::post('/settings/legal', [SiteSettingsController::class, 'updateLegal']);
        Route::post('/settings/notifications', [SiteSettingsController::class, 'updateNotifications']);
        Route::post('/settings/email', [SiteSettingsController::class, 'updateEmail']);
        Route::post('/settings/header', [SiteSettingsController::class, 'updateHeader']);
        Route::post('/settings/ai', [SiteSettingsController::class, 'updateAi']);
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
