<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqQuote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the RFQ / multi-supplier quote comparison module: creating an RFQ with line
 * items, recording quotes from multiple suppliers, comparing them, and awarding one.
 * Wrapped in DatabaseTransactions so none of this touches the real dev sqlite database
 * beyond the test, matching SafetyTest/EquipmentTest's own convention.
 */
class RfqTest extends TestCase
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

    private function makeCompany(string $tag, bool $withRfq = true): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode([
                'rfq_quotes' => $withRfq,
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

    private function makeSupplier(Company $company, string $name): Supplier
    {
        return Supplier::create([
            'company_id' => $company->id,
            'name' => $name,
        ]);
    }

    public function test_rfq_can_be_created_with_line_items(): void
    {
        $company = $this->makeCompany('RQ');
        $owner = $this->makeUser($company, 'owner', 'RQ');
        $project = $this->makeProject($company, 'RQ');

        $response = $this->actingAs($owner)->post('/app/rfqs', [
            'title' => 'Rebar supply for Tower A',
            'project_id' => $project->id,
            'due_date' => '2026-11-01',
            'notes' => 'Needed on site before pouring starts.',
            'item_description' => ['Rebar 12mm', 'Rebar 16mm'],
            'item_qty' => [500, 300],
            'item_unit' => ['kg', 'kg'],
        ]);

        $rfq = Rfq::where('company_id', $company->id)->first();
        $this->assertNotNull($rfq);
        $response->assertRedirect('/app/rfqs/' . $rfq->id);
        $this->assertSame('Rebar supply for Tower A', $rfq->title);
        $this->assertSame('draft', $rfq->status);
        $this->assertSame($project->id, $rfq->project_id);
        $this->assertSame((int) $owner->id, (int) $rfq->created_by);

        $items = RfqItem::where('rfq_id', $rfq->id)->orderBy('id')->get();
        $this->assertSame(2, $items->count());
        $this->assertSame('Rebar 12mm', $items[0]->description);
        $this->assertSame('kg', $items[0]->unit);
        $this->assertSame('500.00', $items[0]->qty);
    }

    public function test_quotes_from_multiple_suppliers_can_be_recorded_and_compared(): void
    {
        $company = $this->makeCompany('RQC');
        $owner = $this->makeUser($company, 'owner', 'RQC');
        $supplierA = $this->makeSupplier($company, 'Al Faisal Steel');
        $supplierB = $this->makeSupplier($company, 'Gulf Rebar Co');
        $supplierC = $this->makeSupplier($company, 'Riyadh Metals');

        $this->actingAs($owner)->post('/app/rfqs', [
            'title' => 'Rebar RFQ',
            'item_description' => ['Rebar 12mm'],
            'item_qty' => [500],
        ]);
        $rfq = Rfq::where('company_id', $company->id)->first();
        $this->assertSame('draft', $rfq->status);

        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplierA->id,
            'total_amount' => 15000,
            'lead_time_days' => 10,
        ]);
        // Recording the first quote moves a draft RFQ into "comparing".
        $this->assertSame('comparing', $rfq->fresh()->status);

        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplierB->id,
            'total_amount' => 12000,
            'lead_time_days' => 15,
        ]);
        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplierC->id,
            'total_amount' => 13000,
            'lead_time_days' => 7,
        ]);

        $this->assertSame(3, RfqQuote::where('rfq_id', $rfq->id)->count());

        // The comparison page shows all three quotes side by side.
        $show = $this->actingAs($owner)->get('/app/rfqs/' . $rfq->id);
        $show->assertOk();
        $show->assertSee('Al Faisal Steel');
        $show->assertSee('Gulf Rebar Co');
        $show->assertSee('Riyadh Metals');
    }

    public function test_awarding_one_quote_marks_only_that_one_as_awarded(): void
    {
        $company = $this->makeCompany('RQA');
        $owner = $this->makeUser($company, 'owner', 'RQA');
        $supplierA = $this->makeSupplier($company, 'Supplier A');
        $supplierB = $this->makeSupplier($company, 'Supplier B');

        $this->actingAs($owner)->post('/app/rfqs', ['title' => 'Award test RFQ']);
        $rfq = Rfq::where('company_id', $company->id)->first();

        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplierA->id,
            'total_amount' => 9000,
        ]);
        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplierB->id,
            'total_amount' => 8500,
        ]);
        $quoteA = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplierA->id)->first();
        $quoteB = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplierB->id)->first();

        $response = $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes/' . $quoteB->id . '/award');
        $response->assertRedirect('/app/rfqs/' . $rfq->id);

        $this->assertTrue($quoteB->fresh()->is_awarded);
        $this->assertFalse($quoteA->fresh()->is_awarded);
        $this->assertSame('awarded', $rfq->fresh()->status);
        $this->assertSame(1, RfqQuote::where('rfq_id', $rfq->id)->where('is_awarded', true)->count());

        // Awarding a different quote later would flip the award to that one and unaward
        // the previous winner — exercised here to prove only one is ever awarded at a time.
        $response2 = $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes/' . $quoteA->id . '/award');
        $response2->assertRedirect('/app/rfqs/' . $rfq->id);
        $this->assertTrue($quoteA->fresh()->is_awarded);
        $this->assertFalse($quoteB->fresh()->is_awarded);
        $this->assertSame(1, RfqQuote::where('rfq_id', $rfq->id)->where('is_awarded', true)->count());
    }

    public function test_rfq_feature_gate_blocks_creation_when_plan_lacks_it(): void
    {
        $company = $this->makeCompany('RQG', withRfq: false);
        $owner = $this->makeUser($company, 'owner', 'RQG');

        $response = $this->actingAs($owner)->post('/app/rfqs', ['title' => 'Should be blocked']);
        $response->assertRedirect('/app/billing');
        $this->assertSame(0, Rfq::where('company_id', $company->id)->count());

        $indexResponse = $this->actingAs($owner)->get('/app/rfqs');
        $indexResponse->assertRedirect('/app/billing');
    }

    public function test_rfq_from_company_a_is_completely_invisible_and_blocked_for_company_b(): void
    {
        $companyA = $this->makeCompany('RTA');
        $ownerA = $this->makeUser($companyA, 'owner', 'RTA');
        $supplierA = $this->makeSupplier($companyA, 'Company A Supplier');

        $companyB = $this->makeCompany('RTB');
        $ownerB = $this->makeUser($companyB, 'owner', 'RTB');
        $projectB = $this->makeProject($companyB, 'RTB');

        $this->actingAs($ownerA)->post('/app/rfqs', [
            'title' => 'Company A only RFQ',
            'item_description' => ['Cement'],
            'item_qty' => [100],
        ]);
        $rfqA = Rfq::where('company_id', $companyA->id)->first();
        $itemA = RfqItem::where('rfq_id', $rfqA->id)->first();

        $this->actingAs($ownerA)->post('/app/rfqs/' . $rfqA->id . '/quotes', [
            'supplier_id' => $supplierA->id,
            'total_amount' => 5000,
        ]);
        $quoteA = RfqQuote::where('rfq_id', $rfqA->id)->first();

        // Company B cannot view, edit, delete, add items/quotes to, or award company A's RFQ.
        $this->actingAs($ownerB)->get('/app/rfqs/' . $rfqA->id)->assertStatus(404);
        $this->actingAs($ownerB)->get('/app/rfqs/' . $rfqA->id . '/edit')->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/rfqs/' . $rfqA->id, ['title' => 'intrusion'])->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/rfqs/' . $rfqA->id . '/delete')->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/rfqs/' . $rfqA->id . '/items', ['description' => 'intrusion'])->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/rfqs/' . $rfqA->id . '/items/' . $itemA->id . '/delete')->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/rfqs/' . $rfqA->id . '/quotes', ['supplier_id' => $supplierA->id, 'total_amount' => 1])->assertStatus(404);
        $this->actingAs($ownerB)->post('/app/rfqs/' . $rfqA->id . '/quotes/' . $quoteA->id . '/award')->assertStatus(404);

        // Company B's own project-scoped index/create pages still render fine (not a
        // trivially-empty-DB pass), and never show company A's RFQ.
        $indexB = $this->actingAs($ownerB)->get('/app/rfqs');
        $indexB->assertOk();
        $indexB->assertDontSee('Company A only RFQ');

        $createB = $this->actingAs($ownerB)->get('/app/rfqs/create');
        $createB->assertOk();
        $createB->assertDontSee('Company A only RFQ');

        // Company A's RFQ, item, and quote are all untouched by the attempted intrusions.
        $this->assertSame('Company A only RFQ', $rfqA->fresh()->title);
        $this->assertNotNull(RfqItem::find($itemA->id));
        $this->assertFalse($quoteA->fresh()->is_awarded);

        // Sanity: $projectB exists and belongs to company B, confirming test isolation setup.
        $this->assertSame($companyB->id, $projectB->company_id);
    }
}
