<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Models\TakeoffPage;
use App\Models\User;
use App\Support\AiTakeoffAnalyzer;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers "Autocount — search all pages" (TakeoffController::autocountAllPages()/
 * saveAllPagesAutocount()) — the same one reference-box-crop Autocount already builds
 * (autocount()'s own crop/validate pipeline, reused here unchanged), fanned out across every
 * CURRENT page of the takeoff instead of just the one it was drawn on, mirroring
 * aiAnalyzeAll()'s own per-page failure-isolation shape.
 *
 * Like TakeoffAutocountTest/TakeoffRevisionTest/AiEstimateGeneratorTest, there is no existing
 * pattern anywhere in this codebase for mocking the raw curl_init() Anthropic call
 * AiTakeoffAnalyzer makes — those three files all work around it by only testing what's
 * reachable without AI configured at all. That isn't enough here: exercising the actual
 * "isolate one page's failure from the others" behavior needs AiTakeoffAnalyzer to actually
 * return different results (success/empty/failure) per page. This file uses a small,
 * test-only seam added for exactly this purpose — AiTakeoffAnalyzer::$findMatchingSymbolsFake
 * (see that property's own docblock) — reset to null in tearDown() so it never leaks into any
 * other test file.
 */
class TakeoffCrossSheetAutocountTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
        AiTakeoffAnalyzer::$findMatchingSymbolsFake = null;
    }

    protected function tearDown(): void
    {
        AiTakeoffAnalyzer::$findMatchingSymbolsFake = null;
        parent::tearDown();
    }

    private function makeCompanyWithOwner(string $tag, bool $takeoffFeature = true): array
    {
        $plan = Plan::create([
            'slug' => 'plan-'.strtolower($tag).'-'.bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['takeoff' => $takeoffFeature]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag).'@example.com',
            'plan_id' => $plan->id,
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

    /** A takeoff with $count real on-disk JPEG pages (via GD, never a hand-crafted fake byte string), so getimagesize()/GD cropping have something real to work against — mirrors TakeoffAutocountTest's own makeTakeoffWithRealImage(), just for N pages instead of one. */
    private function makeMultiPageTakeoff(int $companyId, string $tag, int $count, int $width = 200, int $height = 200): array
    {
        $takeoff = Takeoff::create(['company_id' => $companyId, 'name' => "Plan {$tag}"]);
        $destDir = public_path("uploads/takeoffs/{$companyId}/{$takeoff->id}");
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $pages = [];
        for ($i = 1; $i <= $count; $i++) {
            $relativePath = "/uploads/takeoffs/{$companyId}/{$takeoff->id}/page-{$i}.jpg";
            $im = imagecreatetruecolor($width, $height);
            imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 255, 255, 255));
            imagejpeg($im, public_path($relativePath), 90);
            imagedestroy($im);

            $pages[] = TakeoffPage::create([
                'takeoff_id' => $takeoff->id,
                'page_number' => $i,
                'image_path' => $relativePath,
                'scale_px_per_unit' => 1,
                'scale_unit' => 'm',
            ]);
        }

        return [$takeoff, $pages];
    }

    private function cleanupTakeoffFiles(Takeoff $takeoff): void
    {
        $dir = public_path("uploads/takeoffs/{$takeoff->company_id}/{$takeoff->id}");
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** Fakes isConfigured() to true without ever reaching the real curl call — same approach as the fake hook's own docblock describes. */
    private function fakeAiConfigured(): void
    {
        \App\Models\Setting::set('ai_enabled', '1');
        \App\Models\Setting::set('ai_api_key', 'fake-key-for-tests');
        \App\Models\Setting::set('ai_model', 'claude-sonnet-5');
    }

    // ---------------------------------------------------------------------
    // 1 + 2 + 3: one entry per current page (source included), failures isolated,
    // superseded pages excluded.
    // ---------------------------------------------------------------------

    public function test_autocount_all_pages_covers_every_current_page_isolates_failures_and_excludes_superseded_pages(): void
    {
        $a = $this->makeCompanyWithOwner('CrossSheet');
        [$takeoff, $pages] = $this->makeMultiPageTakeoff($a['company']->id, 'CrossSheet', 3);
        [$page1, $page2, $page3] = $pages;

        // A superseded (non-current) 4th page — must never appear in the search or response.
        $oldPage = TakeoffPage::create([
            'takeoff_id' => $takeoff->id,
            'page_number' => 4,
            'image_path' => '/uploads/takeoffs/does-not-matter/old.jpg',
            'scale_px_per_unit' => 1,
            'scale_unit' => 'm',
            'is_current' => false,
        ]);

        $this->fakeAiConfigured();
        AiTakeoffAnalyzer::$findMatchingSymbolsFake = function (string $pageImagePath, string $cropImagePath) {
            if (str_contains($pageImagePath, 'page-1.jpg')) {
                return [['x' => 10.0, 'y' => 20.0], ['x' => 30.0, 'y' => 40.0]];
            }
            if (str_contains($pageImagePath, 'page-2.jpg')) {
                return []; // configured correctly, searched, genuinely found nothing
            }
            if (str_contains($pageImagePath, 'page-3.jpg')) {
                return null; // simulates an AI call that failed for this one page only
            }

            return null;
        };

        $response = $this->actingAs($a['owner'])->postJson(
            "/app/takeoffs/{$takeoff->id}/pages/{$page1->id}/autocount-all-pages",
            ['x' => 50, 'y' => 50, 'width' => 40, 'height' => 40]
        );

        $response->assertStatus(200)->assertJson(['ok' => true]);
        $resultPages = $response->json('pages');

        // Exactly the 3 CURRENT pages — the superseded 4th page never appears.
        $this->assertCount(3, $resultPages);
        $this->assertFalse(in_array($oldPage->id, array_column($resultPages, 'page_id'), true));

        $byPageId = collect($resultPages)->keyBy('page_id');

        // The source page itself is included (search-everywhere, not just "other" pages) and
        // carries its own real matches.
        // JSON round-tripping turns a whole-number float (10.0) into an int (10) — expected,
        // and not something any caller here relies on being a float.
        $this->assertSame([['x' => 10, 'y' => 20], ['x' => 30, 'y' => 40]], $byPageId[$page1->id]['points']);
        $this->assertFalse($byPageId[$page1->id]['failed']);

        // Genuinely zero matches is a legitimate, non-failed result.
        $this->assertSame([], $byPageId[$page2->id]['points']);
        $this->assertFalse($byPageId[$page2->id]['failed']);

        // This page's own AI call failed — marked failed, with empty points, and critically
        // did NOT prevent page 1's and page 2's own results from coming back correctly above.
        $this->assertSame([], $byPageId[$page3->id]['points']);
        $this->assertTrue($byPageId[$page3->id]['failed']);

        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_autocount_all_pages_requires_the_takeoff_feature(): void
    {
        $a = $this->makeCompanyWithOwner('CrossSheetNoFeature', takeoffFeature: false);
        [$takeoff, $pages] = $this->makeMultiPageTakeoff($a['company']->id, 'CrossSheetNoFeature', 2);

        $response = $this->actingAs($a['owner'])->postJson(
            "/app/takeoffs/{$takeoff->id}/pages/{$pages[0]->id}/autocount-all-pages",
            ['x' => 50, 'y' => 50, 'width' => 40, 'height' => 40]
        );

        $response->assertStatus(403)->assertJson(['ok' => false]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_autocount_all_pages_on_another_companys_page_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('CrossSheetTenantA');
        $b = $this->makeCompanyWithOwner('CrossSheetTenantB');
        [$takeoffA, $pagesA] = $this->makeMultiPageTakeoff($a['company']->id, 'CrossSheetTenantA', 2);

        $response = $this->actingAs($b['owner'])->postJson(
            "/app/takeoffs/{$takeoffA->id}/pages/{$pagesA[0]->id}/autocount-all-pages",
            ['x' => 50, 'y' => 50, 'width' => 40, 'height' => 40]
        );

        $response->assertStatus(404);
        $this->cleanupTakeoffFiles($takeoffA);
    }

    // ---------------------------------------------------------------------
    // 4: saveAllPagesAutocount() creates one measurement per non-empty page entry, skips
    // zero-point entries, and the created measurement's value/points match what was sent.
    // ---------------------------------------------------------------------

    public function test_save_all_pages_autocount_creates_one_measurement_per_nonempty_page_and_skips_empty_ones(): void
    {
        $a = $this->makeCompanyWithOwner('CrossSheetSave');
        [$takeoff, $pages] = $this->makeMultiPageTakeoff($a['company']->id, 'CrossSheetSave', 3);
        [$page1, $page2, $page3] = $pages;

        $page1Points = [['x' => 10, 'y' => 20], ['x' => 30, 'y' => 40], ['x' => 50, 'y' => 60]];
        $page2Points = []; // zero points — must be skipped, not errored
        $page3Points = [['x' => 5, 'y' => 5]];

        $payload = [
            'label' => 'Cross-sheet doors',
            'pages' => json_encode([
                ['page_id' => $page1->id, 'points' => $page1Points],
                ['page_id' => $page2->id, 'points' => $page2Points],
                ['page_id' => $page3->id, 'points' => $page3Points],
            ]),
        ];

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/autocount-all-pages/save", $payload);

        $response->assertStatus(200)->assertJson(['ok' => true, 'created' => 2]);

        $m1 = TakeoffMeasurement::where('takeoff_page_id', $page1->id)->where('label', 'Cross-sheet doors')->first();
        $this->assertNotNull($m1);
        $this->assertSame('count', $m1->type);
        $this->assertSame('3.000', $m1->value);
        $this->assertSame('ea', $m1->unit);
        $this->assertFalse((bool) $m1->is_cutout);
        $this->assertNull($m1->assembly_id);
        $this->assertNull($m1->parent_measurement_id);
        $this->assertSame($page1Points, json_decode($m1->points_json, true));

        $this->assertSame(0, TakeoffMeasurement::where('takeoff_page_id', $page2->id)->count());

        $m3 = TakeoffMeasurement::where('takeoff_page_id', $page3->id)->where('label', 'Cross-sheet doors')->first();
        $this->assertNotNull($m3);
        $this->assertSame('1.000', $m3->value);
        $this->assertSame($page3Points, json_decode($m3->points_json, true));

        $this->cleanupTakeoffFiles($takeoff);
    }

    // ---------------------------------------------------------------------
    // 5: a tampered page_id belonging to another company's takeoff is rejected/skipped, never
    // silently creates a measurement on data this company doesn't own.
    // ---------------------------------------------------------------------

    public function test_save_all_pages_autocount_skips_a_page_id_from_another_companys_takeoff(): void
    {
        $a = $this->makeCompanyWithOwner('CrossSheetSaveTenantA');
        $b = $this->makeCompanyWithOwner('CrossSheetSaveTenantB');
        [$takeoffA, $pagesA] = $this->makeMultiPageTakeoff($a['company']->id, 'CrossSheetSaveTenantA', 1);
        [$takeoffB, $pagesB] = $this->makeMultiPageTakeoff($b['company']->id, 'CrossSheetSaveTenantB', 1);

        // Company A's owner posts a save to THEIR OWN takeoff, but tampers one page entry to
        // point at company B's page id.
        $payload = [
            'label' => 'Sneaky cross-tenant save',
            'pages' => json_encode([
                ['page_id' => $pagesA[0]->id, 'points' => [['x' => 1, 'y' => 1]]],
                ['page_id' => $pagesB[0]->id, 'points' => [['x' => 2, 'y' => 2]]],
            ]),
        ];

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoffA->id}/autocount-all-pages/save", $payload);

        // The legitimate entry still succeeds; only the tampered one is skipped.
        $response->assertStatus(200)->assertJson(['ok' => true, 'created' => 1]);

        $this->assertSame(1, TakeoffMeasurement::where('takeoff_page_id', $pagesA[0]->id)->count());
        // Company B's page gets nothing from this request — never a measurement on data
        // company A doesn't own.
        $this->assertSame(0, TakeoffMeasurement::where('takeoff_page_id', $pagesB[0]->id)->count());
        $this->assertSame(0, TakeoffMeasurement::where('takeoff_id', $takeoffB->id)->count());

        $this->cleanupTakeoffFiles($takeoffA);
        $this->cleanupTakeoffFiles($takeoffB);
    }

    public function test_save_all_pages_autocount_requires_write_ability(): void
    {
        $a = $this->makeCompanyWithOwner('CrossSheetSaveNoWrite');
        $viewer = User::create([
            'company_id' => $a['company']->id,
            'name' => 'Viewer',
            'email' => 'crosssheet-viewer@example.com',
            'password' => Hash::make('password'),
            'role' => 'viewer',
            'status' => 'active',
        ]);
        [$takeoff, $pages] = $this->makeMultiPageTakeoff($a['company']->id, 'CrossSheetSaveNoWrite', 1);

        $payload = [
            'label' => 'x',
            'pages' => json_encode([['page_id' => $pages[0]->id, 'points' => [['x' => 1, 'y' => 1]]]]),
        ];

        $response = $this->actingAs($viewer)->postJson("/app/takeoffs/{$takeoff->id}/autocount-all-pages/save", $payload);

        $response->assertStatus(403)->assertJson(['ok' => false]);
        $this->assertSame(0, TakeoffMeasurement::where('takeoff_id', $takeoff->id)->count());
        $this->cleanupTakeoffFiles($takeoff);
    }
}
