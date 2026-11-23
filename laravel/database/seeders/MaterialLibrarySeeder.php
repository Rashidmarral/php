<?php

namespace Database\Seeders;

use App\Models\MaterialLibraryItem;
use Illuminate\Database\Seeder;

class MaterialLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $items = require database_path('data/material_library_items.php');

        // updateOrCreate (keyed on sku, which every data-file row has) instead of a
        // one-time "already seeded, skip" guard: an install that was seeded before new
        // items were added to the data file must still pick them up on `db:seed --force`,
        // the same silent-skip bug class fixed in DemoCompanySeeder's backfill method.
        foreach ($items as $index => $item) {
            MaterialLibraryItem::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'name_ar' => $item['name_ar'] ?? null,
                    'category' => $item['category'],
                    'unit' => $item['unit'],
                    'material_cost' => $item['material_cost'],
                    'labor_cost' => $item['labor_cost'] ?? 0,
                    'notes' => $item['notes'] ?? null,
                    'is_active' => true,
                    'sort_order' => $index,
                ]
            );
        }

        $this->command?->info(count($items).' material library items seeded/updated.');
    }
}
