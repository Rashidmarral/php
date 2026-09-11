<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Support\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    private const ALLOWED_DOC_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function index(): View
    {
        $companies = DB::table('companies as c')
            ->leftJoin('plans as p', 'p.id', '=', 'c.plan_id')
            ->select('c.*', 'p.name as plan_name')
            ->orderByDesc('c.created_at')
            ->get()
            ->map(fn ($r) => (array) $r);

        return view('admin.companies.index', ['companies' => $companies]);
    }

    public function show(int $id): View
    {
        $company = Company::findOrFail($id);
        $users = User::where('company_id', $company->id)->orderBy('created_at')->get();
        $subscriptions = DB::table('subscriptions as s')
            ->join('plans as p', 'p.id', '=', 's.plan_id')
            ->where('s.company_id', $company->id)
            ->orderByDesc('s.created_at')
            ->select('s.*', 'p.name as plan_name')
            ->get()
            ->map(fn ($r) => (array) $r);
        $payments = Payment::where('company_id', $company->id)->orderByDesc('created_at')->get();
        $projectCount = Project::where('company_id', $company->id)->count();
        $plans = Plan::orderBy('sort_order')->get();

        return view('admin.companies.show', [
            'company' => $company,
            'users' => $users,
            'subscriptions' => $subscriptions,
            'payments' => $payments,
            'projectCount' => $projectCount,
            'plans' => $plans,
        ]);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $status = (string) $request->input('status');
        if (in_array($status, ['trial', 'active', 'past_due', 'suspended', 'cancelled'], true)) {
            $company->update(['status' => $status]);
            AuditLog::record($request->user(), 'company_status_change', 'company', $company->id, "{$company->name} → {$status}");
            $this->flash('success', 'Company status updated.');
        }
        return redirect('/admin/companies/' . $company->id);
    }

    /** Admin can edit any company's full profile on their behalf (name in both languages, ZATCA address, VAT/CR). */
    public function updateProfile(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);

        $data = [
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'email' => trim((string) $request->input('email')),
            'phone' => $request->input('phone', ''),
            'city' => $request->input('city', ''),
            'team_size' => $request->input('team_size', ''),
            'address' => $request->input('address', ''),
            'cr_number' => $request->input('cr_number', ''),
            'vat_number' => $request->input('vat_number', ''),
            'mol_establishment_number' => trim((string) $request->input('mol_establishment_number', '')),
            'building_number' => trim((string) $request->input('building_number', '')),
            'street_name' => trim((string) $request->input('street_name', '')),
            'district' => trim((string) $request->input('district', '')),
            'postal_code' => trim((string) $request->input('postal_code', '')),
            'additional_number' => trim((string) $request->input('additional_number', '')),
            'contractor_classification' => $request->input('contractor_classification', ''),
            'contractor_classification_number' => trim((string) $request->input('contractor_classification_number', '')),
        ];

        $docError = $this->handleDocUpload($request, 'cr_document', $company->id, 'cr_document_path', $data);
        $docError = $docError ?: $this->handleDocUpload($request, 'vat_document', $company->id, 'vat_document_path', $data);
        if ($docError) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id, 'error', $docError);
        }

        $company->update($data);
        AuditLog::record($request->user(), 'company_profile_update', 'company', $company->id, $company->name);

        return $this->redirectWithFlash('/admin/companies/' . $company->id, 'success', 'Company profile updated.');
    }

    /** @param array $data by reference — sets $column on success */
    private function handleDocUpload(Request $request, string $field, int $companyId, string $column, array &$data): ?string
    {
        $file = $request->file($field);
        if (!$file || !$file->isValid()) {
            return null;
        }
        $mime = $file->getMimeType();
        if (!isset(self::ALLOWED_DOC_TYPES[$mime])) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be a PDF, JPG, or PNG file.';
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return ucfirst(str_replace('_', ' ', $field)) . ' must be smaller than 10MB.';
        }
        $filename = "company-{$companyId}-{$field}-" . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_DOC_TYPES[$mime];
        $file->move(public_path('uploads/company-documents'), $filename);
        $data[$column] = "/uploads/company-documents/{$filename}";
        return null;
    }

    /** Admin override: change a company's plan directly, no payment involved. */
    public function updatePlan(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $plan = Plan::find((int) $request->input('plan_id'));
        if (!$plan) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id, 'error', 'Invalid plan.');
        }
        $cycle = $request->input('billing_cycle', 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        Billing::activatePlan($company->id, $plan, $cycle);
        AuditLog::record($request->user(), 'company_plan_override', 'company', $company->id, "{$company->name} → {$plan->name}");

        return $this->redirectWithFlash('/admin/companies/' . $company->id, 'success', "Plan changed to {$plan->name} (admin override, no charge recorded).");
    }

    /** Log the admin in as this company's owner, for support/debugging. Ends via /app/end-impersonation. */
    public function impersonate(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        $owner = User::where('company_id', $company->id)->where('role', 'owner')->first();
        if (!$owner) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id, 'error', 'This company has no owner account to log in as.');
        }

        $admin = $request->user();
        AuditLog::record($admin, 'impersonate_start', 'company', $company->id, "{$admin->name} logged in as {$owner->name} ({$company->name})");

        session(['impersonator_admin_id' => $admin->id]);
        Auth::login($owner);

        return redirect('/app');
    }

    /** Permanently deletes a company and every row it owns. Cannot be undone — used for compliance/data-deletion requests. */
    public function hardDelete(Request $request, int $id): RedirectResponse
    {
        $company = Company::findOrFail($id);
        if ($request->input('confirm_name') !== $company->name) {
            return $this->redirectWithFlash('/admin/companies/' . $company->id, 'error', 'Type the company name exactly to confirm permanent deletion.');
        }

        $companyId = $company->id;

        DB::table('estimate_items')->whereIn('estimate_id', DB::table('estimates')->where('company_id', $companyId)->select('id'))->delete();
        DB::table('invoice_items')->whereIn('invoice_id', DB::table('invoices')->where('company_id', $companyId)->select('id'))->delete();
        DB::table('takeoff_measurements')->whereIn('takeoff_id', DB::table('takeoffs')->where('company_id', $companyId)->select('id'))->delete();

        $directTables = [
            'users', 'subscriptions', 'payments', 'clients', 'projects', 'estimates', 'project_photos',
            'change_orders', 'invoices', 'schedule_tasks', 'takeoffs', 'suppliers', 'materials', 'documents',
            'leads', 'building_types', 'contact_types', 'client_types', 'units_of_measure', 'tax_rates',
            'invoice_payments', 'quick_estimates', 'compliance_documents', 'consultations',
        ];
        foreach ($directTables as $table) {
            DB::table($table)->where('company_id', $companyId)->delete();
        }

        $companyName = $company->name;
        $company->delete();

        AuditLog::record($request->user(), 'company_hard_delete', 'company', $companyId, "Permanently deleted {$companyName} and all its data");

        return $this->redirectWithFlash('/admin/companies', 'success', "{$companyName} and all its data have been permanently deleted.");
    }

    public function exportCsv(): Response
    {
        $companies = DB::table('companies as c')
            ->leftJoin('plans as p', 'p.id', '=', 'c.plan_id')
            ->select('c.*', 'p.name as plan_name')
            ->orderByDesc('c.created_at')
            ->get();

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['ID', 'Name', 'Email', 'Phone', 'City', 'Plan', 'Status', 'Created At']);
        foreach ($companies as $c) {
            fputcsv($csv, [$c->id, $c->name, $c->email, $c->phone, $c->city, $c->plan_name ?? '—', $c->status, $c->created_at]);
        }
        rewind($csv);
        $body = stream_get_contents($csv);
        fclose($csv);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="companies-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
