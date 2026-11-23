<?php

namespace Tests\Feature;

use App\Models\ApprovalChainStep;
use App\Models\ApprovalStepLog;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers Task #54 (multi-step sequential approval chain). The single most
 * important property under test is the first one: a company that never
 * configures a chain for a document_type must behave EXACTLY as the
 * original single-step approve_documents Gate flow did, with zero
 * approval_steps_log rows ever written for it. Everything else here covers
 * the new chain-aware approve()/reject() dispatch, tenant isolation of a
 * company's chain configuration, and the chosen reject-mid-chain behavior
 * (remaining pending steps are marked 'skipped').
 *
 * Wrapped in DatabaseTransactions so none of this touches the real dev
 * sqlite database beyond the test, matching GlobalSearchTest's convention.
 */
class ApprovalChainTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCompanyWithApprovalWorkflow(string $tag): Company
    {
        $plan = Plan::create([
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['approval_workflow' => true]),
        ]);

        return Company::create([
            'name' => "Acme {$tag} Holding",
            'email' => strtolower($tag) . '@example.com',
            'plan_id' => $plan->id,
            'require_estimate_approval' => true,
            'require_invoice_approval' => true,
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

    /**
     * THE regression test: a company with no approval_chain_steps rows at all for
     * 'estimate' must go through the exact original flow — an estimator (never had
     * approve_documents) is refused, the owner's single click approves it, and not
     * one approval_steps_log row is ever written.
     */
    public function test_no_chain_configured_behaves_exactly_like_the_original_single_step_flow(): void
    {
        $company = $this->makeCompanyWithApprovalWorkflow('NC');
        $owner = $this->makeUser($company, 'owner', 'NC');
        $estimator = $this->makeUser($company, 'estimator', 'NC');
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client NC']);

        $estimate = Estimate::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'title' => 'Regression Estimate',
            'status' => 'draft',
            'approval_status' => 'pending',
            'approval_requested_by' => $estimator->id,
            'approval_requested_at' => now(),
        ]);

        $this->assertSame(
            0,
            ApprovalStepLog::where('document_type', 'estimate')->where('document_id', $estimate->id)->count(),
            'no chain configured for this company → no step log rows should ever be written'
        );

        $denied = $this->actingAs($estimator)->post('/app/estimates/' . $estimate->id . '/approve');
        $denied->assertRedirect('/app');
        $estimate->refresh();
        $this->assertSame('pending', $estimate->approval_status);

        $response = $this->actingAs($owner)->post('/app/estimates/' . $estimate->id . '/approve');
        $response->assertRedirect('/app/estimates/' . $estimate->id);
        $estimate->refresh();
        $this->assertSame('approved', $estimate->approval_status);
        $this->assertSame($owner->id, $estimate->approved_by);
        $this->assertNotNull($estimate->approved_at);
        $this->assertSame(0, ApprovalStepLog::where('document_type', 'estimate')->where('document_id', $estimate->id)->count());
    }

    /** Same regression property, for reject() on an invoice instead of approve() on an estimate. */
    public function test_no_chain_configured_reject_flow_is_unchanged(): void
    {
        $company = $this->makeCompanyWithApprovalWorkflow('NCR');
        $admin = $this->makeUser($company, 'admin', 'NCR');
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client NCR']);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => 'NCR-001',
            'status' => 'unpaid',
            'total' => 500,
            'approval_status' => 'pending',
            'approval_requested_by' => $admin->id,
            'approval_requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post('/app/invoices/' . $invoice->id . '/reject', ['reason' => 'Numbers look off']);
        $response->assertRedirect('/app/invoices/' . $invoice->id);
        $invoice->refresh();
        $this->assertSame('rejected', $invoice->approval_status);
        $this->assertSame('Numbers look off', $invoice->rejection_reason);
        $this->assertNull($invoice->approved_by);
        $this->assertSame(0, ApprovalStepLog::where('document_type', 'invoice')->where('document_id', $invoice->id)->count());
    }

    /**
     * The main new-feature test: a 2-step invoice chain (accountant → owner).
     * Confirms step ordering, that only the right role can act on the current
     * step, that the parent stays 'pending' after only step 1 approves, and that
     * approved_by/approved_at only get set once the LAST step approves.
     */
    public function test_two_step_chain_requires_accountant_then_owner_in_order(): void
    {
        $company = $this->makeCompanyWithApprovalWorkflow('CH');
        ApprovalChainStep::insert([
            ['company_id' => $company->id, 'document_type' => 'invoice', 'step_order' => 1, 'role_required' => 'accountant', 'label' => null, 'label_ar' => null, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $company->id, 'document_type' => 'invoice', 'step_order' => 2, 'role_required' => 'owner', 'label' => null, 'label_ar' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $accountant = $this->makeUser($company, 'accountant', 'CH');
        $owner = $this->makeUser($company, 'owner', 'CH');
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client CH']);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => 'CH-001',
            'status' => 'unpaid',
            'total' => 1000,
            'approval_status' => 'pending',
            'approval_requested_by' => $accountant->id,
            'approval_requested_at' => now(),
        ]);

        $logs = ApprovalStepLog::where('document_type', 'invoice')->where('document_id', $invoice->id)->orderBy('step_order')->get();
        $this->assertCount(2, $logs, 'one log row per configured step must be created the moment the invoice becomes pending');
        $this->assertSame('pending', $logs[0]->status);
        $this->assertSame('pending', $logs[1]->status);

        // Owner tries to act before it's their turn — refused, parent untouched.
        $ownerTooEarly = $this->actingAs($owner)->post('/app/invoices/' . $invoice->id . '/approve');
        $ownerTooEarly->assertRedirect('/app/invoices/' . $invoice->id);
        $invoice->refresh();
        $this->assertSame('pending', $invoice->approval_status);
        $this->assertNull($invoice->approved_by);

        // Accountant approves step 1.
        $step1 = $this->actingAs($accountant)->post('/app/invoices/' . $invoice->id . '/approve');
        $step1->assertRedirect('/app/invoices/' . $invoice->id);
        $invoice->refresh();
        $this->assertSame('pending', $invoice->approval_status, 'must stay pending — only step 1 of 2 has approved');
        $this->assertNull($invoice->approved_by);
        $this->assertNull($invoice->approved_at);

        $logs = ApprovalStepLog::where('document_type', 'invoice')->where('document_id', $invoice->id)->orderBy('step_order')->get();
        $this->assertSame('approved', $logs[0]->status);
        $this->assertSame($accountant->id, $logs[0]->acted_by);
        $this->assertNotNull($logs[0]->acted_at);
        $this->assertSame('pending', $logs[1]->status, 'step 2 becomes current only after step 1 approves');

        // Accountant can no longer act — it's the owner's step now.
        $accountantTwice = $this->actingAs($accountant)->post('/app/invoices/' . $invoice->id . '/approve');
        $accountantTwice->assertRedirect('/app/invoices/' . $invoice->id);
        $invoice->refresh();
        $this->assertSame('pending', $invoice->approval_status);

        // Owner approves step 2 — only NOW does the parent flip to approved.
        $step2 = $this->actingAs($owner)->post('/app/invoices/' . $invoice->id . '/approve');
        $step2->assertRedirect('/app/invoices/' . $invoice->id);
        $invoice->refresh();
        $this->assertSame('approved', $invoice->approval_status);
        $this->assertSame($owner->id, $invoice->approved_by);
        $this->assertNotNull($invoice->approved_at);

        $logs = ApprovalStepLog::where('document_type', 'invoice')->where('document_id', $invoice->id)->orderBy('step_order')->get();
        $this->assertSame('approved', $logs[1]->status);
        $this->assertSame($owner->id, $logs[1]->acted_by);
    }

    /**
     * The chosen reject-mid-chain behavior: a reject at step 1 immediately sets the
     * parent to 'rejected' without ever reaching step 2, and the still-pending
     * step 2 row is marked 'skipped' (not left dangling as 'pending').
     */
    public function test_reject_at_first_step_kills_the_whole_chain_immediately(): void
    {
        $company = $this->makeCompanyWithApprovalWorkflow('RJ');
        ApprovalChainStep::insert([
            ['company_id' => $company->id, 'document_type' => 'invoice', 'step_order' => 1, 'role_required' => 'accountant', 'label' => null, 'label_ar' => null, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => $company->id, 'document_type' => 'invoice', 'step_order' => 2, 'role_required' => 'owner', 'label' => null, 'label_ar' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $accountant = $this->makeUser($company, 'accountant', 'RJ');
        $owner = $this->makeUser($company, 'owner', 'RJ');
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client RJ']);

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'invoice_number' => 'RJ-001',
            'status' => 'unpaid',
            'total' => 750,
            'approval_status' => 'pending',
            'approval_requested_by' => $accountant->id,
            'approval_requested_at' => now(),
        ]);

        $response = $this->actingAs($accountant)->post('/app/invoices/' . $invoice->id . '/reject', ['reason' => 'Wrong VAT']);
        $response->assertRedirect('/app/invoices/' . $invoice->id);
        $invoice->refresh();
        $this->assertSame('rejected', $invoice->approval_status);
        $this->assertSame('Wrong VAT', $invoice->rejection_reason);
        $this->assertNull($invoice->approved_by);

        $logs = ApprovalStepLog::where('document_type', 'invoice')->where('document_id', $invoice->id)->orderBy('step_order')->get();
        $this->assertSame('rejected', $logs[0]->status);
        $this->assertSame($accountant->id, $logs[0]->acted_by);
        $this->assertSame('skipped', $logs[1]->status, 'step 2 never got a chance to act once step 1 rejected');

        // The document is dead — the owner cannot revive it via step 2.
        $this->actingAs($owner)->post('/app/invoices/' . $invoice->id . '/approve');
        $invoice->refresh();
        $this->assertSame('rejected', $invoice->approval_status);
    }

    /** Company A's configured chain must never apply to, or leak onto, Company B's documents. */
    public function test_companys_chain_configuration_never_affects_another_companys_documents(): void
    {
        $companyA = $this->makeCompanyWithApprovalWorkflow('TA');
        ApprovalChainStep::insert([
            ['company_id' => $companyA->id, 'document_type' => 'estimate', 'step_order' => 1, 'role_required' => 'accountant', 'label' => null, 'label_ar' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $companyB = $this->makeCompanyWithApprovalWorkflow('TB'); // deliberately configures no chain at all

        $ownerA = $this->makeUser($companyA, 'owner', 'TA');
        $ownerB = $this->makeUser($companyB, 'owner', 'TB');
        $clientB = Client::create(['company_id' => $companyB->id, 'name' => 'Client TB']);

        $estimateB = Estimate::create([
            'company_id' => $companyB->id,
            'client_id' => $clientB->id,
            'title' => 'Tenant Isolation Estimate',
            'status' => 'draft',
            'approval_status' => 'pending',
            'approval_requested_by' => $ownerB->id,
            'approval_requested_at' => now(),
        ]);

        $this->assertSame(
            0,
            ApprovalStepLog::where('document_type', 'estimate')->where('document_id', $estimateB->id)->count(),
            "company A's chain for 'estimate' must never apply to company B's estimate"
        );

        $response = $this->actingAs($ownerB)->post('/app/estimates/' . $estimateB->id . '/approve');
        $response->assertRedirect('/app/estimates/' . $estimateB->id);
        $estimateB->refresh();
        $this->assertSame('approved', $estimateB->approval_status);
        $this->assertSame($ownerB->id, $estimateB->approved_by);

        // Company A's owner cannot even reach company B's estimate (tenant-scoped 404).
        $crossTenant = $this->actingAs($ownerA)->post('/app/estimates/' . $estimateB->id . '/approve');
        $crossTenant->assertNotFound();
    }
}
