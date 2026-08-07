<?php

namespace App\Core;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;

/**
 * Plan-based feature gating. Every key here must also appear in
 * Feature::ALL and be seeded on at least one plan's feature_flags in
 * database/migrate.php for the admin Plan editor checkboxes to make sense.
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
        'change_orders' => 'Change Orders',
        'project_photos' => 'Project Photo Gallery',
        'quick_estimate' => 'Quick Estimate Tool',
        'ai_estimate_generator' => 'AI Estimate Generator',
        'estimate_templates' => 'Estimate Template Library',
        'online_invoice_payments' => 'Client Online Invoice Payments (Moyasar)',
    ];

    private static ?array $cachedFlags = null;
    private static ?array $cachedPlan = null;

    public static function currentPlan(): ?array
    {
        if (self::$cachedPlan !== null) {
            return self::$cachedPlan ?: null;
        }
        $companyId = Auth::companyId();
        if (!$companyId) {
            self::$cachedPlan = [];
            return null;
        }
        $company = Company::find($companyId);
        $plan = $company && $company['plan_id'] ? Plan::find((int) $company['plan_id']) : null;
        self::$cachedPlan = $plan ?: [];
        return $plan;
    }

    public static function flags(): array
    {
        if (self::$cachedFlags !== null) {
            return self::$cachedFlags;
        }
        $plan = self::currentPlan();
        self::$cachedFlags = $plan ? (json_decode((string) ($plan['feature_flags'] ?? '{}'), true) ?: []) : [];
        return self::$cachedFlags;
    }

    public static function allows(string $key): bool
    {
        if (Auth::isSuperAdmin()) {
            return true;
        }
        return !empty(self::flags()[$key]);
    }

    /** Same check as allows(), but for an arbitrary company (e.g. on public client-facing pages with no session). */
    public static function allowsForCompany(string $key, ?array $company): bool
    {
        if (!$company || !$company['plan_id']) {
            return false;
        }
        $plan = Plan::find((int) $company['plan_id']);
        $flags = $plan ? (json_decode((string) ($plan['feature_flags'] ?? '{}'), true) ?: []) : [];
        return !empty($flags[$key]);
    }

    /** Redirects to Billing with an upgrade prompt if the current company's plan lacks $key. */
    public static function requireOrRedirect(string $key): void
    {
        if (self::allows($key)) {
            return;
        }
        $label = self::ALL[$key] ?? $key;
        $_SESSION['flash']['error'][] = "{$label} isn't included in your current plan. Upgrade to unlock it.";
        Controller::redirect('/app/billing');
    }

    public static function userLimit(): ?int
    {
        $plan = self::currentPlan();
        return $plan ? (int) $plan['max_users'] : null;
    }

    public static function projectLimit(): ?int
    {
        $plan = self::currentPlan();
        return $plan ? (int) $plan['max_projects'] : null;
    }

    public static function withinUserLimit(): bool
    {
        $limit = self::userLimit();
        if ($limit === null || $limit >= 999) {
            return true;
        }
        return User::count('company_id = ?', [Auth::companyId()]) < $limit;
    }

    public static function withinProjectLimit(): bool
    {
        $limit = self::projectLimit();
        if ($limit === null || $limit >= 999) {
            return true;
        }
        return Project::count('company_id = ?', [Auth::companyId()]) < $limit;
    }
}
