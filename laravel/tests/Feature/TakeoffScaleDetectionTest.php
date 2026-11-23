<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Takeoff;
use App\Models\TakeoffPage;
use App\Models\User;
use App\Support\AiTakeoffAnalyzer;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers "Detect Scale (AI)" (TakeoffController::detectScale()) — mirrors
 * TakeoffCrossSheetAutocountTest's exact fixture-building and AI-faking approach, the newest,
 * most relevant precedent: there is no existing pattern anywhere in this codebase for mocking
 * the raw curl_init() Anthropic call AiTakeoffAnalyzer makes, so this uses the same kind of
 * small, test-only seam — AiTakeoffAnalyzer::$detectScaleFake (see that property's own
 * docblock) — reset to null in tearDown() so it never leaks into any other test file.
 *
 * This endpoint is a pure SUGGESTION feed for the existing, unmodified calibrate() endpoint —
 * it never writes scale_px_per_unit itself (test 1 proves this explicitly) — so the real
 * calibration math is exercised only through calibrate() itself (test 3), unchanged.
 */
class TakeoffScaleDetectionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
        AiTakeoffAnalyzer::$detectScaleFake = null;
    }

    protected function tearDown(): void
    {
        AiTakeoffAnalyzer::$detectScaleFake = null;
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

    /** A real on-disk JPEG (via GD, never a hand-crafted fake byte string) — mirrors TakeoffAutocountTest's own makeTakeoffWithRealImage(). */
    private function makeTakeoffWithRealImage(int $companyId, string $tag, int $width = 400, int $height = 300): array
    {
        $takeoff = Takeoff::create(['company_id' => $companyId, 'name' => "Plan {$tag}"]);
        $destDir = public_path("uploads/takeoffs/{$companyId}/{$takeoff->id}");
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $relativePath = "/uploads/takeoffs/{$companyId}/{$takeoff->id}/page-1.jpg";
        $im = imagecreatetruecolor($width, $height);
        imagefilledrectangle($im, 0, 0, $width, $height, imagecolorallocate($im, 255, 255, 255));
        imagejpeg($im, public_path($relativePath), 90);
        imagedestroy($im);

        $page = TakeoffPage::create([
            'takeoff_id' => $takeoff->id,
            'page_number' => 1,
            'image_path' => $relativePath,
            'scale_px_per_unit' => 1,
            'scale_unit' => 'm',
        ]);

        return [$takeoff, $page];
    }

    private function cleanupTakeoffFiles(Takeoff $takeoff): void
    {
        $dir = public_path("uploads/takeoffs/{$takeoff->company_id}/{$takeoff->id}");
        foreach (glob($dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($dir);
    }

    /** Fakes isConfigured() to true without ever reaching the real curl call — same approach TakeoffCrossSheetAutocountTest's own docblock describes. */
    private function fakeAiConfigured(): void
    {
        \App\Models\Setting::set('ai_enabled', '1');
        \App\Models\Setting::set('ai_api_key', 'fake-key-for-tests');
        \App\Models\Setting::set('ai_model', 'claude-sonnet-5');
    }

    // ---------------------------------------------------------------------
    // 1: a successful detection returns the expected fields and never itself touches
    // scale_px_per_unit on the page — proving the detection endpoint only ever hands back a
    // suggestion; only the separate, unmodified calibrate() endpoint writes a calibration.
    // ---------------------------------------------------------------------

    public function test_successful_detection_returns_expected_fields_and_never_writes_the_page_scale(): void
    {
        $a = $this->makeCompanyWithOwner('ScaleDetectOk');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'ScaleDetectOk');
        $originalScale = (float) $page->scale_px_per_unit;

        $this->fakeAiConfigured();
        AiTakeoffAnalyzer::$detectScaleFake = function (string $imagePath) {
            return [
                'found' => true,
                'x1' => 10.0,
                'y1' => 380.0,
                'x2' => 110.0,
                'y2' => 380.0,
                'real_length' => 5.0,
                'unit' => 'm',
                'source_note' => 'graphic scale bar at bottom of sheet, labeled 5.00 m',
            ];
        };

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/detect-scale");

        $response->assertStatus(200)->assertJson([
            'ok' => true,
            'x1' => 10.0,
            'y1' => 380.0,
            'x2' => 110.0,
            'y2' => 380.0,
            'real_length' => 5.0,
            'unit' => 'm',
            'source_note' => 'graphic scale bar at bottom of sheet, labeled 5.00 m',
        ]);

        // The whole point of this being a suggestion, not a calibration: the page's own
        // scale_px_per_unit is completely untouched by this endpoint.
        $page->refresh();
        $this->assertSame($originalScale, (float) $page->scale_px_per_unit);

        $this->cleanupTakeoffFiles($takeoff);
    }

    // ---------------------------------------------------------------------
    // 2: a "not found" fake response returns {ok:false} with the expected message, not a 500
    // or an alarming error — an expected, non-erroneous outcome.
    // ---------------------------------------------------------------------

    public function test_not_found_detection_returns_ok_false_with_the_expected_message(): void
    {
        $a = $this->makeCompanyWithOwner('ScaleDetectNotFound');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'ScaleDetectNotFound');
        $originalScale = (float) $page->scale_px_per_unit;

        $this->fakeAiConfigured();
        AiTakeoffAnalyzer::$detectScaleFake = fn (string $imagePath) => ['found' => false];

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/detect-scale");

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.scale_detection_not_found'),
        ]);

        $page->refresh();
        $this->assertSame($originalScale, (float) $page->scale_px_per_unit);

        $this->cleanupTakeoffFiles($takeoff);
    }

    // ---------------------------------------------------------------------
    // 3: the existing, unmodified calibrate() endpoint, called afterward with the detected
    // x1/y1/x2/y2 converted to a pixel distance and the detected real_length — exactly what
    // the frontend computes from the pre-filled canvas points — still correctly sets
    // scale_px_per_unit. A regression/integration check on calibrate() itself, proving the
    // two endpoints compose correctly end to end even though they're tested separately.
    // ---------------------------------------------------------------------

    public function test_detected_points_compose_correctly_with_the_existing_calibrate_endpoint(): void
    {
        $a = $this->makeCompanyWithOwner('ScaleDetectCompose');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'ScaleDetectCompose');

        $this->fakeAiConfigured();
        AiTakeoffAnalyzer::$detectScaleFake = fn (string $imagePath) => [
            'found' => true,
            'x1' => 10.0,
            'y1' => 100.0,
            'x2' => 110.0, // same y, so the pixel distance is a clean 100px
            'y2' => 100.0,
            'real_length' => 5.0,
            'unit' => 'm',
            'source_note' => 'graphic scale bar at bottom of sheet, labeled 5.00 m',
        ];

        $detect = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/detect-scale");
        $detect->assertStatus(200)->assertJson(['ok' => true]);
        $data = $detect->json();

        // Exactly what btn-save-calibration's own dist() helper computes from the two
        // pre-filled canvas points.
        $pixelDistance = sqrt(($data['x2'] - $data['x1']) ** 2 + ($data['y2'] - $data['y1']) ** 2);
        $this->assertSame(100.0, $pixelDistance);

        $calibrate = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/calibrate", [
            'pixel_distance' => $pixelDistance,
            'real_length' => $data['real_length'],
        ]);

        $calibrate->assertStatus(200)->assertJson(['ok' => true, 'scale' => 20.0]);
        $page->refresh();
        $this->assertSame(20.0, (float) $page->scale_px_per_unit);

        $this->cleanupTakeoffFiles($takeoff);
    }

    // ---------------------------------------------------------------------
    // 4: blocked exactly like other AI-gated takeoff actions when AiTakeoffAnalyzer is not
    // configured — same shape as TakeoffAutocountTest::test_autocount_reports_ai_not_configured_for_an_otherwise_valid_box().
    // ---------------------------------------------------------------------

    public function test_detect_scale_reports_ai_not_configured(): void
    {
        $a = $this->makeCompanyWithOwner('ScaleDetectAiOff');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'ScaleDetectAiOff');

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/detect-scale");

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.ai_not_configured'),
        ]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_detect_scale_requires_the_takeoff_feature(): void
    {
        $a = $this->makeCompanyWithOwner('ScaleDetectNoFeature', takeoffFeature: false);
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'ScaleDetectNoFeature');

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/detect-scale");

        $response->assertStatus(403)->assertJson(['ok' => false]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_detect_scale_requires_write_ability(): void
    {
        $a = $this->makeCompanyWithOwner('ScaleDetectNoWrite');
        $viewer = User::create([
            'company_id' => $a['company']->id,
            'name' => 'Viewer',
            'email' => 'scale-detect-viewer@example.com',
            'password' => Hash::make('password'),
            'role' => 'viewer',
            'status' => 'active',
        ]);
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'ScaleDetectNoWrite');

        $response = $this->actingAs($viewer)->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/detect-scale");

        $response->assertStatus(403)->assertJson(['ok' => false]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    // ---------------------------------------------------------------------
    // 5: cross-company ownership scoping, same pattern as every other takeoff test.
    // ---------------------------------------------------------------------

    public function test_detect_scale_on_another_companys_page_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('ScaleDetectTenantA');
        $b = $this->makeCompanyWithOwner('ScaleDetectTenantB');
        [$takeoffA, $pageA] = $this->makeTakeoffWithRealImage($a['company']->id, 'ScaleDetectTenantA');

        $response = $this->actingAs($b['owner'])->postJson("/app/takeoffs/{$takeoffA->id}/pages/{$pageA->id}/detect-scale");

        $response->assertStatus(404);
        $this->cleanupTakeoffFiles($takeoffA);
    }

    // ---------------------------------------------------------------------
    // 6: a superseded (non-current) page's detect-scale route is rejected, same
    // revision_old_action_blocked pattern as calibrate()/addMeasurement() in TakeoffRevisionTest.
    // ---------------------------------------------------------------------

    public function test_a_superseded_page_cannot_run_detect_scale(): void
    {
        $a = $this->makeCompanyWithOwner('ScaleDetectRevLocked');
        [$takeoff, $old] = $this->makeTakeoffWithRealImage($a['company']->id, 'ScaleDetectRevLocked');

        $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$old->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('v2.jpg', 100, 100)]
        );
        $old->refresh();
        $this->assertFalse((bool) $old->is_current);

        $this->fakeAiConfigured();
        AiTakeoffAnalyzer::$detectScaleFake = fn (string $imagePath) => ['found' => false];

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$old->id}/detect-scale");

        $response->assertStatus(422)->assertJson(['ok' => false, 'error' => t('user.takeoffs.revision_old_action_blocked')]);

        $this->cleanupTakeoffFiles($takeoff);
    }
}
