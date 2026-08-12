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
use App\Http\Controllers\App\BillingController;
use App\Http\Controllers\App\BusinessSetupController;
use App\Http\Controllers\App\ChangeOrderController;
use App\Http\Controllers\App\VendorBillController;
use App\Http\Controllers\App\ClientController;
use App\Http\Controllers\App\ComplianceController;
use App\Http\Controllers\App\ConsultationController;
use App\Http\Controllers\App\DocumentController;
use App\Http\Controllers\App\ImpersonationController;
use App\Http\Controllers\App\IntegrationController;
use App\Http\Controllers\App\LeadController;
use App\Http\Controllers\App\MaterialController;
use App\Http\Controllers\App\QuickEstimateController;
use App\Http\Controllers\App\ReportController;
use App\Http\Controllers\App\TakeoffController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\EstimateController;
use App\Http\Controllers\App\InvoiceController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\ProjectPhotoController;
use App\Http\Controllers\App\ScheduleController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\SupplierController;
use App\Http\Controllers\App\TeamController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\QuickEstimateController as SiteQuickEstimateController;
use App\Http\Controllers\Site\ShareController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
| The marketing site (home, pricing, quick-estimate calculator, CMS pages)
| and the token-based client-facing share links (estimate signing, invoice
| viewing/payment) — none of this needs a login.
*/
Route::get('/', [HomeController::class, 'index']);
Route::get('/features', [HomeController::class, 'features']);
Route::get('/pricing', [HomeController::class, 'pricing']);
Route::get('/about', [HomeController::class, 'about']);
Route::get('/contact', [HomeController::class, 'contact']);
Route::post('/contact', [HomeController::class, 'contactSubmit']);
Route::get('/privacy', [HomeController::class, 'privacy']);
Route::get('/terms', [HomeController::class, 'terms']);
Route::get('/p/{slug}', [PageController::class, 'show']);

Route::get('/quick-estimate', [SiteQuickEstimateController::class, 'index']);
Route::post('/quick-estimate', [SiteQuickEstimateController::class, 'store']);
Route::get('/quick-estimate/{id}', [SiteQuickEstimateController::class, 'show']);
Route::get('/quick-estimate/{id}/pdf', [SiteQuickEstimateController::class, 'pdf']);

