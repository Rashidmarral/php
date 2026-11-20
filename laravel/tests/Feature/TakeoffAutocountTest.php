<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Models\TakeoffPage;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers Autocount (TakeoffController::autocount()) end-to-end for everything reachable
 * without a live network call — there is no existing pattern in this codebase for mocking
 * the raw curl_init() Anthropic call AiTakeoffAnalyzer makes (see AiEstimateGeneratorTest's
 * own docblock for the same limitation), so this test never configures AI and relies on the
 * fact that every one of these checks (feature gate, write ability, cross-tenant ownership,
 * and box validation/clamping) happens — and fails fast, before any crop or AI call —
 * strictly before AiTakeoffAnalyzer::isConfigured() is ever consulted. The "it's an ordinary
 * count measurement afterward" half of the flow (TakeoffController::addMeasurement(), the
 * same endpoint a confirmed Autocount review posts to) is covered directly, with no AI
 * involved at all.
 */
class TakeoffAutocountTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
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

    private function makeViewer(int $companyId, string $tag): User
    {
        return User::create([
            'company_id' => $companyId,
            'name' => "{$tag} Viewer",
            'email' => strtolower($tag).'-viewer@example.com',
            'password' => Hash::make('password'),
            'role' => 'viewer',
            'status' => 'active',
        ]);
    }

    /** A real on-disk JPEG (via GD, never a hand-crafted fake byte string) so getimagesize()/GD cropping have something real to work against. */
    private function makeTakeoffWithRealImage(int $companyId, string $tag, int $width = 200, int $height = 200): array
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

    // ---------------------------------------------------------------------
    // Guard rails: feature gate, write ability, cross-tenant ownership.
    // ---------------------------------------------------------------------

    public function test_autocount_requires_the_takeoff_feature(): void
    {
        $a = $this->makeCompanyWithOwner('NoFeature', takeoffFeature: false);
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'NoFeature');

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/autocount", [
            'x' => 50, 'y' => 50, 'width' => 40, 'height' => 40,
        ]);

        $response->assertStatus(403)->assertJson(['ok' => false]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_autocount_requires_write_ability(): void
    {
        $a = $this->makeCompanyWithOwner('NoWrite');
        $viewer = $this->makeViewer($a['company']->id, 'NoWrite');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'NoWrite');

        $response = $this->actingAs($viewer)->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/autocount", [
            'x' => 50, 'y' => 50, 'width' => 40, 'height' => 40,
        ]);

        $response->assertStatus(403)->assertJson(['ok' => false]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_autocount_on_another_companys_page_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('AutocountTenantA');
        $b = $this->makeCompanyWithOwner('AutocountTenantB');
        [$takeoffA, $pageA] = $this->makeTakeoffWithRealImage($a['company']->id, 'AutocountTenantA');

        // Company B's owner, acting on company A's takeoff/page ids directly, must 404 —
        // never reach the box/image logic at all.
        $response = $this->actingAs($b['owner'])->postJson("/app/takeoffs/{$takeoffA->id}/pages/{$pageA->id}/autocount", [
            'x' => 50, 'y' => 50, 'width' => 40, 'height' => 40,
        ]);

        $response->assertStatus(404);
        $this->cleanupTakeoffFiles($takeoffA);
    }

    // ---------------------------------------------------------------------
    // Box validation / clamping — all reachable with no AI configured, since the box is
    // checked strictly before AiTakeoffAnalyzer::isConfigured() (see autocount()'s comment).
    // ---------------------------------------------------------------------

    public function test_autocount_rejects_a_box_smaller_than_the_minimum(): void
    {
        $a = $this->makeCompanyWithOwner('BoxTooSmall');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'BoxTooSmall', 200, 200);

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/autocount", [
            'x' => 50, 'y' => 50, 'width' => 5, 'height' => 5,
        ]);

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.autocount_box_invalid'),
        ]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_autocount_rejects_a_box_covering_most_of_the_page(): void
    {
        $a = $this->makeCompanyWithOwner('BoxTooBig');
        // 200x200 page (area 40000); a 180x180 box (area 32400) is well over the 50% cap.
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'BoxTooBig', 200, 200);

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/autocount", [
            'x' => 10, 'y' => 10, 'width' => 180, 'height' => 180,
        ]);

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.autocount_box_invalid'),
        ]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_autocount_clamps_an_out_of_bounds_box_instead_of_rejecting_it(): void
    {
        $a = $this->makeCompanyWithOwner('BoxClamped');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'BoxClamped', 200, 200);

        // Starts well outside the top-left corner; clamped into bounds this becomes a
        // 10x10 box at (0,0) — exactly at the minimum, so it must be ACCEPTED, not rejected.
        // With no AI configured in this test environment, acceptance surfaces as the
        // ai_not_configured error rather than a box_invalid one — proof the box itself
        // passed validation.
        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/autocount", [
            'x' => -50, 'y' => -50, 'width' => 60, 'height' => 60,
        ]);

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.ai_not_configured'),
        ]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_autocount_rejects_a_box_clamped_below_the_minimum(): void
    {
        $a = $this->makeCompanyWithOwner('BoxClampedTooSmall');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'BoxClampedTooSmall', 200, 200);

        // Starts just 4px inside the right edge of a 200-wide page — clamped width collapses
        // to 4px, under the minimum, so this must still be rejected after clamping.
        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/autocount", [
            'x' => 196, 'y' => 50, 'width' => 40, 'height' => 40,
        ]);

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.autocount_box_invalid'),
        ]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_autocount_reports_ai_not_configured_for_an_otherwise_valid_box(): void
    {
        $a = $this->makeCompanyWithOwner('AiNotConfigured');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'AiNotConfigured', 200, 200);

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/autocount", [
            'x' => 50, 'y' => 50, 'width' => 40, 'height' => 40,
        ]);

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.ai_not_configured'),
        ]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_autocount_reports_a_missing_page_image_file_cleanly(): void
    {
        $a = $this->makeCompanyWithOwner('ImageMissing');
        $takeoff = Takeoff::create(['company_id' => $a['company']->id, 'name' => 'Missing Image Plan']);
        $page = TakeoffPage::create([
            'takeoff_id' => $takeoff->id,
            'page_number' => 1,
            'image_path' => '/uploads/takeoffs/does-not-exist/nope.jpg',
            'scale_px_per_unit' => 1,
            'scale_unit' => 'm',
        ]);

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/autocount", [
            'x' => 50, 'y' => 50, 'width' => 40, 'height' => 40,
        ]);

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.autocount_image_missing'),
        ]);
    }

    // ---------------------------------------------------------------------
    // The "ordinary afterward" half of the flow: a confirmed Autocount review posts to the
    // exact same addMeasurement() endpoint the Count tool itself uses — no AI involved here.
    // ---------------------------------------------------------------------

    public function test_confirmed_autocount_points_save_as_an_ordinary_count_measurement(): void
    {
        $a = $this->makeCompanyWithOwner('AutocountSave');
        [$takeoff, $page] = $this->makeTakeoffWithRealImage($a['company']->id, 'AutocountSave');

        // Three detections reviewed down to two confirmed points — exactly what
        // btn-save-autocount in show.blade.php sends: value = count of confirmed points.
        $confirmedPoints = [['x' => 120, 'y' => 80], ['x' => 340, 'y' => 210]];

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/measurements", [
            'type' => 'count',
            'label' => 'Autocounted symbols',
            'value' => count($confirmedPoints),
            'unit_cost' => 0,
            'points' => json_encode($confirmedPoints),
            'is_cutout' => '0',
        ]);

        $response->assertStatus(200)->assertJson(['ok' => true]);

        $measurement = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->where('label', 'Autocounted symbols')->first();
        $this->assertNotNull($measurement);
        $this->assertSame('count', $measurement->type);
        $this->assertSame('2.000', $measurement->value);
        $this->assertSame('ea', $measurement->unit);
        $this->assertFalse((bool) $measurement->is_cutout);
        $this->assertNull($measurement->assembly_id);
        $this->assertSame($confirmedPoints, json_decode($measurement->points_json, true));

        // Renders and behaves exactly like any other count measurement from here on — e.g.
        // it shows up on the show page and can be deleted the same way as a manual one.
        $show = $this->actingAs($a['owner'])->get("/app/takeoffs/{$takeoff->id}?page_id={$page->id}");
        $show->assertSee('Autocounted symbols');

        $this->actingAs($a['owner'])->post("/app/takeoffs/{$takeoff->id}/measurements/{$measurement->id}/delete")->assertRedirect();
        $this->assertNull(TakeoffMeasurement::find($measurement->id));

        $this->cleanupTakeoffFiles($takeoff);
    }
}
