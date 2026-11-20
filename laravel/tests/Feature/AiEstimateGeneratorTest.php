<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CostCode;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Project;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Models\TakeoffPage;
use App\Models\User;
use App\Support\AiEstimateGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the three AI Estimate Generator improvements batched together (see
 * AiEstimateGenerator's own docblock): multi-photo support, grounding in real takeoff
 * measurements, and historical company pricing. There is no existing pattern anywhere in
 * this codebase for mocking the raw curl_init() Anthropic call AiEstimateGenerator makes
 * (it isn't built on Laravel's Http facade, so Http::fake() doesn't intercept it), so this
 * covers everything reachable without a live network call instead: the two new query-
 * building methods in isolation, multi-photo upload validation at the controller, and that
 * project_id is correctly set on the created Estimate via the deterministic template-
 * fallback path (no AI key configured — exercised with zero network access).
 *
 * Wrapped in DatabaseTransactions, matching EstimateNumericValidationTest/
 * CostCodeHierarchyTest's own convention, so none of this touches the real dev sqlite
 * database — the platform-wide cost codes themselves are already seeded there by
 * CostCodeSeeder and are read-only reference data for every test below.
 */
class AiEstimateGeneratorTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithOwner(string $tag): array
    {
        $company = Company::create([
            'name' => "Acme {$tag} Holding",
            'email' => strtolower($tag).'@example.com',
            // The ai_estimate_generator feature needs a plan/override regardless of AI
            // configuration — mirrors SupplierContactValidationTest's own
            // company_feature_overrides escape hatch, same convention.
            'feature_overrides' => ['ai_estimate_generator' => true],
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag).'-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        return compact('company', 'owner');
    }

    private function costCode(string $code): CostCode
    {
        return CostCode::where('code', $code)->whereNull('company_id')->firstOrFail();
    }

    // ---------------------------------------------------------------------
    // 1. buildHistoricalPricingSummary() in isolation
    // ---------------------------------------------------------------------

    public function test_historical_pricing_returns_null_with_no_history(): void
    {
        $a = $this->makeCompanyWithOwner('NoHistory');

        $summary = AiEstimateGenerator::buildHistoricalPricingSummary($a['company']->id);

        $this->assertNull($summary);
    }

    public function test_historical_pricing_excludes_cost_codes_used_only_once(): void
    {
        $a = $this->makeCompanyWithOwner('OneOff');
        $estimate = Estimate::create([
            'company_id' => $a['company']->id,
            'title' => 'Job 1',
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
            'share_token' => bin2hex(random_bytes(20)),
        ]);
        $concrete = $this->costCode('03 30 00');
        EstimateItem::create([
            'estimate_id' => $estimate->id,
            'description' => 'Ready-mix concrete C25',
            'qty' => 10,
            'unit_cost' => 480,
            'total' => 4800,
            'cost_code_id' => $concrete->id,
        ]);

        $summary = AiEstimateGenerator::buildHistoricalPricingSummary($a['company']->id);

        // Only one past item uses this cost code — below the >=2 threshold meant to avoid
        // anchoring on a single noisy outlier, so nothing is returned.
        $this->assertNull($summary);
    }

    public function test_historical_pricing_averages_and_counts_repeated_cost_codes(): void
    {
        $a = $this->makeCompanyWithOwner('Repeated');
        $concrete = $this->costCode('03 30 00');

        foreach ([400, 500, 540] as $cost) {
            $estimate = Estimate::create([
                'company_id' => $a['company']->id,
                'title' => 'Job',
                'status' => 'draft',
                'subtotal' => 1000,
                'total' => 1000,
                'share_token' => bin2hex(random_bytes(20)),
            ]);
            EstimateItem::create([
                'estimate_id' => $estimate->id,
                'description' => 'Ready-mix concrete C25',
                'qty' => 10,
                'unit_cost' => $cost,
                'total' => $cost * 10,
                'cost_code_id' => $concrete->id,
            ]);
        }

        $summary = AiEstimateGenerator::buildHistoricalPricingSummary($a['company']->id);

        $this->assertNotNull($summary);
        // avg(400, 500, 540) = 480.00, used on 3 past items.
        $this->assertStringContainsString('480.00', $summary);
        $this->assertStringContainsString('3 past jobs', $summary);
        $this->assertStringContainsString('03 30 00', $summary);
    }

    public function test_historical_pricing_never_leaks_another_companys_data(): void
    {
        $a = $this->makeCompanyWithOwner('Leak A');
        $b = $this->makeCompanyWithOwner('Leak B');
        $concrete = $this->costCode('03 30 00');

        foreach ([$a, $b] as $company) {
            foreach ([1, 2] as $i) {
                $estimate = Estimate::create([
                    'company_id' => $company['company']->id,
                    'title' => 'Job',
                    'status' => 'draft',
                    'subtotal' => 1000,
                    'total' => 1000,
                    'share_token' => bin2hex(random_bytes(20)),
                ]);
                EstimateItem::create([
                    'estimate_id' => $estimate->id,
                    'description' => 'Ready-mix concrete C25',
                    'qty' => 10,
                    // Company A always pays 100, company B always pays 999 — if B's rows
                    // ever leaked into A's average, this assertion would catch it.
                    'unit_cost' => $company['company']->id === $a['company']->id ? 100 : 999,
                    'total' => 1000,
                    'cost_code_id' => $concrete->id,
                ]);
            }
        }

        $summaryA = AiEstimateGenerator::buildHistoricalPricingSummary($a['company']->id);
        $summaryB = AiEstimateGenerator::buildHistoricalPricingSummary($b['company']->id);

        $this->assertStringContainsString('100.00', $summaryA);
        $this->assertStringNotContainsString('999.00', $summaryA);
        $this->assertStringContainsString('999.00', $summaryB);
        $this->assertStringNotContainsString('100.00', $summaryB);
    }

    public function test_historical_pricing_caps_at_15_most_frequently_used(): void
    {
        $a = $this->makeCompanyWithOwner('CapFifteen');
        $codes = CostCode::whereNotNull('parent_id')->whereNull('company_id')->orderBy('id')->limit(20)->get();
        $this->assertGreaterThanOrEqual(20, $codes->count(), 'Needs at least 20 seeded sub-codes to test the cap.');

        foreach ($codes as $i => $code) {
            // Give each cost code a distinct use-count (2, 3, 4, ...), descending so the
            // first 15 codes created here are also the 15 most-used ones.
            $uses = 2 + ((19 - $i));
            for ($n = 0; $n < $uses; $n++) {
                $estimate = Estimate::create([
                    'company_id' => $a['company']->id,
                    'title' => 'Job',
                    'status' => 'draft',
                    'subtotal' => 100,
                    'total' => 100,
                    'share_token' => bin2hex(random_bytes(20)),
                ]);
                EstimateItem::create([
                    'estimate_id' => $estimate->id,
                    'description' => 'Item',
                    'qty' => 1,
                    'unit_cost' => 100,
                    'total' => 100,
                    'cost_code_id' => $code->id,
                ]);
            }
        }

        $summary = AiEstimateGenerator::buildHistoricalPricingSummary($a['company']->id);

        $this->assertSame(15, substr_count($summary, 'past jobs'));
    }

    // ---------------------------------------------------------------------
    // 2. buildTakeoffSummary() in isolation
    // ---------------------------------------------------------------------

    public function test_takeoff_summary_returns_null_with_no_project(): void
    {
        $this->assertNull(AiEstimateGenerator::buildTakeoffSummary(null));
    }

    public function test_takeoff_summary_returns_null_for_project_with_no_takeoffs(): void
    {
        $a = $this->makeCompanyWithOwner('NoTakeoffs');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Empty Project']);

        $this->assertNull(AiEstimateGenerator::buildTakeoffSummary($project->id));
    }

    public function test_takeoff_summary_includes_real_measurements_and_excludes_cutouts(): void
    {
        $a = $this->makeCompanyWithOwner('Grounded');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Floor']);
        $takeoff = Takeoff::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'name' => 'Ground Floor Plan']);
        $page = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 1, 'image_path' => '/x.jpg']);

        $livingRoom = TakeoffMeasurement::create([
            'takeoff_id' => $takeoff->id,
            'takeoff_page_id' => $page->id,
            'type' => 'area',
            'label' => 'Living Room floor',
            'value' => 24.5,
            'unit' => 'm²',
            'unit_cost' => 50,
            'total_cost' => 1225,
        ]);
        TakeoffMeasurement::create([
            'takeoff_id' => $takeoff->id,
            'takeoff_page_id' => $page->id,
            'parent_measurement_id' => $livingRoom->id,
            'is_cutout' => true,
            'type' => 'area',
            'label' => 'Closet nook (cutout)',
            'value' => 2.0,
            'unit' => 'm²',
            'unit_cost' => 50,
            'total_cost' => 100,
        ]);
        TakeoffMeasurement::create([
            'takeoff_id' => $takeoff->id,
            'takeoff_page_id' => $page->id,
            'type' => 'count',
            'label' => 'Door',
            'value' => 3,
            'unit' => 'ea',
            'unit_cost' => 0,
            'total_cost' => 0,
        ]);

        $summary = AiEstimateGenerator::buildTakeoffSummary($project->id);

        $this->assertNotNull($summary);
        $this->assertStringContainsString('Living Room floor: 24.50 m²', $summary);
        $this->assertStringContainsString('Door: 3.00 ea', $summary);
        $this->assertStringNotContainsString('Closet nook', $summary);
    }

    // ---------------------------------------------------------------------
    // 3. Multi-photo upload validation at the controller
    // ---------------------------------------------------------------------

    private function makeJpeg(string $name, int $kilobytes = 10): UploadedFile
    {
        return UploadedFile::fake()->image($name)->size($kilobytes);
    }

    public function test_aigenerate_rejects_a_sixth_photo(): void
    {
        $a = $this->makeCompanyWithOwner('SixthPhoto');
        $photos = [];
        for ($i = 0; $i < 6; $i++) {
            $photos[] = $this->makeJpeg("p{$i}.jpg");
        }

        $response = $this->actingAs($a['owner'])->post('/app/estimates/ai/generate', [
            'description' => 'Some project',
            'photos' => $photos,
        ]);

        $response->assertRedirect('/app/estimates/ai');
        $this->assertSame(0, Estimate::where('company_id', $a['company']->id)->count());
    }

    public function test_aigenerate_rejects_invalid_photo_type(): void
    {
        $a = $this->makeCompanyWithOwner('BadType');
        $bad = UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf');

        $response = $this->actingAs($a['owner'])->post('/app/estimates/ai/generate', [
            'description' => 'Some project',
            'photos' => [$bad],
        ]);

        $response->assertRedirect('/app/estimates/ai');
        $this->assertSame(0, Estimate::where('company_id', $a['company']->id)->count());
    }

    public function test_aigenerate_rejects_oversized_photo(): void
    {
        $a = $this->makeCompanyWithOwner('Oversized');
        $tooBig = UploadedFile::fake()->image('big.jpg')->size(9 * 1024);

        $response = $this->actingAs($a['owner'])->post('/app/estimates/ai/generate', [
            'description' => 'Some project',
            'photos' => [$tooBig],
        ]);

        $response->assertRedirect('/app/estimates/ai');
        $this->assertSame(0, Estimate::where('company_id', $a['company']->id)->count());
    }

    public function test_aigenerate_accepts_up_to_five_photos(): void
    {
        $a = $this->makeCompanyWithOwner('FivePhotos');
        $photos = [];
        for ($i = 0; $i < 5; $i++) {
            $photos[] = $this->makeJpeg("p{$i}.jpg");
        }

        $response = $this->actingAs($a['owner'])->post('/app/estimates/ai/generate', [
            'description' => 'Remodel a small kitchen',
            'photos' => $photos,
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Estimate::where('company_id', $a['company']->id)->count());
    }

    // ---------------------------------------------------------------------
    // 4. project_id set on the created Estimate (deterministic template-fallback path —
    //    no AI key configured, so this never touches the network).
    // ---------------------------------------------------------------------

    public function test_aigenerate_sets_project_id_when_a_project_is_selected(): void
    {
        $a = $this->makeCompanyWithOwner('ProjectLink');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Renovation']);

        $response = $this->actingAs($a['owner'])->post('/app/estimates/ai/generate', [
            'description' => 'Remodel a 20m2 kitchen with new cabinets',
            'project_id' => (string) $project->id,
        ]);

        $response->assertRedirect();
        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $this->assertSame($project->id, $estimate->project_id);
    }

    public function test_aigenerate_leaves_project_id_null_when_none_selected(): void
    {
        $a = $this->makeCompanyWithOwner('NoProjectLink');

        $response = $this->actingAs($a['owner'])->post('/app/estimates/ai/generate', [
            'description' => 'Remodel a 20m2 kitchen with new cabinets',
        ]);

        $response->assertRedirect();
        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $this->assertNull($estimate->project_id);
    }

    public function test_aigenerate_ignores_a_project_id_owned_by_another_company(): void
    {
        $a = $this->makeCompanyWithOwner('OwnerCo');
        $other = $this->makeCompanyWithOwner('OtherCo');
        $foreignProject = Project::create(['company_id' => $other['company']->id, 'name' => 'Not Yours']);

        $response = $this->actingAs($a['owner'])->post('/app/estimates/ai/generate', [
            'description' => 'Remodel a 20m2 kitchen with new cabinets',
            'project_id' => (string) $foreignProject->id,
        ]);

        $response->assertRedirect();
        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $this->assertNull($estimate->project_id);
    }
}
