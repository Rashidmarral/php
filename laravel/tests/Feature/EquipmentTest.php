<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentMaintenanceLog;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers Task #56 (Equipment/fleet asset register, maintenance scheduling,
 * utilization/assignment tracking). Wrapped in DatabaseTransactions so none of this
 * touches the real dev sqlite database beyond the test, matching
 * RfiSubmittalDocumentVersionTest/ApprovalChainTest's own convention.
 */
class EquipmentTest extends TestCase
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

    public function test_equipment_can_be_created_and_concurrent_double_booking_is_rejected_until_returned(): void
    {
        $company = $this->makeCompany('EQ');
        $owner = $this->makeUser($company, 'owner', 'EQ');
        $projectA = $this->makeProject($company, 'EQA');
        $projectB = $this->makeProject($company, 'EQB');

        $create = $this->actingAs($owner)->post('/app/equipment', [
            'name' => 'Excavator 20T',
            'category' => 'Excavator',
            'ownership_type' => 'owned',
        ]);
        $equipment = Equipment::where('company_id', $company->id)->first();
        $this->assertNotNull($equipment);
        $this->assertSame('available', $equipment->status);
        $create->assertRedirect('/app/equipment/' . $equipment->id);

        // Assign to project A.
        $this->actingAs($owner)->post('/app/equipment/' . $equipment->id . '/assignments', [
            'project_id' => $projectA->id,
            'assigned_date' => now()->format('Y-m-d'),
        ]);
        $equipment->refresh();
        $this->assertSame('in_use', $equipment->status);
        $this->assertSame(1, EquipmentAssignment::where('equipment_id', $equipment->id)->whereNull('returned_date')->count());

        // A second concurrent assignment to a DIFFERENT project while the first is still
        // unreturned must be rejected — a single asset can't be in two places at once.
        $this->actingAs($owner)->post('/app/equipment/' . $equipment->id . '/assignments', [
            'project_id' => $projectB->id,
            'assigned_date' => now()->format('Y-m-d'),
        ]);
        $this->assertSame(1, EquipmentAssignment::where('equipment_id', $equipment->id)->count(), 'the second concurrent assignment must have been rejected');
        $this->assertSame($projectA->id, EquipmentAssignment::where('equipment_id', $equipment->id)->first()->project_id);

        // Return it — status flips back to available.
        $activeAssignment = EquipmentAssignment::where('equipment_id', $equipment->id)->whereNull('returned_date')->first();
        $this->actingAs($owner)->post('/app/equipment-assignments/' . $activeAssignment->id . '/return');
        $equipment->refresh();
        $this->assertSame('available', $equipment->status);
        $activeAssignment->refresh();
        $this->assertNotNull($activeAssignment->returned_date);

        // Now a second assignment (to project B) succeeds.
        $this->actingAs($owner)->post('/app/equipment/' . $equipment->id . '/assignments', [
            'project_id' => $projectB->id,
            'assigned_date' => now()->format('Y-m-d'),
        ]);
        $equipment->refresh();
        $this->assertSame('in_use', $equipment->status);
        $this->assertSame(2, EquipmentAssignment::where('equipment_id', $equipment->id)->count());
        $this->assertSame($projectB->id, EquipmentAssignment::where('equipment_id', $equipment->id)->whereNull('returned_date')->first()->project_id);
    }

    public function test_equipment_can_also_be_assigned_and_returned_from_the_project_show_page(): void
    {
        $company = $this->makeCompany('EQP');
        $owner = $this->makeUser($company, 'owner', 'EQP');
        $project = $this->makeProject($company, 'EQP');
        $equipment = Equipment::create(['company_id' => $company->id, 'name' => 'Generator 100kVA', 'status' => 'available']);

        $this->actingAs($owner)->post('/app/projects/' . $project->id . '/equipment-assignments', [
            'equipment_id' => $equipment->id,
            'assigned_date' => now()->format('Y-m-d'),
        ]);
        $equipment->refresh();
        $this->assertSame('in_use', $equipment->status);

        $show = $this->actingAs($owner)->get('/app/projects/' . $project->id);
        $show->assertOk();
        $show->assertSee('Generator 100kVA');

        $assignment = EquipmentAssignment::where('equipment_id', $equipment->id)->whereNull('returned_date')->first();
        $this->actingAs($owner)->post('/app/equipment-assignments/' . $assignment->id . '/return', ['from' => 'project']);
        $equipment->refresh();
        $this->assertSame('available', $equipment->status);
    }

    public function test_maintenance_log_reminder_fires_once_and_is_not_duplicated_on_a_second_run(): void
    {
        $company = $this->makeCompany('EQM');
        $owner = $this->makeUser($company, 'owner', 'EQM');
        $equipment = Equipment::create(['company_id' => $company->id, 'name' => 'Concrete Mixer', 'status' => 'available']);

        $this->actingAs($owner)->post('/app/equipment/' . $equipment->id . '/maintenance', [
            'maintenance_date' => now()->format('Y-m-d'),
            'description' => 'Oil change and filter replacement',
            'next_due_date' => now()->addDays(3)->format('Y-m-d'),
        ]);
        $log = EquipmentMaintenanceLog::where('equipment_id', $equipment->id)->first();
        $this->assertNotNull($log);
        $this->assertNull($log->reminder_sent_at);

        // First run of the daily job: the reminder fires (guard was null, next_due_date is
        // within 7 days) and reminder_sent_at gets stamped.
        Artisan::call('app:daily-tasks');
        $firstRunOutput = Artisan::output();
        $this->assertStringContainsString('Equipment maintenance reminders sent: 1', $firstRunOutput);
        $log->refresh();
        $this->assertNotNull($log->reminder_sent_at, 'reminder_sent_at must be stamped after the first run');
        $sentAtAfterFirstRun = $log->reminder_sent_at;

        // Second run the same day: the reminder_sent_at guard means nothing fires again.
        Artisan::call('app:daily-tasks');
        $secondRunOutput = Artisan::output();
        $this->assertStringContainsString('Equipment maintenance reminders sent: 0', $secondRunOutput);
        $log->refresh();
        $this->assertSame($sentAtAfterFirstRun->format('Y-m-d H:i:s'), $log->reminder_sent_at->format('Y-m-d H:i:s'), 'reminder_sent_at must not change on the second run');
    }

    public function test_equipment_with_no_history_can_be_deleted_but_equipment_with_history_is_blocked(): void
    {
        $company = $this->makeCompany('EQD');
        $owner = $this->makeUser($company, 'owner', 'EQD');
        $project = $this->makeProject($company, 'EQD');

        // No history at all — deletable.
        $clean = Equipment::create(['company_id' => $company->id, 'name' => 'Spare Scaffolding Set', 'status' => 'available']);
        $this->actingAs($owner)->post('/app/equipment/' . $clean->id . '/delete');
        $this->assertSame(0, Equipment::where('id', $clean->id)->count());

        // Has maintenance history — blocked.
        $withMaintenance = Equipment::create(['company_id' => $company->id, 'name' => 'Tower Crane', 'status' => 'available']);
        EquipmentMaintenanceLog::create([
            'company_id' => $company->id,
            'equipment_id' => $withMaintenance->id,
            'maintenance_date' => now()->format('Y-m-d'),
            'description' => 'Annual inspection',
        ]);
        $this->actingAs($owner)->post('/app/equipment/' . $withMaintenance->id . '/delete');
        $this->assertSame(1, Equipment::where('id', $withMaintenance->id)->count(), 'equipment with maintenance history must not be deleted');

        // Has assignment history — also blocked, even after being returned.
        $withAssignment = Equipment::create(['company_id' => $company->id, 'name' => 'Pickup Truck', 'status' => 'available']);
        EquipmentAssignment::create([
            'company_id' => $company->id,
            'equipment_id' => $withAssignment->id,
            'project_id' => $project->id,
            'assigned_date' => now()->subDays(10)->format('Y-m-d'),
            'returned_date' => now()->format('Y-m-d'),
        ]);
        $this->actingAs($owner)->post('/app/equipment/' . $withAssignment->id . '/delete');
        $this->assertSame(1, Equipment::where('id', $withAssignment->id)->count(), 'equipment with assignment history must not be deleted');
    }

    public function test_equipment_feature_gate_blocks_creation_when_plan_lacks_it(): void
    {
        $company = $this->makeCompany('EQOFF', withEquipment: false);
        $owner = $this->makeUser($company, 'owner', 'EQOFF');

        $response = $this->actingAs($owner)->post('/app/equipment', ['name' => 'Should be blocked']);
        $response->assertRedirect('/app/billing');
        $this->assertSame(0, Equipment::where('company_id', $company->id)->count());
    }

    public function test_equipment_is_completely_invisible_and_blocked_across_companies(): void
    {
        $companyA = $this->makeCompany('EQTA');
        $companyB = $this->makeCompany('EQTB');
        $ownerA = $this->makeUser($companyA, 'owner', 'EQTA');
        $ownerB = $this->makeUser($companyB, 'owner', 'EQTB');
        $projectA = $this->makeProject($companyA, 'EQTA');
        $projectB = $this->makeProject($companyB, 'EQTB');

        $this->actingAs($ownerA)->post('/app/equipment', ['name' => 'Company A only']);
        $equipmentA = Equipment::where('company_id', $companyA->id)->first();
        $this->actingAs($ownerA)->post('/app/equipment/' . $equipmentA->id . '/assignments', ['project_id' => $projectA->id]);
        $assignmentA = EquipmentAssignment::where('equipment_id', $equipmentA->id)->first();

        // Company B cannot view, edit, delete, log maintenance on, assign, or return
        // company A's equipment/assignment.
        $this->actingAs($ownerB)->get('/app/equipment/' . $equipmentA->id)->assertNotFound();
        $this->actingAs($ownerB)->get('/app/equipment/' . $equipmentA->id . '/edit')->assertNotFound();
        $this->actingAs($ownerB)->post('/app/equipment/' . $equipmentA->id, ['name' => 'intrusion'])->assertNotFound();
        $this->actingAs($ownerB)->post('/app/equipment/' . $equipmentA->id . '/delete')->assertNotFound();
        $this->actingAs($ownerB)->post('/app/equipment/' . $equipmentA->id . '/assignments', ['project_id' => $projectB->id])->assertNotFound();
        $this->actingAs($ownerB)->post('/app/equipment/' . $equipmentA->id . '/maintenance', ['description' => 'intrusion'])->assertNotFound();
        $this->actingAs($ownerB)->post('/app/equipment-assignments/' . $assignmentA->id . '/return')->assertNotFound();
        // Company B cannot reach into company A's equipment via its OWN project-nested route either.
        $this->actingAs($ownerB)->post('/app/projects/' . $projectB->id . '/equipment-assignments', ['equipment_id' => $equipmentA->id]);
        $this->assertSame(1, EquipmentAssignment::where('equipment_id', $equipmentA->id)->count(), 'cross-tenant equipment_id must never be accepted');
        // Company A's own assignment is untouched by any of the above.
        $assignmentA->refresh();
        $this->assertNull($assignmentA->returned_date);

        // Company B's own equipment list never contains company A's asset.
        $indexB = $this->actingAs($ownerB)->get('/app/equipment');
        $indexB->assertOk();
        $indexB->assertDontSee('Company A only');
    }
}
