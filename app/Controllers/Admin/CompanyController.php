<?php

namespace App\Controllers\Admin;

use App\Controllers\User\BillingController;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;

class CompanyController extends Controller
{
    private const ALLOWED_DOC_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function index(): void
    {
        $companies = Company::query(
            'SELECT c.*, p.name AS plan_name FROM companies c LEFT JOIN plans p ON p.id = c.plan_id ORDER BY c.created_at DESC'
        )->fetchAll();
        $this->view('admin/companies/index', ['pageTitle' => 'Companies', 'companies' => $companies], 'layouts/admin');
    }

    public function show(string $id): void
    {
        $company = Company::find((int) $id);
        if (!$company) {
            http_response_code(404);
            die('Company not found.');
        }
        $users = User::where('company_id', $company['id'], 'created_at ASC');
        $subscriptions = Subscription::query(
            'SELECT s.*, p.name AS plan_name FROM subscriptions s JOIN plans p ON p.id = s.plan_id WHERE s.company_id = ? ORDER BY s.created_at DESC',
            [$company['id']]
        )->fetchAll();
        $payments = Payment::where('company_id', $company['id'], 'created_at DESC');
        $projectCount = Project::count('company_id = ?', [$company['id']]);
        $plans = Plan::all('sort_order ASC');

        $this->view('admin/companies/show', [
            'pageTitle' => $company['name'],
            'company' => $company,
            'users' => $users,
            'subscriptions' => $subscriptions,
            'payments' => $payments,
            'projectCount' => $projectCount,
            'plans' => $plans,
        ], 'layouts/admin');
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        $company = Company::find((int) $id);
        if (!$company) {
            http_response_code(404);
            die('Company not found.');
        }
        $status = (string) $this->input('status');
        if (in_array($status, ['trial', 'active', 'past_due', 'suspended', 'cancelled'], true)) {
            Company::update($company['id'], ['status' => $status]);
            Audit::log('company_status_change', 'company', $company['id'], "{$company['name']} → {$status}");
            $this->flash('success', 'Company status updated.');
        }
        self::redirect('/admin/companies/' . $company['id']);
    }

    /** Admin can edit any company's full profile on their behalf (name in both languages, ZATCA address, VAT/CR). */
    public function updateProfile(string $id): void
    {
        $this->verifyCsrf();
        $company = Company::find((int) $id);
        if (!$company) {
            http_response_code(404);
            die('Company not found.');
        }

        $data = [
            'name' => trim((string) $this->input('name')),
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'email' => trim((string) $this->input('email')),
            'phone' => $this->input('phone', ''),
            'city' => $this->input('city', ''),
            'address' => $this->input('address', ''),
            'cr_number' => $this->input('cr_number', ''),
            'vat_number' => $this->input('vat_number', ''),
            'building_number' => trim((string) $this->input('building_number', '')),
            'street_name' => trim((string) $this->input('street_name', '')),
            'district' => trim((string) $this->input('district', '')),
            'postal_code' => trim((string) $this->input('postal_code', '')),
            'additional_number' => trim((string) $this->input('additional_number', '')),
            'contractor_classification' => $this->input('contractor_classification', ''),
            'contractor_classification_number' => trim((string) $this->input('contractor_classification_number', '')),
        ];

        $docError = $this->handleDocUpload('cr_document', $company['id'], 'cr_document_path', $data);
        $docError = $docError ?: $this->handleDocUpload('vat_document', $company['id'], 'vat_document_path', $data);
        if ($docError) {
            $this->flash('error', $docError);
            self::redirect('/admin/companies/' . $company['id']);
        }

        Company::update($company['id'], $data);
        Audit::log('company_profile_update', 'company', $company['id'], $company['name']);

        $this->flash('success', 'Company profile updated.');
        self::redirect('/admin/companies/' . $company['id']);
    }

