<?php

use App\Core\Auth;
use App\Core\Router;
use App\Controllers\Site\HomeController;
use App\Controllers\Auth\AuthController;
use App\Controllers\User\DashboardController;
use App\Controllers\User\ProjectController;
use App\Controllers\User\ChangeOrderController;
use App\Controllers\User\ProjectPhotoController;
use App\Controllers\User\ClientController;
use App\Controllers\User\EstimateController;
use App\Controllers\User\InvoiceController;
use App\Controllers\User\ScheduleController;
use App\Controllers\User\TeamController;
use App\Controllers\User\BillingController;
use App\Controllers\User\SettingsController;
use App\Controllers\User\BusinessSetupController;
use App\Controllers\User\ComplianceController;
use App\Controllers\User\LeadController;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\CompanyController;
use App\Controllers\Admin\CompanyZatcaController;
use App\Controllers\Admin\PlanController;
use App\Controllers\Admin\PaymentController;
use App\Controllers\Admin\AdminUserController;
use App\Controllers\Admin\AdminProfileController;
use App\Controllers\Admin\AdminReportController;
use App\Controllers\Admin\AdminIntegrationController;
use App\Controllers\Admin\SiteSettingsController;
use App\Controllers\Admin\AdminPageController;
use App\Controllers\Admin\AdminTranslationController;
use App\Controllers\Admin\QuickEstimateAdminController;
use App\Controllers\Admin\EstimateTemplateAdminController;
use App\Controllers\Admin\UsageController;
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\ConsultationAdminController;
use App\Controllers\User\ConsultationController;
use App\Controllers\User\ImpersonationController;
use App\Controllers\Site\QuickEstimateController;
use App\Controllers\Site\PageController;
use App\Controllers\Site\ShareController;
use App\Controllers\User\QuickEstimateController as UserQuickEstimateController;
use App\Controllers\User\TakeoffController;
use App\Controllers\User\SupplierController;
use App\Controllers\User\MaterialController;
use App\Controllers\User\DocumentController;
use App\Controllers\User\ReportController;
use App\Controllers\User\IntegrationController;
use App\Controllers\Portal\PortalController;
use App\Core\PortalAuth;

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
$router->get('/p/{slug}', [PageController::class, 'show']);

$router->get('/quick-estimate', [QuickEstimateController::class, 'index']);
$router->post('/quick-estimate', [QuickEstimateController::class, 'store']);
$router->get('/quick-estimate/{id}', [QuickEstimateController::class, 'show']);
$router->get('/quick-estimate/{id}/pdf', [QuickEstimateController::class, 'pdf']);

// ---------- Public share links (client-facing, token-based, no login) ----------
$router->get('/e/{token}', [ShareController::class, 'estimate']);
$router->post('/e/{token}/sign', [ShareController::class, 'signEstimate']);
$router->get('/e/{token}/pdf', [ShareController::class, 'estimatePdf']);
$router->get('/i/{token}', [ShareController::class, 'invoice']);
$router->get('/i/{token}/pdf', [ShareController::class, 'invoicePdf']);
$router->get('/i/{token}/pay', [ShareController::class, 'payInvoice']);
$router->get('/i/{token}/pay/callback', [ShareController::class, 'invoicePaymentCallback']);