Route::get('/e/{token}', [ShareController::class, 'estimate']);
Route::post('/e/{token}/sign', [ShareController::class, 'signEstimate']);
Route::get('/e/{token}/pdf', [ShareController::class, 'estimatePdf']);
Route::get('/i/{token}', [ShareController::class, 'invoice']);
Route::get('/i/{token}/pdf', [ShareController::class, 'invoicePdf']);
Route::get('/i/{token}/pay', [ShareController::class, 'payInvoice']);
Route::get('/i/{token}/pay/callback', [ShareController::class, 'invoicePaymentCallback']);

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
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword']);
Route::post('/forgot-password', [AuthController::class, 'sendResetLink']);
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword']);
Route::post('/reset-password/{token}', [AuthController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| Company (/app) panel — owner/admin/estimator/accountant/viewer roles
|--------------------------------------------------------------------------
*/
Route::prefix('app')->middleware('company.user')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/create', [ProjectController::class, 'create']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::get('/projects/{id}/edit', [ProjectController::class, 'edit']);
    Route::post('/projects/{id}', [ProjectController::class, 'update']);
    Route::post('/projects/{id}/delete', [ProjectController::class, 'destroy']);
    Route::post('/projects/{id}/change-orders', [ChangeOrderController::class, 'store']);
    Route::post('/change-orders/{id}/status', [ChangeOrderController::class, 'updateStatus']);
    Route::post('/change-orders/{id}/delete', [ChangeOrderController::class, 'destroy']);
    Route::post('/projects/{id}/vendor-bills', [VendorBillController::class, 'store']);
    Route::post('/vendor-bills/{id}/delete', [VendorBillController::class, 'destroy']);
    Route::post('/projects/{id}/photos', [ProjectPhotoController::class, 'store']);
    Route::post('/project-photos/{id}/delete', [ProjectPhotoController::class, 'destroy']);

    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/clients/create', [ClientController::class, 'create']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{id}/edit', [ClientController::class, 'edit']);
    Route::post('/clients/{id}', [ClientController::class, 'update']);
    Route::post('/clients/{id}/delete', [ClientController::class, 'destroy']);
    Route::post('/clients/{id}/enable-portal', [ClientController::class, 'enablePortal']);
    Route::post('/clients/{id}/disable-portal', [ClientController::class, 'disablePortal']);

    Route::get('/estimates', [EstimateController::class, 'index']);
    Route::get('/estimates/new', [EstimateController::class, 'newChoice']);
    Route::get('/estimates/create', [EstimateController::class, 'create']);
    Route::post('/estimates', [EstimateController::class, 'store']);
    Route::get('/estimates/templates/{id}', [EstimateController::class, 'templatePreview']);
    Route::post('/estimates/templates/{id}', [EstimateController::class, 'storeFromTemplate']);
    Route::get('/estimates/ai', [EstimateController::class, 'aiGenerator']);
    Route::post('/estimates/ai/generate', [EstimateController::class, 'aiGenerate']);
    Route::get('/estimates/{id}/pdf', [EstimateController::class, 'pdf']);
    Route::get('/estimates/{id}', [EstimateController::class, 'show']);
    Route::post('/estimates/{id}/status', [EstimateController::class, 'updateStatus']);
    Route::post('/estimates/{id}/totals', [EstimateController::class, 'updateTotals']);
    Route::post('/estimates/{id}/delete', [EstimateController::class, 'destroy']);

    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/create', [InvoiceController::class, 'create']);
    Route::post('/invoices', [InvoiceController::class, 'store']);
    Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'pdf']);
    Route::get('/invoices/{id}/xml', [InvoiceController::class, 'xml']);
    Route::get('/invoices/{id}', [InvoiceController::class, 'show']);
    Route::post('/invoices/{id}/status', [InvoiceController::class, 'updateStatus']);
    Route::post('/invoices/{id}/release-retention', [InvoiceController::class, 'releaseRetention']);
    Route::post('/invoices/{id}/delete', [InvoiceController::class, 'destroy']);
    Route::post('/invoices/{id}/submit-zatca', [InvoiceController::class, 'submitZatca']);
    Route::post('/invoices/{id}/send-whatsapp', [InvoiceController::class, 'sendWhatsApp']);

    Route::get('/schedule', [ScheduleController::class, 'index']);
    Route::post('/schedule', [ScheduleController::class, 'store']);
    Route::post('/schedule/{id}/status', [ScheduleController::class, 'updateStatus']);
    Route::post('/schedule/{id}/delete', [ScheduleController::class, 'destroy']);

    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team', [TeamController::class, 'store']);
    Route::post('/team/{id}/role', [TeamController::class, 'updateRole']);
    Route::post('/team/{id}/delete', [TeamController::class, 'destroy']);

    Route::get('/billing', [BillingController::class, 'index']);
    Route::post('/billing/upgrade', [BillingController::class, 'upgrade']);
    Route::get('/billing/checkout', [BillingController::class, 'checkout']);
    Route::post('/billing/bank-transfer', [BillingController::class, 'requestBankTransfer']);
    Route::get('/billing/moyasar-callback', [BillingController::class, 'moyasarCallback']);

    Route::get('/settings', [SettingsController::class, 'index']);
    Route::post('/settings', [SettingsController::class, 'update']);
    Route::post('/settings/password', [SettingsController::class, 'updatePassword']);

    Route::get('/business-setup', [BusinessSetupController::class, 'index']);
    Route::get('/business-setup/units-of-measure', [BusinessSetupController::class, 'units']);
    Route::post('/business-setup/units-of-measure', [BusinessSetupController::class, 'storeUnit']);
    Route::post('/business-setup/units-of-measure/load-defaults', [BusinessSetupController::class, 'loadDefaultUnits']);
    Route::post('/business-setup/units-of-measure/{id}', [BusinessSetupController::class, 'updateUnit']);
    Route::post('/business-setup/units-of-measure/{id}/delete', [BusinessSetupController::class, 'destroyUnit']);
    Route::get('/business-setup/tax-rates', [BusinessSetupController::class, 'taxRates']);
    Route::post('/business-setup/tax-rates', [BusinessSetupController::class, 'storeTaxRate']);
    Route::post('/business-setup/tax-rates/{id}', [BusinessSetupController::class, 'updateTaxRate']);
    Route::post('/business-setup/tax-rates/{id}/delete', [BusinessSetupController::class, 'destroyTaxRate']);
    Route::get('/business-setup/compliance', [ComplianceController::class, 'index']);
    Route::post('/business-setup/compliance', [ComplianceController::class, 'store']);
    Route::post('/business-setup/compliance/{id}', [ComplianceController::class, 'update']);
    Route::post('/business-setup/compliance/{id}/delete', [ComplianceController::class, 'destroy']);
    Route::get('/business-setup/{type}', [BusinessSetupController::class, 'simple']);
    Route::post('/business-setup/{type}', [BusinessSetupController::class, 'storeSimple']);
    Route::post('/business-setup/{type}/load-defaults', [BusinessSetupController::class, 'loadDefaultsSimple']);
    Route::post('/business-setup/{type}/{id}', [BusinessSetupController::class, 'updateSimple']);
    Route::post('/business-setup/{type}/{id}/delete', [BusinessSetupController::class, 'destroySimple']);

    Route::get('/leads', [LeadController::class, 'index']);
    Route::get('/leads/create', [LeadController::class, 'create']);
    Route::post('/leads', [LeadController::class, 'store']);
    Route::get('/leads/{id}/edit', [LeadController::class, 'edit']);
    Route::post('/leads/{id}', [LeadController::class, 'update']);
    Route::post('/leads/{id}/status', [LeadController::class, 'updateStatus']);
    Route::post('/leads/{id}/convert', [LeadController::class, 'convertToClient']);
    Route::post('/leads/{id}/delete', [LeadController::class, 'destroy']);

    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::get('/suppliers/create', [SupplierController::class, 'create']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{id}/edit', [SupplierController::class, 'edit']);
    Route::post('/suppliers/{id}', [SupplierController::class, 'update']);
    Route::post('/suppliers/{id}/delete', [SupplierController::class, 'destroy']);

    Route::get('/materials', [MaterialController::class, 'index']);
    Route::get('/materials/create', [MaterialController::class, 'create']);
    Route::post('/materials', [MaterialController::class, 'store']);
    Route::post('/materials/import', [MaterialController::class, 'importCsv']);
    Route::post('/materials/sync-sheet', [MaterialController::class, 'syncFromSheet']);
    Route::get('/materials/{id}/edit', [MaterialController::class, 'edit']);
    Route::post('/materials/{id}', [MaterialController::class, 'update']);
    Route::post('/materials/{id}/delete', [MaterialController::class, 'destroy']);

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::post('/documents/{id}/delete', [DocumentController::class, 'destroy']);

    Route::get('/integrations', [IntegrationController::class, 'index']);
    Route::post('/integrations/google-sheets', [IntegrationController::class, 'updateGoogleSheets']);
    Route::post('/integrations/client-payments', [IntegrationController::class, 'updateClientPayments']);
    Route::post('/integrations/webhooks', [IntegrationController::class, 'storeWebhook']);
    Route::post('/integrations/webhooks/{id}/toggle', [IntegrationController::class, 'toggleWebhook']);
    Route::post('/integrations/webhooks/{id}/delete', [IntegrationController::class, 'destroyWebhook']);
    Route::post('/integrations/api-tokens', [IntegrationController::class, 'createApiToken']);
    Route::post('/integrations/api-tokens/{id}/delete', [IntegrationController::class, 'revokeApiToken']);

    Route::get('/consultations', [ConsultationController::class, 'index']);
    Route::post('/consultations', [ConsultationController::class, 'store']);
    Route::post('/consultations/{id}/cancel', [ConsultationController::class, 'cancel']);

    Route::post('/end-impersonation', [ImpersonationController::class, 'stop']);

    Route::get('/reports', [ReportController::class, 'overview']);
    Route::get('/reports/profit', [ReportController::class, 'profit']);
    Route::get('/reports/tax', [ReportController::class, 'tax']);
    Route::get('/reports/retention', [ReportController::class, 'retention']);

    Route::get('/quick-estimate', [QuickEstimateController::class, 'index']);
    Route::post('/quick-estimate', [QuickEstimateController::class, 'store']);
    Route::get('/quick-estimate/{id}/pdf', [QuickEstimateController::class, 'pdf']);
    Route::get('/quick-estimate/{id}', [QuickEstimateController::class, 'show']);
    Route::post('/quick-estimate/{id}/convert', [QuickEstimateController::class, 'convertToEstimate']);
    Route::post('/quick-estimate/{id}/delete', [QuickEstimateController::class, 'destroy']);

    Route::get('/takeoffs', [TakeoffController::class, 'index']);
    Route::get('/takeoffs/create', [TakeoffController::class, 'create']);
    Route::post('/takeoffs', [TakeoffController::class, 'store']);
    Route::get('/takeoffs/{id}', [TakeoffController::class, 'show']);
    Route::post('/takeoffs/{id}/measurements', [TakeoffController::class, 'addMeasurement']);
    Route::post('/takeoffs/{id}/measurements/{measurementId}/delete', [TakeoffController::class, 'deleteMeasurement']);
    Route::post('/takeoffs/{id}/calibrate', [TakeoffController::class, 'calibrate']);
    Route::post('/takeoffs/{id}/convert', [TakeoffController::class, 'convertToEstimate']);
    Route::post('/takeoffs/{id}/delete', [TakeoffController::class, 'destroy']);
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
    Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('portal.login');
    Route::post('/login', [PortalAuthController::class, 'login']);
    Route::post('/logout', [PortalAuthController::class, 'logout']);

    Route::middleware('portal.client')->group(function () {
        Route::get('/', [PortalController::class, 'dashboard']);
        Route::get('/projects/{id}', [PortalController::class, 'project']);
        Route::get('/estimates/{id}', [PortalController::class, 'estimate']);
        Route::get('/invoices/{id}', [PortalController::class, 'invoice']);
    });
});
