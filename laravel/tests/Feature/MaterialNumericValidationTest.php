<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric validation and cross-tenant supplier check added to
 * MaterialController::fromInput() (shared by store()/update()) — material_cost/
 * labor_cost/reorder_level, none of which had any type or range check before (each
 * was only ever (float)-cast), and supplier_id, which was previously trusted from
 * the request with no ownership check at all. Mirrors EstimateNumericValidationTest's
 * own shape.
 */
class MaterialNumericValidationTest extends TestCase
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
            'feature_flags' => json_encode(['materials' => true]),
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

    public function test_store_rejects_non_numeric_material_cost(): void
    {
        $a = $this->makeCompanyWithOwner('CostNaN');

        $response = $this->actingAs($a['owner'])->post('/app/materials', [
            'name' => 'Cement 50kg',
            'material_cost' => 'abc',
        ]);

        $response->assertRedirect('/app/materials/create');
        $this->assertSame(0, Material::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_labor_cost(): void
    {
        $a = $this->makeCompanyWithOwner('LaborNeg');

        $response = $this->actingAs($a['owner'])->post('/app/materials', [
            'name' => 'Cement 50kg',
            'labor_cost' => '-10',
        ]);

        $response->assertRedirect('/app/materials/create');
        $this->assertSame(0, Material::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_out_of_range_reorder_level(): void
    {
        $a = $this->makeCompanyWithOwner('ReorderMax');

        $response = $this->actingAs($a['owner'])->post('/app/materials', [
            'name' => 'Cement 50kg',
            'reorder_level' => '99999999999999',
        ]);

        $response->assertRedirect('/app/materials/create');
        $this->assertSame(0, Material::where('company_id', $a['company']->id)->count());
    }

    public function test_store_never_attaches_another_companys_supplier(): void
    {
        $a = $this->makeCompanyWithOwner('CrossTenantA');
        $b = $this->makeCompanyWithOwner('CrossTenantB');
        $otherSupplier = Supplier::create(['company_id' => $b['company']->id, 'name' => 'Other Co Supplier']);

        $response = $this->actingAs($a['owner'])->post('/app/materials', [
            'name' => 'Cement 50kg',
            'supplier_id' => $otherSupplier->id,
        ]);

        $response->assertRedirect();
        $material = Material::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($material);
        $this->assertNull($material->supplier_id);
    }

    public function test_store_truncates_overlong_name(): void
    {
        $a = $this->makeCompanyWithOwner('NameLong');

        $response = $this->actingAs($a['owner'])->post('/app/materials', [
            'name' => str_repeat('A', 300),
        ]);

        $response->assertRedirect();
        $material = Material::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($material);
        $this->assertSame(150, mb_strlen($material->name));
    }

    public function test_store_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('Happy');
        $supplier = Supplier::create(['company_id' => $a['company']->id, 'name' => 'ACME Supplier']);

        $response = $this->actingAs($a['owner'])->post('/app/materials', [
            'name' => 'Cement 50kg',
            'material_cost' => '15.50',
            'labor_cost' => '2.50',
            'supplier_id' => $supplier->id,
        ]);

        $response->assertRedirect();
        $material = Material::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($material);
        $this->assertSame('15.50', $material->material_cost);
        $this->assertSame('18.00', $material->unit_cost);
        $this->assertSame($supplier->id, $material->supplier_id);
    }

    public function test_update_rejects_blank_name(): void
    {
        $a = $this->makeCompanyWithOwner('UpdateBlankName');
        $material = Material::create(['company_id' => $a['company']->id, 'name' => 'Existing Material']);

        $response = $this->actingAs($a['owner'])->post('/app/materials/' . $material->id, [
            'name' => '   ',
        ]);

        $response->assertRedirect('/app/materials/' . $material->id . '/edit');
        $material->refresh();
        $this->assertSame('Existing Material', $material->name);
    }
}
