<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to SubcontractPaymentController::buildPayment()
 * — cumulative_value, retention_percent, and payment_date, none of which had any type
 * check before (cumulative_value was only ever (float)-cast, so a non-numeric value
 * silently became 0 instead of being rejected, which would have let a bad payload sail
 * straight past the "cannot be less than previous" guard). Mirrors
 * ProjectNumericValidationTest/SubcontractNumericValidationTest's own shape.
 */
class SubcontractPaymentNumericValidationTest extends TestCase
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

    private function makeSubcontract(Company $company, string $tag, float $contractValue = 100000): Subcontract
    {
        $project = Project::create(['company_id' => $company->id, 'name' => "Project {$tag}", 'status' => 'in_progress', 'budget' => 500000]);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => "Subcontractor {$tag}"]);

        return Subcontract::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'supplier_id' => $supplier->id,
            'title' => "Subcontract {$tag}",
            'contract_value' => $contractValue,
            'retention_percent' => 10,
        ]);
    }

    public function test_store_rejects_non_numeric_cumulative_value(): void
    {
        $a = $this->makeCompanyWithOwner('SpNaN');
        $subcontract = $this->makeSubcontract($a['company'], 'SpNaN');

        $response = $this->actingAs($a['owner'])->post('/app/subcontracts/'.$subcontract->id.'/payments', [
            'cumulative_value' => 'not-a-number',
        ]);

        $response->assertRedirect('/app/subcontracts/'.$subcontract->id.'/payments/create');
        $this->assertSame(0, SubcontractPayment::where('subcontract_id', $subcontract->id)->count());
    }

    public function test_store_rejects_cumulative_value_exceeding_contract_value(): void
    {
        $a = $this->makeCompanyWithOwner('SpOver');
        $subcontract = $this->makeSubcontract($a['company'], 'SpOver', 50000);

        $response = $this->actingAs($a['owner'])->post('/app/subcontracts/'.$subcontract->id.'/payments', [
            'cumulative_value' => '60000',
        ]);

        $response->assertRedirect('/app/subcontracts/'.$subcontract->id.'/payments/create');
        $this->assertSame(0, SubcontractPayment::where('subcontract_id', $subcontract->id)->count());
    }

    public function test_store_rejects_cumulative_value_below_previous_certified(): void
    {
        $a = $this->makeCompanyWithOwner('SpBelow');
        $subcontract = $this->makeSubcontract($a['company'], 'SpBelow', 100000);
        SubcontractPayment::create([
            'company_id' => $a['company']->id, 'subcontract_id' => $subcontract->id,
            'payment_number' => 1, 'payment_date' => now()->format('Y-m-d'), 'status' => 'certified',
            'cumulative_value' => 40000, 'previous_cumulative_value' => 0, 'this_period_value' => 40000,
            'retention_amount' => 0, 'net_payable' => 40000,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/subcontracts/'.$subcontract->id.'/payments', [
            'cumulative_value' => '30000',
        ]);

        $response->assertRedirect('/app/subcontracts/'.$subcontract->id.'/payments/create');
        $this->assertSame(1, SubcontractPayment::where('subcontract_id', $subcontract->id)->count());
    }

    public function test_store_rejects_out_of_range_retention_percent(): void
    {
        $a = $this->makeCompanyWithOwner('SpRetHigh');
        $subcontract = $this->makeSubcontract($a['company'], 'SpRetHigh', 100000);

        $response = $this->actingAs($a['owner'])->post('/app/subcontracts/'.$subcontract->id.'/payments', [
            'cumulative_value' => '20000',
            'retention_percent' => '150',
        ]);

        $response->assertRedirect('/app/subcontracts/'.$subcontract->id.'/payments/create');
        $this->assertSame(0, SubcontractPayment::where('subcontract_id', $subcontract->id)->count());
    }

    public function test_store_rejects_malformed_payment_date(): void
    {
        $a = $this->makeCompanyWithOwner('SpDateBad');
        $subcontract = $this->makeSubcontract($a['company'], 'SpDateBad', 100000);

        $response = $this->actingAs($a['owner'])->post('/app/subcontracts/'.$subcontract->id.'/payments', [
            'cumulative_value' => '20000',
            'payment_date' => '2026-13-40',
        ]);

        $response->assertRedirect('/app/subcontracts/'.$subcontract->id.'/payments/create');
        $this->assertSame(0, SubcontractPayment::where('subcontract_id', $subcontract->id)->count());
    }

    public function test_store_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('SpHappy');
        $subcontract = $this->makeSubcontract($a['company'], 'SpHappy', 100000);

        $response = $this->actingAs($a['owner'])->post('/app/subcontracts/'.$subcontract->id.'/payments', [
            'cumulative_value' => '40000',
            'retention_percent' => '10',
            'payment_date' => '2027-02-01',
        ]);

        $response->assertRedirect();
        $payment = SubcontractPayment::where('subcontract_id', $subcontract->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('40000.00', $payment->cumulative_value);
        $this->assertSame('36000.00', $payment->net_payable);
    }

    public function test_update_rejects_non_numeric_cumulative_value_and_leaves_stored_value(): void
    {
        $a = $this->makeCompanyWithOwner('SpUpdateNaN');
        $subcontract = $this->makeSubcontract($a['company'], 'SpUpdateNaN', 100000);
        $payment = SubcontractPayment::create([
            'company_id' => $a['company']->id, 'subcontract_id' => $subcontract->id,
            'payment_number' => 1, 'payment_date' => now()->format('Y-m-d'), 'status' => 'draft',
            'cumulative_value' => 30000, 'previous_cumulative_value' => 0, 'this_period_value' => 30000,
            'retention_percent' => 10, 'retention_amount' => 3000, 'net_payable' => 27000,
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/subcontract-payments/'.$payment->id, [
            'cumulative_value' => 'garbage',
        ]);

        $response->assertRedirect('/app/subcontract-payments/'.$payment->id.'/edit');
        $payment->refresh();
        $this->assertSame('30000.00', $payment->cumulative_value);
    }
}
