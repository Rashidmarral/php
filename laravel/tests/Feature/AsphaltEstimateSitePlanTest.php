<?php

namespace Tests\Feature;

use App\Models\AsphaltEstimate;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the site-plan drawing feature (PDF download + live preview) added
 * on top of the Asphalt / Road Estimate module — see AsphaltEstimateTest for
 * the module's own CRUD/conversion coverage, which this class deliberately
 * does not repeat.
 */
class AsphaltEstimateSitePlanTest extends TestCase
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
            'slug' => 'plan-aesp-'.strtolower($tag).'-'.bin2hex(random_bytes(4)),
            'name' => "AE SitePlan Plan {$tag}",
            'feature_flags' => json_encode(['asphalt_estimate' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Paving",
            'email' => strtolower($tag).'-sp@example.com',
            'plan_id' => $plan->id,
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag).'-sp-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        return compact('company', 'owner');
    }

    /** One main road + two cross streets, with a plot grid on the first cross street — exercises every code path the layout math covers. */
    private function threeSegmentPayload(): array
    {
        return [
            'name' => 'Subdivision Phase 2 Roads',
            'segment_name' => ['Main Road', 'Cross Street 1', 'Cross Street 2'],
            'segment_length_m' => [100, 40, 60],
            'segment_width_m' => [10, 8, 8],
            'segment_median_width_m' => [0, 0, 0],
            'segment_subbase_thickness_mm' => [150, 100, 100],
            'segment_subbase_unit_cost_per_m3' => [80, 80, 80],
            'segment_base_course_thickness_mm' => [200, 150, 150],
            'segment_base_course_unit_cost_per_m3' => [120, 120, 120],
            'segment_asphalt_binder_thickness_mm' => [50, 40, 40],
            'segment_asphalt_wearing_thickness_mm' => [40, 30, 30],
            'segment_asphalt_density_kg_per_m3' => [2400, 2400, 2400],
            'segment_asphalt_unit_cost_per_ton' => [250, 250, 250],
            'segment_is_main_road' => ['1', '0', '0'],
            'segment_plot_rows' => [0, 2, 0],
            'segment_plot_cols' => [0, 3, 0],
            'site_width_m' => 300,
            'site_length_m' => 200,
            'markup_percent' => 10,
        ];
    }

    public function test_drawing_returns_a_pdf_for_an_owned_estimate(): void
    {
        $a = $this->makeCompanyWithOwner('Pdf');
        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $this->threeSegmentPayload());
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);

        $response = $this->actingAs($a['owner'])->get('/app/asphalt-estimates/'.$estimate->id.'/drawing');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /** Only the FIRST is_main_road="1" row is honored — the layout's spine must be "Main Road", never one of the others, even if a tampered request flags more than one. */
    public function test_only_the_first_is_main_road_row_is_honored(): void
    {
        $a = $this->makeCompanyWithOwner('MultiMain');
        $payload = $this->threeSegmentPayload();
        $payload['segment_is_main_road'] = ['1', '1', '1'];

        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $payload);
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();
        $segments = $estimate->segments()->get();

        $this->assertTrue((bool) $segments[0]->is_main_road);
        $this->assertFalse((bool) $segments[1]->is_main_road);
        $this->assertFalse((bool) $segments[2]->is_main_road);
    }

    /** A user from another company must never download another company's site-plan PDF. */
    public function test_cross_company_user_is_blocked_from_the_drawing(): void
    {
        $a = $this->makeCompanyWithOwner('TenantA');
        $b = $this->makeCompanyWithOwner('TenantB');
        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $this->threeSegmentPayload());
        $estimate = AsphaltEstimate::where('company_id', $a['company']->id)->first();

        $this->actingAs($b['owner'])->get('/app/asphalt-estimates/'.$estimate->id.'/drawing')->assertNotFound();
    }

    /** No segments at all => AsphaltSitePlanLayout::compute() returns hasLayout=false, so drawing() redirects with a flash error instead of generating an empty PDF. This is only reachable by bypassing the "at least one segment" store() guard, so the estimate is created directly, not via the form. */
    public function test_drawing_redirects_with_flash_error_when_estimate_has_zero_segments(): void
    {
        $a = $this->makeCompanyWithOwner('NoSegments');
        $estimate = AsphaltEstimate::create([
            'company_id' => $a['company']->id,
            'name' => 'Empty Estimate',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($a['owner'])->get('/app/asphalt-estimates/'.$estimate->id.'/drawing');

        $response->assertRedirect('/app/asphalt-estimates/'.$estimate->id);
        $this->assertNotEmpty(session('flash.error'));
    }

    /** show() must render successfully both with segments (live preview drawn) and with zero segments (empty-state message) — catches a Blade syntax error Pint can't. */
    public function test_show_page_renders_with_and_without_segments(): void
    {
        $a = $this->makeCompanyWithOwner('ShowSmoke');
        $this->actingAs($a['owner'])->post('/app/asphalt-estimates', $this->threeSegmentPayload());
        $estimateWithSegments = AsphaltEstimate::where('company_id', $a['company']->id)->first();

        $this->actingAs($a['owner'])->get('/app/asphalt-estimates/'.$estimateWithSegments->id)->assertOk();

        $emptyEstimate = AsphaltEstimate::create([
            'company_id' => $a['company']->id,
            'name' => 'No Segments Yet',
            'status' => 'draft',
        ]);
        $response = $this->actingAs($a['owner'])->get('/app/asphalt-estimates/'.$emptyEstimate->id);
        $response->assertOk();
        $response->assertSee('Add a road segment', false);
    }
}
