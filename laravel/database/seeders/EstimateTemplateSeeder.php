<?php

namespace Database\Seeders;

use App\Models\EstimateTemplate;
use App\Models\EstimateTemplateItem;
use Illuminate\Database\Seeder;

class EstimateTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (EstimateTemplate::count() > 0) {
            $this->command?->info('Estimate templates already seeded, skipping.');
            return;
        }

        $templates = require database_path('data/estimate_templates.php');

        foreach ($templates as $index => $tpl) {
            $template = EstimateTemplate::create([
                'name_en' => $tpl['name_en'], 'name_ar' => $tpl['name_ar'],
                'description_en' => $tpl['description_en'], 'description_ar' => $tpl['description_ar'],
                'building_type' => $tpl['building_type'], 'icon' => $tpl['icon'],
                'is_active' => true, 'sort_order' => $index,
            ]);

            $itemSort = 0;
            foreach ($tpl['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    EstimateTemplateItem::create([
                        'template_id' => $template->id,
                        'section_number' => $section['no'],
                        'section_title_en' => $section['title_en'],
                        'section_title_ar' => $section['title_ar'],
                        'item_number' => $item['no'],
                        'description_en' => $item['en'],
                        'description_ar' => $item['ar'],
                        'item_type' => $item['type'],
                        'default_qty' => $item['qty'],
                        'uom' => $item['uom'],
                        'unit_cost' => $item['cost'],
                        'sort_order' => $itemSort++,
                    ]);
                }
            }
        }

        $first = EstimateTemplate::where('is_active', true)->orderBy('sort_order')->first();
        $first?->update(['is_default_choice' => true]);

        $this->command?->info(count($templates) . ' estimate templates seeded.');
    }
}
