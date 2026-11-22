<?php

namespace Tests\Feature;

use App\Models\AsphaltEstimate;
use App\Models\AsphaltEstimateSegment;
use App\Models\Company;
use App\Models\CostCode;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Plan;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the Asphalt / Road Estimate module end to end via the app's real
 * HTTP routes (same style as QuickEstimateNumericValidationTest/
 * CostCodeHierarchyTest), not just direct model assertions. Wrapped in
 * DatabaseTransactions so none of this touches the real dev sqlite database
 * beyond the test — the platform-wide "32 10 00" cost code is already
 * seeded there by CostCodeSeeder and is read-only reference data for every
 * test below.
 *
 * The two-segment worked example used for the totals test is the exact same
 * hand-worked numbers documented in tests/Unit/AsphaltEstimateCalcTest.php's
 * docblock (segments_subtotal=828,800, total_asphalt_tons=1996.8,
 * total_area_m2=9600, other_costs_subtotal=120,952, subtotal=949,752,
 * markup_amount=94,975.20, tax_amount=156,709.08, total=1,201,436.28).
 */
class AsphaltEstimateTest extends TestCase
{
    use DatabaseTransactions;

    /** See CostCodeHierarchyTest's own docblock on why this reset is needed between tests that touch plan feature flags. */
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompanyWithOwner(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-ae-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "AE Plan {$tag}",
            'feature_flags' => json_encode(['asphalt_estimate' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Paving",
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

    /** The Main Road / Cross Street 1 worked example — see this class's own docblock and AsphaltEstimateCalcTest's. */
    private function twoSegmentPayload(): array
    {
        return [
            'name' => 'Subdivision Phase 1 Roads',
            'segment_name' => ['Main Road', 'Cross Street 1'],
            'segment_length_m' => [1000, 200],
            'segment_width_m' => [10, 8],
            'segment_median_width_m' => [2, 0],
            'segment_subbase_thickness_mm' => [150, 100],
            'segment_subbase_unit_cost_per_m3' => [80, 80],
            'segment_base_course_thickness_mm' => [200, 150],
            'segment_base_course_unit_cost_per_m3' => [120, 120],
            'segment_asphalt_binder_thickness_mm' => [50, 40],
            'segment_asphalt_wearing_thickness_mm' => [40, 30],
            'segment_asphalt_density_kg_per_m3' => [2400, 2400],
            'segment_asphalt_unit_cost_per_ton' => [250, 250],
            'transport_cost_per_ton' => 15,
            'paver_daily_rate' => 2000,
            'paver_days' => 10,
            'roller_daily_rate' => 1500,
            'roller_days' => 10,
            'labor_crew_size' => 20,
            'labor_days' => 10,
            'labor_daily_rate_per_worker' => 150,
            'labor_food_daily_rate_per_worker' => 40,
            'surveyor_fee' => 5000,
            'lab_testing_fee' => 3000,
            'mobilization_fee' => 10000,
            'markup_percent' => 10,
        ];
    }

    public function test_store_computes_segment_and_overall_totals_matching_hand_worked_example(): void
    {
        $a = $this->makeCompanyWithOwner('Totals');
        $taxRate = \App\Models\TaxRate::create([
            'company_id' => $a['company']->id,
            'name' => 'VAT',
            'rate_percent' => 15,
            'is_default' => true,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/asphalt-estimates', [
            ...$this->twoSegmentPayload(),
            'tax_rate_id' => $taxRate->id,
        ]);

        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $response->assertRedirect('/app/asphalt-estimates/' . $estimate->id);

        $segments = AsphaltEstimateSegment::where('asphalt_estimate_id', $estimate->id)->orderBy('id')->get();
        $this->assertCount(2, $segments);

        $mainRoad = $segments[0];
        $this->assertSame('8000.00', $mainRoad->area_m2);
        $this->assertSame('1728.00', $mainRoad->asphalt_tons);
        $this->assertSame('720000.00', $mainRoad->segment_total);

        $crossStreet = $segments[1];
        $this->assertSame('1600.00', $crossStreet->area_m2);
        $this->assertSame('268.80', $crossStreet->asphalt_tons);
        $this->assertSame('108800.00', $crossStreet->segment_total);

        $this->assertSame('9600.00', $estimate->total_area_m2);
        $this->assertSame('1996.80', $estimate->total_asphalt_tons);
        $this->assertSame('828800.00', $estimate->segments_subtotal);
        $this->assertSame('120952.00', $estimate->other_costs_subtotal);
        $this->assertSame('949752.00', $estimate->subtotal);
        $this->assertSame('94975.20', $estimate->markup_amount);
        $this->assertSame('1201436.28', $estimate->total);
    }

    /** length_m/width_m must be > 0 for every named segment, or the whole request is rejected (same validation-redirect convention as EstimateController::store()). */
    public function test_store_rejects_a_segment_with_zero_length(): void
    {
        $a = $this->makeCompanyWithOwner('BadLength');
        $payload = $this->twoSegmentPayload();
        $payload['segment_length_m'][0] = 0;

        $response = $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $payload);

        $response->assertRedirect('/app/asphalt-estimates/create');
        $this->assertSame(0, AsphaltEstimate::where('company_id', $a['company']->id)->count());
    }

    /** A blank-named row is a placeholder from the repeatable-row UI, not a real segment, and must not block the request. */
    public function test_store_skips_rows_with_a_blank_segment_name(): void
    {
        $a = $this->makeCompanyWithOwner('BlankRow');
        $payload = $this->twoSegmentPayload();
        $payload['segment_name'][] = '';
        $payload['segment_length_m'][] = 0;
        $payload['segment_width_m'][] = 0;

        $response = $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $payload);

        $response->assertRedirect();
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $this->assertCount(2, AsphaltEstimateSegment::where('asphalt_estimate_id', $estimate->id)->get());
    }

    public function test_store_requires_at_least_one_segment(): void
    {
        $a = $this->makeCompanyWithOwner('NoSegments');

        $response = $this->actingAs($a['owner'])->post('/app/asphalt-estimates', ['name' => 'Empty Project']);

        $response->assertRedirect('/app/asphalt-estimates/create');
        $this->assertSame(0, AsphaltEstimate::where('company_id', $a['company']->id)->count());
    }

    /** A company whose plan lacks the feature is redirected to Billing rather than allowed through. */
    public function test_index_redirects_when_feature_not_in_plan(): void
    {
        $plan = Plan::create([
            'slug' => 'plan-ae-nofeature-' . bin2hex(random_bytes(4)),
            'name' => 'No Asphalt Plan',
            'feature_flags' => json_encode(['asphalt_estimate' => false]),
        ]);
        $company = Company::create(['name' => 'No Feature Co', 'email' => 'nofeature@example.com', 'plan_id' => $plan->id]);
        $owner = User::create([
            'company_id' => $company->id, 'name' => 'Owner', 'email' => 'nofeature-owner@example.com',
            'password' => Hash::make('password'), 'role' => 'owner', 'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->get('/app/asphalt-estimates');

        $response->assertRedirect('/app/billing');
    }

    /** A segment with a zero-thickness layer produces zero cost for that layer and no corresponding EstimateItem line on conversion. */
    public function test_zero_thickness_layer_is_skipped_on_conversion(): void
    {
        $a = $this->makeCompanyWithOwner('ZeroLayer');
        $payload = $this->twoSegmentPayload();
        // "Entrance Road" has no base course at all — a legitimate real-world input.
        $payload['segment_base_course_thickness_mm'] = [0, 0];

        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $payload);
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();

        $this->actingAs($a['owner'])->post('/app/asphalt-estimates/' . $estimate->id . '/convert');
        $newEstimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($newEstimate);

        $descriptions = EstimateItem::where('estimate_id', $newEstimate->id)->pluck('description');
        foreach ($descriptions as $description) {
            $this->assertStringNotContainsString('Base Course', $description);
        }
        // Subbase and asphalt lines for both segments are still present.
        $this->assertSame(4, $descriptions->filter(fn ($d) => str_contains($d, 'Subbase') || str_contains($d, 'Asphalt ('))->count());
    }

    public function test_convert_to_estimate_produces_correctly_coded_items_and_matching_total(): void
    {
        $a = $this->makeCompanyWithOwner('Convert');
        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $this->twoSegmentPayload());
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();

        $response = $this->actingAs($a['owner'])->post('/app/asphalt-estimates/' . $estimate->id . '/convert');

        $newEstimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($newEstimate);
        $response->assertRedirect('/app/estimates/' . $newEstimate->id);
        $this->assertSame($estimate->total, $newEstimate->total);
        $this->assertSame($estimate->subtotal, $newEstimate->subtotal);

        $items = EstimateItem::where('estimate_id', $newEstimate->id)->get();
        // 3 layers * 2 segments (all non-zero) + 8 non-zero other-cost lines = 14.
        $this->assertCount(14, $items);

        $pavingCode = CostCode::where('company_id', null)->where('code', '32 10 00')->first();
        $this->assertNotNull($pavingCode);
        foreach ($items as $item) {
            $this->assertSame($pavingCode->id, $item->cost_code_id);
        }

        // item_type must reflect what each line actually is, not the 'material' column
        // default — ReportController's Estimated-vs-Actual breakdown groups by this column,
        // so a labor/equipment/fee line left uncategorized would misreport there.
        $byDescription = $items->keyBy('description');
        $this->assertSame('labor', $byDescription->first(fn ($i, $d) => str_contains($d, 'Labor') && !str_contains($d, 'Food'))->item_type);
        $this->assertSame('labor', $byDescription->first(fn ($i, $d) => str_contains($d, 'Food'))->item_type);
        $this->assertSame('equipment', $byDescription->first(fn ($i, $d) => str_contains($d, 'Transport'))->item_type);
        $this->assertSame('equipment', $byDescription->first(fn ($i, $d) => str_contains($d, 'Paver'))->item_type);
        $this->assertSame('equipment', $byDescription->first(fn ($i, $d) => str_contains($d, 'Roller'))->item_type);
        $this->assertSame('other', $byDescription->first(fn ($i, $d) => str_contains($d, 'Surveyor'))->item_type);
        $this->assertSame('other', $byDescription->first(fn ($i, $d) => str_contains($d, 'Laboratory'))->item_type);
        $this->assertSame('other', $byDescription->first(fn ($i, $d) => str_contains($d, 'Mobilization'))->item_type);
        $this->assertSame('material', $byDescription->first(fn ($i, $d) => str_contains($d, 'Subbase'))->item_type);
    }

    /** A user from Company B must never view, edit, delete or convert an asphalt estimate owned by Company A. */
    public function test_cross_company_user_cannot_access_another_companys_asphalt_estimate(): void
    {
        $a = $this->makeCompanyWithOwner('TenantA');
        $b = $this->makeCompanyWithOwner('TenantB');
        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $this->twoSegmentPayload());
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();

        $this->actingAs($b['owner'])->get('/app/asphalt-estimates/' . $estimate->id)->assertNotFound();
        $this->actingAs($b['owner'])->get('/app/asphalt-estimates/' . $estimate->id . '/edit')->assertNotFound();
        $this->actingAs($b['owner'])->post('/app/asphalt-estimates/' . $estimate->id . '/update', $this->twoSegmentPayload())->assertNotFound();
        $this->actingAs($b['owner'])->post('/app/asphalt-estimates/' . $estimate->id . '/convert')->assertNotFound();
        $this->actingAs($b['owner'])->post('/app/asphalt-estimates/' . $estimate->id . '/delete')->assertNotFound();

        // None of those attempts touched the real row or leaked a converted Estimate into Company B.
        $this->assertNotNull(AsphaltEstimate::find($estimate->id));
        $this->assertSame(0, Estimate::where('company_id', $b['company']->id)->count());
    }

