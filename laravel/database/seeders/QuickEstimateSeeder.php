<?php

namespace Database\Seeders;

use App\Models\QuickEstimateAddon;
use App\Models\QuickEstimateFoundation;
use App\Models\QuickEstimateQualityTier;
use App\Models\QuickEstimateRegion;
use Illuminate\Database\Seeder;

class QuickEstimateSeeder extends Seeder
{
    public function run(): void
    {
        if (QuickEstimateRegion::count() === 0) {
            $regions = [
                ['Riyadh (Central Region)', 'الرياض (المنطقة الوسطى)', 100, 1.00, 1],
                ['Jeddah (Western Region)', 'جدة (المنطقة الغربية)', 112, 1.12, 2],
                ['Dammam (Eastern Region)', 'الدمام (المنطقة الشرقية)', 92, 0.92, 3],
                ['Makkah (Holy City)', 'مكة المكرمة (المدينة المقدسة)', 108, 1.08, 4],
                ['Madinah (Holy City)', 'المدينة المنورة (المدينة المقدسة)', 105, 1.05, 5],
            ];
            foreach ($regions as [$en, $ar, $price, $mult, $sort]) {
                QuickEstimateRegion::create([
                    'name_en' => $en, 'name_ar' => $ar, 'price_per_sqm' => $price,
                    'multiplier' => $mult, 'sort_order' => $sort, 'is_active' => true,
                ]);
            }
        }

        if (QuickEstimateFoundation::count() === 0) {
            $foundations = [
                ['Regular Foundation', 'أساسات عادية', 'Standard reinforced concrete', 'خرسانة مسلحة قياسية', 550, 1],
                ['Raft Foundation', 'أساسات حصيرة', 'Reinforced concrete raft', 'حصيرة خرسانية مسلحة', 700, 2],
            ];
            foreach ($foundations as [$en, $ar, $descEn, $descAr, $price, $sort]) {
                QuickEstimateFoundation::create([
                    'name_en' => $en, 'name_ar' => $ar, 'description_en' => $descEn, 'description_ar' => $descAr,
                    'price_per_sqm' => $price, 'sort_order' => $sort, 'is_active' => true,
                ]);
            }
        }

        if (QuickEstimateAddon::count() === 0) {
            // qty_mode: 'area' = price is per m² and auto-multiplies by the total area entered.
            // 'manual' = price is per ton/unit/etc — the user types in how many are needed.
            $addons = [
                ['Water Tank', 'خزان مياه', 'Water storage tank with fittings', 'خزان مياه مع التوصيلات', 40, 'ton', 'manual', false, 1],
                ['Fencing', 'سياج', 'Perimeter fencing', 'سياج محيطي', 143, 'sqm', 'area', false, 2],
                ['Guard Room', 'غرفة حارس', 'Security guard room with basic finishing', 'غرفة حارس مع تشطيب أساسي', 30, 'sqm', 'area', false, 3],
                ['Sewage Tank', 'خزان صرف صحي', 'Sewage tank with connections', 'خزان صرف صحي مع التوصيلات', 35, 'sqm', 'area', false, 4],
                ['Interior Paint', 'دهان داخلي وخارجي', 'Full interior and exterior painting', 'دهان داخلي وخارجي كامل', 90, 'sqm', 'area', false, 5],
                ['Landscaping', 'لياسة', 'Interior and exterior finishing', 'تشطيب داخلي وخارجي', 73, 'sqm', 'area', false, 6],
                ['Plumbing Works', 'أعمال صحية', 'Complete plumbing works', 'أعمال صحية كاملة', 132, 'sqm', 'area', false, 7],
                ['Electrical Works', 'أعمال كهربائية', 'Complete electrical works', 'تمديدات كهربائية كاملة', 135, 'sqm', 'area', false, 8],
                ['Aluminum Works', 'أعمال ألمنيوم', 'Windows, doors and railings', 'نوافذ وأبواب ودرابزين', 60, 'sqm', 'area', false, 9],
                ['Gypsum Ceiling', 'تشطيب الأسقف', 'Suspended gypsum ceiling', 'أسقف جبسية معلقة', 85, 'sqm', 'area', false, 10],
                ['Roof Insulation', 'عزل السطح', 'Waterproofing and thermal insulation', 'عزل مائي وحراري', 43, 'sqm', 'area', false, 11],
                ['WPC Cladding', 'أبواب WPC', 'Weather-resistant WPC cladding', 'كسوة WPC مقاومة للعوامل الجوية', 50, 'sqm', 'area', false, 12],
                ['Site Survey', 'مسح', 'Topographic site survey', 'مسح طبوغرافي للموقع', 90, 'sqm', 'area', true, 13],
                ['Central AC System', 'تكييف مركزي', 'Central air conditioning ductwork', 'نظام تكييف مركزي بالدكت', 800, 'sqm', 'area', true, 14],
                ['Swimming Pool', 'مسبح', 'Standard residential swimming pool', 'مسبح سكني قياسي', 380, 'unit', 'manual', true, 15],
            ];
            foreach ($addons as [$en, $ar, $descEn, $descAr, $price, $unitType, $qtyMode, $isPro, $sort]) {
                QuickEstimateAddon::create([
                    'name_en' => $en, 'name_ar' => $ar, 'description_en' => $descEn, 'description_ar' => $descAr,
                    'unit_price' => $price, 'unit_type' => $unitType, 'qty_mode' => $qtyMode, 'is_pro' => $isPro,
                    'sort_order' => $sort, 'is_active' => true,
                ]);
            }
        }

        if (QuickEstimateQualityTier::count() === 0) {
            // Finish/quality tier — a second multiplier applied alongside the region's, letting a visitor
            // ballpark the cost swing between a bare-bones build and a fully finished one.
            $qualityTiers = [
                ['Economy', 'اقتصادي', 0.85, 1],
                ['Standard', 'قياسي', 1.00, 2],
                ['Premium', 'مميز', 1.30, 3],
            ];
            foreach ($qualityTiers as [$en, $ar, $mult, $sort]) {
                QuickEstimateQualityTier::create([
                    'name_en' => $en, 'name_ar' => $ar,
                    'multiplier' => $mult, 'sort_order' => $sort, 'is_active' => true,
                ]);
            }
        }

        $this->command?->info('Quick estimate calculator data seeded.');
    }
}
