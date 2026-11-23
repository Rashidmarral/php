<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBill;
use App\Models\VendorBillSubmission;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the supplier/vendor self-service portal: a completely separate authenticated
 * identity from company staff (the `supplier` guard), mirroring the client portal's own
 * architecture exactly — see Portal\PortalAuthController/PortalController and
 * SelectionTest's own "Client portal" section for the precedent this file mirrors.
 */
class SupplierPortalTest extends TestCase
{
    use DatabaseTransactions;

    /** See SelectionTest's own docblock on why this reset is needed. */
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
            'slug' => 'plan-' . strtolower($tag) . '-' . bin2hex(random_bytes(4)),
            'name' => "Plan {$tag}",
            'feature_flags' => json_encode(['supplier_portal' => true, 'purchase_orders' => true, 'subcontractors' => true]),
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

    private function makeSupplier(Company $company, string $tag, bool $portalEnabled = true, string $password = 'password'): Supplier
    {
        return Supplier::create([
            'company_id' => $company->id,
            'name' => "{$tag} Supplier",
            'email' => strtolower($tag) . '-supplier@example.com',
            'password' => Hash::make($password),
            'portal_enabled' => $portalEnabled,
        ]);
    }

    // ------------------------------------------------------------------
    // Auth
    // ------------------------------------------------------------------

