<?php

namespace Database\Seeders;

use App\Models\CostCode;
use Illuminate\Database\Seeder;

/**
 * Seeds the platform-wide (company_id = null) CSI-MasterFormat-style cost
 * code hierarchy — see database/data/cost_codes.php for the actual division
 * list. Purely additive reference data: a company that never assigns one of
 * these codes to an EstimateItem/BoqItem/VendorBill sees zero change
 * anywhere (see CostCode's and ReportController::costByCode()'s docblocks).
 */
class CostCodeSeeder extends Seeder
{
    public function run(): void
    {
        if (CostCode::whereNull('company_id')->count() > 0) {
            $this->command?->info('Platform cost codes already seeded, skipping.');

            return;
        }

        $divisions = require database_path('data/cost_codes.php');

        $sortOrder = 0;
        foreach ($divisions as $division) {
            $parent = CostCode::create([
                'company_id' => null,
                'code' => $division['code'],
                'description' => $division['description'],
                'description_ar' => $division['description_ar'] ?? null,
                'parent_id' => null,
                'level' => 1,
                'sort_order' => $sortOrder++,
            ]);

            $childSort = 0;
            foreach ($division['children'] ?? [] as $child) {
                CostCode::create([
                    'company_id' => null,
                    'code' => $child['code'],
                    'description' => $child['description'],
                    'description_ar' => $child['description_ar'] ?? null,
                    'parent_id' => $parent->id,
                    'level' => 2,
                    'sort_order' => $childSort++,
                ]);
            }
        }

        $this->command?->info('Cost code hierarchy seeded.');
    }
}
