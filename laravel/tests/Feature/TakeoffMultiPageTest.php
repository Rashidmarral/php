<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Plan;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Models\TakeoffPage;
use App\Models\User;
use App\Support\Pdf\Pdf;
use App\Support\TakeoffPageBackfill;
use App\Support\TakeoffPdfConverter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the multi-page takeoff feature: PDF upload -> one TakeoffPage per sheet, the
 * single-image backward-compat path, per-page calibration/measurement isolation,
 * convertToEstimate() spanning every page, cross-tenant access, and the historical
 * backfill (TakeoffPageBackfill) that turns a pre-existing single-image takeoff row into
 * its page-1 TakeoffPage.
 */
class TakeoffMultiPageTest extends TestCase
{
    use DatabaseTransactions;

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
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['takeoff' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Co",
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

    /** A real, valid multi-page PDF (via dompdf, already a project dependency) — never a hand-crafted fake byte string. */
    private function makeTestPdf(int $pages): UploadedFile
    {
        $html = '';
        for ($i = 1; $i <= $pages; $i++) {
            $html .= '<div style="page-break-after: always; font-size:40px;">Sheet ' . $i . '</div>';
        }
        $bytes = Pdf::output($html);

        $path = tempnam(sys_get_temp_dir(), 'takeoff_test_pdf_');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'plan-set.pdf', 'application/pdf', null, true);
    }

    public function test_single_image_upload_still_works_exactly_as_before(): void
    {
        $this->assertTrue(TakeoffPdfConverter::isAvailable(), 'pdftoppm must be installed for this test environment.');

        $a = $this->makeCompanyWithOwner('SingleImage');
        $image = UploadedFile::fake()->image('floor-plan.png', 800, 600);

        $response = $this->actingAs($a['owner'])->post('/app/takeoffs', [
            'name' => 'Villa Ground Floor',
            'plan_image' => $image,
        ]);

        $takeoff = Takeoff::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($takeoff);
        $response->assertRedirect('/app/takeoffs/' . $takeoff->id);

        // Backward-compat: the legacy columns are left untouched (null/default), all real
        // data now lives on takeoff_pages.
        $this->assertNull($takeoff->plan_image_path);

        $pages = TakeoffPage::where('takeoff_id', $takeoff->id)->get();
        $this->assertCount(1, $pages);
        $this->assertSame(1, $pages->first()->page_number);
        $this->assertFileExists(public_path($pages->first()->image_path));

        // The show page renders with a working canvas for this one page.
        $show = $this->actingAs($a['owner'])->get('/app/takeoffs/' . $takeoff->id);
        $show->assertStatus(200);
        $show->assertSee('takeoff-canvas', false);

        @unlink(public_path($pages->first()->image_path));
    }

    public function test_pdf_upload_converts_to_the_right_number_of_pages(): void
    {
        $a = $this->makeCompanyWithOwner('PdfThreePage');
        $pdf = $this->makeTestPdf(3);

        $response = $this->actingAs($a['owner'])->post('/app/takeoffs', [
            'name' => 'Full Drawing Set',
            'plan_image' => $pdf,
        ]);

        $takeoff = Takeoff::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($takeoff);
        $response->assertRedirect('/app/takeoffs/' . $takeoff->id);

        $pages = TakeoffPage::where('takeoff_id', $takeoff->id)->orderBy('page_number')->get();
        $this->assertCount(3, $pages);
        foreach ($pages as $i => $page) {
            $this->assertSame($i + 1, $page->page_number);
            $this->assertFileExists(public_path($page->image_path));
            $this->assertStringEndsWith('page-' . ($i + 1) . '.jpg', $page->image_path);
        }

        foreach ($pages as $page) {
            @unlink(public_path($page->image_path));
        }
    }

    public function test_pdf_over_the_page_limit_is_rejected_cleanly(): void
    {
        $a = $this->makeCompanyWithOwner('PdfTooMany');
        $pdf = $this->makeTestPdf(TakeoffPdfConverter::MAX_PAGES + 1);

        $response = $this->actingAs($a['owner'])->post('/app/takeoffs', [
            'name' => 'Way Too Many Sheets',
            'plan_image' => $pdf,
        ]);

        $response->assertRedirect('/app/takeoffs/create');
        $this->assertSame(0, Takeoff::where('company_id', $a['company']->id)->count());
        $this->assertSame(0, TakeoffPage::count());
    }

