<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\PunchListItem;
use App\Models\User;
use App\Support\Notifications;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the warranty / Defects Liability Period (DLP) tracking feature: the
 * punch-list-to-warranty-claim handoff on PunchListController, and RunDailyTasks'
 * own DLP-ending reminder block. Wrapped in DatabaseTransactions so none of this
 * touches the real dev sqlite database beyond the test, matching SafetyTest/
 * RfiSubmittalDocumentVersionTest's own convention.
 */
class WarrantyDlpTest extends TestCase
{
    use DatabaseTransactions;

    /** See RfiSubmittalDocumentVersionTest's own docblock on why this reset is needed. */
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\App\Support\Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompany(string $tag): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['punch_list' => true]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);
    }

    private function makeUser(Company $company, string $role, string $tag): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => ucfirst($role) . " {$tag}",
            'email' => strtolower($role) . '-' . strtolower($tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function makeProject(Company $company, string $tag, array $attrs = []): Project
    {
        return Project::create([
            'company_id' => $company->id,
            'name' => "Project {$tag}",
            'status' => 'in_progress',
            'budget' => 100000,
            ...$attrs,
        ]);
    }

    private function makePunchItem(Company $company, Project $project, string $tag, array $attrs = []): PunchListItem
    {
        return PunchListItem::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'title' => "Punch item {$tag}",
            'status' => 'open',
            'priority' => 'medium',
            ...$attrs,
        ]);
    }

    // ---------------------------------------------------------------
    // 1. Raising a warranty claim within the DLP window
    // ---------------------------------------------------------------

    public function test_punch_list_item_can_be_raised_as_a_warranty_claim_once_project_is_in_its_warranty_period(): void
    {
        $company = $this->makeCompany('WC');
        $owner = $this->makeUser($company, 'owner', 'WC');
        $project = $this->makeProject($company, 'WC', [
            'actual_completion_date' => now()->subDays(10)->format('Y-m-d'),
            'defects_liability_end_date' => now()->addDays(80)->format('Y-m-d'),
        ]);
        $item = $this->makePunchItem($company, $project, 'WC');

        $this->assertTrue($project->isInWarrantyPeriod());

        $response = $this->actingAs($owner)->post('/app/punch-list/' . $item->id . '/warranty-claim');
        $response->assertRedirect('/app/projects/' . $project->id);

        $item->refresh();
        $this->assertSame('warranty_claim', $item->status);
        $this->assertNotNull($item->warranty_claim_raised_at);
    }

    /** Raising a claim is also allowed once the DLP end date has already passed — not just inside the window. */
    public function test_warranty_claim_still_allowed_after_dlp_end_date_has_passed(): void
    {
        $company = $this->makeCompany('WP');
        $owner = $this->makeUser($company, 'owner', 'WP');
        $project = $this->makeProject($company, 'WP', [
            'actual_completion_date' => now()->subDays(400)->format('Y-m-d'),
            'defects_liability_end_date' => now()->subDays(10)->format('Y-m-d'),
        ]);
        $item = $this->makePunchItem($company, $project, 'WP');

        $this->actingAs($owner)->post('/app/punch-list/' . $item->id . '/warranty-claim')
            ->assertRedirect('/app/projects/' . $project->id);

        $this->assertSame('warranty_claim', $item->fresh()->status);
    }

    // ---------------------------------------------------------------
    // 2. Blocked outside DLP window (project not yet completed)
    // ---------------------------------------------------------------

    public function test_warranty_claim_is_blocked_when_project_has_not_actually_completed(): void
    {
        $company = $this->makeCompany('NC');
        $owner = $this->makeUser($company, 'owner', 'NC');
        // No actual_completion_date set at all — still under active construction.
        $project = $this->makeProject($company, 'NC', [
            'defects_liability_end_date' => now()->addDays(80)->format('Y-m-d'),
        ]);
        $item = $this->makePunchItem($company, $project, 'NC');

        $this->assertFalse($project->isInWarrantyPeriod());

        $this->actingAs($owner)->post('/app/punch-list/' . $item->id . '/warranty-claim')
            ->assertRedirect('/app/projects/' . $project->id);

        $item->refresh();
        $this->assertSame('open', $item->status, 'status must stay unchanged — construction is still active');
        $this->assertNull($item->warranty_claim_raised_at);
    }

    /** actual_completion_date set in the FUTURE also blocks it — the project hasn't finished yet. */
    public function test_warranty_claim_is_blocked_when_actual_completion_date_is_in_the_future(): void
    {
        $company = $this->makeCompany('FC');
        $owner = $this->makeUser($company, 'owner', 'FC');
        $project = $this->makeProject($company, 'FC', [
            'actual_completion_date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $item = $this->makePunchItem($company, $project, 'FC');

        $this->actingAs($owner)->post('/app/punch-list/' . $item->id . '/warranty-claim');

        $this->assertSame('open', $item->fresh()->status);
    }

    // ---------------------------------------------------------------
    // 3. RunDailyTasks DLP reminder — independent from the retention reminder
    // ---------------------------------------------------------------

    public function test_dlp_ending_reminder_fires_once_and_is_independent_of_the_retention_release_reminder(): void
    {
        $company = $this->makeCompany('DL');
        $this->makeUser($company, 'owner', 'DL');
        $project = $this->makeProject($company, 'DL', [
            'defects_liability_end_date' => now()->addDays(10)->format('Y-m-d'),
        ]);
        $this->makePunchItem($company, $project, 'DL1', ['status' => 'open']);
        $this->makePunchItem($company, $project, 'DL2', ['status' => 'in_progress']);
        $this->makePunchItem($company, $project, 'DL3', ['status' => 'resolved']);

        $this->assertNull($project->dlp_reminder_sent_at);
        $this->assertNull($project->retention_reminder_sent_at);

        $this->artisan('app:daily-tasks')->assertExitCode(0);

        $project->refresh();
        $this->assertNotNull($project->dlp_reminder_sent_at, 'the DLP reminder must have fired');
        // No retention is held on this project (no invoices at all), so the retention-release
        // reminder's own gate (retentionHeld() > 0) correctly skips it — proving the two
        // reminders are gated independently, not bundled into one.
        $this->assertNull($project->retention_reminder_sent_at, 'retention reminder has its own independent gate (retention held > 0) and must not fire here');

        $firstSentAt = $project->dlp_reminder_sent_at;

        // Running the command again must NOT re-send — guarded by dlp_reminder_sent_at.
        $this->artisan('app:daily-tasks')->assertExitCode(0);
        $project->refresh();
        $this->assertEquals($firstSentAt, $project->dlp_reminder_sent_at, 'must not re-send once already sent');
    }

    /** Both reminders fire independently for the same project when both of their own conditions are met. */
    public function test_dlp_reminder_and_retention_reminder_both_fire_independently_for_the_same_project(): void
    {
        $company = $this->makeCompany('BR');
        $owner = $this->makeUser($company, 'owner', 'BR');
        $project = $this->makeProject($company, 'BR', [
            'defects_liability_end_date' => now()->addDays(5)->format('Y-m-d'),
        ]);

        // Give the project retention held, via a real invoice with retention, so the
        // retention-release reminder's own gate (retentionHeld() > 0) passes too.
        \App\Models\Invoice::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'invoice_number' => 'INV-WARR-1',
            'status' => 'sent',
            'subtotal' => 10000,
            'total' => 10000,
            'retention_amount' => 1000,
            'retention_released' => false,
        ]);

        $this->artisan('app:daily-tasks')->assertExitCode(0);

        $project->refresh();
        $this->assertNotNull($project->dlp_reminder_sent_at, 'DLP reminder must fire');
        $this->assertNotNull($project->retention_reminder_sent_at, 'retention reminder must ALSO fire — the two are independent, not mutually exclusive');
    }

    public function test_notifications_defects_liability_period_ending_does_not_throw_without_smtp_configured(): void
    {
        $company = $this->makeCompany('NT');
        $this->makeUser($company, 'owner', 'NT');
        $project = $this->makeProject($company, 'NT', [
            'defects_liability_end_date' => now()->addDays(15)->format('Y-m-d'),
        ]);

        // Mailer is a no-op without SMTP configured — this just proves the call is safe,
        // matching the other Notifications::* methods' own test-free "never throws" convention.
        Notifications::defectsLiabilityPeriodEnding($company, $project, 3);
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // 4. Cross-tenant isolation
    // ---------------------------------------------------------------

    public function test_warranty_claim_action_is_blocked_across_companies(): void
    {
        $companyA = $this->makeCompany('TA');
        $ownerA = $this->makeUser($companyA, 'owner', 'TA');
        $projectA = $this->makeProject($companyA, 'TA', [
            'actual_completion_date' => now()->subDays(10)->format('Y-m-d'),
            'defects_liability_end_date' => now()->addDays(80)->format('Y-m-d'),
        ]);
        $itemA = $this->makePunchItem($companyA, $projectA, 'TA');

        $companyB = $this->makeCompany('TB');
        $ownerB = $this->makeUser($companyB, 'owner', 'TB');

        $this->actingAs($ownerB)->post('/app/punch-list/' . $itemA->id . '/warranty-claim')
            ->assertNotFound();

        $this->assertSame('open', $itemA->fresh()->status, "company B's attempt must not touch company A's item");

        // Sanity: the rightful owner CAN still raise it, proving the item itself wasn't corrupted.
        $this->actingAs($ownerA)->post('/app/punch-list/' . $itemA->id . '/warranty-claim')
            ->assertRedirect('/app/projects/' . $projectA->id);
        $this->assertSame('warranty_claim', $itemA->fresh()->status);
    }

    // ---------------------------------------------------------------
    // 5. The project show page itself still renders with the new DLP/warranty
    //    additions in place, on top of its pre-existing sections. The full canonical
    //    "every existing section renders" assertion lives in
    //    RfiSubmittalDocumentVersionTest::test_project_show_page_renders_every_existing_section_plus_the_new_ones_in_both_locales(),
    //    which is run explicitly and separately (see this task's own instructions),
    //    not duplicated here.
    // ---------------------------------------------------------------

    public function test_project_show_page_renders_dlp_status_and_warranty_claim_count(): void
    {
        $company = $this->makeCompany('SH');
        $owner = $this->makeUser($company, 'owner', 'SH');
        $project = $this->makeProject($company, 'SH', [
            'actual_completion_date' => now()->subDays(10)->format('Y-m-d'),
            'defects_liability_end_date' => now()->addDays(25)->format('Y-m-d'),
        ]);
        $raisedItem = $this->makePunchItem($company, $project, 'SH1');
        $this->actingAs($owner)->post('/app/punch-list/' . $raisedItem->id . '/warranty-claim');
        // A second, still-open item — this is the one the "Raise as warranty claim" button
        // must appear for, since the first one already moved past that action.
        $this->makePunchItem($company, $project, 'SH2');

        $show = $this->actingAs($owner)->get('/app/projects/' . $project->id);
        $show->assertOk();
        $show->assertSeeText(t('user.punch_list.title'));
        $show->assertSeeText(t('user.punch_list.warranty_claim_count', ['count' => 1]));
        $show->assertSeeText(t('user.punch_list.raise_warranty_claim'), false);
    }
}
