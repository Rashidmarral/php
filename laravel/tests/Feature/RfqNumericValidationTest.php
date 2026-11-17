<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqQuote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the numeric/date validation added to RfqController::store()/addItem()/
 * recordQuote() — line-item qty, quote total_amount/lead_time_days/submitted_at, and
 * the RFQ's own due_date, none of which had any type or range check before (each was
 * only ever (float)/(int)-cast). Mirrors EstimateNumericValidationTest's own shape.
 */
class RfqNumericValidationTest extends TestCase
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
            'feature_flags' => json_encode(['rfq_quotes' => true]),
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

    public function test_store_rejects_non_numeric_item_quantity(): void
    {
        $a = $this->makeCompanyWithOwner('QtyNaN');

        $response = $this->actingAs($a['owner'])->post('/app/rfqs', [
            'title' => 'Steel RFQ',
            'item_description' => ['Rebar 12mm'],
            'item_qty' => ['abc'],
        ]);

        $response->assertRedirect('/app/rfqs/create');
        $this->assertSame(0, Rfq::where('company_id', $a['company']->id)->count());
    }

    public function test_store_rejects_malformed_due_date(): void
    {
        $a = $this->makeCompanyWithOwner('DateBad');

        $response = $this->actingAs($a['owner'])->post('/app/rfqs', [
            'title' => 'Steel RFQ',
            'due_date' => '2026-02-30',
        ]);

        $response->assertRedirect('/app/rfqs/create');
        $this->assertSame(0, Rfq::where('company_id', $a['company']->id)->count());
    }

    public function test_store_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('Happy');

        $response = $this->actingAs($a['owner'])->post('/app/rfqs', [
            'title' => 'Steel RFQ',
            'item_description' => ['Rebar 12mm'],
            'item_qty' => [500],
            'item_unit' => ['kg'],
        ]);

        $response->assertRedirect();
        $rfq = Rfq::where('company_id', $a['company']->id)->first();
        $this->assertNotNull($rfq);
        $item = RfqItem::where('rfq_id', $rfq->id)->first();
        $this->assertSame('500.00', $item->qty);
    }

    public function test_add_item_rejects_out_of_range_quantity(): void
    {
        $a = $this->makeCompanyWithOwner('AddItemMax');
        $rfq = Rfq::create(['company_id' => $a['company']->id, 'title' => 'Existing RFQ', 'status' => 'draft']);

        $response = $this->actingAs($a['owner'])->post('/app/rfqs/' . $rfq->id . '/items', [
            'description' => 'Extra item',
            'qty' => 99999999999,
        ]);

        $response->assertRedirect('/app/rfqs/' . $rfq->id);
        $this->assertSame(0, RfqItem::where('rfq_id', $rfq->id)->count());
    }

    public function test_record_quote_rejects_non_positive_total_amount(): void
    {
        $a = $this->makeCompanyWithOwner('QuoteZero');
        $rfq = Rfq::create(['company_id' => $a['company']->id, 'title' => 'Existing RFQ', 'status' => 'draft']);
        $supplier = Supplier::create(['company_id' => $a['company']->id, 'name' => 'ACME Supplier']);

        $response = $this->actingAs($a['owner'])->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplier->id,
            'total_amount' => '0',
        ]);

        $response->assertRedirect('/app/rfqs/' . $rfq->id);
        $this->assertSame(0, RfqQuote::where('rfq_id', $rfq->id)->count());
    }

    public function test_record_quote_rejects_out_of_range_lead_time_days(): void
    {
        $a = $this->makeCompanyWithOwner('LeadTimeMax');
        $rfq = Rfq::create(['company_id' => $a['company']->id, 'title' => 'Existing RFQ', 'status' => 'draft']);
        $supplier = Supplier::create(['company_id' => $a['company']->id, 'name' => 'ACME Supplier']);

        $response = $this->actingAs($a['owner'])->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplier->id,
            'total_amount' => '1000',
            'lead_time_days' => '999999',
        ]);

        $response->assertRedirect('/app/rfqs/' . $rfq->id);
        $this->assertSame(0, RfqQuote::where('rfq_id', $rfq->id)->count());
    }

    public function test_record_quote_accepts_a_valid_payload(): void
    {
        $a = $this->makeCompanyWithOwner('QuoteHappy');
        $rfq = Rfq::create(['company_id' => $a['company']->id, 'title' => 'Existing RFQ', 'status' => 'draft']);
        $supplier = Supplier::create(['company_id' => $a['company']->id, 'name' => 'ACME Supplier']);

        $response = $this->actingAs($a['owner'])->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplier->id,
            'total_amount' => '12500.50',
            'lead_time_days' => '14',
        ]);

        $response->assertRedirect('/app/rfqs/' . $rfq->id);
        $quote = RfqQuote::where('rfq_id', $rfq->id)->first();
        $this->assertNotNull($quote);
        $this->assertSame('12500.50', $quote->total_amount);
        $this->assertSame(14, $quote->lead_time_days);
    }
}
