<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Selection;
use App\Models\SelectionOption;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the Selections module (allowance tracking): company-side CRUD
 * (SelectionController), cross-tenant isolation on both the company and portal
 * sides, the client portal's choose-option action (sets is_chosen on exactly one
 * option and unsets any previously-chosen sibling, moves the selection to
 * client_selected), and Selection::chosenOption()/variance(). Mirrors
 * AssemblyTest/SafetyTest's own shape for company CRUD and image-upload cleanup.
 */
class SelectionTest extends TestCase
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

    private function makeCompanyWithOwner(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-'.strtolower($tag).'-'.bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['materials' => true]),
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

    // ------------------------------------------------------------------
    // Company-side CRUD
    // ------------------------------------------------------------------

    public function test_store_creates_a_selection_with_its_options(): void
    {
        $a = $this->makeCompanyWithOwner('SelStore');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/selections', [
            'category' => 'Flooring',
            'name' => 'Living Room Flooring',
            'name_ar' => 'أرضية الصالة',
            'allowance_amount' => '3000',
            'due_date' => '2027-01-15',
            'option_name' => ['Quartz', 'Granite', 'Laminate'],
            'option_name_ar' => ['', '', ''],
            'option_price' => ['3200.00', '2800.00', '1500.00'],
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/selections');
        $selection = Selection::where('project_id', $project->id)->first();
        $this->assertNotNull($selection);
        $this->assertSame('Flooring', $selection->category);
        $this->assertSame('Living Room Flooring', $selection->name);
        $this->assertSame($a['company']->id, $selection->company_id);
        $this->assertSame('pending', $selection->status);
        $this->assertSame(3, SelectionOption::where('selection_id', $selection->id)->count());
        $quartz = SelectionOption::where('selection_id', $selection->id)->where('name', 'Quartz')->first();
        $this->assertSame('3200.00', $quartz->price);
        $this->assertFalse((bool) $quartz->is_chosen);
    }

    public function test_store_rejects_blank_category(): void
    {
        $a = $this->makeCompanyWithOwner('SelBlankCat');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/selections', [
            'category' => '   ',
            'name' => 'Something',
            'option_name' => ['Option A'],
            'option_price' => ['100'],
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/selections/create');
        $this->assertSame(0, Selection::where('project_id', $project->id)->count());
    }

    public function test_store_rejects_a_selection_with_no_options(): void
    {
        $a = $this->makeCompanyWithOwner('SelNoOptions');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/selections', [
            'category' => 'Flooring',
            'name' => 'Living Room Flooring',
            'allowance_amount' => '3000',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/selections/create');
        $this->assertSame(0, Selection::where('project_id', $project->id)->count());
    }

    public function test_store_rejects_an_invalid_option_price(): void
    {
        $a = $this->makeCompanyWithOwner('SelBadPrice');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/selections', [
            'category' => 'Flooring',
            'name' => 'Living Room Flooring',
            'option_name' => ['Quartz'],
            'option_price' => ['not-a-number'],
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/selections/create');
        $this->assertSame(0, Selection::where('project_id', $project->id)->count());
    }

    public function test_update_replaces_the_options_wholesale(): void
    {
        $a = $this->makeCompanyWithOwner('SelUpdate');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $selection = Selection::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'category' => 'Flooring',
            'name' => 'Old Name',
            'allowance_amount' => 1000,
        ]);
        SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Old Option', 'price' => 500]);

        $response = $this->actingAs($a['owner'])->post('/app/selections/'.$selection->id, [
            'category' => 'Cabinets',
            'name' => 'New Name',
            'allowance_amount' => '2000',
            'option_name' => ['Option X', 'Option Y'],
            'option_price' => ['700', '900'],
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/selections');
        $selection->refresh();
        $this->assertSame('Cabinets', $selection->category);
        $this->assertSame('New Name', $selection->name);
        $options = SelectionOption::where('selection_id', $selection->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $options);
        $this->assertSame('Option X', $options[0]->name);
        $this->assertSame('Option Y', $options[1]->name);
    }

    public function test_update_carries_forward_the_chosen_flag_for_a_kept_option(): void
    {
        $a = $this->makeCompanyWithOwner('SelUpdateChosen');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $selection = Selection::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'category' => 'Flooring',
            'name' => 'Flooring',
            'allowance_amount' => 1000,
            'status' => 'client_selected',
        ]);
        $chosenOption = SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Quartz', 'price' => 1200, 'is_chosen' => true]);
        SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Granite', 'price' => 900, 'is_chosen' => false]);

        // A company admin editing the description/price doesn't re-pick anything — the
        // form carries each row's existing is_chosen flag forward via a hidden field.
        $response = $this->actingAs($a['owner'])->post('/app/selections/'.$selection->id, [
            'category' => 'Flooring',
            'name' => 'Flooring',
            'allowance_amount' => '1000',
            'option_name' => ['Quartz', 'Granite'],
            'option_price' => ['1250', '900'],
            'option_existing_chosen' => ['1', '0'],
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/selections');
        $selection->refresh();
        $chosen = $selection->chosenOption();
        $this->assertNotNull($chosen);
        $this->assertSame('Quartz', $chosen->name);
        $this->assertSame('1250.00', $chosen->price);
    }

    public function test_destroy_removes_the_selection_its_options_and_uploaded_images(): void
    {
        $a = $this->makeCompanyWithOwner('SelDestroy');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);

        $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/selections', [
            'category' => 'Flooring',
            'name' => 'Living Room Flooring',
            'option_name' => ['Quartz'],
            'option_price' => ['1200'],
            'option_image' => [UploadedFile::fake()->image('quartz.jpg', 400, 300)],
        ]);

        $selection = Selection::where('project_id', $project->id)->first();
        $option = SelectionOption::where('selection_id', $selection->id)->first();
        $this->assertNotNull($option->image_path);
        $this->assertTrue(is_file(public_path($option->image_path)));
        $imagePath = $option->image_path;

        $response = $this->actingAs($a['owner'])->post('/app/selections/'.$selection->id.'/delete');

        $response->assertRedirect('/app/projects/'.$project->id.'/selections');
        $this->assertNull(Selection::find($selection->id));
        $this->assertSame(0, SelectionOption::where('selection_id', $selection->id)->count());
        $this->assertFalse(is_file(public_path($imagePath)));
    }

    public function test_update_status_moves_the_selection_forward(): void
    {
        $a = $this->makeCompanyWithOwner('SelStatus');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $selection = Selection::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'category' => 'Flooring',
            'name' => 'Flooring',
            'status' => 'client_selected',
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/selections/'.$selection->id.'/status', [
            'status' => 'approved',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/selections');
        $selection->refresh();
        $this->assertSame('approved', $selection->status);
    }

    public function test_update_status_rejects_an_unknown_status(): void
    {
        $a = $this->makeCompanyWithOwner('SelBadStatus');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $selection = Selection::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'category' => 'Flooring',
            'name' => 'Flooring',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/selections/'.$selection->id.'/status', [
            'status' => 'not-a-real-status',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/selections');
        $selection->refresh();
        $this->assertSame('pending', $selection->status);
    }

    public function test_index_lists_only_this_companys_selections(): void
    {
        $a = $this->makeCompanyWithOwner('SelIndexA');
        $b = $this->makeCompanyWithOwner('SelIndexB');
        $projectA = Project::create(['company_id' => $a['company']->id, 'name' => 'Project A']);
        $projectB = Project::create(['company_id' => $b['company']->id, 'name' => 'Project B']);
        Selection::create(['company_id' => $a['company']->id, 'project_id' => $projectA->id, 'category' => 'Flooring', 'name' => 'Mine']);
        Selection::create(['company_id' => $b['company']->id, 'project_id' => $projectB->id, 'category' => 'Flooring', 'name' => 'Not mine']);

        $response = $this->actingAs($a['owner'])->get('/app/projects/'.$projectA->id.'/selections');

        $response->assertOk();
        $response->assertSee('Mine');
        $response->assertDontSee('Not mine');
    }

    // ------------------------------------------------------------------
    // Cross-tenant isolation (company side)
    // ------------------------------------------------------------------

    public function test_cross_tenant_index_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('SelCrossIndexA');
        $b = $this->makeCompanyWithOwner('SelCrossIndexB');
        $othersProject = Project::create(['company_id' => $b['company']->id, 'name' => "B's Project"]);

        $response = $this->actingAs($a['owner'])->get('/app/projects/'.$othersProject->id.'/selections');

        $response->assertStatus(404);
    }

    public function test_cross_tenant_edit_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('SelCrossEditA');
        $b = $this->makeCompanyWithOwner('SelCrossEditB');
        $othersProject = Project::create(['company_id' => $b['company']->id, 'name' => "B's Project"]);
        $othersSelection = Selection::create(['company_id' => $b['company']->id, 'project_id' => $othersProject->id, 'category' => 'Flooring', 'name' => "B's Selection"]);

        $response = $this->actingAs($a['owner'])->get('/app/selections/'.$othersSelection->id.'/edit');

        $response->assertStatus(404);
    }

    public function test_cross_tenant_update_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('SelCrossUpdateA');
        $b = $this->makeCompanyWithOwner('SelCrossUpdateB');
        $othersProject = Project::create(['company_id' => $b['company']->id, 'name' => "B's Project"]);
        $othersSelection = Selection::create(['company_id' => $b['company']->id, 'project_id' => $othersProject->id, 'category' => 'Flooring', 'name' => "B's Selection"]);

        $response = $this->actingAs($a['owner'])->post('/app/selections/'.$othersSelection->id, [
            'category' => 'Hijacked',
            'name' => 'Hijacked Name',
            'option_name' => ['Option'],
            'option_price' => ['100'],
        ]);

        $response->assertStatus(404);
        $othersSelection->refresh();
        $this->assertSame("B's Selection", $othersSelection->name);
    }

    public function test_cross_tenant_destroy_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('SelCrossDestroyA');
        $b = $this->makeCompanyWithOwner('SelCrossDestroyB');
        $othersProject = Project::create(['company_id' => $b['company']->id, 'name' => "B's Project"]);
        $othersSelection = Selection::create(['company_id' => $b['company']->id, 'project_id' => $othersProject->id, 'category' => 'Flooring', 'name' => "B's Selection"]);

        $response = $this->actingAs($a['owner'])->post('/app/selections/'.$othersSelection->id.'/delete');

        $response->assertStatus(404);
        $this->assertNotNull(Selection::find($othersSelection->id));
    }

    public function test_cross_tenant_status_update_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('SelCrossStatusA');
        $b = $this->makeCompanyWithOwner('SelCrossStatusB');
        $othersProject = Project::create(['company_id' => $b['company']->id, 'name' => "B's Project"]);
        $othersSelection = Selection::create(['company_id' => $b['company']->id, 'project_id' => $othersProject->id, 'category' => 'Flooring', 'name' => "B's Selection", 'status' => 'pending']);

        $response = $this->actingAs($a['owner'])->post('/app/selections/'.$othersSelection->id.'/status', [
            'status' => 'approved',
        ]);

        $response->assertStatus(404);
        $othersSelection->refresh();
        $this->assertSame('pending', $othersSelection->status);
    }

    // ------------------------------------------------------------------
    // Client portal
    // ------------------------------------------------------------------

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

    public function test_portal_client_can_view_their_own_projects_selections(): void
    {
        $a = $this->makeCompanyWithOwner('SelPortalView');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $client = $this->makeClient($a['company'], $project, 'SelPortalView');
        $selection = Selection::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'category' => 'Flooring', 'name' => 'Living Room Flooring', 'allowance_amount' => 3000]);
        SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Quartz', 'price' => 3200]);

        $response = $this->actingAs($client, 'client')->get('/portal/projects/'.$project->id.'/selections');

        $response->assertOk();
        $response->assertSee('Living Room Flooring');
        $response->assertSee('Quartz');
    }

    public function test_portal_client_from_another_company_cannot_view_selections(): void
    {
        $a = $this->makeCompanyWithOwner('SelPortalOtherCoA');
        $b = $this->makeCompanyWithOwner('SelPortalOtherCoB');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $this->makeClient($a['company'], $project, 'SelPortalOtherCoA');
        // A client belonging to a different company entirely, with no project of their own here.
        $otherClient = Client::create([
            'company_id' => $b['company']->id,
            'name' => 'Other Co Client',
            'email' => 'other-co-client@example.com',
            'password' => Hash::make('password'),
            'portal_enabled' => true,
        ]);

        $response = $this->actingAs($otherClient, 'client')->get('/portal/projects/'.$project->id.'/selections');

        $response->assertStatus(404);
    }

    public function test_portal_client_cannot_view_another_clients_project_selections(): void
    {
        $a = $this->makeCompanyWithOwner('SelPortalSameCoA');
        $projectMine = Project::create(['company_id' => $a['company']->id, 'name' => 'My Project']);
        $projectTheirs = Project::create(['company_id' => $a['company']->id, 'name' => 'Their Project']);
        $this->makeClient($a['company'], $projectMine, 'SelPortalSameCoMine');
        $otherClient = $this->makeClient($a['company'], $projectTheirs, 'SelPortalSameCoTheirs');
        // otherClient only owns $projectTheirs — attempting $projectMine's selections must 404.

        $response = $this->actingAs($otherClient, 'client')->get('/portal/projects/'.$projectMine->id.'/selections');

        $response->assertStatus(404);
    }

    public function test_portal_choose_option_sets_is_chosen_and_unsets_sibling_and_advances_status(): void
    {
        $a = $this->makeCompanyWithOwner('SelPortalChoose');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $client = $this->makeClient($a['company'], $project, 'SelPortalChoose');
        $selection = Selection::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'category' => 'Flooring', 'name' => 'Flooring', 'allowance_amount' => 3000, 'status' => 'pending']);
        $quartz = SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Quartz', 'price' => 3200]);
        $granite = SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Granite', 'price' => 2800, 'is_chosen' => true]);

        $response = $this->actingAs($client, 'client')->post('/portal/selections/'.$selection->id.'/options/'.$quartz->id.'/choose');

        $response->assertRedirect('/portal/projects/'.$project->id.'/selections');
        $quartz->refresh();
        $granite->refresh();
        $selection->refresh();
        $this->assertTrue((bool) $quartz->is_chosen);
        $this->assertFalse((bool) $granite->is_chosen);
        $this->assertSame('client_selected', $selection->status);
    }

    public function test_portal_choose_option_cross_tenant_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('SelPortalChooseXTenantA');
        $b = $this->makeCompanyWithOwner('SelPortalChooseXTenantB');
        $projectA = Project::create(['company_id' => $a['company']->id, 'name' => "A's Project"]);
        $projectB = Project::create(['company_id' => $b['company']->id, 'name' => "B's Project"]);
        $this->makeClient($a['company'], $projectA, 'SelPortalChooseXTenantA');
        $clientB = $this->makeClient($b['company'], $projectB, 'SelPortalChooseXTenantB');
        $selectionA = Selection::create(['company_id' => $a['company']->id, 'project_id' => $projectA->id, 'category' => 'Flooring', 'name' => 'Flooring']);
        $optionA = SelectionOption::create(['selection_id' => $selectionA->id, 'name' => 'Quartz', 'price' => 3200]);

        // clientB (company B, owns only projectB) tries to choose an option on an
        // entirely different company's selection.
        $response = $this->actingAs($clientB, 'client')->post('/portal/selections/'.$selectionA->id.'/options/'.$optionA->id.'/choose');

        $response->assertStatus(404);
        $optionA->refresh();
        $this->assertFalse((bool) $optionA->is_chosen);
    }

    // ------------------------------------------------------------------
    // Model: Selection::chosenOption()/variance()
    // ------------------------------------------------------------------

    public function test_chosen_option_and_variance_are_null_before_any_pick(): void
    {
        $a = $this->makeCompanyWithOwner('SelModelNoPick');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $selection = Selection::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'category' => 'Flooring', 'name' => 'Flooring', 'allowance_amount' => 3000]);
        SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Quartz', 'price' => 3200]);
        SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Granite', 'price' => 2800]);

        $this->assertNull($selection->chosenOption());
        $this->assertNull($selection->variance());
    }

    public function test_variance_is_positive_when_the_chosen_option_is_over_the_allowance(): void
    {
        $a = $this->makeCompanyWithOwner('SelModelOver');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $selection = Selection::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'category' => 'Flooring', 'name' => 'Flooring', 'allowance_amount' => 3000]);
        $chosen = SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Quartz', 'price' => 3200, 'is_chosen' => true]);

        $this->assertSame($chosen->id, $selection->chosenOption()->id);
        $this->assertSame(200.0, $selection->variance());
    }

    public function test_variance_is_negative_when_the_chosen_option_is_under_the_allowance(): void
    {
        $a = $this->makeCompanyWithOwner('SelModelUnder');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Villa Reno']);
        $selection = Selection::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'category' => 'Flooring', 'name' => 'Flooring', 'allowance_amount' => 3000]);
        $chosen = SelectionOption::create(['selection_id' => $selection->id, 'name' => 'Laminate', 'price' => 1500, 'is_chosen' => true]);

        $this->assertSame($chosen->id, $selection->chosenOption()->id);
        $this->assertSame(-1500.0, $selection->variance());
    }
}
