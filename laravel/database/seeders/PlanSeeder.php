<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $featureFlagsByPlan = [
            'starter' => [
                'takeoff' => false, 'suppliers' => true, 'materials' => true, 'documents' => true, 'reports' => false,
                'client_portal' => false, 'integrations' => false, 'zatca_phase2' => false, 'leads' => true,
                'compliance' => true, 'change_orders' => false, 'project_photos' => true, 'quick_estimate' => true,
                'ai_estimate_generator' => false, 'estimate_templates' => true, 'online_invoice_payments' => false,
            ],
            'professional' => [
                'takeoff' => true, 'suppliers' => true, 'materials' => true, 'documents' => true, 'reports' => true,
                'client_portal' => true, 'integrations' => true, 'zatca_phase2' => true, 'leads' => true,
                'compliance' => true, 'change_orders' => true, 'project_photos' => true, 'quick_estimate' => true,
                'ai_estimate_generator' => true, 'estimate_templates' => true, 'online_invoice_payments' => true,
            ],
            'enterprise' => [
                'takeoff' => true, 'suppliers' => true, 'materials' => true, 'documents' => true, 'reports' => true,
                'client_portal' => true, 'integrations' => true, 'zatca_phase2' => true, 'leads' => true,
                'compliance' => true, 'change_orders' => true, 'project_photos' => true, 'quick_estimate' => true,
                'ai_estimate_generator' => true, 'estimate_templates' => true, 'online_invoice_payments' => true,
            ],
        ];

        $plans = [
            [
                'slug' => 'starter', 'name' => 'Starter', 'tagline' => 'For small contractors getting organized',
                'price_monthly' => 199, 'price_yearly' => 1990, 'max_users' => 3, 'max_projects' => 10,
                'consultation_quota_monthly' => 1, 'sort_order' => 1,
                'features' => [
                    'Unlimited estimates & quotes', 'Up to 10 active projects', '3 team members',
                    'Client & invoice management', 'Email support',
                    'ZATCA Phase 1 QR code invoicing',
                    'WhatsApp sharing for estimates & invoices', 'Client e-signature on estimates',
                    'Change orders & retention tracking', 'Site photo diary per project',
                    'Saudi tax invoice PDF layout',
                    '12 built-in estimate templates', 'AI Estimate Generator', 'Role-based team permissions',
                    'Leads pipeline', 'Business Setup (building types, tax rates & more)',
                ],
            ],
            [
                'slug' => 'professional', 'name' => 'Professional', 'tagline' => 'For growing contracting businesses',
                'price_monthly' => 449, 'price_yearly' => 4490, 'max_users' => 15, 'max_projects' => 50,
                'consultation_quota_monthly' => 3, 'sort_order' => 2,
                'features' => [
                    'Everything in Starter', 'Up to 50 active projects', '15 team members',
                    'Job costing & budget tracking', 'Project scheduling', 'Priority support',
                    'ZATCA Phase 1 + Phase 2 (Fatoora) e-invoicing',
                ],
            ],
            [
                'slug' => 'enterprise', 'name' => 'Enterprise', 'tagline' => 'For large contractors & developers',
                'price_monthly' => 899, 'price_yearly' => 8990, 'max_users' => 999, 'max_projects' => 999,
                'consultation_quota_monthly' => 5, 'sort_order' => 3,
                'features' => [
                    'Everything in Professional', 'Unlimited projects & users', 'Multi-branch support',
                    'Dedicated account manager', 'ZATCA-ready VAT invoicing', 'Custom onboarding',
                    'ZATCA Phase 1 + Phase 2 (Fatoora) e-invoicing, fully managed',
                ],
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'tagline' => $p['tagline'],
                    'price_monthly' => $p['price_monthly'],
                    'price_yearly' => $p['price_yearly'],
                    'currency' => 'SAR',
                    'max_users' => $p['max_users'],
                    'max_projects' => $p['max_projects'],
                    'consultation_quota_monthly' => $p['consultation_quota_monthly'],
                    'features' => json_encode($p['features']),
                    'feature_flags' => json_encode($featureFlagsByPlan[$p['slug']]),
                    'is_active' => true,
                    'sort_order' => $p['sort_order'],
                ]
            );
        }

        $this->command?->info('Plans seeded.');
    }
}
