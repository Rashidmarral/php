<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Plan-based feature gating. Every key here must also appear in
 * Feature::ALL and be seeded on at least one plan's feature_flags for the
 * admin Plan editor checkboxes to make sense.
 */
class Feature
{
    public const ALL = [
        'takeoff' => 'Digital Takeoff',
        'suppliers' => 'Suppliers',
        'materials' => 'Materials & Pricing Library',
        'documents' => 'Documents',
        'reports' => 'Business Reports',
        'client_portal' => 'Client Portal',
        'integrations' => 'Integrations',
        'zatca_phase2' => 'ZATCA Phase 2 e-invoicing',
        'leads' => 'Leads & CRM',
        'compliance' => 'Compliance Document Tracker',
        'zakat' => 'Zakat Estimator',
        'change_orders' => 'Change Orders',
        'bank_guarantees' => 'Bank Guarantees & Bonds',
        'purchase_orders' => 'Purchase Orders',
        'recurring_invoices' => 'Recurring Invoices',
        'approval_workflow' => 'Approval Workflow',
        'project_photos' => 'Project Photo Gallery',
        'site_logs' => 'Daily Site Log',
        'punch_list' => 'Punch List / Snag Tracking',
        'quick_estimate' => 'Quick Estimate Tool',
        'ai_estimate_generator' => 'AI Estimate Generator',
        'estimate_templates' => 'Estimate Template Library',
        'online_invoice_payments' => 'Client Online Invoice Payments (Moyasar)',
        'priority_support' => 'Priority Support (faster ticket response)',
    ];

    private static ?Plan $cachedPlan = null;
    private static bool $planResolved = false;
    private static ?array $cachedFlags = null;

    public static function currentPlan(): ?Plan
    {
        if (self::$planResolved) {
            return self::$cachedPlan;
        }
        self::$planResolved = true;

        $companyId = Auth::user()?->company_id;
        if (!$companyId) {
            return self::$cachedPlan = null;
        }
        $company = Company::find($companyId);
        return self::$cachedPlan = ($company && $company->plan_id) ? Plan::find($company->plan_id) : null;
    }

    public static function flags(): array
    {
        if (self::$cachedFlags !== null) {
            return self::$cachedFlags;
        }
        $plan = self::currentPlan();
        return self::$cachedFlags = $plan ? (json_decode((string) $plan->feature_flags, true) ?: []) : [];
    }

    public static function allows(string $key): bool
    {
        if (Auth::user()?->isSuperAdmin()) {
            return true;
        }
        return !empty(self::flags()[$key]);
    }

    /** Same check as allows(), but for an arbitrary company (e.g. on public client-facing pages with no session). */
    public static function allowsForCompany(string $key, ?Company $company): bool
    {
        if (!$company || !$company->plan_id) {
            return false;
        }
        $plan = Plan::find($company->plan_id);
        $flags = $plan ? (json_decode((string) $plan->feature_flags, true) ?: []) : [];
        return !empty($flags[$key]);
    }

    public static function userLimit(): ?int
    {
        $plan = self::currentPlan();
        return $plan ? (int) $plan->max_users : null;
    }

    public static function projectLimit(): ?int
    {
        $plan = self::currentPlan();
        return $plan ? (int) $plan->max_projects : null;
    }

    public static function withinUserLimit(): bool
    {
        $limit = self::userLimit();
        if ($limit === null || $limit >= 999) {
            return true;
        }
        return User::where('company_id', Auth::user()?->company_id)->count() < $limit;
    }

    public static function withinProjectLimit(): bool
    {
        $limit = self::projectLimit();
        if ($limit === null || $limit >= 999) {
            return true;
        }
        return Project::where('company_id', Auth::user()?->company_id)->count() < $limit;
    }
}
