<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Equipment;
use App\Models\EquipmentMaintenanceLog;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to EquipmentController::fields() (shared
 * by store()/update()) and storeMaintenanceLog() — purchase_cost/rental_cost_per_day/
 * purchase_date, and the maintenance log's cost/dates, none of which had any type or
 * range check before (each was only ever (float)-cast or passed through unchecked).
 * Mirrors EstimateNumericValidationTest's own shape.
 */
class EquipmentNumericValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\App\Support\Feature::class);
        $ref->setStaticPropertyValue('cachedPlan', null);
        $ref->setStaticPropertyValue('planResolved', false);
        $ref->setStaticPropertyValue('cachedFlags', null);
    }

    private function makeCompanyWithOwner(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['equipment_management' => true]),
        ]);

        $company = Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);

        $owner = User::create([
            'company_id' => $company->id,
            'name' => "{$tag} Owner",
            'email' => strtolower($tag) . '-owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        return compact('company', 'owner');
    }

    public function test_store_rejects_non_numeric_purchase_cost(): void
    {
        $a = $this->makeCompanyWithOwner('CostNaN');

        $response = $this->actingAs($a['owner'])->post('/app/equipment', [
            'name' => 'Excavator CAT 320',
            'purchase_cost' => 'abc',
        ]);

        $response->assertRedirect('/app/equipment/create');
        $this->assertSame(0, Equipment::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_rental_cost_per_day(): void
    {
        $a = $this->makeCompanyWithOwner('RentalNeg');

        $response = $this->actingAs($a['owner'])->post('/app/equipment', [
            'name' => 'Excavator CAT 320',
            'rental_cost_per_day' => '-100',
        ]);

        $response->assertRedirect('/app/equipment/create');
        $this->assertSame(0, Equipment::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_purchase_date(): void
    {
        $a = $this->makeCompanyWithOwner('DateBad');

        $response = $this->actingAs($a['owner'])->post('/app/equipment', [
            'name' => 'Excavator CAT 320',
            'purchase_date' => '2026-02-30',
        ]);

        $response->assertRedirect('/app/equipment/create');
        $this->assertSame(0, Equipment::where('company_id', $a['company']->id)->count());
    }

    public function test_store_truncates_overlong_name(): void
    {
        $a = $this->makeCompanyWithOwner('NameLong');

        $response = $this->actingAs($a['owner'])->post('/app/equipment', [
            'name' => str_repeat('A', 300),
        ]);

        $response->assertRedirect();
        $equipment = Equipment::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($equipment);
        $this->assertSame(150, mb_strlen($equipment->name));
    }

    public function test_store_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('Happy');

        $response = $this->actingAs($a['owner'])->post('/app/equipment', [
            'name' => 'Excavator CAT 320',
            'purchase_cost' => '450000',
            'rental_cost_per_day' => '1200',
            'purchase_date' => '2025-01-01',
        ]);

        $response->assertRedirect();
        $equipment = Equipment::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($equipment);
        $this->assertSame('450000.00', $equipment->purchase_cost);
        $this->assertSame('1200.00', $equipment->rental_cost_per_day);
    }

    public function test_maintenance_log_rejects_non_numeric_cost(): void
    {
        $a = $this->makeCompanyWithOwner('MaintCostNaN');
        $equipment = Equipment::create(['company_id' => $a['company']->id, 'name' => 'Generator', 'status' => 'available']);

        $response = $this->actingAs($a['owner'])->post('/app/equipment/' . $equipment->id . '/maintenance', [
            'description' => 'Oil change',
            'cost' => 'abc',
        ]);

        $response->assertRedirect('/app/equipment/' . $equipment->id);
        $this->assertSame(0, EquipmentMaintenanceLog::where('equipment_id', $equipment->id)->count());
    }

    public function test_maintenance_log_rejects_malformed_next_due_date(): void
    {
        $a = $this->makeCompanyWithOwner('MaintDateBad');
        $equipment = Equipment::create(['company_id' => $a['company']->id, 'name' => 'Generator', 'status' => 'available']);

        $response = $this->actingAs($a['owner'])->post('/app/equipment/' . $equipment->id . '/maintenance', [
            'description' => 'Oil change',
            'next_due_date' => '2026-02-30',
        ]);

        $response->assertRedirect('/app/equipment/' . $equipment->id);
        $this->assertSame(0, EquipmentMaintenanceLog::where('equipment_id', $equipment->id)->count());
    }

    public function test_maintenance_log_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('MaintHappy');
        $equipment = Equipment::create(['company_id' => $a['company']->id, 'name' => 'Generator', 'status' => 'available']);

        $response = $this->actingAs($a['owner'])->post('/app/equipment/' . $equipment->id . '/maintenance', [
            'description' => 'Oil change',
            'cost' => '350.50',
            'next_due_date' => '2027-01-01',
        ]);

        $response->assertRedirect('/app/equipment/' . $equipment->id);
        $log = EquipmentMaintenanceLog::where('equipment_id', $equipment->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('350.50', $log->cost);
    }
}
