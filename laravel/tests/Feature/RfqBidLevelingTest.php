<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\RfqQuote;
use App\Models\RfqQuoteItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers real line-item bid leveling: an optional per-line price breakdown on top of
 * an RfqQuote's existing lump total_amount (RfqController::recordQuote()/leveling(),
 * ShareController::submitRfqBid()), fully backward compatible with a supplier who
 * only ever gives one lump number. Mirrors RfqTest/RfqBidPortalTest's fixture-building
 * style. Wrapped in DatabaseTransactions, same convention as those.
 *
 * Hand-worked example used throughout (see test 1):
 *   Items: Cement qty=10, Rebar qty=5.
 *   Supplier A: Cement @ 100/unit = 1000.00, Rebar @ 50/unit = 250.00  -> total 1250.00
 *   Supplier B: Cement @ 90/unit  = 900.00,  Rebar @ 60/unit = 300.00  -> total 1200.00
 *   Cheapest per line: Cement -> Supplier B (900.00 < 1000.00); Rebar -> Supplier A (250.00 < 300.00).
 *   Best combined = 900.00 + 250.00 = 1150.00 (cheaper than either supplier's own total,
 *   and assembled from two different suppliers — the point of the "best combined" figure).
 */
class RfqBidLevelingTest extends TestCase
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

    private function makeCompany(string $tag): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['rfq_quotes' => true]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Co",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
        ]);
    }

    private function makeUser(Company $company, string $tag): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => "Owner {$tag}",
            'email' => 'owner-' . strtolower($tag) . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'owner',
            'status' => 'active',
        ]);
    }

    private function makeSupplier(Company $company, string $name): Supplier
    {
        return Supplier::create(['company_id' => $company->id, 'name' => $name]);
    }

    /** @return array{0: Rfq, 1: RfqItem, 2: RfqItem} [rfq, cementItem, rebarItem] */
    private function makeRfqWithTwoItems(Company $company, string $title): array
    {
        $rfq = Rfq::create([
            'company_id' => $company->id,
            'title' => $title,
            'status' => 'draft',
            'created_by' => null,
        ]);
        $cement = RfqItem::create(['rfq_id' => $rfq->id, 'description' => 'Cement', 'qty' => 10, 'unit' => 'bag']);
        $rebar = RfqItem::create(['rfq_id' => $rfq->id, 'description' => 'Rebar', 'qty' => 5, 'unit' => 'ton']);

        return [$rfq, $cement, $rebar];
    }

    public function test_hand_worked_itemized_example_computes_totals_cheapest_lines_and_best_combined(): void
    {
        $company = $this->makeCompany('LV1');
        $owner = $this->makeUser($company, 'LV1');
        $supplierA = $this->makeSupplier($company, 'Supplier A');
        $supplierB = $this->makeSupplier($company, 'Supplier B');
        [$rfq, $cement, $rebar] = $this->makeRfqWithTwoItems($company, 'Leveling RFQ');

        // Supplier A: Cement @ 100 (=1000.00), Rebar @ 50 (=250.00) -> total 1250.00.
        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplierA->id,
            'total_amount' => 999, // must be ignored/overridden once lines are itemized
            'item_unit_price' => [
                $cement->id => 100,
                $rebar->id => 50,
            ],
        ]);
        $quoteA = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplierA->id)->first();
        $this->assertNotNull($quoteA);
        $this->assertSame('1250.00', $quoteA->total_amount);
        $this->assertSame(2, RfqQuoteItem::where('rfq_quote_id', $quoteA->id)->count());

        // Supplier B: Cement @ 90 (=900.00), Rebar @ 60 (=300.00) -> total 1200.00.
        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplierB->id,
            'total_amount' => 1,
            'item_unit_price' => [
                $cement->id => 90,
                $rebar->id => 60,
            ],
        ]);
        $quoteB = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplierB->id)->first();
        $this->assertSame('1200.00', $quoteB->total_amount);

        $response = $this->actingAs($owner)->get('/app/rfqs/' . $rfq->id . '/leveling');
        $response->assertOk();
        $response->assertSee('Supplier A');
        $response->assertSee('Supplier B');
        $response->assertSee('Cement');
        $response->assertSee('Rebar');
        // Best combined: cheapest Cement (900.00, Supplier B) + cheapest Rebar (250.00, Supplier A) = 1150.00.
        $response->assertSee('1,150.00');
        // Each supplier's own submitted total_amount still shown as a separate reference figure.
        $response->assertSee('1,250.00');
        $response->assertSee('1,200.00');
    }

    public function test_record_quote_with_no_itemized_prices_is_unchanged_backward_compatible(): void
    {
        $company = $this->makeCompany('LV2');
        $owner = $this->makeUser($company, 'LV2');
        $supplier = $this->makeSupplier($company, 'Plain Lump Supplier');
        [$rfq] = $this->makeRfqWithTwoItems($company, 'Plain Lump RFQ');

        $response = $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplier->id,
            'total_amount' => 5000,
            'lead_time_days' => 10,
        ]);
        $response->assertRedirect('/app/rfqs/' . $rfq->id);

        $quote = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($quote);
        $this->assertSame('5000.00', $quote->total_amount);
        $this->assertSame(0, RfqQuoteItem::where('rfq_quote_id', $quote->id)->count());
    }

    public function test_record_quote_itemizing_only_one_of_two_items_computes_total_from_that_one_line(): void
    {
        $company = $this->makeCompany('LV3');
        $owner = $this->makeUser($company, 'LV3');
        $supplier = $this->makeSupplier($company, 'Partial Itemizer');
        [$rfq, $cement, $rebar] = $this->makeRfqWithTwoItems($company, 'Partial Itemize RFQ');

        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplier->id,
            'total_amount' => 999999, // must be ignored
            'item_unit_price' => [
                $cement->id => 120, // qty 10 -> 1200.00
                $rebar->id => '',   // left blank: not itemized
            ],
        ]);

        $quote = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($quote);
        $this->assertSame('1200.00', $quote->total_amount);
        $this->assertSame(1, RfqQuoteItem::where('rfq_quote_id', $quote->id)->count());

        $quoteItem = RfqQuoteItem::where('rfq_quote_id', $quote->id)->first();
        $this->assertSame($cement->id, $quoteItem->rfq_item_id);
        $this->assertSame('1200.00', $quoteItem->total);

        // The leveling view shows this supplier's missing Rebar line as not broken down.
        $otherSupplier = $this->makeSupplier($company, 'Second Supplier');
        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $otherSupplier->id,
            'total_amount' => 1300,
        ]);

        $response = $this->actingAs($owner)->get('/app/rfqs/' . $rfq->id . '/leveling');
        $response->assertOk();
        $response->assertSee('—');
        $response->assertSee('1,200.00');
    }

    public function test_public_submit_rfq_bid_supports_the_same_optional_itemization(): void
    {
        $company = $this->makeCompany('LV4');
        $owner = $this->makeUser($company, 'LV4');
        $supplier = $this->makeSupplier($company, 'Public Portal Supplier');
        [$rfq, $cement, $rebar] = $this->makeRfqWithTwoItems($company, 'Public Portal RFQ');

        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/invite', ['supplier_id' => $supplier->id]);
        $quote = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplier->id)->first();

        // Cement @ 80 (qty 10 -> 800.00), Rebar @ 40 (qty 5 -> 200.00) -> total 1000.00.
        $response = $this->post('/rfq-bid/' . $quote->invite_token . '/submit', [
            'total_amount' => 1, // must be ignored once lines are itemized
            'lead_time_days' => 9,
            'item_unit_price' => [
                $cement->id => 80,
                $rebar->id => 40,
            ],
        ]);
        $response->assertRedirect('/rfq-bid/' . $quote->invite_token);

        $quote->refresh();
        $this->assertSame('1000.00', $quote->total_amount);
        $this->assertNotNull($quote->submitted_at);
        $this->assertSame(2, RfqQuoteItem::where('rfq_quote_id', $quote->id)->count());

        $cementItem = RfqQuoteItem::where('rfq_quote_id', $quote->id)->where('rfq_item_id', $cement->id)->first();
        $this->assertSame('800.00', $cementItem->total);
        $this->assertSame('80.00', $cementItem->unit_price);
    }

    public function test_leveling_route_is_blocked_for_a_company_that_does_not_own_the_rfq(): void
    {
        $companyA = $this->makeCompany('LV5A');
        $ownerA = $this->makeUser($companyA, 'LV5A');
        $supplierA = $this->makeSupplier($companyA, 'Company A Supplier');
        [$rfqA, $itemA1, $itemA2] = $this->makeRfqWithTwoItems($companyA, 'Company A Leveling RFQ');

        $this->actingAs($ownerA)->post('/app/rfqs/' . $rfqA->id . '/quotes', [
            'supplier_id' => $supplierA->id,
            'total_amount' => 1000,
            'item_unit_price' => [$itemA1->id => 50, $itemA2->id => 50],
        ]);

        $companyB = $this->makeCompany('LV5B');
        $ownerB = $this->makeUser($companyB, 'LV5B');

        $this->actingAs($ownerB)->get('/app/rfqs/' . $rfqA->id . '/leveling')->assertStatus(404);

        // Company A's own owner can still reach it.
        $this->actingAs($ownerA)->get('/app/rfqs/' . $rfqA->id . '/leveling')->assertOk();
    }
}
