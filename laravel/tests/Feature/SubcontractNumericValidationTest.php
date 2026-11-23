<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to SubcontractController::validated() —
 * contract_value, retention_percent, and start/end dates, none of which had any
 * type or range check before (each was only ever (float)-cast, or clamped with
 * min()/max() instead of being rejected). Mirrors ProjectNumericValidationTest/
 * MaterialNumericValidationTest's own shape and reasoning. Also includes the
 * cross-tenant audit this batch's instructions called for: confirms a supplier_id
 * from another company is rejected by the pre-existing ownedSupplier() guard
 * rather than silently accepted.
 */
class SubcontractNumericValidationTest extends TestCase
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
            'feature_flags' => json_encode(['subcontractors' => true]),
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

    private function makeProject(Company $company, string $tag): Project
    {
        return Project::create([
            'company_id' => $company->id,
            'name' => "Project {$tag}",
            'status' => 'in_progress',
            'budget' => 500000,
        ]);
    }

    private function makeSupplier(Company $company, string $tag): Supplier
    {
        return Supplier::create(['company_id' => $company->id, 'name' => "Subcontractor {$tag}"]);
    }

    public function test_store_rejects_non_numeric_contract_value(): void
    {
        $a = $this->makeCompanyWithOwner('ScNaN');
        $project = $this->makeProject($a['company'], 'ScNaN');
        $supplier = $this->makeSupplier($a['company'], 'ScNaN');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => 'Electrical works',
            'contract_value' => 'not-a-number',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/subcontracts/create');
        $this->assertSame(0, Subcontract::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_zero_contract_value(): void
    {
        $a = $this->makeCompanyWithOwner('ScZero');
        $project = $this->makeProject($a['company'], 'ScZero');
        $supplier = $this->makeSupplier($a['company'], 'ScZero');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => 'Electrical works',
            'contract_value' => '0',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/subcontracts/create');
        $this->assertSame(0, Subcontract::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_retention_percent_above_100(): void
    {
        $a = $this->makeCompanyWithOwner('ScRetHigh');
        $project = $this->makeProject($a['company'], 'ScRetHigh');
        $supplier = $this->makeSupplier($a['company'], 'ScRetHigh');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => 'Electrical works',
            'contract_value' => '50000',
            'retention_percent' => '150',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/subcontracts/create');
        $this->assertSame(0, Subcontract::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_start_date(): void
    {
        $a = $this->makeCompanyWithOwner('ScDateBad');
        $project = $this->makeProject($a['company'], 'ScDateBad');
        $supplier = $this->makeSupplier($a['company'], 'ScDateBad');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => 'Electrical works',
            'contract_value' => '50000',
            'start_date' => '2026-13-40',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/subcontracts/create');
        $this->assertSame(0, Subcontract::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_blank_title(): void
    {
        $a = $this->makeCompanyWithOwner('ScNoTitle');
        $project = $this->makeProject($a['company'], 'ScNoTitle');
        $supplier = $this->makeSupplier($a['company'], 'ScNoTitle');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => '   ',
            'contract_value' => '50000',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/subcontracts/create');
        $this->assertSame(0, Subcontract::where('company_id', $a['company']->id)->count());
    }

    public function test_store_truncates_overlong_title(): void
    {
        $a = $this->makeCompanyWithOwner('ScTitleLong');
        $project = $this->makeProject($a['company'], 'ScTitleLong');
        $supplier = $this->makeSupplier($a['company'], 'ScTitleLong');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => str_repeat('A', 300),
            'contract_value' => '50000',
        ]);

        $response->assertRedirect();
        $subcontract = Subcontract::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($subcontract);
        $this->assertSame(150, mb_strlen($subcontract->title));
    }

    public function test_store_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('ScHappy');
        $project = $this->makeProject($a['company'], 'ScHappy');
        $supplier = $this->makeSupplier($a['company'], 'ScHappy');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/subcontracts', [
            'supplier_id' => $supplier->id,
            'title' => 'Electrical works',
            'contract_value' => '75000',
            'retention_percent' => '10',
            'start_date' => '2027-01-01',
            'end_date' => '2027-06-30',
        ]);

        $response->assertRedirect();
        $subcontract = Subcontract::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($subcontract);
        $this->assertSame('75000.00', $subcontract->contract_value);
        $this->assertSame('10.00', $subcontract->retention_percent);
    }

    public function test_update_rejects_out_of_range_retention_percent_and_leaves_stored_value(): void
    {
        $a = $this->makeCompanyWithOwner('ScUpdateRet');
        $project = $this->makeProject($a['company'], 'ScUpdateRet');
        $supplier = $this->makeSupplier($a['company'], 'ScUpdateRet');
        $subcontract = Subcontract::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'supplier_id' => $supplier->id,
            'title' => 'Existing subcontract',
            'contract_value' => 60000,
            'retention_percent' => 5,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/subcontracts/'.$subcontract->id, [
            'supplier_id' => $supplier->id,
            'title' => 'Existing subcontract',
            'contract_value' => '60000',
            'retention_percent' => '-10',
        ]);

        $response->assertRedirect('/app/subcontracts/'.$subcontract->id.'/edit');
        $subcontract->refresh();
        $this->assertSame('5.00', $subcontract->retention_percent);
    }

    /**
     * Cross-tenant audit (as this batch's instructions required): a supplier_id
     * belonging to a DIFFERENT company must never be accepted as this subcontract's
     * subcontractor — confirms the pre-existing ownedSupplier() guard actually holds,
     * not just that it's present in the source.
     */
    public function test_store_rejects_a_supplier_id_belonging_to_another_company(): void
    {
        $a = $this->makeCompanyWithOwner('ScTenantA');
        $b = $this->makeCompanyWithOwner('ScTenantB');
        $project = $this->makeProject($a['company'], 'ScTenantA');
        $foreignSupplier = $this->makeSupplier($b['company'], 'ScTenantB');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/subcontracts', [
            'supplier_id' => $foreignSupplier->id,
            'title' => 'Intrusion attempt',
            'contract_value' => '10000',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/subcontracts/create');
        $this->assertSame(0, Subcontract::where('company_id', $a['company']->id)->count());
    }
}
