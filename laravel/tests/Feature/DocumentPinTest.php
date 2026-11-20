<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentPin;
use App\Models\Plan;
use App\Models\Project;
use App\Models\PunchListItem;
use App\Models\Rfi;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the plan/drawing pinned-markup viewer: pinning a PunchListItem or an Rfi to an
 * exact (x,y) point on an uploaded Document image, same structure/helpers as
 * RfiSubmittalDocumentVersionTest.
 */
class DocumentPinTest extends TestCase
{
    use DatabaseTransactions;

    /** See RfiSubmittalDocumentVersionTest::setUp() for why this reset is needed. */
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
            'feature_flags' => json_encode([
                'documents' => true,
                'rfi' => true,
            ]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);
    }

    private function makeUser(Company $company, string $tag): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => "Owner {$tag}",
            'email' => strtolower('owner-' . $tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
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

    private function uploadPlanImage(User $owner, Project $project): Document
    {
        $file = UploadedFile::fake()->image('floor-plan.png', 800, 600);
        $this->actingAs($owner)->post('/app/documents', [
            'project_id' => $project->id,
            'file' => $file,
        ]);

        return Document::where('company_id', $owner->company_id)->where('project_id', $project->id)->firstOrFail();
    }

    // ---------------------------------------------------------------
    // Both polymorphic targets
    // ---------------------------------------------------------------

    public function test_a_pin_can_be_placed_linking_a_document_to_a_punch_list_item(): void
    {
        $company = $this->makeCompany('PL');
        $owner = $this->makeUser($company, 'PL');
        $project = $this->makeProject($company, 'PL');
        $document = $this->uploadPlanImage($owner, $project);

        $item = PunchListItem::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'title' => 'Chipped tile',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($owner)->post('/app/documents/' . $document->id . '/pins', [
            'pinnable_type' => 'punch_list_item',
            'pinnable_id' => $item->id,
            'x' => 25.5,
            'y' => 60.25,
        ]);
        $response->assertRedirect('/app/documents/' . $document->id . '/viewer');

        $pin = DocumentPin::where('document_id', $document->id)->first();
        $this->assertNotNull($pin);
        $this->assertSame($company->id, $pin->company_id);
        $this->assertSame(PunchListItem::class, $pin->pinnable_type);
        $this->assertSame($item->id, $pin->pinnable_id);
        $this->assertSame(25.5, (float) $pin->x);
        $this->assertSame(60.25, (float) $pin->y);
        // Label auto-filled from the linked item's title when left blank.
        $this->assertSame('Chipped tile', $pin->label);

        // The morphTo relation resolves back to the exact same punch list item.
        $this->assertInstanceOf(PunchListItem::class, $pin->pinnable);
        $this->assertSame($item->id, $pin->pinnable->id);
        $this->assertTrue($item->documentPins()->whereKey($pin->id)->exists());
    }

    public function test_a_pin_can_be_placed_linking_a_document_to_an_rfi(): void
    {
        $company = $this->makeCompany('RF');
        $owner = $this->makeUser($company, 'RF');
        $project = $this->makeProject($company, 'RF');
        $document = $this->uploadPlanImage($owner, $project);

        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/rfis', [
            'subject' => 'Window flashing detail',
            'question' => 'Please confirm the flashing detail.',
        ]);
        $rfi = Rfi::where('project_id', $project->id)->first();

        $response = $this->actingAs($owner)->post('/app/documents/' . $document->id . '/pins', [
            'pinnable_type' => 'rfi',
            'pinnable_id' => $rfi->id,
            'x' => 10,
            'y' => 90,
            'label' => 'See window head detail',
        ]);
        $response->assertRedirect('/app/documents/' . $document->id . '/viewer');

        $pin = DocumentPin::where('document_id', $document->id)->first();
        $this->assertNotNull($pin);
        $this->assertSame(Rfi::class, $pin->pinnable_type);
        $this->assertSame($rfi->id, $pin->pinnable_id);
        $this->assertSame('See window head detail', $pin->label);
        $this->assertInstanceOf(Rfi::class, $pin->pinnable);
        $this->assertTrue($rfi->documentPins()->whereKey($pin->id)->exists());
    }

    public function test_pin_creation_requires_the_write_ability(): void
    {
        $company = $this->makeCompany('WR');
        $owner = $this->makeUser($company, 'WR');
        $project = $this->makeProject($company, 'WR');
        $document = $this->uploadPlanImage($owner, $project);

        $viewer = User::create([
            'company_id' => $company->id,
            'name' => 'Read Only',
            'email' => 'readonly-wr@example.com',
            'password' => Hash::make('password'),
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $item = PunchListItem::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'title' => 'Blocked pin',
            'status' => 'open',
            'priority' => 'low',
        ]);

        $this->actingAs($viewer)->post('/app/documents/' . $document->id . '/pins', [
            'pinnable_type' => 'punch_list_item',
            'pinnable_id' => $item->id,
            'x' => 5,
            'y' => 5,
        ]);

        $this->assertSame(0, DocumentPin::where('document_id', $document->id)->count());
    }

    // ---------------------------------------------------------------
    // Viewer renders existing pins
    // ---------------------------------------------------------------

    public function test_the_viewer_page_renders_existing_pins_at_their_stored_coordinates(): void
    {
        $company = $this->makeCompany('VW');
        $owner = $this->makeUser($company, 'VW');
        $project = $this->makeProject($company, 'VW');
        $document = $this->uploadPlanImage($owner, $project);

        $item = PunchListItem::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'title' => 'Cracked wall',
            'status' => 'open',
            'priority' => 'high',
        ]);

        DocumentPin::create([
            'document_id' => $document->id,
            'company_id' => $company->id,
            'x' => 33.333,
            'y' => 66.666,
            'pinnable_type' => PunchListItem::class,
            'pinnable_id' => $item->id,
            'created_by' => $owner->id,
            'label' => 'Cracked wall',
        ]);

        $response = $this->actingAs($owner)->get('/app/documents/' . $document->id . '/viewer');
        $response->assertOk();
        $response->assertSee('Cracked wall');
        $response->assertSee('33.333%', false);
        $response->assertSee('66.666%', false);
    }

    public function test_viewer_in_placing_mode_shows_the_pending_pin_hint(): void
    {
        $company = $this->makeCompany('PM');
        $owner = $this->makeUser($company, 'PM');
        $project = $this->makeProject($company, 'PM');
        $document = $this->uploadPlanImage($owner, $project);

        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/rfis', [
            'subject' => 'Door swing clarification',
            'question' => 'Which way does this door swing?',
        ]);
        $rfi = Rfi::where('project_id', $project->id)->first();

        $response = $this->actingAs($owner)->get('/app/documents/' . $document->id . '/viewer?pin_type=rfi&pin_id=' . $rfi->id);
        $response->assertOk();
        $response->assertSee($rfi->displayNumber());
    }

    // ---------------------------------------------------------------
    // Cross-tenant isolation
    // ---------------------------------------------------------------

    public function test_pins_and_documents_are_completely_isolated_across_companies(): void
    {
        $companyA = $this->makeCompany('TA');
        $companyB = $this->makeCompany('TB');
        $ownerA = $this->makeUser($companyA, 'TA');
        $ownerB = $this->makeUser($companyB, 'TB');
        $projectA = $this->makeProject($companyA, 'TA');
        $documentA = $this->uploadPlanImage($ownerA, $projectA);

        $itemA = PunchListItem::create([
            'company_id' => $companyA->id,
            'project_id' => $projectA->id,
            'title' => 'Company A only',
            'status' => 'open',
            'priority' => 'medium',
        ]);

        DocumentPin::create([
            'document_id' => $documentA->id,
            'company_id' => $companyA->id,
            'x' => 50,
            'y' => 50,
            'pinnable_type' => PunchListItem::class,
            'pinnable_id' => $itemA->id,
            'created_by' => $ownerA->id,
            'label' => 'Company A only',
        ]);

        // Company B cannot view company A's document viewer at all.
        $this->actingAs($ownerB)->get('/app/documents/' . $documentA->id . '/viewer')->assertNotFound();

        // Company B cannot place a pin against company A's document...
        $itemB = PunchListItem::create([
            'company_id' => $companyB->id,
            'project_id' => $this->makeProject($companyB, 'TB')->id,
            'title' => 'Company B item',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        $this->actingAs($ownerB)->post('/app/documents/' . $documentA->id . '/pins', [
            'pinnable_type' => 'punch_list_item',
            'pinnable_id' => $itemB->id,
            'x' => 1,
            'y' => 1,
        ])->assertNotFound();

        // ...nor against company A's own punch list item from company B's session, even
        // with a document company B does own.
        $projectB = Project::where('company_id', $companyB->id)->first();
        $documentB = $this->uploadPlanImage($ownerB, $projectB);
        $this->actingAs($ownerB)->post('/app/documents/' . $documentB->id . '/pins', [
            'pinnable_type' => 'punch_list_item',
            'pinnable_id' => $itemA->id,
            'x' => 1,
            'y' => 1,
        ])->assertNotFound();

        $this->assertSame(1, DocumentPin::where('company_id', $companyA->id)->count());
        $this->assertSame(0, DocumentPin::where('company_id', $companyB->id)->count());
    }
}
