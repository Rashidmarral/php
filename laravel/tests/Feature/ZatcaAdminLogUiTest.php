<?php

namespace Tests\Feature;

use App\Jobs\SyncInvoiceToZatca;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\User;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Covers the new platform-admin ZATCA submission-log UI
 * (App\Http\Controllers\Admin\AdminZatcaLogController): the unified
 * cross-company logs listing across all three log tables, a single log's
 * detail page, the pending-documents-for-a-company view, and the
 * admin.super-gated retry action that re-dispatches the matching queued
 * sync job. See ZatcaEnvironmentArchitectureTest for the underlying log
 * tables and submission flow this UI only reads from.
 */
class ZatcaAdminLogUiTest extends TestCase
{
    use DatabaseTransactions;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin',
            'email' => 'zatca-ui-admin-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    /** Read-only admin staff — may browse the panel but every mutating (admin.super) action must reject them. */
    private function supportAdmin(): User
    {
        return User::create([
            'name' => 'Support Admin',
            'email' => 'zatca-ui-support-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => 'support_admin',
            'status' => 'active',
        ]);
    }

    private function onboardedCompany(): Company
    {
        return Company::create([
            'name' => 'Admin UI Test Co',
            'email' => 'zatca-admin-ui-'.uniqid().'@example.com',
            'zatca_environment' => 'developer',
            'zatca_status' => 'onboarded',
            'zatca_production_csid' => 'fake-csid',
            'zatca_production_secret' => 'fake-secret',
        ]);
    }

    private function invoice(Company $company, string $number, string $zatcaStatus = 'not_submitted'): Invoice
    {
        return Invoice::create([
            'company_id' => $company->id,
            'invoice_number' => $number,
            'status' => 'unpaid',
            'total' => 1000,
            'vat_rate' => 0,
            'vat_amount' => 0,
            'zatca_uuid' => (string) Str::uuid(),
            'zatca_status' => $zatcaStatus,
        ]);
    }

