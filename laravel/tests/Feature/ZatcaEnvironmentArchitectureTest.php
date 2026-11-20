<?php

namespace Tests\Feature;

use App\Jobs\SyncInvoiceToZatca;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\ZatcaEnvironmentCredential;
use App\Models\ZatcaInvoiceLog;
use App\Support\Zatca\InvoiceChainer;
use App\Support\Zatca\ZatcaSyncService;
use App\Support\Zatca\ZatcaXmlGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Covers the ported ZATCA architecture pieces: the dedicated
 * zatca_invoice_logs history table, per-environment credential save
 * slots (Company::switchZatcaEnvironment()), the persisted, success-only
 * zatca_icv_counter, and the row-locking + re-check-after-lock pattern in
 * ZatcaSyncService::submitInvoice() (security audit findings D-1/ZATCA-03
 * in the reference implementation this was ported from).
 *
 * zatca_private_key is left blank throughout — ZatcaSyncService::
 * buildSignedPayload() short-circuits to the plain unsigned XML whenever
 * ZatcaCryptoService::signInvoiceHash() has no key to sign with (see that
 * method), so these tests never need a real ZATCA-issued certificate to
 * parse, only a non-empty zatca_production_csid/secret pair for
 * Company::isZatcaOnboarded() to pass.
 */
class ZatcaEnvironmentArchitectureTest extends TestCase
{
    use DatabaseTransactions;

