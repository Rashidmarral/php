<?php

namespace Tests\Feature;

use App\Models\BoqItem;
use App\Models\Company;
use App\Models\PaymentCertificate;
use App\Models\Plan;
use App\Models\Project;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to PaymentCertificateController's
 * buildLines()/validatedCertificateInputs() — per-line cumulative_qty,
 * retention_percent, advance_recovery_percent, and every date field, none of
 * which had any type check before (cumulative_qty and the two percentages were
 * only ever (float)-cast, or clamped with min()/max() instead of being
 * rejected). Mirrors ProjectNumericValidationTest's own shape and reasoning.
 */
class PaymentCertificateNumericValidationTest extends TestCase
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
            'feature_flags' => json_encode(['payment_certificates' => true]),
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

    private function makeProject(Company $company, string $tag, float $advancePaymentAmount = 0): Project
    {
        return Project::create([
            'company_id' => $company->id,
            'name' => "Project {$tag}",
            'status' => 'in_progress',
            'budget' => 500000,
            'advance_payment_amount' => $advancePaymentAmount,
        ]);
    }

    private function makeBoqItem(Project $project, string $tag): BoqItem
    {
        return BoqItem::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'item_number' => '1.0',
            'description' => "BOQ line {$tag}",
            'uom' => 'LS',
            'qty' => 100,
            'unit_price' => 50,
            'total' => 5000,
            'sort_order' => 1,
        ]);
    }

    public function test_store_rejects_non_numeric_line_quantity(): void
    {
        $a = $this->makeCompanyWithOwner('PcNaN');
        $project = $this->makeProject($a['company'], 'PcNaN');
        $boqItem = $this->makeBoqItem($project, 'PcNaN');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/payment-certificates', [
            'certificate_date' => now()->format('Y-m-d'),
            'boq_item_id' => [$boqItem->id],
            'cumulative_qty' => ['not-a-number'],
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/payment-certificates/create');
        $this->assertSame(0, PaymentCertificate::where('project_id', $project->id)->count());
    }

    public function test_store_rejects_line_quantity_exceeding_boq_contract_quantity(): void
    {
        $a = $this->makeCompanyWithOwner('PcOver');
        $project = $this->makeProject($a['company'], 'PcOver');
        $boqItem = $this->makeBoqItem($project, 'PcOver');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/payment-certificates', [
            'certificate_date' => now()->format('Y-m-d'),
            'boq_item_id' => [$boqItem->id],
            'cumulative_qty' => [150],
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/payment-certificates/create');
        $this->assertSame(0, PaymentCertificate::where('project_id', $project->id)->count());
    }

    public function test_store_rejects_out_of_range_retention_percent(): void
    {
        $a = $this->makeCompanyWithOwner('PcRetHigh');
        $project = $this->makeProject($a['company'], 'PcRetHigh');
        $boqItem = $this->makeBoqItem($project, 'PcRetHigh');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/payment-certificates', [
            'certificate_date' => now()->format('Y-m-d'),
            'boq_item_id' => [$boqItem->id],
            'cumulative_qty' => [50],
            'retention_percent' => '150',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/payment-certificates/create');
        $this->assertSame(0, PaymentCertificate::where('project_id', $project->id)->count());
    }

    public function test_store_rejects_out_of_range_advance_recovery_percent_when_project_has_advance(): void
    {
        $a = $this->makeCompanyWithOwner('PcAdvHigh');
        $project = $this->makeProject($a['company'], 'PcAdvHigh', 20000);
        $boqItem = $this->makeBoqItem($project, 'PcAdvHigh');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/payment-certificates', [
            'certificate_date' => now()->format('Y-m-d'),
            'boq_item_id' => [$boqItem->id],
            'cumulative_qty' => [50],
            'advance_recovery_percent' => 'abc',
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/payment-certificates/create');
        $this->assertSame(0, PaymentCertificate::where('project_id', $project->id)->count());
    }

    public function test_store_rejects_malformed_certificate_date(): void
    {
        $a = $this->makeCompanyWithOwner('PcDateBad');
        $project = $this->makeProject($a['company'], 'PcDateBad');
        $boqItem = $this->makeBoqItem($project, 'PcDateBad');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/payment-certificates', [
            'certificate_date' => '2026-13-40',
            'boq_item_id' => [$boqItem->id],
            'cumulative_qty' => [50],
        ]);

        $response->assertRedirect('/app/projects/'.$project->id.'/payment-certificates/create');
        $this->assertSame(0, PaymentCertificate::where('project_id', $project->id)->count());
    }

    public function test_store_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('PcHappy');
        $project = $this->makeProject($a['company'], 'PcHappy');
        $boqItem = $this->makeBoqItem($project, 'PcHappy');

        $response = $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/payment-certificates', [
            'certificate_date' => now()->format('Y-m-d'),
            'boq_item_id' => [$boqItem->id],
            'cumulative_qty' => [50],
            'retention_percent' => '10',
        ]);

        $response->assertRedirect();
        $certificate = PaymentCertificate::where('project_id', $project->id)->first();
        $this->assertNotNull($certificate);
        $this->assertSame('2500.00', $certificate->gross_amount);
        $this->assertSame('10.00', $certificate->retention_percent);
    }

    public function test_update_rejects_out_of_range_retention_percent_and_leaves_stored_value(): void
    {
        $a = $this->makeCompanyWithOwner('PcUpdateRet');
        $project = $this->makeProject($a['company'], 'PcUpdateRet');
        $boqItem = $this->makeBoqItem($project, 'PcUpdateRet');

        $this->actingAs($a['owner'])->post('/app/projects/'.$project->id.'/payment-certificates', [
            'certificate_date' => now()->format('Y-m-d'),
            'boq_item_id' => [$boqItem->id],
            'cumulative_qty' => [50],
            'retention_percent' => '5',
        ]);
        $certificate = PaymentCertificate::where('project_id', $project->id)->first();

        $response = $this->actingAs($a['owner'])->post('/app/payment-certificates/'.$certificate->id, [
            'certificate_date' => now()->format('Y-m-d'),
            'boq_item_id' => [$boqItem->id],
            'cumulative_qty' => [60],
            'retention_percent' => '-5',
        ]);

        $response->assertRedirect('/app/payment-certificates/'.$certificate->id.'/edit');
        $certificate->refresh();
        $this->assertSame('5.00', $certificate->retention_percent);
        $this->assertSame('2500.00', $certificate->gross_amount);
    }
}
