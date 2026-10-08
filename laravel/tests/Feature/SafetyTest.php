<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\SafetyIncident;
use App\Models\ToolboxTalk;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers Task #57 (HSE Safety Incident log and Toolbox Talk record module). Wrapped
 * in DatabaseTransactions so none of this touches the real dev sqlite database
 * beyond the test, matching RfiSubmittalDocumentVersionTest/EquipmentTest's own
 * convention.
 */
class SafetyTest extends TestCase
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

    private function makeCompany(string $tag, bool $withSafety = true): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode([
                'safety_tracking' => $withSafety,
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

    public function test_safety_incident_can_be_logged_with_photo_and_numbers_sequentially_per_project_through_its_status_lifecycle(): void
    {
        $company = $this->makeCompany('SI');
        $owner = $this->makeUser($company, 'owner', 'SI');
        $project = $this->makeProject($company, 'SI');

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/safety-incidents', [
            'incident_date' => '2026-10-01',
            'incident_type' => 'Near Miss',
            'severity' => 'medium',
            'description' => 'Scaffolding plank slipped loose, no injury.',
            'location' => '3rd floor',
            'photo' => UploadedFile::fake()->image('incident.jpg', 400, 300),
        ]);
        $response->assertRedirect('/app/projects/' . $project->id);

        $first = SafetyIncident::where('project_id', $project->id)->first();
        $this->assertSame(1, $first->incident_number);
        $this->assertSame('INC-001', $first->displayNumber());
        $this->assertSame('open', $first->status);
        $this->assertSame('medium', $first->severity);
        $this->assertSame((int) $owner->id, (int) $first->reported_by);
        $this->assertNotNull($first->photo_path);
        $this->assertTrue(is_file(public_path($first->photo_path)));

        // A second incident on the same project increments independently of the first.
        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/safety-incidents', [
            'incident_date' => '2026-10-02',
            'incident_type' => 'other',
            'incident_type_other' => 'Chemical spill',
            'severity' => 'high',
            'description' => 'Small solvent spill, contained immediately.',
        ]);
        $second = SafetyIncident::where('project_id', $project->id)->orderByDesc('id')->first();
        $this->assertSame(2, $second->incident_number);
        $this->assertSame('INC-002', $second->displayNumber());
        // The "Other" free-text escape hatch resolves to the typed value, not the literal "other".
        $this->assertSame('Chemical spill', $second->incident_type);

        // Status lifecycle: open -> under_investigation -> closed.
        $this->actingAs($owner)->post('/app/safety-incidents/' . $first->id . '/status', ['status' => 'under_investigation']);
        $this->assertSame('under_investigation', $first->fresh()->status);

        $this->actingAs($owner)->post('/app/safety-incidents/' . $first->id . '/status', [
            'status' => 'closed',
            'corrective_action' => 'Plank re-secured and inspected.',
        ]);
        $closed = $first->fresh();
        $this->assertSame('closed', $closed->status);
        $this->assertSame('Plank re-secured and inspected.', $closed->corrective_action);

        // Deleting an incident also removes its uploaded photo file.
        $photoPath = $closed->photo_path;
        $this->actingAs($owner)->post('/app/safety-incidents/' . $closed->id . '/delete');
        $this->assertFalse(is_file(public_path($photoPath)));
        $this->assertNull(SafetyIncident::find($closed->id));
    }

    public function test_toolbox_talk_can_be_logged_with_attendee_count_and_photo(): void
    {
        $company = $this->makeCompany('TT');
        $owner = $this->makeUser($company, 'owner', 'TT');
        $project = $this->makeProject($company, 'TT');

        $response = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/toolbox-talks', [
            'talk_date' => '2026-10-01',
            'topic' => 'Ladder safety',
            'attendee_count' => 14,
            'notes' => 'Covered three-point contact and inspection checklist.',
            'photo' => UploadedFile::fake()->image('talk.jpg', 400, 300),
        ]);
        $response->assertRedirect('/app/projects/' . $project->id);

        $talk = ToolboxTalk::where('project_id', $project->id)->first();
        $this->assertSame('Ladder safety', $talk->topic);
        $this->assertSame(14, $talk->attendee_count);
        $this->assertSame((int) $owner->id, (int) $talk->conducted_by);
        $this->assertNotNull($talk->photo_path);
        $this->assertTrue(is_file(public_path($talk->photo_path)));

        $photoPath = $talk->photo_path;
        $this->actingAs($owner)->post('/app/toolbox-talks/' . $talk->id . '/delete');
        $this->assertFalse(is_file(public_path($photoPath)));
        $this->assertNull(ToolboxTalk::find($talk->id));
    }

    public function test_safety_feature_gate_blocks_creation_when_plan_lacks_it(): void
    {
        $company = $this->makeCompany('SG', withSafety: false);
        $owner = $this->makeUser($company, 'owner', 'SG');
        $project = $this->makeProject($company, 'SG');

        $incidentResponse = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/safety-incidents', [
            'incident_date' => '2026-10-01',
            'incident_type' => 'Near Miss',
            'description' => 'Should be blocked.',
        ]);
        $incidentResponse->assertRedirect('/app/billing');
        $this->assertSame(0, SafetyIncident::where('project_id', $project->id)->count());

        $talkResponse = $this->actingAs($owner)->post('/app/projects/' . $project->id . '/toolbox-talks', [
            'talk_date' => '2026-10-01',
            'topic' => 'Should be blocked.',
        ]);
        $talkResponse->assertRedirect('/app/billing');
        $this->assertSame(0, ToolboxTalk::where('project_id', $project->id)->count());
    }

    public function test_safety_incidents_and_toolbox_talks_are_completely_invisible_and_blocked_across_companies(): void
    {
        $companyA = $this->makeCompany('TA');
        $ownerA = $this->makeUser($companyA, 'owner', 'TA');
        $projectA = $this->makeProject($companyA, 'TA');

        $companyB = $this->makeCompany('TB');
        $ownerB = $this->makeUser($companyB, 'owner', 'TB');
        $projectB = $this->makeProject($companyB, 'TB');

        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/safety-incidents', [
            'incident_date' => '2026-10-01',
            'incident_type' => 'Near Miss',
            'description' => 'Company A incident.',
        ]);
        $incident = SafetyIncident::where('project_id', $projectA->id)->first();

        $this->actingAs($ownerA)->post('/app/projects/' . $projectA->id . '/toolbox-talks', [
            'talk_date' => '2026-10-01',
            'topic' => 'Company A talk.',
        ]);
        $talk = ToolboxTalk::where('project_id', $projectA->id)->first();

        // Company B cannot see or act on company A's project, incident, or toolbox talk.
        $this->actingAs($ownerB)->get('/app/projects/' . $projectA->id)->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/projects/' . $projectA->id . '/safety-incidents', [
            'incident_date' => '2026-10-01', 'incident_type' => 'Near Miss', 'description' => 'x',
        ])->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/safety-incidents/' . $incident->id . '/status', ['status' => 'closed'])->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/safety-incidents/' . $incident->id . '/delete')->assertStatus(404);
        $this->actingAs($ownerB)->get('/app/projects/' . $projectA->id . '/safety-incidents')->assertStatus(404);

        $this->actingAs($ownerB)->post('/app/projects/' . $projectA->id . '/toolbox-talks', [
            'talk_date' => '2026-10-01', 'topic' => 'x',
        ])->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/toolbox-talks/' . $talk->id . '/delete')->assertStatus(404);
        $this->actingAs($ownerB)->get('/app/projects/' . $projectA->id . '/toolbox-talks')->assertStatus(404);

        // Both records are untouched.
        $this->assertSame('open', $incident->fresh()->status);
        $this->assertNotNull(ToolboxTalk::find($talk->id));

        // Company B's own project with no records on it still renders correctly (not a trivially-empty-DB pass).
        $this->actingAs($ownerB)->get('/app/projects/' . $projectB->id)->assertOk();
    }
}
