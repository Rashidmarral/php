<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the equipment/resource scheduling calendar (visual Gantt-style timeline over
 * EquipmentAssignment) added alongside Task #56's equipment register. Wrapped in
 * DatabaseTransactions, matching EquipmentTest's own convention.
 */
class EquipmentCalendarTest extends TestCase
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

    private function makeCompany(string $tag, bool $withEquipment = true): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode([
                'equipment_management' => $withEquipment,
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

    public function test_calendar_page_renders_bars_for_the_right_equipment_and_date_ranges(): void
    {
        $company = $this->makeCompany('CAL');
        $owner = $this->makeUser($company, 'owner', 'CAL');
        $project = $this->makeProject($company, 'CAL');

        $excavator = Equipment::create(['company_id' => $company->id, 'name' => 'Excavator 20T', 'status' => 'in_use']);
        $generator = Equipment::create(['company_id' => $company->id, 'name' => 'Generator 100kVA', 'status' => 'available']);

        // An open (unreturned) assignment for the excavator.
        EquipmentAssignment::create([
            'company_id' => $company->id,
            'equipment_id' => $excavator->id,
            'project_id' => $project->id,
            'assigned_date' => now()->subDays(3)->format('Y-m-d'),
        ]);
        // A closed (returned) assignment for the generator.
        EquipmentAssignment::create([
            'company_id' => $company->id,
            'equipment_id' => $generator->id,
            'project_id' => $project->id,
            'assigned_date' => now()->subDays(10)->format('Y-m-d'),
            'returned_date' => now()->subDays(8)->format('Y-m-d'),
        ]);
        // Equipment with no assignments at all must still show up as a row.
        $idleEquipment = Equipment::create(['company_id' => $company->id, 'name' => 'Spare Scaffolding Set', 'status' => 'available']);

        $response = $this->actingAs($owner)->get('/app/equipment/calendar');
        $response->assertOk();
        $response->assertSee('Excavator 20T');
        $response->assertSee('Generator 100kVA');
        $response->assertSee('Spare Scaffolding Set');
        $response->assertSee('Project CAL');
        // Active vs returned bars are distinguished by status class.
        $response->assertSee('status-in_progress');
        $response->assertSee('status-done');
        $response->assertSee('/app/equipment-assignments/');
    }

    public function test_rescheduling_an_assignment_updates_its_dates_and_is_reflected_on_the_calendar(): void
    {
        $company = $this->makeCompany('CALR');
        $owner = $this->makeUser($company, 'owner', 'CALR');
        $project = $this->makeProject($company, 'CALR');
        $equipment = Equipment::create(['company_id' => $company->id, 'name' => 'Tower Crane', 'status' => 'in_use']);

        $assignment = EquipmentAssignment::create([
            'company_id' => $company->id,
            'equipment_id' => $equipment->id,
            'project_id' => $project->id,
            'assigned_date' => now()->subDays(5)->format('Y-m-d'),
        ]);

        $newAssigned = now()->subDays(2)->format('Y-m-d');
        $newReturned = now()->addDays(4)->format('Y-m-d');
        $response = $this->actingAs($owner)->post('/app/equipment-assignments/' . $assignment->id . '/reschedule', [
            'assigned_date' => $newAssigned,
            'returned_date' => $newReturned,
        ]);
        $response->assertRedirect('/app/equipment/calendar');

        $assignment->refresh();
        $this->assertSame($newAssigned, $assignment->assigned_date->format('Y-m-d'));
        $this->assertSame($newReturned, $assignment->returned_date->format('Y-m-d'));

        // The updated range is reflected back on the calendar page.
        $calendar = $this->actingAs($owner)->get('/app/equipment/calendar');
        $calendar->assertOk();
        $calendar->assertSee('Tower Crane');

        // A returned_date before the assigned_date is rejected and leaves the row untouched.
        $invalid = $this->actingAs($owner)->post('/app/equipment-assignments/' . $assignment->id . '/reschedule', [
            'assigned_date' => $newAssigned,
            'returned_date' => now()->subDays(20)->format('Y-m-d'),
        ]);
        $invalid->assertRedirect('/app/equipment/calendar');
        $assignment->refresh();
        $this->assertSame($newReturned, $assignment->returned_date->format('Y-m-d'), 'an invalid date range must not be saved');
    }

    public function test_equipment_feature_gate_blocks_the_calendar_when_plan_lacks_it(): void
    {
        $company = $this->makeCompany('CALOFF', withEquipment: false);
        $owner = $this->makeUser($company, 'owner', 'CALOFF');

        $response = $this->actingAs($owner)->get('/app/equipment/calendar');
        $response->assertRedirect('/app/billing');
    }

    public function test_cross_tenant_isolation_company_b_cannot_see_or_reschedule_company_as_assignments(): void
    {
        $companyA = $this->makeCompany('CALTA');
        $companyB = $this->makeCompany('CALTB');
        $ownerA = $this->makeUser($companyA, 'owner', 'CALTA');
        $ownerB = $this->makeUser($companyB, 'owner', 'CALTB');
        $projectA = $this->makeProject($companyA, 'CALTA');

        $equipmentA = Equipment::create(['company_id' => $companyA->id, 'name' => 'Company A Crane Only', 'status' => 'in_use']);
        $assignmentA = EquipmentAssignment::create([
            'company_id' => $companyA->id,
            'equipment_id' => $equipmentA->id,
            'project_id' => $projectA->id,
            'assigned_date' => now()->subDays(1)->format('Y-m-d'),
        ]);

        // Company B's own calendar never shows company A's equipment or assignment.
        $calendarB = $this->actingAs($ownerB)->get('/app/equipment/calendar');
        $calendarB->assertOk();
        $calendarB->assertDontSee('Company A Crane Only');

        // Company B cannot reschedule company A's assignment via the new route.
        $this->actingAs($ownerB)->post('/app/equipment-assignments/' . $assignmentA->id . '/reschedule', [
            'assigned_date' => now()->format('Y-m-d'),
            'returned_date' => now()->addDays(1)->format('Y-m-d'),
        ])->assertNotFound();

        $assignmentA->refresh();
        $this->assertNull($assignmentA->returned_date, 'cross-tenant reschedule attempt must not modify company A\'s assignment');
    }
}
