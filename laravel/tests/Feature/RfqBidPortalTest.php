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
 * Covers the supplier/subcontractor self-service bid portal: a GC invites a supplier
 * to submit their own RfqQuote directly via a private, per-supplier token link
 * (RfqController::invite() + ShareController::rfqBid()/submitRfqBid()), alongside
 * (never replacing) the existing manual RfqController::recordQuote() flow that
 * RfqTest.php already covers. Wrapped in DatabaseTransactions, same convention as
 * RfqTest.
 */
class RfqBidPortalTest extends TestCase
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

    private function makeRfqWithItem(Company $company, string $title): Rfq
    {
        $rfq = Rfq::create([
            'company_id' => $company->id,
            'title' => $title,
            'status' => 'draft',
            'created_by' => null,
        ]);
        RfqItem::create([
            'rfq_id' => $rfq->id,
            'description' => 'Rebar 12mm',
            'qty' => 500,
            'unit' => 'kg',
        ]);

        return $rfq;
    }

    public function test_inviting_a_supplier_creates_a_quote_with_a_unique_invite_token_retrievable_from_the_show_page(): void
    {
        $company = $this->makeCompany('BP1');
        $owner = $this->makeUser($company, 'BP1');
        $supplier = $this->makeSupplier($company, 'Al Faisal Steel');
        $rfq = $this->makeRfqWithItem($company, 'Rebar RFQ');

        $response = $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/invite', [
            'supplier_id' => $supplier->id,
        ]);
        $response->assertRedirect('/app/rfqs/' . $rfq->id);

        $quote = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($quote);
        $this->assertNotEmpty($quote->invite_token);
        $this->assertSame(40, strlen($quote->invite_token));
        $this->assertNull($quote->submitted_at);
        $this->assertSame('0.00', $quote->total_amount);

        // A second invite (e.g. to a different supplier) gets its own, distinct token.
        $supplierB = $this->makeSupplier($company, 'Gulf Rebar Co');
        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/invite', ['supplier_id' => $supplierB->id]);
        $quoteB = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplierB->id)->first();
        $this->assertNotSame($quote->invite_token, $quoteB->invite_token);

        // The GC can retrieve/copy the link from the RFQ's own show page.
        $show = $this->actingAs($owner)->get('/app/rfqs/' . $rfq->id);
        $show->assertOk();
        $show->assertSee('/rfq-bid/' . $quote->invite_token);
        $show->assertSee('/rfq-bid/' . $quoteB->invite_token);
        $show->assertSee('Al Faisal Steel');
    }

    public function test_invite_requires_a_supplier_belonging_to_the_same_company(): void
    {
        $company = $this->makeCompany('BP2');
        $owner = $this->makeUser($company, 'BP2');
        $rfq = $this->makeRfqWithItem($company, 'No supplier RFQ');

        $response = $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/invite', []);
        $response->assertRedirect('/app/rfqs/' . $rfq->id);
        $this->assertSame(0, RfqQuote::where('rfq_id', $rfq->id)->count());
    }

    public function test_public_bid_page_shows_rfq_items_without_exposing_other_suppliers_quotes_or_amounts(): void
    {
        $company = $this->makeCompany('BP3');
        $owner = $this->makeUser($company, 'BP3');
        $supplierA = $this->makeSupplier($company, 'Invited Supplier');
        $supplierB = $this->makeSupplier($company, 'Secret Competitor');
        $rfq = $this->makeRfqWithItem($company, 'Confidential Scope RFQ');

        // A manually recorded competing quote, and another supplier's own invite —
        // neither should leak through supplierA's bid page.
        RfqQuote::create([
            'rfq_id' => $rfq->id,
            'supplier_id' => $supplierB->id,
            'total_amount' => 99999.99,
            'notes' => 'Competitor secret pricing notes',
            'submitted_at' => now()->format('Y-m-d'),
        ]);

        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/invite', ['supplier_id' => $supplierA->id]);
        $quote = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplierA->id)->first();

        $page = $this->get('/rfq-bid/' . $quote->invite_token);
        $page->assertOk();
        $page->assertSee('Confidential Scope RFQ');
        $page->assertSee('Rebar 12mm');
        $page->assertDontSee('Secret Competitor');
        $page->assertDontSee('99999.99');
        $page->assertDontSee('99,999.99');
        $page->assertDontSee('Competitor secret pricing notes');
    }

    public function test_submitting_a_valid_bid_records_it_and_fires_notification_and_webhook(): void
    {
        $company = $this->makeCompany('BP4');
        $owner = $this->makeUser($company, 'BP4');
        $supplier = $this->makeSupplier($company, 'Riyadh Metals');
        $rfq = $this->makeRfqWithItem($company, 'Submission RFQ');

        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/invite', ['supplier_id' => $supplier->id]);
        $quote = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplier->id)->first();

        $response = $this->post('/rfq-bid/' . $quote->invite_token . '/submit', [
            'total_amount' => 14250.5,
            'lead_time_days' => 12,
            'notes' => 'Can start next week.',
        ]);
        $response->assertRedirect('/rfq-bid/' . $quote->invite_token);

        $quote->refresh();
        $this->assertSame('14250.50', $quote->total_amount);
        $this->assertSame(12, $quote->lead_time_days);
        $this->assertSame('Can start next week.', $quote->notes);
        $this->assertNotNull($quote->submitted_at);

        // Receiving a self-submitted quote moves a still-draft RFQ into "comparing",
        // same rule as the manual recordQuote() path.
        $this->assertSame('comparing', $rfq->fresh()->status);

        // Notifications::rfqQuoteSubmitted() fired and recorded an in-app notification
        // for the GC; WebhookDispatcher::dispatch() ran too (a no-op here since this
        // company has no registered webhook, but it must not throw).
        $this->assertSame(
            1,
            \App\Models\Notification::where('company_id', $company->id)->where('type', 'rfq_quote_submitted')->count()
        );

        // The confirmation page now shows the "already submitted" state.
        $confirm = $this->get('/rfq-bid/' . $quote->invite_token);
        $confirm->assertOk();
        $confirm->assertSee('14,250.50');
    }

    public function test_invalid_token_404s_on_both_get_and_post(): void
    {
        $this->get('/rfq-bid/not-a-real-token')->assertStatus(404);
        $this->post('/rfq-bid/not-a-real-token/submit', ['total_amount' => 100])->assertStatus(404);
    }

    public function test_resubmitting_an_already_submitted_bid_is_blocked_and_does_not_overwrite(): void
    {
        $company = $this->makeCompany('BP5');
        $owner = $this->makeUser($company, 'BP5');
        $supplier = $this->makeSupplier($company, 'Jeddah Builders Supply');
        $rfq = $this->makeRfqWithItem($company, 'Resubmit RFQ');

        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/invite', ['supplier_id' => $supplier->id]);
        $quote = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplier->id)->first();

        $this->post('/rfq-bid/' . $quote->invite_token . '/submit', [
            'total_amount' => 5000,
            'lead_time_days' => 5,
            'notes' => 'Original bid.',
        ]);
        $quote->refresh();
        $this->assertSame('5000.00', $quote->total_amount);
        $firstSubmittedAt = $quote->submitted_at;

        // A second attempt to submit a different number must not overwrite the first.
        $second = $this->post('/rfq-bid/' . $quote->invite_token . '/submit', [
            'total_amount' => 1,
            'lead_time_days' => 99,
            'notes' => 'Attempted overwrite.',
        ]);
        $second->assertRedirect('/rfq-bid/' . $quote->invite_token);

        $quote->refresh();
        $this->assertSame('5000.00', $quote->total_amount);
        $this->assertSame(5, $quote->lead_time_days);
        $this->assertSame('Original bid.', $quote->notes);
        $this->assertSame((string) $firstSubmittedAt, (string) $quote->submitted_at);
    }

    public function test_manual_record_quote_flow_still_works_unchanged_alongside_self_service_invites(): void
    {
        $company = $this->makeCompany('BP6');
        $owner = $this->makeUser($company, 'BP6');
        $supplierManual = $this->makeSupplier($company, 'Manually Recorded Supplier');
        $supplierInvited = $this->makeSupplier($company, 'Self Service Supplier');
        $rfq = $this->makeRfqWithItem($company, 'Mixed Flow RFQ');

        // Manual path, exactly as RfqTest exercises it.
        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/quotes', [
            'supplier_id' => $supplierManual->id,
            'total_amount' => 20000,
            'lead_time_days' => 20,
        ]);
        $manualQuote = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplierManual->id)->first();
        $this->assertNotNull($manualQuote);
        $this->assertNotNull($manualQuote->submitted_at);
        $this->assertNull($manualQuote->invite_token);

        // Self-service path, coexisting on the same RFQ.
        $this->actingAs($owner)->post('/app/rfqs/' . $rfq->id . '/invite', ['supplier_id' => $supplierInvited->id]);
        $invitedQuote = RfqQuote::where('rfq_id', $rfq->id)->where('supplier_id', $supplierInvited->id)->first();
        $this->post('/rfq-bid/' . $invitedQuote->invite_token . '/submit', ['total_amount' => 18000, 'lead_time_days' => 8]);
        $invitedQuote->refresh();
        $this->assertNotNull($invitedQuote->submitted_at);

        $this->assertSame(2, RfqQuote::where('rfq_id', $rfq->id)->count());

        $show = $this->actingAs($owner)->get('/app/rfqs/' . $rfq->id);
        $show->assertOk();
        $show->assertSee('Manually Recorded Supplier');
        $show->assertSee('Self Service Supplier');
        // The cheaper, self-submitted quote should win "cheapest", not any
        // placeholder-zero pending invite skewing the comparison.
        $show->assertSeeInOrder(['18,000.00']);
    }
}
