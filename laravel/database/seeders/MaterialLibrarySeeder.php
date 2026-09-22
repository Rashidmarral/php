<?php

namespace Database\Seeders;

use App\Models\MaterialLibraryItem;
use Illuminate\Database\Seeder;

class MaterialLibrarySeeder extends Seeder
{
    public function run(): void
    {
        if (MaterialLibraryItem::count() > 0) {
            $this->command?->info('Material library already seeded, skipping.');
            return;
        }

        $items = require database_path('data/material_library_items.php');

        foreach ($items as $index => $item) {
            MaterialLibraryItem::create([
                'sku' => $item['sku'] ?? null,
                'name' => $item['name'],
                'name_ar' => $item['name_ar'] ?? null,
                'category' => $item['category'],
                'unit' => $item['unit'],
                'material_cost' => $item['material_cost'],
                'labor_cost' => $item['labor_cost'] ?? 0,
                'notes' => $item['notes'] ?? null,
                'is_active' => true,
                'sort_order' => $index,
            ]);
        }

        $this->command?->info(count($items) . ' material library items seeded.');
    }
}
