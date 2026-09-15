<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reproduces the original app's fresh-install baseline (database/migrate.php)
     * plus its optional demo company (--seed-demo). Idempotent: safe to re-run.
     */
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            SettingSeeder::class,
            QuickEstimateSeeder::class,
            EstimateTemplateSeeder::class,
            MaterialLibrarySeeder::class,
            TenderSeeder::class,
            AdminUserSeeder::class,
            DemoCompanySeeder::class,
        ]);
    }
}