    /** @param array $data by reference — sets $column on success */
    private function handleDocUpload(string $field, int $companyId, string $column, array &$data): ?string
    {
        if (empty($_FILES[$field]['tmp_name']) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $mime = mime_content_type($_FILES[$field]['tmp_name']);
        if (!isset(self::ALLOWED_DOC_TYPES[$mime])) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be a PDF, JPG, or PNG file.';
        }
        if ($_FILES[$field]['size'] > 10 * 1024 * 1024) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be smaller than 10MB.';
        }
        $dir = BASE_PATH . '/public/uploads/company-documents';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = "company-{$companyId}-{$field}-" . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_DOC_TYPES[$mime];
        move_uploaded_file($_FILES[$field]['tmp_name'], "{$dir}/{$filename}");
        $data[$column] = "/uploads/company-documents/{$filename}";
        return null;
    }

    /** Admin override: change a company's plan directly, no payment involved. */
    public function updatePlan(string $id): void
    {
        $this->verifyCsrf();
        $company = Company::find((int) $id);
        if (!$company) {
            http_response_code(404);
            die('Company not found.');
        }
        $plan = Plan::find((int) $this->input('plan_id'));
        if (!$plan) {
            $this->flash('error', 'Invalid plan.');
            self::redirect('/admin/companies/' . $company['id']);
        }
        $cycle = $this->input('billing_cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        BillingController::activatePlan($company['id'], $plan, $cycle);
        Audit::log('company_plan_override', 'company', $company['id'], "{$company['name']} → {$plan['name']}");

        $this->flash('success', "Plan changed to {$plan['name']} (admin override, no charge recorded).");
        self::redirect('/admin/companies/' . $company['id']);
    }

    /** Log the admin in as this company's owner, for support/debugging. Ends via /app/end-impersonation. */
    public function impersonate(string $id): void
    {
        $this->verifyCsrf();
        $company = Company::find((int) $id);
        if (!$company) {
            http_response_code(404);
            die('Company not found.');
        }
        $owner = User::query("SELECT * FROM users WHERE company_id = ? AND role = 'owner' LIMIT 1", [$company['id']])->fetch();
        if (!$owner) {
            $this->flash('error', 'This company has no owner account to log in as.');
            self::redirect('/admin/companies/' . $company['id']);
        }

        $admin = Auth::user();
        Audit::log('impersonate_start', 'company', $company['id'], "{$admin['name']} logged in as {$owner['name']} ({$company['name']})");

        $_SESSION['impersonator_admin_id'] = $admin['id'];
        Auth::login($owner);
        self::redirect('/app');
    }

    /** Permanently deletes a company and every row it owns. Cannot be undone — used for compliance/data-deletion requests. */
    public function hardDelete(string $id): void
    {
        $this->verifyCsrf();
        $company = Company::find((int) $id);
        if (!$company) {
            http_response_code(404);
            die('Company not found.');
        }
        if ($this->input('confirm_name') !== $company['name']) {
            $this->flash('error', 'Type the company name exactly to confirm permanent deletion.');
            self::redirect('/admin/companies/' . $company['id']);
        }

        $companyId = $company['id'];
        $pdo = \App\Core\Database::pdo();

        $pdo->prepare('DELETE FROM estimate_items WHERE estimate_id IN (SELECT id FROM estimates WHERE company_id = ?)')->execute([$companyId]);
        $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE company_id = ?)')->execute([$companyId]);
        $pdo->prepare('DELETE FROM takeoff_measurements WHERE takeoff_id IN (SELECT id FROM takeoffs WHERE company_id = ?)')->execute([$companyId]);

        $directTables = [
            'users', 'subscriptions', 'payments', 'clients', 'projects', 'estimates', 'project_photos',
            'change_orders', 'invoices', 'schedule_tasks', 'takeoffs', 'suppliers', 'materials', 'documents',
            'leads', 'building_types', 'contact_types', 'client_types', 'units_of_measure', 'tax_rates',
            'invoice_payments', 'quick_estimates', 'compliance_documents', 'consultations',
        ];
        foreach ($directTables as $table) {
            $pdo->prepare("DELETE FROM {$table} WHERE company_id = ?")->execute([$companyId]);
        }

        $pdo->prepare('DELETE FROM companies WHERE id = ?')->execute([$companyId]);

        Audit::log('company_hard_delete', 'company', $companyId, "Permanently deleted {$company['name']} and all its data");

        $this->flash('success', "{$company['name']} and all its data have been permanently deleted.");
        self::redirect('/admin/companies');
    }

    public function exportCsv(): void
    {
        $companies = Company::query(
            'SELECT c.*, p.name AS plan_name FROM companies c LEFT JOIN plans p ON p.id = c.plan_id ORDER BY c.created_at DESC'
        )->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="companies-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'City', 'Plan', 'Status', 'Created At']);
        foreach ($companies as $c) {
            fputcsv($out, [$c['id'], $c['name'], $c['email'], $c['phone'], $c['city'], $c['plan_name'] ?? '—', $c['status'], $c['created_at']]);
        }
        fclose($out);
    }
}
