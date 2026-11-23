<?php

namespace Database\Seeders;

use App\Models\Tender;
use Illuminate\Database\Seeder;

/**
 * Sample rows for the admin-curated Tenders board (see Tender model / TenderAdminController).
 * Real Saudi government bodies and giga-projects are named for plausibility; deadlines are
 * relative to seed time so the "open vs. closed" UI always has something to show for both.
 * source_url only points at a portal/domain we're confident is genuinely that entity's own
 * (Etimad for government notices, each giga-project's official site) — never a fabricated link.
 */
class TenderSeeder extends Seeder
{
    public function run(): void
    {
        if (Tender::count() > 0) {
            $this->command?->info('Tenders already seeded, skipping.');
            return;
        }

        $etimad = 'https://tenders.etimad.sa';

        $tenders = [
            [
                'title_en' => 'Construction and Rehabilitation of Secondary Roads Network – Eastern Province',
                'title_ar' => 'إنشاء وتأهيل شبكة الطرق الفرعية – المنطقة الشرقية',
                'description_en' => 'Design, construction, and rehabilitation of secondary road networks including asphalt paving, drainage, and street lighting across several districts of the Eastern Province.',
                'description_ar' => 'تصميم وإنشاء وتأهيل شبكات الطرق الفرعية بما في ذلك رصف الأسفلت والصرف والإنارة في عدة أحياء بالمنطقة الشرقية.',
                'entity_name_en' => 'Ministry of Municipal, Rural Affairs and Housing',
                'entity_name_ar' => 'وزارة الشؤون البلدية والقروية والإسكان',
                'category' => 'government',
                'estimated_value_sar' => 12500000,
                'submission_deadline' => now()->addDays(18)->format('Y-m-d'),
                'location_city' => 'Dammam',
                'source_url' => $etimad,
            ],
            [
                'title_en' => 'Design-Build of New Secondary Schools – Makkah Region (Package 4)',
                'title_ar' => 'تصميم وتنفيذ مدارس ثانوية جديدة – منطقة مكة المكرمة (الحزمة 4)',
                'description_en' => 'Turnkey design and construction of five new secondary school campuses, including furnishing and external works, under the ministry\'s school expansion program.',
                'description_ar' => 'تصميم وتنفيذ متكامل لخمس مدارس ثانوية جديدة، شاملاً التأثيث والأعمال الخارجية، ضمن برنامج التوسع المدرسي للوزارة.',
                'entity_name_en' => 'Ministry of Education',
                'entity_name_ar' => 'وزارة التعليم',
                'category' => 'government',
                'estimated_value_sar' => 45000000,
                'submission_deadline' => now()->addDays(35)->format('Y-m-d'),
                'location_city' => 'Makkah',
                'source_url' => $etimad,
            ],
            [
                'title_en' => 'Wastewater Network Expansion and Pump Station Upgrade – Al Ahsa',
                'title_ar' => 'توسعة شبكة الصرف الصحي وتطوير محطات الضخ – الأحساء',
                'description_en' => 'Expansion of the wastewater collection network and upgrade of two existing pump stations to serve newly developed residential districts.',
                'description_ar' => 'توسعة شبكة تجميع الصرف الصحي وتطوير محطتي ضخ قائمتين لخدمة الأحياء السكنية المطورة حديثًا.',
                'entity_name_en' => 'National Water Company',
                'entity_name_ar' => 'شركة المياه الوطنية',
                'category' => 'government',
                'estimated_value_sar' => 28000000,
                'submission_deadline' => now()->addDays(9)->format('Y-m-d'),
                'location_city' => 'Al Ahsa',
                'source_url' => $etimad,
            ],
            [
                'title_en' => 'Supply and Installation of 132kV Substation Equipment – Qassim',
                'title_ar' => 'توريد وتركيب معدات محطة تحويل 132 ك.ف – القصيم',
                'description_en' => 'Supply, installation, and commissioning of transformers, switchgear, and protection systems for a new 132kV substation.',
                'description_ar' => 'توريد وتركيب وتشغيل المحولات ولوحات التحويل وأنظمة الحماية لمحطة تحويل جديدة بجهد 132 ك.ف.',
                'entity_name_en' => 'Saudi Electricity Company',
                'entity_name_ar' => 'الشركة السعودية للكهرباء',
                'category' => 'government',
                'estimated_value_sar' => 60000000,
                // Deliberately in the past — seeds one closed/expired tender so the
                // open-vs-closed UI states are both exercised out of the box.
                'submission_deadline' => now()->subDays(5)->format('Y-m-d'),
                'location_city' => 'Buraidah',
                'source_url' => $etimad,
            ],
            [
                'title_en' => 'Earthworks and Site Preparation Package – THE LINE Module 3',
                'title_ar' => 'أعمال الحفر وتجهيز الموقع – الوحدة 3 من ذا لاين',
                'description_en' => 'Bulk earthworks, temporary access roads, and site preparation ahead of vertical construction for a designated module of THE LINE.',
                'description_ar' => 'أعمال الحفر الكبرى وطرق الوصول المؤقتة وتجهيز الموقع تمهيدًا لأعمال البناء الرأسي لإحدى وحدات ذا لاين.',
                'entity_name_en' => 'NEOM',
                'entity_name_ar' => 'نيوم',
                'category' => 'giga_project',
                'estimated_value_sar' => null,
                'submission_deadline' => now()->addDays(45)->format('Y-m-d'),
                'location_city' => 'Tabuk Province',
                'source_url' => 'https://www.neom.com',
            ],
            [
                'title_en' => 'Construction of Six Flags Qiddiya Supporting Infrastructure',
                'title_ar' => 'إنشاء البنية التحتية الداعمة لمشروع سِكس فلاجز القدية',
                'description_en' => 'Roads, utilities, and site infrastructure works supporting the Six Flags Qiddiya theme park development.',
                'description_ar' => 'أعمال الطرق والمرافق والبنية التحتية للموقع الداعمة لتطوير مدينة الملاهي سِكس فلاجز القدية.',
                'entity_name_en' => 'Qiddiya Investment Company',
                'entity_name_ar' => 'شركة القدية للاستثمار',
                'category' => 'giga_project',
                'estimated_value_sar' => 350000000,
                'submission_deadline' => now()->addDays(60)->format('Y-m-d'),
                'location_city' => 'Riyadh',
                'source_url' => 'https://www.qiddiya.com',
            ],
            [
                'title_en' => 'Heritage District Landscaping and Public Realm Works – Phase 2',
                'title_ar' => 'أعمال تنسيق الحدائق والفراغات العامة بالحي التراثي – المرحلة 2',
                'description_en' => 'Landscaping, hardscape, street furniture, and lighting for the public realm surrounding the At-Turaif heritage district.',
                'description_ar' => 'أعمال تنسيق الحدائق والأرصفة وأثاث الشوارع والإنارة للفراغات العامة المحيطة بحي الطريف التراثي.',
                'entity_name_en' => 'Diriyah Gate Development Authority',
                'entity_name_ar' => 'هيئة تطوير بوابة الدرعية',
                'category' => 'giga_project',
                'estimated_value_sar' => 90000000,
                'submission_deadline' => now()->addDays(25)->format('Y-m-d'),
                'location_city' => 'Diriyah, Riyadh',
                'source_url' => 'https://www.diriyah.sa',
            ],
            [
                'title_en' => 'Marine Works and Jetty Construction – Amaala Coastal Development',
                'title_ar' => 'الأعمال البحرية وإنشاء الأرصفة البحرية – مشروع أملا الساحلي',
                'description_en' => 'Marine construction including jetties, breakwaters, and dredging works to support the coastal resort development.',
                'description_ar' => 'أعمال بحرية تشمل الأرصفة وحواجز الأمواج وأعمال الجرف لدعم تطوير المنتجع الساحلي.',
                'entity_name_en' => 'Red Sea Global',
                'entity_name_ar' => 'ريد سي جلوبال',
                'category' => 'giga_project',
                'estimated_value_sar' => null,
                'submission_deadline' => now()->addDays(50)->format('Y-m-d'),
                'location_city' => 'Umluj, Tabuk',
                'source_url' => 'https://www.redseaglobal.com',
            ],
        ];

        foreach ($tenders as $index => $t) {
            Tender::create($t + ['is_active' => true, 'sort_order' => $index]);
        }

        $this->command?->info(count($tenders) . ' tenders seeded.');
    }
}
