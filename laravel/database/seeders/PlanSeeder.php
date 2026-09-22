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
                'compliance' => true, 'change_orders' => false, 'bank_guarantees' => false, 'purchase_orders' => false, 'recurring_invoices' => false, 'project_photos' => true, 'quick_estimate' => true,
                'ai_estimate_generator' => false, 'estimate_templates' => true, 'online_invoice_payments' => false,
                'priority_support' => false, 'site_logs' => true, 'punch_list' => true, 'zakat' => false, 'approval_workflow' => false, 'payment_certificates' => false, 'subcontractors' => false, 'ld_eot_tracking' => false,
            ],
            'professional' => [
                'takeoff' => true, 'suppliers' => true, 'materials' => true, 'documents' => true, 'reports' => true,
                'client_portal' => true, 'integrations' => true, 'zatca_phase2' => true, 'leads' => true,
                'compliance' => true, 'change_orders' => true, 'bank_guarantees' => true, 'purchase_orders' => true, 'recurring_invoices' => true, 'project_photos' => true, 'quick_estimate' => true,
                'ai_estimate_generator' => true, 'estimate_templates' => true, 'online_invoice_payments' => true,
                'priority_support' => true, 'site_logs' => true, 'punch_list' => true, 'zakat' => true, 'approval_workflow' => true, 'payment_certificates' => true, 'subcontractors' => true, 'ld_eot_tracking' => true,
            ],
            'enterprise' => [
                'takeoff' => true, 'suppliers' => true, 'materials' => true, 'documents' => true, 'reports' => true,
                'client_portal' => true, 'integrations' => true, 'zatca_phase2' => true, 'leads' => true,
                'compliance' => true, 'change_orders' => true, 'bank_guarantees' => true, 'purchase_orders' => true, 'recurring_invoices' => true, 'project_photos' => true, 'quick_estimate' => true,
                'ai_estimate_generator' => true, 'estimate_templates' => true, 'online_invoice_payments' => true,
                'priority_support' => true, 'site_logs' => true, 'punch_list' => true, 'zakat' => true, 'approval_workflow' => true, 'payment_certificates' => true, 'subcontractors' => true, 'ld_eot_tracking' => true,
            ],
        ];

        $plans = [
            [
                'slug' => 'starter', 'name' => 'Starter', 'name_ar' => 'أساسي',
                'tagline' => 'For small contractors getting organized',
                'tagline_ar' => 'للمقاولين الصغار الذين يريدون التنظيم',
                'price_monthly' => 199, 'price_yearly' => 1990, 'max_users' => 3, 'max_projects' => 10,
                'consultation_quota_monthly' => 1, 'sort_order' => 1,
                'features' => [
                    'Unlimited estimates & quotes', 'Up to 10 active projects', '3 team members',
                    'Client & invoice management', 'Standard support ticketing (email + panel)',
                    'ZATCA Phase 1 QR code invoicing',
                    'WhatsApp sharing for estimates & invoices', 'Client e-signature on estimates',
                    'Site photo diary per project',
                    'Daily site log & punch list tracking',
                    'Saudi tax invoice PDF layout',
                    '12 built-in estimate templates', 'Role-based team permissions',
                    'Leads pipeline', 'Business Setup (building types, tax rates & more)',
                ],
                'features_ar' => [
                    'تقديرات وعروض أسعار غير محدودة', 'حتى 10 مشاريع نشطة', '3 أعضاء فريق',
                    'إدارة العملاء والفواتير', 'تذاكر دعم قياسية (بريد إلكتروني + لوحة التحكم)',
                    'فوترة برمز الاستجابة السريعة (فاتورة المرحلة الأولى)',
                    'مشاركة عبر واتساب للعروض والفواتير', 'توقيع العميل الإلكتروني على العروض',
                    'سجل صور الموقع لكل مشروع',
                    'سجل الموقع اليومي وقائمة الملاحظات (Punch List)',
                    'تخطيط فاتورة ضريبية سعودية بصيغة PDF',
                    '12 قالب تقدير جاهز', 'صلاحيات فريق حسب الدور',
                    'مسار متابعة العملاء المحتملين', 'إعداد الأعمال (أنواع المباني، معدلات الضريبة والمزيد)',
                ],
            ],
            [
                'slug' => 'professional', 'name' => 'Professional', 'name_ar' => 'احترافي',
                'tagline' => 'For growing contracting businesses',
                'tagline_ar' => 'لشركات المقاولات المتنامية',
                'price_monthly' => 449, 'price_yearly' => 4490, 'max_users' => 15, 'max_projects' => 50,
                'consultation_quota_monthly' => 3, 'sort_order' => 2,
                'features' => [
                    'Everything in Starter', 'Up to 50 active projects', '15 team members',
                    'Job costing & budget tracking', 'Visual project scheduling (Gantt)',
                    'Change orders & retention tracking', 'Digital takeoff & AI estimate generator',
                    'Recurring invoices for retainers & maintenance contracts',
                    'Client portal with online payments', 'Priority support (faster ticket response)',
                    'REST API & webhooks', 'ZATCA Phase 1 + Phase 2 (Fatoora) e-invoicing',
                ],
                'features_ar' => [
                    'كل ما في باقة أساسي', 'حتى 50 مشروعًا نشطًا', '15 عضو فريق',
                    'تتبع تكاليف المشروع والميزانية', 'جدولة مشاريع مرئية (مخطط جانت)',
                    'أوامر التغيير وتتبع الاحتجاز', 'القياس الرقمي ومولّد التقديرات بالذكاء الاصطناعي',
                    'فواتير متكررة لعقود الصيانة والاحتجاز الدوري',
                    'بوابة عميل مع الدفع الإلكتروني', 'دعم ذو أولوية (استجابة أسرع للتذاكر)',
                    'واجهة برمجة REST وWebhooks', 'فوترة فاتورة المرحلة الأولى والثانية',
                ],
            ],
            [
                'slug' => 'enterprise', 'name' => 'Enterprise', 'name_ar' => 'المؤسسات',
                'tagline' => 'For large contractors & developers',
                'tagline_ar' => 'للمقاولين والمطورين الكبار',
                'price_monthly' => 899, 'price_yearly' => 8990, 'max_users' => 999, 'max_projects' => 999,
                'consultation_quota_monthly' => 5, 'sort_order' => 3,
                'features' => [
                    'Everything in Professional', 'Unlimited projects & users', 'Multi-branch support',
                    'Dedicated account manager', 'Priority support with faster SLA', 'Custom onboarding',
                    'ZATCA Phase 1 + Phase 2 (Fatoora) e-invoicing, fully managed',
                ],
                'features_ar' => [
                    'كل ما في باقة احترافي', 'مشاريع ومستخدمون غير محدودين', 'دعم متعدد الفروع',
                    'مدير حساب مخصص', 'دعم ذو أولوية باتفاقية مستوى خدمة أسرع', 'تهيئة مخصصة عند الانضمام',
                    'فوترة فاتورة المرحلة الأولى والثانية، بإدارة كاملة',
                ],
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'name' => $p['name'],
                    'name_ar' => $p['name_ar'],
                    'tagline' => $p['tagline'],
                    'tagline_ar' => $p['tagline_ar'],
                    'price_monthly' => $p['price_monthly'],
                    'price_yearly' => $p['price_yearly'],
                    'currency' => 'SAR',
                    'max_users' => $p['max_users'],
                    'max_projects' => $p['max_projects'],
                    'consultation_quota_monthly' => $p['consultation_quota_monthly'],
                    'features' => json_encode($p['features']),
                    'features_ar' => json_encode($p['features_ar']),
                    'feature_flags' => json_encode($featureFlagsByPlan[$p['slug']]),
                    'is_active' => true,
                    'sort_order' => $p['sort_order'],
                ]
            );
        }

        $this->command?->info('Plans seeded.');
    }
}
