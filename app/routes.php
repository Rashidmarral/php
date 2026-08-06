<?php

use App\Core\Auth;
use App\Core\Router;
use App\Controllers\Site\HomeController;
use App\Controllers\Auth\AuthController;
use App\Controllers\User\DashboardController;
use App\Controllers\User\ProjectController;
use App\Controllers\User\ClientController;
use App\Controllers\User\EstimateController;
use App\Controllers\User\InvoiceController;
use App\Controllers\User\ScheduleController;
use App\Controllers\User\TeamController;
use App\Controllers\User\BillingController;
use App\Controllers\User\SettingsController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\CompanyController;
use App\Controllers\Admin\PlanController;
use App\Controllers\Admin\PaymentController;
use App\Controllers\Admin\AdminUserController;
use App\Controllers\Admin\SiteSettingsController;
use App\Controllers\Admin\QuickEstimateAdminController;
use App\Controllers\Site\QuickEstimateController;
use App\Controllers\User\TakeoffController;

/** @var Router $router */

// ---------- Public marketing site ----------
$router->get('/', [HomeController::class, 'index']);
$router->get('/features', [HomeController::class, 'features']);
$router->get('/pricing', [HomeController::class, 'pricing']);
$router->get('/about', [HomeController::class, 'about']);
$router->get('/contact', [HomeController::class, 'contact']);
$router->post('/contact', [HomeController::class, 'contactSubmit']);
$router->get('/privacy', [HomeController::class, 'privacy']);
$router->get('/terms', [HomeController::class, 'terms']);

$router->get('/quick-estimate', [QuickEstimateController::class, 'index']);
$router->post('/quick-estimate', [QuickEstimateController::class, 'store']);
$router->get('/quick-estimate/{id}', [QuickEstimateController::class, 'show']);
$router->get('/quick-estimate/{id}/pdf', [QuickEstimateController::class, 'pdf']);

// ---------- Auth ----------
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

// ---------- User (company) panel ----------
$router->group([fn() => Auth::requireCompanyUser()], function (Router $router) {
    $router->get('/app', [DashboardController::class, 'index']);

    $router->get('/app/projects', [ProjectController::class, 'index']);
    $router->get('/app/projects/create', [ProjectController::class, 'create']);
    $router->post('/app/projects', [ProjectController::class, 'store']);
    $router->get('/app/projects/{id}', [ProjectController::class, 'show']);
    $router->get('/app/projects/{id}/edit', [ProjectController::class, 'edit']);
    $router->post('/app/projects/{id}', [ProjectController::class, 'update']);
    $router->post('/app/projects/{id}/delete', [ProjectController::class, 'destroy']);

    $router->get('/app/clients', [ClientController::class, 'index']);
    $router->get('/app/clients/create', [ClientController::class, 'create']);
    $router->post('/app/clients', [ClientController::class, 'store']);
    $router->get('/app/clients/{id}/edit', [ClientController::class, 'edit']);
    $router->post('/app/clients/{id}', [ClientController::class, 'update']);
    $router->post('/app/clients/{id}/delete', [ClientController::class, 'destroy']);

    $router->get('/app/estimates', [EstimateController::class, 'index']);
    $router->get('/app/estimates/create', [EstimateController::class, 'create']);
    $router->post('/app/estimates', [EstimateController::class, 'store']);
    $router->get('/app/estimates/{id}', [EstimateController::class, 'show']);
    $router->post('/app/estimates/{id}/status', [EstimateController::class, 'updateStatus']);
    $router->post('/app/estimates/{id}/delete', [EstimateController::class, 'destroy']);

    $router->get('/app/invoices', [InvoiceController::class, 'index']);
    $router->get('/app/invoices/create', [InvoiceController::class, 'create']);
    $router->post('/app/invoices', [InvoiceController::class, 'store']);
    $router->get('/app/invoices/{id}', [InvoiceController::class, 'show']);
    $router->post('/app/invoices/{id}/status', [InvoiceController::class, 'updateStatus']);
    $router->post('/app/invoices/{id}/delete', [InvoiceController::class, 'destroy']);

    $router->get('/app/schedule', [ScheduleController::class, 'index']);
    $router->post('/app/schedule', [ScheduleController::class, 'store']);
    $router->post('/app/schedule/{id}/status', [ScheduleController::class, 'updateStatus']);
    $router->post('/app/schedule/{id}/delete', [ScheduleController::class, 'destroy']);

    $router->get('/app/team', [TeamController::class, 'index']);
    $router->post('/app/team', [TeamController::class, 'store']);
    $router->post('/app/team/{id}/delete', [TeamController::class, 'destroy']);

    $router->get('/app/billing', [BillingController::class, 'index']);
    $router->post('/app/billing/upgrade', [BillingController::class, 'upgrade']);

    $router->get('/app/settings', [SettingsController::class, 'index']);
    $router->post('/app/settings', [SettingsController::class, 'update']);

    $router->get('/app/estimates/{id}/pdf', [EstimateController::class, 'pdf']);
    $router->get('/app/invoices/{id}/pdf', [InvoiceController::class, 'pdf']);

    $router->get('/app/takeoffs', [TakeoffController::class, 'index']);
    $router->get('/app/takeoffs/create', [TakeoffController::class, 'create']);
    $router->post('/app/takeoffs', [TakeoffController::class, 'store']);
    $router->get('/app/takeoffs/{id}', [TakeoffController::class, 'show']);
    $router->post('/app/takeoffs/{id}/measurements', [TakeoffController::class, 'addMeasurement']);
    $router->post('/app/takeoffs/{id}/measurements/{measurementId}/delete', [TakeoffController::class, 'deleteMeasurement']);
    $router->post('/app/takeoffs/{id}/calibrate', [TakeoffController::class, 'calibrate']);
    $router->post('/app/takeoffs/{id}/convert', [TakeoffController::class, 'convertToEstimate']);
    $router->post('/app/takeoffs/{id}/delete', [TakeoffController::class, 'destroy']);
});