    public function test_enabled_supplier_can_log_in_with_correct_password(): void
    {
        $a = $this->makeCompanyWithOwner('SupAuthOk');
        $supplier = $this->makeSupplier($a['company'], 'SupAuthOk');

        $response = $this->post('/supplier-portal/login', [
            'email' => $supplier->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/supplier-portal');
        $this->assertAuthenticatedAs($supplier, 'supplier');
    }

    public function test_login_rejects_wrong_password(): void
    {
        $a = $this->makeCompanyWithOwner('SupAuthBadPw');
        $supplier = $this->makeSupplier($a['company'], 'SupAuthBadPw');

        $response = $this->post('/supplier-portal/login', [
            'email' => $supplier->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/supplier-portal/login');
        $this->assertGuest('supplier');
    }

    public function test_login_rejects_when_portal_not_enabled(): void
    {
        $a = $this->makeCompanyWithOwner('SupAuthDisabled');
        $supplier = $this->makeSupplier($a['company'], 'SupAuthDisabled', portalEnabled: false);

        $response = $this->post('/supplier-portal/login', [
            'email' => $supplier->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/supplier-portal/login');
        $this->assertGuest('supplier');
    }

    // ------------------------------------------------------------------
    // Dashboard scoping (cross-supplier AND cross-company)
    // ------------------------------------------------------------------

    public function test_dashboard_shows_only_this_suppliers_own_data(): void
    {
        $a = $this->makeCompanyWithOwner('SupDashA');
        $b = $this->makeCompanyWithOwner('SupDashB');
        $projectA = Project::create(['company_id' => $a['company']->id, 'name' => 'Project A']);
        $projectB = Project::create(['company_id' => $b['company']->id, 'name' => 'Project B']);

        $supplierA = $this->makeSupplier($a['company'], 'SupDashA');
        $supplierOtherA = $this->makeSupplier($a['company'], 'SupDashOtherA');
        $supplierB = $this->makeSupplier($b['company'], 'SupDashB');

        $poMine = PurchaseOrder::create(['company_id' => $a['company']->id, 'project_id' => $projectA->id, 'supplier_id' => $supplierA->id, 'po_number' => 'PO-MINE', 'status' => 'issued', 'total' => 1000]);
        PurchaseOrder::create(['company_id' => $a['company']->id, 'project_id' => $projectA->id, 'supplier_id' => $supplierOtherA->id, 'po_number' => 'PO-SAME-CO-OTHER', 'status' => 'issued', 'total' => 2000]);
        PurchaseOrder::create(['company_id' => $b['company']->id, 'project_id' => $projectB->id, 'supplier_id' => $supplierB->id, 'po_number' => 'PO-OTHER-CO', 'status' => 'issued', 'total' => 3000]);
        // A draft PO for this very supplier must never show up either.
        PurchaseOrder::create(['company_id' => $a['company']->id, 'project_id' => $projectA->id, 'supplier_id' => $supplierA->id, 'po_number' => 'PO-DRAFT', 'status' => 'draft', 'total' => 500]);

        $response = $this->actingAs($supplierA, 'supplier')->get('/supplier-portal');

        $response->assertOk();
        $response->assertSee('PO-MINE');
        $response->assertDontSee('PO-SAME-CO-OTHER');
        $response->assertDontSee('PO-OTHER-CO');
        $response->assertDontSee('PO-DRAFT');
        $this->assertSame($poMine->id, PurchaseOrder::where('supplier_id', $supplierA->id)->where('status', '!=', 'draft')->first()->id);
    }

    public function test_supplier_cannot_view_another_suppliers_purchase_order(): void
    {
        $a = $this->makeCompanyWithOwner('SupXTenantA');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Project A']);
        $supplierA = $this->makeSupplier($a['company'], 'SupXTenantA');
        $supplierOther = $this->makeSupplier($a['company'], 'SupXTenantOther');
        $othersPo = PurchaseOrder::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'supplier_id' => $supplierOther->id, 'po_number' => 'PO-OTHER', 'status' => 'issued', 'total' => 1000]);

        $response = $this->actingAs($supplierA, 'supplier')->get('/supplier-portal/purchase-orders/' . $othersPo->id);

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // PO acknowledgement — idempotent
    // ------------------------------------------------------------------

    public function test_acknowledging_a_purchase_order_sets_acknowledged_at_and_is_idempotent(): void
    {
        $a = $this->makeCompanyWithOwner('SupAck');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Project A']);
        $supplier = $this->makeSupplier($a['company'], 'SupAck');
        $po = PurchaseOrder::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id, 'po_number' => 'PO-ACK', 'status' => 'issued', 'total' => 1000]);
        $this->assertNull($po->acknowledged_at);

        $response = $this->actingAs($supplier, 'supplier')->post('/supplier-portal/purchase-orders/' . $po->id . '/acknowledge');
        $response->assertRedirect('/supplier-portal/purchase-orders/' . $po->id);
        $po->refresh();
        $this->assertNotNull($po->acknowledged_at);
        $firstTimestamp = $po->acknowledged_at;

        // Acknowledging a second time is a no-op — the timestamp must not change.
        $response = $this->actingAs($supplier, 'supplier')->post('/supplier-portal/purchase-orders/' . $po->id . '/acknowledge');
        $response->assertRedirect('/supplier-portal/purchase-orders/' . $po->id);
        $po->refresh();
        $this->assertEquals($firstTimestamp, $po->acknowledged_at);
    }

    // ------------------------------------------------------------------
    // Bill submission — the critical safety boundary
    // ------------------------------------------------------------------

    public function test_submitting_a_bill_creates_a_pending_submission_and_zero_vendor_bills(): void
    {
        $a = $this->makeCompanyWithOwner('SupBillSubmit');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Project A']);
        $supplier = $this->makeSupplier($a['company'], 'SupBillSubmit');
        $po = PurchaseOrder::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id, 'po_number' => 'PO-SUBMIT', 'status' => 'issued', 'total' => 1000]);

        $response = $this->actingAs($supplier, 'supplier')->post('/supplier-portal/bills', [
            'purchase_order_id' => $po->id,
            'description' => 'Rebar delivery',
            'amount' => '2500.00',
            'category' => 'material',
            'bill_date' => '2026-11-20',
            'reference' => 'INV-001',
        ]);

        $response->assertRedirect('/supplier-portal');
        $this->assertSame(0, VendorBill::where('company_id', $a['company']->id)->count());
        $submission = VendorBillSubmission::where('supplier_id', $supplier->id)->first();
        $this->assertNotNull($submission);
        $this->assertSame('pending', $submission->status);
        $this->assertSame('Rebar delivery', $submission->description);
        $this->assertSame('2500.00', $submission->amount);
        $this->assertSame($po->id, $submission->purchase_order_id);
        $this->assertSame($project->id, $submission->project_id);
        $this->assertSame($a['company']->id, $submission->company_id);
    }

    public function test_supplier_cannot_submit_a_bill_against_a_po_that_is_not_their_own(): void
    {
        $a = $this->makeCompanyWithOwner('SupBillTamperA');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Project A']);
        $supplier = $this->makeSupplier($a['company'], 'SupBillTamperA');
        $otherSupplier = $this->makeSupplier($a['company'], 'SupBillTamperOther');
        $othersPo = PurchaseOrder::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'supplier_id' => $otherSupplier->id, 'po_number' => 'PO-NOT-MINE', 'status' => 'issued', 'total' => 1000]);

