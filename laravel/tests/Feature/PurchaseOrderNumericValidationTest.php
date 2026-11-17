<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to PurchaseOrderController::store() for the
 * PO create form — line-item qty/unit price and issue/expected-delivery dates, none of
 * which had any type or range check before (qty/price were only ever (float)-cast).
 * Mirrors EstimateNumericValidationTest's own shape and reasoning.
 */
class PurchaseOrderNumericValidationTest extends TestCase
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

    private function makeCompanyWithOwnerAndProject(string $tag): array
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['purchase_orders' => true]),
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

        $project = Project::create([
            'company_id' => $company->id,
            'name' => "{$tag} Project",
            'status' => 'in_progress',
            'budget' => 100000,
        ]);

        return compact('company', 'owner', 'project');
    }

    private function basePayload(): array
    {
        return [
            'item_description' => ['Cement bags'],
            'item_qty' => [10],
            'item_price' => [50],
        ];
    }

    public function test_store_rejects_non_numeric_quantity(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('QtyNaN');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/purchase-orders', [
            'item_description' => ['Cement bags'],
            'item_qty' => ['not-a-number'],
            'item_price' => [50],
        ]);

        $response->assertRedirect();
        $this->assertSame(0, PurchaseOrder::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_zero_quantity(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('QtyZero');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/purchase-orders', [
            'item_description' => ['Cement bags'],
            'item_qty' => [0],
            'item_price' => [50],
        ]);

        $response->assertRedirect();
        $this->assertSame(0, PurchaseOrder::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_negative_unit_price(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('PriceNeg');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/purchase-orders', [
            'item_description' => ['Cement bags'],
            'item_qty' => [10],
            'item_price' => [-5],
        ]);

        $response->assertRedirect();
        $this->assertSame(0, PurchaseOrder::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_blank_unit_price_as_zero(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('PriceBlank');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/purchase-orders', [
            'item_description' => ['Free sample'],
            'item_qty' => [1],
            'item_price' => [''],
        ]);

        $response->assertRedirect();
        $po = PurchaseOrder::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($po);
        $this->assertSame('0.00', $po->subtotal);
    }

    public function test_store_rejects_malformed_issue_date(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('DateBad');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/purchase-orders', [
            ...$this->basePayload(),
            'issue_date' => '2026-99-99',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, PurchaseOrder::where('company_id', $a['company']->id)->count());
    }

    public function test_store_truncates_overlong_notes(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('NotesLong');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/purchase-orders', [
            ...$this->basePayload(),
            'notes' => str_repeat('x', 400),
        ]);

        $response->assertRedirect();
        $po = PurchaseOrder::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($po);
        $this->assertSame(255, mb_strlen($po->notes));
    }

    public function test_store_accepts_a_valid_payload_and_computes_totals(): void
    {
        $a = $this->makeCompanyWithOwnerAndProject('Happy');

        $response = $this->actingAs($a['owner'])->post('/app/projects/' . $a['project']->id . '/purchase-orders', [
            ...$this->basePayload(),
            'status' => 'issued',
        ]);

        $response->assertRedirect();
        $po = PurchaseOrder::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($po);
        $this->assertSame('500.00', $po->subtotal);
    }
}