// ---------- Platform admin panel ----------
$router->group([fn() => Auth::requireSuperAdmin()], function (Router $router) {
    $router->get('/admin', [AdminDashboardController::class, 'index']);

    $router->get('/admin/companies', [CompanyController::class, 'index']);
    $router->get('/admin/companies/{id}', [CompanyController::class, 'show']);
    $router->post('/admin/companies/{id}/status', [CompanyController::class, 'updateStatus']);

    $router->get('/admin/plans', [PlanController::class, 'index']);
    $router->get('/admin/plans/create', [PlanController::class, 'create']);
    $router->post('/admin/plans', [PlanController::class, 'store']);
    $router->get('/admin/plans/{id}/edit', [PlanController::class, 'edit']);
    $router->post('/admin/plans/{id}', [PlanController::class, 'update']);
    $router->post('/admin/plans/{id}/delete', [PlanController::class, 'destroy']);

    $router->get('/admin/payments', [PaymentController::class, 'index']);

    $router->get('/admin/admins', [AdminUserController::class, 'index']);
    $router->post('/admin/admins', [AdminUserController::class, 'store']);
    $router->post('/admin/admins/{id}/delete', [AdminUserController::class, 'destroy']);

    $router->get('/admin/settings', [SiteSettingsController::class, 'index']);
    $router->post('/admin/settings', [SiteSettingsController::class, 'update']);

    $router->get('/admin/quick-estimate', [QuickEstimateAdminController::class, 'index']);

    $router->get('/admin/quick-estimate/regions', [QuickEstimateAdminController::class, 'regions']);
    $router->post('/admin/quick-estimate/regions', [QuickEstimateAdminController::class, 'storeRegion']);
    $router->post('/admin/quick-estimate/regions/{id}', [QuickEstimateAdminController::class, 'updateRegion']);
    $router->post('/admin/quick-estimate/regions/{id}/delete', [QuickEstimateAdminController::class, 'destroyRegion']);

    $router->get('/admin/quick-estimate/foundations', [QuickEstimateAdminController::class, 'foundations']);
    $router->post('/admin/quick-estimate/foundations', [QuickEstimateAdminController::class, 'storeFoundation']);
    $router->post('/admin/quick-estimate/foundations/{id}', [QuickEstimateAdminController::class, 'updateFoundation']);
    $router->post('/admin/quick-estimate/foundations/{id}/delete', [QuickEstimateAdminController::class, 'destroyFoundation']);

    $router->get('/admin/quick-estimate/addons', [QuickEstimateAdminController::class, 'addons']);
    $router->post('/admin/quick-estimate/addons', [QuickEstimateAdminController::class, 'storeAddon']);
    $router->post('/admin/quick-estimate/addons/{id}', [QuickEstimateAdminController::class, 'updateAddon']);
    $router->post('/admin/quick-estimate/addons/{id}/delete', [QuickEstimateAdminController::class, 'destroyAddon']);

    $router->get('/admin/quick-estimate/leads', [QuickEstimateAdminController::class, 'leads']);
    $router->post('/admin/quick-estimate/leads/{id}/status', [QuickEstimateAdminController::class, 'updateLeadStatus']);
});
