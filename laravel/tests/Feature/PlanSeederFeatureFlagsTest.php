<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Support\Feature;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Proves PlanSeeder actually seeds the 4 newly-added Feature::ALL keys
 * (cash_flow_forecasting, activity_log, multi_currency, granular_permissions) on each of
 * the 3 real plans, tiered exactly like every other key: off on Starter, on for
 * Professional and Enterprise — matching rfq_quotes's own tiering. Wrapped in
 * DatabaseTransactions so running the real seeder here never leaves stray plan rows
 * behind in the dev sqlite database.
 */
class PlanSeederFeatureFlagsTest extends TestCase
{
    use DatabaseTransactions;

    private const NEW_KEYS = ['cash_flow_forecasting', 'activity_log', 'multi_currency', 'granular_permissions'];

    public function test_new_feature_keys_are_present_in_feature_all(): void
    {
        foreach (self::NEW_KEYS as $key) {
            $this->assertArrayHasKey($key, Feature::ALL, "{$key} must be registered in Feature::ALL");
        }
    }

    public function test_plan_seeder_tiers_the_new_keys_exactly_like_rfq_quotes(): void
    {
        (new PlanSeeder)->run();

        $starter = Plan::where('slug', 'starter')->first();
        $professional = Plan::where('slug', 'professional')->first();
        $enterprise = Plan::where('slug', 'enterprise')->first();
        $this->assertNotNull($starter);
        $this->assertNotNull($professional);
        $this->assertNotNull($enterprise);

        $starterFlags = json_decode((string) $starter->feature_flags, true);
        $proFlags = json_decode((string) $professional->feature_flags, true);
        $entFlags = json_decode((string) $enterprise->feature_flags, true);

        foreach (self::NEW_KEYS as $key) {
            $this->assertArrayHasKey($key, $starterFlags, "{$key} must be seeded on Starter");
            $this->assertArrayHasKey($key, $proFlags, "{$key} must be seeded on Professional");
            $this->assertArrayHasKey($key, $entFlags, "{$key} must be seeded on Enterprise");

            $this->assertFalse($starterFlags[$key], "{$key} must be false on Starter, same tiering as rfq_quotes");
            $this->assertTrue($proFlags[$key], "{$key} must be true on Professional, same tiering as rfq_quotes");
            $this->assertTrue($entFlags[$key], "{$key} must be true on Enterprise, same tiering as rfq_quotes");

            // Same tiering shape as the already-confirmed rfq_quotes key, on every plan.
            $this->assertSame($starterFlags['rfq_quotes'], $starterFlags[$key]);
            $this->assertSame($proFlags['rfq_quotes'], $proFlags[$key]);
            $this->assertSame($entFlags['rfq_quotes'], $entFlags[$key]);
        }
    }
}