    public function test_update_replaces_segments_wholesale(): void
    {
        $a = $this->makeCompanyWithOwner('Update');
        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $this->twoSegmentPayload());
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();

        $payload = $this->twoSegmentPayload();
        $payload['segment_name'] = ['Main Road Only'];
        $payload['segment_length_m'] = [500];
        $payload['segment_width_m'] = [10];
        $payload['segment_median_width_m'] = [0];
        $payload['segment_subbase_thickness_mm'] = [100];
        $payload['segment_subbase_unit_cost_per_m3'] = [80];
        $payload['segment_base_course_thickness_mm'] = [0];
        $payload['segment_base_course_unit_cost_per_m3'] = [0];
        $payload['segment_asphalt_binder_thickness_mm'] = [50];
        $payload['segment_asphalt_wearing_thickness_mm'] = [0];
        $payload['segment_asphalt_density_kg_per_m3'] = [2400];
        $payload['segment_asphalt_unit_cost_per_ton'] = [250];

        $response = $this->actingAs($a['owner'])->post('/app/asphalt-estimates/' . $estimate->id . '/update', $payload);
        $response->assertRedirect('/app/asphalt-estimates/' . $estimate->id);

        $segments = AsphaltEstimateSegment::where('asphalt_estimate_id', $estimate->id)->get();
        $this->assertCount(1, $segments);
        $this->assertSame('Main Road Only', $segments[0]->name);
    }

    public function test_destroy_removes_the_estimate_and_its_segments(): void
    {
        $a = $this->makeCompanyWithOwner('Destroy');
        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $this->twoSegmentPayload());
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();

        $response = $this->actingAs($a['owner'])->post('/app/asphalt-estimates/' . $estimate->id . '/delete');

        $response->assertRedirect('/app/asphalt-estimates');
        $this->assertNull(AsphaltEstimate::find($estimate->id));
        $this->assertSame(0, AsphaltEstimateSegment::where('asphalt_estimate_id', $estimate->id)->count());
    }

    /** Smoke-tests every GET view (index/create/show/edit) actually renders — catches a Blade syntax error pint can't. */
    public function test_every_view_renders_successfully(): void
    {
        $a = $this->makeCompanyWithOwner('Views');
        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $this->twoSegmentPayload());
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();

        $this->actingAs($a['owner'])->get('/app/asphalt-estimates')->assertOk();
        $this->actingAs($a['owner'])->get('/app/asphalt-estimates/create')->assertOk();
        $this->actingAs($a['owner'])->get('/app/asphalt-estimates/' . $estimate->id)->assertOk();
        $this->actingAs($a['owner'])->get('/app/asphalt-estimates/' . $estimate->id . '/edit')->assertOk();
    }
}
