<?php

namespace App\Controllers\Admin;

use App\Controllers\User\BillingController;
use App\Core\Controller;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;

class CompanyController extends Controller
{
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
        if (in_array($status, ['trial', 'active', 'suspended', 'cancelled'], true)) {
            Company::update($company['id'], ['status' => $status]);
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

        Company::update($company['id'], [
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
        ]);

        $this->flash('success', 'Company profile updated.');
        self::redirect('/admin/companies/' . $company['id']);
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

        $this->flash('success', "Plan changed to {$plan['name']} (admin override, no charge recorded).");
        self::redirect('/admin/companies/' . $company['id']);
    }
}