    public function test_per_page_calibration_and_measurements_are_isolated(): void
    {
        $a = $this->makeCompanyWithOwner('PageIsolation');
        $pdf = $this->makeTestPdf(2);

        $this->actingAs($a['owner'])->post('/app/takeoffs', [
            'name' => 'Two Sheet Plan',
            'plan_image' => $pdf,
        ]);
        $takeoff = Takeoff::where('company_id', $a['company']->id)->first();
        $pages = TakeoffPage::where('takeoff_id', $takeoff->id)->orderBy('page_number')->get();
        [$page1, $page2] = [$pages[0], $pages[1]];

        // Calibrate page 1 and page 2 differently.
        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page1->id}/calibrate", [
            'pixel_distance' => 100, 'real_length' => 10,
        ])->assertStatus(200);
        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page2->id}/calibrate", [
            'pixel_distance' => 400, 'real_length' => 10,
        ])->assertStatus(200);

        $page1->refresh();
        $page2->refresh();
        $this->assertSame('10.000000', $page1->scale_px_per_unit);
        $this->assertSame('40.000000', $page2->scale_px_per_unit);

        // Add a measurement on each page with a distinct label.
        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page1->id}/measurements", [
            'type' => 'length', 'label' => 'Page One Wall', 'value' => 5, 'unit_cost' => 10,
        ])->assertStatus(200);
        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page2->id}/measurements", [
            'type' => 'length', 'label' => 'Page Two Wall', 'value' => 7, 'unit_cost' => 10,
        ])->assertStatus(200);

        // Viewing page 1 shows only page 1's measurement.
        $show1 = $this->actingAs($a['owner'])->get("/app/takeoffs/{$takeoff->id}?page_id={$page1->id}");
        $show1->assertSee('Page One Wall');
        $show1->assertDontSee('Page Two Wall');

        // Viewing page 2 shows only page 2's measurement.
        $show2 = $this->actingAs($a['owner'])->get("/app/takeoffs/{$takeoff->id}?page_id={$page2->id}");
        $show2->assertSee('Page Two Wall');
        $show2->assertDontSee('Page One Wall');

        foreach ($pages as $page) {
            @unlink(public_path($page->image_path));
        }
    }

    public function test_convert_to_estimate_pulls_measurements_from_every_page(): void
    {
        $a = $this->makeCompanyWithOwner('ConvertAllPages');
        $takeoff = Takeoff::create(['company_id' => $a['company']->id, 'name' => 'Multi Page Convert']);
        $page1 = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 1, 'image_path' => '/uploads/takeoffs/x/1.jpg', 'scale_px_per_unit' => 10, 'scale_unit' => 'm']);
        $page2 = TakeoffPage::create(['takeoff_id' => $takeoff->id, 'page_number' => 2, 'image_path' => '/uploads/takeoffs/x/2.jpg', 'scale_px_per_unit' => 10, 'scale_unit' => 'm']);

        TakeoffMeasurement::create(['takeoff_id' => $takeoff->id, 'takeoff_page_id' => $page1->id, 'type' => 'length', 'label' => 'Sheet 1 Item', 'value' => 10, 'unit' => 'm', 'unit_cost' => 5, 'total_cost' => 50]);
        TakeoffMeasurement::create(['takeoff_id' => $takeoff->id, 'takeoff_page_id' => $page2->id, 'type' => 'count', 'label' => 'Sheet 2 Item', 'value' => 3, 'unit' => 'ea', 'unit_cost' => 20, 'total_cost' => 60]);

        $response = $this->actingAs($a['owner'])->post("/app/takeoffs/{$takeoff->id}/convert");

        $estimate = Estimate::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($estimate);
        $response->assertRedirect('/app/estimates/' . $estimate->id);
        $this->assertSame('110.00', $estimate->total);

        $items = EstimateItem::where('estimate_id', $estimate->id)->get();
        $this->assertCount(2, $items);
        $this->assertTrue($items->contains(fn ($i) => str_contains($i->description, 'Sheet 1 Item')));
        $this->assertTrue($items->contains(fn ($i) => str_contains($i->description, 'Sheet 2 Item')));
    }

    public function test_one_company_cannot_reach_another_companys_takeoff_page(): void
    {
        $a = $this->makeCompanyWithOwner('CrossTenantOwnerA');
        $b = $this->makeCompanyWithOwner('CrossTenantOwnerB');

        $takeoffA = Takeoff::create(['company_id' => $a['company']->id, 'name' => 'Company A Plan']);
        $pageA = TakeoffPage::create(['takeoff_id' => $takeoffA->id, 'page_number' => 1, 'image_path' => '/uploads/takeoffs/a/1.jpg', 'scale_px_per_unit' => 1, 'scale_unit' => 'm']);

        // Company B's owner cannot view, calibrate, or add a measurement to company A's takeoff/page.
        $this->actingAs($b['owner'])->get("/app/takeoffs/{$takeoffA->id}")->assertStatus(404);

        $this->actingAs($b['owner'])->postJson("/app/takeoffs/{$takeoffA->id}/pages/{$pageA->id}/calibrate", [
            'pixel_distance' => 100, 'real_length' => 10,
        ])->assertStatus(404);

        $this->actingAs($b['owner'])->postJson("/app/takeoffs/{$takeoffA->id}/pages/{$pageA->id}/measurements", [
            'type' => 'length', 'value' => 5, 'unit_cost' => 10,
        ])->assertStatus(404);

        $pageA->refresh();
        $this->assertSame('1.000000', $pageA->scale_px_per_unit);
        $this->assertSame(0, TakeoffMeasurement::where('takeoff_page_id', $pageA->id)->count());
    }

    /**
     * TakeoffPageBackfill::run() is the migration's own data-migration step, pulled into its
     * own method precisely so it can be exercised directly here. Since every migration has
     * already run by the time tests execute, "data from before this migration" is simulated
     * with a raw DB insert bypassing the Takeoff/TakeoffMeasurement models entirely (an
     * Eloquent create() would go through current code, which never leaves takeoff_page_id
     * null) — then the backfill is called directly and must turn that legacy row into a
     * correct page-1 TakeoffPage and point the legacy measurement at it.
     */
    public function test_backfill_turns_a_pre_existing_legacy_takeoff_into_a_page_one_row(): void
    {
        $a = $this->makeCompanyWithOwner('BackfillLegacy');

        $legacyTakeoffId = \Illuminate\Support\Facades\DB::table('takeoffs')->insertGetId([
            'company_id' => $a['company']->id,
            'project_id' => null,
            'name' => 'Legacy Pre-Migration Plan',
            'plan_image_path' => '/uploads/takeoffs/legacy/old-plan.jpg',
            'scale_px_per_unit' => 42.5,
            'scale_unit' => 'ft',
            'created_at' => now(),
        ]);

        $legacyMeasurementId = \Illuminate\Support\Facades\DB::table('takeoff_measurements')->insertGetId([
            'takeoff_id' => $legacyTakeoffId,
            'takeoff_page_id' => null,
            'type' => 'length',
            'label' => 'Legacy Wall',
            'points_json' => '[]',
            'value' => 12,
            'unit' => 'ft',
            'unit_cost' => 3,
            'total_cost' => 36,
            'created_at' => now(),
        ]);

        // A takeoff with no plan image at all has nothing to migrate — must be left alone.
        $legacyNoImageId = \Illuminate\Support\Facades\DB::table('takeoffs')->insertGetId([
            'company_id' => $a['company']->id,
            'project_id' => null,
            'name' => 'Legacy No Image',
            'plan_image_path' => null,
            'scale_px_per_unit' => 1,
            'scale_unit' => 'm',
            'created_at' => now(),
        ]);

        TakeoffPageBackfill::run();

        $page = TakeoffPage::where('takeoff_id', $legacyTakeoffId)->first();
        $this->assertNotNull($page);
        $this->assertSame(1, $page->page_number);
        $this->assertSame('/uploads/takeoffs/legacy/old-plan.jpg', $page->image_path);
        $this->assertSame('42.500000', $page->scale_px_per_unit);
        $this->assertSame('ft', $page->scale_unit);

        $measurement = TakeoffMeasurement::find($legacyMeasurementId);
        $this->assertSame($page->id, $measurement->takeoff_page_id);

        $this->assertSame(0, TakeoffPage::where('takeoff_id', $legacyNoImageId)->count());
    }
}
