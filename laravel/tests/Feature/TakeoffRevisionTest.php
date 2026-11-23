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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers plan revision comparison (addenda tracking): uploading a revised plan image for a
 * takeoff page (TakeoffController::reviseUpload(), the exact mirror of
 * DocumentController::uploadVersion() — same version/supersedes_id/is_current columns and
 * DB::transaction shape, see TakeoffPage's own docblock) and comparing two revisions with AI
 * (TakeoffController::compareRevision()). There is no existing pattern in this codebase for
 * mocking the raw curl_init() Anthropic call AiTakeoffAnalyzer makes (see
 * AiEstimateGeneratorTest/TakeoffAutocountTest's own docblocks for the same limitation), so
 * this file never configures AI and relies on the fact that every guard rail (feature gate,
 * write ability, cross-tenant ownership, and "nothing to compare against") is checked and
 * fails fast, strictly before AiTakeoffAnalyzer::isConfigured() is ever consulted.
 */
class TakeoffRevisionTest extends TestCase
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

    private function makeTakeoffWithPage(int $companyId, string $tag): array
    {
        $takeoff = Takeoff::create(['company_id' => $companyId, 'name' => "Plan {$tag}"]);
        $destDir = public_path("uploads/takeoffs/{$companyId}/{$takeoff->id}");
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $relativePath = "/uploads/takeoffs/{$companyId}/{$takeoff->id}/page-1.jpg";
        $im = imagecreatetruecolor(100, 100);
        imagefilledrectangle($im, 0, 0, 100, 100, imagecolorallocate($im, 255, 255, 255));
        imagejpeg($im, public_path($relativePath), 90);
        imagedestroy($im);

        $page = TakeoffPage::create([
            'takeoff_id' => $takeoff->id,
            'page_number' => 1,
            'image_path' => $relativePath,
            'scale_px_per_unit' => 10,
            'scale_unit' => 'm',
        ]);
        // create() doesn't read back column defaults applied at the DB level
        // (version/supersedes_id/is_current) — refresh so this test's own assertions
        // about those columns reflect what's actually in the row, same as any model
        // refetched elsewhere in this test.
        $page->refresh();

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
    // Uploading a revision.
    // ---------------------------------------------------------------------

    public function test_uploading_a_revision_creates_a_new_current_page_and_marks_the_old_one_superseded(): void
    {
        $a = $this->makeCompanyWithOwner('Rev');
        [$takeoff, $old] = $this->makeTakeoffWithPage($a['company']->id, 'Rev');

        $this->assertSame(1, $old->version);
        $this->assertTrue((bool) $old->is_current);
        $this->assertNull($old->supersedes_id);

        $response = $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$old->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('revised.jpg', 100, 100)]
        );

        $old->refresh();
        $this->assertFalse((bool) $old->is_current, "the OLD row's is_current must flip to false");

        $new = TakeoffPage::where('supersedes_id', $old->id)->first();
        $this->assertNotNull($new, 'a NEW takeoff_pages row must be created');
        $response->assertRedirect('/app/takeoffs/'.$takeoff->id.'?page_id='.$new->id);

        $this->assertSame(2, $new->version);
        $this->assertTrue((bool) $new->is_current);
        $this->assertSame($old->id, $new->supersedes_id);
        $this->assertSame($old->page_number, $new->page_number);
        $this->assertSame($old->takeoff_id, $new->takeoff_id);
        $this->assertFileExists(public_path($new->image_path));
        $this->assertFileExists(public_path($old->image_path), 'the old plan image file is never deleted');

        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_old_pages_existing_measurements_stay_attached_to_the_old_page_and_untouched_while_the_new_page_starts_empty(): void
    {
        $a = $this->makeCompanyWithOwner('RevMeasure');
        [$takeoff, $old] = $this->makeTakeoffWithPage($a['company']->id, 'RevMeasure');

        $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$old->id}/measurements", [
            'type' => 'length',
            'label' => 'North wall',
            'value' => 5,
            'unit_cost' => 10,
            'points' => json_encode([['x' => 0, 'y' => 0], ['x' => 50, 'y' => 0]]),
            'is_cutout' => '0',
        ]);
        $oldMeasurement = TakeoffMeasurement::where('takeoff_page_id', $old->id)->first();
        $this->assertNotNull($oldMeasurement);

        $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$old->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('revised.jpg', 100, 100)]
        );
        $new = TakeoffPage::where('supersedes_id', $old->id)->first();

        // The old measurement is still attached to the OLD page, completely untouched.
        $oldMeasurement->refresh();
        $this->assertSame($old->id, $oldMeasurement->takeoff_page_id);
        $this->assertSame('North wall', $oldMeasurement->label);
        $this->assertSame('5.000', $oldMeasurement->value);

        // The new page starts with zero measurements — never auto-carried-over or re-projected.
        $this->assertSame(0, TakeoffMeasurement::where('takeoff_page_id', $new->id)->count());
        $this->assertSame(1, TakeoffMeasurement::where('takeoff_page_id', $old->id)->count());

        // The old revision, viewed directly, still shows its own measurement exactly as it was.
        $oldView = $this->actingAs($a['owner'])->get("/app/takeoffs/{$takeoff->id}?page_id={$old->id}");
        $oldView->assertOk();
        $oldView->assertSee('North wall');

        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_version_chain_returns_every_revision_oldest_first_ending_at_the_current_one(): void
    {
        $a = $this->makeCompanyWithOwner('RevChain');
        [$takeoff, $v1] = $this->makeTakeoffWithPage($a['company']->id, 'RevChain');

        $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$v1->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('v2.jpg', 100, 100)]
        );
        $v2 = TakeoffPage::where('supersedes_id', $v1->id)->first();

        $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$v2->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('v3.jpg', 100, 100)]
        );
        $v3 = TakeoffPage::where('supersedes_id', $v2->id)->first();

        $this->assertSame(3, $v3->version);
        $this->assertTrue((bool) $v3->is_current);

        // Walking from any point in the chain gives the exact same oldest-to-current order.
        // Each starting point is re-fetched fresh immediately before the call — versionChain()
        // itself always queries fresh for every OTHER row in the chain, but (like
        // Document::versionChain()) trusts its own $this as given, so a caller holding a
        // stale, long-cached PHP object (as this test loop otherwise would, having fetched
        // $v1/$v2 before later revisions existed) must refresh it first, exactly as any real
        // caller already does by only ever calling this on a model it just fetched.
        foreach ([$v1, $v2, $v3] as $startingPoint) {
            $startingPoint->refresh();
            $chain = $startingPoint->versionChain();
            $this->assertCount(3, $chain);
            $this->assertSame([$v1->id, $v2->id, $v3->id], array_map(fn ($p) => $p->id, $chain));
            $this->assertTrue((bool) $chain[2]->is_current);
            $this->assertFalse((bool) $chain[0]->is_current);
            $this->assertFalse((bool) $chain[1]->is_current);
        }

        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_revise_requires_the_takeoff_feature(): void
    {
        $a = $this->makeCompanyWithOwner('RevNoFeature', takeoffFeature: false);
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id, 'RevNoFeature');

        $response = $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$page->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('x.jpg', 100, 100)]
        );

        $response->assertRedirect('/app/billing');
        $this->assertSame(0, TakeoffPage::where('supersedes_id', $page->id)->count());
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_revise_requires_write_ability(): void
    {
        $a = $this->makeCompanyWithOwner('RevNoWrite');
        $viewer = $this->makeViewer($a['company']->id, 'RevNoWrite');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id, 'RevNoWrite');

        $response = $this->actingAs($viewer)->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$page->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('x.jpg', 100, 100)]
        );

        $response->assertRedirect('/app');
        $this->assertSame(0, TakeoffPage::where('supersedes_id', $page->id)->count());
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_revise_rejects_an_invalid_file_type(): void
    {
        $a = $this->makeCompanyWithOwner('RevBadType');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id, 'RevBadType');

        $response = $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$page->id}/revise",
            ['plan_image' => UploadedFile::fake()->create('plan.pdf', 100, 'application/pdf')]
        );

        $response->assertRedirect('/app/takeoffs/'.$takeoff->id.'?page_id='.$page->id);
        $this->assertSame(0, TakeoffPage::where('supersedes_id', $page->id)->count());
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_revise_on_another_companys_page_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('RevTenantA');
        $b = $this->makeCompanyWithOwner('RevTenantB');
        [$takeoffA, $pageA] = $this->makeTakeoffWithPage($a['company']->id, 'RevTenantA');

        $response = $this->actingAs($b['owner'])->post(
            "/app/takeoffs/{$takeoffA->id}/pages/{$pageA->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('intrusion.jpg', 100, 100)]
        );

        $response->assertStatus(404);
        $this->assertSame(0, TakeoffPage::where('supersedes_id', $pageA->id)->count());
        $this->cleanupTakeoffFiles($takeoffA);
    }

    // ---------------------------------------------------------------------
    // Old-revision read-only lockout, server side (defense in depth — the UI never offers
    // these actions on a superseded page, see show.blade.php's own $isViewingOldRevision gating).
    // ---------------------------------------------------------------------

    public function test_a_superseded_page_can_no_longer_be_measured_on_or_calibrated(): void
    {
        $a = $this->makeCompanyWithOwner('RevLocked');
        [$takeoff, $old] = $this->makeTakeoffWithPage($a['company']->id, 'RevLocked');
        $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$old->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('v2.jpg', 100, 100)]
        );
        $old->refresh();
        $this->assertFalse((bool) $old->is_current);

        $measure = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$old->id}/measurements", [
            'type' => 'count', 'label' => 'x', 'value' => 1, 'unit_cost' => 0, 'points' => '[]', 'is_cutout' => '0',
        ]);
        $measure->assertStatus(422)->assertJson(['ok' => false, 'error' => t('user.takeoffs.revision_old_action_blocked')]);
        $this->assertSame(0, TakeoffMeasurement::where('takeoff_page_id', $old->id)->count());

        $calibrate = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$old->id}/calibrate", [
            'pixel_distance' => 100, 'real_length' => 5,
        ]);
        $calibrate->assertStatus(422)->assertJson(['ok' => false, 'error' => t('user.takeoffs.revision_old_action_blocked')]);

        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_show_page_marks_an_old_revision_read_only_and_offers_a_link_back_to_current(): void
    {
        $a = $this->makeCompanyWithOwner('RevReadonlyUi');
        [$takeoff, $old] = $this->makeTakeoffWithPage($a['company']->id, 'RevReadonlyUi');
        $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$old->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('v2.jpg', 100, 100)]
        );
        $new = TakeoffPage::where('supersedes_id', $old->id)->first();

        $oldView = $this->actingAs($a['owner'])->get("/app/takeoffs/{$takeoff->id}?page_id={$old->id}");
        $oldView->assertOk();
        $oldView->assertSeeText(t('user.takeoffs.revision_readonly_heading'));
        $oldView->assertSee(t('user.takeoffs.revision_view_current'));
        $oldView->assertDontSee('id="tool-calibrate"', false);

        $currentView = $this->actingAs($a['owner'])->get("/app/takeoffs/{$takeoff->id}?page_id={$new->id}");
        $currentView->assertOk();
        $currentView->assertSee('id="tool-calibrate"', false);
        $currentView->assertSee(t('user.takeoffs.upload_revised_plan'));
        $currentView->assertSeeText(t('user.takeoffs.revision_history_heading'));

        $this->cleanupTakeoffFiles($takeoff);
    }

    // ---------------------------------------------------------------------
    // Compare-revision.
    // ---------------------------------------------------------------------

    public function test_compare_revision_is_rejected_cleanly_when_there_is_nothing_to_compare_against(): void
    {
        $a = $this->makeCompanyWithOwner('CompareNone');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id, 'CompareNone');
        $this->assertNull($page->supersedes_id);

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/compare-revision");

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.compare_no_previous_version'),
        ]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_compare_revision_reports_ai_not_configured_once_there_is_something_to_compare(): void
    {
        $a = $this->makeCompanyWithOwner('CompareAiOff');
        [$takeoff, $old] = $this->makeTakeoffWithPage($a['company']->id, 'CompareAiOff');
        $this->actingAs($a['owner'])->post(
            "/app/takeoffs/{$takeoff->id}/pages/{$old->id}/revise",
            ['plan_image' => UploadedFile::fake()->image('v2.jpg', 100, 100)]
        );
        $new = TakeoffPage::where('supersedes_id', $old->id)->first();
        $this->assertNotNull($new->supersedes_id);

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$new->id}/compare-revision");

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.takeoffs.ai_not_configured'),
        ]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_compare_revision_requires_the_takeoff_feature(): void
    {
        $a = $this->makeCompanyWithOwner('CompareNoFeature', takeoffFeature: false);
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id, 'CompareNoFeature');

        $response = $this->actingAs($a['owner'])->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/compare-revision");

        $response->assertStatus(403)->assertJson(['ok' => false]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_compare_revision_requires_write_ability(): void
    {
        $a = $this->makeCompanyWithOwner('CompareNoWrite');
        $viewer = $this->makeViewer($a['company']->id, 'CompareNoWrite');
        [$takeoff, $page] = $this->makeTakeoffWithPage($a['company']->id, 'CompareNoWrite');

        $response = $this->actingAs($viewer)->postJson("/app/takeoffs/{$takeoff->id}/pages/{$page->id}/compare-revision");

        $response->assertStatus(403)->assertJson(['ok' => false]);
        $this->cleanupTakeoffFiles($takeoff);
    }

    public function test_compare_revision_on_another_companys_page_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('CompareTenantA');
        $b = $this->makeCompanyWithOwner('CompareTenantB');
        [$takeoffA, $pageA] = $this->makeTakeoffWithPage($a['company']->id, 'CompareTenantA');

        $response = $this->actingAs($b['owner'])->postJson("/app/takeoffs/{$takeoffA->id}/pages/{$pageA->id}/compare-revision");

        $response->assertStatus(404);
        $this->cleanupTakeoffFiles($takeoffA);
    }
}