    private function invoiceLog(Company $company, Invoice $invoice, string $status = 'failed'): ZatcaInvoiceLog
    {
        return ZatcaInvoiceLog::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'environment' => 'developer',
            'invoice_type' => 'b2c',
            'direction' => 'reporting',
            'status' => $status,
            'request_uuid' => (string) Str::uuid(),
            'error_message' => $status === 'failed' ? 'HTTP 400: invalid invoice' : null,
            'submitted_at' => now(),
            'cleared_at' => in_array($status, ['cleared', 'reported'], true) ? now() : null,
        ]);
    }

    private function creditNote(Company $company, Invoice $invoice, string $number): CreditNote
    {
        return CreditNote::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'note_number' => $number,
            'total' => 500,
            'zatca_uuid' => (string) Str::uuid(),
            'zatca_status' => 'not_submitted',
        ]);
    }

    private function debitNote(Company $company, Invoice $invoice, string $number): DebitNote
    {
        return DebitNote::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'note_number' => $number,
            'total' => 300,
            'zatca_uuid' => (string) Str::uuid(),
            'zatca_status' => 'not_submitted',
        ]);
    }

    /** (1) The unified logs listing must render rows pulled from all three log tables at once. */
    public function test_admin_log_listing_shows_rows_from_all_three_log_types(): void
    {
        $admin = $this->superAdmin();
        $company = $this->onboardedCompany();
        $invoice = $this->invoice($company, 'INV-UI-0001');
        $this->invoiceLog($company, $invoice, 'failed');

        $creditNote = $this->creditNote($company, $invoice, 'CN-UI-0001');
        ZatcaCreditNoteLog::create([
            'company_id' => $company->id,
            'credit_note_id' => $creditNote->id,
            'environment' => 'developer',
            'invoice_type' => 'b2c',
            'direction' => 'reporting',
            'status' => 'reported',
            'request_uuid' => (string) Str::uuid(),
            'submitted_at' => now(),
            'cleared_at' => now(),
        ]);

        $debitNote = $this->debitNote($company, $invoice, 'DN-UI-0001');
        ZatcaDebitNoteLog::create([
            'company_id' => $company->id,
            'debit_note_id' => $debitNote->id,
            'environment' => 'developer',
            'invoice_type' => 'b2c',
            'direction' => 'reporting',
            'status' => 'queued',
            'request_uuid' => (string) Str::uuid(),
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/admin/zatca/logs');

        $response->assertOk();
        $response->assertSee('INV-UI-0001', false);
        $response->assertSee('CN-UI-0001', false);
        $response->assertSee('DN-UI-0001', false);
    }

    /** (2) The detail page renders one specific log's full submission record. */
    public function test_admin_log_show_renders_a_specific_logs_detail(): void
    {
        $admin = $this->superAdmin();
        $company = $this->onboardedCompany();
        $invoice = $this->invoice($company, 'INV-UI-0002', 'failed');
        $log = $this->invoiceLog($company, $invoice, 'failed');
        $log->update(['xml_payload' => '<Invoice>test-payload</Invoice>', 'response_payload' => '{"error":"bad request"}']);

        $response = $this->actingAs($admin)->get("/admin/zatca/logs/invoice/{$log->id}");

        $response->assertOk();
        $response->assertSee('INV-UI-0002', false);
        $response->assertSee($log->request_uuid, false);
        $response->assertSee('invalid invoice', false);
        $response->assertSee('test-payload', false);
        $response->assertSee('bad request', false);
    }

    /** (3) retry() dispatches the correctly-typed queued job and is blocked for anyone below admin.super. */
    public function test_admin_retry_dispatches_the_right_job_and_requires_admin_super(): void
    {
        Queue::fake();

        $company = $this->onboardedCompany();
        $invoice = $this->invoice($company, 'INV-UI-0003', 'failed');
        $log = $this->invoiceLog($company, $invoice, 'failed');

        $supportAdmin = $this->supportAdmin();
        $blocked = $this->actingAs($supportAdmin)->post("/admin/zatca/logs/invoice/{$log->id}/retry");
        $blocked->assertStatus(403);
        Queue::assertNotPushed(SyncInvoiceToZatca::class);

        $superAdmin = $this->superAdmin();
        $allowed = $this->actingAs($superAdmin)->post("/admin/zatca/logs/invoice/{$log->id}/retry");
        $allowed->assertRedirect();
        Queue::assertPushed(SyncInvoiceToZatca::class, fn ($job) => $job->invoiceId === $invoice->id);
    }

    /** (3b) retry() must refuse a document that has already been cleared/reported, and must not dispatch anything. */
    public function test_admin_retry_refuses_an_already_locked_document(): void
    {
        Queue::fake();

        $company = $this->onboardedCompany();
        $invoice = $this->invoice($company, 'INV-UI-0004', 'cleared');
        $log = $this->invoiceLog($company, $invoice, 'cleared');

        $admin = $this->superAdmin();
        $response = $this->actingAs($admin)->post("/admin/zatca/logs/invoice/{$log->id}/retry");

        $response->assertRedirect();
        Queue::assertNotPushed(SyncInvoiceToZatca::class);
    }

    /** (4) The pending-documents view lists only documents still awaiting a successful (cleared/reported) outcome. */
    public function test_pending_documents_view_lists_only_unsubmitted_docs(): void
    {
        $admin = $this->superAdmin();
        $company = $this->onboardedCompany();

        $this->invoice($company, 'INV-UI-PEND-0001', 'not_submitted');
        $this->invoice($company, 'INV-UI-PEND-0002', 'cleared');

        $response = $this->actingAs($admin)->get("/admin/companies/{$company->id}/zatca/pending");

        $response->assertOk();
        $response->assertSee('INV-UI-PEND-0001', false);
        $response->assertDontSee('INV-UI-PEND-0002', false);
    }
}
