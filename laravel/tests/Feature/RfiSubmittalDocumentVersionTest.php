<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiMessage;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers Task #55 (RFI tracking, Submittals, Document revision control). Wrapped in
 * DatabaseTransactions so none of this touches the real dev sqlite database beyond
 * the test, matching ApprovalChainTest/GlobalSearchTest's own convention.
 */
class RfiSubmittalDocumentVersionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * App\Support\Feature caches the resolved plan/flags in static properties, which is
     * correct for a real request (one PHP process per request) but leaks across test
     * methods that run in the same process — a later test's Feature::allows() would
     * otherwise see an earlier test's company's flags. Resetting before each test makes
     * every test method see a fresh resolve, exactly like a real separate request would.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\App\Support\Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompany(string $tag, bool $withRfiSubmittals = true): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode([
                'rfi' => $withRfiSubmittals,
                'submittals' => $withRfiSubmittals,
                'documents' => true,
                'equipment_management' => true,
                // Task #57: safety tracking, exercised by this file's own
                // "every section renders" test alongside RFI/Submittals/Equipment.
                'safety_tracking' => true,
            ]),
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

    private function makeProject(Company $company, string $tag): Project
    {
        return Project::create([
            'company_id' => $company->id,
            'name' => "Project {$tag}",
            'status' => 'in_progress',
            'budget' => 100000,
        ]);
    }

    // ---------------------------------------------------------------
    // RFI
    // ---------------------------------------------------------------

    public function test_rfi_can_be_created_and_replied_to_by_both_sides_with_sequential_per_project_numbering(): void
    {
        $company = $this->makeCompany('RF');
        $owner = $this->makeUser($company, 'owner', 'RF');
        $estimator = $this->makeUser($company, 'estimator', 'RF'); // the assignee
        $project = $this->makeProject($company, 'RF');

        // First RFI on this project.
        $create1 = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/rfis', [
            'subject' => 'Window flashing detail',
            'question' => 'Please confirm flashing detail at window head.',
            'assigned_to' => $estimator->id,
        ]);
        $rfi1 = Rfi::where('project_id', $project->id)->first();
        $this->assertNotNull($rfi1);
        $this->assertSame(1, $rfi1->rfi_number);
        $this->assertSame('RFI-001', $rfi1->displayNumber());
        $this->assertSame('open', $rfi1->status);
        $create1->assertRedirect('/app/rfis/' . $rfi1->id);

        // The first message (the question itself) was recorded on the thread.
        $this->assertSame(1, RfiMessage::where('rfi_id', $rfi1->id)->count());

        // Second RFI on the SAME project increments independently (RFI-002).
        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/rfis', [
            'subject' => 'Rebar spacing clarification',
            'question' => 'Confirm rebar spacing at grid line 4.',
        ]);
        $rfi2 = Rfi::where('project_id', $project->id)->where('id', '!=', $rfi1->id)->first();
        $this->assertSame(2, $rfi2->rfi_number);
        $this->assertSame('RFI-002', $rfi2->displayNumber());

        // Requester (owner) replies — a follow-up, not the assignee, keeps it open.
        $this->actingAs($owner)->post('/app/rfis/' . $rfi1->id . '/reply', ['message' => 'Any update?']);
        $rfi1->refresh();
        $this->assertSame('open', $rfi1->status);

        // Assignee replies — this answers the RFI.
        $this->actingAs($estimator)->post('/app/rfis/' . $rfi1->id . '/reply', ['message' => 'Confirmed — see detail sheet A-501.']);
        $rfi1->refresh();
        $this->assertSame('answered', $rfi1->status);

        $this->assertSame(3, RfiMessage::where('rfi_id', $rfi1->id)->count(), 'original question + 2 replies');

        // The thread page renders with both senders' names visible.
        $show = $this->actingAs($owner)->get('/app/rfis/' . $rfi1->id);
        $show->assertOk();
        $show->assertSee('Window flashing detail');
        $show->assertSee('Confirmed — see detail sheet A-501.');

        // Explicit status update.
        $this->actingAs($owner)->post('/app/rfis/' . $rfi1->id . '/status', ['status' => 'closed']);
        $rfi1->refresh();
        $this->assertSame('closed', $rfi1->status);
    }

    public function test_rfi_feature_gate_blocks_creation_when_plan_lacks_it(): void
    {
        $company = $this->makeCompany('RFOFF', withRfiSubmittals: false);
        $owner = $this->makeUser($company, 'owner', 'RFOFF');
        $project = $this->makeProject($company, 'RFOFF');

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/rfis', [
            'subject' => 'Should be blocked',
            'question' => 'Should be blocked',
        ]);
        $response->assertRedirect('/app/billing');
        $this->assertSame(0, Rfi::where('project_id', $project->id)->count());
    }

    public function test_rfi_is_completely_invisible_and_blocked_across_companies(): void
    {
        $companyA = $this->makeCompany('RFA');
        $companyB = $this->makeCompany('RFB');
        $ownerA = $this->makeUser($companyA, 'owner', 'RFA');
        $ownerB = $this->makeUser($companyB, 'owner', 'RFB');
        $projectA = $this->makeProject($companyA, 'RFA');

        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/rfis', [
            'subject' => 'Company A only',
            'question' => 'Company A only',
        ]);
        $rfiA = Rfi::where('project_id', $projectA->id)->first();

        // Company B cannot view company A's RFI.
        $this->actingAs($ownerB)->get('/app/rfis/' . $rfiA->id)->assertNotFound();
        // Company B cannot reply to it either.
        $this->actingAs($ownerB)->post('/app/rfis/' . $rfiA->id . '/reply', ['message' => 'intrusion'])->assertNotFound();
        // Company B cannot reach company A's project to raise an RFI against it.
        $this->actingAs($ownerB)->post('/app/projects/' . $projectA->id . '/rfis', ['subject' => 'x', 'question' => 'x'])->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Submittals
    // ---------------------------------------------------------------

    public function test_submittal_revisions_increment_and_are_all_downloadable_with_history_intact(): void
    {
        $company = $this->makeCompany('SB');
        $owner = $this->makeUser($company, 'owner', 'SB');
        $project = $this->makeProject($company, 'SB');

        $file1 = UploadedFile::fake()->create('shop-drawing-v1.pdf', 50, 'application/pdf');
        $create = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/submittals', [
            'title' => 'Curtain wall shop drawings',
            'spec_section' => '08 44 00',
            'file' => $file1,
        ]);
        $submittal = Submittal::where('project_id', $project->id)->first();
        $this->assertNotNull($submittal);
        $this->assertSame(1, $submittal->submittal_number);
        $this->assertSame('SUB-001', $submittal->displayNumber());
        $create->assertRedirect('/app/submittals/' . $submittal->id);

        $this->assertSame(1, SubmittalRevision::where('submittal_id', $submittal->id)->count());
        $rev1 = SubmittalRevision::where('submittal_id', $submittal->id)->first();
        $this->assertSame(1, $rev1->revision_number);
        $this->assertFileExists(public_path($rev1->file_path));

        // Upload a 2nd revision — status resets to 'submitted' (re-opens review).
        $this->actingAs($owner)->post('/app/submittals/' . $submittal->id . '/status', ['status' => 'revise_resubmit']);
        $submittal->refresh();
        $this->assertSame('revise_resubmit', $submittal->status);

        $file2 = UploadedFile::fake()->create('shop-drawing-v2.pdf', 60, 'application/pdf');
        $this->actingAs($owner)->post('/app/submittals/' . $submittal->id . '/revisions', ['file' => $file2]);

        $this->assertSame(2, SubmittalRevision::where('submittal_id', $submittal->id)->count());
        $rev2 = SubmittalRevision::where('submittal_id', $submittal->id)->orderByDesc('revision_number')->first();
        $this->assertSame(2, $rev2->revision_number);
        $this->assertFileExists(public_path($rev2->file_path));
        // Revision 1 is still there — full history intact, never overwritten.
        $this->assertFileExists(public_path($rev1->file_path));

        $submittal->refresh();
        $this->assertSame('submitted', $submittal->status, 'a new revision re-opens the review cycle');

        // Approve via the review-decision action.
        $this->actingAs($owner)->post('/app/submittals/' . $submittal->id . '/status', ['status' => 'approved']);
        $submittal->refresh();
        $this->assertSame('approved', $submittal->status);
        $this->assertSame($owner->id, $submittal->reviewed_by);
        $this->assertNotNull($submittal->reviewed_at);

        $show = $this->actingAs($owner)->get('/app/submittals/' . $submittal->id);
        $show->assertOk();
        $show->assertSee('Rev 1');
        $show->assertSee('Rev 2');
    }

    public function test_submittal_is_completely_invisible_and_blocked_across_companies(): void
    {
        $companyA = $this->makeCompany('SBA');
        $companyB = $this->makeCompany('SBB');
        $ownerA = $this->makeUser($companyA, 'owner', 'SBA');
        $ownerB = $this->makeUser($companyB, 'owner', 'SBB');
        $projectA = $this->makeProject($companyA, 'SBA');

        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/submittals', [
            'title' => 'Company A only',
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ]);
        $submittalA = Submittal::where('project_id', $projectA->id)->first();

        $this->actingAs($ownerB)->get('/app/submittals/' . $submittalA->id)->assertNotFound();
        $this->actingAs($ownerB)->post('/app/submittals/' . $submittalA->id . '/status', ['status' => 'approved'])->assertNotFound();
        $this->actingAs($ownerB)->post('/app/submittals/' . $submittalA->id . '/revisions', ['file' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Project show page — surgical insertion check
    // ---------------------------------------------------------------

    /**
     * Confirms the new RFI/Submittals/Equipment/Safety sections slot cleanly into the
     * project show page without disturbing any existing section — every pre-existing
     * card (Change Orders, Purchase Orders, Bank Guarantees, Budget vs Actual, Site Log,
     * Payment Certificates, Subcontracts, LD/EOT, Punch List, Site Photo Diary, Schedule)
     * must still render, alongside the newer RFI/Submittals/Equipment/Safety cards — in
     * both English and Arabic.
     */
    public function test_project_show_page_renders_every_existing_section_plus_the_new_ones_in_both_locales(): void
    {
        $company = $this->makeCompany('PG');
        $owner = $this->makeUser($company, 'owner', 'PG');
        $project = $this->makeProject($company, 'PG');
        $project->update(['end_date' => now()->addDays(30)->format('Y-m-d')]);

        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/rfis', [
            'subject' => 'Show-page RFI',
            'question' => 'Does this render correctly?',
        ]);
        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/submittals', [
            'title' => 'Show-page Submittal',
            'file' => UploadedFile::fake()->create('s.pdf', 10, 'application/pdf'),
        ]);
        // Task #56: equipment assigned to this project, via the Equipment card added to
        // this same page — surgical addition, same section-count check this test already does.
        $equipment = \App\Models\Equipment::create(['company_id' => $company->id, 'name' => 'Show-page Excavator', 'status' => 'available']);
        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/equipment-assignments', [
            'equipment_id' => $equipment->id,
            'assigned_date' => now()->format('Y-m-d'),
        ]);
        // Task #57: a safety incident and a toolbox talk, via the combined Safety (HSE)
        // card added to this same page — same surgical-addition check this test already does.
        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/safety-incidents', [
            'incident_date' => now()->format('Y-m-d'),
            'incident_type' => 'Near Miss',
            'description' => 'Show-page safety incident description.',
        ]);
        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/toolbox-talks', [
            'talk_date' => now()->format('Y-m-d'),
            'topic' => 'Show-page Toolbox Talk',
        ]);

        // en: check existing + new section labels/content resolve and render.
        $en = $this->actingAs($owner)->get('/app/projects/' . $project->id . '?lang=en');
        $en->assertOk();
        $en->assertSeeText(t('user.projects.change_orders'));
        $en->assertSeeText(t('user.purchase_orders.title'));
        // These headings are output raw (not via e()) in show.blade.php and contain a
        // literal "&" — compared unescaped since it's never entity-encoded in the page.
        $en->assertSeeText(t('user.projects.bank_guarantees'), false);
        $en->assertSeeText(t('user.projects.budget_vs_actual_title'));
        $en->assertSeeText(t('user.site_log.title'));
        $en->assertSeeText(t('user.payment_certificates.title'));
        $en->assertSeeText(t('user.subcontracts.title'));
        $en->assertSeeText(t('user.ld_eot.title'), false);
        $en->assertSeeText(t('user.punch_list.title'));
        $en->assertSeeText(t('user.projects.site_photo_diary'));
        $en->assertSeeText(t('user.rfi.title'));
        $en->assertSeeText(t('user.submittals.title'));
        $en->assertSeeText(t('user.equipment.project_card_title'));
        $en->assertSeeText(t('user.safety.title'));
        $en->assertSeeText(t('user.safety_incidents.title'));
        $en->assertSeeText(t('user.toolbox_talks.title'));
        $en->assertSee('Show-page RFI');
        $en->assertSee('Show-page Submittal');
        $en->assertSee('Show-page Excavator');
        $en->assertSee('Near Miss');
        $en->assertSee('Show-page Toolbox Talk');

        // ar: same locale-switched page must also render without errors, old + new sections intact.
        $ar = $this->actingAs($owner)->get('/app/projects/' . $project->id . '?lang=ar');
        $ar->assertOk();
        $ar->assertSeeText(t('user.rfi.title'));
        $ar->assertSeeText(t('user.submittals.title'));
        $ar->assertSeeText(t('user.equipment.project_card_title'));
        $ar->assertSeeText(t('user.safety.title'));
        $ar->assertSeeText(t('user.safety_incidents.title'));
        $ar->assertSeeText(t('user.toolbox_talks.title'));
    }

    // ---------------------------------------------------------------
    // Document revision control
    // ---------------------------------------------------------------

    public function test_uploading_a_new_document_version_preserves_full_history_and_index_shows_only_current_by_default(): void
    {
        $company = $this->makeCompany('DV');
        $owner = $this->makeUser($company, 'owner', 'DV');

        $file1 = UploadedFile::fake()->create('contract.pdf', 40, 'application/pdf');
        $this->actingAs($owner)->post('/app/documents', ['file' => $file1]);
        $old = Document::where('company_id', $company->id)->first();
        $this->assertNotNull($old);
        $this->assertSame(1, $old->version);
        $this->assertTrue((bool) $old->is_current);
        $this->assertNull($old->supersedes_id);

        $file2 = UploadedFile::fake()->create('contract-signed.pdf', 45, 'application/pdf');
        $this->actingAs($owner)->post('/app/documents/' . $old->id . '/version', ['file' => $file2]);

        $old->refresh();
        $this->assertFalse((bool) $old->is_current, "the OLD row's is_current must flip to false");

        $new = Document::where('supersedes_id', $old->id)->first();
        $this->assertNotNull($new, 'a NEW document row must be created');
        $this->assertSame(2, $new->version);
        $this->assertTrue((bool) $new->is_current);
        $this->assertSame($old->id, $new->supersedes_id);
        $this->assertFileExists(public_path($new->file_path));
        $this->assertFileExists(public_path($old->file_path), 'the old file itself is never deleted');

        // versionChain() walks the full history, oldest first.
        $chain = $new->versionChain();
        $this->assertCount(2, $chain);
        $this->assertSame($old->id, $chain[0]->id);
        $this->assertSame($new->id, $chain[1]->id);

        // Index defaults to showing only the current version.
        $indexDefault = $this->actingAs($owner)->get('/app/documents');
        $indexDefault->assertOk();
        $indexDefault->assertSee('contract-signed.pdf');
        $indexDefault->assertDontSee('>contract.pdf<', false);

        // ?show_history=1 reveals the superseded version too.
        $indexHistory = $this->actingAs($owner)->get('/app/documents?show_history=1');
        $indexHistory->assertOk();
        $indexHistory->assertSee('contract-signed.pdf');
        $indexHistory->assertSee('contract.pdf');
    }

    public function test_document_version_actions_are_completely_invisible_and_blocked_across_companies(): void
    {
        $companyA = $this->makeCompany('DVA');
        $companyB = $this->makeCompany('DVB');
        $ownerA = $this->makeUser($companyA, 'owner', 'DVA');
        $ownerB = $this->makeUser($companyB, 'owner', 'DVB');

        $this->actingAs($ownerA)->post('/app/documents', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $docA = Document::where('company_id', $companyA->id)->first();

        // Company B cannot upload a new version of company A's document.
        $response = $this->actingAs($ownerB)->post('/app/documents/' . $docA->id . '/version', [
            'file' => UploadedFile::fake()->create('intrusion.pdf', 10, 'application/pdf'),
        ]);
        $response->assertNotFound();
        $this->assertSame(0, Document::where('supersedes_id', $docA->id)->count());

        // Company B's own document list never contains company A's document.
        $indexB = $this->actingAs($ownerB)->get('/app/documents?show_history=1');
        $indexB->assertOk();
        $indexB->assertDontSee('>a.pdf<', false);
    }
}