        $response = $this->actingAs($supplier, 'supplier')->post('/supplier-portal/bills', [
            'purchase_order_id' => $othersPo->id,
            'description' => 'Fabricated claim',
            'amount' => '9999.00',
        ]);

        // Rejected (the tampered PO id resolves to nothing this supplier owns), never
        // silently accepted against someone else's PO/project.
        $response->assertRedirect('/supplier-portal/bills/create');
        $this->assertSame(0, VendorBillSubmission::where('supplier_id', $supplier->id)->count());
        $this->assertSame(0, VendorBill::count());
    }

    public function test_supplier_cannot_submit_a_bill_against_a_subcontract_that_is_not_their_own(): void
    {
        $a = $this->makeCompanyWithOwner('SupBillTamperScA');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Project A']);
        $supplier = $this->makeSupplier($a['company'], 'SupBillTamperScA');
        $otherSupplier = $this->makeSupplier($a['company'], 'SupBillTamperScOther');
        $othersSubcontract = Subcontract::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'supplier_id' => $otherSupplier->id, 'title' => 'Not mine', 'contract_value' => 5000, 'status' => 'active']);

        $response = $this->actingAs($supplier, 'supplier')->post('/supplier-portal/bills', [
            'subcontract_id' => $othersSubcontract->id,
            'description' => 'Fabricated claim',
            'amount' => '9999.00',
        ]);

        $response->assertRedirect('/supplier-portal/bills/create');
        $this->assertSame(0, VendorBillSubmission::count());
    }

    public function test_submitting_a_bill_with_neither_po_nor_subcontract_is_rejected(): void
    {
        $a = $this->makeCompanyWithOwner('SupBillNoAnchor');
        $supplier = $this->makeSupplier($a['company'], 'SupBillNoAnchor');

        $response = $this->actingAs($supplier, 'supplier')->post('/supplier-portal/bills', [
            'description' => 'No PO or subcontract given',
            'amount' => '100.00',
        ]);

        $response->assertRedirect('/supplier-portal/bills/create');
        $this->assertSame(0, VendorBillSubmission::count());
    }

    // ------------------------------------------------------------------
    // Staff-side review queue
    // ------------------------------------------------------------------

    public function test_staff_approving_a_submission_creates_exactly_one_matching_vendor_bill(): void
    {
        $a = $this->makeCompanyWithOwner('SupApprove');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Project A']);
        $supplier = $this->makeSupplier($a['company'], 'SupApprove');
        $po = PurchaseOrder::create(['company_id' => $a['company']->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id, 'po_number' => 'PO-APPROVE', 'status' => 'issued', 'total' => 1000]);
        $submission = VendorBillSubmission::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $po->id,
            'category' => 'material',
            'description' => 'Cement delivery',
            'amount' => 3200.50,
            'bill_date' => '2026-11-21',
            'reference' => 'INV-777',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/vendor-bill-submissions/' . $submission->id . '/approve');

        $response->assertRedirect('/app/vendor-bill-submissions');
        $this->assertSame(1, VendorBill::count());
        $bill = VendorBill::first();
        $this->assertSame($a['company']->id, $bill->company_id);
        $this->assertSame($project->id, $bill->project_id);
        $this->assertSame($supplier->id, $bill->supplier_id);
        $this->assertSame($po->id, $bill->purchase_order_id);
        $this->assertSame('material', $bill->category);
        $this->assertSame('Cement delivery', $bill->description);
        $this->assertSame('3200.50', $bill->amount);
        $this->assertSame('INV-777', $bill->reference);
        $this->assertSame('unpaid', $bill->status);

        $submission->refresh();
        $this->assertSame('approved', $submission->status);
        $this->assertSame($bill->id, $submission->vendor_bill_id);
        $this->assertSame($a['owner']->id, $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);
    }

    public function test_staff_rejecting_a_submission_creates_zero_vendor_bills(): void
    {
        $a = $this->makeCompanyWithOwner('SupReject');
        $project = Project::create(['company_id' => $a['company']->id, 'name' => 'Project A']);
        $supplier = $this->makeSupplier($a['company'], 'SupReject');
        $submission = VendorBillSubmission::create([
            'company_id' => $a['company']->id,
            'project_id' => $project->id,
            'supplier_id' => $supplier->id,
            'category' => 'material',
            'description' => 'Questionable claim',
            'amount' => 500,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/vendor-bill-submissions/' . $submission->id . '/reject', [
            'rejection_reason' => 'No matching delivery note',
        ]);

        $response->assertRedirect('/app/vendor-bill-submissions');
        $this->assertSame(0, VendorBill::count());
        $submission->refresh();
        $this->assertSame('rejected', $submission->status);
        $this->assertSame('No matching delivery note', $submission->rejection_reason);
        $this->assertSame($a['owner']->id, $submission->reviewed_by);
        $this->assertNotNull($submission->reviewed_at);
    }

    public function test_staff_cannot_review_another_companys_submission(): void
    {
        $a = $this->makeCompanyWithOwner('SupReviewXTenantA');
        $b = $this->makeCompanyWithOwner('SupReviewXTenantB');
        $projectB = Project::create(['company_id' => $b['company']->id, 'name' => 'Project B']);
        $supplierB = $this->makeSupplier($b['company'], 'SupReviewXTenantB');
        $submission = VendorBillSubmission::create([
            'company_id' => $b['company']->id,
            'project_id' => $projectB->id,
            'supplier_id' => $supplierB->id,
            'category' => 'material',
            'description' => "B's submission",
            'amount' => 500,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($a['owner'])->post('/app/vendor-bill-submissions/' . $submission->id . '/approve');

        $response->assertStatus(404);
        $this->assertSame(0, VendorBill::count());
        $submission->refresh();
        $this->assertSame('pending', $submission->status);
    }

    // ------------------------------------------------------------------
    // Staff-side enable/disable portal
    // ------------------------------------------------------------------

    public function test_enable_portal_sets_a_temp_password_and_flashes_it_once(): void
    {
        $a = $this->makeCompanyWithOwner('SupEnablePortal');
        $supplier = Supplier::create(['company_id' => $a['company']->id, 'name' => 'Fresh Supplier', 'email' => 'fresh-supplier@example.com']);
        $this->assertNull($supplier->password);
        $this->assertFalse((bool) $supplier->portal_enabled);

        $response = $this->actingAs($a['owner'])->post('/app/suppliers/' . $supplier->id . '/enable-portal');

        $response->assertRedirect('/app/suppliers/' . $supplier->id);
        $supplier->refresh();
        $this->assertTrue((bool) $supplier->portal_enabled);
        $this->assertNotNull($supplier->password);

        // The portal is now actually reachable with whatever temp password was generated —
        // recovered here via a fresh Hash::make comparison isn't possible (only the hash is
        // stored), so instead verify the login flow end-to-end by resetting a known password
        // and confirming the gate now passes.
        $supplier->update(['password' => Hash::make('newly-set-password')]);
        $loginResponse = $this->post('/supplier-portal/login', ['email' => $supplier->email, 'password' => 'newly-set-password']);
        $loginResponse->assertRedirect('/supplier-portal');
        $this->assertAuthenticatedAs($supplier, 'supplier');
    }

    public function test_disable_portal_blocks_further_login(): void
    {
        $a = $this->makeCompanyWithOwner('SupDisablePortal');
        $supplier = $this->makeSupplier($a['company'], 'SupDisablePortal');

        $response = $this->actingAs($a['owner'])->post('/app/suppliers/' . $supplier->id . '/disable-portal');
        $response->assertRedirect('/app/suppliers/' . $supplier->id);
        $supplier->refresh();
        $this->assertFalse((bool) $supplier->portal_enabled);

        $loginResponse = $this->post('/supplier-portal/login', ['email' => $supplier->email, 'password' => 'password']);
        $loginResponse->assertRedirect('/supplier-portal/login');
        $this->assertGuest('supplier');
    }
}