// ---------- Auth ----------
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
$router->post('/forgot-password', [AuthController::class, 'sendResetLink']);
$router->get('/reset-password/{token}', [AuthController::class, 'showResetPassword']);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPassword']);

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
    $router->post('/app/projects/{id}/change-orders', [ChangeOrderController::class, 'store']);
    $router->post('/app/change-orders/{id}/status', [ChangeOrderController::class, 'updateStatus']);
    $router->post('/app/change-orders/{id}/delete', [ChangeOrderController::class, 'destroy']);
    $router->post('/app/projects/{id}/photos', [ProjectPhotoController::class, 'store']);
    $router->post('/app/project-photos/{id}/delete', [ProjectPhotoController::class, 'destroy']);

    $router->get('/app/clients', [ClientController::class, 'index']);
    $router->get('/app/clients/create', [ClientController::class, 'create']);
    $router->post('/app/clients', [ClientController::class, 'store']);
    $router->get('/app/clients/{id}/edit', [ClientController::class, 'edit']);
    $router->post('/app/clients/{id}', [ClientController::class, 'update']);
    $router->post('/app/clients/{id}/delete', [ClientController::class, 'destroy']);
    $router->post('/app/clients/{id}/enable-portal', [ClientController::class, 'enablePortal']);
    $router->post('/app/clients/{id}/disable-portal', [ClientController::class, 'disablePortal']);

    $router->get('/app/quick-estimate', [UserQuickEstimateController::class, 'index']);
    $router->post('/app/quick-estimate', [UserQuickEstimateController::class, 'store']);
    $router->get('/app/quick-estimate/{id}', [UserQuickEstimateController::class, 'show']);
    $router->get('/app/quick-estimate/{id}/pdf', [UserQuickEstimateController::class, 'pdf']);
    $router->post('/app/quick-estimate/{id}/convert', [UserQuickEstimateController::class, 'convertToEstimate']);
    $router->post('/app/quick-estimate/{id}/delete', [UserQuickEstimateController::class, 'destroy']);

    $router->get('/app/estimates', [EstimateController::class, 'index']);
    $router->get('/app/estimates/new', [EstimateController::class, 'newChoice']);
    $router->get('/app/estimates/create', [EstimateController::class, 'create']);
    $router->post('/app/estimates', [EstimateController::class, 'store']);
    $router->get('/app/estimates/templates/{id}', [EstimateController::class, 'templatePreview']);
    $router->post('/app/estimates/templates/{id}', [EstimateController::class, 'storeFromTemplate']);
    $router->get('/app/estimates/ai', [EstimateController::class, 'aiGenerator']);
    $router->post('/app/estimates/ai/generate', [EstimateController::class, 'aiGenerate']);
    $router->get('/app/estimates/{id}', [EstimateController::class, 'show']);
    $router->post('/app/estimates/{id}/status', [EstimateController::class, 'updateStatus']);
    $router->post('/app/estimates/{id}/delete', [EstimateController::class, 'destroy']);

    $router->get('/app/invoices', [InvoiceController::class, 'index']);
    $router->get('/app/invoices/create', [InvoiceController::class, 'create']);
    $router->post('/app/invoices', [InvoiceController::class, 'store']);
    $router->get('/app/invoices/{id}', [InvoiceController::class, 'show']);
    $router->post('/app/invoices/{id}/status', [InvoiceController::class, 'updateStatus']);
    $router->post('/app/invoices/{id}/release-retention', [InvoiceController::class, 'releaseRetention']);
    $router->post('/app/invoices/{id}/delete', [InvoiceController::class, 'destroy']);
    $router->get('/app/invoices/{id}/xml', [InvoiceController::class, 'xml']);
    $router->post('/app/invoices/{id}/submit-zatca', [InvoiceController::class, 'submitZatca']);
    $router->post('/app/invoices/{id}/send-whatsapp', [InvoiceController::class, 'sendWhatsApp']);

    $router->get('/app/schedule', [ScheduleController::class, 'index']);
    $router->post('/app/schedule', [ScheduleController::class, 'store']);
    $router->post('/app/schedule/{id}/status', [ScheduleController::class, 'updateStatus']);
    $router->post('/app/schedule/{id}/delete', [ScheduleController::class, 'destroy']);

    $router->get('/app/team', [TeamController::class, 'index']);
    $router->post('/app/team', [TeamController::class, 'store']);
    $router->post('/app/team/{id}/role', [TeamController::class, 'updateRole']);
    $router->post('/app/team/{id}/delete', [TeamController::class, 'destroy']);

    $router->get('/app/billing', [BillingController::class, 'index']);
    $router->post('/app/billing/upgrade', [BillingController::class, 'upgrade']);
    $router->get('/app/billing/checkout', [BillingController::class, 'checkout']);
    $router->post('/app/billing/bank-transfer', [BillingController::class, 'requestBankTransfer']);
    $router->get('/app/billing/moyasar-callback', [BillingController::class, 'moyasarCallback']);

    $router->get('/app/settings', [SettingsController::class, 'index']);
    $router->post('/app/settings', [SettingsController::class, 'update']);
    $router->post('/app/settings/password', [SettingsController::class, 'updatePassword']);

    $router->get('/app/consultations', [ConsultationController::class, 'index']);
    $router->post('/app/consultations', [ConsultationController::class, 'store']);
    $router->post('/app/consultations/{id}/cancel', [ConsultationController::class, 'cancel']);

    $router->post('/app/end-impersonation', [ImpersonationController::class, 'stop']);

    $router->get('/app/leads', [LeadController::class, 'index']);
    $router->get('/app/leads/create', [LeadController::class, 'create']);
    $router->post('/app/leads', [LeadController::class, 'store']);
    $router->get('/app/leads/{id}/edit', [LeadController::class, 'edit']);
    $router->post('/app/leads/{id}', [LeadController::class, 'update']);
    $router->post('/app/leads/{id}/status', [LeadController::class, 'updateStatus']);
    $router->post('/app/leads/{id}/convert', [LeadController::class, 'convertToClient']);
    $router->post('/app/leads/{id}/delete', [LeadController::class, 'destroy']);

    $router->get('/app/business-setup', [BusinessSetupController::class, 'index']);
    $router->get('/app/business-setup/units-of-measure', [BusinessSetupController::class, 'units']);
    $router->post('/app/business-setup/units-of-measure', [BusinessSetupController::class, 'storeUnit']);
    $router->post('/app/business-setup/units-of-measure/load-defaults', [BusinessSetupController::class, 'loadDefaultUnits']);
    $router->post('/app/business-setup/units-of-measure/{id}', [BusinessSetupController::class, 'updateUnit']);
    $router->post('/app/business-setup/units-of-measure/{id}/delete', [BusinessSetupController::class, 'destroyUnit']);
    $router->get('/app/business-setup/tax-rates', [BusinessSetupController::class, 'taxRates']);
    $router->post('/app/business-setup/tax-rates', [BusinessSetupController::class, 'storeTaxRate']);
    $router->post('/app/business-setup/tax-rates/{id}', [BusinessSetupController::class, 'updateTaxRate']);
    $router->post('/app/business-setup/tax-rates/{id}/delete', [BusinessSetupController::class, 'destroyTaxRate']);
    $router->get('/app/business-setup/compliance', [ComplianceController::class, 'index']);
    $router->post('/app/business-setup/compliance', [ComplianceController::class, 'store']);
    $router->post('/app/business-setup/compliance/{id}', [ComplianceController::class, 'update']);
    $router->post('/app/business-setup/compliance/{id}/delete', [ComplianceController::class, 'destroy']);
    $router->get('/app/business-setup/{type}', [BusinessSetupController::class, 'simple']);
    $router->post('/app/business-setup/{type}', [BusinessSetupController::class, 'storeSimple']);
    $router->post('/app/business-setup/{type}/load-defaults', [BusinessSetupController::class, 'loadDefaultsSimple']);
    $router->post('/app/business-setup/{type}/{id}', [BusinessSetupController::class, 'updateSimple']);
    $router->post('/app/business-setup/{type}/{id}/delete', [BusinessSetupController::class, 'destroySimple']);

    $router->get('/app/suppliers', [SupplierController::class, 'index']);
    $router->get('/app/suppliers/create', [SupplierController::class, 'create']);
    $router->post('/app/suppliers', [SupplierController::class, 'store']);
    $router->get('/app/suppliers/{id}/edit', [SupplierController::class, 'edit']);
    $router->post('/app/suppliers/{id}', [SupplierController::class, 'update']);
    $router->post('/app/suppliers/{id}/delete', [SupplierController::class, 'destroy']);

    $router->get('/app/materials', [MaterialController::class, 'index']);
    $router->get('/app/materials/create', [MaterialController::class, 'create']);
    $router->post('/app/materials', [MaterialController::class, 'store']);
    $router->post('/app/materials/import', [MaterialController::class, 'importCsv']);
    $router->post('/app/materials/sync-sheet', [MaterialController::class, 'syncFromSheet']);
    $router->get('/app/materials/{id}/edit', [MaterialController::class, 'edit']);
    $router->post('/app/materials/{id}', [MaterialController::class, 'update']);
    $router->post('/app/materials/{id}/delete', [MaterialController::class, 'destroy']);

    $router->get('/app/documents', [DocumentController::class, 'index']);
    $router->post('/app/documents', [DocumentController::class, 'store']);
    $router->post('/app/documents/{id}/delete', [DocumentController::class, 'destroy']);

    $router->get('/app/reports', [ReportController::class, 'overview']);
    $router->get('/app/reports/profit', [ReportController::class, 'profit']);
    $router->get('/app/reports/tax', [ReportController::class, 'tax']);
    $router->get('/app/reports/retention', [ReportController::class, 'retention']);

    $router->get('/app/integrations', [IntegrationController::class, 'index']);
    $router->post('/app/integrations/google-sheets', [IntegrationController::class, 'updateGoogleSheets']);
    $router->post('/app/integrations/client-payments', [IntegrationController::class, 'updateClientPayments']);

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
// Both super_admin and read-only support_admin can browse everything registered directly in this
// outer group. Anything that mutates data is additionally nested inside a requireSuperAdmin() group
// below, so a support_admin hitting a POST route (even by guessing the URL) gets a clean 403.
$router->group([fn() => Auth::requireAdminPanelAccess()], function (Router $router) {
    $router->get('/admin', [AdminDashboardController::class, 'index']);
    $router->get('/admin/reports', [AdminReportController::class, 'index']);
    $router->get('/admin/usage', [UsageController::class, 'index']);
    $router->get('/admin/audit-log', [AuditLogController::class, 'index']);

    $router->get('/admin/companies', [CompanyController::class, 'index']);
    $router->get('/admin/companies/export.csv', [CompanyController::class, 'exportCsv']);
    $router->get('/admin/companies/{id}', [CompanyController::class, 'show']);
    $router->get('/admin/companies/{id}/zatca', [CompanyZatcaController::class, 'show']);

    $router->get('/admin/plans', [PlanController::class, 'index']);
    $router->get('/admin/plans/create', [PlanController::class, 'create']);
    $router->get('/admin/plans/{id}/edit', [PlanController::class, 'edit']);

    $router->get('/admin/payments', [PaymentController::class, 'index']);
    $router->get('/admin/payments/export.csv', [PaymentController::class, 'exportCsv']);
    $router->get('/admin/payments/{id}', [PaymentController::class, 'show']);

    $router->get('/admin/profile', [AdminProfileController::class, 'index']);
    $router->post('/admin/profile', [AdminProfileController::class, 'update']);

    $router->get('/admin/admins', [AdminUserController::class, 'index']);

    $router->get('/admin/settings', [SiteSettingsController::class, 'index']);
    $router->get('/admin/settings/payments', [SiteSettingsController::class, 'payments']);
    $router->get('/admin/settings/legal', [SiteSettingsController::class, 'legal']);
    $router->get('/admin/settings/notifications', [SiteSettingsController::class, 'notifications']);
    $router->get('/admin/settings/email', [SiteSettingsController::class, 'email']);
    $router->get('/admin/settings/header', [SiteSettingsController::class, 'header']);
    $router->get('/admin/settings/ai', [SiteSettingsController::class, 'ai']);
    $router->get('/admin/integrations', [AdminIntegrationController::class, 'index']);

    $router->get('/admin/pages', [AdminPageController::class, 'index']);
    $router->get('/admin/pages/create', [AdminPageController::class, 'create']);
    $router->get('/admin/pages/{id}/edit', [AdminPageController::class, 'edit']);

    $router->get('/admin/translations', [AdminTranslationController::class, 'index']);

    $router->get('/admin/quick-estimate', [QuickEstimateAdminController::class, 'index']);
    $router->get('/admin/quick-estimate/regions', [QuickEstimateAdminController::class, 'regions']);
    $router->get('/admin/quick-estimate/foundations', [QuickEstimateAdminController::class, 'foundations']);
    $router->get('/admin/quick-estimate/addons', [QuickEstimateAdminController::class, 'addons']);
    $router->get('/admin/quick-estimate/leads', [QuickEstimateAdminController::class, 'leads']);

    $router->get('/admin/estimate-templates', [EstimateTemplateAdminController::class, 'index']);
    $router->get('/admin/estimate-templates/{id}/items', [EstimateTemplateAdminController::class, 'items']);

    $router->get('/admin/consultations', [ConsultationAdminController::class, 'index']);

    // ---- Everything below mutates platform data: super_admin only ----
    $router->group([fn() => Auth::requireSuperAdmin()], function (Router $router) {
        $router->post('/admin/companies/{id}/status', [CompanyController::class, 'updateStatus']);
        $router->post('/admin/companies/{id}/plan', [CompanyController::class, 'updatePlan']);
        $router->post('/admin/companies/{id}/profile', [CompanyController::class, 'updateProfile']);
        $router->post('/admin/companies/{id}/impersonate', [CompanyController::class, 'impersonate']);
        $router->post('/admin/companies/{id}/hard-delete', [CompanyController::class, 'hardDelete']);

        $router->post('/admin/companies/{id}/zatca/environment', [CompanyZatcaController::class, 'updateEnvironment']);
        $router->post('/admin/companies/{id}/zatca/csr', [CompanyZatcaController::class, 'generateCsr']);
        $router->post('/admin/companies/{id}/zatca/compliance-csid', [CompanyZatcaController::class, 'requestComplianceCsid']);
        $router->post('/admin/companies/{id}/zatca/production-csid', [CompanyZatcaController::class, 'requestProductionCsid']);

        $router->post('/admin/plans', [PlanController::class, 'store']);
        $router->post('/admin/plans/{id}', [PlanController::class, 'update']);
        $router->post('/admin/plans/{id}/delete', [PlanController::class, 'destroy']);

        $router->post('/admin/payments/{id}/update', [PaymentController::class, 'update']);
        $router->post('/admin/payments/{id}/apply-plan', [PaymentController::class, 'applyPlan']);
        $router->post('/admin/payments/{id}/approve', [PaymentController::class, 'approve']);
        $router->post('/admin/payments/{id}/reject', [PaymentController::class, 'reject']);

        $router->post('/admin/admins', [AdminUserController::class, 'store']);
        $router->post('/admin/admins/{id}/delete', [AdminUserController::class, 'destroy']);

        $router->post('/admin/settings', [SiteSettingsController::class, 'update']);
        $router->post('/admin/settings/payments', [SiteSettingsController::class, 'updatePayments']);
        $router->post('/admin/settings/legal', [SiteSettingsController::class, 'updateLegal']);
        $router->post('/admin/settings/notifications', [SiteSettingsController::class, 'updateNotifications']);
        $router->post('/admin/settings/email', [SiteSettingsController::class, 'updateEmail']);
        $router->post('/admin/settings/header', [SiteSettingsController::class, 'updateHeader']);
        $router->post('/admin/settings/ai', [SiteSettingsController::class, 'updateAi']);

        $router->post('/admin/pages', [AdminPageController::class, 'store']);
        $router->post('/admin/pages/{id}', [AdminPageController::class, 'update']);
        $router->post('/admin/pages/{id}/delete', [AdminPageController::class, 'destroy']);

        $router->post('/admin/translations/update', [AdminTranslationController::class, 'update']);
        $router->post('/admin/translations/store', [AdminTranslationController::class, 'store']);
        $router->post('/admin/translations/reset', [AdminTranslationController::class, 'reset']);

        $router->post('/admin/quick-estimate/regions', [QuickEstimateAdminController::class, 'storeRegion']);
        $router->post('/admin/quick-estimate/regions/{id}', [QuickEstimateAdminController::class, 'updateRegion']);
        $router->post('/admin/quick-estimate/regions/{id}/delete', [QuickEstimateAdminController::class, 'destroyRegion']);
        $router->post('/admin/quick-estimate/foundations', [QuickEstimateAdminController::class, 'storeFoundation']);
        $router->post('/admin/quick-estimate/foundations/{id}', [QuickEstimateAdminController::class, 'updateFoundation']);
        $router->post('/admin/quick-estimate/foundations/{id}/delete', [QuickEstimateAdminController::class, 'destroyFoundation']);
        $router->post('/admin/quick-estimate/addons', [QuickEstimateAdminController::class, 'storeAddon']);
        $router->post('/admin/quick-estimate/addons/{id}', [QuickEstimateAdminController::class, 'updateAddon']);
        $router->post('/admin/quick-estimate/addons/{id}/delete', [QuickEstimateAdminController::class, 'destroyAddon']);
        $router->post('/admin/quick-estimate/leads/{id}/status', [QuickEstimateAdminController::class, 'updateLeadStatus']);

        $router->post('/admin/estimate-templates', [EstimateTemplateAdminController::class, 'store']);
        $router->post('/admin/estimate-templates/{id}', [EstimateTemplateAdminController::class, 'update']);
        $router->post('/admin/estimate-templates/{id}/delete', [EstimateTemplateAdminController::class, 'destroy']);
        $router->post('/admin/estimate-templates/{id}/default', [EstimateTemplateAdminController::class, 'setDefault']);
        $router->post('/admin/estimate-templates/{id}/items', [EstimateTemplateAdminController::class, 'storeItem']);
        $router->post('/admin/estimate-templates/{id}/items/{itemId}', [EstimateTemplateAdminController::class, 'updateItem']);
        $router->post('/admin/estimate-templates/{id}/items/{itemId}/delete', [EstimateTemplateAdminController::class, 'destroyItem']);

        $router->post('/admin/consultations/{id}/update', [ConsultationAdminController::class, 'update']);
    });
});

// ---------- Client Portal ----------
$router->get('/portal/login', [PortalController::class, 'showLogin']);
$router->post('/portal/login', [PortalController::class, 'login']);
$router->post('/portal/logout', [PortalController::class, 'logout']);

$router->group([fn() => PortalAuth::requireLogin()], function (Router $router) {
    $router->get('/portal', [PortalController::class, 'dashboard']);
    $router->get('/portal/projects/{id}', [PortalController::class, 'project']);
    $router->get('/portal/estimates/{id}', [PortalController::class, 'estimate']);
    $router->get('/portal/invoices/{id}', [PortalController::class, 'invoice']);
});
