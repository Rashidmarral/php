<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\QuickEstimate;
use App\Models\QuickEstimateAddon;
use App\Models\QuickEstimateFoundation;
use App\Models\QuickEstimateRegion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric validation added to QuickEstimateController::store()/update()
 * — total_area and discount_percent previously only had max()/min() clamping (no
 * rejection of non-numeric input, and no upper bound on total_area at all), and
 * addon_qty[] was floatval()'d with no bounds whatsoever. A crafted huge
 * total_area/addon quantity could overflow quick_estimates' decimal(10,2)/
 * decimal(12,2) columns and throw a database error instead of a clean
 * validation message — same spirit as EstimateNumericValidationTest, applied
 * to the Quick Estimate calculator.
 *
 * Wrapped in DatabaseTransactions, matching ApprovalChainTest's own convention,
 * so none of this touches the real dev sqlite database.
 */
class QuickEstimateNumericValidationTest extends TestCase
{
    use DatabaseTransactions;

    /** See ActivityLogTest's own docblock on why this reset is needed between tests that touch plan feature flags. */
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\App\Support\Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompanyWithOwner(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-qe-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "QE Plan {$tag}",
            'feature_flags' => json_encode(['quick_estimate' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Holding",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag) . '-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        return compact('company', 'owner');
    }

    private function makeRegionAndFoundation(string $tag): array
    {
        $region = QuickEstimateRegion::create([
            'name_en' => "Region {$tag}",
            'name_ar' => "منطقة {$tag}",
            'price_per_sqm' => 1500,
            'multiplier' => 1,
            'is_active' => true,
        ]);
        $foundation = QuickEstimateFoundation::create([
            'name_en' => "Foundation {$tag}",
            'name_ar' => "أساس {$tag}",
            'price_per_sqm' => 200,
            'is_active' => true,
        ]);

        return compact('region', 'foundation');
    }

    private function basePayload(array $rf): array
    {
        return [
            'region_id' => $rf['region']->id,
            'foundation_id' => $rf['foundation']->id,
            'total_area' => 200,
        ];
    }

    public function test_store_rejects_total_area_above_max_bound(): void
    {
        $a = $this->makeCompanyWithOwner('AreaMax');
        $rf = $this->makeRegionAndFoundation('AreaMax');

        $response = $this->actingAs($a['owner'])->post('/app/quick-estimate', [
            ...$this->basePayload($rf),
            'total_area' => '99999999999',
        ]);

        $response->assertRedirect('/app/quick-estimate');
        $this->assertSame(0, QuickEstimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_non_numeric_total_area(): void
    {
        $a = $this->makeCompanyWithOwner('AreaNaN');
        $rf = $this->makeRegionAndFoundation('AreaNaN');

        $response = $this->actingAs($a['owner'])->post('/app/quick-estimate', [
            ...$this->basePayload($rf),
            'total_area' => 'not-a-number',
        ]);

        $response->assertRedirect('/app/quick-estimate');
        $this->assertSame(0, QuickEstimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_discount_percent_above_100(): void
    {
        $a = $this->makeCompanyWithOwner('DiscountHigh');
        $rf = $this->makeRegionAndFoundation('DiscountHigh');

        $response = $this->actingAs($a['owner'])->post('/app/quick-estimate', [
            ...$this->basePayload($rf),
            'discount_percent' => '250',
        ]);

        $response->assertRedirect('/app/quick-estimate');
        $this->assertSame(0, QuickEstimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_discount_percent_as_zero(): void
    {
        $a = $this->makeCompanyWithOwner('DiscountBlank');
        $rf = $this->makeRegionAndFoundation('DiscountBlank');

        $response = $this->actingAs($a['owner'])->post('/app/quick-estimate', [
            ...$this->basePayload($rf),
            'discount_percent' => '',
        ]);

        $response->assertRedirect();
        $estimate = QuickEstimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $this->assertSame('0.00', $estimate->discount_percent);
    }

    public function test_store_rejects_non_numeric_addon_qty_for_a_selected_addon(): void
    {
        $a = $this->makeCompanyWithOwner('AddonNaN');
        $rf = $this->makeRegionAndFoundation('AddonNaN');
        $addon = QuickEstimateAddon::create([
            'name_en' => 'Swimming pool',
            'name_ar' => 'حمام سباحة',
            'unit_price' => 500,
            'unit_type' => 'unit',
            'qty_mode' => 'manual',
            'is_active' => true,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/quick-estimate', [
            ...$this->basePayload($rf),
            'addons' => [$addon->id],
            'addon_qty' => [$addon->id => 'not-a-number'],
        ]);

        $response->assertRedirect('/app/quick-estimate');
        $this->assertSame(0, QuickEstimate::where('company_id', $a['company']->id)->count());
    }

    /**
     * An unchecked add-on's quantity input is still submitted by the form (the
     * field stays in the DOM whether the checkbox is ticked or not), so a stray
     * value left in it must never block the whole request — only SELECTED
     * add-ons' quantities are validated.
     */
    public function test_store_ignores_garbage_addon_qty_for_an_unselected_addon(): void
    {
        $a = $this->makeCompanyWithOwner('AddonUnselected');
        $rf = $this->makeRegionAndFoundation('AddonUnselected');
        $addon = QuickEstimateAddon::create([
            'name_en' => 'Swimming pool',
            'name_ar' => 'حمام سباحة',
            'unit_price' => 500,
            'unit_type' => 'unit',
            'qty_mode' => 'manual',
            'is_active' => true,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/quick-estimate', [
            ...$this->basePayload($rf),
            'addons' => [],
            'addon_qty' => [$addon->id => 'not-a-number'],
        ]);

        $response->assertRedirect();
        $this->assertSame(1, QuickEstimate::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_a_valid_payload_and_computes_totals(): void
    {
        $a = $this->makeCompanyWithOwner('Happy');
        $rf = $this->makeRegionAndFoundation('Happy');

        $response = $this->actingAs($a['owner'])->post('/app/quick-estimate', [
            ...$this->basePayload($rf),
            'discount_percent' => '10',
        ]);

        $response->assertRedirect();
        $estimate = QuickEstimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $this->assertSame('200.00', $estimate->total_area);
        $this->assertSame('10.00', $estimate->discount_percent);
    }
}