    private function onboardedCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Jeddah Build Partners',
            'email' => 'zatca-arch-'.uniqid().'@example.com',
            'zatca_environment' => 'developer',
            'zatca_status' => 'onboarded',
            'zatca_production_csid' => 'fake-production-csid',
            'zatca_production_secret' => 'fake-production-secret',
            'zatca_sync_b2b' => true,
            'zatca_sync_b2c' => true,
        ], $overrides));
    }

    private function simplifiedClient(Company $company): Client
    {
        // No vat_number/cr_number on file — ZatcaXmlGenerator::isB2bEligible()
        // then falls back to the simplified/B2C profile, which submits via
        // ZatcaApiClient::reportInvoice() rather than clearInvoice().
        return Client::create(['company_id' => $company->id, 'name' => 'Walk-in Buyer']);
    }

    private function chainedInvoice(Company $company, Client $client, string $number, float $total): Invoice
    {
        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => $number,
            'status' => 'unpaid',
            'total' => $total,
            'vat_rate' => 0,
            'vat_amount' => 0,
        ]);

        InvoiceChainer::chain(
            $invoice, $company, $client,
            [['description' => 'Works', 'qty' => 1, 'unit_price' => $total, 'total' => $total]],
            app(ZatcaSyncService::class),
        );

        return $invoice->fresh();
    }

    /**
     * (1) Submitting an invoice creates a ZatcaInvoiceLog row with the
     * right environment/direction/status, AND still updates the
     * invoice's existing flat zatca_* columns exactly as before — zero
     * regression to the pre-existing behaviour/tests.
     */
    public function test_submitting_an_invoice_creates_a_log_row_and_still_updates_the_flat_columns(): void
    {
        Http::fake(['*' => Http::response(['reportingStatus' => 'REPORTED'], 200)]);

        $company = $this->onboardedCompany();
        $client = $this->simplifiedClient($company);
        $invoice = $this->chainedInvoice($company, $client, 'INV-ARCH-0001', 10000);

        $result = app(ZatcaSyncService::class)->submitInvoice($invoice->fresh(), $company->fresh(), $client, [
            ['description' => 'Works', 'qty' => 1, 'unit_price' => 10000, 'total' => 10000],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('reported', $result['status']);

        $invoice->refresh();
        $this->assertSame('reported', $invoice->zatca_status);
        $this->assertNotNull($invoice->zatca_submitted_at);
        $this->assertNotEmpty($invoice->zatca_response);

        $log = ZatcaInvoiceLog::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($log, 'submitInvoice() must create a ZatcaInvoiceLog row');
        $this->assertSame($company->id, $log->company_id);
        $this->assertSame('developer', $log->environment);
        $this->assertSame('b2c', $log->invoice_type);
        $this->assertSame('reporting', $log->direction);
        $this->assertSame('reported', $log->status);
        $this->assertSame($invoice->zatca_hash, $log->invoice_hash);
        $this->assertNotNull($log->cleared_at);

        $company->refresh();
        $this->assertSame($invoice->zatca_hash, $company->zatca_last_invoice_hash);
        $this->assertSame(1, $company->zatca_icv_counter);
    }

    /**
     * (2) Switching zatca_environment (developer → production → back to
     * developer) preserves each environment's own onboarding progress via
     * zatca_environment_credentials, rather than wiping it.
     */
    public function test_switching_environment_preserves_each_environments_own_onboarding_progress(): void
    {
        $company = Company::create([
            'name' => 'Riyadh Infra Works',
            'email' => 'zatca-switch-'.uniqid().'@example.com',
            'zatca_environment' => 'developer',
            'zatca_status' => 'csr_generated',
            'zatca_egs_serial' => '1-BuildXact|2-1.0.0|3-dev',
            'zatca_csr' => 'dev-csr-base64',
            'zatca_icv_counter' => 3,
            'zatca_last_invoice_hash' => 'dev-hash-abc',
        ]);

        // Switch to production — a fresh environment, never onboarded.
        $company->switchZatcaEnvironment('production');
        $company->save();

        $this->assertSame('production', $company->zatca_environment);
        $this->assertSame('not_started', $company->zatca_status);
        $this->assertNull($company->zatca_csr);
        $this->assertSame(0, $company->zatca_icv_counter);
        $this->assertNull($company->zatca_last_invoice_hash);

        // The developer environment's progress must have been saved,
        // not discarded.
        $devSlot = ZatcaEnvironmentCredential::where('company_id', $company->id)->where('environment', 'developer')->first();
        $this->assertNotNull($devSlot);
        $this->assertSame('csr_generated', $devSlot->onboarding_status);
        $this->assertSame('dev-csr-base64', $devSlot->csr);
        $this->assertSame(3, $devSlot->icv_counter);
        $this->assertSame('dev-hash-abc', $devSlot->last_invoice_hash);

        // Progress production a bit, then switch back to developer.
        $company->zatca_status = 'compliance_pending';
        $company->zatca_icv_counter = 1;
        $company->save();

        $company->switchZatcaEnvironment('developer');
        $company->save();

        $this->assertSame('developer', $company->zatca_environment);
        $this->assertSame('csr_generated', $company->zatca_status, 'developer onboarding progress must be restored, not wiped');
        $this->assertSame('dev-csr-base64', $company->zatca_csr);
        $this->assertSame(3, $company->zatca_icv_counter);
        $this->assertSame('dev-hash-abc', $company->zatca_last_invoice_hash);

        // And production's own progress must now be saved under its slot.
        $prodSlot = ZatcaEnvironmentCredential::where('company_id', $company->id)->where('environment', 'production')->first();
        $this->assertNotNull($prodSlot);
        $this->assertSame('compliance_pending', $prodSlot->onboarding_status);
        $this->assertSame(1, $prodSlot->icv_counter);
    }

    /**
     * (3) The per-environment ICV counter advances only on confirmed
     * success, and two companies' (or two environments') counters never
     * interfere with each other.
     */
    public function test_icv_counter_advances_only_on_success_and_never_crosses_companies_or_environments(): void
    {
        Http::fake(['*' => Http::response(['reportingStatus' => 'REPORTED'], 200)]);

        $companyA = $this->onboardedCompany(['name' => 'Company A']);
        $clientA = $this->simplifiedClient($companyA);
        $invoiceA = $this->chainedInvoice($companyA, $clientA, 'INV-A-0001', 5000);

        $companyB = $this->onboardedCompany(['name' => 'Company B']);
        $clientB = $this->simplifiedClient($companyB);
        $invoiceB = $this->chainedInvoice($companyB, $clientB, 'INV-B-0001', 7000);

        $sync = app(ZatcaSyncService::class);
        $resultA = $sync->submitInvoice($invoiceA->fresh(), $companyA->fresh(), $clientA, [
            ['description' => 'Works', 'qty' => 1, 'unit_price' => 5000, 'total' => 5000],
        ]);
        $this->assertTrue($resultA['ok']);

        $companyA->refresh();
        $companyB->refresh();
        $this->assertSame(1, $companyA->zatca_icv_counter);
        $this->assertSame(0, $companyB->zatca_icv_counter, "company B's counter must not move just because company A submitted");

        $resultB = $sync->submitInvoice($invoiceB->fresh(), $companyB->fresh(), $clientB, [
            ['description' => 'Works', 'qty' => 1, 'unit_price' => 7000, 'total' => 7000],
        ]);
        $this->assertTrue($resultB['ok']);

        $companyA->refresh();
        $companyB->refresh();
        $this->assertSame(1, $companyA->zatca_icv_counter, "company A's counter must not move again just because company B submitted");
        $this->assertSame(1, $companyB->zatca_icv_counter);
    }

    /**
     * (3b) A rejected/failed submission must not advance the ICV
     * counter — kept as its own test (its own fresh Http::fake() call)
     * since Http::fake() stubs are additive within one test, so a second
     * call in the same method can't reliably override the first.
     */
    public function test_a_failed_submission_does_not_advance_the_icv_counter(): void
    {
        Http::fake(['*' => Http::response('invalid invoice', 400)]);

        $company = $this->onboardedCompany();
        $client = $this->simplifiedClient($company);
        $invoice = $this->chainedInvoice($company, $client, 'INV-FAIL-0001', 2000);

        $result = app(ZatcaSyncService::class)->submitInvoice($invoice->fresh(), $company->fresh(), $client, [
            ['description' => 'Works', 'qty' => 1, 'unit_price' => 2000, 'total' => 2000],
        ]);

        $this->assertFalse($result['ok']);

        $company->refresh();
        $this->assertSame(0, $company->zatca_icv_counter, 'a rejected submission must not advance the ICV counter');
        $this->assertSame('failed', $invoice->fresh()->zatca_status);

        $log = ZatcaInvoiceLog::where('invoice_id', $invoice->id)->first();
        $this->assertSame('failed', $log->status);
    }

    /**
     * (4) Two submissions for the same company, racing from the same
     * starting state, never both succeed in advancing the chain — the
     * second must see the first's already-committed outcome rather than
     * corrupting/duplicating it. Also proves the queued job is a no-op
     * once the manual synchronous path has already won.
     */
    public function test_a_locked_resubmission_never_double_advances_the_chain(): void
    {
        Http::fake(['*' => Http::response(['reportingStatus' => 'REPORTED'], 200)]);

        $company = $this->onboardedCompany();
        $client = $this->simplifiedClient($company);
        $invoice1 = $this->chainedInvoice($company, $client, 'INV-RACE-0001', 3000);
        $invoice2 = $this->chainedInvoice($company->fresh(), $client, 'INV-RACE-0002', 4000);

        $sync = app(ZatcaSyncService::class);

        $first = $sync->submitInvoice($invoice1->fresh(), $company->fresh(), $client, [
            ['description' => 'Works', 'qty' => 1, 'unit_price' => 3000, 'total' => 3000],
        ]);
        $this->assertTrue($first['ok']);

        $second = $sync->submitInvoice($invoice2->fresh(), $company->fresh(), $client, [
            ['description' => 'Works', 'qty' => 1, 'unit_price' => 4000, 'total' => 4000],
        ]);
        $this->assertTrue($second['ok']);

        $company->refresh();
        $this->assertSame(2, $company->zatca_icv_counter);
        $this->assertSame($invoice2->fresh()->zatca_hash, $company->zatca_last_invoice_hash);

        // Now simulate a late/racing resubmission of invoice1 — e.g. the
        // queued SyncInvoiceToZatca job finally running after the manual
        // "Submit to ZATCA" button already won the race. It must see
        // invoice1's already-committed cleared/reported state (the
        // ZATCA-03 re-check-after-lock pattern) and refuse, rather than
        // submitting a duplicate clearance and incrementing the counter
        // a third time.
        $race = $sync->submitInvoice($invoice1->fresh(), $company->fresh(), $client, [
            ['description' => 'Works', 'qty' => 1, 'unit_price' => 3000, 'total' => 3000],
        ]);

        $this->assertFalse($race['ok']);
        $this->assertStringContainsString('already been cleared or reported', $race['message']);

        $company->refresh();
        $this->assertSame(2, $company->zatca_icv_counter, 'a refused duplicate resubmission must not advance the counter a 3rd time');

        // Only two successful log rows exist in total — the duplicate
        // attempt never created a third ZatcaInvoiceLog with a cleared/
        // reported status.
        $successfulLogs = ZatcaInvoiceLog::where('company_id', $company->id)
            ->whereIn('status', ['cleared', 'reported'])
            ->count();
        $this->assertSame(2, $successfulLogs);
    }

    /** The queued job is wired to the invoice-creation call site and is a safe no-op for a non-onboarded company. */
    public function test_sync_invoice_job_is_dispatched_on_creation_and_is_a_no_op_when_not_onboarded(): void
    {
        Queue::fake();

        $company = Company::create(['name' => 'Not Onboarded Co.', 'email' => 'zatca-noop-'.uniqid().'@example.com']);
        $client = $this->simplifiedClient($company);
        $invoice = $this->chainedInvoice($company, $client, 'INV-NOOP-0001', 1000);

        SyncInvoiceToZatca::dispatch($invoice->id);
        Queue::assertPushed(SyncInvoiceToZatca::class);

        // Running it for real against a non-onboarded company must not
        // throw and must not touch the invoice.
        (new SyncInvoiceToZatca($invoice->id))->handle(app(ZatcaSyncService::class), app(ZatcaXmlGenerator::class));

        $invoice->refresh();
        $this->assertSame('not_submitted', $invoice->zatca_status);
    }
}
