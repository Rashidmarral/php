<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ProjectUpdate;
use App\Models\ScheduleTask;
use App\Models\Setting;
use App\Models\SiteLog;
use App\Models\User;
use App\Support\AiProjectUpdateDrafter;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers "Project Updates" — the client-visible progress feed (ProjectUpdate/
 * ProjectUpdateController) and its AI-assisted first draft (AiProjectUpdateDrafter).
 * Mirrors TakeoffScaleDetectionTest's AI-faking-seam approach (AiProjectUpdateDrafter::
 * $draftFake, reset to null in tearDown() so it never leaks into other test files) and
 * SelectionTest's own "Client portal" section for the makeClient()/actingAs(..., 'client')
 * pattern.
 *
 * On the "nothing to summarize" case: draftWithAi() never calls AiProjectUpdateDrafter::
 * draft() at all when the project has no recent site logs/photo captions/schedule tasks —
 * test_draft_with_ai_returns_not_enough_data_without_calling_the_ai_when_project_has_no_activity()
 * proves this directly by asserting the fake closure is never invoked, rather than relying
 * on the AI to honestly report "nothing to summarize" itself (same "never let the AI
 * hallucinate from nothing" stance as every other conservative AI method in this app).
 */
class ProjectUpdateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
        AiProjectUpdateDrafter::$draftFake = null;
    }

    protected function tearDown(): void
    {
        AiProjectUpdateDrafter::$draftFake = null;
        parent::tearDown();
    }

    private function makeCompanyWithOwner(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-'.strtolower($tag).'-'.bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['client_portal' => true]),
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

    private function makeClient(Company $company, Project $project, string $tag): Client
    {
        $client = Client::create([
            'company_id' => $company->id,
            'name' => "{$tag} Client",
            'email' => strtolower($tag).'-client@example.com',
            'password' => Hash::make('password'),
            'portal_enabled' => true,
        ]);
        $project->update(['client_id' => $client->id]);

        return $client;
    }

    // ------------------------------------------------------------------
    // 1: draft/publish/unpublish visibility in the client portal.
    // ------------------------------------------------------------------

    public function test_a_draft_update_is_not_visible_in_the_client_portal(): void
    {
        $a = $this->makeCompanyWithOwner('UpdDraftHidden');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $client = $this->makeClient($a['company'], $project, 'UpdDraftHidden');

        $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/updates', [
            'title' => 'Week 1',
            'body' => 'Foundation work has started.',
            'submit_action' => 'draft',
        ]);

        $update = ProjectUpdate::where('project_id', $project->id)->first();
        $this->assertNotNull($update);
        $this->assertFalse($update->isPublished());

        $response = $this->actingAs($client, 'client')->get('/portal/projects/'.$project->id);
        $response->assertOk();
        $response->assertDontSee('Foundation work has started.');
    }

    public function test_publishing_an_update_makes_it_visible_and_unpublishing_hides_it_again(): void
    {
        $a = $this->makeCompanyWithOwner('UpdPublishToggle');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $client = $this->makeClient($a['company'], $project, 'UpdPublishToggle');

        $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/updates', [
            'title' => 'Week 2',
            'body' => 'Walls are up on the ground floor.',
            'submit_action' => 'draft',
        ]);
        $update = ProjectUpdate::where('project_id', $project->id)->first();

        // Still a draft — not visible yet.
        $this->actingAs($client, 'client')->get('/portal/projects/'.$project->id)
            ->assertDontSee('Walls are up on the ground floor.');

        $this->actingAs($a['owner'])->post('/app/project-updates/'.$update->id.'/publish');
        $update->refresh();
        $this->assertTrue($update->isPublished());

        $this->actingAs($client, 'client')->get('/portal/projects/'.$project->id)
            ->assertSee('Walls are up on the ground floor.');

        $this->actingAs($a['owner'])->post('/app/project-updates/'.$update->id.'/unpublish');
        $update->refresh();
        $this->assertFalse($update->isPublished());

        $this->actingAs($client, 'client')->get('/portal/projects/'.$project->id)
            ->assertDontSee('Walls are up on the ground floor.');
    }

    public function test_store_with_publish_now_publishes_immediately(): void
    {
        $a = $this->makeCompanyWithOwner('UpdStorePublishNow');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);

        $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/updates', [
            'title' => 'Week 3',
            'body' => 'Roof is on.',
            'submit_action' => 'publish',
        ]);

        $update = ProjectUpdate::where('project_id', $project->id)->first();
        $this->assertNotNull($update);
        $this->assertTrue($update->isPublished());
        $this->assertSame('manual', $update->source);
    }

    // ------------------------------------------------------------------
    // 2: draftWithAi() returns the drafted text and saves nothing itself.
    // ------------------------------------------------------------------

    public function test_draft_with_ai_returns_drafted_text_and_creates_no_update_row(): void
    {
        $a = $this->makeCompanyWithOwner('UpdDraftAiOk');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        SiteLog::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'log_date' => now()->format('Y-m-d'),
            'weather' => 'Sunny',
            'workers_on_site' => 12,
            'notes' => 'Poured the foundation slab.',
        ]);

        Setting::set('ai_enabled', '1');
        Setting::set('ai_api_key', 'fake-key-for-tests');

        $receivedContext = null;
        AiProjectUpdateDrafter::$draftFake = function (array $context) use (&$receivedContext) {
            $receivedContext = $context;

            return 'Great progress this week — the foundation slab has been poured.';
        };

        $response = $this->actingAs($a['owner'])->postJson('/app/projects/'.$project->id.'/updates/draft-with-ai');

        $response->assertStatus(200)->assertJson([
            'ok' => true,
            'body' => 'Great progress this week — the foundation slab has been poured.',
        ]);

        $this->assertNotNull($receivedContext);
        $this->assertSame('Villa Reno', $receivedContext['project_name']);
        $this->assertCount(1, $receivedContext['recent_site_logs']);

        // The whole point: drafting is never the same as saving.
        $this->assertSame(0, ProjectUpdate::where('project_id', $project->id)->count());
    }

    // ------------------------------------------------------------------
    // 3: "nothing to summarize" — the fake AI closure is never invoked.
    // ------------------------------------------------------------------

    public function test_draft_with_ai_returns_not_enough_data_without_calling_the_ai_when_project_has_no_activity(): void
    {
        $a = $this->makeCompanyWithOwner('UpdDraftAiEmpty');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Empty Project']);

        Setting::set('ai_enabled', '1');
        Setting::set('ai_api_key', 'fake-key-for-tests');

        $called = false;
        AiProjectUpdateDrafter::$draftFake = function (array $context) use (&$called) {
            $called = true;

            return 'Should never get here.';
        };

        $response = $this->actingAs($a['owner'])->postJson('/app/projects/'.$project->id.'/updates/draft-with-ai');

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.project_updates.nothing_to_summarize'),
        ]);
        $this->assertFalse($called);
        $this->assertSame(0, ProjectUpdate::where('project_id', $project->id)->count());
    }

    public function test_draft_with_ai_reports_ai_not_configured(): void
    {
        $a = $this->makeCompanyWithOwner('UpdDraftAiOff');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        SiteLog::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'log_date' => now()->format('Y-m-d'), 'notes' => 'Something happened.']);

        $response = $this->actingAs($a['owner'])->postJson('/app/projects/'.$project->id.'/updates/draft-with-ai');

        $response->assertStatus(422)->assertJson([
            'ok' => false,
            'error' => t('user.project_updates.ai_not_configured'),
        ]);
    }

    public function test_draft_with_ai_uses_photo_captions_and_task_status_grouping(): void
    {
        $a = $this->makeCompanyWithOwner('UpdDraftAiContext');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        ProjectPhoto::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'caption' => 'Foundation poured, north wing',
            'file_path' => '/uploads/project-photos/test.jpg',
            'taken_on' => now()->format('Y-m-d'),
        ]);
        ScheduleTask::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'title' => 'Excavation', 'status' => 'done']);
        ScheduleTask::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'title' => 'Framing', 'status' => 'in_progress']);
        ScheduleTask::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'title' => 'Roofing', 'status' => 'pending']);

        Setting::set('ai_enabled', '1');
        Setting::set('ai_api_key', 'fake-key-for-tests');

        $receivedContext = null;
        AiProjectUpdateDrafter::$draftFake = function (array $context) use (&$receivedContext) {
            $receivedContext = $context;

            return 'Drafted update.';
        };

        $this->actingAs($a['owner'])->postJson('/app/projects/'.$project->id.'/updates/draft-with-ai')
            ->assertStatus(200)->assertJson(['ok' => true]);

        $this->assertSame(['Foundation poured, north wing'], $receivedContext['recent_photo_captions']);
        $this->assertSame(['Excavation'], $receivedContext['tasks_done']);
        $this->assertSame(['Framing'], $receivedContext['tasks_in_progress']);
        $this->assertSame(['Roofing'], $receivedContext['tasks_upcoming']);
    }

    // ------------------------------------------------------------------
    // 4: cross-company ownership scoping.
    // ------------------------------------------------------------------

    public function test_company_b_cannot_view_company_a_updates_index(): void
    {
        $a = $this->makeCompanyWithOwner('UpdTenantA');
        $b = $this->makeCompanyWithOwner('UpdTenantB');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);

        $response = $this->actingAs($b['owner'])->get('/app/projects/'.$project->id.'/updates');

        $response->assertStatus(404);
    }

    public function test_company_b_cannot_publish_company_a_update(): void
    {
        $a = $this->makeCompanyWithOwner('UpdTenantPublishA');
        $b = $this->makeCompanyWithOwner('UpdTenantPublishB');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $update = ProjectUpdate::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'body' => 'Confidential internal progress note.',
            'source' => 'manual',
        ]);

        $response = $this->actingAs($b['owner'])->post('/app/project-updates/'.$update->id.'/publish');

        $response->assertStatus(404);
        $update->refresh();
        $this->assertFalse($update->isPublished());
    }

    public function test_company_b_cannot_delete_company_a_update(): void
    {
        $a = $this->makeCompanyWithOwner('UpdTenantDeleteA');
        $b = $this->makeCompanyWithOwner('UpdTenantDeleteB');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $update = ProjectUpdate::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'body' => 'Confidential internal progress note.',
            'source' => 'manual',
        ]);

        $response = $this->actingAs($b['owner'])->post('/app/project-updates/'.$update->id.'/delete');

        $response->assertStatus(404);
        $this->assertNotNull(ProjectUpdate::find($update->id));
    }

    public function test_company_b_cannot_draft_with_ai_for_company_a_project(): void
    {
        $a = $this->makeCompanyWithOwner('UpdTenantDraftA');
        $b = $this->makeCompanyWithOwner('UpdTenantDraftB');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);

        $response = $this->actingAs($b['owner'])->postJson('/app/projects/'.$project->id.'/updates/draft-with-ai');

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // 5: a client can only ever see published updates for THEIR OWN project.
    // ------------------------------------------------------------------

    public function test_client_cannot_see_another_clients_project_updates(): void
    {
        $a = $this->makeCompanyWithOwner('UpdClientOtherProject');
        $projectMine = Project::create(['company_id' => $a['company']->id, 'name' => 'My Project']);
        $projectTheirs = Project::create(['company_id' => $a['company']->id, 'name' => 'Their Project']);
        $this->makeClient($a['company'], $projectMine, 'UpdClientOtherProjectMine');
        $otherClient = $this->makeClient($a['company'], $projectTheirs, 'UpdClientOtherProjectTheirs');

        ProjectUpdate::create([
            'company_id' => $a['company']->id,
            'project_id' => $projectMine->id,
            'body' => 'Update meant only for the other client.',
            'source' => 'manual',
            'published_at' => now(),
        ]);

        // otherClient only owns $projectTheirs — viewing $projectMine must 404 entirely.
        $response = $this->actingAs($otherClient, 'client')->get('/portal/projects/'.$projectMine->id);
        $response->assertStatus(404);
    }

    public function test_client_from_another_company_cannot_see_the_update(): void
    {
        $a = $this->makeCompanyWithOwner('UpdClientOtherCoA');
        $b = $this->makeCompanyWithOwner('UpdClientOtherCoB');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $this->makeClient($a['company'], $project, 'UpdClientOtherCoA');
        $otherCoClient = Client::create([
            'company_id' => $b['company']->id,
            'name' => 'Other Co Client',
            'email' => 'other-co-client-updates@example.com',
            'password' => Hash::make('password'),
            'portal_enabled' => true,
        ]);

        ProjectUpdate::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'body' => 'Update meant only for Company A client.',
            'source' => 'manual',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($otherCoClient, 'client')->get('/portal/projects/'.$project->id);
        $response->assertStatus(404);
    }

    public function test_client_sees_only_published_updates_for_their_own_project(): void
    {
        $a = $this->makeCompanyWithOwner('UpdClientOwnProject');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $client = $this->makeClient($a['company'], $project, 'UpdClientOwnProject');

        ProjectUpdate::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'title' => 'Visible Update',
            'body' => 'This one is published and should be visible.',
            'source' => 'manual',
            'published_at' => now(),
        ]);
        ProjectUpdate::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'title' => 'Hidden Draft',
            'body' => 'This one is still a draft and must stay hidden.',
            'source' => 'manual',
            'published_at' => null,
        ]);

        $response = $this->actingAs($client, 'client')->get('/portal/projects/'.$project->id);

        $response->assertOk();
        $response->assertSee('This one is published and should be visible.');
        $response->assertDontSee('This one is still a draft and must stay hidden.');
    }
}
